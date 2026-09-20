<?php
// Run migration 030 — standalone course duration (days + zero months)
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$sqlFile = database_path('migrations/030_course_days_duration.sql');
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_file($sqlFile)) {
    $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', file_get_contents($sqlFile));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
            $messages[] = 'OK: ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 80) . '…';
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                $errors[] = $e->getMessage();
            }
        }
    }
    if (empty($errors)) {
        SessionModel::setFlash('success', 'Course duration migration applied.');
        header('Location: ' . url('admin/courses/index.php'));
        exit;
    }
}

$page_title = 'Course duration migration';
ob_start();
?>
<div class="card"><div class="card-body">
  <h5>Migration 030 — hours / days / weeks / months</h5>
  <p class="text-muted">Adds <code>days_of_study</code> and allows <code>duration_months = 0</code> for short courses.</p>
  <?php foreach ($messages as $m): ?><div class="alert alert-success small"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger small"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
  <form method="post">
    <button type="submit" class="btn btn-primary">Run migration</button>
    <a href="<?= url('admin/courses/index.php') ?>" class="btn btn-link">Back</a>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
