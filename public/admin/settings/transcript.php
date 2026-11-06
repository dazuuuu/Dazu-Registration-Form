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
                    if ($kind === 'stamp') {   // the areas belong to the old picture: mark them again on this one
                        tr_setting_save($db, 'stamp_date_box', '', $me);
                        tr_setting_save($db, 'stamp_sign_box', '', $me);
                    }
                    $f = ['success', $kind === 'stamp' ? 'Stamp saved, background removed. Now mark the date area and the Sign: line on it.' : 'Logo saved. Transcripts released from now on use it.'];
                } else {
                    $f = ['error', $r['error']];
                }
                break;
            case 'stamp_areas':
                $d = stamp_box((string) ($_POST['date_box'] ?? ''));
                $g = stamp_box((string) ($_POST['sign_box'] ?? ''));
                if (!$d || !$g) {
                    $f = ['error', 'Mark both areas: the date and the Sign: line.'];
                } else {
                    $j = fn(array $b) => json_encode(array_map(fn($v) => round($v, 4), $b));
                    tr_setting_save($db, 'stamp_date_box', $j($d), $me);
                    tr_setting_save($db, 'stamp_sign_box', $j($g), $me);
                    $f = ['success', 'Areas saved. Letters and transcripts issued from now on get this stamp with their own date and the director\'s signature.'];
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
$dateBox = $installed ? stamp_box($s['stamp_date_box']) : null;
$signBox = $installed ? stamp_box($s['stamp_sign_box']) : null;
$sampleUri = null;   // the finished stamp as a document issued today would get it
if ($stampUri && $dateBox && $signBox) {
    $png = stamp_compose(tr_abs($s['stamp_path']), $dateBox, $signBox, new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')), dsig_active($db)['file'] ?? null);
    $sampleUri = $png ? 'data:image/png;base64,' . base64_encode($png) : null;
}
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
<?php $miss = $installed ? tr_settings_missing($db) : []; if ($miss): ?><div class="alert alert-warning">No letter of recommendation or transcript can be issued until <?php echo $e(implode(' and ', $miss)); ?> <?php echo count($miss) > 1 ? 'are' : 'is'; ?> in place.</div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3 p-md-4 h-100">
      <div class="d-flex justify-content-between"><h6 class="fw-bold">Official stamp</h6><span class="badge rounded-pill <?php echo $stampUri ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $stampUri ? 'Set' : 'Not set'; ?></span></div>
      <p class="small text-muted">Upload a photo or scan of the stamp once. The white background is removed by itself; the stamp's colours stay exactly as they are.
        It is then printed by itself on every letter of recommendation and transcript, with that document's issue date written in it and the director's signature on its Sign: line.</p>
      <?php if (!$stampUri): ?><div class="trs-box mb-3"><span class="small text-muted">No stamp yet</span></div><?php else: ?>
      <div class="mb-2 small">
        <span class="badge <?php echo $dateBox ? 'bg-success' : 'bg-warning text-dark'; ?>">Date area <?php echo $dateBox ? 'marked' : 'not marked'; ?></span>
        <span class="badge <?php echo $signBox ? 'bg-success' : 'bg-warning text-dark'; ?>">Sign: line <?php echo $signBox ? 'marked' : 'not marked'; ?></span>
      </div>
      <div class="d-flex flex-wrap gap-3 align-items-start mb-3">
        <div>
          <div class="btn-group btn-group-sm mb-2" role="group">
            <button type="button" class="btn btn-outline-danger" id="mkDate"><i class="fas fa-calendar me-1"></i>Mark the date</button>
            <button type="button" class="btn btn-outline-primary" id="mkSign"><i class="fas fa-signature me-1"></i>Mark the Sign: line</button>
          </div>
          <div id="stArea" style="position:relative;display:inline-block;user-select:none;cursor:crosshair;background:repeating-conic-gradient(#eee 0 25%,#fff 0 50%) 0 0/16px 16px;border:1px solid #ddd;border-radius:6px">
            <img src="<?php echo $stampUri; ?>" alt="Official stamp" id="stImg" draggable="false" style="display:block;width:300px;max-width:100%">
            <div id="bxDate" style="position:absolute;border:2px solid #dc3545;background:rgba(220,53,69,.12);display:none;pointer-events:none"></div>
            <div id="bxSign" style="position:absolute;border:2px solid #0d6efd;background:rgba(13,110,253,.12);display:none;pointer-events:none"></div>
          </div>
          <div class="form-text" style="max-width:300px">Choose a button, then drag a box on the stamp: around the old date, and over the dotted line after "Sign:".</div>
          <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="stamp_areas">
            <input type="hidden" name="date_box" id="inDate" value="<?php echo $e($s['stamp_date_box']); ?>"><input type="hidden" name="sign_box" id="inSign" value="<?php echo $e($s['stamp_sign_box']); ?>">
            <button class="btn btn-sm btn-success"><i class="fas fa-save me-1"></i>Save the two areas</button>
          </form>
        </div>
        <?php if ($sampleUri): ?><div class="text-center"><div class="small fw-semibold mb-1">How documents issued today get it</div>
          <img src="<?php echo $sampleUri; ?>" alt="Stamp with today's date" style="width:220px;background:#fff;border:1px solid #eee;border-radius:6px"></div><?php endif; ?>
      </div>
      <script>
      (function () {
        var area = document.getElementById('stArea'), img = document.getElementById('stImg'), mode = null, start = null;
        var boxes = { date: document.getElementById('bxDate'), sign: document.getElementById('bxSign') }, inputs = { date: document.getElementById('inDate'), sign: document.getElementById('inSign') };
        function draw(k) { var b; try { b = JSON.parse(inputs[k].value || 'null'); } catch (e) { b = null; } var el = boxes[k];
          if (!b) { el.style.display = 'none'; return; } var W = img.clientWidth, H = img.clientHeight;
          el.style.display = 'block'; el.style.left = b.x * W + 'px'; el.style.top = b.y * H + 'px'; el.style.width = b.w * W + 'px'; el.style.height = b.h * H + 'px'; }
        function setMode(m) { mode = m; document.getElementById('mkDate').classList.toggle('active', m === 'date'); document.getElementById('mkSign').classList.toggle('active', m === 'sign'); }
        document.getElementById('mkDate').onclick = function () { setMode('date'); };
        document.getElementById('mkSign').onclick = function () { setMode('sign'); };
        function pos(ev) { var r = img.getBoundingClientRect(), p = ev.touches ? ev.touches[0] : ev; return { x: Math.max(0, Math.min(1, (p.clientX - r.left) / r.width)), y: Math.max(0, Math.min(1, (p.clientY - r.top) / r.height)) }; }
        function down(ev) { if (!mode) { alert('Choose "Mark the date" or "Mark the Sign: line" first.'); return; } ev.preventDefault(); start = pos(ev); }
        function move(ev) { if (!start) return; var p = pos(ev); inputs[mode].value = JSON.stringify({ x: Math.min(start.x, p.x), y: Math.min(start.y, p.y), w: Math.abs(p.x - start.x), h: Math.abs(p.y - start.y) }); draw(mode); }
        function up() { start = null; }
        area.addEventListener('mousedown', down); window.addEventListener('mousemove', move); window.addEventListener('mouseup', up);
        area.addEventListener('touchstart', down, { passive: false }); window.addEventListener('touchmove', move); window.addEventListener('touchend', up);
        img.complete ? (draw('date'), draw('sign')) : img.addEventListener('load', function () { draw('date'); draw('sign'); });
      })();
      </script>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap"><?php echo csrf_field(); ?><input type="hidden" name="action" value="stamp">
        <input type="file" name="image" class="form-control form-control-sm" style="max-width:280px" accept="image/png,image/jpeg" required>
        <button class="btn btn-sm btn-success" <?php echo $installed ? '' : 'disabled'; ?>><i class="fas fa-upload me-1"></i>Upload stamp</button>
      </form>
      <div class="form-text">PNG or JPG, up to 5 MB. A new stamp is used only for documents issued after it; documents already issued keep theirs.</div>
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
