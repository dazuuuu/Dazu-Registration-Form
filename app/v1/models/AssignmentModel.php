<?php
// app/v1/models/AssignmentModel.php
// Tutor assignments: create and publish to chosen students, order lock, due-date close, extensions, unlock/skip,
// submissions with versions, grading with rubric and feedback, progress. Tables: tutor_assignment* (migrations 065, 066).
// $tutorId is tutors.id; $uid / $studentUserId is the student's users.id; $actor is the users.id doing the action.
// All stored times are UTC ('Y-m-d H:i:s'); use local()/toUtc() for East Africa Time.
namespace Models;

use PDO;

class AssignmentModel {
    public const TZ = 'Africa/Nairobi';

    public const SUBMIT_TYPES = [
        'link'   => 'Link',
        'text'   => 'Written answers',
        'images' => 'Photos or screenshots',
        'file'   => 'File upload',
    ];
    public const GRADING_MODES = ['marks' => 'Marks', 'pass' => 'Pass or Not yet', 'feedback' => 'Feedback only'];
    public const REASONS = [
        'power'   => 'Power or internet problem',
        'illness' => 'Illness or medical',
        'family'  => 'Family matter',
        'work'    => 'Work or school clash',
        'device'  => 'Computer or software problem',
        'other'   => 'Other',
    ];
    // state => [label, icon, css class] — every status has a word and a symbol, never colour alone.
    public const STATES = [
        'graded'      => ['Graded', 'fa-circle-check', 'st-graded'],
        'submitted'   => ['Submitted', 'fa-check', 'st-submitted'],
        'returned'    => ['Returned', 'fa-rotate-left', 'st-returned'],
        'in_progress' => ['In progress', 'fa-ellipsis', 'st-progress'],
        'open'        => ['Open', 'fa-circle', 'st-open'],
        'extension'   => ['Extension asked', 'fa-e', 'st-extension'],
        'locked'      => ['Locked', 'fa-lock', 'st-locked'],
        'closed'      => ['Closed', 'fa-lock', 'st-closed'],
        'skipped'     => ['Skipped', 'fa-forward', 'st-skipped'],
    ];
    /** States that count as "done" and unlock the next assignment. */
    public const DONE = ['submitted', 'returned', 'graded', 'skipped'];

