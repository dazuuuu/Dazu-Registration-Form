<?php
// The web app manifest of the Dazu Secretary app (installable). New file; the shared portal manifest is not used.
require_once __DIR__ . '/../../app/v1/config/paths.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$root = WEB_ROOT === '' ? '' : rtrim(WEB_ROOT, '/');
$scope = $root . '/secretary/';
$icons = [];
foreach ([72, 96, 128, 144, 152, 180, 192, 384, 512] as $s) $icons[] = ['src' => $root . "/assets/icons/secretary-$s.png?v=2", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any'];
foreach ([192, 512] as $s) $icons[] = ['src' => $root . "/assets/icons/secretary-maskable-$s.png?v=2", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'maskable'];
echo json_encode([
    'id' => $scope,
    'name' => 'Dazu Secretary',
    'short_name' => 'Dazu Secretary',
    'description' => 'Your tasks, enquiries, blogs, calendar and alerts — Dazu Training Institute.',
    'start_url' => url('secretary/dashboard/index.php'),
    'scope' => $scope,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#048C44',
    'icons' => $icons,
    'shortcuts' => [['name' => 'Notifications', 'url' => url('secretary/notifications/index.php')]],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
