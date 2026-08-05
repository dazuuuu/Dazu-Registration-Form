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

$page_title = 'Reset Password — Dazu hub Institute';
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16 flex items-center justify-center">
  <div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
      <div class="space-y-2 text-center">
        <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">Verify &amp; Reset</span>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Reset Password</h1>
        <p class="text-xs text-slate-500 font-medium leading-relaxed">
          Enter the 6-digit code sent to<br>
          <strong class="text-slate-700"><?php echo htmlspecialchars($resetEmail); ?></strong>
        </p>
      </div>

      <?php if ($error): ?>
        <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="p-4 border border-green-200 bg-green-50 text-green-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <?php echo csrf_field(); ?>
        <div class="space-y-2">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="otp">Verification Code</label>
          <input type="text" id="otp" name="otp" required inputmode="numeric" pattern="\d{6}" maxlength="6"
                 placeholder="123456" autocomplete="one-time-code"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-center tracking-[0.4em] font-mono font-bold focus:outline-none focus:border-green-600">
        </div>
        <div class="space-y-2">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="password">New Password</label>
          <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
        </div>
        <div class="space-y-2">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
        </div>
        <button type="submit" class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Update Password</button>
      </form>

      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="resend" value="1">
        <button type="submit" class="w-full py-3 text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl">Resend Code</button>
      </form>

      <div class="text-center space-y-3 text-xs text-slate-500">
        <a href="<?php echo url('auth/forgot_password.php'); ?>" class="text-slate-600 hover:text-green-600 font-semibold block">Use a different email</a>
        <a href="<?php echo url('auth/login.php'); ?>" class="text-green-600 font-bold hover:underline block">← Back to Sign In</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
