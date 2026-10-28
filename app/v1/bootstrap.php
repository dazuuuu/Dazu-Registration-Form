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
