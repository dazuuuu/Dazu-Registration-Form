<?php
require_once __DIR__ . '/_init.php';

try {
    blog_api_require_admin();

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (empty($input['name'])) {
        blog_api_response(false, 'Category name is required');
    }

    $blogController = new BlogController($pdo);
    $result = $blogController->createCategory(
        $input['name'],
        $input['description'] ?? null,
        $input['color'] ?? '#667eea'
    );

    blog_api_response(
        (bool) $result['success'],
        $result['message'],
        ['category_id' => $result['category_id'] ?? null]
    );
} catch (Throwable $e) {
    blog_api_response(false, 'Server error: ' . $e->getMessage());
}
