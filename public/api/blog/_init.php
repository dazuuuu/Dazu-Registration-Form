<?php
// Shared bootstrap for blog API endpoints.
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function blog_api_response(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function blog_api_require_admin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] > 2) {
        blog_api_response(false, 'Unauthorized access');
    }
}

function blog_api_upload_featured(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $result = upload_image($file, public_path('uploads/blogs'), 'uploads/blogs');
    if (!$result['ok']) {
        blog_api_response(false, $result['error'] ?? 'Image upload failed');
    }

    return url($result['path']);
}

function blog_api_parse_tags($raw): array
{
    if (is_array($raw)) {
        return array_values(array_filter(array_map('trim', $raw)));
    }

    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}
