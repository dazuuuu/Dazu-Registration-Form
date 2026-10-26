<?php
/**
 * Intern Portal — everything except tutor-assisted teaching (that lives in InternTeachingService.php and is private
 * to the tutor and the intern).
 *
 * Who sees what
 *  - An intern sees only their own rows: every function that reads intern data takes the intern id from intern_current().
 *  - Supervisors are admin accounts (role_id 2). "My interns" lists only the interns they supervise; the HR pages
 *    (admin/interns) list every intern because admins manage the whole programme.
 *  - Files are kept in private storage (app/v1/storage/interns/...) and only served by intern/file.php after a check.
 *
 * Tables: migration 075_intern_portal.sql.
 */
require_once __DIR__ . '/PortalNotifications.php';

const INTERN_TASK_STATUSES = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'submitted' => 'Submitted', 'revision' => 'Needs Revision', 'completed' => 'Completed'];
const INTERN_PRIORITIES = ['normal' => 'Normal', 'medium' => 'Medium', 'high' => 'High'];
const INTERN_ATT_STATUSES = ['on_time' => 'On time', 'late' => 'Late', 'early_out' => 'Early out', 'absent' => 'Absent'];
const INTERN_DOC_CATEGORIES = ['agreement' => 'Agreement', 'id' => 'ID', 'cv' => 'CV', 'certificate' => 'Certificate', 'report' => 'Report',
                               'form' => 'Form', 'completion_letter' => 'Completion letter', 'other' => 'Other'];
const INTERN_EVAL_KINDS = ['self' => 'Self-assessment', 'skills' => 'Skills assessment', 'midterm' => 'Mid-term evaluation', 'final' => 'Final evaluation'];
const INTERN_EVAL_CRITERIA = ['professionalism' => 'Professionalism', 'technical' => 'Technical Skills', 'communication' => 'Communication', 'teamwork' => 'Teamwork'];
const INTERN_UPLOAD_TYPES = [
    'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    'text/plain' => 'txt', 'application/zip' => 'zip', 'video/mp4' => 'mp4',
];
const INTERN_MAX_UPLOAD = 20 * 1024 * 1024;

