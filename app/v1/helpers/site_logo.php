<?php
// Site logo from Admin → Settings → Branding (logo_path). Navbar is not driven by this helper.

if (!function_exists('site_logo_rel_path')) {
    function site_logo_rel_path(): ?string
    {
        static $cached = false;
        static $path = null;
        if ($cached) {
            return $path;
        }
        $cached = true;
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            $settings = new SettingModel($pdo);
            $val = trim((string) $settings->get('logo_path', ''));
            $path = $val !== '' ? $val : null;
        } catch (Throwable $e) {
            $path = null;
        }
        return $path;
    }
}

if (!function_exists('site_logo_url')) {
    function site_logo_url(?string $fallback = null): string
    {
        $rel = site_logo_rel_path();
        if ($rel) {
            if (!function_exists('settings_asset_url')) {
                require_once __DIR__ . '/uploads.php';
            }
            $url = settings_asset_url($rel);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }
        return $fallback !== null && $fallback !== ''
            ? $fallback
            : 'https://i.ibb.co/fzfZFtGc/223025.jpg';
    }
}

if (!function_exists('site_logo_absolute_path')) {
    function site_logo_absolute_path(): ?string
    {
        $rel = site_logo_rel_path();
        if ($rel === null) {
            return null;
        }
        if (preg_match('#^https?://#i', $rel)) {
            return null;
        }
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $rel = ltrim($rel, '/');
        $candidates = [
            PROJECT_ROOT . '/public/' . $rel,
            public_path($rel),
        ];
        foreach ($candidates as $abs) {
            if (is_string($abs) && is_file($abs)) {
                return $abs;
            }
        }
        return null;
    }
}

if (!function_exists('site_logo_data_uri')) {
    function site_logo_data_uri(): ?string
    {
        $abs = site_logo_absolute_path();
        if (!$abs) {
            return null;
        }
        $bytes = @file_get_contents($abs);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($abs);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                $mime = $detected;
            }
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
