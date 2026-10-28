<?php
// Admin Settings: Letter of recommendation. The footer (Email, Website, LinkedIn) printed on new letters; a final letter keeps the footer
// it was made with. The signature and the stamp come from Director signature and Transcript settings; a final letter cannot be made
// while either is missing. Only the office administrators (role_id ≤ 2) may change it; every change goes to the audit log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/StudentLetterService.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the letter settings.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/letter.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = sl_installed($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif (!$installed) {
        $f = ['error', 'Run System Updates (migration 095) first.'];
    } else {
        $r = sl_settings_save($db, $_POST, (int) ($_SESSION['user_id'] ?? 0));
        $f = $r['ok'] ? ['success', 'Footer saved. Letters made from now on use it.'] : ['error', $r['error']];
    }
    $_SESSION['sls_flash'] = $f;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['sls_flash'] ?? null;
unset($_SESSION['sls_flash']);
$s = sl_settings($db);
$missing = $installed ? sl_settings_missing($db) : [];
$page_title = 'Settings · Letter of recommendation';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Letter of recommendation', 'active' => true]];
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash[0] === 'success' ? 'success' : 'danger'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 095) to use this page.</div><?php endif; ?>
<?php if ($missing): ?><div class="alert alert-warning">No final letter can be made until <?php echo $e(implode(' and ', $missing)); ?> <?php echo count($missing) > 1 ? 'are' : 'is'; ?> added.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3 p-md-4">
      <h6 class="fw-bold">Footer</h6>
      <p class="small text-muted">Printed at the bottom of every new letter. A letter already made keeps the footer it was made with.</p>
      <form method="POST" class="row g-2"><?php echo csrf_field(); ?>
        <div class="col-12"><label class="form-label small fw-semibold">Email</label><input name="email" type="email" class="form-control form-control-sm" maxlength="160" required value="<?php echo $e($s['email']); ?>"></div>
        <div class="col-12"><label class="form-label small fw-semibold">Website</label><input name="website" class="form-control form-control-sm" maxlength="160" required value="<?php echo $e($s['website']); ?>"></div>
        <div class="col-12"><label class="form-label small fw-semibold">LinkedIn</label><input name="linkedin" class="form-control form-control-sm" maxlength="200" required value="<?php echo $e($s['linkedin']); ?>"></div>
        <div class="col-12"><button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>>Save footer</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card p-3 p-md-4">
      <h6 class="fw-bold">Signature and stamp</h6>
      <p class="small text-muted mb-2">Added by the system to every final letter.</p>
      <div class="small mb-1"><i class="fas fa-<?php echo in_array('the director signature', $missing, true) ? 'xmark text-danger' : 'check text-success'; ?> me-1"></i>Director signature · <a href="<?php echo url('admin/settings/director_signature.php'); ?>">Open</a></div>
      <div class="small"><i class="fas fa-<?php echo in_array('the official stamp', $missing, true) ? 'xmark text-danger' : 'check text-success'; ?> me-1"></i>Official stamp · <a href="<?php echo url('admin/settings/transcript.php'); ?>">Open</a></div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
