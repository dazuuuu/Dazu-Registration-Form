<?php
// Self-healing schema: after a code upload, the first request creates any tables/columns the new code needs
// (from the listed required migrations), so pages never 500 just because nobody ran Admin → System Updates.
// It runs once per migration-set version and then remembers it in app/v1/storage/cache/.

if (!function_exists('dazu_schema_guard')) {
    function dazu_schema_guard(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // Migrations whose tables the current code reads on every portal (add new ones here).
        $files = ['056_team_member_socials.sql', '057_certificates_and_lms.sql', '058_tutor_announcements.sql',
                  '059_certificate_collected_status.sql', '060_missing_columns.sql', '061_tutor_course_builder_and_documents.sql',
                  '062_portal_notifications.sql', '063_tutor_admin_rights.sql'];
        $required = ['certificates', 'course_modules', 'module_contents', 'tutor_announcements',
                     'tutor_course_overviews', 'tutor_course_resources', 'student_module_progress', 'personal_documents',
                     'portal_notifications', 'push_subscriptions'];
        // Columns that must exist, as table.column => text their type must contain (e.g. a newer ENUM option).
        $columnHas = [
            'certificates.status'                 => "'collected'",
            'registrations.secondary_phone'       => 'varchar',
            'tutor_students.timeline_days'        => 'int',
            'student_submissions.module_content_id' => 'int',
            'student_submissions.submission_type' => "'module'",
            'student_submissions.github_url'      => 'varchar',
            'module_contents.answer_mode'         => 'varchar',
            'module_contents.options_json'        => 'text',
            'module_contents.correct_option'      => 'int',
            'module_contents.link_url'            => 'varchar',
            'module_contents.order_index'         => 'int',
            'course_modules.instructions'         => 'text',
            'course_modules.video_url'            => 'varchar',
            'registrations.studies_completed_at'  => 'timestamp',
            'registrations.studies_completed_by'  => 'int',
            'user_profiles.tshirt_size'           => 'varchar',
            'tutor_students.start_date'           => 'date',
            'tutors.can_admin'                    => 'tinyint',
        ];

        $dir = dirname(__DIR__) . '/databases/required_migrations';
        $cacheDir = dirname(__DIR__) . '/storage/cache';
        try {
            // Keyed by database name too, so a marker copied from another machine never matches this one.
            $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $stamp = $cacheDir . '/schema_' . md5($db . '|' . implode('|', $files) . '|' . implode(',', $required) . '|' . json_encode($columnHas)) . '.ok';
            if (is_file($stamp)) {
                return;
            }

            $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $missing = array_diff($required, $have);
            $columnsOk = static function () use ($pdo, $columnHas): bool {
                foreach ($columnHas as $col => $needle) {
                    [$table, $column] = explode('.', $col);
                    $st = $pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                    $st->execute([$table, $column]);
                    if (strpos((string) $st->fetchColumn(), $needle) === false) {
                        return false;
                    }
                }
                return true;
            };
            if ($missing || !$columnsOk()) {
                foreach ($files as $f) {
                    $sql = @file_get_contents($dir . '/' . $f);
                    if ($sql === false) {
                        continue;
                    }
                    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                        try {
                            $pdo->exec($stmt);
                        } catch (PDOException $e) {
                            // "already exists" / "duplicate column" etc. — expected on partly-updated databases
                        }
                    }
                }
                $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                if (array_diff($required, $have) || !$columnsOk()) {
                    error_log('[schema_guard] still missing tables/columns: ' . implode(', ', array_diff($required, $have)));
                    return; // try again next request; do not write the stamp
                }
            }
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            @file_put_contents($stamp, date('c'));
        } catch (Throwable $e) {
            error_log('[schema_guard] ' . $e->getMessage());
        }
    }
}
