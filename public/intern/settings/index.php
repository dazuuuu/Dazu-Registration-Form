<?php
// Settings: phone alerts on/off for this device, install the app, and change password.
require __DIR__ . '/../_boot.php';
$page_title = 'Settings';
ob_start();
?>
<div class="ip-card" style="max-width:720px">
    <h3 class="mb-3">Phone alerts</h3>
    <p class="ip-muted">Get alerts for new tasks, deadlines tomorrow, announcements, report decisions and teaching assignments — even when the app is closed.</p>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-success d-none" data-pwa-enable-push><i class="fas fa-bell me-1"></i>Turn on phone alerts</button>
        <button type="button" class="btn btn-outline-secondary" data-push-off="<?php echo in_e(url('intern/notifications/api.php')); ?>"><i class="fas fa-bell-slash me-1"></i>Turn off on this device</button>
    </div>
    <div class="ip-muted mt-2">Alerts need HTTPS and a phone that allows notifications for this app. You still see every alert under Notifications.</div>
</div>
<div class="ip-card" style="max-width:720px">
    <h3 class="mb-3">App</h3>
    <button type="button" class="btn btn-outline-success d-none" data-pwa-install><i class="fas fa-download me-1"></i>Install the Dazu Intern app</button>
    <div class="ip-muted mt-2" data-pwa-install-help></div>
    <div class="ip-muted">The app works on slow internet. If you clock in with no connection, it is saved on your phone and sent when you are back online.</div>
</div>
<div class="ip-card" style="max-width:720px">
    <h3 class="mb-3">Password</h3>
    <a class="btn btn-outline-secondary" href="<?php echo url('auth/change_password.php'); ?>"><i class="fas fa-key me-1"></i>Change password</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
