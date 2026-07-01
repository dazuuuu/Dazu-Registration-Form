<?php
// app/bootstrap.php — legacy shim for store/CMS pages.
require_once __DIR__ . '/v1/bootstrap.php';

// Extended autoload for non-namespaced legacy classes
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
