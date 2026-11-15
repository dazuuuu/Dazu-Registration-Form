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
/* Sign-in shaped like the Dazu logo: the graduation cap is the header, the two green swooshes of the "A" frame the form,
   and the round dot is the ENTER button. Phones get the same pieces stacked as a card. */
.lg{--g:#15A24A;--g2:#0c7a35;--g3:#064d22;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:radial-gradient(circle at 50% 40%,#ffffff 0%,#eef6f0 65%,#e3efe6 100%);padding:36px 16px}
.lg-msg{width:100%;max-width:520px;margin-bottom:14px;font-size:12px;font-weight:600;border-radius:10px;padding:12px 14px}
.lg-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.lg-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d}
.lg-info{background:#fff;border:1px solid #e2e8f0;color:#334155}.lg-msg a{color:var(--g);text-decoration:underline}
.lg-stage{position:relative;width:640px;height:720px}
.lg-stage>svg{position:absolute;inset:0;width:100%;height:100%;filter:drop-shadow(0 22px 34px rgba(6,77,34,.18))}
.lg-cap{position:absolute;left:0;right:0;top:78px;text-align:center;color:#fff;pointer-events:none}
.lg-cap small{display:block;font-size:10px;letter-spacing:.3em;text-transform:uppercase;opacity:.85}
.lg-cap b{display:block;font-size:22px;letter-spacing:.12em;text-transform:uppercase;font-weight:800;margin-top:2px}
.lg-panel{position:absolute;left:160px;top:232px;width:320px;background:#fff;border-radius:18px;padding:26px 28px 20px;box-shadow:0 18px 40px rgba(6,77,34,.16)}
.lg-f{position:relative;margin-bottom:16px}
.lg-f label{display:block;font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#64748b;margin-bottom:4px}
.lg-f input{width:100%;border:0;border-bottom:2px solid #e2e8f0;background:transparent;padding:8px 34px 8px 2px;font-size:15px;color:#0f172a;outline:none;border-radius:0}
.lg-f input:focus{border-bottom-color:var(--g)}
.lg-eye{position:absolute;right:0;bottom:6px;border:0;background:none;color:#94a3b8;padding:4px;cursor:pointer}.lg-eye:hover{color:var(--g)}
.lg-eye svg{width:18px;height:18px}
.lg-forgot{font-size:11px;font-weight:700;color:var(--g);text-decoration:none}.lg-forgot:hover{text-decoration:underline}
.lg-dot{position:absolute;left:262px;top:560px;width:116px;height:116px;border-radius:50%;border:0;cursor:pointer;color:#fff;
  background:radial-gradient(circle at 35% 30%,#22c55e,#15A24A 55%,#0c7a35);box-shadow:0 14px 30px rgba(6,77,34,.35);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;font-size:11px;font-weight:800;letter-spacing:.16em;transition:transform .15s}
.lg-dot:hover{transform:scale(1.05)}.lg-dot svg{width:24px;height:24px}
.lg-links{margin-top:10px;display:flex;gap:18px;font-size:12px}
.lg-links a{color:#64748b;text-decoration:none}.lg-links a.ap{color:var(--g);font-weight:700}
@media(max-width:680px){
  .lg-stage{width:100%;max-width:400px;height:auto;padding-top:120px;padding-bottom:20px}
  .lg-stage>svg{display:none}
  .lg-stage::before{content:"";position:absolute;left:50%;top:0;width:260px;height:110px;margin-left:-130px;background:linear-gradient(135deg,#22c55e,#0c7a35);
    clip-path:polygon(50% 0,100% 45%,50% 90%,0 45%)}
  .lg-cap{top:30px}
  .lg-panel{position:relative;left:auto;top:auto;width:100%}
  .lg-dot{position:relative;left:auto;top:auto;margin:22px auto 0;width:96px;height:96px}
}
</style>
<div class="lg">
  <?php if ($error): ?><div class="lg-msg lg-err"><?php echo htmlspecialchars($error); ?><?php if (!empty($adminPortalHint)): ?> <a href="<?php echo url('admin/portal.php'); ?>">Open the admin portal</a><?php endif; ?></div><?php endif; ?>
  <?php if ($success): ?><div class="lg-msg lg-ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($continueUrl): ?><div class="lg-msg lg-info">You're signed in as <strong><?php echo htmlspecialchars($_SESSION['email'] ?? $_SESSION['username'] ?? ''); ?></strong>. <a href="<?php echo htmlspecialchars($continueUrl); ?>">Continue</a> or sign in below with a different account.</div><?php endif; ?>

  <form method="POST" class="lg-stage" autocomplete="on">
    <svg viewBox="0 0 640 720" aria-hidden="true">
      <defs>
        <linearGradient id="lgCap" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#22c55e"/><stop offset="1" stop-color="#0c7a35"/></linearGradient>
        <linearGradient id="lgLegR" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#15A24A"/><stop offset=".7" stop-color="#22c55e"/><stop offset="1" stop-color="#064d22"/></linearGradient>
        <linearGradient id="lgLegL" x1="1" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0c7a35"/><stop offset="1" stop-color="#15A24A"/></linearGradient>
      </defs>
      <!-- left leg of the A -->
      <path d="M330 300 C 300 360, 200 520, 110 700 L 30 700 C 120 560, 230 400, 288 270 Z" fill="url(#lgLegL)"/>
      <!-- right leg (the long swoosh), crossing over -->
      <path d="M230 205 C 300 230, 330 260, 360 320 C 410 420, 470 560, 560 700 L 470 700 C 400 590, 350 470, 300 370 C 270 310, 240 260, 200 225 Z" fill="url(#lgLegR)"/>
      <!-- graduation cap -->
      <polygon points="320,30 600,130 320,230 40,130" fill="url(#lgCap)"/>
      <polygon points="320,190 600,130 600,142 320,202" fill="#0c7a35" opacity=".35"/>
      <!-- tassel -->
      <line x1="560" y1="145" x2="560" y2="250" stroke="#0c7a35" stroke-width="6" stroke-linecap="round"/>
      <path d="M548 250 h24 l6 40 h-36 z" fill="#15A24A"/>
    </svg>
    <div class="lg-cap"><small>Dazu Training Institute</small><b>Portal sign in</b></div>

    <div class="lg-panel">
      <div class="lg-f"><label for="email">Email or registration number</label>
        <input type="text" id="email" name="email" required autocomplete="username" placeholder="student@gmail.com or DCH061/26" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
      <div class="lg-f"><label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="button" class="lg-eye" id="pwToggle" aria-label="Show password" aria-pressed="false" title="Show password">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3" stroke-width="2"/><path data-slash stroke-linecap="round" stroke-width="2" d="M4 4l16 16" style="display:none"/></svg></button></div>
      <a class="lg-forgot" href="<?php echo url('auth/forgot_password.php'); ?>">Forgot password?</a>
    </div>

    <button type="submit" class="lg-dot" aria-label="Sign in">
      <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>ENTER</button>
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
