<?php
// Bootstrap course list + apply modal for public layout
if (!empty($skip_apply_modal)) {
    return;
}

if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}

require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_catalog.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$dh_apply_modal_courses] = course_catalog_for_display($courseModel);

include __DIR__ . '/apply_modal.php';
