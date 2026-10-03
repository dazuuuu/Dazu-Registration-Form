<?php
// public/api/blog/update.php
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
    
    $blog_id = $_POST['blog_id'] ?? null;
    if (!$blog_id) {
        sendJsonResponse(false, 'Blog ID is required');
    }
    
    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    // Cover image: optional when replacing, but a post must always end up with one
    require_once __DIR__ . '/../../../app/v1/helpers/blog_image.php';
    $featured_image = null;
    $cover = blog_save_cover($_FILES['featured_image'] ?? null);
    if ($cover['ok']) {
        $featured_image = $cover['url'];
    } elseif (empty($cover['none'])) {
        sendJsonResponse(false, $cover['error']);
    } else {
        $chk = $pdo->prepare('SELECT featured_image FROM blogs WHERE id = ?');
        $chk->execute([(int) $blog_id]);
        if (trim((string) $chk->fetchColumn()) === '') {
            sendJsonResponse(false, 'A cover image is required.');
        }
    }

    // Handle tags
    $tags = [];
    if (!empty($_POST['tags'])) {
        $tags = explode(',', $_POST['tags']);
        $tags = array_map('trim', $tags);
    }
    $_POST['tags'] = $tags;
    
    $blogController = new BlogController($pdo);
    $result = $blogController->update($blog_id, $_POST, $featured_image);
    
    sendJsonResponse($result['success'], $result['message']);
    
} catch (Throwable $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>