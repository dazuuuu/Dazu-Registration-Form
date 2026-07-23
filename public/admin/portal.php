<?php
// public/admin/portal.php
require_once __DIR__ . '/../../app/v1/config/paths.php';
require_once __DIR__ . '/../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../app/v1/helpers/functions.php';

// If already logged in as admin, redirect to admin profile
if (isLoggedIn() && isAdmin()) {
    redirect(url('admin/dashboard.php'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Invalid credentials';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials';
        } elseif ($user['role_id'] > 2) {
            $error = 'Access denied. Admin privileges required.';
        } elseif (!$user['is_active']) {
            $error = 'Account is deactivated.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;

            redirect(url('admin/dashboard.php'));
        }
    }
}

// Logo (mirrors the navbar's logic — PNG if present, else inline mark)
$logoPath = url('assets/images/logo/logo.png');
$logoExists = isset($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . $logoPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal — Dazu Hub Institute</title>

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="wp-auth-body">

<div class="wp-auth-wrap">

    <a class="wp-auth-logo" href="<?php echo url('/'); ?>" aria-label="Dazu Hub Institute — Home">
        <?php if ($logoExists): ?>
            <img src="<?php echo $logoPath; ?>" alt="Dazu Hub Institute logo">
        <?php else: ?>
            <span class="wp-auth-logo-mark"><i class="fas fa-user-shield"></i></span>
        <?php endif; ?>
        <span class="wp-auth-logo-name">Dazu Hub</span>
    </a>

    <div class="wp-auth-card">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="wp-auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="admin@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="wp-auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <div class="wp-auth-row">
                <a href="<?php echo url('forgot-password'); ?>" class="wp-auth-forgot">Forgot password?</a>
            </div>

            <button type="submit" class="wp-auth-submit">
                <i class="fas fa-user-shield"></i> Sign in as admin
            </button>
        </form>
    </div>

    <p class="wp-auth-back">
        <a href="<?php echo url('auth/login.php'); ?>"><i class="fas fa-arrow-left me-1"></i> Back to user login</a>
    </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
