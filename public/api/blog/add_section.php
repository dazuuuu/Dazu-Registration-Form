<?php
require_once __DIR__ . '/_init.php';

try {
    blog_api_require_admin();

    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

    $blogController = new BlogController($pdo);

    if (isset($_FILES['media_file'])) {
        $_POST['media_file'] = $_FILES['media_file'];
    }

    $result = $blogController->addSection($_POST['blog_id'], $_POST, $_POST['sort_order'] ?? 0);

    blog_api_response((bool) $result, $result ? 'Section added successfully' : 'Failed to add section');
} catch (Throwable $e) {
    blog_api_response(false, 'Server error: ' . $e->getMessage());
}
