<?php
// public/api/blog/delete_section.php
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

    $sectionId = $_POST['section_id'] ?? null;
    if (!$sectionId) {
        sendJsonResponse(false, 'Section ID is required');
    }

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $blogController = new BlogController($pdo);
    $result = $blogController->deleteSection($sectionId);

    sendJsonResponse($result ? true : false, $result ? 'Block deleted successfully' : 'Failed to delete block');

} catch (Throwable $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
