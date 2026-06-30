<?php
// public/auth/login.php
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/models/UserModel.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

// If already logged in, redirect based on role
if (isLoggedIn()) {
    $role_id = $_SESSION['role_id'] ?? 0;
    if ($role_id <= 2) {
        redirect('/Realestate/public/profile/admin/index.php');
    } else {
        redirect('/Realestate/public/profile/client/index.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } else {
        $user = $userModel->getUserByEmail($email);

        if (!$user) {
            $error = 'Invalid email or password';
            $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password';
            $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
        } elseif (!$user['is_active']) {
            $error = 'Account is deactivated. Contact administrator.';
        } else {
            // Login success - set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;

            $success = 'Login successful! Redirecting...';

            // Redirect based on role
            if ($user['role_id'] <= 2) {
                header("refresh:2;url=/Realestate/public/profile/admin/index.php");
            } else {
                header("refresh:2;url=/Realestate/public/profile/client/index.php");
            }
        }
    }
}

// Logo (mirrors the navbar's logic — PNG if present, else inline mark)
$logoPath = '/Realestate/public/assets/images/logo/logo.png';
$logoExists = isset($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . $logoPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Realestate</title>

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">

<div class="auth-wrap">

    <!-- Brand panel -->
    <aside class="auth-brand">
        <img class="auth-brand-bgimg"
             src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=1400&q=80" alt="" aria-hidden="true">
        <div class="auth-brand-inner">
            <a class="auth-logo" href="/Realestate/public/" aria-label="Realestate — Home">
                <?php if ($logoExists): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Realestate logo" class="brand-logo">
                <?php else: ?>
                    <svg class="brand-mark" width="30" height="30" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="12" height="12" fill="currentColor"/>
                        <rect x="16" width="12" height="12" fill="currentColor" opacity=".45"/>
                        <rect y="16" width="12" height="12" fill="currentColor" opacity=".45"/>
                        <rect x="16" y="16" width="12" height="12" fill="currentColor"/>
                    </svg>
                <?php endif; ?>
                <span class="brand-name">Realestate</span>
            </a>

            <h1>Welcome back to <span>Realestate</span>.</h1>
            <p class="lead">Sign in to manage your projects, track progress, and pick up right where you left off.</p>

            <ul class="auth-points">
                <li><i class="fas fa-check"></i> Your projects and updates in one place</li>
                <li><i class="fas fa-check"></i> Secure, private access to your account</li>
                <li><i class="fas fa-check"></i> Transparent progress, no surprises</li>
            </ul>
        </div>
        <p class="auth-brand-foot">&copy; <?php echo date('Y'); ?> Realestate. All rights reserved.</p>
    </aside>

    <!-- Form panel -->
    <main class="auth-form">
        <div class="auth-form-top">
            <a href="/Realestate/public/" class="auth-back"><i class="fas fa-arrow-left"></i> Back to home</a>
        </div>

        <div class="auth-form-body">
            <div class="auth-mark"><i class="fas fa-arrow-right-to-bracket"></i></div>
            <h2>Sign in</h2>
            <p class="sub">Enter your details to access your account.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="auth-field">
                    <label for="email">Email address</label>
                    <div class="auth-input">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="auth-submit">
                    <i class="fas fa-arrow-right-to-bracket"></i> Sign in
                </button>
            </form>

            <p class="auth-alt">
                Don't have an account? <a href="register.php">Create one</a>
            </p>

            <hr class="auth-divider">

            <p class="auth-portal">
                <a href="/Realestate/public/admin/portal.php"><i class="fas fa-user-shield me-1"></i> Admin portal</a>
            </p>
        </div>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>