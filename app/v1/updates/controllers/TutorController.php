<?php
// app/controllers/TutorController.php
namespace Controllers;

use Models\Connection;
use Models\TutorModel;
use Models\CurriculumModel;
use Models\SubmissionModel;
use Models\ProgressModel;

require_once __DIR__ . '/../services/TutorNotifier.php';

class TutorController {
    private $db;
    private $tutorModel;
    private $notifier;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->tutorModel = new TutorModel($this->db);
        $this->notifier = new \TutorNotifier();
    }

    public function createAndEmail(array $data, array $courseIds, bool $sendEmail = true): array {
        $result = $this->tutorModel->create($data, $courseIds);
        if (!$result['ok']) return $result;

        if ($sendEmail) {
            $tutor = $this->tutorModel->getById($result['tutor_id']);
            $courses = array_column($tutor['courses'] ?? [], 'title');
            $tutor['full_name'] = $data['full_name'];
            $mail = $this->notifier->sendCredentials($tutor, $result['temp_password'], $courses);
            if ($mail['ok']) {
                $this->tutorModel->markCredentialsSent($result['tutor_id']);
            }
            $result['email_sent'] = $mail['ok'];
            if (!$mail['ok']) $result['email_error'] = $mail['message'];
        }
        return $result;
    }

    public function sendCredentials(int $tutorId): array {
        $tutor = $this->tutorModel->getById($tutorId);
        if (!$tutor) return ['ok' => false, 'error' => 'Tutor not found.'];

        $tempPassword = bin2hex(random_bytes(8));
        $stmt = $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
        $stmt->execute([':h' => password_hash($tempPassword, PASSWORD_DEFAULT), ':id' => $tutor['user_id']]);

        $tutor['full_name'] = $this->tutorModel->fullName($tutor);
        $courses = array_column($tutor['courses'] ?? [], 'title');
        $mail = $this->notifier->sendCredentials($tutor, $tempPassword, $courses);
        if ($mail['ok']) $this->tutorModel->markCredentialsSent($tutorId);

        return $mail['ok']
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Password reset but email failed: ' . $mail['message']];
    }

    public function gradeSubmission(int $submissionId, int $tutorUserId, float $marks, string $feedback): array {
        $submissionModel = new SubmissionModel($this->db);
        $sub = $submissionModel->getById($submissionId);
        if (!$sub) return ['ok' => false, 'error' => 'Submission not found.'];

        $tutor = $this->tutorModel->getByUserId($tutorUserId);
        if (!$tutor || !$this->tutorModel->tutorTeachesCourse((int) $tutor['id'], (int) $sub['course_id'])) {
            return ['ok' => false, 'error' => 'You are not assigned to this course.'];
        }

        if (!$submissionModel->grade($submissionId, $marks, $feedback, $tutorUserId)) {
            return ['ok' => false, 'error' => 'Could not grade submission.'];
        }

        $unitId = (int) ($sub['test_unit_id'] ?? $sub['project_unit_id'] ?? 0);
        if ($unitId) {
            $progress = new ProgressModel($this->db);
            $progress->evaluateUnitCompletion((int) $sub['registration_id'], $unitId, new CurriculumModel($this->db));
        }

        $maxMarks = 100;
        if ($sub['submission_type'] === 'test' && $sub['test_id']) {
            $t = (new CurriculumModel($this->db))->getTestById((int) $sub['test_id']);
            $maxMarks = (float) ($t['max_marks'] ?? 100);
        } elseif ($sub['project_id']) {
            $p = (new CurriculumModel($this->db))->getProjectById((int) $sub['project_id']);
            $maxMarks = (float) ($p['max_marks'] ?? 100);
        }

        $sub['marks'] = $marks;
        $sub['feedback'] = $feedback;
        $stmt = $this->db->prepare("SELECT email, full_name FROM registrations WHERE id = :id");
        $stmt->execute([':id' => $sub['registration_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $this->notifier->sendGradedNotice($student['email'], $student['full_name'], $sub, $maxMarks);
        }

        return ['ok' => true];
    }
}
