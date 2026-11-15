<?php
// public/router.php — local development only: php -S localhost:8000 -t public public/router.php
// Mimics the clean-URL rules of public/.htaccess, which the PHP built-in server does not read.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;                                  // real file: let PHP serve it
}
if (preg_match('#^/verify/tutor/([A-Za-z0-9]{32,64})/?$#', $path, $m)) {
    $_GET['t'] = $m[1];
    require __DIR__ . '/verify/tutor.php';
    return true;
}
$map = ['/login' => '/auth/login.php', '/forgot-password' => '/auth/forgot_password.php', '/reset-password' => '/auth/reset_password.php', '/blog' => '/blogs/index.php'];
$clean = rtrim($path, '/');
if (isset($map[$clean])) {
    require __DIR__ . $map[$clean];
    return true;
}
if ($clean !== '' && is_file(__DIR__ . $clean . '.php')) {   // /setup -> setup.php
    require __DIR__ . $clean . '.php';
    return true;
}
if ($clean !== '' && is_file(__DIR__ . $clean . '/index.php')) {   // /admin -> admin/index.php
    require __DIR__ . $clean . '/index.php';
    return true;
}
// index.php works out its base folder and clean URLs (/courses/<course-name>) from SCRIPT_NAME, as Apache sets it
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
require __DIR__ . '/index.php';
return true;
