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

$page_title = 'Forgot Password — Dazu hub Institute';
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16 flex items-center justify-center">
  <div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
      <div class="space-y-2 text-center">
        <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">Account Recovery</span>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Forgot Password</h1>
        <p class="text-xs text-slate-500 font-medium leading-relaxed">Enter your account email. We will send a 6-digit verification code valid for 15 minutes.</p>
      </div>

      <?php if ($error): ?>
        <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="p-4 border border-green-200 bg-green-50 text-green-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-6">
        <?php echo csrf_field(); ?>
        <div class="space-y-2">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="email">Account Email</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 placeholder="you@example.com"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
        </div>
        <button type="submit" class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Send Verification Code</button>
      </form>

      <div class="text-center space-y-3 text-xs text-slate-500">
        <a href="<?php echo url('auth/login.php'); ?>" class="text-green-600 font-bold hover:underline block">← Back to Sign In</a>
        <a href="<?php echo url('/'); ?>" class="text-slate-400 hover:text-slate-600 block">Back to website</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
