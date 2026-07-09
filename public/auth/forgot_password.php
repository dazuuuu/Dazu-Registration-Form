<?php
// public/auth/forgot_password.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/controllers/PasswordResetController.php';

if (isLoggedIn()) {
    redirect(url('profile/'));
}

$error = '';
$success = '';
$controller = new PasswordResetController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = '[err] security check failed — refresh and try again';
    } else {
        try {
            $result = $controller->requestOtp($_POST['email'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');

            if ($result['success']) {
                if (!empty($result['sent'])) {
                    $_SESSION['password_reset_email'] = $result['email'];
                    $_SESSION['password_reset_started'] = time();
                    header('Location: ' . url('auth/reset_password.php'));
                    exit();
                }
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'password_reset_otps') !== false) {
                $error = '[err] password reset is not configured — run migration 022_password_reset_otps.sql';
            } else {
                $error = '[err] request failed — try again later';
            }
        }
    }
}

$auth_page_title = 'Forgot password';
$auth_window_title = 'dazu@hub — auth.recover';
$auth_page_mode = 'recover';
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

<p class="auth-terminal-hint" style="color:var(--term-green-dim);font-size:0.85rem;margin-bottom:1rem;">
    Enter your account email. We will send a 6-digit OTP code valid for 15 minutes.
    Works for admin, tutor, student, and user accounts.
</p>

<form method="POST" class="auth-terminal-form" novalidate>
    <?php echo csrf_field(); ?>
    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/recover$</span>
        <label class="auth-cmd-label" for="email">export EMAIL=</label>
        <input type="email" id="email" name="email" class="auth-cmd-input"
               placeholder="you@dazuhub.institute" autocomplete="email"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    </div>

    <button type="submit" class="auth-cmd-submit">
        <i class="fas fa-paper-plane"></i> ./send-otp.sh --execute
    </button>
</form>

<div class="auth-terminal-links">
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <a href="login.php">cd ../login</a>
    </div>
</div>

<?php require __DIR__ . '/../components/auth/terminal_layout_end.php'; ?>
