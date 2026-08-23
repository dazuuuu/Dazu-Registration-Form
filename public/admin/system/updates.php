<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/services/MigrationService.php';
require_once __DIR__ . '/../../../app/v1/services/UpdatePackageService.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$migrationService = new MigrationService($pdo);
$updateService = new UpdatePackageService($pdo);
$errors = [];
$runResults = [];
$updateResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed. Please try again.';
        redirect(url('admin/system/updates.php'));
    }

    if (isset($_POST['run_updates'])) {
        $result = $migrationService->applyPending();
        $runResults = $result['results'];

        if (!empty($result['failed'])) {
            $_SESSION['flash']['error'] = 'System update stopped at ' . $result['failed'][0]['filename'] . ': ' . $result['failed'][0]['error'];
        } elseif ($result['ran'] > 0) {
            $_SESSION['flash']['success'] = 'System update complete. Applied ' . $result['ran'] . ' migration' . ($result['ran'] === 1 ? '' : 's') . '.';
        } else {
            $_SESSION['flash']['success'] = 'Required migrations are already up to date.';
        }

        $_SESSION['migration_run_results'] = $runResults;
        redirect(url('admin/system/updates.php'));
    }

    if (isset($_POST['run_feature_updates'])) {
        $result = $updateService->applyPending();
        $updateResults = $result['results'];

        if (!empty($result['failed'])) {
            $_SESSION['flash']['error'] = 'Feature update stopped at ' . $result['failed'][0]['filename'] . ': ' . $result['failed'][0]['error'];
        } elseif ($result['ran'] > 0) {
            $_SESSION['flash']['success'] = 'Feature update complete. Applied ' . $result['ran'] . ' package' . ($result['ran'] === 1 ? '' : 's') . '.';
        } else {
            $_SESSION['flash']['success'] = 'No pending feature updates.';
        }

        $_SESSION['feature_update_results'] = $updateResults;
        redirect(url('admin/system/updates.php'));
    }

    if (isset($_POST['revert_update'])) {
        $filename = basename((string) ($_POST['revert_update'] ?? ''));
        $result = $updateService->revert($filename);
        if ($result['ok']) {
            $_SESSION['flash']['success'] = 'Reverted update: ' . $filename;
        } else {
            $_SESSION['flash']['error'] = $result['error'] ?? 'Could not revert update.';
        }
        redirect(url('admin/system/updates.php'));
    }
}

$lastResults = $_SESSION['migration_run_results'] ?? [];
unset($_SESSION['migration_run_results']);
$lastFeatureResults = $_SESSION['feature_update_results'] ?? [];
unset($_SESSION['feature_update_results']);

try {
    $pending = $migrationService->pending();
    $pendingUpdates = $updateService->pending();
    $appliedUpdates = $updateService->appliedActive();
} catch (Throwable $e) {
    $pending = [];
    $pendingUpdates = [];
    $appliedUpdates = [];
    $errors[] = $e->getMessage();
}

$page_title = 'System Updates';
$breadcrumbs = [
    ['label' => 'System Updates', 'active' => true],
];

ob_start();
?>
<style>
.update-hero{border:1px solid #e4e7ec;border-radius:8px;background:#fff;padding:22px;margin-bottom:20px}
.update-status-icon{width:48px;height:48px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:20px}
.migration-list .list-group-item{display:flex;align-items:center;justify-content:space-between;gap:12px}
.migration-code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:.86rem}
</style>

