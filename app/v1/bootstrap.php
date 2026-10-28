<?php
// app/v1/bootstrap.php — application bootstrap (autoload, session, helpers).

require_once __DIR__ . '/config/paths.php';

$appConfig = require __DIR__ . '/config/app.php';
if (!empty($appConfig['timezone'])) {
    date_default_timezone_set($appConfig['timezone']);
}

define('ROOT_PATH', APP_V1_ROOT);

spl_autoload_register(function ($class) {
    $prefix = 'Controllers\\';
    $base = APP_V1_ROOT . '/controllers/';
    if (strpos($class, $prefix) === 0) {
        $file = $base . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) { require_once $file; return true; }
    }

    $prefix = 'Models\\';
    $base = APP_V1_ROOT . '/models/';
    if (strpos($class, $prefix) === 0) {
        $file = $base . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) { require_once $file; return true; }
    }

    $flat = [
        'SessionModel' => '/models/Session.php',
        'UserModel'    => '/models/UserModel.php',
        'SettingModel' => '/models/SettingModel.php',
    ];
    if (isset($flat[$class])) {
        $file = APP_V1_ROOT . $flat[$class];
        if (is_file($file)) { require_once $file; return true; }
    }

    return false;
});

// Legacy autoload for non-namespaced classes (store/CMS)
spl_autoload_register(function ($class) {
    $dirs = [
        APP_V1_ROOT . '/models/',
        APP_V1_ROOT . '/controllers/',
        APP_V1_ROOT . '/helpers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return true; }
    }
    return false;
});

if (session_status() === PHP_SESSION_NONE) {
    // Session cookies only: closing the browser drops them (see helpers/session_guard.php for idle/close logout).
    ini_set('session.cookie_lifetime', '0');
    // A student- or tutor-portal preview (admin menu) lives in its own session; its cookie only ever travels to
    // /students/ (DAZUPREVIEW), /tutor/ (DAZUPREVIEWT) or /intern/ (DAZUPREVIEWI).
    $defaultSessionName = session_name();
    $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    foreach (['DAZUPREVIEW' => '/students', 'DAZUPREVIEWT' => '/tutor', 'DAZUPREVIEWI' => '/intern'] as $previewCookie => $previewDir) {
        $previewBase = (defined('WEB_ROOT') ? rtrim(WEB_ROOT, '/') : '') . $previewDir;
        if (!empty($_COOKIE[$previewCookie]) && ($reqPath === $previewBase || strpos($reqPath, $previewBase . '/') === 0)) {
            session_name($previewCookie);
            session_set_cookie_params(['path' => $previewBase . '/', 'httponly' => true, 'samesite' => 'Lax']);
            session_start();
            if (!empty($_SESSION['logged_in']) && !empty($_SESSION['preview'])) {
                break;
            }
            // A preview that ended or expired: drop its cookie and fall back to the normal session, otherwise
            // the portal and the login page keep redirecting to each other.
            $_SESSION = [];
            session_destroy();
            setcookie($previewCookie, '', time() - 3600, $previewBase . '/');
            unset($_COOKIE[$previewCookie]);
            session_name($defaultSessionName);
            session_id((string) ($_COOKIE[$defaultSessionName] ?? session_create_id()));
            session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            break;
        }
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

require_once APP_V1_ROOT . '/helpers/session_guard.php';
// Middleware::requireAuth() starts the guard itself on pages that load it before this file: do it only once.
$dazuGuardFirst = !defined('DAZU_GUARD_ACTIVE');
if ($dazuGuardFirst) {
    session_guard_check();
    define('DAZU_GUARD_ACTIVE', true);
}
if (PHP_SAPI !== 'cli') {
    if ($dazuGuardFirst) ob_start('session_guard_inject');
    ob_start('dazu_head_meta');      // favicon, share preview, canonical link and robots tag on every page
    ob_start('dazu_clean_links');   // runs first: links without .php everywhere
}
unset($dazuGuardFirst);

require_once APP_V1_ROOT . '/helpers/functions.php';
require_once APP_V1_ROOT . '/helpers/contact.php';
require_once APP_V1_ROOT . '/helpers/uploads.php';
require_once APP_V1_ROOT . '/config/db_connect.php';

if (!empty($appConfig['timezone']) && isset($pdo)) {
    try {
        $offsetSeconds = (new DateTime('now', new DateTimeZone($appConfig['timezone'])))->getOffset();
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $offsetSeconds = abs($offsetSeconds);
        $mysqlTz = sprintf(
            '%s%02d:%02d',
            $sign,
            intdiv($offsetSeconds, 3600),
            intdiv($offsetSeconds % 3600, 60)
        );
        $pdo->exec("SET time_zone = '{$mysqlTz}'");
    } catch (Throwable $e) {
        // Non-fatal: OTP expiry uses MySQL NOW() on both write and read.
    }
}

// A page that crashes (fatal error) used to show half a page and nothing else, because the server hides PHP errors.
// Now the error is written to storage/logs/fatal.log (Superadmin → File check shows it) and the visitor sees a short message.
if (!defined('DAZU_FATAL_LOG')) {
    define('DAZU_FATAL_LOG', true);
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) return;
        $root = dirname(__DIR__, 2);
        $dir = function_exists('storage_path') ? storage_path('logs') : __DIR__ . '/storage/logs';
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            $line = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' | '
                  . str_replace($root . '/', '', $err['message']) . ' in ' . str_replace($root . '/', '', $err['file']) . ':' . $err['line'];
            $log = $dir . '/fatal.log';
            if (is_file($log) && filesize($log) > 512000) @rename($log, $log . '.old');
            @file_put_contents($log, preg_replace('/\s+/', ' ', $line) . "\n", FILE_APPEND | LOCK_EX);
        }
        if (PHP_SAPI !== 'cli') {
            while (ob_get_level() > 0) @ob_end_clean();   // drop the half-printed page
            if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=UTF-8'); }
            echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Something went wrong</title>'
               . '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:15vh auto;padding:0 16px;text-align:center;color:#334155">'
               . '<h1 style="font-size:20px">Something went wrong on this page</h1><p>The problem was recorded for the office. Please try again in a few minutes.</p>'
               . '<p><a href="javascript:history.back()" style="color:#15A24A">Go back</a></p></div>';
        }
    });
}
