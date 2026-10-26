<?php
// app/v1/services/PortalPreviewService.php
// "Preview the student portal" / "Preview the tutor portal": an admin opens a portal as a temporary student or tutor, in its own
// browser session (a separate cookie that only travels to /students/ or /tutor/), so their admin login is untouched.
// The tutor preview also gets one temporary student, so its dashboard, students and course builder have something to show. The temporary account and its registration are deleted
// when the preview is exited, and any that were left behind are swept away after a few hours. The temporary student is not a
// real enrolment: its fee is 0 and it never sends email.

require_once __DIR__ . '/../models/ProgressModel.php';

class PortalPreviewService
{
    public const COOKIE = 'DAZUPREVIEW';
    public const TUTOR_COOKIE = 'DAZUPREVIEWT';
    public const INTERN_COOKIE = 'DAZUPREVIEWI';
    public const REG_PREFIX = 'PREVIEW-';
    private const STALE_HOURS = 3;

    public static function cookiePath(string $portal = 'students'): string
    {
        return (defined('WEB_ROOT') ? rtrim(WEB_ROOT, '/') : '') . '/' . $portal . '/';
    }

    /** Create the temporary student and open a preview session for it. Returns ['ok', 'error'?, 'redirect'?]. */
    public static function start(PDO $pdo, int $adminId): array
    {
        self::purgeStale($pdo);
        $made = self::createStudent($pdo, $adminId);
        if (!$made['ok']) {
            return $made;
        }
        self::openSession(self::COOKIE, 'students', [
            'user_id' => $made['user_id'], 'username' => $made['username'], 'email' => $made['email'],
            'role' => 'student', 'role_id' => $made['role_id'],
            'preview' => ['user_id' => $made['user_id'], 'reg_id' => $made['reg_id'], 'started' => time()],
        ]);
        return ['ok' => true, 'redirect' => url('students/dashboard/index.php')];
    }

