<?php
// app/controllers/TutorController.php
namespace Controllers;

use Models\Connection;
use Models\TutorModel;
use Models\CurriculumModel;
use Models\SubmissionModel;
use Models\ProgressModel;

require_once __DIR__ . '/../services/TutorNotifier.php';
require_once __DIR__ . '/../services/PortalNotifications.php';

class TutorController {
    private $db;
    private $tutorModel;
    private $notifier;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->tutorModel = new TutorModel($this->db);
        $this->notifier = new \TutorNotifier();
    }

    public function createAndEmail(array $data, array $studentIds, bool $sendEmail = true): array {
        $result = $this->tutorModel->create($data, $studentIds);
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

    /** Mark a student's answer to a question in one of this tutor's own modules, and email the result. */
    public function gradeModuleSubmission(int $submissionId, int $tutorUserId, float $marks, string $feedback): array {
        require_once __DIR__ . '/../models/TutorCourseModel.php';
        $tutor = $this->tutorModel->getByUserId($tutorUserId);
        $sub = $tutor ? (new \Models\TutorCourseModel($this->db))->gradeModuleSubmission($tutorUserId, $submissionId, $marks, $feedback, $tutorUserId) : null;
        if (!$sub) return ['ok' => false, 'error' => 'Submission not found.'];

        $stmt = $this->db->prepare("SELECT email, full_name FROM registrations WHERE id = :id");
        $stmt->execute([':id' => $sub['registration_id']]);
        if ($student = $stmt->fetch()) {
            $sub['submission_type'] = 'question';
            $this->notifier->sendGradedNotice($student['email'], $student['full_name'], $sub, (float) ($sub['max_score'] ?: 10));
        }
        $this->notifyGraded((int) $sub['registration_id'], (string) $sub['item_title'], $marks, (float) ($sub['max_score'] ?: 10),
            'students/course/tutor_module.php?id=' . (int) $sub['module_id'], $tutorUserId);
        return ['ok' => true];
    }

    public function gradeSubmission(int $submissionId, int $tutorUserId, float $marks, string $feedback): array {
        $submissionModel = new SubmissionModel($this->db);
        $sub = $submissionModel->getById($submissionId);
        if (!$sub) return ['ok' => false, 'error' => 'Submission not found.'];

        $tutor = $this->tutorModel->getByUserId($tutorUserId);
        if (!$tutor || !$this->tutorModel->tutorHasStudent((int) $tutor['id'], (int) $sub['registration_id'])) {
            return ['ok' => false, 'error' => 'You are not assigned to this student.'];
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
        } elseif ($sub['submission_type'] === 'project' && $sub['project_id']) {
            $p = (new CurriculumModel($this->db))->getProjectById((int) $sub['project_id']);
            $maxMarks = (float) ($p['max_marks'] ?? 100);
        } elseif ($sub['submission_type'] === 'assignment' && $sub['module_content_id']) {
            $stmt = $this->db->prepare("SELECT max_score FROM module_contents WHERE id = ?");
            $stmt->execute([(int)$sub['module_content_id']]);
            $mc = $stmt->fetch();
            $maxMarks = $mc ? (float)($mc['max_score'] ?? 100) : 100;
        }

        $sub['marks'] = $marks;
        $sub['feedback'] = $feedback;
        $stmt = $this->db->prepare("SELECT email, full_name FROM registrations WHERE id = :id");
        $stmt->execute([':id' => $sub['registration_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $this->notifier->sendGradedNotice($student['email'], $student['full_name'], $sub, $maxMarks);
        }
        $this->notifyGraded((int) $sub['registration_id'], (string) $sub['item_title'], $marks, $maxMarks, 'students/course/index.php', $tutorUserId);

        return ['ok' => true];
    }

    private function notifyGraded(int $registrationId, string $item, float $marks, float $max, string $path, int $tutorUserId): void {
        $st = $this->db->prepare("SELECT user_id FROM registrations WHERE id = ?");
        $st->execute([$registrationId]);
        $fmt = fn($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
        \PortalNotifications::notify($this->db, (int) $st->fetchColumn(), 'graded', 'Your answer was marked: ' . $fmt($marks) . '/' . $fmt($max),
            $item, $path, \PortalNotifications::photoOf($this->db, $tutorUserId));
    }
}
