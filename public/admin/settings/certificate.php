<?php
// Admin Settings: Certificate. Edit every text line of the certificate (wording, font, size, colour, bold, italic, spacing, height,
// shown or hidden) with a live preview. One template for every certificate. Only the office administrators (role_id ≤ 2);
// every save and reset is written to certificate_template_events.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the certificate.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/certificate.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$me = (int) ($_SESSION['user_id'] ?? 0);

$sample = fn(?array $tpl) => cert_render_html([
    'snapshot_name' => 'Jane Wanjiku Kamau', 'snapshot_course' => 'Web Development', 'snapshot_duration_months' => 6,
    'certificate_no' => 'DTI-CERT-2026-0001', 'qr_code_hash' => 'sample', 'tutor_name' => 'Tutor Name', 'issued_at' => date('Y-m-d'),
    'taught_skills' => '', '_tpl' => $tpl, '_watermark' => 'SAMPLE',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if (($_POST['action'] ?? '') === 'preview') { http_response_code(403); exit('Security check failed. Refresh the page.'); }
        $f = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'preview') {
        header('Content-Type: text/html; charset=UTF-8');
        echo $sample(cert_tpl_from_post($_POST));
        exit;
    } elseif (($_POST['action'] ?? '') === 'reset') {
        cert_tpl_save($db, null, $me);
        $f = ['success', 'The certificate is back to the original design.'];
    } else {
        cert_tpl_save($db, cert_tpl_from_post($_POST), $me);
        $f = ['success', 'Certificate saved. Every certificate printed or downloaded from now on uses it.'];
    }
    $_SESSION['ctpl_flash'] = $f;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['ctpl_flash'] ?? null;
unset($_SESSION['ctpl_flash']);
$tpl = cert_tpl();
$installed = true;
try { $db->query('SELECT 1 FROM certificate_template_events LIMIT 1'); } catch (Throwable $ex) { $installed = false; }

$page_title = 'Settings · Certificate';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Certificate', 'active' => true]];
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 097) so changes are logged.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-5">
    <form method="POST" id="ctpl"><?php echo csrf_field(); ?>
      <div class="card p-3 mb-2">
        <div class="small text-muted">Change any line, then <b>Save</b>. The preview updates as you type. Untick <b>Show</b> to remove a line.
          Use <code>**words**</code> for bold. Filled in for each student:
          <?php foreach (cert_tpl_placeholders() as $ph => $what): ?><code title="<?php echo $e($what); ?>"><?php echo $e($ph); ?></code> <?php endforeach; ?></div>
      </div>
      <div class="accordion" id="lines">
      <?php foreach ($tpl as $k => $l): $n = 'tpl[' . $k . ']'; ?>
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed py-2 small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#l-<?php echo $k; ?>">
            <?php echo $e($l['label']); ?><span class="ms-2 text-muted fw-normal text-truncate" style="max-width:200px"><?php echo $e($l['text']); ?></span></button></h2>
          <div id="l-<?php echo $k; ?>" class="accordion-collapse collapse" data-bs-parent="#lines"><div class="accordion-body row g-2">
            <div class="col-12"><textarea class="form-control form-control-sm" rows="2" maxlength="400" name="<?php echo $n; ?>[text]"><?php echo $e($l['text']); ?></textarea></div>
            <div class="col-6"><label class="form-label small mb-0">Font</label><select class="form-select form-select-sm" name="<?php echo $n; ?>[font]">
              <?php foreach (cert_tpl_fonts() as $fk => $fv): ?><option value="<?php echo $fk; ?>" <?php echo $l['font'] === $fk ? 'selected' : ''; ?>><?php echo $e($fv[0]); ?></option><?php endforeach; ?></select></div>
            <div class="col-3"><label class="form-label small mb-0">Size (pt)</label><input type="number" step="0.5" min="4" max="96" class="form-control form-control-sm" name="<?php echo $n; ?>[size]" value="<?php echo $e($l['size']); ?>"></div>
            <div class="col-3"><label class="form-label small mb-0">Colour</label><input type="color" class="form-control form-control-sm form-control-color w-100" name="<?php echo $n; ?>[color]" value="<?php echo $e($l['color']); ?>"></div>
            <div class="col-4"><label class="form-label small mb-0">Spacing (pt)</label><input type="number" step="0.5" min="0" max="20" class="form-control form-control-sm" name="<?php echo $n; ?>[sp]" value="<?php echo $e($l['sp']); ?>"></div>
            <div class="col-4"><label class="form-label small mb-0">From top (mm)</label><input type="number" step="0.5" min="0" max="205" class="form-control form-control-sm" name="<?php echo $n; ?>[top]" value="<?php echo $e($l['top']); ?>"></div>
            <div class="col-4 d-flex flex-column justify-content-end small">
              <label><input type="checkbox" name="<?php echo $n; ?>[bold]" value="1" <?php echo $l['bold'] ? 'checked' : ''; ?>> Bold</label>
              <label><input type="checkbox" name="<?php echo $n; ?>[italic]" value="1" <?php echo $l['italic'] ? 'checked' : ''; ?>> Italic</label>
              <label><input type="checkbox" name="<?php echo $n; ?>[show]" value="1" <?php echo $l['show'] ? 'checked' : ''; ?>> Show</label>
            </div>
          </div></div>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-success btn-sm" name="action" value="save"><i class="fas fa-save me-1"></i>Save certificate</button>
        <button class="btn btn-outline-secondary btn-sm" name="action" value="reset" formnovalidate onclick="return confirm('Go back to the original certificate design?')">Original design</button>
      </div>
      <p class="small text-muted mt-2 mb-0">The artwork, logo, QR code and signatures stay in place. Certificates are drawn when they are opened, so a saved change also shows on certificates already issued.</p>
    </form>
  </div>
  <div class="col-xl-7">
    <div class="card p-2" style="position:sticky;top:80px">
      <div class="small text-muted mb-1 px-1">Live preview (sample student)</div>
      <div id="pv-wrap" style="width:100%;overflow:hidden;background:#eef0ec;border-radius:6px"><iframe id="pv" title="Certificate preview" style="width:1123px;height:794px;border:0;transform-origin:0 0;background:#fff" srcdoc="<?php echo $e($sample(null)); ?>"></iframe></div>
    </div>
  </div>
</div>
<script>
(function () {
  var form = document.getElementById('ctpl'), pv = document.getElementById('pv'), wrap = document.getElementById('pv-wrap'), t;
  function fit() { var s = wrap.clientWidth / 1123; pv.style.transform = 'scale(' + s + ')'; wrap.style.height = (794 * s) + 'px'; }
  window.addEventListener('resize', fit); fit();
  function refresh() {
    var fd = new FormData(form); fd.set('action', 'preview');
    fetch(<?php echo json_encode($self); ?>, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); }).then(function (h) { pv.srcdoc = h; });
  }
  form.addEventListener('input', function () { clearTimeout(t); t = setTimeout(refresh, 400); });
  form.addEventListener('change', function () { clearTimeout(t); t = setTimeout(refresh, 150); });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
