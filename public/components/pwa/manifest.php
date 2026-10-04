<?php
// Web app manifest for an installable portal. Expects $pwa = [portal, name, short_name, description, start, shortcuts[]].
require_once __DIR__ . '/../../../app/v1/config/paths.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$root = WEB_ROOT === '' ? '' : rtrim(WEB_ROOT, '/');
$scope = $root . '/' . $pwa['portal'] . '/';
echo json_encode([
    'id' => $scope,
    'name' => $pwa['name'],
    'short_name' => $pwa['short_name'],
    'description' => $pwa['description'],
    'start_url' => $scope . $pwa['start'],
    'scope' => $scope,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#0D9488',
    'icons' => [
        ['src' => $root . '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $root . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => array_map(fn($s) => ['name' => $s[0], 'url' => $scope . $s[1]], $pwa['shortcuts']),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
