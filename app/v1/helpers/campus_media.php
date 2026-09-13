<?php

require_once __DIR__ . '/../models/CampusMediaModel.php';

if (!function_exists('campus_media_for_homepage')) {
    function campus_media_for_homepage(PDO $pdo, int $limit = 8): array
    {
        return (new CampusMediaModel($pdo))->getAll('active', $limit, 0);
    }
}

if (!function_exists('campus_media_url')) {
    function campus_media_url(?string $path): string
    {
        if (!$path) {
            return '';
        }
        if (function_exists('settings_asset_url')) {
            return settings_asset_url($path);
        }
        if (preg_match('#^(https?:)?/#', $path)) {
            return $path;
        }
        return function_exists('url') ? url($path) : '/' . ltrim($path, '/');
    }
}
