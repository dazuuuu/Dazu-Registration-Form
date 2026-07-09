<?php
// public/auth/reset_password.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/controllers/PasswordResetController.php';

if (isLoggedIn()) {
    redirect(url('profile/'));
}

$resetEmail = $_SESSION['password_reset_email'] ?? '';
if ($resetEmail === '') {
    header('Location: ' . url('auth/forgot_password.php'));
    exit();
}

$error = '';
$success = '';
$controller = new PasswordResetController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = '[err] security check failed — refresh and try again';
    } elseif (isset($_POST['resend'])) {
        try {
            $result = $controller->requestOtp($resetEmail, $_SERVER['REMOTE_ADDR'] ?? '');
            if ($result['success'] && !empty($result['sent'])) {
                $success = '[ ok ] a new verification code has been sent';
            } elseif ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            $error = '[err] could not resend code — try again later';
        }
    } else {
        try {
            $result = $controller->resetPassword(
                $resetEmail,
                $_POST['otp'] ?? '',
                $_POST['password'] ?? '',
                $_POST['confirm_password'] ?? ''
            );

            if ($result['success']) {
                unset($_SESSION['password_reset_email'], $_SESSION['password_reset_started']);
                $success = $result['message'];
                header('refresh:2;url=' . url('auth/login.php'));
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'password_reset_otps') !== false) {
                $error = '[err] password reset is not configured — run migration 022_password_reset_otps.sql';
            } else {
                $error = '[err] reset failed — try again later';
            }
        }
    }
}

$auth_page_title = 'Reset password';
$auth_window_title = 'dazu@hub — auth.reset';
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
    Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($resetEmail); ?></strong>, then choose a new password.
</p>

<form method="POST" class="auth-terminal-form" novalidate>
    <?php echo csrf_field(); ?>
    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/reset$</span>
        <label class="auth-cmd-label" for="otp">export OTP=</label>
        <input type="text" id="otp" name="otp" class="auth-cmd-input"
               placeholder="123456" inputmode="numeric" pattern="\d{6}" maxlength="6"
               autocomplete="one-time-code" required>
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/reset$</span>
        <label class="auth-cmd-label" for="password">export NEW_PASS=</label>
        <input type="password" id="password" name="password" class="auth-cmd-input"
               placeholder="••••••••" autocomplete="new-password" minlength="6" required>
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/reset$</span>
        <label class="auth-cmd-label" for="confirm_password">export PASS_CONFIRM=</label>
        <input type="password" id="confirm_password" name="confirm_password" class="auth-cmd-input"
               placeholder="••••••••" autocomplete="new-password" minlength="6" required>
    </div>

    <button type="submit" class="auth-cmd-submit">
        <i class="fas fa-key"></i> ./reset-password.sh --execute
    </button>
</form>

<form method="POST" class="mt-3">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="resend" value="1">
    <button type="submit" class="auth-cmd-submit" style="background:transparent;border:1px solid var(--term-green-dim);color:var(--term-green);">
        <i class="fas fa-redo"></i> ./resend-otp.sh
    </button>
</form>

<div class="auth-terminal-links">
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <a href="forgot_password.php">use different email</a>
        <span>·</span>
        <a href="login.php">cd ../login</a>
    </div>
</div>

<?php require __DIR__ . '/../components/auth/terminal_layout_end.php'; ?>
