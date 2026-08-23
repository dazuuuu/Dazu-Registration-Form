<?php
// Site branding: logo path and institute name (Admin → Settings or defaults).

if (!function_exists('site_default_logo_rel_path')) {
    function site_default_logo_rel_path(): string
    {
        return 'assets/images/logo/logo.png';
    }
}

if (!function_exists('site_institute_name')) {
    function site_institute_name(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = 'Dazu Institute';
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            if (isset($pdo)) {
                $settings = new SettingModel($pdo);
                $val = trim((string) $settings->get('site_name', ''));
                if ($val !== '') {
                    $cached = $val;
                }
            }
        } catch (Throwable $e) {
            // use default
        }

        return $cached;
    }
}

if (!function_exists('site_logo_rel_path')) {
    function site_logo_rel_path(): string
    {
        static $cached = false;
        static $path = null;
        if ($cached) {
            return $path ?? site_default_logo_rel_path();
        }
        $cached = true;
        $path = site_default_logo_rel_path();
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                require_once __DIR__ . '/../config/db_connect.php';
            }
            if (isset($pdo)) {
                $settings = new SettingModel($pdo);
                $val = trim((string) $settings->get('logo_path', ''));
                if ($val !== '') {
                    $path = $val;
                }
            }
        } catch (Throwable $e) {
            // keep default
        }
        return $path ?? site_default_logo_rel_path();
    }
}

if (!function_exists('site_logo_rel_candidates')) {
    /** @return string[] */
    function site_logo_rel_candidates(): array
    {
        $paths = [];
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (isset($pdo)) {
                $settings = new SettingModel($pdo);
                $val = trim((string) $settings->get('logo_path', ''));
                if ($val !== '') {
                    $paths[] = $val;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        $paths[] = site_default_logo_rel_path();
        return array_values(array_unique($paths));
    }
}

if (!function_exists('site_logo_resolve_absolute')) {
    function site_logo_resolve_absolute(string $rel): ?string
    {
        if ($rel === '' || preg_match('#^https?://#i', $rel)) {
            return null;
        }
        if (!defined('PROJECT_ROOT')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $rel = ltrim($rel, '/');
        $candidates = [
            public_path($rel),
        ];
        if (defined('PROJECT_ROOT')) {
            $candidates[] = PROJECT_ROOT . '/public/' . $rel;
        }
        foreach ($candidates as $abs) {
            if (is_string($abs) && is_file($abs)) {
                return $abs;
            }
        }
        return null;
    }
}

if (!function_exists('site_logo_url')) {
    function site_logo_url(?string $fallback = null): string
    {
        if (!function_exists('settings_asset_url')) {
            require_once __DIR__ . '/uploads.php';
        }
        foreach (site_logo_rel_candidates() as $rel) {
            $url = settings_asset_url($rel);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }
        if ($fallback !== null && $fallback !== '') {
            return $fallback;
        }
        return url(site_default_logo_rel_path());
    }
}

if (!function_exists('site_logo_absolute_path')) {
    function site_logo_absolute_path(): ?string
    {
        foreach (site_logo_rel_candidates() as $rel) {
            $abs = site_logo_resolve_absolute($rel);
            if ($abs !== null) {
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
