<?php
// public/courses/cover.php — public course cover for WhatsApp/Facebook/Twitter link previews.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$fallback = 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=1200&h=630&q=80';

$pdo = Connection::getInstance()->getConnection();
$course = course_resolve_for_page(new CourseModel($pdo));
if ($course) {
    $course = course_catalog_enrich($course);
}

if (!course_is_shareable($course)) {
    header('Location: ' . $fallback, true, 302);
    exit();
}

$img = trim((string) dazu_course_image_url($course));
if ($img === '' || $img === 'null') {
    header('Location: ' . $fallback, true, 302);
    exit();
}

if (preg_match('#^https?://#i', $img)) {
    if (str_contains($img, 'images.unsplash.com')) {
        $img = preg_replace('/([?&])w=\d+/', '$1w=1200', $img) ?: $img;
        if (!str_contains($img, 'h=')) {
            $img .= (str_contains($img, '?') ? '&' : '?') . 'h=630';
        } else {
            $img = preg_replace('/([?&])h=\d+/', '$1h=630', $img) ?: $img;
        }
    }
    header('Location: ' . $img, true, 302);
    exit();
}

$pathPart = parse_url($img, PHP_URL_PATH);
$rel = ltrim((string) ($pathPart ?: $img), '/');
if (str_contains($rel, '..')) {
    header('Location: ' . $fallback, true, 302);
    exit();
}

$candidates = [
    public_path($rel),
    PROJECT_ROOT . '/public/' . $rel,
];
if (preg_match('#(uploads/courses/covers/[^/?#]+)$#', $rel, $m)) {
    array_unshift($candidates, public_path($m[1]));
}

$file = null;
foreach ($candidates as $abs) {
    if (is_string($abs) && is_file($abs) && is_readable($abs)) {
        $file = $abs;
        break;
    }
}

if ($file === null) {
    header('Location: ' . $fallback, true, 302);
    exit();
}

$mime = 'image/jpeg';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($file);
    if (is_string($detected) && str_starts_with($detected, 'image/')) {
        $mime = $detected;
    }
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');
readfile($file);
exit();
