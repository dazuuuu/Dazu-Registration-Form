<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/UserModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

require_method('POST');
$body = read_json_body();
$email = trim($body['email'] ?? $_POST['email'] ?? '');
$password = $body['password'] ?? $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    json_response(['success' => false, 'message' => 'Email and password are required'], 422);
}

$userModel = new UserModel(api_pdo());
$user = $userModel->getUserByEmail($email);

if (!$user || !password_verify($password, $user['password_hash'])) {
    $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? '');
    json_response(['success' => false, 'message' => 'Invalid email or password'], 401);
}

if (empty($user['is_active'])) {
    json_response(['success' => false, 'message' => 'Account is deactivated'], 403);
}

SessionModel::set('user_id', (int) $user['id']);
SessionModel::set('username', $user['username']);
SessionModel::set('email', $user['email']);
SessionModel::set('role', $user['role_name']);
SessionModel::set('role_id', (int) $user['role_id']);
SessionModel::set('logged_in', true);

if (!empty($user['must_change_password'])) {
    json_response([
        'success' => true,
        'message' => 'Password change required',
        'must_change_password' => true,
        'redirect' => url('auth/change_password.php'),
    ]);
}

$redirect = match ($user['role_name']) {
    'superadmin', 'admin' => url('admin/dashboard.php'),
    'student' => url('student'),
    'tutor' => url('tutor'),
    'employee' => url('employee/dashboard/index.php'),
    default => url(''),
};

json_response([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
        'role' => $user['role_name'],
        'role_id' => (int) $user['role_id'],
    ],
    'redirect' => $redirect,
]);
