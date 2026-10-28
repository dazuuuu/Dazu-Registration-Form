<?php
// The site logo: always the Dazu brand logo (assets/images/logo/logo.png, same as app/v1/assets/brand/dazu-logo.png).

require_once __DIR__ . '/brand.php';

if (!function_exists('site_logo_rel_path')) {
    /** The Dazu logo everywhere (website, emails, receipts, forms): assets/images/logo/logo.png. A logo uploaded in Settings is no longer used. */
    function site_logo_rel_path(): ?string
    {
        return 'assets/images/logo/logo.png';
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
            : (function_exists('brand_url') ? brand_url('logo') : 'https://i.ibb.co/fzfZFtGc/223025.jpg');
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
