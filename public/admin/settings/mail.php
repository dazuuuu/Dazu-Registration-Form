<?php
// public/admin/settings/mail.php — SMTP credentials for every email the system sends (one shared configuration).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/mail_settings.php';
require_once __DIR__ . '/../../../app/v1/services/Mailer.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

Middleware::requireAdmin();

$error = null;
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $r = mail_settings_save($pdo, $_POST);
        if (!$r['ok']) {
            $error = $r['error'];
        } else {
            $to = trim((string) ($_POST['test_to'] ?? ''));
            if ($to !== '') {
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Settings saved, but the test address is not a valid email.';
                } else {
                    // Send with exactly what is saved now, so a pass here means real emails will work too.
                    $cfg = require __DIR__ . '/../../../app/v1/config/mail.php';
                    $cfg['enabled'] = true;
                    $res = (new Mailer($cfg))->send($to, 'Test', 'Test email from ' . ($cfg['from_name'] ?? 'Dazu Training Institute'),
                        '<div style="font-family:Arial,sans-serif"><h3 style="color:#15a24a">Email is working</h3><p>This is a test message sent from the admin settings page. If you can read this, the SMTP details are correct.</p></div>');
                    $testResult = ['ok' => !empty($res['ok']), 'message' => (string) ($res['message'] ?? ''), 'to' => $to];
                }
            }
            if (!$error) {
                SessionModel::setFlash('success', 'Email settings saved.' . ($testResult ? ($testResult['ok'] ? ' Test email sent to ' . htmlspecialchars($testResult['to']) . '.' : '') : ''));
                if ($testResult && !$testResult['ok']) {
                    SessionModel::setFlash('error', 'The test email FAILED: ' . htmlspecialchars($testResult['message']));
                }
                header('Location: mail.php');
                exit();
            }
        }
    }
}

// Show the saved values (never the password) merged with the defaults currently in effect.
$cfg = require __DIR__ . '/../../../app/v1/config/mail.php';
$saved = mail_settings_read();
$hasPassword = (string) ($cfg['password'] ?? '') !== '';
$isSet = trim((string) ($cfg['host'] ?? '')) !== '' && trim((string) ($cfg['username'] ?? '')) !== '' && $hasPassword;
$val = fn(string $k, $default = '') => htmlspecialchars((string) ($_POST[$k] ?? $cfg[$k] ?? $default), ENT_QUOTES);
$enc = $_POST['encryption'] ?? (($cfg['encryption'] ?? 'tls') ?: 'none');
$enabled = $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['enabled']) : !empty($cfg['enabled']);

$page_title = 'Email settings';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Email', 'active' => true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!empty($saved['password_unreadable'])): ?><div class="alert alert-warning">The saved password could not be read (its encryption key is missing). Enter the password again and save.</div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card"><div class="card-header fw-semibold"><i class="fas fa-envelope me-2"></i>SMTP server</div><div class="card-body">
      <p class="text-muted small">One setup for every email the system sends: student credentials, receipts, tutor and staff accounts, password resets and enquiries.
        <?php if ($isSet): ?><span class="badge bg-success">Configured</span><?php else: ?><span class="badge bg-danger">Not configured</span><?php endif; ?></p>
      <form method="POST" autocomplete="off"><?php echo csrf_field(); ?>
        <div class="row g-3">
          <div class="col-md-8"><label class="form-label">SMTP host</label><input name="host" class="form-control" value="<?php echo $val('host'); ?>" placeholder="smtp.gmail.com" required></div>
          <div class="col-md-4"><label class="form-label">Port</label><input name="port" type="number" class="form-control" value="<?php echo $val('port', 587); ?>" min="1" max="65535" required></div>
          <div class="col-md-4"><label class="form-label">Encryption</label>
            <select name="encryption" class="form-select">
              <option value="tls" <?php echo $enc === 'tls' ? 'selected' : ''; ?>>TLS (port 587)</option>
              <option value="ssl" <?php echo $enc === 'ssl' ? 'selected' : ''; ?>>SSL (port 465)</option>
              <option value="none" <?php echo $enc === 'none' ? 'selected' : ''; ?>>None</option>
            </select></div>
          <div class="col-md-8"><label class="form-label">Username <span class="text-muted small">(usually the full email address)</span></label><input name="username" class="form-control" value="<?php echo $val('username'); ?>" autocomplete="off" required></div>
          <div class="col-12"><label class="form-label">Password <span class="text-muted small">(for Gmail, use an App Password)</span></label>
            <input name="password" type="password" class="form-control" autocomplete="new-password" placeholder="<?php echo $hasPassword ? '•••••••• saved. Leave blank to keep it' : 'SMTP password'; ?>" <?php echo $hasPassword ? '' : 'required'; ?>>
            <div class="form-text">Stored encrypted. It is never shown again after saving.</div></div>
          <div class="col-md-6"><label class="form-label">"From" email <span class="text-muted small">(optional)</span></label><input name="from_email" type="email" class="form-control" value="<?php echo $val('from_email'); ?>" placeholder="no-reply@yourdomain.com"></div>
          <div class="col-md-6"><label class="form-label">"From" name</label><input name="from_name" class="form-control" value="<?php echo $val('from_name'); ?>" maxlength="100"></div>
          <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="en" <?php echo $enabled ? 'checked' : ''; ?>><label class="form-check-label" for="en">Send emails from the system</label></div></div>
          <div class="col-12"><hr class="my-1"><label class="form-label mt-2">Send a test email to <span class="text-muted small">(optional; verifies the details right now)</span></label>
            <input name="test_to" type="email" class="form-control" value="<?php echo htmlspecialchars((string)($_POST['test_to'] ?? ($_SESSION['email'] ?? '')), ENT_QUOTES); ?>" placeholder="you@example.com"></div>
        </div>
        <button class="btn btn-primary mt-3"><i class="fas fa-paper-plane me-1"></i>Save and send test</button>
        <div class="form-text mt-1">Leave the test address empty to only save.</div>
      </form>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header fw-semibold">Common setups</div><div class="card-body small">
      <p class="mb-2"><strong>Gmail</strong><br>Host smtp.gmail.com, port 587, TLS. The password must be a 16-character <em>App Password</em> (Google account → Security → 2-Step Verification → App passwords), not your normal password.</p>
      <p class="mb-2"><strong>cPanel / your own domain</strong><br>Host mail.yourdomain.com, port 465 with SSL (or 587 with TLS). Username is the full mailbox address.</p>
      <p class="mb-0 text-muted">If the test fails, the exact reason from the mail server is shown, so you can correct it here without touching any files.</p>
    </div></div>
  </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
