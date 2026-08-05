<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_catalog.php';

use Models\CourseModel;

require_method('GET');
$pdo = api_pdo();
$model = new CourseModel($pdo);
[$courses] = course_catalog_for_display($model);

json_response([
    'success' => true,
    'data' => array_map('api_shape_course', $courses),
]);
