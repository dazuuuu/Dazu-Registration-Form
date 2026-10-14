<?php
// public/admin/import.php — restore a site backup (made with Export Site) over this site. Available while the menu is active.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../app/v1/services/SiteBackupService.php';

Middleware::requireAdmin();

$pdo = \Models\Connection::getInstance()->getConnection();
if ((new SettingModel($pdo))->get('site_import_enabled', '1') === '0') {
    SessionModel::setFlash('warning', 'The Import Site menu is deactivated.');
    header('Location: ' . url('admin/dashboard.php')); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(0);
    $back = 'import.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    if (trim((string) ($_POST['confirm'] ?? '')) !== 'IMPORT') {
        SessionModel::setFlash('error', 'Type IMPORT in the confirmation box to continue.');
        header('Location: ' . $back); exit();
    }
    $zipPath = null;
    $pick = basename((string) ($_POST['server_file'] ?? ''));
    if ($pick !== '' && isset(SiteBackupService::serverImports()[$pick])) {
        $zipPath = SiteBackupService::importsDir() . '/' . $pick;
    } elseif (!empty($_FILES['backup']['tmp_name']) && ($_FILES['backup']['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['backup']['tmp_name'])) {
        $zipPath = $_FILES['backup']['tmp_name'];
    }
    if (!$zipPath) {
        $tooBig = in_array($_FILES['backup']['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        SessionModel::setFlash('error', $tooBig
            ? 'That file is larger than this server allows for uploads. Ask your developer to place it on the server, then pick it from the list.'
            : 'Choose a backup file to import.');
        header('Location: ' . $back); exit();
    }
    $r = (new SiteBackupService($pdo))->import($zipPath);
    if ($r['ok']) {
        // The accounts were replaced by the backup's, so this session may no longer be valid: sign in again.
        SessionModel::setFlash('success', htmlspecialchars('Import complete: ' . $r['tables'] . ' tables and ' . $r['files'] . ' files restored.'
            . (!empty($r['updated']) ? ' The database was updated to V' . $r['version'] . '.' : '') . ' Sign in with the account from the backup.'));
        if (!empty($r['update_error'])) {
            SessionModel::setFlash('error', htmlspecialchars('Updating the database to this version stopped: ' . $r['update_error'] . ' Open System Updates.'));
        }
        header('Location: ' . url('auth/login.php')); exit();
    }
    SessionModel::setFlash('error', htmlspecialchars($r['error']));
    header('Location: ' . $back); exit();
}

$serverFiles = SiteBackupService::serverImports();
$page_title = 'Import site';
$breadcrumbs = [['label' => 'Import site', 'active' => true]];
ob_start();
?>
<div class="row justify-content-center"><div class="col-lg-7">
<div class="card border-danger"><div class="card-header"><h5 class="mb-0 text-danger"><i class="fas fa-file-import me-2"></i>Import a site backup</h5></div><div class="card-body">
    <div class="alert alert-warning small py-2">This <strong>replaces</strong> the data on this site with the backup's data: the database, all accounts and the uploaded files. A copy of the current data is saved first.</div>
    <form method="POST" enctype="multipart/form-data" autocomplete="off" onsubmit="return confirm('Replace this site with the backup?');"><?php echo csrf_field(); ?>
        <label class="form-label">Backup zip</label>
        <input type="file" name="backup" accept=".zip,application/zip" class="form-control mb-1">
        <div class="form-text mb-3">This server accepts uploads up to <?php echo htmlspecialchars(ini_get('upload_max_filesize')); ?>. Bigger? Ask your developer to place it on the server and it will appear in the list below.</div>
        <?php if ($serverFiles): ?>
        <label class="form-label">…or a zip already on the server</label>
        <select name="server_file" class="form-select mb-3"><option value="">— none —</option>
            <?php foreach ($serverFiles as $f => $size): ?><option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?> (<?php echo number_format($size / 1048576, 1); ?> MB)</option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label class="form-label">Type <strong>IMPORT</strong> to confirm</label>
        <input type="text" name="confirm" class="form-control mb-3" required autocomplete="off">
        <button class="btn btn-danger w-100"><i class="fas fa-upload me-1"></i>Import backup</button>
    </form>
</div></div>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../templates/admin/layout.php';
