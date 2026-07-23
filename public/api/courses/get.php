<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_catalog.php';

use Models\CourseModel;

require_method('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Course id required'], 422);
}

$pdo = api_pdo();
$model = new CourseModel($pdo);
$course = $model->getById($id);
if (!$course || empty($course['is_active'])) {
    json_response(['success' => false, 'message' => 'Course not found'], 404);
}

$enriched = course_catalog_enrich($course);
json_response(['success' => true, 'data' => api_shape_course($enriched)]);
