<?php
// public/superadmin/settings.php — security settings: the dashboard PIN.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/superadmin_pin.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';

Middleware::requireSuperPortal();

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'settings.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'lock_now') {
        sa_pin_lock();
        SessionModel::setFlash('success', 'Dashboard locked.');
        header('Location: ' . $back); exit();
    }

    if ($action === 'change_pin' || $action === 'reset_pin') {
        // Changing the PIN needs the superadmin's own password. Wrong passwords are throttled like PIN guesses.
        if ($wait = (int) max(0, ($_SESSION['sa_pw_block_until'] ?? 0) - time())) {
            SessionModel::setFlash('error', 'Too many wrong passwords. Try again in ' . ceil($wait / 60) . ' minute(s).');
            header('Location: ' . $back); exit();
        }
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? AND role_id = 1');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();
        $password = (string) ($_POST['password'] ?? '');

        if ($hash === '' || !password_verify($password, $hash)) {
            $_SESSION['sa_pw_fail'] = (int) ($_SESSION['sa_pw_fail'] ?? 0) + 1;
            if ($_SESSION['sa_pw_fail'] >= 5) { $_SESSION['sa_pw_block_until'] = time() + 300; $_SESSION['sa_pw_fail'] = 0; }
            SessionModel::setFlash('error', 'That password is not correct, so the PIN was not changed.');
            header('Location: ' . $back); exit();
        }
        unset($_SESSION['sa_pw_fail']);

        if ($action === 'reset_pin') {
            $ok = sa_pin_set($pdo, $userId, null);
            sa_pin_lock();
            SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'PIN reset. Set a new one below.' : 'Could not save the PIN. Apply the pending system update and try again.');
            header('Location: ' . $back); exit();
        }

        $new = (string) ($_POST['new_pin'] ?? '');
        $confirm = (string) ($_POST['confirm_pin'] ?? '');
        if (!sa_pin_valid_format($new)) {
            SessionModel::setFlash('error', 'The PIN must be 4 to 8 digits (numbers only).');
        } elseif ($new !== $confirm) {
            SessionModel::setFlash('error', 'The two PINs do not match.');
        } elseif ($new === SA_DEFAULT_PIN) {
            SessionModel::setFlash('error', 'That PIN is too easy to guess. Choose a different one.');
        } elseif (!sa_pin_set($pdo, $userId, $new)) {
            SessionModel::setFlash('error', 'Could not save the PIN. Apply the pending system update and try again.');
        } else {
            sa_pin_lock();
            SessionModel::setFlash('success', 'PIN changed. The dashboard is locked, so enter the new PIN to view it.');
        }
        header('Location: ' . $back); exit();
    }
    header('Location: ' . $back); exit();
}

$unlocked = sa_pin_unlocked();

$page_title = 'Settings';
$sa_subtitle = 'Security for the superadmin dashboard.';
ob_start();
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header fw-semibold"><i class="fas fa-lock me-2"></i>Dashboard PIN</div><div class="card-body">
            <p class="small text-muted">The PIN unblurs the money figures and student details on the dashboard. It stays unlocked for <?php echo (int)(SA_PIN_TTL / 60); ?> minutes, then blurs again. To change it you must enter your superadmin password.</p>
            <form method="POST" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="change_pin">
                <label class="form-label">New PIN <span class="text-muted small">(4–8 digits)</span></label>
                <input type="password" name="new_pin" class="form-control mb-3" inputmode="numeric" pattern="\d{4,8}" maxlength="8" required autocomplete="new-password">
                <label class="form-label">Confirm new PIN</label>
                <input type="password" name="confirm_pin" class="form-control mb-3" inputmode="numeric" pattern="\d{4,8}" maxlength="8" required autocomplete="new-password">
                <label class="form-label">Your superadmin password</label>
                <input type="password" name="password" class="form-control mb-3" required autocomplete="current-password">
                <button class="btn btn-primary w-100"><i class="fas fa-key me-1"></i>Change PIN</button>
            </form>
            <hr>
            <form method="POST" onsubmit="return confirm('Reset the PIN?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reset_pin">
                <label class="form-label small">Forgot your PIN? Enter your password to reset it</label>
                <div class="input-group input-group-sm"><input type="password" name="password" class="form-control" placeholder="Your superadmin password" required autocomplete="current-password"><button class="btn btn-outline-secondary">Reset</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header fw-semibold"><i class="fas fa-eye-slash me-2"></i>Dashboard visibility</div><div class="card-body">
            <p class="mb-2">Right now the dashboard is <strong><?php echo $unlocked ? 'unlocked' : 'locked'; ?></strong><?php echo $unlocked ? ' (' . ceil(sa_pin_seconds_left() / 60) . ' min left)' : ''; ?>.</p>
            <p class="small text-muted">While locked, the server sends only placeholder figures, so nothing sensitive is hidden merely by blurring.</p>
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="lock_now">
                <button class="btn btn-outline-secondary" <?php echo $unlocked ? '' : 'disabled'; ?>><i class="fas fa-lock me-1"></i>Lock now</button>
            </form>
        </div></div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../templates/superadmin/layout.php';
