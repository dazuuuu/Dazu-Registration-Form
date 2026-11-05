<?php
// Admin Settings: Certificate editor. Works like a word processor on the certificate itself: click a text and type; select words
// and press B/I; the toolbar sets font, size, colour, spacing; drag the ⠿ handle to move a line; remove or bring back lines;
// insert a student field (name, course, date…) that fills in for each student.
//   no ?cert   = the template every certificate uses (also ones already issued, unless they have their own edits)
//   ?cert=<id> = one issued certificate only (certificates.template_json); "Use the template again" drops its own edits.
// Only the office administrators (role_id ≤ 2); every save and reset is written to certificate_template_events.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the certificate.');
}
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$me = (int) ($_SESSION['user_id'] ?? 0);
$hasOwn = true;
try { $db->query('SELECT template_json FROM certificates LIMIT 1'); } catch (Throwable $ex) { $hasOwn = false; }

$certId = $hasOwn ? (int) ($_GET['cert'] ?? $_POST['cert'] ?? 0) : 0;
$cert = $certId ? (new \Models\CertificateModel($db))->getCertificateById($certId) : null;
if ($certId && !$cert) { http_response_code(404); exit('Certificate not found.'); }
$self = url('admin/settings/certificate.php' . ($certId ? '?cert=' . $certId : ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'reset') {
        cert_tpl_save($db, null, $me, $certId);
        $f = ['success', $certId ? 'This certificate uses the template again.' : 'The template is back to the original design.'];
    } else {
        $in = json_decode((string) ($_POST['tpl_json'] ?? ''), true);
        if (!is_array($in)) {
            $f = ['danger', 'Nothing to save.'];
        } else {
            cert_tpl_save($db, cert_tpl_from_post(['tpl' => $in]), $me, $certId);
            $f = ['success', $certId ? 'Saved. This certificate now prints with your changes.' : 'Saved. Every certificate without its own edits now prints like this.'];
        }
    }
    $_SESSION['ctpl_flash'] = $f;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['ctpl_flash'] ?? null;
unset($_SESSION['ctpl_flash']);

// What is being edited, drawn by the same code as the PDF, with every line tagged for the editor.
if ($cert) {
    $own = json_decode((string) ($cert['template_json'] ?? ''), true) ?: null;
    $tpl = cert_tpl($own);
    $base = cert_enrich($cert);
} else {
    $own = null;
    $tpl = cert_tpl();
    $base = ['snapshot_name' => 'Jane Wanjiku Kamau', 'snapshot_course' => 'Web Development', 'snapshot_duration_months' => 6,
             'certificate_no' => 'DTI-CERT-2026-0001', 'qr_code_hash' => 'sample', 'tutor_name' => 'Tutor Name', 'issued_at' => date('Y-m-d'), 'taught_skills' => ''];
}
$GLOBALS['cert_tpl_live'] = true;
$liveHtml = cert_render_html(['_tpl' => $tpl, '_live' => true] + $base);
$GLOBALS['cert_tpl_live'] = false;
$liveVars = $GLOBALS['cert_tpl_vars'] ?? [];

// Find a certificate to edit on its own.
$q = trim((string) ($_GET['q'] ?? ''));
$found = [];
if ($hasOwn) {
    $sql = "SELECT id, certificate_no, snapshot_name, snapshot_course, issued_at, template_json IS NOT NULL AS own FROM certificates WHERE status <> 'withdrawn'";
    $args = [];
    if ($q !== '') { $sql .= ' AND (certificate_no LIKE ? OR snapshot_name LIKE ?)'; $args = ['%' . $q . '%', '%' . $q . '%']; }
    $st = $db->prepare($sql . ' ORDER BY id DESC LIMIT 12');
    $st->execute($args);
    $found = $st->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Settings · Certificate';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Certificate', 'active' => true]];
