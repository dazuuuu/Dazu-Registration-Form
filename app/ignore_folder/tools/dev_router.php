<?php
// Local preview with clean URLs on PHP's built-in server (which ignores public/.htaccess):
//   php -S localhost:8000 -t public app/v1/tools/dev_router.php
// Without this router the site still works locally — links just keep their .php form.
define('DAZU_PRETTY_ROUTER', true);

$root = $_SERVER['DOCUMENT_ROOT'];
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$rel = trim($path, '/');

$serve = static function (string $file, array $get = []) use ($root): bool {
    $_GET = $get + $_GET;
    $_SERVER['SCRIPT_FILENAME'] = $root . '/' . $file;
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/' . $file;
    chdir(dirname($root . '/' . $file));
    require $root . '/' . $file;
    return true;
};

// Real files (css, images, admin/*.php …) are served as-is
if ($rel !== '' && is_file($root . '/' . $rel)) {
    return false;
}

$fixed = ['login' => 'auth/login.php', 'forgot-password' => 'auth/forgot_password.php', 'reset-password' => 'auth/reset_password.php',
          'blog' => 'blogs/index.php', 'testimonials/submit' => 'testimonials/submit.php'];
if (isset($fixed[$rel])) {
    return $serve($fixed[$rel]);
}
if (preg_match('#^blog/([^/]+)$#', $rel, $m)) {
    return $serve('blogs/readmore.php', ['slug' => $m[1]]);
}
if ($rel !== '' && is_file($root . '/' . $rel . '/index.php')) {
    return $serve($rel . '/index.php');
}
if ($rel !== '' && is_file($root . '/' . $rel . '.php')) {
    return $serve($rel . '.php');
}
return $rel === '' ? $serve('index.php') : false;
