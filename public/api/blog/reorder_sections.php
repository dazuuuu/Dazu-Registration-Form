<?php
// public/api/blog/reorder_sections.php
// Accepts POST order[] = array of section IDs in their new display order.
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

    $order = $_POST['order'] ?? [];
    if (!is_array($order) || empty($order)) {
        sendJsonResponse(false, 'Order is required');
    }

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $blogController = new BlogController($pdo);

    $sortMap = [];
    foreach (array_values($order) as $index => $sectionId) {
        $sortMap[(int) $sectionId] = $index;
    }

    $result = $blogController->reorderSections($sortMap);

    sendJsonResponse($result, $result ? 'Order saved' : 'Failed to save order');

} catch (Throwable $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
