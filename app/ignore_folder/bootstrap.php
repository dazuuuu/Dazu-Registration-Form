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
    // A student- or tutor-portal preview (admin menu) lives in its own session; its cookie only ever travels to
    // /students/ (DAZUPREVIEW) or /tutor/ (DAZUPREVIEWT).
    $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    foreach (['DAZUPREVIEW' => '/students', 'DAZUPREVIEWT' => '/tutor'] as $previewCookie => $previewDir) {
        $previewBase = (defined('WEB_ROOT') ? rtrim(WEB_ROOT, '/') : '') . $previewDir;
        if (!empty($_COOKIE[$previewCookie]) && ($reqPath === $previewBase || strpos($reqPath, $previewBase . '/') === 0)) {
            session_name($previewCookie);
            session_set_cookie_params(['path' => $previewBase . '/', 'httponly' => true, 'samesite' => 'Lax']);
            break;
        }
    }
    session_start();
}

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
