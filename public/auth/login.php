<?php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';
require_once __DIR__ . '/../../app/v1/helpers/tutor_permissions.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (!empty($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = 'Your session expired — please sign in again.';
}

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ((int)($_SESSION['role_id'] ?? 99) === 1) {
        redirect(url('superadmin/dashboard.php'));
    } elseif (in_array($role, ['admin', 'superadmin'], true) || (int)($_SESSION['role_id'] ?? 99) <= 2) {
        redirect(url('admin/dashboard.php'));
    } elseif ($role === 'student') {
        redirect(url('students/dashboard/index.php'));
    } elseif ($role === 'tutor') {
        redirect(url(tutor_admin_allowed($pdo, (int) $_SESSION['user_id']) ? 'auth/choose-portal.php' : 'tutor/dashboard/index.php'));
    } elseif ($role === 'employee') {
        redirect(url('employee/dashboard/index.php'));
    } else {
        redirect(url('profile/client/index.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginInput === '' || $password === '') {
        $error = 'Email or registration number and password are required.';
    } else {
        $user = $userModel->getUserByIdentifier($loginInput);

        if (!$user || (int) $user['role_id'] === 1 || !password_verify($password, $user['password_hash'])) {   // the superadmin signs in on their own portal
            $error = 'Invalid email/registration number or password.';
            $userModel->logLoginAttempt($loginInput, $_SERVER['REMOTE_ADDR'] ?? '');
        } elseif (empty($user['is_active'])) {
            $error = 'Account is deactivated. Contact the administrator.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;
            unset($_SESSION['tutor_admin']);
            // A real sign-in replaces any leftover portal preview on this browser, so /students/ and /tutor/ use this session.
            foreach (['DAZUPREVIEW' => 'students', 'DAZUPREVIEWT' => 'tutor'] as $previewCookie => $portal) {
                if (!empty($_COOKIE[$previewCookie])) {
                    setcookie($previewCookie, '', time() - 3600, rtrim(WEB_ROOT, '/') . '/' . $portal . '/');
                }
            }
            session_regenerate_id(true);

            if (!empty($user['must_change_password'])) {
                redirect(url('auth/change_password.php'));
            }

            if ((int)$user['role_id'] === 1) {
                redirect(url('superadmin/dashboard.php'));
            } elseif ($user['role_id'] <= 2) {
                redirect(url('admin/dashboard.php'));
            } elseif ($user['role_name'] === 'student') {
                redirect(url('students/dashboard/index.php'));
            } elseif ($user['role_name'] === 'tutor') {
                // A tutor with admin rights chooses which portal to open.
                redirect(url(tutor_admin_allowed($pdo, (int) $user['id']) ? 'auth/choose-portal.php' : 'tutor/dashboard/index.php'));
            } elseif ($user['role_name'] === 'employee') {
                redirect(url('employee/dashboard/index.php'));
            } else {
                redirect(url('profile/client/index.php'));
            }
        }
    }
}

$page_title = 'Sign In — Dazu Training Institute';
$standalone = true;
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16 flex items-center justify-center">
  <div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
      <div class="space-y-2 text-center">
        <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">Dazu Training Institute</span>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Sign In</h1>
        <p class="text-xs text-slate-400 font-semibold">Students, tutors and staff sign in here. Admins use the admin portal link below.</p>
      </div>

      <?php if ($error): ?>
        <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="p-4 border border-green-200 bg-green-50 text-green-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-6">
        <div class="space-y-2">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="email">Email or Registration Number</label>
          <input type="text" id="email" name="email" required autocomplete="username"
                 placeholder="e.g. student@gmail.com or DCH061/26"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
        </div>
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-widest text-slate-500" for="password">Password</label>
            <a href="<?php echo url('auth/forgot_password.php'); ?>" class="text-[10px] font-bold text-green-600 hover:underline">Forgot password?</a>
          </div>
          <input type="password" id="password" name="password" required autocomplete="current-password"
                 class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
        </div>
        <button type="submit" class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Sign In</button>
      </form>

      <div class="text-center space-y-3 text-xs text-slate-500">
        <a href="<?php echo url('courses/index.php'); ?>" class="text-green-600 font-bold hover:underline block">New student? Apply here</a>
        <a href="<?php echo url('/'); ?>" class="text-slate-400 hover:text-slate-600 block">← Back to website</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
