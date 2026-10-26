<?php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/services/MigrationService.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$migrationService = new MigrationService($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_updates'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed. Please try again.';
        redirect(url('admin/system/updates.php'));
    }

    $result = $migrationService->applyPending();

    if (!empty($result['failed'])) {
        // The file name is kept out of the message; the version it belongs to is what admins know.
        $_SESSION['flash']['error'] = 'The update could not finish: ' . $result['failed'][0]['error'];
    } elseif ($result['ran'] > 0) {
        $_SESSION['flash']['success'] = 'Update installed. You are now on V' . $migrationService->currentVersion() . '.';
    } else {
        $_SESSION['flash']['success'] = 'System is already up to date.';
    }
    redirect(url('admin/system/updates.php'));
}

try {
    $releases = $migrationService->releases();
    $pending = array_values(array_filter($releases, static fn ($r) => !$r['installed']));
    $current = $migrationService->currentVersion();
} catch (Throwable $e) {
    $releases = $pending = [];
    $current = null;
    $errors[] = $e->getMessage();
}
$installed = array_reverse(array_values(array_filter($releases, static fn ($r) => $r['installed'])));
$target = $pending ? end($pending) : null;

$page_title = 'System Updates';
$breadcrumbs = [
    ['label' => 'System Updates', 'active' => true],
];

ob_start();
?>
<style>
.upd-card{border:1px solid #e4e7ec;border-radius:10px;background:#fff;padding:22px;margin-bottom:20px}
.upd-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex:none}
.upd-ver{display:inline-block;font-weight:700;font-size:.8rem;padding:3px 10px;border-radius:999px;background:#eef2f6;color:#344054}
.upd-ver.new{background:#fef0c7;color:#93370d}
.upd-notes{margin:.5rem 0 0;padding-left:1.1rem;color:#475467}
.upd-hist .list-group-item{display:flex;gap:12px;align-items:flex-start;justify-content:space-between}
</style>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="upd-card">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if ($target): ?>
        <div class="upd-icon bg-warning-subtle text-warning"><i class="fas fa-download"></i></div>
        <div>
          <h2 class="h5 mb-1">V<?php echo htmlspecialchars($target['version']); ?> update available</h2>
          <div class="text-muted">You are on <?php echo $current ? 'V' . htmlspecialchars($current) : 'an older version'; ?>.</div>
        </div>
      <?php else: ?>
        <div class="upd-icon bg-success-subtle text-success"><i class="fas fa-check"></i></div>
        <div>
          <h2 class="h5 mb-1">Up to date<?php echo $current ? ' · V' . htmlspecialchars($current) : ''; ?></h2>
          <div class="text-muted">You have the latest version.</div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($target): ?>
      <form method="post" onsubmit="return confirm('Install V<?php echo htmlspecialchars($target['version']); ?> now?');">
        <?php echo csrf_field(); ?>
        <button type="submit" name="run_updates" value="1" class="btn btn-warning"><i class="fas fa-download me-1"></i> Install update</button>
      </form>
    <?php endif; ?>
  </div>

  <?php foreach (array_reverse($pending) as $r): ?>
    <div class="border-top mt-3 pt-3">
      <span class="upd-ver new">V<?php echo htmlspecialchars($r['version']); ?></span>
      <strong class="ms-1"><?php echo htmlspecialchars($r['title']); ?></strong>
      <?php if ($r['notes']): ?><ul class="upd-notes"><?php foreach ($r['notes'] as $n): ?><li><?php echo htmlspecialchars($n); ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($installed): ?>
<div class="card">
  <div class="card-header"><i class="fas fa-clock-rotate-left me-2"></i>Installed versions</div>
  <div class="list-group list-group-flush upd-hist">
    <?php foreach ($installed as $i => $r): ?>
      <div class="list-group-item">
        <div>
          <span class="upd-ver">V<?php echo htmlspecialchars($r['version']); ?></span>
          <span class="fw-semibold ms-1"><?php echo htmlspecialchars($r['title']); ?></span>
          <?php if ($i === 0): ?><span class="badge bg-success ms-1">Current</span><?php endif; ?>
          <?php if ($r['notes']): ?><div class="small text-muted mt-1"><?php echo htmlspecialchars(implode(' · ', $r['notes'])); ?></div><?php endif; ?>
        </div>
        <div class="small text-muted text-nowrap"><?php echo $r['installed_at'] ? htmlspecialchars(date('d M Y', strtotime($r['installed_at']))) : ''; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
