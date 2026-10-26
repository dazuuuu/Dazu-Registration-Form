<?php
// public/superadmin/logout.php
require_once __DIR__ . '/../../app/v1/init.php';

$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: ' . url('superadmin/login.php'));
exit();
