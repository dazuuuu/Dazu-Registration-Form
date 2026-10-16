<?php
// public/auth/logout.php — also the target of the idle sign-out in assets/js/session-guard.js (?reason=idle).
require_once __DIR__ . '/../../app/v1/init.php';

$loginPage = (int) ($_SESSION['role_id'] ?? 0) === 1 ? 'superadmin/login.php' : 'auth/login.php';
$query = ($_GET['reason'] ?? '') === 'idle' ? '?reason=idle' : '';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . url($loginPage) . $query);
exit();
?>
