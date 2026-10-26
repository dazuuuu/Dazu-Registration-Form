<?php
// app/v1/models/ClassworkModel.php
// Quizzes a tutor sets for all their students, several of them, or just one. (Assignments: AssignmentModel.)
// $tutorId everywhere is tutors.id; $studentUserId is the student's users.id (tutor_students.user_id).
// A student sees an item when its audience is 'all' and they are assigned to the tutor, or they are in its targets.
namespace Models;

use PDO;

class ClassworkModel {
    public const QTYPES = [
        'mcq'  => 'Multiple choice (auto-marked)',
        'text' => 'Written answer',
        'code' => 'Code',
    ];

    // Student name and registration number for a row aliased s (latest approved registration).
    private const NAME_SQL = "(SELECT r.full_name FROM registrations r WHERE r.user_id = s.student_user_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1) AS full_name,
             (SELECT r.registration_no FROM registrations r WHERE r.user_id = s.student_user_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1) AS registration_no";

    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /** Students assigned to a tutor: user_id => [full_name, registration_no, course_title]. */
    public function studentsOf(int $tutorId): array {
        $st = $this->db->prepare(
            "SELECT r.user_id, r.full_name, r.registration_no, c.title AS course_title
             FROM tutor_students ts
             JOIN registrations r ON r.user_id = ts.user_id AND r.status = 'approved'
             JOIN courses c ON c.id = r.course_id
             WHERE ts.tutor_id = ? ORDER BY r.full_name, r.id DESC"
        );
        $st->execute([$tutorId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['user_id']] ??= $r;
        }
        return $out;
    }

    /** Validated audience from a form: ['all', []] or ['selected', [user ids]]; null when no student was picked. */
    public function audienceFromPost(int $tutorId, array $post): ?array {
        if (($post['audience'] ?? 'all') === 'all') {
            return ['all', []];
        }
        $mine = $this->studentsOf($tutorId);
        $ids = array_values(array_filter(array_map('intval', (array) ($post['students'] ?? [])), fn($id) => isset($mine[$id])));
        return $ids ? ['selected', array_unique($ids)] : null;
    }

