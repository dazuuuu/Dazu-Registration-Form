<?php
// Admin Settings: Transcript. The official stamp (uploaded once), the transcript logo and the faded logo watermark (Light, Medium, Strong).
// The director's signature is the one on the Director signature page (the same one used on tutor IDs).
// A transcript cannot be released while the stamp or the signature is missing. Each released transcript keeps the stamp, signature,
// logo and watermark it was released with, so a change here only affects transcripts released later.
// Only the office administrators (role_id ≤ 2) may change it; every change goes to the audit log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TranscriptService.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the transcript settings.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/transcript.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = tr_installed($db);
$me = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = ['error', 'Run System Updates (migration 094) first.'];
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif ($installed) {
        switch ($_POST['action'] ?? '') {
            case 'stamp':
            case 'logo':
                $kind = $_POST['action'];
                $r = tr_store_image($_FILES['image'] ?? [], $kind);
                if ($r['ok']) {
                    tr_setting_save($db, $kind . '_path', $r['path'], $me);
                    $f = ['success', ($kind === 'stamp' ? 'Stamp' : 'Logo') . ' saved. Transcripts released from now on use it.'];
                } else {
                    $f = ['error', $r['error']];
                }
                break;
            case 'logo_default':
                tr_setting_save($db, 'logo_path', '', $me);
                $f = ['success', 'Transcripts released from now on use the site logo.'];
                break;
            case 'watermark':
                $level = (string) ($_POST['watermark_level'] ?? 'light');
                tr_setting_save($db, 'watermark_on', !empty($_POST['watermark_on']) ? '1' : '0', $me);
                tr_setting_save($db, 'watermark_level', isset(TR_WATERMARK[$level]) ? $level : 'light', $me);
                $f = ['success', 'Watermark saved.'];
                break;
        }
    }
    $_SESSION['trs_flash'] = $f;
    header('Location: ' . $self);
    exit;
}

$flash = $_SESSION['trs_flash'] ?? null;
unset($_SESSION['trs_flash']);
$s = $installed ? tr_settings($db) : ['stamp_path' => '', 'logo_path' => '', 'watermark_on' => '0', 'watermark_level' => 'light'];
$img = function (?string $abs) { return $abs ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($abs)) : null; };
$stampUri = $img(tr_abs($s['stamp_path']));
$ownLogo = tr_abs($s['logo_path']);
$logoUri = $installed ? $img(tr_current_logo_file($db)) : null;
$sig = dsig_active($db);
$page_title = 'Settings · Transcript';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Transcript', 'active' => true]];
ob_start();
?>
<style>.trs-box{height:130px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid #e3e6e0;background:#fff}.trs-box img{max-width:85%;max-height:115px}</style>
<?php if ($flash): ?><div class="alert alert-<?php echo ['success' => 'success', 'error' => 'danger'][$flash[0]] ?? 'info'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 094) to use this page.</div><?php endif; ?>
<?php if (!$stampUri || !$sig): ?><div class="alert alert-warning">No transcript can be released until <?php echo $e(implode(' and ', array_filter([!$stampUri ? 'the official stamp' : null, !$sig ? 'the director signature' : null]))); ?> <?php echo (!$stampUri && !$sig) ? 'are' : 'is'; ?> added.</div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3 p-md-4 h-100">
      <div class="d-flex justify-content-between"><h6 class="fw-bold">Official stamp</h6><span class="badge rounded-pill <?php echo $stampUri ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $stampUri ? 'Set' : 'Not set'; ?></span></div>
      <p class="small text-muted">Upload the stamp once. It is printed by itself on every transcript when it is released.</p>
      <div class="trs-box mb-3"><?php echo $stampUri ? '<img src="' . $stampUri . '" alt="Official stamp">' : '<span class="small text-muted">No stamp yet</span>'; ?></div>
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap"><?php echo csrf_field(); ?><input type="hidden" name="action" value="stamp">
        <input type="file" name="image" class="form-control form-control-sm" style="max-width:280px" accept="image/png,image/jpeg" required>
        <button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>><i class="fas fa-upload me-1"></i>Upload stamp</button>
      </form>
      <div class="form-text">PNG with a transparent background looks best. Up to 5 MB.</div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 p-md-4 h-100">
      <div class="d-flex justify-content-between"><h6 class="fw-bold">Director signature</h6><span class="badge rounded-pill <?php echo $sig ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $sig ? 'Set' : 'Not set'; ?></span></div>
      <p class="small text-muted">Transcripts use the director signature you already keep for tutor IDs<?php echo $sig ? ' (' . $e($sig['name']) . ')' : ''; ?>.</p>
      <div class="trs-box mb-3"><?php echo $sig ? '<img src="' . $img($sig['file']) . '" alt="Director signature">' : '<span class="small text-muted">No signature yet</span>'; ?></div>
      <a class="btn btn-sm btn-outline-success" href="<?php echo url('admin/settings/director_signature.php'); ?>">Open Director signature</a>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 p-md-4 h-100">
      <h6 class="fw-bold">Transcript logo</h6>
      <p class="small text-muted"><?php echo $ownLogo ? 'Your own transcript logo is used.' : 'The site logo is used until you upload a transcript logo.'; ?> Each released transcript keeps the logo it was released with.</p>
      <div class="trs-box mb-3"><?php echo $logoUri ? '<img src="' . $logoUri . '" alt="Logo">' : '<span class="small text-muted">No logo</span>'; ?></div>
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap"><?php echo csrf_field(); ?><input type="hidden" name="action" value="logo">
        <input type="file" name="image" class="form-control form-control-sm" style="max-width:280px" accept="image/png,image/jpeg" required>
        <button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>><i class="fas fa-upload me-1"></i>Upload logo</button>
      </form>
      <?php if ($ownLogo): ?><form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="logo_default"><button class="btn btn-sm btn-outline-secondary">Use the site logo instead</button></form><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 p-md-4 h-100">
      <h6 class="fw-bold">Watermark</h6>
      <p class="small text-muted">A faded copy of the transcript logo behind the table.</p>
      <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="watermark">
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="watermark_on" value="1" id="trwm" <?php echo $s['watermark_on'] === '1' ? 'checked' : ''; ?>><label class="form-check-label" for="trwm">Show the watermark</label></div>
        <label class="form-label small fw-semibold">Strength</label>
        <select name="watermark_level" class="form-select form-select-sm mb-3" style="max-width:200px">
          <?php foreach (TR_WATERMARK as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo $s['watermark_level'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>>Save watermark</button>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
