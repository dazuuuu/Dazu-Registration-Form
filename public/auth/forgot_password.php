<?php
// public/auth/forgot_password.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/controllers/PasswordResetController.php';

if (isLoggedIn()) {
    redirect(url('/'));
}

$error = '';
$success = '';
$controller = new PasswordResetController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        try {
            $result = $controller->requestOtp($_POST['email'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '');

            if ($result['success']) {
                if (!empty($result['sent'])) {
                    $_SESSION['password_reset_email'] = $result['email'];
                    $_SESSION['password_reset_started'] = time();
                    redirect(url('auth/reset_password.php'));
                }
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } catch (Throwable $e) {
            $error = 'Request failed. Please try again later.';
        }
    }
}

$page_title = 'Forgot Password — Dazu Training Institute';
ob_start();
?>
<?php require __DIR__ . '/../components/public/neu_auth.php'; ?>
<div class="neu-page"><div class="neu-card">
  <div class="neu-logo"><img src="<?php echo url('assets/icons/students-512.png'); ?>" alt="Dazu Training Institute"></div>
  <h1 class="neu-title">Forgot password</h1>
  <p class="neu-sub">Account recovery</p>
  <?php if ($error): ?><div class="neu-msg neu-err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="neu-msg neu-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <p class="neu-note">Enter your account email. We will send a 6-digit code valid for 15 minutes.</p>
  <form method="POST"><?php echo csrf_field(); ?>
    <label class="neu-field"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><input type="email" id="email" name="email" required autocomplete="email" aria-label="Account email" placeholder="you@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
    <button type="submit" class="neu-btn">Send code</button>
  </form>
  <div class="neu-links"><a class="pri" href="<?php echo url('auth/login.php'); ?>">← Back to sign in</a><br><a href="<?php echo url('/'); ?>">Back to website</a></div>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
