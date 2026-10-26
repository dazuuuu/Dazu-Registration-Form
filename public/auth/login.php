<?php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';
require_once __DIR__ . '/../../app/v1/helpers/tutor_permissions.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (!empty($_SESSION['session_expired']) || ($_GET['error'] ?? '') === 'session_expired' || ($_GET['reason'] ?? '') === 'idle') {
    $error = 'You were signed out after 30 minutes of inactivity. Please sign in again.';
    unset($_SESSION['session_expired']);
}
if (!empty($_SESSION['session_replaced'])) {
    $error = 'You were signed out because your account was signed in on another device.';
    unset($_SESSION['session_replaced']);
}

// Already signed in: offer to continue, but let a different account sign in on the same browser.
$continueUrl = null;
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? '';
    if ((int)($_SESSION['role_id'] ?? 99) === 1) {
        $continueUrl = url('superadmin/dashboard.php');
    } elseif (in_array($role, ['admin', 'superadmin'], true) || (int)($_SESSION['role_id'] ?? 99) <= 2) {
        $continueUrl = url('admin/dashboard.php');
    } elseif ($role === 'student') {
        $continueUrl = url('students/dashboard/index.php');
    } elseif ($role === 'tutor') {
        $continueUrl = url(tutor_admin_allowed($pdo, (int) $_SESSION['user_id']) ? 'auth/choose-portal.php' : 'tutor/dashboard/index.php');
    } elseif ($role === 'employee') {
        $continueUrl = url('employee/dashboard/index.php');
    } elseif ($role === 'intern') {
        $continueUrl = url('intern/dashboard/index.php');
    } else {
        $continueUrl = url('profile/client/index.php');
    }
}

/** Role id of 'tutor' when this admin account is also an active tutor (they get the tutor portal here), else 0. */
function login_admin_tutor_role(PDO $pdo, int $userId): int
{
    try {
        $st = $pdo->prepare("SELECT (SELECT id FROM roles WHERE role_name = 'tutor') FROM tutors WHERE user_id = ? AND is_active = 1");
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Rate limit, last 15 minutes: 5 failed sign-ins for this email / reg. no., or 20 from this IP (a campus shares one IP). */
function login_too_many_attempts(PDO $pdo, string $identifier): bool
{
    try {
        $st = $pdo->prepare('SELECT SUM(email = ?), SUM(ip_address = ?) FROM login_attempts WHERE attempt_time > NOW() - INTERVAL 15 MINUTE AND (email = ? OR ip_address = ?)');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $st->execute([$identifier, $ip, $identifier, $ip]);
        [$byUser, $byIp] = array_map('intval', $st->fetch(PDO::FETCH_NUM) ?: [0, 0]);
        return $byUser >= 5 || $byIp >= 20;
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($loginInput === '' || $password === '') {
        $error = 'Email or registration number and password are required.';
    } elseif (login_too_many_attempts($pdo, $loginInput)) {
        $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $user = $userModel->getUserByIdentifier($loginInput);

        if (!$user || (int) $user['role_id'] === 1 || !password_verify($password, $user['password_hash'])) {   // the superadmin signs in on their own portal
            $error = 'Invalid email/registration number or password.';
            $userModel->logLoginAttempt($loginInput, $_SERVER['REMOTE_ADDR'] ?? '');
        } elseif (empty($user['is_active'])) {
            $error = 'Account is deactivated. Contact the administrator.';
        } elseif ((int) $user['role_id'] === 2 && !($adminTutorRoleId = login_admin_tutor_role($pdo, (int) $user['id']))) {
            // This page is for students, tutors and staff. Admins sign in on the admin portal.
            $error = 'Admins sign in on the admin portal.';
            $adminPortalHint = true;
        } else {
            session_guard_login();   // drop whoever was signed in before on this browser
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['logged_in'] = true;
            // An admin who also teaches (e.g. the Nairobi admin) opens only the tutor portal from here.
            if (!empty($adminTutorRoleId)) {
                $_SESSION['role'] = 'tutor';
                $_SESSION['role_id'] = $adminTutorRoleId;
                $_SESSION['admin_tutor'] = true;   // "Admin portal" in the tutor menu switches without signing in again
                $user['role_name'] = 'tutor';
                $user['role_id'] = $adminTutorRoleId;
            }
            // A real sign-in replaces any leftover portal preview on this browser, so /students/ and /tutor/ use this session.
            foreach (['DAZUPREVIEW' => 'students', 'DAZUPREVIEWT' => 'tutor', 'DAZUPREVIEWI' => 'intern'] as $previewCookie => $portal) {
                if (!empty($_COOKIE[$previewCookie])) {
                    setcookie($previewCookie, '', time() - 3600, rtrim(WEB_ROOT, '/') . '/' . $portal . '/');
                }
            }
            session_regenerate_id(true);
            session_guard_claim();   // students, tutors, interns, staff: this device signs out any other

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
            } elseif ($user['role_name'] === 'intern') {
                redirect(url('intern/dashboard/index.php'));
            } else {
                redirect(url('profile/client/index.php'));
            }
        }
    }
}

$page_title = 'Sign In — Dazu Training Institute';
$standalone = true;
$og_image = url('assets/images/share/students-share.png');
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16 flex items-center justify-center">
  <div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
      <div class="space-y-2 text-center">
        <img src="<?php echo url('assets/icons/students-512.png'); ?>" alt="Dazu Student" class="w-24 h-24 mx-auto rounded-2xl">
        <span class="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">Dazu Training Institute</span>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Sign In</h1>
        <p class="text-xs text-slate-400 font-semibold">Students and tutors sign in here. Admins use the admin portal.</p>
      </div>

      <?php if ($error): ?>
        <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($error); ?>
          <?php if (!empty($adminPortalHint)): ?> <a href="<?php echo url('admin/portal.php'); ?>" class="underline">Open the admin portal</a><?php endif; ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="p-4 border border-green-200 bg-green-50 text-green-700 text-xs font-semibold rounded-xl"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <?php if ($continueUrl): ?>
        <div class="p-4 border border-slate-200 bg-slate-50 text-slate-700 text-xs font-semibold rounded-xl">
          You're signed in as <strong><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? ''); ?></strong>.
          <a href="<?php echo htmlspecialchars($continueUrl); ?>" class="text-green-700 underline">Continue</a>
          or sign in below with a different account (this signs the current one out).
        </div>
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
          <div class="relative">
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   class="w-full px-4 py-3 pr-12 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600">
            <button type="button" id="pwToggle" aria-label="Show password" aria-pressed="false" title="Show password"
                    class="absolute inset-y-0 right-0 px-4 flex items-center text-slate-400 hover:text-green-600">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg>
            </button>
          </div>
          <script>(function () { var b = document.getElementById('pwToggle'), i = document.getElementById('password');
            b.addEventListener('click', function () { var show = i.type === 'password'; i.type = show ? 'text' : 'password';
              b.setAttribute('aria-pressed', show); b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); b.title = b.getAttribute('aria-label');
              b.querySelector('[data-slash]').style.display = show ? '' : 'none'; i.focus(); }); })();</script>
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
