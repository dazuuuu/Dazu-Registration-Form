<?php
// app/v1/config/paths.php
// Single source of truth for filesystem and web URLs.
//
// Hosting: set APP_WEB_ROOT=/ (or empty) when public/ is the document root.
// Local Ampps: leave unset or use /Registration-System/public

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}
if (!defined('APP_V1_ROOT')) {
    define('APP_V1_ROOT', dirname(__DIR__));
}
if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public');
}

if (!defined('WEB_ROOT')) {
    $envFile = PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) putenv("$k=$v");
        }
    }
    $env = getenv('APP_WEB_ROOT');
    if ($env === false || $env === null) {
        $env = '/Registration-System/public';
    }
    define('WEB_ROOT', rtrim((string) $env, '/') ?: '');
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

/** Absolute filesystem path inside /public */
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
