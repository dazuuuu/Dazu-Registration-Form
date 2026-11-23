<?php
// public/courses/brochure.php — download the course brochure PDF
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_fee_structure.php';
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

$rel = ltrim((string) ($course['brochure_path'] ?? ''), '/');
if ($rel === '') {
    // This course has no brochure of its own: fall back to the site-wide default (Admin → Courses → Brochure).
    $rel = ltrim((string) (course_default_brochure_path() ?? ''), '/');
}
if ($rel === '') {
    http_response_code(404);
    echo 'Brochure not found.';
    exit();
}
if (preg_match('#^https?://#i', $rel)) {
    header('Location: ' . $rel);
    exit();
}

$abs = public_path($rel);
if (!is_file($abs)) {
    $abs = PROJECT_ROOT . '/public/' . $rel;
}
if (!is_file($abs)) {
    http_response_code(404);
    echo 'Brochure file missing.';
    exit();
}

$slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $slug . '-brochure.pdf"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit();
