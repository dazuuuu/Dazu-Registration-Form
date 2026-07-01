<?php
// app/v1/config/paths.php
// Single source of truth for filesystem and web URLs.
//
// Deploy: all public entry points require app/v1/init.php or app/v1/bootstrap.php directly.
// Hosting: set APP_WEB_ROOT= (empty) when public/ is the document root.

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 3));
}
if (!defined('APP_V1_ROOT')) {
    define('APP_V1_ROOT', dirname(__DIR__));
}
if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', PROJECT_ROOT . DIRECTORY_SEPARATOR . 'public');
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

if (!defined('WEB_ROOT')) {
    $envFile = PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env';
    $webRoot = null;

    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '' && getenv($k) === false) putenv("$k=$v");
            if ($k === 'APP_WEB_ROOT') {
                $webRoot = $v;
            }
        }
    }

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
