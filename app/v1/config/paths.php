<?php
// app/v1/config/paths.php
// Single source of truth for filesystem and web URLs.

require_once __DIR__ . '/env.php';

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}
if (!defined('APP_V1_ROOT')) {
    define('APP_V1_ROOT', dirname(__DIR__));
}

/**
 * Detect URL prefix when public/ is in the request path (local Ampps/subfolder).
 * When public/ is the document root (cPanel), returns empty string.
 */
if (!function_exists('dazu_detect_web_root')) {
    function dazu_detect_web_root(): string {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== '' && preg_match('#^(.*)/public(?=/|$)#', $script, $m)) {
            return $m[1] . '/public';
        }
        return '';
    }
}

/**
 * Filesystem path to web-facing files (uploads, assets).
 * cPanel: often DOCUMENT_ROOT is e.g. .../dazuaihub.com (not .../public).
 */
if (!function_exists('dazu_detect_public_root')) {
    function dazu_detect_public_root(): string {
        $override = dazu_env('APP_PUBLIC_ROOT');
        if ($override !== null && $override !== '') {
            return rtrim(str_replace('\\', '/', $override), '/');
        }

        $repoPublic = str_replace('\\', '/', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public');
        $script     = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

        // Ampps / subfolder install: .../Registration-System/public/...
        if ($script !== '' && preg_match('#/public(/|$)#', $script) && is_dir($repoPublic)) {
            return $repoPublic;
        }

        // cPanel: document root is the live site folder (dazuaihub.com, public_html, etc.)
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot !== '' && is_dir($docRoot)) {
            return $docRoot;
        }

        return is_dir($repoPublic) ? $repoPublic : $docRoot;
    }
}

if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', dazu_detect_public_root());
}

if (!defined('WEB_ROOT')) {
    $webRoot = dazu_env('APP_WEB_ROOT');

    if ($webRoot === null && array_key_exists('APP_WEB_ROOT', $_SERVER)) {
        $webRoot = $_SERVER['APP_WEB_ROOT'];
    }

    if ($webRoot === null) {
        $fromEnv = getenv('APP_WEB_ROOT');
        if ($fromEnv !== false) {
            $webRoot = $fromEnv;
        }
    }

    if ($webRoot === null) {
        $webRoot = dazu_detect_web_root();
    }

    define('WEB_ROOT', rtrim((string) $webRoot, '/') ?: '');
}

/** Web URL from site root (public). Example: url('auth/login.php') */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        $path = ltrim($path, '/');
        if (WEB_ROOT === '') {
            return '/' . $path;
        }
        return WEB_ROOT . '/' . $path;
    }
}

/** Static asset under /public/assets */
if (!function_exists('asset')) {
    function asset(string $path): string {
        return url('assets/' . ltrim($path, '/'));
    }
}

/** Absolute filesystem path inside the web document root */
if (!function_exists('public_path')) {
    function public_path(string $path = ''): string {
        return PUBLIC_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }
}

/** Absolute filesystem path inside /app/v1 */
if (!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        return APP_V1_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }
}

/** Legacy alias used in settings */
if (!defined('BASE_URL')) {
    define('BASE_URL', WEB_ROOT === '' ? '/' : WEB_ROOT . '/');
}

if (!function_exists('settings_asset_url')) {
    function settings_asset_url(?string $storedPath): string {
        if ($storedPath === null || $storedPath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $storedPath)) {
            return $storedPath;
        }
        if ($storedPath[0] === '/') {
            return $storedPath;
        }
        return url(ltrim($storedPath, '/'));
    }
}
