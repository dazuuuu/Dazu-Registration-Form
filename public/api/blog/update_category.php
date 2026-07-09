<?php
require_once __DIR__ . '/_init.php';

try {
    blog_api_require_admin();

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $categoryId = (int) ($input['id'] ?? $input['category_id'] ?? 0);

    if ($categoryId <= 0) {
        blog_api_response(false, 'Category ID is required');
    }

    if (empty($input['name'])) {
        blog_api_response(false, 'Category name is required');
    }

    $blogController = new BlogController($pdo);
    $result = $blogController->updateCategory($categoryId, [
        'name' => $input['name'],
        'description' => $input['description'] ?? null,
        'color' => $input['color'] ?? '#667eea',
    ]);

    blog_api_response((bool) $result['success'], $result['message']);
} catch (Throwable $e) {
    blog_api_response(false, 'Server error: ' . $e->getMessage());
}
