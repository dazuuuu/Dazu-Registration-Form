<?php
// public/courses/fee-structure.php — view/download formal school-fee document
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

$html = course_fee_structure_document_html($course);
$download = !empty($_GET['download']);
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower(trim((string) ($course['abbreviation'] ?? 'course')))) ?: 'course';
$title = htmlspecialchars($course['title'] ?? 'Fee structure');
$id = (int) $course['id'];

$pdf = $download ? course_fee_structure_render_pdf($course) : null;
if ($pdf !== null) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '-fee-structure.pdf"');
    echo $pdf;
    exit();
}

$filename = $slug . '-fee-structure.html';

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . ' — Fee Structure</title></head><body style="margin:0;padding:24px;background:#fff;">'
        . $html
        . '</body></html>';
    exit();
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . ' — Fee Structure</title>'
    . '<style>body{background:#f5f5f5;padding:24px;font-family:Georgia,serif;}@media print{body{background:#fff;padding:0;}.actions{display:none;}}.actions{text-align:center;margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}.actions a,.actions button{padding:10px 20px;border-radius:0;text-decoration:none;font-weight:bold;cursor:pointer;border:2px solid #171717;font-size:14px;}.btn-download{background:#171717;color:#fff;}.btn-print{background:#fff;color:#171717;}.btn-back{background:#fff;color:#15A24A;border-color:#15A24A;}</style></head><body>'
    . $html
    . '<div class="actions">'
    . '<a class="btn-download" href="' . htmlspecialchars(url('courses/fee-structure.php?id=' . $id . '&download=1')) . '">Download</a>'
    . '<button type="button" class="btn-print" onclick="window.print()">Print</button>'
    . '<a class="btn-back" href="' . htmlspecialchars(dazu_course_apply_url($course)) . '">Back to course</a>'
    . '</div></body></html>';
