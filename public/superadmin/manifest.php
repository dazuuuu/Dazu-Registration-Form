<?php
// public/superadmin/manifest.php — PWA manifest for the superadmin app (WEB_ROOT-aware).
require_once __DIR__ . '/../../app/v1/config/paths.php';

header('Content-Type: application/manifest+json; charset=utf-8');
$root = WEB_ROOT === '' ? '' : rtrim(WEB_ROOT, '/');

echo json_encode([
    'id' => $root . '/superadmin/',
    'name' => 'DazuHub Superadmin',
    'short_name' => 'DazuHub Admin',
    'description' => 'Finance, students, tutors, staff and courses for Dazu Training Institute.',
    'start_url' => $root . '/superadmin/dashboard.php',
    'scope' => $root . '/superadmin/',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#1b1c30',
    'theme_color' => '#1b1c30',
    'icons' => [
        ['src' => $root . '/assets/superadmin/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . '/assets/superadmin/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . '/assets/superadmin/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => $root . '/assets/superadmin/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    'shortcuts' => [
        ['name' => 'Enrol a student', 'url' => $root . '/superadmin/students/enroll.php'],
        ['name' => 'Payments', 'url' => $root . '/superadmin/payments/index.php'],
        ['name' => 'Salaries', 'url' => $root . '/superadmin/payroll/index.php'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
