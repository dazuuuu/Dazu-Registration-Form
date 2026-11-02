<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/controllers/TestimonialController.php';

require_method('GET');
$featured = !empty($_GET['featured']);
$type = $_GET['type'] ?? null;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

$controller = new TestimonialController(api_pdo());
$rows = $controller->getApproved($limit, $featured);

if ($type === 'video') {
    $rows = array_values(array_filter($rows, fn($r) => ($r['testimonial_type'] ?? 'text') === 'video'));
} elseif ($type === 'text') {
    $rows = array_values(array_filter($rows, fn($r) => ($r['testimonial_type'] ?? 'text') !== 'video'));
}

json_response([
    'success' => true,
    'data' => array_map('api_shape_testimonial', $rows),
    'stats' => $controller->getStats(),
]);
