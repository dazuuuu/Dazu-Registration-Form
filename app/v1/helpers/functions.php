<?php
// app/v1/helpers/functions.php

if (!function_exists('url')) {
    require_once __DIR__ . '/../config/paths.php';
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2;
}

function isSuperAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

/** asset() plus a ?v= file timestamp, so a changed stylesheet or script is never served stale from a phone's cache. */
function sa_asset(string $path): string {
    $file = dirname(__DIR__, 3) . '/public/assets/' . ltrim($path, '/');
    return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function getRoleBasedProfileUrl() {
    if (isAdmin()) {
        return url('profile/admin/index.php');
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        return url('students/dashboard/index.php');
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'tutor') {
        return url('tutor/dashboard/index.php');
    }
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee') {
        return url('employee/dashboard/index.php');
    }
    return url('profile/client/index.php');
}

/**
 * Return user id only if it exists in users. Optionally fall back to first active admin.
 * Fixes FK errors when session still holds an id from an old database import.
 */
function resolve_valid_user_id($pdo, ?int $userId, bool $fallbackAdmin = false): ?int {
    if ($userId !== null && $userId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
    }

    if (!$fallbackAdmin) {
        return null;
    }

    $stmt = $pdo->query(
        "SELECT u.id FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.role_name IN ('superadmin', 'admin') AND u.is_active = 1
         ORDER BY u.id ASC
         LIMIT 1"
    );
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}
?>