<?php
// public/admin/backup.php — export the whole site (database + uploads) as one zip, e.g. to move to another host.
// Exporting is one click. The Export and Import features each have an on/off switch; switching needs the PIN.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../app/v1/helpers/superadmin_pin.php';
require_once __DIR__ . '/../../app/v1/services/SiteBackupService.php';

Middleware::requireAdmin();

$pdo = \Models\Connection::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$settings = new SettingModel($pdo);
$features = ['export' => 'site_export_enabled', 'import' => 'site_import_enabled'];
$on = fn(string $f): bool => $settings->get($features[$f], '1') !== '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif ($action === 'toggle' && isset($features[$_POST['feature'] ?? ''])) {
        $f = $_POST['feature'];
        if (!sa_pin_verify($pdo, $userId, (string) ($_POST['pin'] ?? ''))) {
            SessionModel::setFlash('error', 'That PIN is not correct.');
        } else {
            $now = $on($f);
            $settings->set($features[$f], $now ? '0' : '1');
            SessionModel::setFlash('success', ucfirst($f) . ' is now ' . ($now ? 'deactivated' : 'active') . '.');
        }
    } elseif ($action === 'export' && $on('export')) {
        @set_time_limit(0);
        @ignore_user_abort(true);
        $tmp = tempnam(sys_get_temp_dir(), 'dazu_bak_');
        try {
            (new SiteBackupService($pdo))->export($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            error_log('[admin backup export] ' . $e->getMessage());
            SessionModel::setFlash('error', 'Export failed: ' . htmlspecialchars($e->getMessage()));
            header('Location: backup.php'); exit();
        }
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="site-backup-' . date('Y-m-d-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
        exit();
    }
    header('Location: backup.php'); exit();
}

$page_title = 'Export site';
$breadcrumbs = [['label' => 'Export site', 'active' => true]];
$toggle = function (string $f, string $label) use ($on): string {
    $isOn = $on($f);
    ob_start(); ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 border-top">
        <div><strong><?php echo $label; ?></strong> <span class="badge <?php echo $isOn ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $isOn ? 'Active' : 'Deactivated'; ?></span></div>
        <form method="POST" class="input-group input-group-sm" style="max-width:260px" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="feature" value="<?php echo $f; ?>">
            <input type="password" name="pin" class="form-control" placeholder="PIN" inputmode="numeric" required autocomplete="off">
            <button class="btn <?php echo $isOn ? 'btn-outline-danger' : 'btn-outline-success'; ?>"><?php echo $isOn ? 'Deactivate' : 'Activate'; ?></button>
        </form>
    </div>
    <?php return ob_get_clean();
};
ob_start();
?>
<div class="row justify-content-center"><div class="col-lg-7">
<?php if ($on('export')): ?>
<div class="card mb-3"><div class="card-header"><h5 class="mb-0"><i class="fas fa-file-export me-2"></i>Export the whole site</h5></div><div class="card-body">
    <p class="text-muted small">Downloads one zip with everything in this site: the whole database (students, courses, payments, settings and all sign-in accounts) plus every uploaded image, video and document, and the private files. </p>
    <p class="text-muted small">Restore it on the new host through the site's setup page, or through Import Site. The accounts come across too, so you sign in with the same details.</p>
    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="export">
        <button class="btn btn-success w-100"><i class="fas fa-download me-1"></i>Download full backup</button>
    </form>
    <p class="small text-muted mt-3 mb-0">Large sites can take a few minutes to prepare. Leave the page open until the download starts. The zip holds student data and password hashes, so keep it private.</p>
</div></div>
<?php else: ?>
<div class="alert alert-secondary">Export is deactivated. Switch it on below with the PIN.</div>
<?php endif; ?>
<div class="card"><div class="card-header"><h5 class="mb-0">Turn features on or off</h5></div><div class="card-body pt-2">
    <p class="small text-muted mb-1">Switching needs the PIN. When a feature is off its menu item is hidden and its page is closed.</p>
    <?php echo $toggle('export', 'Export Site'); ?>
    <?php echo $toggle('import', 'Import Site'); ?>
</div></div>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../templates/admin/layout.php';
