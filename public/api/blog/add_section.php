<?php
// public/api/blog/add_section.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        sendJsonResponse(false, 'Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $blogController = new BlogController($pdo);

    $data = $_POST;
    if (isset($_FILES['media_file'])) {
        $data['media_file'] = $_FILES['media_file'];
    }
    if (isset($_FILES['gallery_files'])) {
        $data['gallery_files'] = $_FILES['gallery_files'];
    }

    $blogId = $_POST['blog_id'] ?? null;
    if (!$blogId) {
        sendJsonResponse(false, 'Blog ID is required');
    }

    $result = $blogController->addSection($blogId, $data, $_POST['sort_order'] ?? 0);

    sendJsonResponse($result ? true : false, $result ? 'Block added successfully' : 'Failed to add block');

} catch (Throwable $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
