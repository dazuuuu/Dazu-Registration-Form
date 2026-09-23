<?php
// public/setup.php — first-launch setup for a NEW host: restore a site backup made from the admin "Export Site" page.
//
// Needs no configuration: it asks for the default PIN (111333) and only works while the site has no accounts yet
// (a fresh install). Once a backup is restored the page locks itself.
require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/services/SiteBackupService.php';
require_once __DIR__ . '/../app/v1/helpers/superadmin_pin.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$pdo = \Models\Connection::getInstance()->getConnection();

/** A fresh install: no users table yet, no users, or only the default superadmin the migrations seed. */
function setup_is_fresh(PDO $pdo): bool
{
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username <> 'superadmin.dazuhub'")->fetchColumn();
        return $n === 0;
    } catch (Throwable $e) {
        return true;
    }
}

$fresh = setup_is_fresh($pdo);
$state = !$fresh ? 'locked' : 'open';
$message = null;
$done = null;

if ($state === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(0);
    $wait = (int) max(0, ($_SESSION['setup_block_until'] ?? 0) - time());
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = 'Security check failed. Reload the page and try again.';
    } elseif ($wait) {
        $message = 'Too many wrong PINs. Try again in ' . ceil($wait / 60) . ' minute(s).';
    } elseif (!hash_equals(SA_DEFAULT_PIN, (string) ($_POST['setup_pin'] ?? ''))) {
        $_SESSION['setup_fail'] = (int) ($_SESSION['setup_fail'] ?? 0) + 1;
        if ($_SESSION['setup_fail'] >= 5) { $_SESSION['setup_block_until'] = time() + 900; $_SESSION['setup_fail'] = 0; }
        $message = 'That PIN is not correct.';
    } else {
        $zipPath = null;
        $pick = basename((string) ($_POST['server_file'] ?? ''));
        if ($pick !== '' && isset(SiteBackupService::serverImports()[$pick])) {
            $zipPath = SiteBackupService::importsDir() . '/' . $pick;
        } elseif (!empty($_FILES['backup']['tmp_name']) && ($_FILES['backup']['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['backup']['tmp_name'])) {
            $zipPath = $_FILES['backup']['tmp_name'];
        } elseif (in_array($_FILES['backup']['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $message = 'That file is larger than this server allows for uploads. Ask your developer to place it on the server, then pick it from the list.';
        } else {
            $message = 'Choose the backup zip to restore.';
        }
        if ($zipPath) {
            $r = (new SiteBackupService($pdo))->import($zipPath);
            if ($r['ok']) {
                $done = $r;
                $state = 'done';
                $_SESSION['setup_fail'] = 0;
            } else {
                $message = $r['error'];
            }
        }
    }
}

$serverFiles = $state === 'open' ? SiteBackupService::serverImports() : [];
$limit = ini_get('upload_max_filesize');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Site setup</title>
<style>
:root{--bg:#f4f6f8;--card:#fff;--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--acc:#15a24a;--bad:#dc2626}
@media (prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#131c2e;--ink:#e5e7eb;--mut:#94a3b8;--line:#243047}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,sans-serif}
main{max-width:520px;margin:0 auto;padding:32px 16px}h1{font-size:1.4rem;margin:0 0 4px}.s{color:var(--mut);margin:0 0 20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px}
label{display:block;font-weight:600;font-size:.85rem;margin:14px 0 4px}input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:transparent;color:inherit;font:inherit}
button,.btn{display:block;margin-top:18px;width:100%;padding:12px;border:0;border-radius:8px;background:var(--acc);color:#fff;font-weight:700;font-size:1rem;cursor:pointer;text-align:center;text-decoration:none}
.err{color:var(--bad);margin:0 0 12px}.mut{color:var(--mut);font-size:.85rem}code{background:var(--line);padding:1px 5px;border-radius:4px}
</style></head><body><main>
<h1>Site setup</h1>
<p class="s">Restore a backup made with <strong>Export Site</strong> on the old host.</p>
<div class="card">
<?php if ($state === 'locked'): ?>
  <p><strong>This site is already set up.</strong></p>
  <p class="mut">Setup only runs on an empty site, so it is locked. <a href="<?php echo htmlspecialchars(url('auth/login.php')); ?>">Go to sign in</a>.</p>
<?php elseif ($state === 'done'): ?>
  <p><strong>Backup restored.</strong> <?php echo (int) $done['tables']; ?> tables and <?php echo (int) $done['files']; ?> files.</p>
  <p class="mut">The accounts came across with it, so sign in with the same details as on the old site. Delete the backup zip from the server.</p>
  <a class="btn" href="<?php echo htmlspecialchars(url('auth/login.php')); ?>">Go to sign in</a>
<?php else: ?>
  <?php if ($message): ?><p class="err"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <form method="POST" enctype="multipart/form-data" autocomplete="off"><?php echo csrf_field(); ?>
    <label>PIN</label><input type="password" name="setup_pin" inputmode="numeric" required autocomplete="off">
    <div class="mut">The default PIN.</div>
    <label>Backup zip</label><input type="file" name="backup" accept=".zip,application/zip">
    <div class="mut">This server accepts uploads up to <?php echo htmlspecialchars($limit); ?>.</div>
    <?php if ($serverFiles): ?>
    <label>…or a zip already on the server</label>
    <select name="server_file"><option value="">— none —</option>
      <?php foreach ($serverFiles as $f => $size): ?><option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?> (<?php echo number_format($size / 1048576, 1); ?> MB)</option><?php endforeach; ?>
    </select>
    <?php else: ?>
    <div class="mut" style="margin-top:8px">Zip too big to upload? Ask your developer to place it on the server; it will then appear in a list here.</div>
    <?php endif; ?>
    <button>Restore backup</button>
  </form>
<?php endif; ?>
</div>
</main></body></html>
