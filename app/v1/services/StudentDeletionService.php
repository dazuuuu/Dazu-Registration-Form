<?php
// app/v1/services/StudentDeletionService.php
// Permanently delete a current (approved) student: every registration of theirs, their login, and everything tied to them.
// Most tables cascade from users/registrations by foreign key; the ones below have no foreign key and are cleaned here.
// Kept on purpose: tutor_payroll_history (tutors' pay records) and SmS business records (their foreign keys set NULL).

class StudentDeletionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{ok:bool, error?:string, name?:string, courses?:int} */
    public function delete(int $registrationId, int $actorUserId): array
    {
        $st = $this->db->prepare("SELECT * FROM registrations WHERE id = ?");
        $st->execute([$registrationId]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) return ['ok' => false, 'error' => 'Student not found.'];
        $uid = (int) ($reg['user_id'] ?? 0);

        // Never delete a staff account by mistake.
        if ($uid) {
            $st = $this->db->prepare(
                "SELECT u.role_id, (SELECT COUNT(*) FROM tutors t WHERE t.user_id = u.id) AS tutor,
                        (SELECT COUNT(*) FROM employees e WHERE e.user_id = u.id) AS employee FROM users u WHERE u.id = ?"
            );
            $st->execute([$uid]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u && (in_array((int) $u['role_id'], [1, 2, 4, 13], true) || $u['tutor'] || $u['employee'])) {
                return ['ok' => false, 'error' => 'This login also belongs to a staff account (admin, tutor or employee), so it cannot be deleted here.'];
            }
        }

        // All registrations of this person (a student may be enrolled in several courses).
        if ($uid) {
            $st = $this->db->prepare("SELECT id FROM registrations WHERE user_id = ?");
            $st->execute([$uid]);
            $regIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $regIds = [$registrationId];
        }
        if (!in_array($registrationId, $regIds, true)) $regIds[] = $registrationId;
        $files = $this->privateFiles($uid);

        try {
            $this->db->beginTransaction();
            if ($uid) {
                // Assignments and quizzes (no foreign keys).
                $subs = $this->ids("SELECT id FROM tutor_assignment_submissions WHERE student_user_id = ?", [$uid]);
                foreach (['tutor_assignment_answers', 'tutor_assignment_files', 'tutor_assignment_versions', 'tutor_assignment_criterion_scores'] as $t) {
                    $this->deleteIn($t, 'submission_id', $subs);
                }
                foreach (['tutor_assignment_submissions', 'tutor_assignment_recipients', 'tutor_assignment_requests', 'tutor_assignment_targets', 'tutor_quiz_targets'] as $t) {
                    $this->exec("DELETE FROM $t WHERE student_user_id = ?", [$uid]);
                }
                $attempts = $this->ids("SELECT id FROM tutor_quiz_attempts WHERE student_user_id = ?", [$uid]);
                $this->deleteIn('tutor_quiz_answers', 'attempt_id', $attempts);
                $this->exec("DELETE FROM tutor_quiz_attempts WHERE student_user_id = ?", [$uid]);
                $this->exec("UPDATE tutor_assignment_events SET student_user_id = NULL WHERE student_user_id = ?", [$uid]);
                $this->exec("DELETE FROM tutor_announcements WHERE student_id = ?", [$uid]);
                // Personal data keyed by user id.
                foreach (['portal_notifications', 'push_subscriptions', 'personal_documents', 'student_id_cards', 'tutor_students', 'user_profiles'] as $t) {
                    $this->exec("DELETE FROM $t WHERE user_id = ?", [$uid]);
                }
            }
            // Data keyed by registration id without a cascading foreign key.
            foreach (['student_module_progress', 'admission_documents', 'student_id_cards'] as $t) {
                $this->deleteIn($t, 'registration_id', $regIds);
            }
            // Registrations (cascades payments, certificates, submissions, unit progress), then the login (cascades the rest).
            $this->deleteIn('registrations', 'id', $regIds);
            if ($uid) $this->exec("DELETE FROM users WHERE id = ?", [$uid]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[StudentDeletionService] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not delete the student: ' . $e->getMessage()];
        }

        foreach ($files as $path) {
            is_dir($path) ? $this->rmTree($path) : @unlink($path);
        }
        error_log(sprintf('[StudentDeletionService] user %d deleted student "%s" (%s), user_id %d, registrations %s',
            $actorUserId, $reg['full_name'], $reg['registration_no'], $uid, implode(',', $regIds)));
        return ['ok' => true, 'name' => (string) $reg['full_name'], 'courses' => count($regIds)];
    }

    /** Private files to remove after the rows are gone: personal documents, assignment uploads, passport photo. */
    private function privateFiles(int $uid): array
    {
        if (!$uid) return [];
        $app = dirname(__DIR__);
        $out = [$app . '/storage/documents/' . $uid];
        foreach ($this->ids("SELECT id FROM tutor_assignment_submissions WHERE student_user_id = ?", [$uid]) as $sid) {
            $out[] = $app . '/storage/assignments/submissions/' . $sid;
        }
        try {
            $st = $this->db->prepare("SELECT profile_photo_path FROM user_profiles WHERE user_id = ?");
            $st->execute([$uid]);
            $photo = (string) $st->fetchColumn();
            if ($photo !== '' && !preg_match('#(^|/)\.\.(/|$)#', $photo)) {
                $abs = realpath(dirname($app, 2) . '/public/' . ltrim(preg_replace('#^/?Registration-System/public/#', '', $photo), '/'));
                if ($abs && str_starts_with($abs, realpath(dirname($app, 2) . '/public/uploads') ?: "\0")) $out[] = $abs;
            }
        } catch (Throwable $e) {
        }
        return array_filter($out, 'file_exists');
    }

    private function ids(string $sql, array $args): array
    {
        try {
            $st = $this->db->prepare($sql);
            $st->execute($args);
            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            return []; // table not created on this install
        }
    }

    private function deleteIn(string $table, string $col, array $ids): void
    {
        if (!$ids) return;
        $this->exec("DELETE FROM $table WHERE $col IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
    }

    /** Runs a statement; a table or column missing on an older install is skipped, anything else aborts the delete. */
    private function exec(string $sql, array $args): void
    {
        try {
            $this->db->prepare($sql)->execute($args);
        } catch (PDOException $e) {
            if (!in_array((int) ($e->errorInfo[1] ?? 0), [1146, 1054], true)) throw $e;
        }
    }

    private function rmTree(string $dir): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
