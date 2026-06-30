<?php
// public/admin/portal.php
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

// If already logged in as admin, redirect to admin profile
if (isLoggedIn() && isAdmin()) {
    redirect('/Realestate/public/profile/admin/index.php');
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

            redirect('/Realestate/public/profile/admin/index.php');
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
    <title>Admin Portal — Prime Estate</title>

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* Admin variant: deeper, more "secure" brand panel */
        .auth-brand.is-admin{ background:linear-gradient(135deg, #050B16 0%, #0A1320 55%, #0B2D7A 130%); }
    </style>
</head>
<body class="auth-body">

<div class="auth-wrap">

    <!-- Brand panel (admin variant) -->
    <aside class="auth-brand is-admin">
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

            <h1>Administrator <span>access</span>.</h1>
            <p class="lead">A restricted area for managing projects, services, blog content, and accounts.</p>

            <ul class="auth-points">
                <li><i class="fas fa-lock"></i> Restricted to admin accounts only</li>
                <li><i class="fas fa-shield-halved"></i> Secure, session-based authentication</li>
                <li><i class="fas fa-clock-rotate-left"></i> Sign-in attempts are recorded</li>
            </ul>
        </div>
        <p class="auth-brand-foot">&copy; <?php echo date('Y'); ?> Ismano. Authorised personnel only.</p>
    </aside>

    <!-- Form panel -->
    <main class="auth-form">
        <div class="auth-form-top">
            <a href="/Realestate/public/" class="auth-back"><i class="fas fa-arrow-left"></i> Back to home</a>
        </div>

        <div class="auth-form-body">
            <div class="auth-mark"><i class="fas fa-user-shield"></i></div>
            <h2>Admin portal</h2>
            <p class="sub">Secure administrator access.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="admin@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
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
                    <i class="fas fa-user-shield"></i> Sign in as admin
                </button>
            </form>

            <hr class="auth-divider">

            <p class="auth-portal">
                <a href="/Realestate/public/auth/login.php"><i class="fas fa-arrow-left me-1"></i> Back to user login</a>
            </p>
        </div>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>