if (!function_exists('in_e')) {
    function in_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    // ── Settings & audit ─────────────────────────────────────────────────────────────────────────────────
    function in_settings(PDO $db): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = ['attendance_enabled' => '1', 'work_start' => '08:00', 'work_end' => '17:00', 'late_after_minutes' => '10', 'work_days' => '1,2,3,4,5',
                      'save_location' => '0', 'daily_hours' => '9', 'weight_attendance' => '25', 'weight_tasks' => '35', 'weight_training' => '20', 'weight_assessments' => '20'];
            try {
                foreach ($db->query('SELECT setting_key, setting_value FROM intern_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $cache[$k] = $v;
            } catch (Throwable $e) {
            }
        }
        return $cache;
    }

    function in_setting_save(PDO $db, array $values): void
    {
        $st = $db->prepare('INSERT INTO intern_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($values as $k => $v) $st->execute([$k, (string) $v]);
    }

    function in_audit(PDO $db, ?int $internId, string $action, string $detail = ''): void
    {
        try {
            $db->prepare('INSERT INTO intern_audit_log (actor_id, intern_id, action, detail, ip) VALUES (?, ?, ?, ?, ?)')
               ->execute([(int) ($_SESSION['user_id'] ?? 0) ?: null, $internId, $action, mb_substr($detail, 0, 500) ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) {
            error_log('[intern audit] ' . $e->getMessage());
        }
    }

    function in_is_staff(): bool
    {
        return !empty($_SESSION['logged_in']) && (int) ($_SESSION['role_id'] ?? 99) <= 2;
    }

    // ── Interns ─────────────────────────────────────────────────────────────────────────────────────────
    function in_select(): string
    {
        return "SELECT i.*, u.email, u.username, u.is_active AS account_active,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name,
                       up.first_name, d.name AS department,
                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(sp.first_name,''), ' ', COALESCE(sp.last_name,''))), ''), su.username) AS supervisor_name,
                       su.email AS supervisor_email, sp.phone AS supervisor_phone
                FROM interns i
                JOIN users u ON u.id = i.user_id
                LEFT JOIN user_profiles up ON up.user_id = u.id
                LEFT JOIN intern_departments d ON d.id = i.department_id
                LEFT JOIN users su ON su.id = i.supervisor_id
                LEFT JOIN user_profiles sp ON sp.user_id = su.id";
    }

    /** The intern record of the signed-in intern, or null. */
    function in_current(PDO $db): ?array
    {
        static $me = false;
        if ($me === false) {
            $me = null;
            try {
                $st = $db->prepare(in_select() . ' WHERE i.user_id = ? LIMIT 1');
                $st->execute([(int) ($_SESSION['user_id'] ?? 0)]);
                $me = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
            }
        }
        return $me;
    }

    function in_get(PDO $db, int $id): ?array
    {
        $st = $db->prepare(in_select() . ' WHERE i.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** HR list (all interns), or only the interns this supervisor looks after. */
    function in_list(PDO $db, ?int $supervisorId = null, string $q = '', string $status = ''): array
    {
        $where = ['1=1'];
        $args = [];
        if ($supervisorId) { $where[] = 'i.supervisor_id = ?'; $args[] = $supervisorId; }
        if ($status !== '') { $where[] = 'i.status = ?'; $args[] = $status; }
        if ($q !== '') {
            $where[] = "(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,'')) LIKE ? OR u.email LIKE ? OR i.institution LIKE ? OR d.name LIKE ?)";
            array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        try {
            $st = $db->prepare(in_select() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.status = \'active\' DESC, full_name');
            $st->execute($args);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function in_departments(PDO $db, bool $activeOnly = false): array
    {
        try {
            return $db->query('SELECT d.*, (SELECT COUNT(*) FROM interns i WHERE i.department_id = d.id) AS interns FROM intern_departments d'
                              . ($activeOnly ? ' WHERE d.is_active = 1' : '') . ' ORDER BY d.name')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Admin accounts that can supervise interns. */
    function in_supervisors(PDO $db): array
    {
        return $db->query("SELECT u.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name, u.email
                           FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id
                           WHERE u.role_id = 2 AND u.is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    function in_role_id(PDO $db): ?int
    {
        $id = $db->query("SELECT id FROM roles WHERE role_name = 'intern' LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Fields an admin manages on an intern record. */
    function in_clean_fields(array $in): array
    {
        $date = fn($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        return [
            'department_id' => (int) ($in['department_id'] ?? 0) ?: null,
            'supervisor_id' => (int) ($in['supervisor_id'] ?? 0) ?: null,
            'institution' => mb_substr(trim((string) ($in['institution'] ?? '')), 0, 150) ?: null,
            'course' => mb_substr(trim((string) ($in['course'] ?? '')), 0, 150) ?: null,
            'year_of_study' => mb_substr(trim((string) ($in['year_of_study'] ?? '')), 0, 20) ?: null,
            'student_id_no' => mb_substr(trim((string) ($in['student_id_no'] ?? '')), 0, 50) ?: null,
            'phone' => mb_substr(trim((string) ($in['phone'] ?? '')), 0, 30) ?: null,
            'start_date' => $date($in['start_date'] ?? null),
            'end_date' => $date($in['end_date'] ?? null),
        ];
    }

    /** Create the account (temporary password emailed) and the intern record. */
    function in_create(PDO $db, array $in): array
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['full_name'] ?? '')));
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        if ($name === '') return ['ok' => false, 'error' => 'Enter the intern\'s full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid email address.'];
        if (!($roleId = in_role_id($db))) return ['ok' => false, 'error' => 'The intern role is missing. Run System Updates (migration 075).'];
        $st = $db->prepare('SELECT 1 FROM users WHERE LOWER(email) = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) return ['ok' => false, 'error' => 'A user with this email already exists.'];
        $f = in_clean_fields($in);
        if ($f['start_date'] && $f['end_date'] && $f['end_date'] < $f['start_date']) return ['ok' => false, 'error' => 'The end date is before the start date.'];

        $base = preg_replace('/[^a-z0-9]+/', '.', strtolower(strstr($email, '@', true))) ?: 'intern';
        $username = 'INT-' . $base;
        for ($n = 2; ; $n++) {
            $st = $db->prepare('SELECT 1 FROM users WHERE username = ?');
            $st->execute([$username]);
            if (!$st->fetchColumn()) break;
            $username = 'INT-' . $base . $n;
        }
        $password = bin2hex(random_bytes(5));
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password)
                          VALUES (?, ?, ?, ?, 1, 1, 'active', 1)")->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $roleId]);
            $uid = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)')->execute([$uid, $first, $last, $f['phone']]);
            $db->prepare('INSERT INTO interns (user_id, department_id, supervisor_id, institution, course, year_of_study, student_id_no, phone, start_date, end_date)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$uid, $f['department_id'], $f['supervisor_id'], $f['institution'], $f['course'], $f['year_of_study'], $f['student_id_no'], $f['phone'], $f['start_date'], $f['end_date']]);
            $internId = (int) $db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        in_audit($db, $internId, 'intern_created', $email);
        if ($f['supervisor_id']) {
            PortalNotifications::notify($db, $f['supervisor_id'], 'intern', 'New intern: ' . $name, 'You are their supervisor.', 'admin/interns/supervise.php?id=' . $internId);
        }
        $login = PortalNotifications::absolute('auth/login.php');
        $sent = in_mail($email, $name, 'Your Dazu intern account',
            'An intern account has been created for you at Dazu Training Institute. Sign in at <a href="' . in_e($login) . '">' . in_e($login) . '</a>'
            . '<br>Email: <strong>' . in_e($email) . '</strong><br>Temporary password: <strong>' . in_e($password) . '</strong>'
            . '<br>You will choose your own password the first time you sign in.');
        return ['ok' => true, 'id' => $internId, 'emailed' => $sent, 'password' => $password];
    }

    function in_update(PDO $db, int $id, array $in): array
    {
        $old = in_get($db, $id);
        if (!$old) return ['ok' => false, 'error' => 'Intern not found.'];
        // Fields left out of the form keep their current value.
        $in += array_intersect_key($old, array_flip(['department_id', 'supervisor_id', 'institution', 'course', 'year_of_study', 'student_id_no',
                                                     'phone', 'start_date', 'end_date', 'status']));
        $f = in_clean_fields($in);
        if ($f['start_date'] && $f['end_date'] && $f['end_date'] < $f['start_date']) return ['ok' => false, 'error' => 'The end date is before the start date.'];
        $status = in_array($in['status'] ?? '', ['active', 'completed', 'suspended', 'withdrawn'], true) ? $in['status'] : 'active';
        $db->prepare('UPDATE interns SET department_id = ?, supervisor_id = ?, institution = ?, course = ?, year_of_study = ?, student_id_no = ?, phone = ?,
                      start_date = ?, end_date = ?, status = ?, skills = ?, final_eval_open = ? WHERE id = ?')
           ->execute([$f['department_id'], $f['supervisor_id'], $f['institution'], $f['course'], $f['year_of_study'], $f['student_id_no'], $f['phone'],
                      $f['start_date'], $f['end_date'], $status, mb_substr(trim((string) ($in['skills'] ?? $old['skills'])), 0, 2000) ?: null,
                      !empty($in['final_eval_open']) ? 1 : 0, $id]);
        $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$status === 'suspended' || $status === 'withdrawn' ? 0 : 1, (int) $old['user_id']]);
        in_audit($db, $id, 'intern_updated', 'status ' . $status);
        if ($f['supervisor_id'] && (int) $f['supervisor_id'] !== (int) $old['supervisor_id']) {
            PortalNotifications::notify($db, (int) $f['supervisor_id'], 'intern', 'New intern: ' . $old['full_name'], 'You are their supervisor.', 'admin/interns/supervise.php?id=' . $id);
        }
        if (!empty($in['final_eval_open']) && empty($old['final_eval_open'])) in_audit($db, $id, 'final_eval_opened');
        // When the internship ends or the account is turned off, the intern's teaching access closes on its own
        // (it_sync() runs before every teaching read), so admin code never touches the teaching tables here.
        return ['ok' => true];
    }

    /** First day, last day, week n of N, days left. */
    function in_period(array $intern): array
    {
        $start = $intern['start_date'] ? strtotime($intern['start_date']) : null;
        $end = $intern['end_date'] ? strtotime($intern['end_date']) : null;
        $today = strtotime(date('Y-m-d'));
        $weeks = $start && $end ? max(1, (int) ceil(($end - $start + 86400) / (7 * 86400))) : null;
        $week = $start ? max(1, min($weeks ?? PHP_INT_MAX, (int) floor(($today - $start) / (7 * 86400)) + 1)) : null;
        return ['start' => $start, 'end' => $end, 'weeks' => $weeks, 'week' => $start && $today >= $start ? $week : null,
                'days_left' => $end ? max(0, (int) round(($end - $today) / 86400)) : null];
    }

    // ── Mail & notifications ─────────────────────────────────────────────────────────────────────────────
    function in_mail(string $to, string $name, string $subject, string $html): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2937;';
        try {
            require_once __DIR__ . '/Mailer.php';
            $r = (new Mailer(require __DIR__ . '/../config/mail.php'))->send($to, $name, $subject . ' — Dazu Training Institute',
                '<p style="' . $p . '">Hi ' . in_e(explode(' ', trim($name))[0]) . ',</p><p style="' . $p . '">' . $html . '</p><p style="' . $p . '">Dazu Training Institute</p>');
            return !empty($r['ok']) && empty($r['skipped']);
        } catch (Throwable $e) {
            error_log('[intern mail] ' . $e->getMessage());
            return false;
        }
    }

    /** Notify the intern (bell + push) and email them. */
    function in_notify_intern(PDO $db, array $intern, string $type, string $title, string $body, string $path, bool $email = true): void
    {
        PortalNotifications::notify($db, (int) $intern['user_id'], $type, $title, $body, $path);
        if ($email) in_mail((string) $intern['email'], (string) $intern['full_name'], $title, in_e($body) . '<br><a href="' . in_e(PortalNotifications::absolute($path)) . '">Open the intern portal</a>');
    }

    /** "Deadline tomorrow" reminders; runs at most once per request, cheap when there is nothing to send. */
    function in_deadline_reminders(PDO $db): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $rows = $db->query("SELECT t.id, t.title, t.intern_id FROM intern_tasks t
                                WHERE t.deadline = CURDATE() + INTERVAL 1 DAY AND t.status IN ('todo','in_progress','revision') AND t.reminded_at IS NULL LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $t) {
                $db->prepare('UPDATE intern_tasks SET reminded_at = NOW() WHERE id = ?')->execute([(int) $t['id']]);
                if ($in = in_get($db, (int) $t['intern_id'])) {
                    in_notify_intern($db, $in, 'intern_deadline', 'Deadline tomorrow: ' . $t['title'], 'Submit your work before the end of tomorrow.', 'intern/tasks/view.php?id=' . (int) $t['id']);
                }
            }
        } catch (Throwable $e) {
        }
    }

    // ── Private files ────────────────────────────────────────────────────────────────────────────────────
    /** Save an upload under storage/interns/<sub>/. @return array{ok:bool, path?:string, name?:string, mime?:string, size?:int, error?:string} */
    function in_store_file(array $file, string $sub, array $allowed = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok' => false, 'error' => 'Choose a file.', 'none' => true];
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > INTERN_MAX_UPLOAD) return ['ok' => false, 'error' => 'The file did not upload or is larger than 20 MB.'];
        if (!is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        $types = $allowed ?: INTERN_UPLOAD_TYPES;
        if (!isset($types[$mime])) return ['ok' => false, 'error' => 'This file type is not allowed. Use PDF, Word, Excel, PowerPoint, an image, text, ZIP or MP4.'];
        $dir = storage_path('interns/' . trim($sub, '/'));
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the storage folder.'];
        $ht = storage_path('interns/.htaccess');
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
        $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'Could not save the file.'];
        $orig = preg_replace('/[^\w .()-]+/u', '_', basename((string) $file['name'])) ?: 'file.' . $types[$mime];
        return ['ok' => true, 'path' => 'interns/' . trim($sub, '/') . '/' . $name, 'name' => mb_substr($orig, 0, 200), 'mime' => $mime, 'size' => (int) $file['size']];
    }

    /** Absolute path of a stored intern file, only inside storage/interns. */
    function in_file_abs(?string $rel): ?string
    {
        if (!$rel || !str_starts_with($rel, 'interns/')) return null;
        $base = realpath(storage_path('interns'));
        $abs = realpath(storage_path($rel));
        return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs) ? $abs : null;
    }

    function in_file_delete(?string $rel): void
    {
        if ($abs = in_file_abs($rel)) @unlink($abs);
    }

    /** Link to a protected file: kind = doc | task | learn | photo | teach. */
    function in_file_url(string $kind, int $id): string
    {
        return url('intern/file.php?k=' . $kind . '&id=' . $id);
    }

    // ── Tasks ─────────────────────────────────────────────────────────────────────────────────────────────
    function in_tasks(PDO $db, int $internId): array
    {
        try {
            $st = $db->prepare("SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS assigned_by_name,
                                       (SELECT c.body FROM intern_task_comments c WHERE c.task_id = t.id AND c.kind = 'revision' ORDER BY c.id DESC LIMIT 1) AS revision_comment
                                FROM intern_tasks t LEFT JOIN users u ON u.id = t.assigned_by LEFT JOIN user_profiles p ON p.user_id = u.id
                                WHERE t.intern_id = ? ORDER BY t.status = 'completed', t.deadline IS NULL, t.deadline, t.id DESC");
            $st->execute([$internId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function in_task(PDO $db, int $taskId, ?int $internId = null): ?array
    {
        $st = $db->prepare("SELECT t.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS assigned_by_name
                            FROM intern_tasks t LEFT JOIN users u ON u.id = t.assigned_by LEFT JOIN user_profiles p ON p.user_id = u.id
                            WHERE t.id = ?" . ($internId ? ' AND t.intern_id = ' . (int) $internId : ''));
        $st->execute([$taskId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function in_task_files(PDO $db, int $taskId): array
    {
        $st = $db->prepare('SELECT * FROM intern_task_files WHERE task_id = ? ORDER BY id');
        $st->execute([$taskId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function in_task_thread(PDO $db, int $taskId): array
    {
        $st = $db->prepare("SELECT x.* FROM (
                SELECT 'submission' AS kind, s.id, s.created_at, s.intern_id AS who, s.notes AS body, s.link FROM intern_task_submissions s WHERE s.task_id = ?
                UNION ALL
                SELECT c.kind, c.id, c.created_at, c.user_id AS who, c.body, NULL FROM intern_task_comments c WHERE c.task_id = ?) x ORDER BY x.created_at, x.id");
        $st->execute([$taskId, $taskId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function in_task_create(PDO $db, int $internId, array $in, array $files): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) return ['ok' => false, 'error' => 'Enter a task title (up to 200 characters).'];
        $priority = isset(INTERN_PRIORITIES[$in['priority'] ?? '']) ? $in['priority'] : 'normal';
        $deadline = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['deadline'] ?? '')) ? $in['deadline'] : null;
        $intern = in_get($db, $internId);
        if (!$intern) return ['ok' => false, 'error' => 'Intern not found.'];
        $db->prepare('INSERT INTO intern_tasks (intern_id, title, description, priority, deadline, assigned_by) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$internId, $title, trim((string) ($in['description'] ?? '')) ?: null, $priority, $deadline, (int) $_SESSION['user_id']]);
        $taskId = (int) $db->lastInsertId();
        in_attach_files($db, $taskId, null, $files, 'tasks/' . $taskId);
        in_audit($db, $internId, 'task_created', $title);
        in_notify_intern($db, $intern, 'intern_task', 'New task: ' . $title, ($deadline ? 'Due ' . date('j M', strtotime($deadline)) . '. ' : '') . 'Open My Tasks to start it.', 'intern/tasks/view.php?id=' . $taskId);
        return ['ok' => true, 'id' => $taskId];
    }

    /** Save every file of a multiple <input type=file>. */
    function in_attach_files(PDO $db, int $taskId, ?int $submissionId, array $files, string $sub): array
    {
        $errors = [];
        if (empty($files['name']) || !is_array($files['name'])) return $errors;
        foreach (array_keys($files['name']) as $i) {
            $one = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            $r = in_store_file($one, $sub);
            if (!$r['ok']) { if (empty($r['none'])) $errors[] = $one['name'] . ': ' . $r['error']; continue; }
            $db->prepare('INSERT INTO intern_task_files (task_id, submission_id, path, name, mime, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([$taskId, $submissionId, $r['path'], $r['name'], $r['mime'], (int) $_SESSION['user_id']]);
        }
        return $errors;
    }

    /** Intern: start a task. */
    function in_task_start(PDO $db, array $intern, int $taskId): bool
    {
        $st = $db->prepare("UPDATE intern_tasks SET status = 'in_progress' WHERE id = ? AND intern_id = ? AND status = 'todo'");
        $st->execute([$taskId, (int) $intern['id']]);
        if ($st->rowCount()) in_audit($db, (int) $intern['id'], 'task_started', '#' . $taskId);
        return (bool) $st->rowCount();
    }

    /** Intern: submit (or resubmit) work — notes, a link and/or files. */
    function in_task_submit(PDO $db, array $intern, int $taskId, array $in, array $files): array
    {
        $task = in_task($db, $taskId, (int) $intern['id']);
        if (!$task || !in_array($task['status'], ['todo', 'in_progress', 'revision'], true)) return ['ok' => false, 'error' => 'This task cannot be submitted now.'];
        $notes = trim((string) ($in['notes'] ?? ''));
        $link = trim((string) ($in['link'] ?? ''));
        if ($link !== '' && !preg_match('#^https?://#i', $link)) return ['ok' => false, 'error' => 'The link must start with http:// or https://'];
        $hasFile = !empty($files['name']) && is_array($files['name']) && array_filter($files['name']);
        if ($notes === '' && $link === '' && !$hasFile) return ['ok' => false, 'error' => 'Upload a file, paste a link or write notes.'];
        $db->prepare('INSERT INTO intern_task_submissions (task_id, intern_id, notes, link) VALUES (?, ?, ?, ?)')->execute([$taskId, (int) $intern['id'], $notes ?: null, $link ?: null]);
        $subId = (int) $db->lastInsertId();
        $errors = in_attach_files($db, $taskId, $subId, $files, 'tasks/' . $taskId);
        $db->prepare("UPDATE intern_tasks SET status = 'submitted' WHERE id = ?")->execute([$taskId]);
        in_audit($db, (int) $intern['id'], $task['status'] === 'revision' ? 'task_resubmitted' : 'task_submitted', $task['title']);
        if ($intern['supervisor_id']) {
            PortalNotifications::notify($db, (int) $intern['supervisor_id'], 'intern', $intern['full_name'] . ' submitted: ' . $task['title'], 'Review it and approve or send it back.', 'admin/interns/supervise.php?id=' . (int) $intern['id'] . '&task=' . $taskId);
        }
        return ['ok' => true, 'warnings' => $errors];
    }

    /** Supervisor: approve, send back with a comment, or just comment. */
    function in_task_review(PDO $db, int $taskId, string $decision, string $comment): array
    {
        $task = in_task($db, $taskId);
        if (!$task) return ['ok' => false, 'error' => 'Task not found.'];
        $intern = in_get($db, (int) $task['intern_id']);
        $comment = trim($comment);
        if ($decision === 'revision' && $comment === '') return ['ok' => false, 'error' => 'Write what needs to change.'];
        if ($decision === 'approve') {
            $db->prepare("UPDATE intern_tasks SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$taskId]);
            $kind = 'approved';
        } elseif ($decision === 'revision') {
            $db->prepare("UPDATE intern_tasks SET status = 'revision' WHERE id = ?")->execute([$taskId]);
            $kind = 'revision';
        } else {
            if ($comment === '') return ['ok' => false, 'error' => 'Write a comment.'];
            $kind = 'comment';
        }
        if ($comment !== '' || $kind === 'approved') {
            $db->prepare('INSERT INTO intern_task_comments (task_id, user_id, kind, body) VALUES (?, ?, ?, ?)')->execute([$taskId, (int) $_SESSION['user_id'], $kind, $comment !== '' ? $comment : 'Approved.']);
        }
        in_audit($db, (int) $task['intern_id'], 'task_' . $kind, $task['title']);
        if ($intern) {
            $msg = ['approved' => ['Task approved: ', 'Well done — it is now completed.'], 'revision' => ['Needs revision: ', $comment], 'comment' => ['New comment on ', $comment]][$kind];
            in_notify_intern($db, $intern, 'intern_task', $msg[0] . $task['title'], $msg[1], 'intern/tasks/view.php?id=' . $taskId, $kind !== 'comment');
        }
        return ['ok' => true];
    }

    // ── Attendance ───────────────────────────────────────────────────────────────────────────────────────
    function in_work_days(PDO $db): array
    {
        return array_map('intval', array_filter(explode(',', in_settings($db)['work_days'])));
    }

    function in_today(PDO $db, int $internId): ?array
    {
        $st = $db->prepare('SELECT * FROM intern_attendance WHERE intern_id = ? AND work_date = CURDATE()');
        $st->execute([$internId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Clock in or out. Rate limited (one action per 30 seconds); IP always saved, location when admin turned it on.
     * $at lets the offline queue send the time the button was really pressed (only accepted for today, within 12 hours).
     */
    function in_clock(PDO $db, array $intern, string $action, ?float $lat = null, ?float $lng = null, ?int $at = null): array
    {
        $s = in_settings($db);
        if ($s['attendance_enabled'] !== '1') return ['ok' => false, 'error' => 'Attendance is turned off.'];
        if ($intern['status'] !== 'active') return ['ok' => false, 'error' => 'Your internship is not active.'];
        $now = time();
        $when = ($at && $at <= $now && $at > $now - 12 * 3600 && date('Y-m-d', $at) === date('Y-m-d')) ? $at : $now;
        $key = 'in_clock_' . (int) $intern['id'];
        if (!empty($_SESSION[$key]) && $now - (int) $_SESSION[$key] < 30) return ['ok' => false, 'error' => 'Please wait a few seconds and try again.'];
        $_SESSION[$key] = $now;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $row = in_today($db, (int) $intern['id']);
        $date = date('Y-m-d', $when);
        if ($action === 'in') {
            if ($row && $row['clock_in']) return ['ok' => false, 'error' => 'You already clocked in today.'];
            $late = $when > strtotime($date . ' ' . $s['work_start']) + 60 * (int) $s['late_after_minutes'];
            $useLoc = $s['save_location'] === '1' && $lat !== null && $lng !== null;
            $db->prepare('INSERT INTO intern_attendance (intern_id, work_date, clock_in, status, in_ip, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), status = VALUES(status), in_ip = VALUES(in_ip), latitude = VALUES(latitude), longitude = VALUES(longitude)')
               ->execute([(int) $intern['id'], $date, date('Y-m-d H:i:s', $when), $late ? 'late' : 'on_time', $ip, $useLoc ? $lat : null, $useLoc ? $lng : null]);
            in_audit($db, (int) $intern['id'], 'clock_in', date('H:i', $when) . ($late ? ' late' : ''));
            return ['ok' => true, 'message' => 'Clocked in at ' . date('g:i A', $when) . ($late ? ' — late' : ' — on time') . '.'];
        }
        if (!$row || !$row['clock_in']) return ['ok' => false, 'error' => 'Clock in first.'];
        if ($row['clock_out']) return ['ok' => false, 'error' => 'You already clocked out today.'];
        $early = $when < strtotime($date . ' ' . $s['work_end']);
        $status = $row['status'] === 'late' ? 'late' : ($early ? 'early_out' : $row['status']);
        $db->prepare('UPDATE intern_attendance SET clock_out = ?, out_ip = ?, status = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', $when), $ip, $status, (int) $row['id']]);
        in_audit($db, (int) $intern['id'], 'clock_out', date('H:i', $when));
        return ['ok' => true, 'message' => 'Clocked out at ' . date('g:i A', $when) . '.'];
    }

    function in_attendance_rows(PDO $db, int $internId, ?string $from = null, ?string $to = null): array
    {
        $st = $db->prepare('SELECT * FROM intern_attendance WHERE intern_id = ? AND work_date BETWEEN ? AND ? ORDER BY work_date DESC');
        $st->execute([$internId, $from ?? '2000-01-01', $to ?? date('Y-m-d')]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function in_minutes(array $row, ?int $now = null): int
    {
        if (!$row['clock_in']) return 0;
        $end = $row['clock_out'] ? strtotime($row['clock_out']) : ($row['work_date'] === date('Y-m-d') ? ($now ?? time()) : strtotime($row['clock_in']));
        return max(0, (int) floor(($end - strtotime($row['clock_in'])) / 60));
    }

    function in_hm(int $minutes): string
    {
        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    /** Rate %, present/late/absent counts and hours this week, from the start date to today (work days only). */
    function in_attendance_stats(PDO $db, array $intern): array
    {
        $workDays = in_work_days($db);
        $start = $intern['start_date'] ?: date('Y-m-d', strtotime('-30 days'));
        $end = min(date('Y-m-d'), $intern['end_date'] ?: date('Y-m-d'));
        $rows = [];
        foreach (in_attendance_rows($db, (int) $intern['id'], $start, $end) as $r) $rows[$r['work_date']] = $r;
        $days = $present = $late = $absent = $excused = 0;
        for ($d = strtotime($start); $d <= strtotime($end); $d += 86400) {
            if (!in_array((int) date('N', $d), $workDays, true)) continue;
            $key = date('Y-m-d', $d);
            $r = $rows[$key] ?? null;
            if ($key === date('Y-m-d') && !$r) continue;   // today is not over yet
            $days++;
            if ($r && $r['clock_in']) {
                $present++;
                if ($r['status'] === 'late') $late++;
            } elseif ($r && $r['excused']) {
                $excused++;
                $days--;   // an excused absence does not count against the intern
            } else {
                $absent++;
            }
        }
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekMin = 0;
        foreach ($rows as $k => $r) if ($k >= $weekStart) $weekMin += in_minutes($r);
        $monthLate = count(array_filter($rows, fn($r) => $r['status'] === 'late' && substr($r['work_date'], 0, 7) === date('Y-m')));
        $target = count(array_filter($workDays, fn($n) => $n <= 7)) * (float) in_settings($db)['daily_hours'];
        return ['days' => $days, 'present' => $present, 'late' => $late, 'late_month' => $monthLate, 'absent' => $absent + $excused, 'excused' => $excused,
                'rate' => $days ? (int) round($present / $days * 100) : 100, 'week_minutes' => $weekMin, 'week_target' => $target, 'rows' => $rows];
    }

    /** Supervisor/admin correction: set times, status or excuse an absence. */
    function in_attendance_correct(PDO $db, int $internId, string $date, array $in): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['ok' => false, 'error' => 'Choose a date.'];
        $t = fn($v) => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? $date . ' ' . $v . ':00' : null;
        $status = isset(INTERN_ATT_STATUSES[$in['status'] ?? '']) ? $in['status'] : 'on_time';
        $db->prepare('INSERT INTO intern_attendance (intern_id, work_date, clock_in, clock_out, status, excused, note, corrected_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), clock_out = VALUES(clock_out), status = VALUES(status), excused = VALUES(excused), note = VALUES(note), corrected_by = VALUES(corrected_by)')
           ->execute([$internId, $date, $t($in['clock_in'] ?? ''), $t($in['clock_out'] ?? ''), $status, !empty($in['excused']) ? 1 : 0,
                      mb_substr(trim((string) ($in['note'] ?? '')), 0, 255) ?: null, (int) $_SESSION['user_id']]);
        in_audit($db, $internId, 'attendance_corrected', $date . ' ' . $status);
        return ['ok' => true];
    }

    // ── Learning ─────────────────────────────────────────────────────────────────────────────────────────
    function in_modules(PDO $db, int $internId): array
    {
        try {
            $st = $db->prepare('SELECT m.* FROM intern_learning_modules m JOIN intern_learning_assignments a ON a.module_id = m.id WHERE a.intern_id = ? ORDER BY a.assigned_at, m.id');
            $st->execute([$internId]);
            $mods = $st->fetchAll(PDO::FETCH_ASSOC);
            $items = $db->prepare('SELECT it.*, p.done_at, p.score FROM intern_learning_items it
                                   LEFT JOIN intern_learning_progress p ON p.item_id = it.id AND p.intern_id = ? WHERE it.module_id = ? ORDER BY it.position, it.id');
            foreach ($mods as &$m) {
                $items->execute([$internId, (int) $m['id']]);
                $m['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
                $done = count(array_filter($m['items'], fn($i) => $i['done_at']));
                $m['done'] = $done;
                $m['percent'] = $m['items'] ? (int) round($done / count($m['items']) * 100) : 0;
            }
            return $mods;
        } catch (Throwable $e) {
            return [];
        }
    }

    function in_training_percent(array $modules): int
    {
        $total = array_sum(array_map(fn($m) => count($m['items']), $modules));
        $done = array_sum(array_column($modules, 'done'));
        return $total ? (int) round($done / $total * 100) : 0;
    }

    /** Intern completes an item; a quiz is marked and passes at 50%. */
    function in_learning_complete(PDO $db, array $intern, int $itemId, array $in): array
    {
        $st = $db->prepare('SELECT it.* FROM intern_learning_items it JOIN intern_learning_assignments a ON a.module_id = it.module_id AND a.intern_id = ? WHERE it.id = ?');
        $st->execute([(int) $intern['id'], $itemId]);
        $item = $st->fetch(PDO::FETCH_ASSOC);
        if (!$item) return ['ok' => false, 'error' => 'Learning item not found.'];
        $score = null;
        $answer = null;
        if ($item['type'] === 'quiz') {
            $qs = json_decode((string) $item['quiz_json'], true) ?: [];
            $right = 0;
            foreach ($qs as $i => $q) if ((string) ($in['q'][$i] ?? '') === (string) ($q['answer'] ?? '')) $right++;
            $score = $qs ? (int) round($right / count($qs) * 100) : 100;
            if ($score < 50) return ['ok' => false, 'error' => 'You scored ' . $score . '%. You need 50% to pass — try again.'];
        } elseif ($item['type'] === 'assignment') {
            $answer = trim((string) ($in['answer'] ?? ''));
            if ($answer === '') return ['ok' => false, 'error' => 'Write your answer or paste a link.'];
        }
        $db->prepare('INSERT INTO intern_learning_progress (item_id, intern_id, score, answer) VALUES (?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE score = VALUES(score), answer = VALUES(answer), done_at = NOW()')->execute([$itemId, (int) $intern['id'], $score, $answer]);
        in_audit($db, (int) $intern['id'], 'learning_done', $item['title'] . ($score !== null ? ' ' . $score . '%' : ''));
        return ['ok' => true, 'score' => $score];
    }

    // ── Reports & logbook ────────────────────────────────────────────────────────────────────────────────
    function in_reports(PDO $db, int $internId): array
    {
        try {
            $st = $db->prepare('SELECT * FROM intern_reports WHERE intern_id = ? ORDER BY period_start DESC, id DESC');
            $st->execute([$internId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function in_report_label(array $r): string
    {
        $d = strtotime($r['period_start']);
        return match ($r['type']) {
            'daily' => 'Daily log · ' . date('D j M', $d),
            'weekly' => 'Weekly report · Week of ' . date('j M', $d),
            default => 'Monthly report · ' . date('F Y', $d),
        };
    }

    /** Intern: new report, or fix and re-send a rejected/pending one. */
    function in_report_save(PDO $db, array $intern, array $in): array
    {
        $type = in_array($in['type'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $in['type'] : 'daily';
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['period_start'] ?? '')) ? $in['period_start'] : date('Y-m-d');
        if ($type === 'weekly') $date = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        if ($type === 'monthly') $date = date('Y-m-01', strtotime($date));
        $f = fn($k) => mb_substr(trim((string) ($in[$k] ?? '')), 0, 5000) ?: null;
        if (!$f('activities')) return ['ok' => false, 'error' => 'Write what you did.'];
        $st = $db->prepare('SELECT * FROM intern_reports WHERE intern_id = ? AND type = ? AND period_start = ?');
        $st->execute([(int) $intern['id'], $type, $date]);
        $old = $st->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['status'] === 'approved') return ['ok' => false, 'error' => 'That report is already approved.'];
        $db->prepare("INSERT INTO intern_reports (intern_id, type, period_start, activities, skills, challenges, lessons) VALUES (?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE activities = VALUES(activities), skills = VALUES(skills), challenges = VALUES(challenges), lessons = VALUES(lessons), status = 'pending'")
           ->execute([(int) $intern['id'], $type, $date, $f('activities'), $f('skills'), $f('challenges'), $f('lessons')]);
        in_audit($db, (int) $intern['id'], $old ? 'report_resent' : 'report_sent', $type . ' ' . $date);
        if ($intern['supervisor_id']) {
            PortalNotifications::notify($db, (int) $intern['supervisor_id'], 'intern', $intern['full_name'] . ' sent a ' . $type . ' report', 'Approve or reject it.', 'admin/interns/supervise.php?id=' . (int) $intern['id'] . '#reports');
        }
        return ['ok' => true];
    }

    function in_report_review(PDO $db, int $reportId, string $decision, string $comment): array
    {
        $st = $db->prepare('SELECT * FROM intern_reports WHERE id = ?');
        $st->execute([$reportId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return ['ok' => false, 'error' => 'Report not found.'];
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        if ($status === 'rejected' && trim($comment) === '') return ['ok' => false, 'error' => 'Say what to fix.'];
        $db->prepare('UPDATE intern_reports SET status = ?, supervisor_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
           ->execute([$status, trim($comment) ?: null, (int) $_SESSION['user_id'], $reportId]);
        in_audit($db, (int) $r['intern_id'], 'report_' . $status, in_report_label($r));
        if ($intern = in_get($db, (int) $r['intern_id'])) {
            in_notify_intern($db, $intern, 'intern_report', 'Report ' . $status . ': ' . in_report_label($r), trim($comment) ?: ($status === 'approved' ? 'Good work.' : 'Fix it and send it again.'), 'intern/reports/index.php');
        }
        return ['ok' => true];
    }

    // ── Evaluations & progress ───────────────────────────────────────────────────────────────────────────
    function in_evaluations(PDO $db, int $internId): array
    {
        $out = [];
        try {
            $st = $db->prepare('SELECT * FROM intern_evaluations WHERE intern_id = ?');
            $st->execute([$internId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['kind']] = $r;
        } catch (Throwable $e) {
        }
        return $out;
    }

    function in_eval_score(?array $ev): ?float
    {
        if (!$ev) return null;
        $vals = array_filter(array_map(fn($k) => $ev[$k] !== null ? (int) $ev[$k] : null, array_keys(INTERN_EVAL_CRITERIA)), fn($v) => $v !== null);
        return $vals ? round(array_sum($vals) / count($vals), 1) : null;
    }

    /** Save an evaluation. Intern: self / skills (submitted). Supervisor: midterm / final, optionally released. */
    function in_eval_save(PDO $db, int $internId, string $kind, array $in, bool $bySupervisor): array
    {
        if (!isset(INTERN_EVAL_KINDS[$kind])) return ['ok' => false, 'error' => 'Unknown evaluation.'];
        if ($bySupervisor !== in_array($kind, ['midterm', 'final'], true)) return ['ok' => false, 'error' => 'You cannot fill this evaluation.'];
        $intern = in_get($db, $internId);
        if (!$intern) return ['ok' => false, 'error' => 'Intern not found.'];
        if ($kind === 'final' && empty($intern['final_eval_open'])) return ['ok' => false, 'error' => 'The final evaluation is not open yet. The admin opens it.'];
        $current = in_evaluations($db, $internId)[$kind] ?? null;
        if (!$bySupervisor && $current && $current['status'] !== 'draft') return ['ok' => false, 'error' => 'You already submitted this.'];
        $stars = [];
        foreach (array_keys(INTERN_EVAL_CRITERIA) as $k) {
            $v = (int) ($in[$k] ?? 0);
            $stars[$k] = $v >= 1 && $v <= 5 ? $v : null;
        }
        if (in_array(null, $stars, true)) return ['ok' => false, 'error' => 'Give 1 to 5 stars for every item.'];
        $release = $bySupervisor && !empty($in['release']);
        $status = $bySupervisor ? ($release ? 'released' : 'draft') : 'submitted';
        $db->prepare('INSERT INTO intern_evaluations (intern_id, kind, professionalism, technical, communication, teamwork, feedback, status, evaluator_id, released_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE professionalism = VALUES(professionalism), technical = VALUES(technical), communication = VALUES(communication),
                        teamwork = VALUES(teamwork), feedback = VALUES(feedback), status = VALUES(status), evaluator_id = VALUES(evaluator_id), released_at = COALESCE(released_at, VALUES(released_at))')
           ->execute([$internId, $kind, $stars['professionalism'], $stars['technical'], $stars['communication'], $stars['teamwork'],
                      mb_substr(trim((string) ($in['feedback'] ?? '')), 0, 5000) ?: null, $status, (int) $_SESSION['user_id'], $release ? date('Y-m-d H:i:s') : null]);
        in_audit($db, $internId, 'evaluation_' . $kind . '_' . $status);
        if ($release && (!$current || $current['status'] !== 'released')) {
            in_notify_intern($db, $intern, 'intern_eval', INTERN_EVAL_KINDS[$kind] . ' released', 'Your supervisor\'s evaluation is ready to read.', 'intern/assessments/index.php');
        }
        return ['ok' => true];
    }

    /**
     * Progress made of attendance, tasks, training and assessments (weights from Settings).
     * $teaching = ['taught' => n, 'planned' => n, 'rating' => avg|null] is only passed on the intern's own pages
     * (InternTeachingService); admin screens never pass it.
     */
    function in_progress(PDO $db, array $intern, ?array $teaching = null): array
    {
        $s = in_settings($db);
        $tasks = in_tasks($db, (int) $intern['id']);
        $taskTotal = count($tasks) + ($teaching['total'] ?? 0);
        $taskDone = count(array_filter($tasks, fn($t) => $t['status'] === 'completed')) + ($teaching['taught'] ?? 0);
        $att = in_attendance_stats($db, $intern);
        $training = in_training_percent(in_modules($db, (int) $intern['id']));
        $evals = in_evaluations($db, (int) $intern['id']);
        $scores = array_filter([
            ($evals['midterm']['status'] ?? '') === 'released' ? in_eval_score($evals['midterm']) : null,
            ($evals['final']['status'] ?? '') === 'released' ? in_eval_score($evals['final']) : null,
            $teaching['rating'] ?? null,
        ], fn($v) => $v !== null);
        $parts = [
            'attendance' => $att['rate'],
            'tasks' => $taskTotal ? (int) round($taskDone / $taskTotal * 100) : 0,
            'training' => $training,
            'assessments' => $scores ? (int) round(array_sum($scores) / count($scores) / 5 * 100) : 0,
        ];
        $w = ['attendance' => (int) $s['weight_attendance'], 'tasks' => (int) $s['weight_tasks'], 'training' => (int) $s['weight_training'], 'assessments' => (int) $s['weight_assessments']];
        $sum = array_sum($w) ?: 1;
        $overall = (int) round(array_sum(array_map(fn($k) => $parts[$k] * $w[$k], array_keys($parts))) / $sum);
        return ['overall' => $overall, 'parts' => $parts, 'task_total' => $taskTotal, 'task_done' => $taskDone, 'attendance' => $att, 'evals' => $evals];
    }

    // ── Messages ─────────────────────────────────────────────────────────────────────────────────────────
    function in_messages(PDO $db, int $internId): array
    {
        try {
            $st = $db->prepare("SELECT m.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS sender_name
                                FROM intern_messages m JOIN users u ON u.id = m.sender_id LEFT JOIN user_profiles p ON p.user_id = u.id
                                WHERE m.intern_id = ? ORDER BY m.created_at, m.id LIMIT 500");
            $st->execute([$internId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Unread messages for this reader (the intern reads the supervisor's; the supervisor reads the intern's). */
    function in_unread_messages(PDO $db, array $intern, bool $forIntern): int
    {
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM intern_messages WHERE intern_id = ? AND read_at IS NULL AND sender_id ' . ($forIntern ? '<>' : '=') . ' ?');
            $st->execute([(int) $intern['id'], (int) $intern['user_id']]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    function in_messages_mark_read(PDO $db, array $intern, bool $forIntern): void
    {
        $db->prepare('UPDATE intern_messages SET read_at = NOW() WHERE intern_id = ? AND read_at IS NULL AND sender_id ' . ($forIntern ? '<>' : '=') . ' ?')
           ->execute([(int) $intern['id'], (int) $intern['user_id']]);
    }

    function in_message_send(PDO $db, array $intern, string $body): array
    {
        $body = trim($body);
        if ($body === '') return ['ok' => false, 'error' => 'Write a message.'];
        $me = (int) $_SESSION['user_id'];
        $db->prepare('INSERT INTO intern_messages (intern_id, sender_id, body) VALUES (?, ?, ?)')->execute([(int) $intern['id'], $me, mb_substr($body, 0, 4000)]);
        in_audit($db, (int) $intern['id'], 'message_sent');
        if ($me === (int) $intern['user_id']) {
            if ($intern['supervisor_id']) PortalNotifications::notify($db, (int) $intern['supervisor_id'], 'intern_message', 'Message from ' . $intern['full_name'], mb_strimwidth($body, 0, 120, '…'), 'admin/interns/supervise.php?id=' . (int) $intern['id'] . '#messages');
        } else {
            PortalNotifications::notify($db, (int) $intern['user_id'], 'intern_message', 'Message from your supervisor', mb_strimwidth($body, 0, 120, '…'), 'intern/messages/index.php');
        }
        return ['ok' => true];
    }

    // ── Documents ────────────────────────────────────────────────────────────────────────────────────────
    function in_documents(PDO $db, int $internId): array
    {
        try {
            $st = $db->prepare("SELECT d.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS uploader
                                FROM intern_documents d LEFT JOIN users u ON u.id = d.uploaded_by LEFT JOIN user_profiles p ON p.user_id = u.id
                                WHERE d.intern_id = ? ORDER BY d.created_at DESC");
            $st->execute([$internId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function in_document_add(PDO $db, int $internId, string $category, string $title, array $file): array
    {
        $category = isset(INTERN_DOC_CATEGORIES[$category]) ? $category : 'other';
        $r = in_store_file($file, 'docs/' . $internId);
        if (!$r['ok']) return $r;
        $title = mb_substr(trim($title), 0, 200) ?: $r['name'];
        $db->prepare('INSERT INTO intern_documents (intern_id, category, title, path, mime, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$internId, $category, $title, $r['path'], $r['mime'], $r['size'], (int) $_SESSION['user_id']]);
        in_audit($db, $internId, 'document_uploaded', $category . ': ' . $title);
        return ['ok' => true];
    }

    function in_document_delete(PDO $db, int $docId, ?int $internId = null): bool
    {
        $st = $db->prepare('SELECT * FROM intern_documents WHERE id = ?' . ($internId ? ' AND intern_id = ' . (int) $internId : ''));
        $st->execute([$docId]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        if (!$d) return false;
        in_file_delete($d['path']);
        $db->prepare('DELETE FROM intern_documents WHERE id = ?')->execute([$docId]);
        in_audit($db, (int) $d['intern_id'], 'document_deleted', $d['title']);
        return true;
    }

    // ── Data protection: export and delete (Kenya Data Protection Act) ───────────────────────────────────
    function in_export(PDO $db, int $internId): array
    {
        $intern = in_get($db, $internId);
        $q = function (string $sql) use ($db, $internId) {
            $st = $db->prepare($sql);
            $st->execute([$internId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };
        in_audit($db, $internId, 'data_exported');
        return [
            'exported_at' => date('c'),
            'intern' => $intern,
            'tasks' => $q('SELECT * FROM intern_tasks WHERE intern_id = ?'),
            'submissions' => $q('SELECT * FROM intern_task_submissions WHERE intern_id = ?'),
            'attendance' => $q('SELECT * FROM intern_attendance WHERE intern_id = ?'),
            'learning' => $q('SELECT * FROM intern_learning_progress WHERE intern_id = ?'),
            'reports' => $q('SELECT * FROM intern_reports WHERE intern_id = ?'),
            'evaluations' => $q('SELECT * FROM intern_evaluations WHERE intern_id = ?'),
            'messages' => $q('SELECT * FROM intern_messages WHERE intern_id = ?'),
            'documents' => $q('SELECT id, category, title, mime, size, created_at FROM intern_documents WHERE intern_id = ?'),
        ];
    }

    /** Remove the intern, their files and their sign-in account. The audit log keeps an entry (no personal data). */
    function in_delete(PDO $db, int $internId): bool
    {
        $intern = in_get($db, $internId);
        if (!$intern) return false;
        require_once __DIR__ . '/InternTeachingService.php';
        it_delete_for_intern($db, $internId);
        $files = $db->prepare('SELECT path FROM intern_documents WHERE intern_id = ? UNION ALL SELECT f.path FROM intern_task_files f JOIN intern_tasks t ON t.id = f.task_id WHERE t.intern_id = ?');
        $files->execute([$internId, $internId]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $p) in_file_delete($p);
        in_file_delete($intern['photo_path']);
        foreach (['DELETE f FROM intern_task_files f JOIN intern_tasks t ON t.id = f.task_id WHERE t.intern_id = ?',
                  'DELETE c FROM intern_task_comments c JOIN intern_tasks t ON t.id = c.task_id WHERE t.intern_id = ?',
                  'DELETE FROM intern_task_submissions WHERE intern_id = ?', 'DELETE FROM intern_tasks WHERE intern_id = ?',
                  'DELETE FROM intern_attendance WHERE intern_id = ?', 'DELETE FROM intern_learning_progress WHERE intern_id = ?',
                  'DELETE FROM intern_learning_assignments WHERE intern_id = ?', 'DELETE FROM intern_reports WHERE intern_id = ?',
                  'DELETE FROM intern_evaluations WHERE intern_id = ?', 'DELETE FROM intern_messages WHERE intern_id = ?',
                  'DELETE FROM intern_documents WHERE intern_id = ?', 'DELETE FROM interns WHERE id = ?'] as $sql) {
            $db->prepare($sql)->execute([$internId]);
        }
        $uid = (int) $intern['user_id'];
        foreach (['DELETE FROM portal_notifications WHERE user_id = ?', 'DELETE FROM push_subscriptions WHERE user_id = ?',
                  'DELETE FROM announcement_recipients WHERE user_id = ?', 'DELETE FROM user_profiles WHERE user_id = ?', 'DELETE FROM users WHERE id = ?'] as $sql) {
            try { $db->prepare($sql)->execute([$uid]); } catch (Throwable $e) { }
        }
        in_audit($db, null, 'intern_deleted', 'intern #' . $internId);
        return true;
    }

    /** Red badge counts for the intern menu. */
    function in_badges(PDO $db, array $intern): array
    {
        $tasks = 0;
        try {
            $st = $db->prepare("SELECT COUNT(*) FROM intern_tasks WHERE intern_id = ? AND status IN ('todo','revision')");
            $st->execute([(int) $intern['id']]);
            $tasks = (int) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        $ann = 0;
        try {
            $st = $db->prepare('SELECT COUNT(*) FROM announcement_recipients WHERE user_id = ? AND read_at IS NULL');
            $st->execute([(int) $intern['user_id']]);
            $ann = (int) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        return ['tasks' => $tasks, 'announcements' => $ann, 'messages' => in_unread_messages($db, $intern, true)];
    }
}
