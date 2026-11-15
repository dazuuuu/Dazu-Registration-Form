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
<?php require __DIR__ . '/../components/public/neu_auth.php'; ?>
<div class="neu-page"><div class="neu-card">
  <div class="neu-logo"><img src="<?php echo url('assets/icons/students-512.png'); ?>" alt="Dazu Training Institute"></div>
  <h1 class="neu-title">Dazu Training Institute</h1>
  <p class="neu-sub">Students &amp; tutors portal</p>
  <?php if ($error): ?><div class="neu-msg neu-err"><?php echo htmlspecialchars($error); ?><?php if (!empty($adminPortalHint)): ?> <a href="<?php echo url('admin/portal.php'); ?>">Open the admin portal</a><?php endif; ?></div><?php endif; ?>
  <?php if ($success): ?><div class="neu-msg neu-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($continueUrl): ?><div class="neu-msg neu-info">Signed in as <strong><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? ''); ?></strong>. <a href="<?php echo htmlspecialchars($continueUrl); ?>">Continue</a> or sign in with another account.</div><?php endif; ?>
  <form method="POST" autocomplete="on">
    <label class="neu-field"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg><input type="text" id="email" name="email" required autocomplete="username" aria-label="Email or registration number" placeholder="Email or registration number" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
    <div class="neu-field"><svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg><input type="password" id="password" name="password" required autocomplete="current-password" aria-label="Password" placeholder="Password"><button type="button" class="neu-eye" aria-label="Show password" aria-pressed="false" title="Show password"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg></button></div>
    <button type="submit" class="neu-btn">Login</button>
  </form>
  <div class="neu-links"><a href="<?php echo url('auth/forgot_password.php'); ?>">Forgot password?</a> · <a class="pri" href="<?php echo url('courses/index.php'); ?>">New student? Apply</a><br><a href="<?php echo url('/'); ?>">← Back to website</a></div>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
