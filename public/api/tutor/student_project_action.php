<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentProjectController.php';

use Models\Connection;
use Models\TutorModel;

header('Content-Type: application/json');

try {
    Middleware::requireTutor();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $projectId = (int) ($input['project_id'] ?? 0);
    $action = $input['action'] ?? '';

    if ($projectId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $pdo = Connection::getInstance()->getConnection();
    $tutorModel = new TutorModel($pdo);
    $tutor = $tutorModel->getByUserId((int) $_SESSION['user_id']);

    if (!$tutor) {
        echo json_encode(['success' => false, 'message' => 'Tutor profile not found']);
        exit;
    }

    $controller = new StudentProjectController($pdo);
    $result = $action === 'approve'
        ? $controller->approve($projectId, (int) $tutor['id'])
        : $controller->reject($projectId, (int) $tutor['id']);

    echo json_encode($result);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
