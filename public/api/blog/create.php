<?php
require_once __DIR__ . '/_init.php';

try {
    blog_api_require_admin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        blog_api_response(false, 'Invalid request method');
    }

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $featured_image = blog_api_upload_featured($_FILES['featured_image'] ?? null);
    $_POST['tags'] = blog_api_parse_tags($_POST['tags'] ?? '');

    $blogController = new BlogController($pdo);
    $result = $blogController->create($_POST, $featured_image);

    blog_api_response(
        (bool) $result['success'],
        $result['message'],
        ['blog_id' => $result['blog_id'] ?? null]
    );
} catch (Throwable $e) {
    blog_api_response(false, 'Server error: ' . $e->getMessage());
}
