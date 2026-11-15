<?php
// Web app manifest for an installable portal. Expects $pwa = [portal, name, short_name, description, start, shortcuts[]].
require_once __DIR__ . '/../../../app/v1/config/paths.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$root = WEB_ROOT === '' ? '' : rtrim(WEB_ROOT, '/');
$scope = $root . '/' . $pwa['portal'] . '/';
// Students and tutors have their own app icons; the other portals use the general Dazu icon.
$ico = in_array($pwa['portal'], ['students', 'tutor'], true) ? $pwa['portal'] : 'favicon';
$mask = $ico === 'favicon' ? 'favicon' : $ico . '-maskable';
// Bump DAZU_ICON_VERSION when the icons change: the new file names make installed apps (Android/Chrome/Edge) fetch the new icon.
$iv = '?v=' . (defined('DAZU_ICON_VERSION') ? DAZU_ICON_VERSION : '2');
echo json_encode([
    'id' => $scope,
    'name' => $pwa['name'],
    'short_name' => $pwa['short_name'],
    'description' => $pwa['description'],
    // The clean address (no .php, no index.php), so opening the app never starts with a redirect.
    'start_url' => url($pwa['portal'] . '/' . $pwa['start']),
    'scope' => $scope,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#44A024',
    'icons' => [
        ['src' => $root . "/assets/icons/$ico-192.png$iv", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . "/assets/icons/$ico-512.png$iv", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . "/assets/icons/$mask-512.png$iv", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => array_map(fn($s) => ['name' => $s[0], 'url' => url($pwa['portal'] . '/' . $s[1])], $pwa['shortcuts']),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
