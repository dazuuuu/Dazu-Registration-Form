<?php
// public/router.php — PHP built-in server router.
// Usage from the project root:
//   php -S localhost:8000 -t public public/router.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$uri = '/' . ltrim($uri, '/');
$path = rtrim($uri, '/') ?: '/';

$pretty = [
    '/setup' => '/setup/index.php',
    '/login' => '/auth/login.php',
    '/forgot-password' => '/auth/forgot_password.php',
    '/reset-password' => '/auth/reset_password.php',
    '/testimonials/submit' => '/testimonials/submit.php',
    '/apply' => '/apply/index.php',
    '/courses' => '/courses/index.php',
    '/about' => '/about/index.php',
    '/contact' => '/contact/index.php',
    '/projects' => '/projects/index.php',
    '/blog' => '/blogs/index.php',
];

if (isset($pretty[$path])) {
    require __DIR__ . $pretty[$path];
    return true;
}

$requested = __DIR__ . $uri;
if ($uri !== '/' && is_file($requested)) {
    return false;
}

if (is_dir($requested) && is_file(rtrim($requested, '/') . '/index.php')) {
    require rtrim($requested, '/') . '/index.php';
    return true;
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo 'Not found';
return true;
