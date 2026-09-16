<?php
// app/models/SubmissionModel.php
namespace Models;

class SubmissionModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function submit(int $registrationId, string $type, int $targetId, string $answerText, ?string $filePath = null): array {
        if (!in_array($type, ['test', 'project'], true)) {
            return ['ok' => false, 'error' => 'Invalid submission type.'];
        }
        if (empty(trim($answerText)) && empty($filePath)) {
            return ['ok' => false, 'error' => 'Please provide an answer or upload a file.'];
        }

        $col = $type === 'test' ? 'test_id' : 'project_id';
        $stmt = $this->db->prepare(
            "SELECT id, status FROM student_submissions
             WHERE registration_id = :rid AND submission_type = :type AND $col = :tid
             ORDER BY submitted_at DESC LIMIT 1"
        );
        $stmt->execute([':rid' => $registrationId, ':type' => $type, ':tid' => $targetId]);
        $existing = $stmt->fetch();

        if ($existing && $existing['status'] === 'graded') {
            return ['ok' => false, 'error' => 'This has already been graded. Contact your tutor to resubmit.'];
        }

        if ($existing) {
            $upd = $this->db->prepare(
                "UPDATE student_submissions SET answer_text = :text, file_path = :file,
                 status = 'submitted', submitted_at = NOW(), marks = NULL, feedback = NULL, graded_by = NULL, graded_at = NULL
                 WHERE id = :id"
            );
            $upd->execute([':text' => $answerText, ':file' => $filePath, ':id' => $existing['id']]);
            return ['ok' => true, 'id' => (int) $existing['id']];
        }

        $sql = "INSERT INTO student_submissions (registration_id, submission_type, test_id, project_id, answer_text, file_path)
                VALUES (:rid, :type, :test_id, :project_id, :text, :file)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':rid'        => $registrationId,
            ':type'       => $type,
            ':test_id'    => $type === 'test' ? $targetId : null,
            ':project_id' => $type === 'project' ? $targetId : null,
            ':text'       => $answerText,
            ':file'       => $filePath,
        ]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function getGradedSubmission(int $registrationId, string $type, int $targetId): ?array {
        $col = $type === 'test' ? 'test_id' : 'project_id';
        $stmt = $this->db->prepare(
            "SELECT * FROM student_submissions
             WHERE registration_id = :rid AND submission_type = :type AND $col = :tid AND status = 'graded'
             ORDER BY graded_at DESC LIMIT 1"
        );
        $stmt->execute([':rid' => $registrationId, ':type' => $type, ':tid' => $targetId]);
        return $stmt->fetch() ?: null;
    }

    public function getSubmission(int $registrationId, string $type, int $targetId): ?array {
        $col = $type === 'test' ? 'test_id' : 'project_id';
        $stmt = $this->db->prepare(
            "SELECT * FROM student_submissions
             WHERE registration_id = :rid AND submission_type = :type AND $col = :tid
             ORDER BY submitted_at DESC LIMIT 1"
        );
        $stmt->execute([':rid' => $registrationId, ':type' => $type, ':tid' => $targetId]);
        return $stmt->fetch() ?: null;
    }

    public function getPendingForTutor(int $tutorId): array {
        $stmt = $this->db->prepare(
            "SELECT s.*, r.full_name, r.registration_no, c.title AS course_title,
                    COALESCE(t.title, p.title) AS item_title, s.submission_type
             FROM student_submissions s
             JOIN registrations r ON r.id = s.registration_id
             JOIN courses c ON r.course_id = c.id
             JOIN course_tutors ct ON ct.course_id = c.id AND ct.tutor_id = :tid
             LEFT JOIN unit_tests t ON s.test_id = t.id
             LEFT JOIN unit_projects p ON s.project_id = p.id
             WHERE s.status = 'submitted'
             ORDER BY s.submitted_at ASC"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    public function getForRegistration(int $registrationId): array {
        $stmt = $this->db->prepare(
            "SELECT s.*, COALESCE(t.title, p.title) AS item_title
             FROM student_submissions s
             LEFT JOIN unit_tests t ON s.test_id = t.id
             LEFT JOIN unit_projects p ON s.project_id = p.id
             WHERE s.registration_id = :rid ORDER BY s.submitted_at DESC"
        );
        $stmt->execute([':rid' => $registrationId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT s.*, r.full_name, r.registration_no, r.course_id, c.title AS course_title,
                    COALESCE(t.title, p.title) AS item_title, t.unit_id AS test_unit_id, p.unit_id AS project_unit_id
             FROM student_submissions s
             JOIN registrations r ON r.id = s.registration_id
             JOIN courses c ON r.course_id = c.id
             LEFT JOIN unit_tests t ON s.test_id = t.id
             LEFT JOIN unit_projects p ON s.project_id = p.id
             WHERE s.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function grade(int $submissionId, float $marks, string $feedback, int $gradedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE student_submissions
             SET marks = :marks, feedback = :fb, status = 'graded', graded_by = :by, graded_at = NOW()
             WHERE id = :id AND status = 'submitted'"
        );
        return $stmt->execute([
            ':marks' => $marks,
            ':fb'    => $feedback,
            ':by'    => $gradedBy,
            ':id'    => $submissionId,
        ]);
    }
}
