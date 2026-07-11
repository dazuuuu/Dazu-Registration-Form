<?php
// app/v1/bootstrap.php — application bootstrap (autoload, session, helpers).

require_once __DIR__ . '/config/paths.php';

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
    session_start();
}

require_once APP_V1_ROOT . '/helpers/functions.php';
require_once APP_V1_ROOT . '/helpers/contact.php';
require_once APP_V1_ROOT . '/helpers/uploads.php';
require_once APP_V1_ROOT . '/config/db_connect.php';
