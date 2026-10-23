<?php
// Admin Settings: Director signature. Uploaded once; used automatically on the back of every tutor ID card made from
// now on and on new certificates (each certificate keeps the version it was issued with). Every version is kept and
// an older one can be made active again. Also the editable wording on the card back (phone, websites, branches).
// Only admins (role_id ≤ 2) may change it; every change goes to the audit log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the director signature.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/director_signature.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = (function () use ($db) { try { $db->query('SELECT 1 FROM director_signatures LIMIT 1'); return true; } catch (Throwable $e) { return false; } })();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = ['error', 'Run System Updates (migration 081) first.'];
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif ($installed) {
        switch ($_POST['action'] ?? '') {
            case 'upload':
                $cur = dsig_active($db);
                $r = dsig_upload($db, $_FILES['signature'] ?? [], (string) ($_POST['name'] ?? ($cur['name'] ?? '')), (string) ($_POST['title'] ?? ($cur['title'] ?? '')));
                $f = !$r['ok'] ? ['error', $r['error']] : [$r['check']['status'] === 'ok' ? 'success' : 'warning',
                    'Version ' . $r['version'] . ' saved and active.' . ($r['check']['status'] === 'ok' ? ' It looks good.' : ' It could be better: ' . implode(' ', $r['check']['notes']))];
                break;
            case 'names':
                $r = dsig_save_names($db, (string) ($_POST['name'] ?? ''), (string) ($_POST['title'] ?? ''));
                $f = $r['ok'] ? ['success', 'Name and title saved.'] : ['error', $r['error']];
                break;
            case 'activate':
                $r = dsig_activate($db, (int) ($_POST['id'] ?? 0));
                $f = $r['ok'] ? ['success', 'That version is active again. New IDs and certificates use it.'] : ['error', $r['error']];
                break;
            case 'card':
                idset_save($db, $_POST);
                $f = ['success', 'ID card details saved.'];
                break;
        }
    }
    $_SESSION['tid_flash'] = $f;
    header('Location: ' . $self);
    exit;
}

