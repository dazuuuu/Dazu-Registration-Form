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
<style>
/* Triangle sign-in (brand green). Desktop: the form sits inside a white triangle with an outline and dark shards holding ENTER.
   Phones: the same look as a simple card, so the fields stay a comfortable size. */
.lg{--g:#15A24A;--gd:#0b5d2b;--gx:#06301a;--ln:#4ade80;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:radial-gradient(circle at 50% 45%,#f8fafc 0%,#e7ece9 70%,#dfe6e2 100%);padding:40px 16px;font-family:inherit}
.lg-msg{width:100%;max-width:520px;margin-bottom:18px;font-size:12px;font-weight:600;border-radius:10px;padding:12px 14px}
.lg-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.lg-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
.lg-info{background:#fff;border:1px solid #e2e8f0;color:#334155}.lg-msg a{color:var(--g);text-decoration:underline}
.lg-stage{position:relative;width:600px;height:620px}
.lg-stage>svg{position:absolute;inset:0;width:100%;height:100%;filter:drop-shadow(0 30px 40px rgba(15,40,25,.18))}
.lg-form{position:absolute;left:70px;top:150px;width:260px}
.lg-logo{width:44px;height:44px;border-radius:10px;display:block;margin-bottom:14px}
.lg-t{font-size:26px;line-height:1.15;letter-spacing:.06em;color:#64748b;font-weight:300;margin:0;text-transform:uppercase}
.lg-t b{display:block;font-weight:700;color:#334155}
.lg-bar{height:2px;width:120px;margin:12px 0 26px;background:linear-gradient(90deg,var(--g) 0 30px,#bbf7d0 30px)}
.lg-f{position:relative;margin-bottom:14px}
.lg-f input{width:100%;border:0;border-bottom:1px solid #e2e8f0;background:transparent;padding:10px 34px 10px 2px;font-size:14px;color:#0f172a;letter-spacing:.04em;outline:none;border-radius:0}
.lg-f input::placeholder{color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;font-size:12px}
.lg-f input:focus{border-bottom-color:var(--g)}
.lg-eye{position:absolute;right:0;top:6px;border:0;background:none;color:#94a3b8;padding:4px;cursor:pointer}.lg-eye:hover{color:var(--g)}
.lg-forgot{display:inline-block;margin:6px 0 0 26px;font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8;border:1px solid #e2e8f0;padding:5px 10px;text-decoration:none}
.lg-forgot:hover{color:var(--g);border-color:var(--g)}
.lg-enter{position:absolute;left:372px;top:432px;width:110px;height:84px;border:0;background:none;color:#fff;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;font-size:11px;letter-spacing:.14em;font-weight:600}
.lg-enter svg{width:22px;height:22px}
.lg-enter:hover{color:#bbf7d0}
.lg-links{margin-top:10px;display:flex;gap:18px;font-size:12px}
.lg-links a{color:#64748b;text-decoration:none}.lg-links a.ap{color:var(--g);font-weight:700}
.lg-mob-btn{display:none}
@media(max-width:660px){
  .lg-stage{width:100%;max-width:400px;height:auto;background:#fff;border-radius:18px;padding:34px 26px 26px;box-shadow:0 20px 40px rgba(15,40,25,.14);
    border-top:4px solid var(--g);overflow:hidden}
  .lg-stage>svg{display:none}
  .lg-form{position:static;width:100%}
  .lg-enter{display:none}
  .lg-mob-btn{display:flex;width:100%;margin-top:22px;align-items:center;justify-content:center;gap:10px;border:0;border-radius:10px;padding:14px;color:#fff;
    background:linear-gradient(135deg,var(--gd),var(--gx));font-size:12px;letter-spacing:.14em;font-weight:700;cursor:pointer}
}
</style>
<div class="lg">
  <?php if ($error): ?><div class="lg-msg lg-err"><?php echo htmlspecialchars($error); ?><?php if (!empty($adminPortalHint)): ?> <a href="<?php echo url('admin/portal.php'); ?>">Open the admin portal</a><?php endif; ?></div><?php endif; ?>
  <?php if ($success): ?><div class="lg-msg lg-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($continueUrl): ?><div class="lg-msg lg-info">You're signed in as <strong><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? ''); ?></strong>. <a href="<?php echo htmlspecialchars($continueUrl); ?>">Continue</a> or sign in below with a different account.</div><?php endif; ?>

  <form method="POST" class="lg-stage" autocomplete="on">
    <svg viewBox="0 0 600 620" aria-hidden="true">
      <defs>
        <linearGradient id="lgShardA" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#15A24A"/><stop offset="1" stop-color="#0b5d2b"/></linearGradient>
        <linearGradient id="lgShardB" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b5d2b"/><stop offset="1" stop-color="#06301a"/></linearGradient>
      </defs>
      <!-- green outline triangle behind, shifted up-right -->
      <polygon points="70,10 590,290 70,570" fill="none" stroke="#4ade80" stroke-width="4" opacity=".9"/>
      <polygon points="82,30 560,290 82,550" fill="none" stroke="#4ade80" stroke-width="1.5" opacity=".6"/>
      <!-- dark shards holding ENTER -->
      <polygon points="345,400 520,330 505,600" fill="url(#lgShardB)"/>
      <polygon points="330,420 480,345 470,590 380,520" fill="url(#lgShardA)"/>
      <polygon points="455,560 500,600 470,605" fill="none" stroke="#4ade80" stroke-width="3"/>
      <!-- light frame and white main triangle -->
      <polygon points="18,30 595,320 18,610" fill="#eef2ef"/>
      <polygon points="34,58 560,320 34,582" fill="#ffffff"/>
      <!-- corner ticks -->
      <polyline points="54,92 54,80 66,86" fill="none" stroke="#4ade80" stroke-width="1.5"/>
      <polyline points="496,284 508,290 496,300" fill="none" stroke="#4ade80" stroke-width="1.5"/>
      <polyline points="54,548 54,560 66,554" fill="none" stroke="#4ade80" stroke-width="1.5"/>
    </svg>

    <div class="lg-form">
      <img class="lg-logo" src="<?php echo url('assets/icons/students-512.png'); ?>" alt="Dazu Training Institute">
      <h1 class="lg-t">Log into<b>Dazu portal</b></h1>
      <div class="lg-bar"></div>
      <div class="lg-f"><input type="text" id="email" name="email" required autocomplete="username" aria-label="Email or registration number"
             placeholder="Email or reg. number…" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
      <div class="lg-f"><input type="password" id="password" name="password" required autocomplete="current-password" aria-label="Password" placeholder="Password…">
        <button type="button" class="lg-eye" id="pwToggle" aria-label="Show password" aria-pressed="false" title="Show password">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg></button></div>
      <a class="lg-forgot" href="<?php echo url('auth/forgot_password.php'); ?>">Forgot password?</a>
      <button type="submit" class="lg-mob-btn"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>ENTER</button>
    </div>

    <button type="submit" class="lg-enter" aria-label="Sign in">
      <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/><circle cx="12" cy="16" r="1.2" fill="currentColor"/></svg>ENTER</button>
  </form>

  <div class="lg-links"><a class="ap" href="<?php echo url('courses/index.php'); ?>">New student? Apply here</a><a href="<?php echo url('/'); ?>">← Back to website</a></div>
</div>
<script>(function () { var b = document.getElementById('pwToggle'), i = document.getElementById('password');
  b.addEventListener('click', function () { var show = i.type === 'password'; i.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show); b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); b.title = b.getAttribute('aria-label');
    b.querySelector('[data-slash]').style.display = show ? '' : 'none'; i.focus(); }); })();</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
