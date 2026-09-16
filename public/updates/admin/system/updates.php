<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/services/MigrationService.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$migrationService = new MigrationService($pdo);
$errors = [];
$runResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_updates'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed. Please try again.';
        redirect(url('admin/system/updates.php'));
    }

    $result = $migrationService->applyPending();
    $runResults = $result['results'];

    if (!empty($result['failed'])) {
        $_SESSION['flash']['error'] = 'System update stopped at ' . $result['failed'][0]['filename'] . ': ' . $result['failed'][0]['error'];
    } elseif ($result['ran'] > 0) {
        $_SESSION['flash']['success'] = 'System update complete. Applied ' . $result['ran'] . ' migration' . ($result['ran'] === 1 ? '' : 's') . '.';
    } else {
        $_SESSION['flash']['success'] = 'System is already up to date.';
    }

    $_SESSION['migration_run_results'] = $runResults;
    redirect(url('admin/system/updates.php'));
}

$lastResults = $_SESSION['migration_run_results'] ?? [];
unset($_SESSION['migration_run_results']);

try {
    $pending = $migrationService->pending();
    $migrationRows = $migrationService->statusRows();
} catch (Throwable $e) {
    $pending = [];
    $migrationRows = [];
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
.update-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.update-meta-card{background:#fff;border:1px solid #e4e7ec;border-radius:8px;padding:14px}
.update-meta-card small{display:block;color:#667085;font-weight:600;text-transform:uppercase;font-size:.68rem;letter-spacing:.06em;margin-bottom:4px}
.update-meta-card strong{font-size:1.1rem}
</style>

<div class="update-hero">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if ($pending): ?>
        <div class="update-status-icon bg-warning-subtle text-warning"><i class="fas fa-download"></i></div>
        <div>
          <h2 class="h5 mb-1">System update available</h2>
          <div class="text-muted"><?php echo number_format(count($pending)); ?> migration<?php echo count($pending) === 1 ? '' : 's'; ?> ready to run.</div>
        </div>
      <?php else: ?>
        <div class="update-status-icon bg-success-subtle text-success"><i class="fas fa-check"></i></div>
        <div>
          <h2 class="h5 mb-1">System is up to date</h2>
          <div class="text-muted">No pending database migrations were found.</div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($pending): ?>
      <form method="post" onsubmit="return confirm('Run pending system migrations now?');">
        <?php echo csrf_field(); ?>
        <button type="submit" name="run_updates" value="1" class="btn btn-warning">
          <i class="fas fa-database me-1"></i> Run update
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="update-meta">
  <div class="update-meta-card">
    <small>Migration model</small>
    <strong>MigrationService</strong>
    <div class="text-muted small">Reads required SQL files and tracks them in <code>schema_migrations</code>.</div>
  </div>
  <div class="update-meta-card">
    <small>Total files</small>
    <strong><?php echo number_format(count($migrationRows)); ?></strong>
    <div class="text-muted small"><code>databases/required_migrations</code></div>
  </div>
  <div class="update-meta-card">
    <small>Pending</small>
    <strong class="<?php echo $pending ? 'text-warning' : 'text-success'; ?>"><?php echo number_format(count($pending)); ?></strong>
    <div class="text-muted small">Files not yet recorded as applied.</div>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $error): ?><div><?php echo htmlspecialchars($error); ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($lastResults): ?>
  <div class="card">
    <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Last update run</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Migration</th><th>Status</th><th>Notes</th></tr></thead>
          <tbody>
            <?php foreach ($lastResults as $result): ?>
              <tr>
                <td class="migration-code"><?php echo htmlspecialchars($result['filename']); ?></td>
                <td>
                  <span class="badge text-bg-<?php echo $result['status'] === 'failed' ? 'danger' : 'success'; ?>">
                    <?php echo htmlspecialchars($result['status']); ?>
                  </span>
                </td>
                <td class="small text-muted">
                  <?php if (!empty($result['error'])): ?>
                    <?php echo htmlspecialchars($result['error']); ?>
                  <?php elseif (!empty($result['skipped'])): ?>
                    <?php echo count($result['skipped']); ?> already-applied statement<?php echo count($result['skipped']) === 1 ? '' : 's'; ?> skipped.
                  <?php else: ?>
                    Applied cleanly.
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

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

<div class="card">
  <div class="card-header"><i class="fas fa-database me-2"></i>Migration files</div>
  <div class="card-body p-0">
    <?php if (!$migrationRows): ?>
      <div class="p-4 text-muted">No migration files were found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead>
            <tr>
              <th>Migration</th>
              <th>Status</th>
              <th>Applied at</th>
              <th>File updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($migrationRows as $row): ?>
              <tr>
                <td class="migration-code"><?php echo htmlspecialchars($row['filename']); ?></td>
                <td>
                  <?php if ($row['status'] === 'applied'): ?>
                    <span class="badge text-bg-success">Applied</span>
                  <?php else: ?>
                    <span class="badge text-bg-warning">Pending</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <?php echo $row['applied_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($row['applied_at']))) : '—'; ?>
                </td>
                <td class="small text-muted">
                  <?php echo $row['modified_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($row['modified_at']))) : '—'; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
