<?php
// public/courses/brochure.php — serve uploaded course brochure PDF
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$course = course_resolve_for_page($courseModel);

if (!course_is_shareable($course)) {
    http_response_code(404);
    echo 'Brochure not found.';
    exit();
}

$path = trim((string) ($course['brochure_path'] ?? ''));
if ($path === '') {
    http_response_code(404);
    echo 'Brochure not found.';
    exit();
}

if (preg_match('#^https?://#i', $path)) {
    header('Location: ' . $path);
    exit();
}

if (!defined('PROJECT_ROOT')) {
    require_once __DIR__ . '/../../app/v1/config/paths.php';
}
$rel = ltrim($path, '/');
$abs = PROJECT_ROOT . '/public/' . $rel;
if (!is_file($abs)) {
    $abs = public_path($rel);
}
if (!is_file($abs)) {
    http_response_code(404);
    echo 'Brochure file missing.';
    exit();
}

$slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
$filename = $slug . '-brochure.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit();