$flash = $_SESSION['tid_flash'] ?? null;
unset($_SESSION['tid_flash']);
$versions = $installed ? dsig_versions($db) : [];
$active = null;
foreach ($versions as $v) if ($v['is_active']) $active = $v;
$sigUri = $active ? sig_data_uri($active['file_path']) : null;
$s = idset_all($db);
$page_title = 'Settings · Director signature';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Director signature', 'active' => true]];
ob_start();
?>
<style>
.ds-box{height:110px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid #e3e6e0}
.ds-box img{max-width:85%;max-height:90px}
.ds-preview canvas{width:100%;max-width:240px;display:block;margin:0 auto;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.12)}
</style>
<?php if ($flash): ?><div class="alert alert-<?php echo ['success' => 'success', 'warning' => 'warning', 'error' => 'danger'][$flash[0]] ?? 'info'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 081) to use this page.</div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card p-3 p-md-4 mb-3">
      <div class="d-flex justify-content-between align-items-start mb-1"><h6 class="fw-bold mb-0">Director signature</h6>
        <?php if ($active): ?><span class="badge rounded-pill bg-success">Active · version <?php echo (int) $active['version']; ?></span><?php else: ?><span class="badge rounded-pill bg-secondary">Not set</span><?php endif; ?></div>
      <p class="small text-muted">Upload the director's signature once. The system uses it by itself on the back of every tutor ID and on certificates. Nobody has to add it again.</p>
      <div class="row g-2 mb-3">
        <div class="col-6"><div class="small text-muted mb-1">On white</div><div class="ds-box bg-white"><?php echo $sigUri ? '<img src="' . $sigUri . '" alt="Director signature">' : '<span class="small text-muted">No signature yet</span>'; ?></div></div>
        <div class="col-6"><div class="small text-muted mb-1">On dark</div><div class="ds-box" style="background:#373435"><?php echo $sigUri ? '<img src="' . $sigUri . '" alt="" style="filter:invert(1)">' : '<span class="small" style="color:#bbb">No signature yet</span>'; ?></div></div>
      </div>
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center"><?php echo csrf_field(); ?><input type="hidden" name="action" value="upload">
        <input type="file" name="signature" class="form-control form-control-sm" style="max-width:280px" accept="image/png,image/jpeg" required>
        <button class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i>Upload new signature</button>
      </form>
      <div class="form-text">PNG or JPG, up to 5 MB. Sign in dark ink on plain white paper. The background is removed automatically. The old version is kept in history.</div>
      <?php if ($active): $notes = array_filter(explode("\n", (string) $active['check_notes'])); ?>
        <div class="rounded p-2 mt-3 small" style="background:<?php echo $notes ? '#fff7e0' : '#e8f5e1'; ?>">
          <?php if (!$notes): ?><div><i class="fas fa-check text-success me-1"></i>Dark and clear</div><div><i class="fas fa-check text-success me-1"></i>Background removed</div><div><i class="fas fa-check text-success me-1"></i>Not cut off</div>
          <?php else: foreach ($notes as $n): ?><div><i class="fas fa-triangle-exclamation text-warning me-1"></i><?php echo $e($n); ?></div><?php endforeach; ?><div class="text-muted mt-1">You can keep it, or upload a clearer one.</div><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card p-3 p-md-4 mb-3">
      <h6 class="fw-bold">Name and title printed under the signature</h6>
      <form method="POST" class="row g-2 align-items-end"><?php echo csrf_field(); ?><input type="hidden" name="action" value="names">
        <div class="col-md-6"><label class="form-label small fw-semibold">Name</label><input name="name" class="form-control form-control-sm" maxlength="150" required value="<?php echo $e($active['name'] ?? 'Mwangi Ngigi'); ?>"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold">Title</label><input name="title" class="form-control form-control-sm" maxlength="80" required value="<?php echo $e($active['title'] ?? 'CEO & Founder'); ?>"></div>
        <div class="col-12"><button class="btn btn-sm btn-success" <?php echo $active ? '' : 'disabled title="Upload the signature first"'; ?>>Save</button></div>
      </form>
    </div>

    <div class="card p-3 p-md-4">
      <h6 class="fw-bold">ID card details</h6>
      <p class="small text-muted">The wording on the back of tutor ID cards, and the verification page. Changes apply to cards made from now on.</p>
      <form method="POST" class="row g-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="card">
        <?php foreach (['property_line' => 'Top line', 'org_name' => 'Organisation', 'contact_line' => 'Contact heading', 'phone' => 'Phone', 'email' => 'Email', 'website_1' => 'Website 1', 'website_2' => 'Website 2', 'locations_line' => 'Locations heading',
                        'branch_1_name' => 'Branch 1 name', 'branch_1_address' => 'Branch 1 address', 'branch_2_name' => 'Branch 2 name', 'branch_2_address' => 'Branch 2 address', 'verify_base' => 'Verification site (QR address)'] as $k => $label): ?>
          <div class="col-md-6"><label class="form-label small fw-semibold mb-1"><?php echo $e($label); ?></label><input name="<?php echo $k; ?>" class="form-control form-control-sm" maxlength="160" value="<?php echo $e($s[$k]); ?>"></div>
        <?php endforeach; ?>
        <div class="col-12 form-text mt-0">Changing the verification site changes the QR on new cards only. Cards already printed keep their QR, so keep the old site working.</div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="show_photo_on_verify" value="1" id="svp" <?php echo $s['show_photo_on_verify'] === '1' ? 'checked' : ''; ?>><label class="form-check-label small" for="svp">Show photo on verification page</label></div></div>
        <div class="col-12"><button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>>Save ID card details</button></div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card p-3 p-md-4 mb-3">
      <h6 class="fw-bold">Where it shows</h6>
      <?php foreach (['Back of every tutor ID', 'Tutor certificates', 'Student certificates'] as $w): ?>
        <div class="d-flex justify-content-between border-bottom py-2 small"><span><?php echo $w; ?></span><span class="badge rounded-pill bg-success-subtle text-success">Automatic</span></div>
      <?php endforeach; ?>
      <div class="small text-muted mt-2">A new signature is used for IDs and certificates made from now on. Certificates already issued keep the signature they were issued with.</div>
    </div>
    <div class="card p-3 p-md-4 mb-3 ds-preview">
      <h6 class="fw-bold">Preview on the ID back</h6>
      <canvas data-pdf="<?php echo $e(url('id_cards/tutor_preview.php?doc=sample') . '&v=' . md5(($active['id'] ?? '') . ($active['name'] ?? '') . json_encode($s))); ?>" data-page="2"></canvas>
    </div>
    <div class="card p-3 p-md-4">
      <h6 class="fw-bold">History</h6>
      <?php if (!$versions): ?><div class="small text-muted">No versions yet.</div><?php endif; ?>
      <?php foreach ($versions as $v): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2 small gap-2">
          <span>Version <?php echo (int) $v['version']; ?> uploaded by <?php echo $e($v['username'] ?? 'the office'); ?><?php echo $v['is_active'] ? ' <span class="badge bg-success">Active</span>' : ''; ?></span>
          <span class="d-flex align-items-center gap-2 text-muted text-nowrap"><?php echo $e(date('j M Y', strtotime($v['created_at']))); ?>
            <?php if (!$v['is_active']): ?><form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>"><button class="btn btn-sm btn-outline-secondary py-0">Use again</button></form><?php endif; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script src="<?php echo $e(url('assets/vendor/pdfjs/pdf.min.js')); ?>"></script>
<script>
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = <?php echo json_encode(url('assets/vendor/pdfjs/pdf.worker.min.js')); ?>;
    document.querySelectorAll('canvas[data-pdf]').forEach(function (cv) {
        pdfjsLib.getDocument({ url: cv.dataset.pdf, withCredentials: true }).promise.then(function (pdf) { return pdf.getPage(+cv.dataset.page); }).then(function (page) {
            var v = page.getViewport({ scale: 1000 / page.getViewport({ scale: 1 }).width }); cv.width = v.width; cv.height = v.height;
            return page.render({ canvasContext: cv.getContext('2d'), viewport: v }).promise;
        });
    });
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
