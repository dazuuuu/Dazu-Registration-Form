<?php
// app/helpers/functions.php

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2;
}

function isSuperAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
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
    return url('profile/client/index.php');
}
?>