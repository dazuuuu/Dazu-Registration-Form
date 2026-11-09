<?php
// Superadmin: Vendor packages. Checks each Composer package in app/v1/vendor against the file manifest and downloads a
// missing or broken one straight onto the server (VendorRepair.php), so the vendor folder never has to be uploaded.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/services/VendorRepair.php';
Middleware::requireSuperPortal();

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$self = url('superadmin/vendor.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msgs = [];
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $msgs[] = [false, 'Security check failed. Please refresh and try again.'];
    } else {
        $check = vr_check();
        $names = ($_POST['package'] ?? '') === '*broken*'
            ? array_keys(array_filter($check['packages'], fn($p) => !$p['ok']))
            : array_intersect([(string) ($_POST['package'] ?? '')], array_keys($check['packages']));
        if (!$names) $msgs[] = [true, 'Nothing to repair: every package is complete.'];
        foreach ($names as $n) { $r = vr_install($n); $msgs[] = [$r['ok'], $r['message']]; }
    }
    $_SESSION['vr_flash'] = $msgs;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['vr_flash'] ?? [];
unset($_SESSION['vr_flash']);
$c = vr_check();
$broken = count(array_filter($c['packages'], fn($p) => !$p['ok']));
$page_title = 'Vendor packages';
ob_start();
?>
<div class="container-fluid py-3">
  <h4 class="mb-1">Vendor packages</h4>
  <p class="text-muted small mb-3">The PHP libraries in <code>app/v1/vendor</code> (PDFs, email…). You don't upload this folder: a missing or broken package is downloaded here,
    straight onto the server, in exactly the version the system uses, and checked file by file before it replaces anything. <a href="<?php echo url('superadmin/file_check.php'); ?>">File check</a></p>
  <?php foreach ($flash as [$ok, $m]): ?><div class="alert alert-<?php echo $ok ? 'success' : 'danger'; ?> py-2"><?php echo $e($m); ?></div><?php endforeach; ?>
  <?php if (!$c['manifest']): ?><div class="alert alert-warning">app/v1/file_manifest.json is missing, so downloads cannot be checked. Upload it first.</div><?php endif; ?>
  <?php if (!$c['autoloader']['ok']): ?><div class="alert alert-danger">The Composer autoloader is missing files (<?php echo $e(implode(', ', array_slice($c['autoloader']['missing'], 0, 6))); ?>). These 13 small files can't be downloaded: upload <code>app/v1/vendor/autoload.php</code> and the <code>app/v1/vendor/composer/</code> folder.</div><?php endif; ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0"><table class="table align-middle mb-0">
      <thead class="table-light"><tr><th class="ps-3">Package</th><th>Version</th><th>State</th><th class="text-end pe-3">
        <?php if ($broken): ?><form method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Downloading…'"><?php echo csrf_field(); ?><input type="hidden" name="package" value="*broken*"><button class="btn btn-success btn-sm"><i class="fas fa-download me-1"></i>Repair all (<?php echo $broken; ?>)</button></form><?php endif; ?></th></tr></thead>
      <tbody>
      <?php foreach ($c['packages'] as $name => $p): ?>
        <tr><td class="ps-3 fw-semibold"><?php echo $e($name); ?></td><td><?php echo $e($p['version']); ?></td>
          <td><?php if ($p['ok']): ?><span class="badge bg-success">Complete (<?php echo (int) $p['files']; ?> files)</span>
            <?php elseif (!$p['present']): ?><span class="badge bg-danger">Folder missing</span>
            <?php else: ?><span class="badge bg-warning text-dark"><?php echo count($p['missing']); ?> missing, <?php echo count($p['different']); ?> different</span>
              <div class="small text-muted text-break"><?php echo $e(implode(', ', array_slice(array_merge($p['missing'], $p['different']), 0, 4))); ?><?php echo count($p['missing']) + count($p['different']) > 4 ? '…' : ''; ?></div><?php endif; ?></td>
          <td class="text-end pe-3"><form method="POST" class="d-inline" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerText='Downloading…'"><?php echo csrf_field(); ?><input type="hidden" name="package" value="<?php echo $e($name); ?>">
            <button class="btn btn-sm <?php echo $p['ok'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><?php echo $p['ok'] ? 'Download again' : 'Download & repair'; ?></button></form></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/superadmin/layout.php';
