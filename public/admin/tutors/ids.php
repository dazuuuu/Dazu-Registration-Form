<?php
// Admin: Tutor IDs. Every tutor's card status, the filters (ready to print, photo needs update, no photo, printed,
// edited by tutor), and the print file: Download selected / Download all ready IDs as one PDF (front then back for
// each tutor, 50 × 85 mm with 3 mm bleed) or A4 sheets of 8. Big batches come as a ZIP of PDFs. Admins only.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/tutors/ids.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = tid_installed($db) && (function () use ($db) { try { $db->query('SELECT 1 FROM id_print_batches LIMIT 1'); return true; } catch (Throwable $e) { return false; } })();

$send = function (string $abs, string $name): void {
    header('Content-Type: ' . (str_ends_with($name, '.zip') ? 'application/zip' : 'application/pdf'));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($abs);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['tid_flash'] = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif (!$installed) {
        $_SESSION['tid_flash'] = ['error', 'Run System Updates (migration 081) first.'];
    } elseif (($_POST['action'] ?? '') === 'again') {
        if ($f = tid_batch_file($db, (int) ($_POST['batch'] ?? 0))) {
            tid_audit($db, null, 'print_download', 'Again: ' . $f[1]);
            $send($f[0], $f[1]);
        }
        $_SESSION['tid_flash'] = ['error', 'That file is no longer on the server. Make it again.'];
    } elseif (($_POST['action'] ?? '') === 'download') {
        $ids = (array) ($_POST['ids'] ?? []);
        if (($_POST['scope'] ?? '') === 'all') $ids = $db->query('SELECT id FROM tutors')->fetchAll(PDO::FETCH_COLUMN);
        $opt = ['layout' => $_POST['layout'] ?? 'single', 'marks' => !empty($_POST['marks']), 'mark_sent' => !empty($_POST['mark_sent'])];
        $_SESSION['tid_print_opt'] = $opt;
        $r = tid_print_file($db, $ids, $opt);
        if ($r['ok'] && ($f = tid_batch_file($db, $r['batch_id']))) {
            $send($f[0], $f[1]);
        }
        $_SESSION['tid_flash'] = ['error', ($r['error'] ?? 'The file could not be made.') . (!empty($r['skipped']) ? ' ' . implode('; ', array_slice($r['skipped'], 0, 5)) : '')];
    } elseif (($_POST['action'] ?? '') === 'printed') {
        foreach ((array) ($_POST['ids'] ?? []) as $id) {
            $st = $db->prepare('SELECT user_id FROM tutors WHERE id = ?');
            $st->execute([(int) $id]);
            if (($uid = (int) $st->fetchColumn()) && ($t = staff_tutor($db, $uid))) tid_set_id_status($db, $t, 'printed');
        }
        $_SESSION['tid_flash'] = ['success', 'Marked as printed.'];
    }
    header('Location: ' . $self . (isset($_GET['f']) ? '?f=' . urlencode((string) $_GET['f']) : ''));
    exit;
}

$rows = [];
$hasDir = (bool) dsig_active($db);
if ($installed) {
    $list = $db->query(
        "SELECT t.*, u.email, u.is_active AS user_active,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name, up.profile_photo_path
         FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
         ORDER BY t.staff_no IS NULL, t.staff_no, full_name"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($list as $t) {
        $hasPhoto = (bool) idc_student_photo_file($db, (int) $t['user_id']);
        [$ready, $why] = tid_ready($db, $t, $hasDir);
        $t['_photo'] = $hasPhoto ? ($t['photo_status'] === 'none' ? 'ok' : $t['photo_status']) : ($t['photo_status'] === 'requested' ? 'requested' : 'none');
        $t['_sig'] = sig_abs($t['signature_path']) ? ($t['signature_status'] === 'none' ? 'ok' : $t['signature_status']) : 'none';
        $t['_id'] = tid_id_status($db, $t);
        $t['_ready'] = $ready; $t['_why'] = $why;
        $rows[] = $t;
    }
}
$active = array_filter($rows, fn($t) => $t['is_active'] && $t['user_active']);
$filters = [
    'ready' => ['Ready to print', fn($t) => $t['_ready']],
    'all' => ['All', fn($t) => true],
    'photo' => ['Photo needs update', fn($t) => in_array($t['_photo'], ['needs_update', 'requested'], true)],
    'nophoto' => ['No photo', fn($t) => $t['_photo'] === 'none'],
    'printed' => ['Printed', fn($t) => $t['_id'] === 'printed'],
    'edited' => ['Edited by tutor', fn($t) => !empty($t['edited_by_tutor'])],
];
$f = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'ready';
$shown = array_filter($rows, $filters[$f][1]);
$readyIds = array_map(fn($t) => (int) $t['id'], array_filter($rows, fn($t) => $t['_ready']));
$batches = $installed ? $db->query('SELECT b.*, u.username FROM id_print_batches b LEFT JOIN users u ON u.id = b.admin_id ORDER BY b.id DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC) : [];
$opt = $_SESSION['tid_print_opt'] ?? ['layout' => 'single', 'marks' => true, 'mark_sent' => true];
$flash = $_SESSION['tid_flash'] ?? null;
unset($_SESSION['tid_flash']);
$tone = ['ok' => 'success', 'approved' => 'success', 'needs_update' => 'warning text-dark', 'requested' => 'danger', 'none' => 'secondary',
         'preview_ready' => 'success', 'sent_to_print' => 'primary', 'printed' => 'dark', 'not_created' => 'secondary'];
$pill = fn(string $k, string $l) => '<span class="badge rounded-pill bg-' . $tone[$k] . '">' . $e($l) . '</span>';

$page_title = 'Tutor IDs';
$breadcrumbs = [['label' => 'Tutors', 'url' => url('admin/tutors/index.php')], ['label' => 'Tutor IDs', 'active' => true]];
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo ['success' => 'success', 'warning' => 'warning', 'error' => 'danger'][$flash[0]] ?? 'info'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migrations 080 and 081) to use this page.</div><?php endif; ?>
<?php if ($installed && !$hasDir): ?><div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2"><span>No IDs can be printed until the director signature is set.</span><a class="btn btn-sm btn-warning" href="<?php echo $e(url('admin/settings/director_signature.php')); ?>">Set director signature</a></div><?php endif; ?>

