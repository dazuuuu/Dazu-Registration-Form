<?php
/**
 * Pentagon UI — packages endpoint (courses from database).
 * GET /api/packages.php?limit=12&featured=1
 */
require_once __DIR__ . '/_lib/bootstrap.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/helpers/pentagon_api.php';

use Models\CourseModel;

require_method('GET');
$pdo = api_pdo();
$model = new CourseModel($pdo);
[$courses] = course_catalog_for_display($model);

$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 0;
if ($limit > 0) {
    $courses = array_slice($courses, 0, $limit);
}

json_response([
    'status' => 'success',
    'success' => true,
    'count' => count($courses),
    'data' => array_map('pentagon_shape_package', $courses),
]);
