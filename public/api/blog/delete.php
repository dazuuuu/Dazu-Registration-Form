<?php
require_once __DIR__ . '/_init.php';

try {
    blog_api_require_admin();

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $blog_id = $input['id'] ?? null;

    if (!$blog_id) {
        blog_api_response(false, 'Blog ID is required');
    }

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $blogController = new BlogController($pdo);
    $result = $blogController->delete($blog_id);

    blog_api_response((bool) $result['success'], $result['message']);
} catch (Throwable $e) {
    blog_api_response(false, 'Server error: ' . $e->getMessage());
}
