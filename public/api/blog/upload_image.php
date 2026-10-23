<?php
// Image inserted in the blog editor: saved (compressed) as a file, the editor gets its URL instead of embedding base64.
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'], $_SESSION['role_id']) || $_SESSION['role_id'] > 2 || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
$up = upload_image($_FILES['image'] ?? [], public_path('uploads/blogs/content'), url('uploads/blogs/content'), ['jpg', 'jpeg', 'png', 'webp', 'gif'], 20 * 1024 * 1024);
echo json_encode($up['ok'] ? ['ok' => true, 'url' => $up['path']] : ['ok' => false, 'error' => $up['error'] ?? 'Upload failed']);