<div class="row g-3 mb-3">
  <?php foreach ([['Tutors', count($active), 'active', ''], ['Ready to print', count($readyIds), 'photo uploaded', 'text-success'],
                  ['Waiting for a photo', count(array_filter($active, fn($t) => $t['_photo'] === 'none')), 'tutor must upload', 'text-warning'],
                  ['Printed', count(array_filter($rows, fn($t) => $t['_id'] === 'printed')), 'marked as printed', '']] as [$l, $n, $sub, $cls]): ?>
    <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="small text-muted"><?php echo $l; ?></div><div class="h3 fw-bold mb-0 <?php echo $cls; ?>"><?php echo (int) $n; ?></div><div class="small text-muted"><?php echo $sub; ?></div></div></div>
  <?php endforeach; ?>
</div>

<form method="POST" id="tidsForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="download"><input type="hidden" name="scope" value="selected" id="tidsScope">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
  <div class="d-flex gap-1 flex-wrap">
    <?php foreach ($filters as $k => [$label, $fn]): ?>
      <a href="<?php echo $e($self . '?f=' . $k); ?>" class="btn btn-sm rounded-pill <?php echo $f === $k ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo $e($label); ?> <?php echo count(array_filter($rows, $fn)); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button type="submit" class="btn btn-sm btn-outline-dark" id="tidsSel" disabled><i class="fas fa-download me-1"></i>Download selected (<span>0</span>)</button>
    <button type="submit" class="btn btn-sm btn-success" onclick="document.getElementById('tidsScope').value='all'" <?php echo $readyIds ? '' : 'disabled'; ?>><i class="fas fa-download me-1"></i>Download all ready IDs (<?php echo count($readyIds); ?>)</button>
  </div>
</div>

