<?php
// public/auth/register.php
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/models/UserModel.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/Realestate/public/profile/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Validate
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
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
                // Create user (role_id: 3 = regular user)
                if ($userModel->createUser($username, $email, $password, 3)) {
                    $user = $userModel->getUserByEmail($email);
                    if ($user) {
                        $userModel->updateProfile($user['id'], $first_name, $last_name, $phone);
                    }
                    $success = 'Registration successful! Redirecting to login...';
                    header("refresh:2;url=login.php");
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

// Safe redisplay helper for sticky form values (prevents reflected XSS)
function old(string $key): string {
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
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
    <title>Create account — Ismano</title>

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
             src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1400&q=80" alt="" aria-hidden="true">
        <div class="auth-brand-inner">
            <a class="auth-logo" href="/Realestate/public/" aria-label="Ismano — Home">
                <?php if ($logoExists): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Ismano logo" class="brand-logo">
                <?php else: ?>
                    <svg class="brand-mark" width="30" height="30" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="12" height="12" fill="currentColor"/>
                        <rect x="16" width="12" height="12" fill="currentColor" opacity=".45"/>
                        <rect y="16" width="12" height="12" fill="currentColor" opacity=".45"/>
                        <rect x="16" y="16" width="12" height="12" fill="currentColor"/>
                    </svg>
                <?php endif; ?>
                <span class="brand-name">Ismano</span>
            </a>

            <h1>Start your journey with <span>Ismano</span>.</h1>
            <p class="lead">Create an account to follow your projects, receive updates, and work with us directly.</p>

            <ul class="auth-points">
                <li><i class="fas fa-check"></i> Track every project in one dashboard</li>
                <li><i class="fas fa-check"></i> Direct, transparent communication</li>
                <li><i class="fas fa-check"></i> Free to join — no commitment</li>
            </ul>
        </div>
        <p class="auth-brand-foot">&copy; <?php echo date('Y'); ?> Prime Investment. All rights reserved.</p>
    </aside>

    <!-- Form panel -->
    <main class="auth-form">
        <div class="auth-form-top">
            <a href="/Realestate/public/" class="auth-back"><i class="fas fa-arrow-left"></i> Back to home</a>
        </div>

        <div class="auth-form-body">
            <div class="auth-mark"><i class="fas fa-user-plus"></i></div>
            <h2>Create your account</h2>
            <p class="sub">It only takes a minute to get started.</p>

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
                    <label for="username">Username *</label>
                    <div class="auth-input">
                        <i class="fas fa-at"></i>
                        <input type="text" id="username" name="username" placeholder="Choose a username"
                               value="<?php echo old('username'); ?>" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="email">Email address *</label>
                    <div class="auth-input">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com"
                               value="<?php echo old('email'); ?>" required>
                    </div>
                </div>

                <div class="auth-row">
                    <div class="auth-field">
                        <label for="first_name">First name</label>
                        <div class="auth-input">
                            <i class="fas fa-user"></i>
                            <input type="text" id="first_name" name="first_name" placeholder="First name"
                                   value="<?php echo old('first_name'); ?>">
                        </div>
                    </div>
                    <div class="auth-field">
                        <label for="last_name">Last name</label>
                        <div class="auth-input">
                            <i class="fas fa-user"></i>
                            <input type="text" id="last_name" name="last_name" placeholder="Last name"
                                   value="<?php echo old('last_name'); ?>">
                        </div>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="phone">Phone</label>
                    <div class="auth-input">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" placeholder="Phone number"
                               value="<?php echo old('phone'); ?>">
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password *</label>
                    <div class="auth-input">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                    </div>
                    <small class="auth-hint">Minimum 6 characters.</small>
                </div>

                <div class="auth-field">
                    <label for="confirm_password">Confirm password *</label>
                    <div class="auth-input">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                    </div>
                </div>

                <button type="submit" class="auth-submit">
                    <i class="fas fa-user-plus"></i> Create account
                </button>
            </form>

            <p class="auth-alt">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>