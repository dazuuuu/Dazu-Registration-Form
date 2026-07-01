<?php
// public/auth/login.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (isLoggedIn()) {
    $role_id = $_SESSION['role_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';
    if ($role_id <= 2) {
        redirect(url('profile/admin/index.php'));
    } elseif ($role === 'student') {
        redirect(url('students/dashboard/index.php'));
    } elseif ($role === 'tutor') {
        redirect(url('tutor/dashboard/index.php'));
    } else {
        redirect(url('profile/client/index.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = '[err] EMAIL and PASS are required';
    } else {
        $user = $userModel->getUserByEmail($email);

        if (!$user) {
            $error = '[err] authentication failed — invalid credentials';
            $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
        } elseif (!password_verify($password, $user['password_hash'])) {
            $error = '[err] authentication failed — invalid credentials';
            $userModel->logLoginAttempt($email, $_SERVER['REMOTE_ADDR']);
        } elseif (!$user['is_active']) {
            $error = '[err] account deactivated — contact administrator';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;

            $success = '[ ok ] login successful — redirecting...';

            if ($user['role_id'] <= 2) {
                header("refresh:2;url=" . url("profile/admin/index.php"));
            } elseif ($user['role_name'] === 'student') {
                header("refresh:2;url=" . url("students/dashboard/index.php"));
            } elseif ($user['role_name'] === 'tutor') {
                header("refresh:2;url=" . url("tutor/dashboard/index.php"));
            } else {
                header("refresh:2;url=" . url("profile/client/index.php"));
            }
        }
    }
}

$auth_page_title = 'Sign in';
$auth_window_title = 'dazu@hub — auth.dazu.hub';
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
        <span class="auth-cmd-prompt">dazu@hub:~/auth$</span>
        <label class="auth-cmd-label" for="email">export EMAIL=</label>
        <input type="email" id="email" name="email" class="auth-cmd-input"
               placeholder="you@dazuhub.institute" autocomplete="username"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/auth$</span>
        <label class="auth-cmd-label" for="password">export PASS=</label>
        <input type="password" id="password" name="password" class="auth-cmd-input"
               placeholder="••••••••" autocomplete="current-password" required>
        <span id="passEcho" class="auth-cmd-label" style="margin-left:4px;color:var(--term-green-dim);" aria-hidden="true"></span>
    </div>

    <button type="submit" class="auth-cmd-submit">
        <i class="fas fa-terminal"></i> ./signin.sh --execute
    </button>
</form>

<div class="auth-terminal-links">
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <span>new user?</span>
        <a href="register.php">./register.sh</a>
    </div>
    <div class="row">
        <span class="guest">admin@hub:~$</span>
        <a href="<?php echo url('admin/portal.php'); ?>">sudo portal</a>
    </div>
</div>

<?php require __DIR__ . '/../components/auth/terminal_layout_end.php'; ?>
