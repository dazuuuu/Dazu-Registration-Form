<?php
// public/api/blog/create.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function sendJsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        sendJsonResponse(false, 'Your session has ended. Please sign in again (your text is still in the editor — copy it first).');
    }
    $_SESSION['last_activity'] = time();   // saving a post is activity
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method');
    }
    
    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    // Cover image is mandatory
    require_once __DIR__ . '/../../../app/v1/helpers/blog_image.php';
    @set_time_limit(120);
    $cover = blog_save_cover($_FILES['featured_image'] ?? null);
    if (!$cover['ok']) {
        sendJsonResponse(false, $cover['error']);
    }
    $featured_image = $cover['url'];

    // Handle tags
    $tags = [];
    if (!empty($_POST['tags'])) {
        $tags = explode(',', $_POST['tags']);
        $tags = array_map('trim', $tags);
    }
    
    $_POST['tags'] = $tags;
    
    // After the cover upload: pasted base64 images become compressed files.
    if (isset($_POST['content'])) $_POST['content'] = blog_inline_images_to_files((string) $_POST['content']);
    $blogController = new BlogController($pdo);
    $result = $blogController->create($_POST, $featured_image);
    
    sendJsonResponse($result['success'], $result['message'], ['blog_id' => $result['blog_id'] ?? null]);
    
} catch (Throwable $e) {
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>