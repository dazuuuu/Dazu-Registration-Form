<?php
require_once __DIR__ . '/_init.php';

$page_title = 'SmS Database Migration';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/sms/index.php')],
    ['label' => 'Migration', 'active' => true],
];

$messages = [];
$errors = [];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $sqlFile = dirname(__DIR__, 3) . '/databases/migrations/028_sms_module.sql';
    if (!is_file($sqlFile)) {
        $errors[] = 'Migration file not found: databases/migrations/028_sms_module.sql';
    } else {
        $sql = file_get_contents($sqlFile);
        $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $sql);
        $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));

        foreach ($statements as $statement) {
            if ($statement === '' || strpos(ltrim($statement), '--') === 0) {
                continue;
            }
            try {
                $pdo->exec($statement);
                if (preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?/i', $statement, $m)) {
                    $messages[] = 'Created/verified table: ' . $m[1];
                }
            } catch (PDOException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $ran = empty($errors);
        if ($ran) {
            $sms->log('migration', 'SmS tables migration 028 applied', 'system', null);
        }
    }
}

$tables = ['sms_students', 'sms_receipts', 'sms_student_payments', 'sms_prospects', 'sms_projects',
    'sms_project_meetings', 'sms_project_followups', 'sms_sales', 'sms_activity', 'sms_receipt_counter'];
$existing = [];
foreach ($tables as $t) {
    try {
        $pdo->query('SELECT 1 FROM ' . $t . ' LIMIT 1');
        $existing[] = $t;
    } catch (Throwable $e) {
        // table missing
    }
}

ob_start();
?>
<div class="card shadow-sm"><div class="card-body">
  <h4 class="mb-3">SmS module database setup</h4>
  <p class="text-muted">Run migration <code>028_sms_module.sql</code> to create all SmS tables. Safe to run more than once (uses <code>CREATE TABLE IF NOT EXISTS</code>).</p>

  <h6 class="mt-4">Table status</h6>
  <ul class="list-group mb-4">
    <?php foreach ($tables as $t): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
      <?= htmlspecialchars($t) ?>
      <?php if (in_array($t, $existing, true)): ?>
        <span class="badge bg-success">Exists</span>
      <?php else: ?>
        <span class="badge bg-warning text-dark">Missing</span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($messages): ?>
    <div class="alert alert-success">
      <?php foreach ($messages as $m): ?><div><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <button type="submit" name="run_migration" value="1" class="btn btn-success" onclick="return confirm('Run SmS migration now?')">
      <i class="fas fa-database me-1"></i> Run migration
    </button>
    <a href="<?= url('admin/sms/index.php') ?>" class="btn btn-link">Back to dashboard</a>
  </form>

  <hr>
  <p class="small text-muted mb-0">Alternative: import <code>databases/migrations/028_sms_module.sql</code> via phpMyAdmin.</p>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
