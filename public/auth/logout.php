<?php
// public/auth/logout.php
require_once __DIR__ . '/../../app/v1/init.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . url('auth/login.php'));
exit();
?>