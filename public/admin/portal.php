<?php
// public/admin/portal.php — admin login (terminal UI)
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (isLoggedIn() && isAdmin()) {
    redirect(url('profile/admin/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = '[err] EMAIL and PASS are required';
    } else {
        $user = $userModel->getUserByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = '[err] authentication failed — invalid credentials';
            $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR'] ?? '');
        } elseif ((int) $user['role_id'] > 2) {
            $error = '[err] access denied — admin privileges required';
        } elseif (!$user['is_active']) {
            $error = '[err] account deactivated — contact superadmin';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;

            $success = '[ ok ] admin session established — redirecting...';
            header('refresh:1;url=' . url('profile/admin/index.php'));
        }
    }
}

$auth_page_title = 'Admin portal';
$auth_window_title = 'dazu@hub — admin.portal';
$auth_page_mode = 'admin';
require __DIR__ . '/../components/auth/terminal_layout_start.php';
?>

<div id="authBootLog" class="auth-terminal-boot" aria-live="polite"></div>

<?php if ($error): ?>
    <div class="auth-terminal-alert error" role="alert">
        <i class="fas fa-times-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="auth-terminal-alert success" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<form method="POST" class="auth-terminal-form" novalidate>
    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">admin@hub:~/portal$</span>
        <label class="auth-cmd-label" for="email">export EMAIL=</label>
        <input type="email" id="email" name="email" class="auth-cmd-input"
               placeholder="admin@dazuhub.institute" autocomplete="username"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">admin@hub:~/portal$</span>
        <label class="auth-cmd-label" for="password">export PASS=</label>
        <input type="password" id="password" name="password" class="auth-cmd-input"
               placeholder="••••••••" autocomplete="current-password" required>
        <span id="passEcho" class="auth-cmd-label" style="margin-left:4px;color:var(--term-green-dim);" aria-hidden="true"></span>
    </div>

    <button type="submit" class="auth-cmd-submit">
        <i class="fas fa-user-shield"></i> ./sudo-portal.sh --execute
    </button>
</form>

<div class="auth-terminal-links">
    <div class="row">
        <span class="guest">user@hub:~$</span>
        <span>forgot password?</span>
        <a href="<?php echo url('auth/forgot_password.php'); ?>">./recovery.sh</a>
    </div>
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <a href="<?php echo url('auth/login.php'); ?>">cd ../login</a>
    </div>
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <a href="<?php echo url('/'); ?>">cd ../../home</a>
    </div>
</div>

<?php require __DIR__ . '/../components/auth/terminal_layout_end.php'; ?>
