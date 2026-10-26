<?php
require_once __DIR__ . '/_init.php';

$page_title = 'SmS Database Migration';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => url('superadmin/dashboard.php#sms-alerts')],
    ['label' => 'SmS setup', 'active' => true],
];

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $migrationFiles = [
        database_path('migrations/028_sms_module.sql'),
        database_path('migrations/029_sms_must_change_password.sql'),
    ];
    foreach ($migrationFiles as $sqlFile) {
        if (!is_file($sqlFile)) {
            $errors[] = 'Migration file not found: ' . basename($sqlFile);
            continue;
        }
        $sql = file_get_contents($sqlFile);
        $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $sql);
        $buffer = '';
        $statements = [];
        foreach (preg_split('/\R/', $sql) as $line) {
            $trim = trim($line);
            if ($trim === '' || strpos($trim, '--') === 0) {
                continue;
            }
            $buffer .= $line . "\n";
            if (substr(rtrim($line), -1) === ';') {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }

        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
                if (preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?/i', $statement, $m)) {
                    $messages[] = 'Created/verified table: ' . $m[1];
                } elseif (stripos($statement, 'ALTER TABLE') !== false) {
                    $messages[] = 'Applied: ' . basename($sqlFile);
                }
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') === false) {
                    $errors[] = basename($sqlFile) . ': ' . $e->getMessage();
                }
            }
        }
    }

    if (empty($errors) && SmsModuleService::isInstalled($pdo)) {
        $sms->log('migration', 'SmS migrations applied', 'system', null);
        sms_flash('success', 'SmS database setup completed.');
        sms_redirect('superadmin/dashboard.php#sms-alerts');
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
  <p class="text-muted">Runs migrations <code>028</code> (SmS tables) and <code>029</code> (first-login password flag).</p>

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
    <a href="<?= url('superadmin/dashboard.php#sms-alerts') ?>" class="btn btn-link">Back to dashboard</a>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
