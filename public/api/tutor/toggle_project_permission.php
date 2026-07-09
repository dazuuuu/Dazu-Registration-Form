<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProjectModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentProjectController.php';

use Models\Connection;
use Models\TutorModel;
use Models\StudentModel;

header('Content-Type: application/json');

try {
    Middleware::requireTutor();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $registrationId = (int) ($input['registration_id'] ?? 0);
    $allowed = !empty($input['allowed']);

    if ($registrationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Registration ID is required']);
        exit;
    }

    $pdo = Connection::getInstance()->getConnection();
    $tutorModel = new TutorModel($pdo);
    $studentModel = new StudentModel($pdo);
    $tutor = $tutorModel->getByUserId((int) $_SESSION['user_id']);

    if (!$tutor) {
        echo json_encode(['success' => false, 'message' => 'Tutor profile not found']);
        exit;
    }

    $student = $studentModel->getById($registrationId);
    if (!$student || $student['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    if (!$tutorModel->tutorTeachesCourse((int) $tutor['id'], (int) $student['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'You are not assigned to this student\'s course']);
        exit;
    }

    if ($studentModel->setCanPostProject($registrationId, $allowed)) {
        echo json_encode([
            'success' => true,
            'message' => $allowed ? 'Student can now submit projects.' : 'Project submissions disabled for this student.',
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Failed to update permission']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
