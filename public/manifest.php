<?php
// public/manifest.php — PWA manifest with WEB_ROOT-aware URLs
require_once __DIR__ . '/../app/v1/config/paths.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$start = WEB_ROOT === '' ? '/' : WEB_ROOT . '/';
$iconBase = WEB_ROOT === '' ? '' : WEB_ROOT;

echo json_encode([
    'name' => 'Dazu Hub Institute',
    'short_name' => 'Dazu Hub',
    'description' => 'Tech training institute — courses, student portal, and learning management.',
    'start_url' => $start,
    'scope' => $start,
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#ffffff',
    'theme_color' => '#2E7D32',
    'icons' => [
        [
            'src' => $iconBase . '/assets/icons/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => $iconBase . '/assets/icons/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
