<?php
// public/superadmin/backup.php — export the whole site (database + uploads) and import it again, e.g. to move to another host.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/SiteBackupService.php';

Middleware::requireSuperPortal();

$pdo = \Models\Connection::getInstance()->getConnection();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$svc = new SiteBackupService($pdo);

/** Both actions expose or replace everything, so the superadmin's own password is required (wrong guesses are throttled). */
function backup_password_ok(PDO $pdo, int $userId): ?string
{
    if ($wait = (int) max(0, ($_SESSION['sa_pw_block_until'] ?? 0) - time())) {
        return 'Too many wrong passwords. Try again in ' . ceil($wait / 60) . ' minute(s).';
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? AND role_id = 1');
    $stmt->execute([$userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        $_SESSION['sa_pw_fail'] = (int) ($_SESSION['sa_pw_fail'] ?? 0) + 1;
        if ($_SESSION['sa_pw_fail'] >= 5) { $_SESSION['sa_pw_block_until'] = time() + 300; $_SESSION['sa_pw_fail'] = 0; }
        return 'That password is not correct.';
    }
    $_SESSION['sa_pw_fail'] = 0;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'backup.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'import' && ($err = backup_password_ok($pdo, $userId))) {
        SessionModel::setFlash('error', $err);
        header('Location: ' . $back); exit();
    }

    if ($action === 'export') {
        @ignore_user_abort(true);
        $tmp = tempnam(sys_get_temp_dir(), 'dazu_bak_');
        try {
            $svc->export($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            error_log('[backup export] ' . $e->getMessage());
            SessionModel::setFlash('error', 'Export failed: ' . htmlspecialchars($e->getMessage()));
            header('Location: ' . $back); exit();
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

    if ($action === 'import') {
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
            $tooBig = ($_FILES['backup']['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($_FILES['backup']['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE;
            SessionModel::setFlash('error', $tooBig
                ? 'That file is larger than this server allows for uploads. Ask your developer to place it on the server instead (see below).'
                : 'Choose a backup file to import.');
            header('Location: ' . $back); exit();
        }
        $r = $svc->import($zipPath);
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', htmlspecialchars($r['ok']
            ? 'Import complete: ' . $r['tables'] . ' tables and ' . $r['files'] . ' files restored. If you cannot sign in, use the account from the backup.'
            : $r['error']));
        header('Location: ' . $back); exit();
    }
    header('Location: ' . $back); exit();
}

$serverFiles = SiteBackupService::serverImports();
$mb = fn(int $b): string => number_format($b / 1048576, 1) . ' MB';
$limit = ini_get('upload_max_filesize') . ' upload / ' . ini_get('post_max_size') . ' post';

$page_title = 'Backup & migrate';
$sa_subtitle = 'Export the whole website, or restore one on this server.';
ob_start();
?>
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><strong>Moving hosts?</strong> On the new host you can pull everything straight from the live database and site, with no zip needed.</span>
    <a class="btn btn-sm btn-primary" href="<?php echo url('superadmin/migrate.php'); ?>">Pull from live</a>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header fw-semibold"><i class="fas fa-file-export me-2"></i>Export everything</div><div class="card-body">
            <p class="small text-muted">Downloads one zip with the whole database (students, courses, payments, settings, users) plus every uploaded image, video and document, and the private files (receipts, mail key). </p>
            <p class="small text-muted">The zip holds sign-in password hashes and student data. Keep it private.</p>
            <form method="POST" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="action" value="export">
                <button class="btn btn-primary w-100"><i class="fas fa-download me-1"></i>Download full backup</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Large sites can take a few minutes to prepare. Leave the page open until the download starts.</p>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 border-danger"><div class="card-header fw-semibold text-danger"><i class="fas fa-file-import me-2"></i>Import a backup</div><div class="card-body">
            <div class="alert alert-warning small py-2">This <strong>replaces</strong> the data on this site with the backup's data (same tables, same uploads). A copy of the current data is saved first.</div>
            <form method="POST" enctype="multipart/form-data" autocomplete="off" onsubmit="return confirm('Replace this site\'s data with the backup?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="import">
                <label class="form-label">Backup zip</label>
                <input type="file" name="backup" accept=".zip,application/zip" class="form-control mb-1">
                <div class="form-text mb-3">This server accepts <?php echo htmlspecialchars($limit); ?>.</div>
                <?php if ($serverFiles): ?>
                <label class="form-label">…or a zip already on the server</label>
                <select name="server_file" class="form-select mb-3"><option value="">— none —</option>
                    <?php foreach ($serverFiles as $f => $size): ?><option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?> (<?php echo $mb($size); ?>)</option><?php endforeach; ?>
                </select>
                <?php endif; ?>
                <label class="form-label">Your superadmin password</label>
                <input type="password" name="password" class="form-control mb-3" required autocomplete="current-password">
                <label class="form-label">Type <strong>IMPORT</strong> to confirm</label>
                <input type="text" name="confirm" class="form-control mb-3" required autocomplete="off">
                <button class="btn btn-danger w-100"><i class="fas fa-upload me-1"></i>Import backup</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Backup too big to upload? Ask your developer to place it on the server and it will appear in the list above.</p>
        </div></div>
    </div>
</div>
<div class="card mt-3"><div class="card-header fw-semibold">Moving to a new host</div><div class="card-body small">
    <ol class="mb-0">
        <li>Upload the code to the new host and set its database login.</li>
        <li>Open the site once so it can reach the database, and sign in as superadmin (or run the System Updates so the tables exist).</li>
        <li>Import the backup here. Links to uploaded files are adjusted if the new address or folder is different.</li>
        <li>Sign in again with your account from the backup.</li>
    </ol>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../templates/superadmin/layout.php';
