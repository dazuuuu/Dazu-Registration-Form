<?php
// public/api/blog/search.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
    require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';
    
    $keyword = $_GET['q'] ?? $_GET['keyword'] ?? null;
    
    if (!$keyword) {
        echo json_encode(['success' => false, 'message' => 'Search keyword required']);
        exit();
    }
    
    $blogController = new BlogController($pdo);
    $results = $blogController->searchBlogs($keyword);
    
    echo json_encode(['success' => true, 'data' => $results, 'keyword' => $keyword]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>