    /** User ids that can see an item (for notifications and the "not submitted" list). */
    public function recipients(string $kind, array $item): array {
        if ($item['audience'] === 'all') {
            return array_keys($this->studentsOf((int) $item['tutor_id']));
        }
        [$table, $col] = $kind === 'quiz' ? ['tutor_quiz_targets', 'quiz_id'] : ['tutor_assignment_targets', 'assignment_id'];
        $st = $this->db->prepare("SELECT student_user_id FROM $table WHERE $col = ?");
        $st->execute([(int) $item['id']]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    private function setTargets(string $table, string $col, int $id, array $userIds): void {
        $this->db->prepare("DELETE FROM $table WHERE $col = ?")->execute([$id]);
        $ins = $this->db->prepare("INSERT IGNORE INTO $table ($col, student_user_id) VALUES (?, ?)");
        foreach ($userIds as $uid) {
            $ins->execute([$id, (int) $uid]);
        }
    }

    private function visibleSql(string $alias, string $targets, string $col): string {
        return "($alias.audience = 'all' AND EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.tutor_id = $alias.tutor_id AND ts.user_id = :uid1))
                OR EXISTS (SELECT 1 FROM $targets t WHERE t.$col = $alias.id AND t.student_user_id = :uid2)";
    }

    /* ---------- Quizzes ---------- */

    public function createQuiz(int $tutorId, array $d, array $audience): int {
        $this->db->prepare("INSERT INTO tutor_quizzes (tutor_id, title, description, due_at, audience) VALUES (?, ?, ?, ?, ?)")
            ->execute([$tutorId, $d['title'], $d['description'], $d['due_at'], $audience[0]]);
        $id = (int) $this->db->lastInsertId();
        $this->setTargets('tutor_quiz_targets', 'quiz_id', $id, $audience[1]);
        return $id;
    }

    public function updateQuiz(int $id, array $d, array $audience): void {
        $this->db->prepare("UPDATE tutor_quizzes SET title = ?, description = ?, due_at = ?, audience = ? WHERE id = ?")
            ->execute([$d['title'], $d['description'], $d['due_at'], $audience[0], $id]);
        $this->setTargets('tutor_quiz_targets', 'quiz_id', $id, $audience[1]);
    }

    public function quizTargets(int $quizId): array {
        $st = $this->db->prepare("SELECT student_user_id FROM tutor_quiz_targets WHERE quiz_id = ?");
        $st->execute([$quizId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    public function quizzesForTutor(int $tutorId): array {
        $st = $this->db->prepare(
            "SELECT q.*,
                    (SELECT COUNT(*) FROM tutor_quiz_questions x WHERE x.quiz_id = q.id) AS questions,
                    (SELECT COUNT(*) FROM tutor_quiz_attempts t WHERE t.quiz_id = q.id AND t.submitted_at IS NOT NULL) AS submitted,
                    (SELECT COUNT(*) FROM tutor_quiz_attempts t WHERE t.quiz_id = q.id AND t.submitted_at IS NOT NULL AND t.graded_at IS NULL) AS ungraded
             FROM tutor_quizzes q WHERE q.tutor_id = ? ORDER BY q.created_at DESC"
        );
        $st->execute([$tutorId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getQuiz(int $id, ?int $tutorId = null): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_quizzes WHERE id = ?" . ($tutorId ? " AND tutor_id = " . (int) $tutorId : ''));
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function setPublished(int $id, bool $on): void {
        $this->db->prepare("UPDATE tutor_quizzes SET is_published = ? WHERE id = ?")->execute([$on ? 1 : 0, $id]);
    }

    public function deleteQuiz(int $tutorId, int $id): void {
        if (!$this->getQuiz($id, $tutorId)) return;
        $this->db->prepare("DELETE ans FROM tutor_quiz_answers ans JOIN tutor_quiz_attempts t ON t.id = ans.attempt_id WHERE t.quiz_id = ?")->execute([$id]);
        foreach (['tutor_quiz_attempts', 'tutor_quiz_questions', 'tutor_quiz_targets'] as $t) {
            $this->db->prepare("DELETE FROM $t WHERE quiz_id = ?")->execute([$id]);
        }
        $this->db->prepare("DELETE FROM tutor_quizzes WHERE id = ?")->execute([$id]);
    }

    public function questions(int $quizId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_quiz_questions WHERE quiz_id = ? ORDER BY order_index, id");
        $st->execute([$quizId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['options'] = json_decode((string) $r['options_json'], true) ?: [];
        }
        return $rows;
    }

    public function addQuestion(int $quizId, string $type, string $prompt, array $options, ?int $correct, int $points): void {
        $options = array_values(array_filter(array_map('trim', $options), 'strlen'));
        $next = $this->db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 FROM tutor_quiz_questions WHERE quiz_id = ?");
        $next->execute([$quizId]);
        $this->db->prepare(
            "INSERT INTO tutor_quiz_questions (quiz_id, qtype, prompt, options_json, correct_option, points, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([$quizId, $type, $prompt, $type === 'mcq' ? json_encode($options) : null, $type === 'mcq' ? $correct : null,
                    max(1, $points), (int) $next->fetchColumn()]);
    }

    public function deleteQuestion(int $quizId, int $questionId): void {
        $this->db->prepare("DELETE FROM tutor_quiz_questions WHERE id = ? AND quiz_id = ?")->execute([$questionId, $quizId]);
    }

    public function hasAttempts(int $quizId): bool {
        $st = $this->db->prepare("SELECT COUNT(*) FROM tutor_quiz_attempts WHERE quiz_id = ?");
        $st->execute([$quizId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function quizzesForStudent(int $studentUserId): array {
        $st = $this->db->prepare(
            "SELECT q.*, t.submitted_at, t.score, t.max_score, t.graded_at FROM tutor_quizzes q
             LEFT JOIN tutor_quiz_attempts t ON t.quiz_id = q.id AND t.student_user_id = :uid3
             WHERE q.is_published = 1 AND (" . $this->visibleSql('q', 'tutor_quiz_targets', 'quiz_id') . ")
             ORDER BY q.created_at DESC"
        );
        $st->execute([':uid1' => $studentUserId, ':uid2' => $studentUserId, ':uid3' => $studentUserId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function quizForStudent(int $studentUserId, int $id): ?array {
        foreach ($this->quizzesForStudent($studentUserId) as $q) {
            if ((int) $q['id'] === $id) return $q;
        }
        return null;
    }

    public function attempt(int $quizId, int $studentUserId): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_quiz_attempts WHERE quiz_id = ? AND student_user_id = ?");
        $st->execute([$quizId, $studentUserId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** question_id => answer row. */
    public function answers(int $attemptId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_quiz_answers WHERE attempt_id = ?");
        $st->execute([$attemptId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $out[(int) $a['question_id']] = $a;
        }
        return $out;
    }

    /** One submission per student. Multiple choice is marked now; if nothing needs the tutor, the attempt is graded. */
    public function submitQuiz(int $quizId, int $studentUserId, array $post): bool {
        if ($this->attempt($quizId, $studentUserId)) return false;
        $questions = $this->questions($quizId);
        $own = !$this->db->inTransaction();
        if ($own) $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO tutor_quiz_attempts (quiz_id, student_user_id, submitted_at) VALUES (?, ?, NOW())")
                ->execute([$quizId, $studentUserId]);
            $attemptId = (int) $this->db->lastInsertId();
            $ins = $this->db->prepare("INSERT INTO tutor_quiz_answers (attempt_id, question_id, selected_option, answer_text, score) VALUES (?, ?, ?, ?, ?)");
            $manual = false;
            foreach ($questions as $q) {
                $qid = (int) $q['id'];
                if ($q['qtype'] === 'mcq') {
                    $sel = isset($post['q'][$qid]) && $post['q'][$qid] !== '' ? (int) $post['q'][$qid] : null;
                    $score = ($sel !== null && $sel === (int) $q['correct_option']) ? (float) $q['points'] : 0.0;
                    $ins->execute([$attemptId, $qid, $sel, null, $score]);
                } else {
                    $manual = true;
                    $ins->execute([$attemptId, $qid, null, trim((string) ($post['q'][$qid] ?? '')), null]);
                }
            }
            $this->recalc($attemptId, !$manual);
            if ($own) $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($own) $this->db->rollBack();
            throw $e;
        }
    }

    private function recalc(int $attemptId, bool $graded, ?string $feedback = null): void {
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(a.score), 0) AS score, COALESCE(SUM(q.points), 0) AS max
             FROM tutor_quiz_answers a JOIN tutor_quiz_questions q ON q.id = a.question_id WHERE a.attempt_id = ?"
        );
        $st->execute([$attemptId]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        $this->db->prepare("UPDATE tutor_quiz_attempts SET score = ?, max_score = ?, graded_at = " . ($graded ? 'NOW()' : 'NULL')
            . ($feedback !== null ? ', feedback = ?' : '') . " WHERE id = ?")
            ->execute(array_merge([$t['score'], $t['max']], $feedback !== null ? [$feedback] : [], [$attemptId]));
    }

    public function quizAttempts(int $quizId): array {
        $st = $this->db->prepare(
            "SELECT t.*, " . str_replace('s.student_user_id', 't.student_user_id', self::NAME_SQL) . " FROM tutor_quiz_attempts t
             WHERE t.quiz_id = ? AND t.submitted_at IS NOT NULL ORDER BY t.submitted_at DESC"
        );
        $st->execute([$quizId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Tutor marks the written/code answers (and may override multiple choice); $scores = question_id => points. */
    public function gradeAttempt(int $quizId, int $attemptId, array $scores, string $feedback): ?array {
        $st = $this->db->prepare("SELECT * FROM tutor_quiz_attempts WHERE id = ? AND quiz_id = ?");
        $st->execute([$attemptId, $quizId]);
        $attempt = $st->fetch(PDO::FETCH_ASSOC);
        if (!$attempt) return null;
        $points = [];
        foreach ($this->questions($quizId) as $q) {
            $points[(int) $q['id']] = (float) $q['points'];
        }
        $up = $this->db->prepare("UPDATE tutor_quiz_answers SET score = ? WHERE attempt_id = ? AND question_id = ?");
        foreach ($scores as $qid => $val) {
            $qid = (int) $qid;
            if (!isset($points[$qid]) || $val === '') continue;
            $up->execute([max(0, min($points[$qid], (float) $val)), $attemptId, $qid]);
        }
        $this->recalc($attemptId, true, $feedback);
        return $attempt;
    }

    /** Quiz attempts waiting for the tutor to mark written/code answers (assignments: AssignmentModel::awaitingCount). */
    public function ungradedCount(int $tutorId): int {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM tutor_quiz_attempts t JOIN tutor_quizzes q ON q.id = t.quiz_id
             WHERE q.tutor_id = ? AND t.submitted_at IS NOT NULL AND t.graded_at IS NULL"
        );
        $st->execute([$tutorId]);
        return (int) $st->fetchColumn();
    }

    public static function dueLabel(?string $due): string {
        if (!$due) return 'No due date';
        $ts = strtotime($due);
        return ($ts < time() ? 'Was due ' : 'Due ') . date('d M Y, H:i', $ts);
    }
}
