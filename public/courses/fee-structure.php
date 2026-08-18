<?php
// public/courses/fee-structure.php — view/download a course fee breakdown
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_fee_structure.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$course = course_resolve_for_page($courseModel);

if (!course_is_shareable($course)) {
    http_response_code(404);
    echo 'Fee structure not found.';
    exit();
}

$items = course_fee_structure_items($course);
$html = course_fee_structure_document_html($course, $items);
$download = !empty($_GET['download']);
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
$filename = $slug . '-fee-structure.html';
$title = htmlspecialchars($course['title'] ?? 'Fee structure');
$id = (int) $course['id'];

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . ' — Fee Structure</title></head><body>'
        . $html
        . '</body></html>';
    exit();
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . ' — Fee Structure</title>'
    . '<style>body{background:#f5f6f8;padding:24px;font-family:Arial,sans-serif;}@media print{body{background:#fff;padding:0;}.actions{display:none;}}.actions{text-align:center;margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}.actions a,.actions button{padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;cursor:pointer;border:none;font-size:14px;}.btn-download{background:#15A24A;color:#fff;}.btn-print{background:#fff;border:1px solid #ccc;color:#333;}</style></head><body>'
    . $html
    . '<div class="actions">'
    . '<a class="btn-download" href="' . htmlspecialchars(url('courses/fee-structure.php?id=' . $id . '&download=1')) . '">Download</a>'
    . '<button type="button" class="btn-print" onclick="window.print()">Print</button>'
    . '<a class="btn-print" href="' . htmlspecialchars(dazu_course_apply_url($course)) . '">Back to course</a>'
    . '</div></body></html>';
