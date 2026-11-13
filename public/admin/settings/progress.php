<?php
// Admin Settings: Student Progress. The day and count numbers behind "Working well", "Slowing down" and "Not working".
// Only the office administrators (role_id 2) may change them. The page works out each student's status live from these numbers.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/StudentProgress.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the Student Progress settings.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/progress.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = sp_installed($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif (!$installed) {
        $f = ['error', 'Run System Updates (migration 103) first.'];
    } elseif (isset($_POST['reset'])) {
        $db->exec('DELETE FROM progress_settings');
        $f = ['success', 'Back to the standard numbers.'];
    } else {
        $r = sp_settings_save($db, $_POST, (int) ($_SESSION['user_id'] ?? 0));
        $f = $r['ok'] ? ['success', 'Saved. Every student\'s status now uses these numbers.'] : ['error', $r['error']];
    }
    $_SESSION['sp_flash'] = $f;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['sp_flash'] ?? null;
unset($_SESSION['sp_flash']);
$s = sp_settings($db);

$page_title = 'Settings · Student Progress';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Student Progress', 'active' => true]];
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash[0] === 'success' ? 'success' : 'danger'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 103) to save changes. Until then the standard numbers are used.</div><?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width:720px">
  <div class="card-body">
    <p class="text-muted">The Student Progress page gives every student one of three words, worked out again each time the page opens. Change the numbers here. The standard numbers are in brackets.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <h6 class="mt-3" style="color:#048C44">Working well</h6>
      <div class="mb-3"><label class="form-label">Handed in work within the last this many days (7)</label>
        <input type="number" name="working_days" min="1" max="365" class="form-control" style="max-width:140px" value="<?php echo (int) $s['working_days']; ?>" required>
        <div class="form-text">And fewer late or missing assignments than the Slowing down number below.</div></div>
      <h6 class="mt-4" style="color:#B45309">Slowing down</h6>
      <div class="mb-3"><label class="form-label">Nothing handed in for this many days or more (7). It stays Slowing down until the Not working days below.</label>
        <input type="text" class="form-control" style="max-width:140px" value="<?php echo (int) $s['working_days']; ?>" disabled>
        <div class="form-text">This is the same number as the Working well days above.</div></div>
      <div class="mb-3"><label class="form-label">This many late or missing assignments, or more (2)</label>
        <input type="number" name="slowing_missing" min="1" max="365" class="form-control" style="max-width:140px" value="<?php echo (int) $s['slowing_missing']; ?>" required></div>
      <h6 class="mt-4" style="color:#B91C1C">Not working</h6>
      <div class="mb-3"><label class="form-label">Nothing handed in for more than this many days (14)</label>
        <input type="number" name="slowing_days" min="1" max="365" class="form-control" style="max-width:140px" value="<?php echo (int) $s['slowing_days']; ?>" required></div>
      <div class="mb-3"><label class="form-label">This many late or missing assignments, or more (3)</label>
        <input type="number" name="notworking_missing" min="1" max="365" class="form-control" style="max-width:140px" value="<?php echo (int) $s['notworking_missing']; ?>" required></div>
      <button class="btn btn-success" <?php echo $installed ? '' : 'disabled'; ?>>Save</button>
      <button class="btn btn-outline-secondary" name="reset" value="1" <?php echo $installed ? '' : 'disabled'; ?> onclick="return confirm('Go back to the standard numbers?');">Use the standard numbers</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
