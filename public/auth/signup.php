<?php
// public/auth/sign.php
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/models/UserModel.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (isLoggedIn()) {
    redirect('/Realestate/public/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_code = $_POST['admin_code'];
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Validate
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (empty($admin_code) || $admin_code !== 'ADMIN2026!') {
        $error = 'Invalid admin registration code';
    } else {
        // Check if user exists
        $existingUser = $userModel->getUserByEmail($email);
        if ($existingUser) {
            $error = 'Email already registered';
        } else {
            $existingUsername = $userModel->getUserByUsername($username);
            if ($existingUsername) {
                $error = 'Username already taken';
            } else {
                // Create admin (role_id: 2 = admin)
                if ($userModel->createUser($username, $email, $password, 2)) {
                    $user = $userModel->getUserByEmail($email);
                    if ($user) {
                        $userModel->updateProfile($user['id'], $first_name, $last_name, $phone);
                    }
                    $success = 'Admin registration successful! Redirecting to login...';
                    header("refresh:2;url=/Realestate/public/admin/portal.php");
                } else {
                    $error = 'Admin registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - Ismano</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; }
        .card { border-radius: 15px; margin-top: 50px; }
        .btn-register { background: #f5576c; border: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">Admin Registration</h3>
                        <p class="text-center text-muted">Restricted Access - Admin Code Required</p>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label>Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label>Admin Registration Code *</label>
                                <input type="password" name="admin_code" class="form-control" required>
                                <small class="text-muted">Required for admin account creation</small>
                            </div>
                            <div class="mb-3">
                                <label>Password *</label>
                                <input type="password" name="password" class="form-control" required>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="mb-4">
                                <label>Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-register w-100">Register Admin</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="/Realestate/public/admin/portal.php">Admin Login</a></p>
                            <hr>
                            <p class="small text-muted">User registration? <a href="register.php">Click here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>