<div class="update-hero">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if ($pending || $pendingUpdates): ?>
        <div class="update-status-icon bg-warning-subtle text-warning"><i class="fas fa-download"></i></div>
        <div>
          <h2 class="h5 mb-1">System update available</h2>
          <div class="text-muted">
            <?php if ($pending): ?><?php echo number_format(count($pending)); ?> migration<?php echo count($pending) === 1 ? '' : 's'; ?><?php endif; ?>
            <?php if ($pending && $pendingUpdates): ?> · <?php endif; ?>
            <?php if ($pendingUpdates): ?><?php echo number_format(count($pendingUpdates)); ?> feature update<?php echo count($pendingUpdates) === 1 ? '' : 's'; ?><?php endif; ?>
            ready to run.
          </div>
        </div>
      <?php else: ?>
        <div class="update-status-icon bg-success-subtle text-success"><i class="fas fa-check"></i></div>
        <div>
          <h2 class="h5 mb-1">System is up to date</h2>
          <div class="text-muted">No pending migrations or feature updates were found.</div>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($pending): ?>
        <form method="post" onsubmit="return confirm('Run pending system migrations now?');">
          <?php echo csrf_field(); ?>
          <button type="submit" name="run_updates" value="1" class="btn btn-warning">
            <i class="fas fa-database me-1"></i> Run migrations
          </button>
        </form>
      <?php endif; ?>
      <?php if ($pendingUpdates): ?>
        <form method="post" onsubmit="return confirm('Apply pending feature updates now?');">
          <?php echo csrf_field(); ?>
          <button type="submit" name="run_feature_updates" value="1" class="btn btn-dark">
            <i class="fas fa-box-open me-1"></i> Run feature updates
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($lastResults): ?>
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Last migration run</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Migration</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($lastResults as $result): ?>
              <tr>
                <td class="migration-code"><?php echo htmlspecialchars($result['filename']); ?></td>
                <td><span class="badge text-bg-<?php echo $result['status'] === 'failed' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($result['status']); ?></span></td>
                <td class="small text-muted"><?php echo !empty($result['error']) ? htmlspecialchars($result['error']) : 'Applied cleanly.'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($lastFeatureResults): ?>
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Last feature update run</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Update</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($lastFeatureResults as $result): ?>
              <tr>
                <td class="migration-code"><?php echo htmlspecialchars($result['filename']); ?></td>
                <td><span class="badge text-bg-<?php echo $result['status'] === 'failed' ? 'danger' : 'success'; ?>"><?php echo htmlspecialchars($result['status']); ?></span></td>
                <td class="small text-muted"><?php echo !empty($result['error']) ? htmlspecialchars($result['error']) : 'Applied cleanly.'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-box-open me-2"></i>Feature updates</span>
    <span class="badge bg-secondary">databases/updates/</span>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">Upload optional update packages to <code>databases/updates/</code>. Each update needs an apply script (<code>.sql</code>) and may include a paired revert script (<code>.revert.sql</code>). Run updates here to test new features without changing core migrations — revert if needed.</p>

    <?php if ($appliedUpdates): ?>
      <h6 class="text-uppercase text-muted small">Applied</h6>
      <div class="list-group mb-3">
        <?php foreach ($appliedUpdates as $update): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <span class="migration-code"><?php echo htmlspecialchars($update['filename']); ?></span>
              <span class="badge bg-success ms-2">Active</span>
              <div class="small text-muted">Applied <?php echo htmlspecialchars($update['applied_at']); ?></div>
            </div>
            <?php if (!empty($update['revert_path'])): ?>
              <form method="post" onsubmit="return confirm('Revert this update? Database columns added by it will be removed.');">
                <?php echo csrf_field(); ?>
                <button type="submit" name="revert_update" value="<?php echo htmlspecialchars($update['filename']); ?>" class="btn btn-sm btn-outline-danger">
                  <i class="fas fa-undo me-1"></i> Revert
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($pendingUpdates): ?>
      <h6 class="text-uppercase text-muted small">Pending</h6>
      <div class="list-group list-group-flush migration-list">
        <?php foreach ($pendingUpdates as $update): ?>
          <div class="list-group-item">
            <span class="migration-code"><?php echo htmlspecialchars($update['filename']); ?></span>
            <span class="badge bg-warning text-dark">Pending</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$appliedUpdates): ?>
      <p class="text-muted mb-0 small">No feature updates in the queue.</p>
    <?php endif; ?>
  </div>
</div>

<?php if ($pending): ?>
  <div class="card">
    <div class="card-header"><i class="fas fa-list me-2"></i>Pending migrations</div>
    <div class="list-group list-group-flush migration-list">
      <?php foreach ($pending as $migration): ?>
        <div class="list-group-item">
          <span class="migration-code"><?php echo htmlspecialchars($migration['filename']); ?></span>
          <span class="badge bg-warning text-dark">Pending</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
