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

/*
 * The one backend lives in app/v1. Everything server-side sits inside it, so nothing outside app/ and public/ needs to know
 * where things are. Web URLs are unaffected by where these folders are (only public/ is served), so links and permalinks
 * never depend on them. Always build filesystem paths from the constants and helpers below, never from hard-coded '../..'.
 *
 *   BACKEND_ROOT    app/v1                (config, models, controllers, services, helpers, templates, lib)
 *   DATABASES_PATH  app/v1/databases      (SQL migrations, migrate.php)
 *   STORAGE_PATH    app/v1/storage        (private files: SMS receipts, the mail key)
 *   VENDOR_PATH     app/v1/vendor         (Composer packages; composer.json sits beside it)
 *   TESTS_PATH      app/v1/tests
 *
 * Each can be moved with a server environment variable (APP_STORAGE_PATH, APP_DATABASES_PATH, APP_VENDOR_PATH), e.g. to keep private
 * files outside the code folder on a shared host.
 */
if (!defined('BACKEND_ROOT')) {
    define('BACKEND_ROOT', APP_V1_ROOT);
}
if (!function_exists('dazu_backend_dir')) {
    function dazu_backend_dir(string $envKey, string $default): string {
        $o = function_exists('dazu_env') ? dazu_env($envKey) : null;
        return ($o !== null && $o !== '') ? rtrim(str_replace('\\', '/', (string) $o), '/') : $default;
    }
}
if (!defined('DATABASES_PATH')) {
    define('DATABASES_PATH', dazu_backend_dir('APP_DATABASES_PATH', BACKEND_ROOT . '/databases'));
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dazu_backend_dir('APP_STORAGE_PATH', BACKEND_ROOT . '/storage'));
}
if (!defined('VENDOR_PATH')) {
    define('VENDOR_PATH', dazu_backend_dir('APP_VENDOR_PATH', BACKEND_ROOT . '/vendor'));
}
if (!defined('TESTS_PATH')) {
    define('TESTS_PATH', BACKEND_ROOT . '/tests');
}

if (!function_exists('backend_path')) {
    /** Absolute path inside the backend (app/v1). */
    function backend_path(string $rel = ''): string {
        return BACKEND_ROOT . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('database_path')) {
    function database_path(string $rel = ''): string {
        return DATABASES_PATH . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('storage_path')) {
    /** Absolute path in private storage. */
    function storage_path(string $rel = ''): string {
        return STORAGE_PATH . ($rel === '' ? '' : '/' . ltrim($rel, '/'));
    }
}
if (!function_exists('storage_resolve')) {
    /**
     * Turn a path saved in the database ('storage/sms/receipts/x.pdf') into a real file. Files are looked for in the current
     * storage folder first, then in the old location at the project root, so records saved before the move still open.
     */
    function storage_resolve(?string $stored): ?string {
        if ($stored === null || $stored === '') {
            return null;
        }
        $rel = preg_replace('#^storage/#', '', ltrim(str_replace('\\', '/', $stored), '/'));
        foreach ([STORAGE_PATH . '/' . $rel, PROJECT_ROOT . '/storage/' . $rel] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
        return STORAGE_PATH . '/' . $rel;   // where it will be (or should be) written
    }
}
if (!function_exists('vendor_autoload_files')) {
    /** Where a Composer autoloader may be, in order: the current vendor folder, then the old project-root one. */
    function vendor_autoload_files(): array {
        return [VENDOR_PATH . '/autoload.php', PROJECT_ROOT . '/vendor/autoload.php'];
    }
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

        // Clean relative path for filesystem checks
        $cleanRel = ltrim(preg_replace('#^/Registration-System/public/#', '', $storedPath), '/');
        if (WEB_ROOT !== '' && str_starts_with($cleanRel, ltrim(WEB_ROOT, '/') . '/')) {
            $cleanRel = substr($cleanRel, strlen(ltrim(WEB_ROOT, '/') . '/'));
        }

        $fullUrl = url($cleanRel);

        // Append file modification time to bust browser / service worker cache
        $abs = public_path($cleanRel);
        if (is_file($abs)) {
            $mtime = @filemtime($abs);
            if ($mtime) {
                $fullUrl .= (str_contains($fullUrl, '?') ? '&' : '?') . 'v=' . $mtime;
            }
        }

        return $fullUrl;
    }
}
