<?php
// Admin Settings: Certificate. The default wording printed under the name (≤ 200 characters, fields {course} {duration}
// {student_name}) and the NITA Accreditation (logo, number, where and how it shows). Live preview of the real certificate;
// nothing is kept until Save. Saved settings apply to certificates issued after the save; an issued certificate changes only
// when it is reissued (Certificates list). Only the office administrators (role_id ≤ 2); every save is logged.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_v2.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the certificate.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/certificate.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$me = (int) ($_SESSION['user_id'] ?? 0);
$set = cv2_settings($db);

$sample = ['snapshot_name' => 'Wesley Ezekiel Omondi', 'snapshot_course' => 'Social Media Management', 'snapshot_duration_months' => 1,
           'certificate_no' => 'DDS-2026-0003', 'qr_code_hash' => 'sample', 'tutor_name' => 'Course Tutor', 'issued_at' => date('Y-m-d'), 'taught_skills' => ''];

/** The NITA settings from the form, over the saved ones. */
$nitaFromPost = function () use ($set): array {
    return cv2_nita_clean(['logo' => !empty($_POST['nita_remove_logo']) ? '' : $set['nita']['logo'], 'number' => $_POST['nita_number'] ?? '', 'position' => $_POST['nita_position'] ?? '',
                           'style' => $_POST['nita_style'] ?? '', 'size' => $_POST['nita_size'] ?? '', 'color' => $_POST['nita_color'] ?? '']);
};
/** A NITA logo chosen in the form (PNG or JPG), or null. */
$upload = function (): ?array {
    $f = $_FILES['nita_logo'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return null;
    return in_array(@getimagesize($f['tmp_name'])['mime'] ?? '', ['image/png', 'image/jpeg'], true) ? $f : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if (($_POST['action'] ?? '') === 'preview') { http_response_code(403); exit('Refresh the page.'); }
        $_SESSION['cset_flash'] = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'preview') {
        header('Content-Type: text/html; charset=UTF-8');
        $d = cv2_live($db, $sample, ['wording' => (string) ($_POST['wording'] ?? ''), 'extra' => '', 'nita' => $nitaFromPost()]);
        if ($f = $upload()) $d['nita_logo'] = $f['tmp_name'];   // the logo just chosen, not saved yet
        echo cv2_html($d);
        exit;
    } else {
        $nita = $nitaFromPost();
        $err = null;
        if (!empty($_FILES['nita_logo']['name'])) {
            $r = tr_store_image($_FILES['nita_logo'], 'nita');
            if ($r['ok']) $nita['logo'] = $r['path']; else $err = $r['error'];
        }
        $w = cv2_clean_wording((string) ($_POST['wording'] ?? ''));
        cv2_settings_save($db, $w !== '' ? $w : CV2_DEFAULT_WORDING, $nita, $me);
        $_SESSION['cset_flash'] = $err ? ['warning', 'Saved, but the NITA logo was not: ' . $err] : ['success', 'Saved. Certificates issued from now on use this. To update an issued certificate, press Reissue on it in Certificates.'];
    }
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['cset_flash'] ?? null;
unset($_SESSION['cset_flash']);
$n = $set['nita'];
$nitaLogo = tr_abs($n['logo'] ?: null);
$installed = true;
try { $db->query('SELECT issued_snapshot FROM certificates LIMIT 1'); } catch (Throwable $ex) { $installed = false; }

$page_title = 'Settings · Certificate';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Certificate', 'active' => true]];
ob_start();
?>
<style>
.count{font-weight:600;color:#037A3B}.count.amber{color:#b45309}.count.full{color:#b42318}
.pv{overflow:hidden;background:#e9ece7;border-radius:8px}#pv{width:1123px;height:794px;border:0;transform-origin:0 0;background:#fff}
</style>
<?php if ($flash): ?><div class="alert alert-<?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Run System Updates (migration 100) so issued certificates are kept exactly as issued and can be reissued.</div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" id="tf" action="<?php echo $e($self); ?>"><?php echo csrf_field(); ?>
<div class="row g-3">
  <div class="col-xl-4">
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-1">Default wording</h6>
      <p class="small text-muted mb-2">Printed under the student's name on every new certificate. Fields: <code>{course}</code> <code>{duration}</code> <code>{student_name}</code>.
        The student's name, registration number and certificate number are locked. The tutor's extra lines are written per certificate.</p>
      <textarea class="form-control form-control-sm" name="wording" id="wording" rows="3" maxlength="<?php echo CV2_WORDING_MAX; ?>" data-max="<?php echo CV2_WORDING_MAX; ?>"><?php echo $e($set['wording']); ?></textarea>
      <div class="d-flex justify-content-between small mt-1"><span class="text-muted">Up to <?php echo CV2_WORDING_MAX; ?> characters.</span><span class="count" data-for="wording"></span></div>
    </div>
    <div class="card p-3 mb-3">
      <h6 class="fw-bold mb-2">NITA Accreditation</h6>
      <label class="form-label small fw-semibold mb-1">NITA logo</label>
      <?php if ($nitaLogo): ?><div class="mb-2"><img src="<?php echo $e(cv2_uri_any($nitaLogo)); ?>" alt="NITA logo" style="max-height:48px"> <label class="small ms-2"><input type="checkbox" name="nita_remove_logo" value="1"> Remove</label></div><?php endif; ?>
      <input type="file" name="nita_logo" class="form-control form-control-sm mb-2" accept="image/png,image/jpeg">
      <label class="form-label small fw-semibold mb-1">NITA number</label>
      <input name="nita_number" class="form-control form-control-sm mb-2" maxlength="40" value="<?php echo $e($n['number']); ?>">
      <div class="row g-2">
        <div class="col-6"><label class="form-label small fw-semibold mb-1">Where</label><select name="nita_position" class="form-select form-select-sm">
          <?php foreach (['top_right' => 'Top right', 'under_qr' => 'Under the QR code', 'footer' => 'Footer line'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $n['position'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small fw-semibold mb-1">How</label><select name="nita_style" class="form-select form-select-sm">
          <?php foreach (['block' => 'Block with logo', 'line' => 'Plain line'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $n['style'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small fw-semibold mb-1">Text size</label><select name="nita_size" class="form-select form-select-sm">
          <?php foreach (['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $n['size'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label small fw-semibold mb-1">Colour</label><input type="color" name="nita_color" class="form-control form-control-sm form-control-color w-100" value="<?php echo $e($n['color']); ?>"></div>
      </div>
      <div class="form-text">Our logo green (#048C44) is the default colour.</div>
    </div>
    <div class="d-flex align-items-center gap-2"><span class="small" id="dirty" hidden style="color:#b45309">Unsaved changes</span><button class="btn btn-success ms-auto" name="action" value="save"><i class="fas fa-save me-1"></i>Save</button></div>
    <p class="small text-muted mt-2">Saved changes apply to certificates issued after the save. Issued certificates stay exactly as issued until you press <b>Reissue</b> on them in <a href="<?php echo url('admin/certificates/index.php'); ?>">Certificates</a>.</p>
  </div>
  <div class="col-xl-8"><div class="card p-2"><div class="small text-muted px-1 mb-1">Live preview of the real certificate (sample student)</div><div class="pv" id="wrap"><iframe id="pv" title="Certificate preview"></iframe></div></div></div>
</div>
</form>
<?php
$content = ob_get_clean();
ob_start();
require __DIR__ . '/../../components/certificates/text_editor_js.php';
$extra_js = ($extra_js ?? '') . ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