    /** Create a temporary tutor with one temporary student, and open a tutor-portal preview session. */
    public static function startTutor(PDO $pdo, int $adminId): array
    {
        self::purgeStale($pdo);
        $tutorRole = (int) $pdo->query("SELECT id FROM roles WHERE role_name = 'tutor' LIMIT 1")->fetchColumn();
        if (!$tutorRole) {
            return ['ok' => false, 'error' => "The 'tutor' role is missing."];
        }
        $student = self::createStudent($pdo, $adminId);
        if (!$student['ok']) {
            return $student;
        }
        $token = bin2hex(random_bytes(5));
        $username = 'preview_' . $token;
        $email = $username . '@preview.invalid';
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (?, ?, ?, ?, 1, 1, 'active')"
            )->execute([$username, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $tutorRole]);
            $tutorUserId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)')
                ->execute([$tutorUserId, 'Preview', 'Tutor', '']);
            $pdo->prepare('INSERT INTO tutors (user_id, bio, is_active) VALUES (?, ?, 1)')
                ->execute([$tutorUserId, 'Temporary tutor for the portal preview.']);
            $tutorId = (int) $pdo->lastInsertId();
            // Plain assignment (not through payroll), so the preview never shows up in salaries.
            $pdo->prepare('INSERT INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$tutorId, $student['user_id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            self::deleteUser($pdo, (int) $student['user_id']);
            error_log('[PortalPreviewService::startTutor] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not start the preview: ' . $e->getMessage()];
        }
        try {
            require_once __DIR__ . '/PortalNotifications.php';
            PortalNotifications::notify($pdo, $tutorUserId, 'preview', 'Welcome to the tutor portal preview',
                'This is how tutors see new submissions and students. Everything here is deleted when you exit.', 'tutor/dashboard/index.php');
        } catch (Throwable $e) {
            // the bell is optional for the look of the portal
        }
        self::openSession(self::TUTOR_COOKIE, 'tutor', [
            'user_id' => $tutorUserId, 'username' => $username, 'email' => $email, 'role' => 'tutor', 'role_id' => $tutorRole,
            'preview' => ['user_id' => $tutorUserId, 'extra_user_ids' => [(int) $student['user_id']], 'started' => time()],
        ]);
        return ['ok' => true, 'redirect' => url('tutor/dashboard/index.php')];
    }

    /** Create a temporary intern (with a few sample tasks) and open an intern-portal preview session. */
    public static function startIntern(PDO $pdo, int $adminId): array
    {
        self::purgeStale($pdo);
        $role = (int) $pdo->query("SELECT id FROM roles WHERE role_name = 'intern' LIMIT 1")->fetchColumn();
        if (!$role) {
            return ['ok' => false, 'error' => 'The intern portal is not installed yet. Run System Updates (migration 075).'];
        }
        $username = 'preview_' . bin2hex(random_bytes(5));
        $email = $username . '@preview.invalid';
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")
                ->execute([$username, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $role]);
            $uid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)')->execute([$uid, 'Preview', 'Intern', '']);
            $dept = $pdo->query('SELECT id FROM intern_departments WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn() ?: null;
            $pdo->prepare('INSERT INTO interns (user_id, department_id, supervisor_id, institution, course, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$uid, $dept, $adminId ?: null, 'Preview University', 'Preview course', date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('+90 days'))]);
            $internId = (int) $pdo->lastInsertId();
            $task = $pdo->prepare('INSERT INTO intern_tasks (intern_id, title, description, priority, deadline, status, assigned_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ([['Read the company policy', 'Read it and write three things you learned.', 'normal', '+3 days', 'todo'],
                      ['Set up your work laptop', 'Install the tools your supervisor listed.', 'medium', '+1 day', 'in_progress'],
                      ['Write your first weekly report', 'Use Reports & Logbook.', 'high', '+5 days', 'todo']] as [$t, $d, $p, $due, $st]) {
                $task->execute([$internId, $t, $d, $p, date('Y-m-d', strtotime($due)), $st, $adminId ?: $uid]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[PortalPreviewService::startIntern] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not start the preview: ' . $e->getMessage()];
        }
        self::openSession(self::INTERN_COOKIE, 'intern', [
            'user_id' => $uid, 'username' => $username, 'email' => $email, 'role' => 'intern', 'role_id' => $role,
            'preview' => ['user_id' => $uid, 'started' => time()],
        ]);
        return ['ok' => true, 'redirect' => url('intern/dashboard/index.php')];
    }

    /** @return array{ok:bool, error?:string, user_id?:int, reg_id?:int, username?:string, email?:string, role_id?:int} */
    private static function createStudent(PDO $pdo, int $adminId): array
    {
        $course = null;
        try {
            $course = $pdo->query(
                "SELECT c.id FROM courses c LEFT JOIN curriculum_units u ON u.course_id = c.id AND u.is_active = 1
                 WHERE c.is_active = 1 GROUP BY c.id ORDER BY COUNT(u.id) DESC, c.id LIMIT 1"
            )->fetchColumn();
        } catch (Throwable $e) {
            $course = $pdo->query('SELECT id FROM courses WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
        }
        if (!$course) {
            return ['ok' => false, 'error' => 'Add and activate at least one course first, so the preview has something to show.'];
        }
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE role_name = 'student' LIMIT 1")->fetchColumn();
        if (!$roleId) {
            return ['ok' => false, 'error' => "The 'student' role is missing."];
        }

        $token = bin2hex(random_bytes(5));
        $username = 'preview_' . $token;
        $email = $username . '@preview.invalid';
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (?, ?, ?, ?, 1, 1, 'active')"
            )->execute([$username, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $roleId]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)')
                ->execute([$userId, 'Preview', 'Student', '']);
            // Only the columns this database actually has (older installs lack some).
            $vals = [
                'registration_no' => self::REG_PREFIX . strtoupper($token), 'full_name' => 'Preview Student', 'email' => $email,
                'phone' => '0000000000', 'course_id' => (int) $course, 'study_mode' => 'online', 'id_document_path' => 'admin/preview',
                'registration_fee' => 0, 'course_fee' => 0, 'payment_plan' => 'full', 'installment_count' => 1, 'installment_amount' => 0,
                'payment_reference' => 'Portal preview', 'possible_returning_student' => 0, 'enrollment_type' => 'new',
                'current_learning_month' => 1, 'fee_status' => 'paid_in_full', 'registration_fee_waived' => 1, 'installments_paid' => 0,
                'status' => 'approved', 'reviewed_by' => $adminId ?: null, 'user_id' => $userId, 'portal_locked' => 0,
            ];
            $have = array_column($pdo->query('SHOW COLUMNS FROM registrations')->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $vals = array_intersect_key($vals, array_flip($have));
            $cols = array_keys($vals);
            $extraCols = in_array('reviewed_at', $have, true) ? ', reviewed_at' : '';
            $extraVal = $extraCols ? ', NOW()' : '';
            $pdo->prepare('INSERT INTO registrations (' . implode(',', $cols) . $extraCols . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . $extraVal . ')')
                ->execute(array_values($vals));
            $regId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[PortalPreviewService::start] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not start the preview: ' . $e->getMessage()];
        }
        try {
            (new \Models\ProgressModel($pdo))->initializeForRegistration($regId, (int) $course, 1);
        } catch (Throwable $e) {
            // curriculum tables are optional for the look of the portal
        }

        return ['ok' => true, 'user_id' => $userId, 'reg_id' => $regId, 'username' => $username, 'email' => $email, 'role_id' => $roleId];
    }

    /** Write a preview session under its own cookie (path-limited to the portal), then hand the admin session back untouched. */
    private static function openSession(string $cookie, string $portal, array $data): void
    {
        $adminName = session_name();
        $adminSid = session_id();
        session_write_close();
        session_name($cookie);
        session_id(bin2hex(random_bytes(16)));
        session_set_cookie_params(['path' => self::cookiePath($portal), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
        $_SESSION = $data + ['logged_in' => true];
        session_write_close();
        session_name($adminName);
        session_id($adminSid);
        session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    /** Delete the temporary account of the current preview session and end that session. */
    public static function end(PDO $pdo): void
    {
        $p = $_SESSION['preview'] ?? null;
        if (is_array($p) && !empty($p['user_id'])) {
            foreach (array_merge([(int) $p['user_id']], array_map('intval', $p['extra_user_ids'] ?? [])) as $uid) {
                self::deleteUser($pdo, $uid);
            }
        }
        $portal = [self::TUTOR_COOKIE => 'tutor', self::INTERN_COOKIE => 'intern'][session_name()] ?? 'students';
        $_SESSION = [];
        setcookie(session_name(), '', time() - 3600, self::cookiePath($portal));
        session_destroy();
    }

    private static function deleteUser(PDO $pdo, int $userId): void
    {
        try {
            // Only ever removes preview accounts.
            $st = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND username LIKE 'preview\\_%'");
            $st->execute([$userId]);
            if (!$st->fetchColumn()) return;
            foreach ([
                "DELETE mc FROM module_contents mc JOIN course_modules m ON m.id = mc.module_id WHERE m.tutor_id = ?",
                "DELETE FROM course_modules WHERE tutor_id = ?",
                "DELETE FROM tutor_course_overviews WHERE tutor_id = ?",
                "DELETE FROM tutor_course_resources WHERE tutor_id = ?",
                "DELETE FROM tutor_announcements WHERE student_id = ?",
                "DELETE FROM portal_notifications WHERE user_id = ?",
                "DELETE FROM push_subscriptions WHERE user_id = ?",
                "DELETE FROM tutors WHERE user_id = ?",
                // a preview intern and everything it made
                "DELETE f FROM intern_task_files f JOIN intern_tasks t ON t.id = f.task_id JOIN interns i ON i.id = t.intern_id WHERE i.user_id = ?",
                "DELETE c FROM intern_task_comments c JOIN intern_tasks t ON t.id = c.task_id JOIN interns i ON i.id = t.intern_id WHERE i.user_id = ?",
                "DELETE s FROM intern_task_submissions s JOIN interns i ON i.id = s.intern_id WHERE i.user_id = ?",
                "DELETE t FROM intern_tasks t JOIN interns i ON i.id = t.intern_id WHERE i.user_id = ?",
                "DELETE a FROM intern_attendance a JOIN interns i ON i.id = a.intern_id WHERE i.user_id = ?",
                "DELETE r FROM intern_reports r JOIN interns i ON i.id = r.intern_id WHERE i.user_id = ?",
                "DELETE e FROM intern_evaluations e JOIN interns i ON i.id = e.intern_id WHERE i.user_id = ?",
                "DELETE m FROM intern_messages m JOIN interns i ON i.id = m.intern_id WHERE i.user_id = ?",
                "DELETE p FROM intern_learning_progress p JOIN interns i ON i.id = p.intern_id WHERE i.user_id = ?",
                "DELETE a FROM intern_learning_assignments a JOIN interns i ON i.id = a.intern_id WHERE i.user_id = ?",
                "DELETE d FROM intern_documents d JOIN interns i ON i.id = d.intern_id WHERE i.user_id = ?",
                "DELETE FROM interns WHERE user_id = ?",
                "DELETE FROM announcement_recipients WHERE user_id = ?",
            ] as $sql) {
                try {
                    $pdo->prepare($sql)->execute([$userId]);
                } catch (Throwable $e) {
                    // table not installed yet
                }
            }
            $pdo->prepare('DELETE FROM registrations WHERE user_id = ? AND registration_no LIKE ?')->execute([$userId, self::REG_PREFIX . '%']);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        } catch (Throwable $e) {
            error_log('[PortalPreviewService::deleteUser] ' . $e->getMessage());
        }
    }

    public static function purgeStale(PDO $pdo): void
    {
        try {
            $st = $pdo->prepare("SELECT user_id FROM registrations WHERE registration_no LIKE ? AND created_at < (NOW() - INTERVAL " . self::STALE_HOURS . " HOUR)");
            $st->execute([self::REG_PREFIX . '%']);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                self::deleteUser($pdo, (int) $uid);
            }
            $st = $pdo->query("SELECT id FROM users WHERE username LIKE 'preview\\_%' AND created_at < (NOW() - INTERVAL " . self::STALE_HOURS . " HOUR)");
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                self::deleteUser($pdo, (int) $uid);
            }
        } catch (Throwable $e) {
            error_log('[PortalPreviewService::purgeStale] ' . $e->getMessage());
        }
    }
}