    private const STUDENT_NAME_SQL = "COALESCE((SELECT r.full_name FROM registrations r WHERE r.user_id = %s AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1),
              (SELECT u.username FROM users u WHERE u.id = %s))";

    private PDO $db;
    /** Assignment ids unlocked for a student during the last chain() call (for "now open" notices). */
    public array $justUnlocked = [];

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /* ================= Time ================= */

    public static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    public static function ts(?string $utc): int {
        return $utc ? (int) strtotime($utc . ' UTC') : 0;
    }

    /** East Africa Time input ("2026-10-14T17:00" or "2026-10-14 17:00") → UTC string. */
    public static function toUtc(?string $local): ?string {
        $local = trim((string) $local);
        if ($local === '') return null;
        try {
            $d = new \DateTime(str_replace('T', ' ', $local), new \DateTimeZone(self::TZ));
        } catch (\Throwable $e) {
            return null;
        }
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function local(?string $utc, string $fmt = 'D j M Y, H:i'): string {
        if (!$utc) return '';
        $d = new \DateTime($utc, new \DateTimeZone('UTC'));
        return $d->setTimezone(new \DateTimeZone(self::TZ))->format($fmt);
    }

    /** Value for <input type="datetime-local">. */
    public static function localInput(?string $utc): string {
        return $utc ? self::local($utc, 'Y-m-d\TH:i') : '';
    }

    /** "Due today, 17:00" / "Due tomorrow, 09:00" / "Due Fri 14 Oct, 17:00". */
    public static function dueText(?string $utc, string $prefix = 'Due'): string {
        if (!$utc) return 'No due date';
        $today = self::local(self::now(), 'Y-m-d');
        $day = self::local($utc, 'Y-m-d');
        $when = $day === $today ? 'today' : ($day === date('Y-m-d', strtotime($today . ' +1 day')) ? 'tomorrow' : self::local($utc, 'D j M'));
        return $prefix . ' ' . $when . ', ' . self::local($utc, 'H:i');
    }

    /* ================= Grade scale (admin setting) ================= */

    /** ['distinction' => 80, 'merit' => 65, 'pass' => 50]; stored in site_settings. */
    public function gradeScale(): array {
        $scale = ['distinction' => 80, 'merit' => 65, 'pass' => 50];
        try {
            $st = $this->db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('assignment_scale_distinction','assignment_scale_merit','assignment_scale_pass')");
            $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if (is_numeric($v)) $scale[str_replace('assignment_scale_', '', $k)] = (int) $v;
            }
        } catch (\Throwable $e) {
        }
        return $scale;
    }

    public static function gradeLabel(float $percent, array $scale): string {
        if ($percent >= $scale['distinction']) return 'Distinction';
        if ($percent >= $scale['merit']) return 'Merit';
        if ($percent >= $scale['pass']) return 'Pass';
        return 'Not yet';
    }

    /* ================= Audit ================= */

    public function log(?int $assignmentId, ?int $actor, ?int $studentUserId, string $event, array $details = []): void {
        try {
            $this->db->prepare("INSERT INTO tutor_assignment_events (assignment_id, actor_user_id, student_user_id, event, details, created_at) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$assignmentId, $actor, $studentUserId, $event, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, self::now()]);
        } catch (\Throwable $e) {
            error_log('[AssignmentModel log] ' . $e->getMessage());
        }
    }

    /** Simple rate limit: how many of these events this user caused in the last $seconds. */
    public function recentCount(int $actor, array $events, int $seconds): int {
        $in = implode(',', array_fill(0, count($events), '?'));
        $st = $this->db->prepare("SELECT COUNT(*) FROM tutor_assignment_events WHERE actor_user_id = ? AND event IN ($in) AND created_at >= ?");
        $st->execute(array_merge([$actor], $events, [gmdate('Y-m-d H:i:s', time() - $seconds)]));
        return (int) $st->fetchColumn();
    }

    public function events(int $assignmentId, ?int $studentUserId = null, int $limit = 100): array {
        $sql = "SELECT e.*, u.username AS actor_name FROM tutor_assignment_events e LEFT JOIN users u ON u.id = e.actor_user_id WHERE e.assignment_id = ?";
        $args = [$assignmentId];
        if ($studentUserId) { $sql .= " AND e.student_user_id = ?"; $args[] = $studentUserId; }
        $st = $this->db->prepare($sql . " ORDER BY e.id DESC LIMIT " . (int) $limit);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ================= Tutor, courses, students ================= */

    public function tutorByUser(int $userId): ?array {
        $st = $this->db->prepare(
            "SELECT t.*, u.email, u.username, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name
             FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.user_id = ?"
        );
        $st->execute([$userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function tutorById(int $tutorId): ?array {
        $st = $this->db->prepare("SELECT user_id FROM tutors WHERE id = ?");
        $st->execute([$tutorId]);
        $uid = (int) $st->fetchColumn();
        return $uid ? $this->tutorByUser($uid) : null;
    }

    /** Courses the tutor teaches (from their assigned students' approved enrolments): id => title. */
    public function tutorCourses(int $tutorId): array {
        $st = $this->db->prepare(
            "SELECT DISTINCT c.id, c.title FROM courses c
             JOIN registrations r ON r.course_id = c.id AND r.status = 'approved'
             JOIN tutor_students ts ON ts.user_id = r.user_id
             WHERE ts.tutor_id = ? ORDER BY c.title"
        );
        $st->execute([$tutorId]);
        return array_map(fn($t) => (string) $t, $st->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * The tutor's own students, optionally only those approved in one course: user_id => [user_id, full_name, registration_no, email, course_title].
     * This is the only list a tutor may pick recipients from.
     */
    public function tutorStudents(int $tutorId, ?int $courseId = null): array {
        $sql = "SELECT r.user_id, r.full_name, r.registration_no, u.email, c.title AS course_title, r.course_id
                FROM tutor_students ts
                JOIN registrations r ON r.user_id = ts.user_id AND r.status = 'approved'
                JOIN users u ON u.id = r.user_id
                JOIN courses c ON c.id = r.course_id
                WHERE ts.tutor_id = ?";
        $args = [$tutorId];
        if ($courseId) { $sql .= " AND r.course_id = ?"; $args[] = $courseId; }
        $st = $this->db->prepare($sql . " ORDER BY r.full_name, r.id DESC");
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['user_id']] ??= $r;
        }
        return $out;
    }

    public function isTutorStudent(int $tutorId, int $uid): bool {
        $st = $this->db->prepare("SELECT 1 FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
        $st->execute([$tutorId, $uid]);
        return (bool) $st->fetchColumn();
    }

    public function student(int $uid): array {
        $st = $this->db->prepare(
            "SELECT u.id AS user_id, u.email, " . sprintf(self::STUDENT_NAME_SQL, 'u.id', 'u.id') . " AS full_name,
                    (SELECT r.registration_no FROM registrations r WHERE r.user_id = u.id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1) AS registration_no
             FROM users u WHERE u.id = ?"
        );
        $st->execute([$uid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: ['user_id' => $uid, 'email' => null, 'full_name' => 'Student', 'registration_no' => ''];
    }

    /* ================= Assignments ================= */

    public function get(int $id): ?array {
        $st = $this->db->prepare(
            "SELECT a.*, c.title AS course_title FROM tutor_assignments a LEFT JOIN courses c ON c.id = a.course_id WHERE a.id = ?"
        );
        $st->execute([$id]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        return $a ? $this->decorate($a) : null;
    }

    /** Assignment only if this tutor owns it. */
    public function getForTutor(int $id, int $tutorId): ?array {
        $a = $this->get($id);
        return $a && (int) $a['tutor_id'] === $tutorId ? $a : null;
    }

    private function decorate(array $a): array {
        $a['types'] = array_keys(array_filter([
            'link' => $a['submit_link'] ?? 0, 'text' => $a['submit_text'] ?? 0,
            'images' => $a['submit_images'] ?? 0, 'file' => $a['submit_file'] ?? 0,
        ]));
        $a['types_text'] = self::typesText($a['types']);
        return $a;
    }

    public static function typesText(array $types): string {
        $names = array_map(fn($t) => mb_strtolower(self::SUBMIT_TYPES[$t]), $types);
        if (count($names) < 2) return $names ? ucfirst($names[0]) : 'Nothing set';
        $last = array_pop($names);
        return ucfirst(implode(', ', $names) . ' and ' . $last);
    }

    /** Number shown as "Assignment N": position among the tutor's published assignments in that course. */
    public function number(array $a): int {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM tutor_assignments WHERE tutor_id = ? AND COALESCE(course_id, 0) = ? AND status <> 'archived'
             AND (sort_order < ? OR (sort_order = ? AND id <= ?))"
        );
        $st->execute([(int) $a['tutor_id'], (int) $a['course_id'], (int) $a['sort_order'], (int) $a['sort_order'], (int) $a['id']]);
        return max(1, (int) $st->fetchColumn());
    }

    public function nextSortOrder(int $tutorId, ?int $courseId): int {
        $st = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM tutor_assignments WHERE tutor_id = ? AND COALESCE(course_id, 0) = ?");
        $st->execute([$tutorId, (int) $courseId]);
        return (int) $st->fetchColumn();
    }

    /** All of a tutor's assignments (or every tutor's when $tutorId is null) with counts, ordered by course then order. */
    public function listAll(?int $tutorId, bool $withArchived = false): array {
        $sql = "SELECT a.*, c.title AS course_title,
                    (SELECT COUNT(*) FROM tutor_assignment_recipients r WHERE r.assignment_id = a.id AND r.removed_at IS NULL) AS recipients,
                    (SELECT COUNT(*) FROM tutor_assignment_submissions s WHERE s.assignment_id = a.id AND s.submitted_at IS NOT NULL) AS submitted,
                    (SELECT COUNT(*) FROM tutor_assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted') AS awaiting,
                    (SELECT COUNT(*) FROM tutor_assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') AS graded,
                    (SELECT COUNT(*) FROM tutor_assignment_requests q WHERE q.assignment_id = a.id AND q.status = 'pending') AS requests
                FROM tutor_assignments a LEFT JOIN courses c ON c.id = a.course_id WHERE 1 = 1";
        $args = [];
        if ($tutorId !== null) { $sql .= " AND a.tutor_id = ?"; $args[] = $tutorId; }
        if (!$withArchived) $sql .= " AND a.status <> 'archived'";
        $st = $this->db->prepare($sql . " ORDER BY c.title, a.course_id, a.sort_order, a.id");
        $st->execute($args);
        return array_map([$this, 'decorate'], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public function questions(int $assignmentId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_questions WHERE assignment_id = ? ORDER BY position, id");
        $st->execute([$assignmentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rubric(int $assignmentId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_rubric WHERE assignment_id = ? ORDER BY position, id");
        $st->execute([$assignmentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachments(int $assignmentId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_attachments WHERE assignment_id = ? ORDER BY position, id");
        $st->execute([$assignmentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function attachment(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_attachments WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Active recipient user ids. */
    public function recipientIds(int $assignmentId): array {
        $st = $this->db->prepare("SELECT student_user_id FROM tutor_assignment_recipients WHERE assignment_id = ? AND removed_at IS NULL");
        $st->execute([$assignmentId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function recipient(int $assignmentId, int $uid): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_recipients WHERE assignment_id = ? AND student_user_id = ?");
        $st->execute([$assignmentId, $uid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasSubmissions(int $assignmentId): bool {
        $st = $this->db->prepare("SELECT 1 FROM tutor_assignment_submissions WHERE assignment_id = ? AND submitted_at IS NOT NULL LIMIT 1");
        $st->execute([$assignmentId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Create or update an assignment with its questions, rubric and recipients.
     * $d: course_id, title, description, details, instructions, due_at (UTC), sequential_lock, close_after_due, max_extension_days,
     *     submit_link/text/images/file, link_sites, grading_mode, max_score, pass_mark_percent.
     * $questions: list of [id|null, text]; $rubric: list of [id|null, name, max_marks]; $recipients: validated user ids.
     * @return array{id:int, added:int[], removed:int[]}
     */
    public function save(int $tutorId, ?int $id, array $d, array $questions, array $rubric, array $recipients, int $actor): array {
        $own = !$this->db->inTransaction();
        if ($own) $this->db->beginTransaction();
        try {
            $cols = ['course_id', 'title', 'description', 'details', 'instructions', 'due_at', 'sequential_lock', 'close_after_due',
                     'max_extension_days', 'submit_link', 'submit_text', 'submit_images', 'submit_file', 'link_sites', 'grading_mode',
                     'max_score', 'pass_mark_percent'];
            $vals = array_map(fn($c) => $d[$c] ?? null, $cols);
            if ($id) {
                $old = $this->getForTutor($id, $tutorId);
                $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
                $extra = '';
                if ($old && (int) $old['course_id'] !== (int) $d['course_id']) {
                    $extra = ', sort_order = ' . $this->nextSortOrder($tutorId, $d['course_id'] ?: null);
                }
                $this->db->prepare("UPDATE tutor_assignments SET $set, updated_at = ?$extra WHERE id = ? AND tutor_id = ?")
                    ->execute(array_merge($vals, [self::now(), $id, $tutorId]));
                $this->log($id, $actor, null, 'edit');
            } else {
                $this->db->prepare(
                    "INSERT INTO tutor_assignments (tutor_id, sort_order, audience, status, created_at, updated_at, " . implode(', ', $cols) . ")
                     VALUES (?, ?, 'selected', 'draft', ?, ?, " . implode(', ', array_fill(0, count($cols), '?')) . ")"
                )->execute(array_merge([$tutorId, $this->nextSortOrder($tutorId, $d['course_id'] ?: null), self::now(), self::now()], $vals));
                $id = (int) $this->db->lastInsertId();
                $this->log($id, $actor, null, 'create');
            }
            $this->syncRows('tutor_assignment_questions', $id, $questions, fn($q) => ['text' => $q[1]]);
            $this->syncRows('tutor_assignment_rubric', $id, $rubric, fn($r) => ['name' => $r[1], 'max_marks' => $r[2]]);
            [$added, $removed] = $this->syncRecipients($id, $recipients);
            if ($added || $removed) $this->log($id, $actor, null, 'recipients', ['added' => $added, 'removed' => $removed]);
            if ($own) $this->db->commit();
            return ['id' => $id, 'added' => $added, 'removed' => $removed];
        } catch (\Throwable $e) {
            if ($own) $this->db->rollBack();
            throw $e;
        }
    }

    /** Update rows in place by id (so answers and rubric scores keep pointing at them), insert new, delete the rest. */
    private function syncRows(string $table, int $assignmentId, array $rows, callable $fields): void {
        $st = $this->db->prepare("SELECT id FROM $table WHERE assignment_id = ?");
        $st->execute([$assignmentId]);
        $existing = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $keep = [];
        foreach (array_values($rows) as $i => $row) {
            $f = $fields($row);
            $rid = (int) ($row[0] ?? 0);
            if ($rid && in_array($rid, $existing, true)) {
                $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($f)));
                $this->db->prepare("UPDATE $table SET $set, position = ? WHERE id = ?")->execute(array_merge(array_values($f), [$i + 1, $rid]));
                $keep[] = $rid;
            } else {
                $this->db->prepare("INSERT INTO $table (assignment_id, position, " . implode(', ', array_keys($f)) . ") VALUES (?, ?, " . implode(', ', array_fill(0, count($f), '?')) . ")")
                    ->execute(array_merge([$assignmentId, $i + 1], array_values($f)));
            }
        }
        foreach (array_diff($existing, $keep) as $gone) {
            $this->db->prepare("DELETE FROM $table WHERE id = ?")->execute([$gone]);
        }
    }

    /** @return array{0:int[],1:int[]} [added, removed] */
    private function syncRecipients(int $assignmentId, array $userIds): array {
        $st = $this->db->prepare("SELECT student_user_id, removed_at FROM tutor_assignment_recipients WHERE assignment_id = ?");
        $st->execute([$assignmentId]);
        $current = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $current[(int) $r['student_user_id']] = $r['removed_at'] === null;
        $added = $removed = [];
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if (!isset($current[$uid])) {
                $this->db->prepare("INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at) VALUES (?, ?, ?)")
                    ->execute([$assignmentId, $uid, self::now()]);
                $added[] = $uid;
            } elseif (!$current[$uid]) {
                $this->db->prepare("UPDATE tutor_assignment_recipients SET removed_at = NULL WHERE assignment_id = ? AND student_user_id = ?")
                    ->execute([$assignmentId, $uid]);
                $added[] = $uid;
            }
        }
        foreach ($current as $uid => $active) {
            if ($active && !in_array($uid, $userIds, true)) {
                $this->db->prepare("UPDATE tutor_assignment_recipients SET removed_at = ? WHERE assignment_id = ? AND student_user_id = ?")
                    ->execute([self::now(), $assignmentId, $uid]);
                $removed[] = $uid;
            }
        }
        return [$added, $removed];
    }

    /** Publish a draft. Returns recipient ids not yet notified (each student is emailed once per assignment). */
    public function publish(int $id, int $actor): array {
        $this->db->prepare("UPDATE tutor_assignments SET status = 'published', published_at = COALESCE(published_at, ?) WHERE id = ? AND status = 'draft'")
            ->execute([self::now(), $id]);
        $this->log($id, $actor, null, 'publish');
        return $this->unnotified($id);
    }

    public function unnotified(int $id): array {
        $st = $this->db->prepare("SELECT student_user_id FROM tutor_assignment_recipients WHERE assignment_id = ? AND removed_at IS NULL AND notified_at IS NULL");
        $st->execute([$id]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function markNotified(int $id, array $uids): void {
        $up = $this->db->prepare("UPDATE tutor_assignment_recipients SET notified_at = ? WHERE assignment_id = ? AND student_user_id = ?");
        foreach ($uids as $uid) $up->execute([self::now(), $id, $uid]);
    }

    public function addAttachment(int $assignmentId, string $kind, ?string $path, ?string $url, ?string $orig, string $label): void {
        $st = $this->db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM tutor_assignment_attachments WHERE assignment_id = ?");
        $st->execute([$assignmentId]);
        $this->db->prepare("INSERT INTO tutor_assignment_attachments (assignment_id, kind, file_path, url, original_name, label, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$assignmentId, $kind, $path, $url, $orig, mb_substr($label, 0, 150), (int) $st->fetchColumn(), self::now()]);
    }

    public function removeAttachment(int $assignmentId, int $attId): void {
        $a = $this->attachment($attId);
        if (!$a || (int) $a['assignment_id'] !== $assignmentId) return;
        $this->db->prepare("DELETE FROM tutor_assignment_attachments WHERE id = ?")->execute([$attId]);
        if ($a['file_path'] && function_exists('asg_file_abs') && ($abs = asg_file_abs($a['file_path']))) @unlink($abs);
    }

    /** Tutors may delete only while nobody has submitted; otherwise it is archived. Returns 'deleted' | 'archived' | null. */
    public function deleteOrArchive(int $id, int $actor, bool $allowArchive): ?string {
        if (!$this->hasSubmissions($id)) {
            foreach ($this->attachments($id) as $att) $this->removeAttachment($id, (int) $att['id']);
            foreach (['tutor_assignment_questions', 'tutor_assignment_rubric', 'tutor_assignment_recipients', 'tutor_assignment_requests', 'tutor_assignment_targets'] as $t) {
                $this->db->prepare("DELETE FROM $t WHERE assignment_id = ?")->execute([$id]);
            }
            $this->db->prepare("DELETE FROM tutor_assignment_submissions WHERE assignment_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM tutor_assignments WHERE id = ?")->execute([$id]);
            $this->log($id, $actor, null, 'delete');
            return 'deleted';
        }
        if (!$allowArchive) return null;
        $this->setArchived($id, true, $actor);
        return 'archived';
    }

    public function setArchived(int $id, bool $on, int $actor): void {
        if ($on) {
            $this->db->prepare("UPDATE tutor_assignments SET status = 'archived', archived_at = ? WHERE id = ?")->execute([self::now(), $id]);
        } else {
            $this->db->prepare("UPDATE tutor_assignments SET status = IF(published_at IS NULL, 'draft', 'published'), archived_at = NULL WHERE id = ?")->execute([$id]);
        }
        $this->log($id, $actor, null, $on ? 'archive' : 'restore');
    }

    /** New order for one course: $ids in the order wanted (only the tutor's own are touched). */
    public function reorder(int $tutorId, array $ids, int $actor): void {
        $up = $this->db->prepare("UPDATE tutor_assignments SET sort_order = ? WHERE id = ? AND tutor_id = ?");
        foreach (array_values(array_map('intval', $ids)) as $i => $id) $up->execute([$i + 1, $id, $tutorId]);
        $this->log(null, $actor, null, 'reorder', ['ids' => array_map('intval', $ids)]);
    }

    /* ================= Rubric templates ================= */

    public function rubricTemplates(int $tutorId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_rubric_templates WHERE tutor_id = ? ORDER BY name");
        $st->execute([$tutorId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveRubricTemplate(int $tutorId, string $name, array $criteria): void {
        $this->db->prepare("INSERT INTO tutor_rubric_templates (tutor_id, name, criteria_json, created_at) VALUES (?, ?, ?, ?)")
            ->execute([$tutorId, mb_substr($name, 0, 120), json_encode(array_values($criteria), JSON_UNESCAPED_UNICODE), self::now()]);
    }

    public function deleteRubricTemplate(int $tutorId, int $id): void {
        $this->db->prepare("DELETE FROM tutor_rubric_templates WHERE id = ? AND tutor_id = ?")->execute([$id, $tutorId]);
    }

    /* ================= The order lock and due-date close (per student) ================= */

    /**
     * Every published assignment given to a student, in order, with its state for that student.
     * Optional filters limit it to one tutor and/or course. Groups are worked out per tutor + course.
     * Side effects (by design, "never re-lock"): stamps unlocked_at the first time an item is available and closed_at when it closes.
     */
    public function chain(int $uid, ?int $tutorId = null, ?int $courseId = null): array {
        $this->justUnlocked = [];
        $sql = "SELECT a.*, c.title AS course_title, r.id AS recipient_id, r.unlocked_at, r.current_due_at, r.closed_at, r.skipped_at, r.added_at,
                       s.id AS sub_id, s.status AS sub_status, s.version AS sub_version, s.submitted_at, s.score, s.grade_label, s.pass_result,
                       s.published_at AS result_published_at, s.draft_saved_at, s.is_late, s.returned_at,
                       (SELECT q.id FROM tutor_assignment_requests q WHERE q.assignment_id = a.id AND q.student_user_id = r.student_user_id AND q.type = 'extension' AND q.status = 'pending' LIMIT 1) AS ext_request_id,
                       (SELECT q.id FROM tutor_assignment_requests q WHERE q.assignment_id = a.id AND q.student_user_id = r.student_user_id AND q.type = 'unlock' AND q.status = 'pending' LIMIT 1) AS unlock_request_id
                FROM tutor_assignment_recipients r
                JOIN tutor_assignments a ON a.id = r.assignment_id AND a.status = 'published'
                LEFT JOIN courses c ON c.id = a.course_id
                LEFT JOIN tutor_assignment_submissions s ON s.assignment_id = a.id AND s.student_user_id = r.student_user_id
                WHERE r.student_user_id = ? AND r.removed_at IS NULL";
        $args = [$uid];
        if ($tutorId) { $sql .= " AND a.tutor_id = ?"; $args[] = $tutorId; }
        if ($courseId !== null) { $sql .= " AND COALESCE(a.course_id, 0) = ?"; $args[] = $courseId; }
        $st = $this->db->prepare($sql . " ORDER BY a.tutor_id, COALESCE(a.course_id, 0), a.sort_order, a.id");
        $st->execute($args);

        $now = time();
        $out = [];
        $group = null;
        $prevDone = true;
        $prevIndex = 0;
        $index = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $key = $a['tutor_id'] . '-' . (int) $a['course_id'];
            if ($key !== $group) { $group = $key; $prevDone = true; $index = 0; $prevIndex = 0; }
            $a = $this->decorate($a);
            $a['index'] = ++$index;
            $a['prev_index'] = $prevIndex;
            $a['due'] = $a['current_due_at'] ?: $a['due_at'];
            $a['late'] = false;
            $submitted = $a['submitted_at'] !== null && in_array($a['sub_status'], ['submitted', 'returned', 'graded'], true);

            if ($submitted) {
                $state = $a['sub_status'] === 'graded' && $a['result_published_at'] ? 'graded' : ($a['sub_status'] === 'returned' ? 'returned' : 'submitted');
                if (!$a['unlocked_at']) $this->stamp($a, 'unlocked_at');
            } elseif ($a['skipped_at']) {
                $state = 'skipped';
            } elseif ((int) $a['sequential_lock'] && !$prevDone && !$a['unlocked_at']) {
                $state = 'locked';
            } else {
                if (!$a['unlocked_at']) $this->stamp($a, 'unlocked_at');
                $pastDue = $a['due'] && self::ts($a['due']) <= $now;
                if ($pastDue && (int) $a['close_after_due'] && !$a['ext_request_id']) {
                    $state = 'closed';
                    if (!$a['closed_at']) $this->stamp($a, 'closed_at', $a['due']);
                } else {
                    $a['late'] = $pastDue;
                    $state = $a['ext_request_id'] ? 'extension' : ($a['sub_status'] === 'draft' && $a['draft_saved_at'] ? 'in_progress' : 'open');
                }
            }
            $a['state'] = $state;
            $a['done'] = in_array($state, self::DONE, true);
            $prevDone = $a['done'];
            $prevIndex = $a['index'];
            $out[] = $a;
        }
        return $out;
    }

    private function stamp(array &$a, string $col, ?string $value = null): void {
        $value = $value ?: self::now();
        $this->db->prepare("UPDATE tutor_assignment_recipients SET $col = ? WHERE id = ? AND $col IS NULL")->execute([$value, (int) $a['recipient_id']]);
        $a[$col] = $value;
        if ($col === 'unlocked_at') $this->justUnlocked[] = (int) $a['id'];
    }

    /** One assignment as the student sees it (state included), or null when it was not given to them. */
    public function itemForStudent(int $uid, int $assignmentId): ?array {
        $a = $this->get($assignmentId);
        if (!$a || $a['status'] !== 'published') return null;
        foreach ($this->chain($uid, (int) $a['tutor_id'], (int) $a['course_id']) as $x) {
            if ((int) $x['id'] === $assignmentId) return $x;
        }
        return null;
    }

    /* ================= Submissions (student side) ================= */

    public function submission(int $assignmentId, int $uid): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_submissions WHERE assignment_id = ? AND student_user_id = ?");
        $st->execute([$assignmentId, $uid]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        return $s ? $this->decorateSub($s) : null;
    }

    public function submissionById(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_submissions WHERE id = ?");
        $st->execute([$id]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        return $s ? $this->decorateSub($s) : null;
    }

    private function decorateSub(array $s): array {
        $s['links'] = json_decode((string) $s['links_json'], true) ?: [];
        $st = $this->db->prepare("SELECT question_id, answer_text FROM tutor_assignment_answers WHERE submission_id = ?");
        $st->execute([(int) $s['id']]);
        $s['answers'] = array_map('strval', $st->fetchAll(PDO::FETCH_KEY_PAIR));
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_files WHERE submission_id = ? AND removed_at IS NULL ORDER BY id");
        $st->execute([(int) $s['id']]);
        $s['files'] = $st->fetchAll(PDO::FETCH_ASSOC);
        return $s;
    }

    public function file(int $id): ?array {
        $st = $this->db->prepare(
            "SELECT f.*, s.assignment_id, s.student_user_id FROM tutor_assignment_files f JOIN tutor_assignment_submissions s ON s.id = f.submission_id WHERE f.id = ?"
        );
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ensureSubmission(int $assignmentId, int $uid): int {
        $this->db->prepare("INSERT IGNORE INTO tutor_assignment_submissions (assignment_id, student_user_id, status, version) VALUES (?, ?, 'draft', 0)")
            ->execute([$assignmentId, $uid]);
        $st = $this->db->prepare("SELECT id FROM tutor_assignment_submissions WHERE assignment_id = ? AND student_user_id = ?");
        $st->execute([$assignmentId, $uid]);
        return (int) $st->fetchColumn();
    }

    /** Answer boxes for written answers: the tutor's questions, or one general box (question_id 0) when there are none. */
    public function answerBoxes(array $a): array {
        if (!(int) $a['submit_text']) return [];
        return $this->questions((int) $a['id']) ?: [['id' => 0, 'text' => 'Your answer', 'position' => 1]];
    }

    /** Whether the student may still change their work: open states, or submitted/returned (not after a published final result). */
    public static function canEdit(array $item): bool {
        return in_array($item['state'], ['open', 'in_progress', 'extension', 'submitted', 'returned'], true);
    }

    /** Save the working copy (links, answers, note). Files are added/removed separately. */
    public function saveDraft(int $assignmentId, int $uid, array $links, array $answers, ?string $note): int {
        $subId = $this->ensureSubmission($assignmentId, $uid);
        $this->db->prepare("UPDATE tutor_assignment_submissions SET links_json = ?, note = ?, draft_saved_at = ? WHERE id = ?")
            ->execute([$links ? json_encode(array_values($links), JSON_UNESCAPED_SLASHES) : null, $note, self::now(), $subId]);
        $up = $this->db->prepare("INSERT INTO tutor_assignment_answers (submission_id, question_id, answer_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text)");
        foreach ($answers as $qid => $text) $up->execute([$subId, (int) $qid, $text]);
        return $subId;
    }

    public function addFile(int $assignmentId, int $uid, string $kind, array $stored, string $orig): void {
        $subId = $this->ensureSubmission($assignmentId, $uid);
        $this->db->prepare("INSERT INTO tutor_assignment_files (submission_id, kind, file_path, original_name, mime, bytes, added_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$subId, $kind, $stored['path'], mb_substr(basename($orig), 0, 255), $stored['mime'], (int) $stored['bytes'], self::now()]);
        $this->db->prepare("UPDATE tutor_assignment_submissions SET draft_saved_at = ? WHERE id = ?")->execute([self::now(), $subId]);
    }

    public function removeFile(int $uid, int $fileId): void {
        $f = $this->file($fileId);
        if ($f && (int) $f['student_user_id'] === $uid) {
            $this->db->prepare("UPDATE tutor_assignment_files SET removed_at = ? WHERE id = ?")->execute([self::now(), $fileId]);
        }
    }

    /**
     * Send the working copy as a new version. Checks what the tutor allowed and that something was sent.
     * @return array{ok:bool, error?:string, version?:int, resubmitted?:bool, unlocked?:int[]}
     */
    public function submit(array $item, int $uid): array {
        if (!self::canEdit($item)) return ['ok' => false, 'error' => 'This assignment can no longer be changed.'];
        $sub = $this->submission((int) $item['id'], $uid);
        if (!$sub) return ['ok' => false, 'error' => 'Add your work first: ' . mb_strtolower($item['types_text']) . '.'];
        $questions = $this->answerBoxes($item);
        $hasLinks = $sub && $sub['links'];
        $hasAnswers = $sub && array_filter($sub['answers'], fn($t) => trim($t) !== '');
        $images = $sub ? array_filter($sub['files'], fn($f) => $f['kind'] === 'image') : [];
        $docs = $sub ? array_filter($sub['files'], fn($f) => $f['kind'] === 'file') : [];
        if (!$hasLinks && !$hasAnswers && !$images && !$docs) {
            return ['ok' => false, 'error' => 'Add your work first: ' . mb_strtolower($item['types_text']) . '.'];
        }
        if ((int) $item['submit_text'] && $questions && (int) $questions[0]['id'] !== 0) {
            foreach ($questions as $i => $q) {
                if (trim($sub['answers'][(int) $q['id']] ?? '') === '') return ['ok' => false, 'error' => 'Answer question ' . ($i + 1) . ' before you submit.'];
            }
        }
        $version = (int) $sub['version'] + 1;
        $late = $item['due'] && self::ts($item['due']) < time() ? 1 : 0;
        $snapshot = [
            'links' => $sub['links'],
            'answers' => array_map(fn($q) => ['question_id' => (int) $q['id'], 'question' => $q['text'], 'answer' => $sub['answers'][(int) $q['id']] ?? ''], $questions),
            'files' => array_map(fn($f) => ['id' => (int) $f['id'], 'kind' => $f['kind'], 'name' => $f['original_name'], 'bytes' => (int) $f['bytes']], $sub['files']),
            'note' => $sub['note'],
            'late' => (bool) $late,
        ];
        $this->db->prepare("UPDATE tutor_assignment_submissions SET status = 'submitted', version = ?, submitted_at = ?, is_late = ? WHERE id = ?")
            ->execute([$version, self::now(), $late, (int) $sub['id']]);
        $this->db->prepare("INSERT INTO tutor_assignment_versions (submission_id, version, kind, snapshot, actor_user_id, created_at) VALUES (?, ?, 'submission', ?, ?, ?)")
            ->execute([(int) $sub['id'], $version, json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid, self::now()]);
        // Asking for more time no longer matters once the work is in.
        $this->db->prepare("UPDATE tutor_assignment_requests SET status = 'cancelled' WHERE assignment_id = ? AND student_user_id = ? AND status = 'pending'")
            ->execute([(int) $item['id'], $uid]);
        $this->log((int) $item['id'], $uid, $uid, $version > 1 ? 'resubmit' : 'submit', ['version' => $version, 'late' => (bool) $late]);
        $this->chain($uid, (int) $item['tutor_id'], (int) $item['course_id']);
        return ['ok' => true, 'version' => $version, 'resubmitted' => $version > 1, 'unlocked' => $this->justUnlocked];
    }

    /** Snapshots: kind 'submission' (every send) or 'grade' (every publish/return), newest first. */
    public function versions(int $submissionId, string $kind): array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_versions WHERE submission_id = ? AND kind = ? ORDER BY version DESC, id DESC");
        $st->execute([$submissionId, $kind]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['data'] = json_decode((string) $r['snapshot'], true) ?: [];
        return $rows;
    }

    /* ================= Grading (tutor side) ================= */

    /** Submission + assignment + student, if the tutor (or an admin when $tutorId is null) may see it. */
    public function submissionForGrader(int $submissionId, ?int $tutorId): ?array {
        $s = $this->submissionById($submissionId);
        if (!$s || $s['submitted_at'] === null) return null;
        $a = $this->get((int) $s['assignment_id']);
        if (!$a) return null;
        if ($tutorId !== null && ((int) $a['tutor_id'] !== $tutorId || !$this->isTutorStudent($tutorId, (int) $s['student_user_id']))) return null;
        return ['sub' => $s, 'a' => $a, 'student' => $this->student((int) $s['student_user_id'])];
    }

    public function criterionScores(int $submissionId): array {
        $st = $this->db->prepare("SELECT criterion_id, score, comment FROM tutor_assignment_criterion_scores WHERE submission_id = ?");
        $st->execute([$submissionId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['criterion_id']] = $r;
        return $out;
    }

    /**
     * Save a grade. $action: 'draft' (private), 'publish' (student sees it), 'return' (send back for changes).
     * $in: score, criteria [criterion_id => [score, comment]], pass_result, why, well, improve.
     * @return array{ok:bool, error?:string, updated?:bool}
     */
    public function grade(array $ctx, array $in, string $action, int $actor): array {
        $a = $ctx['a'];
        $s = $ctx['sub'];
        $mode = $a['grading_mode'];
        $max = (float) $a['max_score'];
        $why = trim((string) ($in['why'] ?? ''));
        $well = trim((string) ($in['well'] ?? ''));
        $improve = trim((string) ($in['improve'] ?? ''));
        $rubric = $mode === 'marks' ? $this->rubric((int) $a['id']) : [];
        $crit = [];
        $score = null;
        $label = null;
        $pass = null;

        if ($mode === 'marks') {
            if ($rubric) {
                $sum = 0.0;
                $all = true;
                foreach ($rubric as $r) {
                    $v = $in['criteria'][(int) $r['id']]['score'] ?? '';
                    $c = mb_substr(trim((string) ($in['criteria'][(int) $r['id']]['comment'] ?? '')), 0, 500);
                    if ($v === '' || !is_numeric($v)) { $all = false; $crit[(int) $r['id']] = [null, $c]; continue; }
                    $v = max(0, min((float) $r['max_marks'], (float) $v));
                    $crit[(int) $r['id']] = [$v, $c];
                    $sum += $v;
                }
                $score = $all ? $sum : null;
            } elseif (($in['score'] ?? '') !== '' && is_numeric($in['score'])) {
                $score = max(0, min($max, (float) $in['score']));
            }
            if ($score !== null) $label = self::gradeLabel($max > 0 ? $score / $max * 100 : 0, $this->gradeScale());
        } elseif ($mode === 'pass') {
            $pass = in_array($in['pass_result'] ?? '', ['pass', 'not_yet'], true) ? $in['pass_result'] : null;
            $label = $pass === 'pass' ? 'Pass' : ($pass ? 'Not yet' : null);
        } else {
            $pass = 'reviewed';
            $label = 'Reviewed';
        }

        if ($action !== 'draft') {
            if ($action === 'publish' && $mode === 'marks' && $score === null) return ['ok' => false, 'error' => $rubric ? 'Give a mark for every rubric line.' : 'Enter the marks.'];
            if ($action === 'publish' && $mode === 'pass' && !$pass) return ['ok' => false, 'error' => 'Choose Pass or Not yet.'];
            if ($why === '') return ['ok' => false, 'error' => $action === 'return' ? 'Explain what the student must change (Why this mark).' : '"Why this mark" is required.'];
            $full = ($mode === 'marks' && $score !== null && $score >= $max) || $pass === 'pass' || $mode === 'feedback';
            if ($improve === '' && ($action === 'return' || !$full)) return ['ok' => false, 'error' => '"What to improve" is required unless full marks are given.'];
        }

        $now = self::now();
        $wasPublished = $s['published_at'] !== null && $s['status'] === 'graded';
        $status = $action === 'publish' ? 'graded' : ($action === 'return' ? 'returned' : $s['status']);
        $this->db->prepare(
            "UPDATE tutor_assignment_submissions SET score = ?, grade_label = ?, pass_result = ?, feedback_why = ?, feedback_well = ?, feedback_improve = ?,
                    feedback = ?, graded_by = ?, graded_at = ?, status = ?, published_at = ?, returned_at = ?, grade_version = grade_version + ? WHERE id = ?"
        )->execute([
            $score, $label, $pass, $why ?: null, $well ?: null, $improve ?: null, $why ?: null, $actor, $now, $status,
            $action === 'publish' ? $now : ($action === 'return' ? null : $s['published_at']),
            $action === 'return' ? $now : $s['returned_at'],
            $action === 'draft' ? 0 : 1, (int) $s['id'],
        ]);
        $up = $this->db->prepare("INSERT INTO tutor_assignment_criterion_scores (submission_id, criterion_id, score, comment) VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE score = VALUES(score), comment = VALUES(comment)");
        foreach ($crit as $cid => [$v, $c]) $up->execute([(int) $s['id'], $cid, $v, $c ?: null]);

        if ($action !== 'draft') {
            $snap = ['action' => $action, 'score' => $score, 'max' => $max, 'label' => $label, 'pass_result' => $pass, 'why' => $why, 'well' => $well,
                     'improve' => $improve, 'criteria' => $crit, 'submission_version' => (int) $s['version'], 'by' => $actor];
            $st = $this->db->prepare("SELECT grade_version FROM tutor_assignment_submissions WHERE id = ?");
            $st->execute([(int) $s['id']]);
            $this->db->prepare("INSERT INTO tutor_assignment_versions (submission_id, version, kind, snapshot, actor_user_id, created_at) VALUES (?, ?, 'grade', ?, ?, ?)")
                ->execute([(int) $s['id'], (int) $st->fetchColumn(), json_encode($snap, JSON_UNESCAPED_UNICODE), $actor, $now]);
        }
        $this->log((int) $a['id'], $actor, (int) $s['student_user_id'], $action === 'draft' ? 'grade_draft' : ($action === 'return' ? 'return' : ($wasPublished ? 'grade_update' : 'grade')),
            ['score' => $score, 'label' => $label]);
        return ['ok' => true, 'updated' => $wasPublished && $action === 'publish'];
    }

    /* ================= Grading queue ================= */

    /**
     * Submissions for the tutor's queue (or all, for admins when $tutorId is null). Oldest first.
     * $tab: awaiting | graded | returned | all. Filters: course, assignment, q (name search).
     */
    public function queue(?int $tutorId, string $tab, array $f = []): array {
        $where = ["s.submitted_at IS NOT NULL", "a.status <> 'archived'"];
        $args = [];
        if ($tutorId !== null) {
            $where[] = "a.tutor_id = ?";
            $where[] = "EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.tutor_id = a.tutor_id AND ts.user_id = s.student_user_id)";
            $args[] = $tutorId;
        }
        $statusFor = ['awaiting' => 'submitted', 'graded' => 'graded', 'returned' => 'returned'];
        if (isset($statusFor[$tab])) { $where[] = "s.status = ?"; $args[] = $statusFor[$tab]; }
        if (!empty($f['course'])) { $where[] = "a.course_id = ?"; $args[] = (int) $f['course']; }
        if (!empty($f['assignment'])) { $where[] = "a.id = ?"; $args[] = (int) $f['assignment']; }
        $name = sprintf(self::STUDENT_NAME_SQL, 's.student_user_id', 's.student_user_id');
        if (!empty($f['q'])) { $where[] = "$name LIKE ?"; $args[] = '%' . $f['q'] . '%'; }
        $st = $this->db->prepare(
            "SELECT s.*, a.title, a.course_id, a.tutor_id, a.max_score, a.grading_mode, c.title AS course_title, $name AS full_name
             FROM tutor_assignment_submissions s
             JOIN tutor_assignments a ON a.id = s.assignment_id
             LEFT JOIN courses c ON c.id = a.course_id
             WHERE " . implode(' AND ', $where) . " ORDER BY s.submitted_at ASC, s.id ASC LIMIT 500"
        );
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Count for the Submissions menu badge. */
    public function awaitingCount(int $tutorId): int {
        try {
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM tutor_assignment_submissions s JOIN tutor_assignments a ON a.id = s.assignment_id
                 WHERE a.tutor_id = ? AND a.status <> 'archived' AND s.status = 'submitted' AND s.submitted_at IS NOT NULL
                 AND EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.tutor_id = a.tutor_id AND ts.user_id = s.student_user_id)"
            );
            $st->execute([$tutorId]);
            return (int) $st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Next submission awaiting a grade after this one (oldest first), for "Publish and open next". */
    public function nextAwaiting(int $tutorId, int $afterSubmissionId): ?int {
        foreach ($this->queue($tutorId, 'awaiting') as $row) {
            if ((int) $row['id'] !== $afterSubmissionId) return (int) $row['id'];
        }
        return null;
    }

    /* ================= Extension and unlock requests ================= */

    public function requests(?int $tutorId, string $status = 'pending', ?int $assignmentId = null): array {
        $where = ["q.status = ?"];
        $args = [$status];
        if ($tutorId !== null) { $where[] = "a.tutor_id = ?"; $args[] = $tutorId; }
        if ($assignmentId) { $where[] = "a.id = ?"; $args[] = $assignmentId; }
        $st = $this->db->prepare(
            "SELECT q.*, a.title, a.due_at, a.course_id, a.tutor_id, a.max_extension_days, c.title AS course_title, r.current_due_at,
                    " . sprintf(self::STUDENT_NAME_SQL, 'q.student_user_id', 'q.student_user_id') . " AS full_name
             FROM tutor_assignment_requests q
             JOIN tutor_assignments a ON a.id = q.assignment_id
             LEFT JOIN courses c ON c.id = a.course_id
             LEFT JOIN tutor_assignment_recipients r ON r.assignment_id = q.assignment_id AND r.student_user_id = q.student_user_id
             WHERE " . implode(' AND ', $where) . " ORDER BY q.created_at ASC"
        );
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function request(int $id): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_requests WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function latestRequest(int $assignmentId, int $uid, string $type): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_assignment_requests WHERE assignment_id = ? AND student_user_id = ? AND type = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$assignmentId, $uid, $type]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function requestExtension(array $item, int $uid, string $reason, string $text, ?string $wantUtc): array {
        if (!in_array($item['state'], ['open', 'in_progress'], true)) return ['ok' => false, 'error' => 'You can only ask for more time on an open assignment you have not submitted.'];
        if (!$item['due']) return ['ok' => false, 'error' => 'This assignment has no due date.'];
        if (self::ts($item['due']) <= time()) return ['ok' => false, 'error' => 'The due time has passed. Ask your tutor to unlock it instead.'];
        if (!isset(self::REASONS[$reason])) return ['ok' => false, 'error' => 'Choose a reason.'];
        if (trim($text) === '') return ['ok' => false, 'error' => 'Tell your tutor what happened.'];
        if (!$wantUtc || self::ts($wantUtc) <= self::ts($item['due'])) return ['ok' => false, 'error' => 'Pick a new date and time after the current due time.'];
        $limit = self::ts($item['due']) + (int) $item['max_extension_days'] * 86400;
        if (self::ts($wantUtc) > $limit) return ['ok' => false, 'error' => 'You can ask for at most ' . (int) $item['max_extension_days'] . ' days (until ' . self::local(gmdate('Y-m-d H:i:s', $limit)) . ').'];
        $this->db->prepare("INSERT INTO tutor_assignment_requests (assignment_id, student_user_id, type, reason_code, reason_text, requested_due_at, created_at) VALUES (?, ?, 'extension', ?, ?, ?, ?)")
            ->execute([(int) $item['id'], $uid, $reason, mb_substr(trim($text), 0, 2000), $wantUtc, self::now()]);
        $id = (int) $this->db->lastInsertId();
        $this->log((int) $item['id'], $uid, $uid, 'extension_request', ['request_id' => $id, 'wants' => $wantUtc]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function requestUnlock(array $item, int $uid, string $text): array {
        if ($item['state'] !== 'closed') return ['ok' => false, 'error' => 'This assignment is not closed.'];
        if ($item['unlock_request_id']) return ['ok' => false, 'error' => 'You have already asked. Your tutor will decide soon.'];
        $this->db->prepare("INSERT INTO tutor_assignment_requests (assignment_id, student_user_id, type, reason_text, created_at) VALUES (?, ?, 'unlock', ?, ?)")
            ->execute([(int) $item['id'], $uid, mb_substr(trim($text), 0, 2000) ?: null, self::now()]);
        $id = (int) $this->db->lastInsertId();
        $this->log((int) $item['id'], $uid, $uid, 'unlock_request', ['request_id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    /** Give one student a new due date (approve extension, unlock a closed one, or extend directly). */
    public function setStudentDue(int $assignmentId, int $uid, string $dueUtc, int $actor, string $event): void {
        $this->db->prepare("UPDATE tutor_assignment_recipients SET current_due_at = ?, closed_at = NULL, reminded_at = NULL, unlocked_at = COALESCE(unlocked_at, ?) WHERE assignment_id = ? AND student_user_id = ?")
            ->execute([$dueUtc, self::now(), $assignmentId, $uid]);
        $this->log($assignmentId, $actor, $uid, $event, ['new_due_at' => $dueUtc]);
    }

    /**
     * Approve or decline a pending request. Approving sets the student's due date to $dueUtc.
     * @return array{ok:bool, error?:string, request?:array}
     */
    public function decide(int $requestId, ?int $tutorId, bool $approve, ?string $dueUtc, string $note, int $actor): array {
        $q = $this->request($requestId);
        if (!$q || $q['status'] !== 'pending') return ['ok' => false, 'error' => 'That request was already decided.'];
        $a = $this->get((int) $q['assignment_id']);
        if (!$a || ($tutorId !== null && ((int) $a['tutor_id'] !== $tutorId || !$this->isTutorStudent($tutorId, (int) $q['student_user_id'])))) {
            return ['ok' => false, 'error' => 'Request not found.'];
        }
        if ($approve && (!$dueUtc || self::ts($dueUtc) <= time())) return ['ok' => false, 'error' => 'Pick a new due date in the future.'];
        $this->db->prepare("UPDATE tutor_assignment_requests SET status = ?, decided_by = ?, decided_at = ?, decision_note = ?, new_due_at = ? WHERE id = ?")
            ->execute([$approve ? 'approved' : 'declined', $actor, self::now(), mb_substr(trim($note), 0, 2000) ?: null, $approve ? $dueUtc : null, $requestId]);
        if ($approve) {
            $this->setStudentDue((int) $q['assignment_id'], (int) $q['student_user_id'], $dueUtc, $actor, $q['type'] === 'unlock' ? 'unlock' : 'extension_approved');
        } else {
            $this->log((int) $q['assignment_id'], $actor, (int) $q['student_user_id'], $q['type'] . '_declined');
        }
        return ['ok' => true, 'request' => $this->request($requestId) + ['assignment' => $a]];
    }

    /** Unlock directly (no request): new due date for that student; any pending unlock request is marked approved. */
    public function unlock(int $assignmentId, int $uid, string $dueUtc, int $actor): void {
        $this->setStudentDue($assignmentId, $uid, $dueUtc, $actor, 'unlock');
        $this->db->prepare("UPDATE tutor_assignment_requests SET status = 'approved', decided_by = ?, decided_at = ?, new_due_at = ? WHERE assignment_id = ? AND student_user_id = ? AND status = 'pending'")
            ->execute([$actor, self::now(), $dueUtc, $assignmentId, $uid]);
    }

    /** Skip a closed (or any unsubmitted) assignment for one student so the next one opens. Returns newly unlocked ids. */
    public function skip(array $a, int $uid, int $actor): array {
        $this->db->prepare("UPDATE tutor_assignment_recipients SET skipped_by = ?, skipped_at = ? WHERE assignment_id = ? AND student_user_id = ?")
            ->execute([$actor, self::now(), (int) $a['id'], $uid]);
        $this->db->prepare("UPDATE tutor_assignment_requests SET status = 'cancelled' WHERE assignment_id = ? AND student_user_id = ? AND status = 'pending'")
            ->execute([(int) $a['id'], $uid]);
        $this->log((int) $a['id'], $actor, $uid, 'skip');
        $this->chain($uid, (int) $a['tutor_id'], (int) $a['course_id']);
        return $this->justUnlocked;
    }

    /* ================= Progress ================= */

    /**
     * Grid for one tutor + course: ['assignments' => published list, 'rows' => [uid => ['student'=>..., 'cells'=>[aid=>item], 'done'=>n, 'total'=>n]]].
     * Rows are every student who was given at least one of these assignments.
     */
    public function progress(int $tutorId, ?int $courseId): array {
        $sql = "SELECT a.* FROM tutor_assignments a WHERE a.tutor_id = ? AND a.status = 'published'";
        $args = [$tutorId];
        if ($courseId !== null) { $sql .= " AND COALESCE(a.course_id, 0) = ?"; $args[] = $courseId; }
        $st = $this->db->prepare($sql . " ORDER BY a.sort_order, a.id");
        $st->execute($args);
        $assignments = array_map([$this, 'decorate'], $st->fetchAll(PDO::FETCH_ASSOC));
        if (!$assignments) return ['assignments' => [], 'rows' => []];
        $ids = array_map(fn($a) => (int) $a['id'], $assignments);
        $st = $this->db->prepare("SELECT DISTINCT student_user_id FROM tutor_assignment_recipients WHERE removed_at IS NULL AND assignment_id IN (" . implode(',', $ids) . ")");
        $st->execute();
        $rows = [];
        foreach (array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)) as $uid) {
            if (!$this->isTutorStudent($tutorId, $uid)) continue;
            $cells = [];
            foreach ($this->chain($uid, $tutorId, $courseId) as $item) $cells[(int) $item['id']] = $item;
            $done = count(array_filter($cells, fn($c) => in_array($c['state'], ['submitted', 'returned', 'graded'], true)));
            $rows[$uid] = ['student' => $this->student($uid), 'cells' => $cells, 'done' => $done, 'total' => count($cells)];
        }
        uasort($rows, fn($x, $y) => strcasecmp((string) $x['student']['full_name'], (string) $y['student']['full_name']));
        return ['assignments' => $assignments, 'rows' => $rows];
    }

    /** One assignment, one row per recipient with that student's state (for the assignment page). */
    public function assignmentStudents(array $a): array {
        $out = [];
        foreach ($this->recipientIds((int) $a['id']) as $uid) {
            $item = null;
            foreach ($this->chain($uid, (int) $a['tutor_id'], (int) $a['course_id']) as $x) {
                if ((int) $x['id'] === (int) $a['id']) { $item = $x; break; }
            }
            if (!$item) continue;
            $out[$uid] = ['student' => $this->student($uid), 'item' => $item, 'sub' => $item['sub_id'] ? $this->submissionById((int) $item['sub_id']) : null];
        }
        uasort($out, fn($x, $y) => strcasecmp((string) $x['student']['full_name'], (string) $y['student']['full_name']));
        return $out;
    }

    /** Short "Link + 2 answers" summary of what a submission contains. */
    public static function contentSummary(?array $sub): string {
        if (!$sub || $sub['submitted_at'] === null) return 'Not submitted';
        $parts = [];
        if ($sub['links']) $parts[] = count($sub['links']) > 1 ? count($sub['links']) . ' links' : 'Link';
        $n = count(array_filter($sub['answers'], fn($t) => trim($t) !== ''));
        if ($n) $parts[] = $n . ' answer' . ($n > 1 ? 's' : '');
        $img = count(array_filter($sub['files'], fn($f) => $f['kind'] === 'image'));
        if ($img) $parts[] = $img . ' image' . ($img > 1 ? 's' : '');
        $doc = count(array_filter($sub['files'], fn($f) => $f['kind'] === 'file'));
        if ($doc) $parts[] = $doc . ' file' . ($doc > 1 ? 's' : '');
        if (!$parts && (!empty($sub['answer_text']) || !empty($sub['file_path']))) $parts[] = 'Earlier answer';
        return implode(' + ', $parts) ?: 'Sent';
    }

    /** Result text for a graded item: "78 / 100 Merit", "Pass", "Reviewed". */
    public static function resultText(array $x): string {
        if (($x['grading_mode'] ?? 'marks') === 'marks' && $x['score'] !== null) {
            return rtrim(rtrim(number_format((float) $x['score'], 2, '.', ''), '0'), '.') . ' / ' . (int) $x['max_score'] . ($x['grade_label'] ? ' ' . $x['grade_label'] : '');
        }
        return (string) ($x['grade_label'] ?: 'Graded');
    }

    /* ================= Reminders (cron) ================= */

    /** Recipients whose (effective) due time is within the next 24 hours and who have not sent anything yet. */
    public function dueSoon(): array {
        $now = self::now();
        $soon = gmdate('Y-m-d H:i:s', time() + 86400);
        $st = $this->db->prepare(
            "SELECT r.*, a.tutor_id, a.course_id, a.title FROM tutor_assignment_recipients r
             JOIN tutor_assignments a ON a.id = r.assignment_id AND a.status = 'published'
             LEFT JOIN tutor_assignment_submissions s ON s.assignment_id = a.id AND s.student_user_id = r.student_user_id
             WHERE r.removed_at IS NULL AND r.reminded_at IS NULL AND r.skipped_at IS NULL
               AND COALESCE(r.current_due_at, a.due_at) > ? AND COALESCE(r.current_due_at, a.due_at) <= ?
               AND (s.id IS NULL OR s.submitted_at IS NULL)"
        );
        $st->execute([$now, $soon]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markReminded(int $recipientId): void {
        $this->db->prepare("UPDATE tutor_assignment_recipients SET reminded_at = ? WHERE id = ?")->execute([self::now(), $recipientId]);
    }

    /** Pending requests older than 24 hours that have not been reminded yet. */
    public function staleRequests(): array {
        $st = $this->db->prepare("SELECT id FROM tutor_assignment_requests WHERE status = 'pending' AND reminded_at IS NULL AND created_at <= ?");
        $st->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function markRequestReminded(int $id): void {
        $this->db->prepare("UPDATE tutor_assignment_requests SET reminded_at = ? WHERE id = ?")->execute([self::now(), $id]);
    }
}
