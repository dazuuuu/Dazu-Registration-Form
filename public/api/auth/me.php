<?php
require_once __DIR__ . '/../_lib/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    json_response(['success' => true, 'authenticated' => false, 'user' => null]);
}

json_response([
    'success' => true,
    'authenticated' => true,
    'user' => [
        'id' => (int) $_SESSION['user_id'],
        'email' => $_SESSION['email'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'role_id' => (int) ($_SESSION['role_id'] ?? 0),
    ],
]);
