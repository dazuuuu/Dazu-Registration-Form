<?php
// public/auth/reset_password.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/controllers/PasswordResetController.php';

if (isLoggedIn()) {
    redirect(url('/'));
}

$resetEmail = $_SESSION['password_reset_email'] ?? '';
if ($resetEmail === '') {
    redirect(url('auth/forgot_password.php'));
}

$error = '';
$success = '';
$controller = new PasswordResetController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (isset($_POST['resend'])) {
        try {
            $result = $controller->requestOtp($resetEmail, $_SERVER['REMOTE_ADDR'] ?? '');
            if ($result['success'] && !empty($result['sent'])) {
                $success = 'A new verification code has been sent to your email.';
            } elseif ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            $error = 'Could not resend code. Please try again later.';
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
                header('refresh:3;url=' . url('auth/login.php'));
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            $error = 'Reset failed. Please try again later.';
        }
    }
}

$page_title = 'Reset Password — Dazu Training Institute';
ob_start();
?>
<?php require __DIR__ . '/../components/public/neu_auth.php'; ?>
<div class="neu-page"><div class="neu-card">
  <div class="neu-logo"><img src="<?php echo url('assets/icons/students-512.png'); ?>" alt="Dazu Training Institute"></div>
  <h1 class="neu-title">Reset password</h1>
  <p class="neu-sub">Verify &amp; reset</p>
  <?php if ($error): ?><div class="neu-msg neu-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="neu-msg neu-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <p class="neu-note">Enter the 6-digit code sent to<br><strong style="color:#0f172a"><?php echo htmlspecialchars($resetEmail); ?></strong></p>
  <form method="POST"><?php echo csrf_field(); ?>
    <label class="neu-field neu-otp"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/></svg><input type="text" id="otp" name="otp" required inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="••••••" autocomplete="one-time-code" aria-label="Verification code"></label>
    <div class="neu-field"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg><input type="password" id="password" name="password" required minlength="6" autocomplete="new-password" aria-label="New password" placeholder="New password"><button type="button" class="neu-eye" aria-label="Show password" aria-pressed="false" title="Show password"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg></button></div>
    <div class="neu-field"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg><input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password" aria-label="Confirm password" placeholder="Confirm password"><button type="button" class="neu-eye" aria-label="Show password" aria-pressed="false" title="Show password"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg></button></div>
    <button type="submit" class="neu-btn">Update password</button>
  </form>
  <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="resend" value="1"><button type="submit" class="neu-btn2">Resend code</button></form>
  <div class="neu-links"><a href="<?php echo url('auth/forgot_password.php'); ?>">Use a different email</a><br><a class="pri" href="<?php echo url('auth/login.php'); ?>">← Back to sign in</a></div>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
