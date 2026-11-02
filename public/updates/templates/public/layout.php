<?php
// public/templates/public/layout.php — Dazu Training Institute shell (replaces legacy theme layout)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';

if (!empty($extra_css) && empty($extra_head)) {
    $extra_head = $extra_css;
}

require __DIR__ . '/dazu_layout.php';