ob_start();
?>
<style>
.ce-bar{position:sticky;top:64px;z-index:5;background:#fff;border:1px solid #e3e6e1;border-radius:10px;padding:8px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.ce-bar .form-select,.ce-bar .form-control{width:auto;display:inline-block}
.ce-bar .sep{width:1px;height:26px;background:#e3e6e1;margin:0 2px}
.ce-bar button.on{background:#048C44;color:#fff;border-color:#048C44}
.ce-page{background:#e9ece7;border-radius:10px;padding:14px;overflow:hidden}
#pv{width:1123px;height:794px;border:0;transform-origin:0 0;background:#fff;box-shadow:0 6px 24px rgba(0,0,0,.15)}
.ce-dis{opacity:.45;pointer-events:none}
</style>
<?php if ($flash): ?><div class="alert alert-<?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$hasOwn): ?><div class="alert alert-warning">Run System Updates (migration 099) to edit issued certificates one by one. The template can be edited now.</div><?php endif; ?>

<div class="row g-3">
<div class="col-xl-9">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div>
      <?php if ($cert): ?>
        <div class="fw-semibold">Editing certificate <?php echo $e($cert['certificate_no']); ?> · <?php echo $e($cert['snapshot_name']); ?></div>
        <div class="small text-muted"><?php echo $own ? 'This certificate has its own edits.' : 'This certificate follows the template. Your changes here apply to it only.'; ?> <a href="<?php echo url('admin/settings/certificate.php'); ?>">Edit the template instead</a></div>
      <?php else: ?>
        <div class="fw-semibold">Editing the template for every certificate</div>
        <div class="small text-muted">Shown with a sample student. Green-backed words (name, course, date…) fill in for each student.</div>
      <?php endif; ?>
    </div>
    <form method="POST" id="ceForm" class="d-flex gap-2"><?php echo csrf_field(); ?>
      <input type="hidden" name="cert" value="<?php echo (int) $certId; ?>"><input type="hidden" name="tpl_json" id="tplJson">
      <button class="btn btn-success btn-sm" name="action" value="save"><i class="fas fa-save me-1"></i>Save</button>
      <button class="btn btn-outline-secondary btn-sm" name="action" value="reset" onclick="return confirm(<?php echo $e(json_encode($cert ? 'Drop this certificate\'s own edits and use the template again?' : 'Put the template back to the original design?')); ?>)"><?php echo $cert ? 'Use the template again' : 'Original design'; ?></button>
      <?php if ($cert): ?><a class="btn btn-outline-dark btn-sm" target="_blank" href="<?php echo url('admin/certificates/preview.php?id=' . (int) $cert['id']); ?>"><i class="fas fa-file-pdf me-1"></i>PDF</a><?php endif; ?>
    </form>
  </div>

  <div class="ce-bar mb-2" id="bar">
    <span class="small text-muted me-1" id="cur">Click a text on the certificate</span>
    <span class="sep"></span>
    <select class="form-select form-select-sm" id="tFont" title="Font"><?php foreach (cert_tpl_fonts() as $fk => $fv): ?><option value="<?php echo $fk; ?>"><?php echo $e($fv[0]); ?></option><?php endforeach; ?></select>
    <input type="number" class="form-control form-control-sm" id="tSize" min="4" max="96" step="0.5" style="width:72px" title="Size (pt)">
    <input type="color" class="form-control form-control-sm form-control-color" id="tColor" title="Colour">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="tBold" title="Bold (select words to bold only them)"><b>B</b></button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="tItalic" title="Italic"><i>I</i></button>
    <span class="small text-muted ms-1">Spacing</span><input type="number" class="form-control form-control-sm" id="tSp" min="0" max="20" step="0.5" style="width:64px" title="Letter spacing (pt)">
    <span class="sep"></span>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="tUp" title="Move up 1 mm">↑</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="tDown" title="Move down 1 mm">↓</button>
    <select class="form-select form-select-sm" id="tField" title="Insert a student field where the cursor is"><option value="">+ Field</option><?php foreach (cert_tpl_placeholders() as $ph => $what): if ($ph === '{extra}') continue; ?><option value="<?php echo $e($ph); ?>"><?php echo $e($what); ?></option><?php endforeach; ?></select>
    <button type="button" class="btn btn-sm btn-outline-danger" id="tHide" title="Remove this line from the certificate"><i class="fas fa-eye-slash"></i></button>
    <select class="form-select form-select-sm" id="tShow" title="Bring back a removed line"><option value="">Removed lines…</option></select>
  </div>
  <div class="ce-page" id="wrap"><iframe id="pv" title="Certificate" srcdoc="<?php echo $e($liveHtml); ?>"></iframe></div>
  <p class="small text-muted mt-2 mb-0">Click a text and type. Select words and press <b>B</b> or <b>I</b> to change only them. Drag the green ⠿ handle to move a line. Changes show at once; <b>Save</b> keeps them.</p>
</div>

<div class="col-xl-3">
  <div class="card p-3">
    <div class="fw-semibold mb-1">Edit one issued certificate</div>
    <form method="GET" class="input-group input-group-sm mb-2"><input name="q" class="form-control" placeholder="Number or name" value="<?php echo $e($q); ?>"><button class="btn btn-outline-secondary">Find</button></form>
    <?php if (!$found): ?><div class="small text-muted"><?php echo $hasOwn ? 'No certificates found.' : 'Needs migration 099.'; ?></div><?php endif; ?>
    <div class="list-group list-group-flush small">
      <a href="<?php echo url('admin/settings/certificate.php'); ?>" class="list-group-item list-group-item-action <?php echo !$certId ? 'active' : ''; ?>"><i class="fas fa-layer-group me-1"></i>The template (all certificates)</a>
      <?php foreach ($found as $c): ?>
        <a href="<?php echo url('admin/settings/certificate.php?cert=' . (int) $c['id']); ?>" class="list-group-item list-group-item-action <?php echo $certId === (int) $c['id'] ? 'active' : ''; ?>">
          <div class="fw-semibold"><?php echo $e($c['snapshot_name']); ?><?php if ($c['own']): ?> <span class="badge bg-warning text-dark">own edits</span><?php endif; ?></div>
          <div class="text-muted" style="font-size:.75rem"><?php echo $e($c['certificate_no']); ?> · <?php echo $e($c['snapshot_course']); ?></div></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>

<script>
(function () {
  var pv = document.getElementById('pv'), wrap = document.getElementById('wrap');
  var state = <?php echo json_encode(array_map(fn($l) => array_intersect_key($l, array_flip(['text', 'font', 'size', 'color', 'bold', 'italic', 'sp', 'top', 'show', 'label'])), $tpl), JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  var vars = <?php echo json_encode($liveVars, JSON_HEX_TAG | JSON_HEX_AMP); ?>, fonts = <?php echo json_encode(array_map(fn($f) => $f[1], cert_tpl_fonts())); ?>;
  var $ = function (id) { return document.getElementById(id); }, doc, cur = null, scale = 1, MM = 1123 / 297;
  function fit() { scale = Math.min(1, (wrap.clientWidth - 28) / 1123); pv.style.transform = 'scale(' + scale + ')'; wrap.style.height = (794 * scale + 28) + 'px'; }
  window.addEventListener('resize', fit); fit();
  function el(k) { return doc && doc.querySelector('[data-k="' + k + '"]'); }

  // Saved form of a line: **bold**, {placeholders}, new lines.
  function serialize(node) {
    var out = '';
    node.childNodes.forEach(function (n) {
      if (n.nodeType === 3) { out += n.nodeValue.replace(/ /g, ' '); return; }
      if (n.nodeType !== 1 || n.classList.contains('ce-h')) return;
      var t = n.tagName;
      if (n.dataset && n.dataset.ph) { out += n.dataset.ph; return; }
      if (t === 'BR') { out += '\n'; return; }
      var inner = serialize(n);
      if (t === 'B' || t === 'STRONG' || (n.style && (n.style.fontWeight === 'bold' || +n.style.fontWeight >= 600))) out += inner.trim() ? '**' + inner + '**' : inner;
      else if (t === 'DIV' || t === 'P') out += (out && !/\n$/.test(out) ? '\n' : '') + inner;
      else out += inner;
    });
    return out.replace(/\*\*\*\*/g, '');
  }
  function style(k) {
    var s = state[k], e = el(k); if (!e) return;
    e.style.display = s.show ? '' : 'none';
    e.style.fontFamily = fonts[s.font]; e.style.fontSize = s.size + 'pt'; e.style.color = s.color;
    e.style.fontWeight = s.bold ? 'bold' : 'normal'; e.style.fontStyle = s.italic ? 'italic' : 'normal';
    e.style.letterSpacing = s.sp + 'pt'; e.style.top = s.top + 'mm'; e.style.lineHeight = s.size >= 16 ? 1 : 1.35;
  }
  function removedList() {
    var sel = $('tShow'); sel.length = 1;
    Object.keys(state).forEach(function (k) { if (!state[k].show) sel.add(new Option(state[k].label, k)); });
  }
  function pick(k) {
    cur = k; var s = state[k];
    doc.querySelectorAll('[data-k].ce-on').forEach(function (x) { x.classList.remove('ce-on'); });
    var e = el(k);
    if (e) {   // the drag handle sits just left of the line, or inside it for lines that start at the page edge
      e.classList.add('ce-on'); e.appendChild(handle);
      handle.style.left = parseFloat(e.style.left) < 10 ? '3mm' : '-7mm';
    }
    $('cur').textContent = s.label; $('bar').classList.remove('ce-dis');
    $('tFont').value = s.font; $('tSize').value = s.size; $('tColor').value = s.color; $('tSp').value = s.sp;
    $('tBold').classList.toggle('on', !!s.bold); $('tItalic').classList.toggle('on', !!s.italic);
  }
  function set(prop, v) { if (!cur) return; state[cur][prop] = v; style(cur); pick(cur); }
  var handle;

  pv.addEventListener('load', function () {
    doc = pv.contentDocument;
    var css = doc.createElement('style');
    css.textContent = '[data-k]{cursor:text;outline:none;min-width:8mm}[data-k]:hover{outline:1px dashed rgba(4,140,68,.6);outline-offset:2px}'
      + '[data-k].ce-on{outline:1.5px solid #048C44;outline-offset:2px}.ph{background:rgba(4,140,68,.13);border-radius:3px}'
      + '.ce-h{position:absolute;left:-7mm;top:0;width:5mm;height:5mm;background:#048C44;color:#fff;font:10pt sans-serif;line-height:5mm;text-align:center;border-radius:2px;cursor:ns-resize;user-select:none}';
    doc.head.appendChild(css);
    handle = doc.createElement('span'); handle.className = 'ce-h'; handle.contentEditable = 'false'; handle.textContent = '⠿'; handle.title = 'Drag to move';
    Object.keys(state).forEach(function (k) { var e = el(k); if (e) { e.contentEditable = 'true'; e.spellcheck = false; style(k); } });
    removedList();
    doc.addEventListener('mousedown', function (ev) {
      if (ev.target === handle) {   // drag the line up or down
        ev.preventDefault(); var y0 = ev.screenY, t0 = state[cur].top;   // screen pixels: the page is shown scaled down
        var mv = function (m) { state[cur].top = Math.max(0, Math.min(205, Math.round((t0 + (m.screenY - y0) / (MM * scale)) * 2) / 2)); style(cur); };
        var up = function () { doc.removeEventListener('mousemove', mv); doc.removeEventListener('mouseup', up); };
        doc.addEventListener('mousemove', mv); doc.addEventListener('mouseup', up); return;
      }
      var e = ev.target.closest && ev.target.closest('[data-k]'); if (e) pick(e.getAttribute('data-k'));
    });
    doc.addEventListener('input', function (ev) { var e = ev.target.closest && ev.target.closest('[data-k]'); if (e) state[e.getAttribute('data-k')].text = serialize(e); });
    doc.addEventListener('keydown', function (ev) { if ((ev.ctrlKey || ev.metaKey) && /^[bi]$/i.test(ev.key)) { ev.preventDefault(); fmt(ev.key.toLowerCase() === 'b' ? 'bold' : 'italic'); } });
  });

  // B / I: words selected → only them (bold is saved; italic for a whole line); nothing selected → the whole line.
  function fmt(kind) {
    if (!cur) return;
    var sel = doc.getSelection(), e = el(cur);
    if (kind === 'bold' && sel && !sel.isCollapsed && e.contains(sel.anchorNode)) {
      doc.execCommand('styleWithCSS', false, false); doc.execCommand('bold'); state[cur].text = serialize(e); return;
    }
    set(kind, !state[cur][kind]);
  }
  $('tBold').onclick = function () { fmt('bold'); };
  $('tItalic').onclick = function () { fmt('italic'); };
  $('tFont').onchange = function () { set('font', this.value); };
  $('tSize').oninput = function () { if (this.value) set('size', +this.value); };
  $('tColor').oninput = function () { set('color', this.value); };
  $('tSp').oninput = function () { set('sp', +this.value || 0); };
  $('tUp').onclick = function () { if (cur) set('top', Math.max(0, state[cur].top - 1)); };
  $('tDown').onclick = function () { if (cur) set('top', Math.min(205, state[cur].top + 1)); };
  $('tHide').onclick = function () { if (!cur) return; set('show', false); removedList(); };
  $('tShow').onchange = function () { var k = this.value; if (!k) return; state[k].show = true; style(k); removedList(); pick(k); };
  $('tField').onchange = function () {   // a field chip at the cursor
    var ph = this.value; this.value = ''; if (!ph || !cur) return;
    var e = el(cur), sel = doc.getSelection(), chip = doc.createElement('span');
    chip.className = 'ph'; chip.contentEditable = 'false'; chip.dataset.ph = ph; chip.innerHTML = vars[ph] || ph;
    if (sel && sel.rangeCount && e.contains(sel.anchorNode)) { var r = sel.getRangeAt(0); r.deleteContents(); r.insertNode(chip); } else e.appendChild(chip);
    state[cur].text = serialize(e);
  };
  $('bar').classList.add('ce-dis');
  document.getElementById('ceForm').addEventListener('submit', function () {
    var out = {}; Object.keys(state).forEach(function (k) { var s = state[k]; out[k] = { text: s.text, font: s.font, size: s.size, color: s.color, bold: s.bold ? 1 : 0, italic: s.italic ? 1 : 0, sp: s.sp, top: s.top, show: s.show ? 1 : 0 }; });
    $('tplJson').value = JSON.stringify(out);
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