<div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr class="small text-muted"><th style="width:32px"><input type="checkbox" class="form-check-input" id="tidsAll"></th><th>Tutor</th><th>Photo</th><th>Signature</th><th>ID status</th><th class="text-end"></th></tr></thead>
  <tbody>
  <?php if (!$shown): ?><tr><td colspan="6" class="text-center text-muted small py-4">No tutors here.</td></tr><?php endif; ?>
  <?php foreach ($shown as $t): $thumb = $t['_photo'] !== 'none' ? url('id_cards/tutor_photo.php?u=' . (int) $t['user_id']) . '&v=' . md5(($t['id_photo_path'] ?? '') . $t['profile_photo_path']) : null; ?>
    <tr>
      <td><input type="checkbox" class="form-check-input tids-row" name="ids[]" value="<?php echo (int) $t['id']; ?>" <?php echo $t['_ready'] ? '' : 'disabled title="' . $e($t['_why']) . '"'; ?>></td>
      <td><div class="d-flex align-items-center gap-2">
        <div style="width:34px;aspect-ratio:4/5;border-radius:6px;overflow:hidden;border:2px solid <?php echo $thumb ? '#44A024' : '#ccc'; ?>;<?php echo $thumb ? '' : 'border-style:dashed;'; ?>flex-shrink:0"><?php if ($thumb): ?><img src="<?php echo $e($thumb); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?></div>
        <div><div class="fw-semibold small"><?php echo $e(tid_name($t)); ?> <?php if (!empty($t['edited_by_tutor'])): ?><span class="badge bg-info-subtle text-info">Edited by tutor</span><?php endif; ?></div>
          <div class="small text-muted"><?php echo $e($t['staff_no'] ?: 'No ID number'); ?><?php echo !$t['_ready'] ? ' · ' . $e($t['_why']) : ''; ?></div></div></div></td>
      <td><?php echo $pill($t['_photo'], ['ok' => 'Uploaded', 'approved' => 'Approved', 'needs_update' => 'Needs update', 'requested' => 'Requested', 'none' => 'No photo'][$t['_photo']]); ?></td>
      <td><?php echo $pill($t['_sig'] === 'none' ? 'none' : ($t['_sig'] === 'ok' ? 'ok' : 'needs_update'), $t['_sig'] === 'none' ? 'No signature' : ($t['_sig'] === 'ok' ? 'Signature ok' : 'Needs update')); ?></td>
      <td><?php echo $t['_ready'] && in_array($t['_id'], ['preview_ready'], true) ? $pill('ok', 'Ready to print') : $pill($t['_id'], $t['_photo'] === 'none' ? 'Waiting for photo' : TID_ID_LABELS[$t['_id']]); ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm <?php echo $t['_ready'] ? 'btn-success' : 'btn-outline-secondary'; ?>" href="<?php echo $e(url('admin/tutors/id.php?id=' . (int) $t['id'])); ?>">Preview</a>
        <button type="submit" class="btn btn-sm btn-outline-dark" name="ids[]" value="<?php echo (int) $t['id']; ?>" formnovalidate onclick="tidsOne(this)" <?php echo $t['_ready'] ? '' : 'disabled title="' . $e($t['_why']) . '"'; ?>>Download</button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card p-3 h-100">
    <h6 class="fw-bold">Print file options</h6>
    <div class="d-flex gap-2 flex-wrap mb-2">
      <label class="border rounded-3 px-3 py-2 small"><input type="radio" class="form-check-input me-1" name="layout" value="single" <?php echo ($opt['layout'] ?? 'single') !== 'a4' ? 'checked' : ''; ?>> One card per page</label>
      <label class="border rounded-3 px-3 py-2 small"><input type="radio" class="form-check-input me-1" name="layout" value="a4" <?php echo ($opt['layout'] ?? '') === 'a4' ? 'checked' : ''; ?>> A4 sheets, 8 cards each</label>
    </div>
    <div class="d-flex justify-content-between small border-bottom py-2"><span>Size 50 × 85 mm · 3 mm bleed · front then back</span><span class="badge bg-light text-dark">Print shop</span></div>
    <div class="form-check form-switch py-2 border-bottom small"><input class="form-check-input" type="checkbox" name="marks" value="1" id="om" <?php echo !empty($opt['marks']) ? 'checked' : ''; ?>><label class="form-check-label" for="om">Cut marks</label></div>
    <div class="form-check form-switch py-2 small"><input class="form-check-input" type="checkbox" name="mark_sent" value="1" id="os" <?php echo !empty($opt['mark_sent']) ? 'checked' : ''; ?>><label class="form-check-label" for="os">Mark IDs as "Sent to print" after download</label></div>
    <div class="small text-muted">A4 sheets: print double-sided, flip on the long edge. More than 30 IDs come as a ZIP of PDFs.</div>
  </div></div>
</form>
  <div class="col-lg-5"><div class="card p-3 h-100">
    <h6 class="fw-bold">Last downloads</h6>
    <?php if (!$batches): ?><div class="small text-muted">No downloads yet.</div><?php endif; ?>
    <?php foreach ($batches as $b): ?>
      <div class="d-flex justify-content-between align-items-center border-bottom py-2">
        <div><div class="small fw-semibold"><?php echo $e($b['file_name']); ?></div><div class="small text-muted"><?php echo (int) $b['count']; ?> IDs · by <?php echo $e($b['username'] ?? 'the office'); ?> · <?php echo $e(date('j M Y, H:i', strtotime($b['created_at']))); ?></div></div>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="again"><input type="hidden" name="batch" value="<?php echo (int) $b['id']; ?>"><button class="btn btn-sm btn-outline-secondary"><i class="fas fa-download me-1"></i>Again</button></form>
      </div>
    <?php endforeach; ?>
    <?php $sent = array_filter($rows, fn($t) => $t['_id'] === 'sent_to_print'); if ($sent): ?>
      <form method="POST" class="mt-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="printed">
        <?php foreach ($sent as $t): ?><input type="hidden" name="ids[]" value="<?php echo (int) $t['id']; ?>"><?php endforeach; ?>
        <button class="btn btn-sm btn-outline-dark w-100">Mark all <?php echo count($sent); ?> "Sent to print" IDs as Printed</button></form>
    <?php endif; ?>
  </div></div>
</div>
<script>
(function () {
    var rows = document.querySelectorAll('.tids-row:not([disabled])'), sel = document.getElementById('tidsSel'), all = document.getElementById('tidsAll');
    function upd() { var n = document.querySelectorAll('.tids-row:checked').length; sel.querySelector('span').textContent = n; sel.disabled = !n; }
    rows.forEach(function (r) { r.addEventListener('change', upd); });
    all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); upd(); });
    // One row's Download: send only that tutor.
    window.tidsOne = function (btn) { rows.forEach(function (r) { r.checked = false; }); document.getElementById('tidsScope').value = 'selected'; };
    document.getElementById('tidsForm').addEventListener('submit', function () { setTimeout(function () { location.reload(); }, 4000); });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
