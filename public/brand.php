<?php
// public/brand.php — streams the brand images (?f=logo or ?f=mark). If the file is ever missing it serves a built-in
// SVG mark instead, so a logo link can never come back broken.
require_once __DIR__ . '/../app/v1/config/paths.php';
require_once __DIR__ . '/../app/v1/helpers/brand.php';

$name = $_GET['f'] ?? 'logo';
$file = brand_file($name);

if ($file) {
    $etag = '"' . md5($name . filemtime($file) . filesize($file)) . '"';
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=300');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><path d="M6 6h50a50 50 0 0 1 0 108H6v-20h50a30 30 0 0 0 0-68H26v40H6z" fill="#3fa028"/></svg>';
