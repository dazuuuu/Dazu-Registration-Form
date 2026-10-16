<?php
// public/superadmin/login.php — separate sign-in for the superadmin dashboard.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['logged_in']) && (int)($_SESSION['role_id'] ?? 99) === 1) {
    redirect(url('superadmin/dashboard.php'));
}

$error = '';
$email = '';
if (!empty($_SESSION['session_expired']) || ($_GET['reason'] ?? '') === 'idle') {
    $error = 'You were signed out after 5 minutes of inactivity. Please sign in again.';
    unset($_SESSION['session_expired']);
}
$locked = ($_SESSION['sa_lock_until'] ?? 0) > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($locked) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $userModel = new UserModel($pdo);
        $user = $userModel->getUserByEmail($email);
        // One message for every failure, so the page does not reveal which accounts exist.
        if (!$user || (int)$user['role_id'] !== 1 || !password_verify($password, $user['password_hash']) || empty($user['is_active'])) {
            $error = 'Invalid email or password.';
            try { $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? ''); } catch (Throwable $e) { /* logging must never block the login form */ }
            $_SESSION['sa_fail'] = ($_SESSION['sa_fail'] ?? 0) + 1;
            if ($_SESSION['sa_fail'] >= 5) {
                $_SESSION['sa_lock_until'] = time() + 300;
                $_SESSION['sa_fail'] = 0;
            }
        } else {
            session_guard_login();   // drop whoever was signed in before on this browser
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = (int)$user['role_id'];
            $_SESSION['logged_in'] = true;
            redirect(url('superadmin/dashboard.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin sign in — Dazu Training Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo sa_asset('css/superadmin.css'); ?>">
    <?php include __DIR__ . '/../components/superadmin/pwa_head.php'; ?>
</head>
<body class="sa-auth-body">
<div class="sa-auth-shell">
    <div class="sa-auth-side">
        <div class="sa-brand"><img class="sa-brand-mark" src="<?php echo asset('superadmin/icon.svg'); ?>" alt="" width="38" height="38"><span>Dazu<span class="sa-accent">Hub</span><small>Superadmin</small></span></div>
        <h1>Finance and control,<br>in one place.</h1>
        <p>Payments, salaries, student balances and system settings for the institute.</p>
        <div class="sa-auth-glass">
            <div><span>Owed to staff</span><strong>KES &bull;&bull;&bull;&bull;&bull;</strong></div>
            <div><span>Collected</span><strong>KES &bull;&bull;&bull;&bull;&bull;</strong></div>
        </div>
    </div>
    <div class="sa-auth-form">
        <h2>Sign in</h2>
        <p class="text-muted">Superadmin access only.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST" autocomplete="on">
            <?php echo csrf_field(); ?>
            <label class="form-label">Email</label>
            <div class="sa-input"><i class="fas fa-envelope"></i><input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required autofocus autocomplete="username"></div>
            <label class="form-label mt-3">Password</label>
            <div class="sa-input"><i class="fas fa-lock"></i><input type="password" name="password" id="saPw" required autocomplete="current-password">
                <button type="button" class="sa-eye" onclick="var i=document.getElementById('saPw');i.type=i.type==='password'?'text':'password'" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
            <button class="btn sa-btn-primary w-100 mt-4" <?php echo $locked ? 'disabled' : ''; ?>>Sign in</button>
        </form>
    </div>
</div>
<script src="<?php echo sa_asset('superadmin/app.js'); ?>"></script>
</body>
</html>
