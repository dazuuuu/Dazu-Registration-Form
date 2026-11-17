<?php
// Admin: the Secretaries list. Every switch saves the moment it is changed (switch.php); Edit, Reset password and Suspend are here.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryAdminService.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

use Models\Connection;

secretary_admin_require();   // the normal admin only: everyone else, the super admin included, gets a plain 403

$pdo = Connection::getInstance()->getConnection();
$admin = new SecretaryAdminService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$by = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'The security check failed. Please try again.');
    } else {
        switch ($_POST['action'] ?? '') {
            case 'reset':
                $r = $admin->resetPassword($id, $by);
                if (empty($r['ok'])) SessionModel::setFlash('error', $h($r['error'] ?? 'Could not reset.'));
                elseif (!empty($r['email_sent'])) SessionModel::setFlash('success', 'A new temporary password was emailed. She must choose her own password when she signs in.');
                else SessionModel::setFlash('warning', 'The new password was made but the email was not sent (' . $h($r['email_error'] ?? 'unknown reason') . ').');
                break;
            case 'suspend':
            case 'unsuspend':
                $r = $admin->setSuspended($id, $_POST['action'] === 'suspend', $by);
                SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok'])
                    ? ($_POST['action'] === 'suspend' ? 'Suspended. She cannot sign in.' : 'The suspension was lifted.') : $h($r['error'] ?? 'Could not save.'));
                break;
        }
    }
    header('Location: index.php');
    exit();
}

$rows = [];
$notReady = false;
try {
    $rows = $admin->all();
} catch (Throwable $e) {
    $notReady = true;   // migrations 122, 123 and 124 not run yet
}

$branches = secretary_branches();
$statuses = secretary_statuses();
$initials = static function (string $name): string {
    $p = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(mb_substr($p[0] ?? '?', 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$page_title = 'Secretaries';
$breadcrumbs = [['label' => 'Secretaries', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.sec-wrap { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; }
.sec-btn { background:var(--g); border:0; color:#fff; font-weight:800; border-radius:12px; padding:.7rem 1.3rem; min-height:44px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
.sec-btn:hover { background:var(--gd); color:#fff; }
.sec-tablewrap { background:#fff; border:1px solid #d9e6de; border-radius:16px; margin-top:16px; overflow:hidden; }
.sec-table { width:100%; border-collapse:collapse; }
.sec-table th { background:var(--gl); color:var(--gd); font-size:.74rem; text-transform:uppercase; letter-spacing:.04em; padding:10px 12px; text-align:left; white-space:nowrap; }
.sec-table td { padding:12px; border-top:1px solid #e6efe9; vertical-align:middle; }
.sec-av { width:48px; height:48px; border-radius:50%; background:var(--gl); color:var(--gd); font-weight:800; display:flex; align-items:center; justify-content:center; object-fit:cover; flex:0 0 auto; }
.sec-who { display:flex; align-items:center; gap:12px; min-width:0; }
.sec-name { font-weight:800; color:#111827; }
.sec-meta { font-size:.8rem; color:#6b7280; overflow-wrap:anywhere; }
.sec-pill { display:inline-block; font-size:.72rem; font-weight:800; padding:2px 10px; border-radius:99px; background:var(--gl); color:var(--gd); }
.sec-pill.on_leave { background:#fff4d6; color:#8a6100; } .sec-pill.left { background:#eee; color:#555; } .sec-pill.suspended { background:#fde2e0; color:#b42318; }
.sec-select { min-height:44px; border:1px solid #cbd5d0; border-radius:10px; padding:0 10px; background:#fff; font-weight:600; max-width:100%; }
.sec-select:focus-visible, .sec-sw:focus-visible { outline:3px solid rgba(4,140,68,.4); outline-offset:2px; }
.sec-swlab { display:inline-flex; align-items:center; gap:10px; min-height:44px; cursor:pointer; font-weight:600; font-size:.88rem; margin:0; }
.sec-sw { appearance:none; -webkit-appearance:none; flex:0 0 auto; width:52px; height:30px; border-radius:15px; background:#cbd5d0; position:relative; cursor:pointer; transition:background .15s; margin:0; }
.sec-sw::after { content:''; position:absolute; top:3px; left:3px; width:24px; height:24px; border-radius:50%; background:#fff; transition:transform .15s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
.sec-sw:checked { background:var(--g); } .sec-sw:checked::after { transform:translateX(22px); }
.sec-sw:disabled, .sec-select:disabled { opacity:.55; cursor:progress; }
.sec-act { display:flex; gap:8px; flex-wrap:wrap; }
.sec-act form { margin:0; }
.sec-abtn { border:1px solid var(--g); color:var(--gd); background:#fff; border-radius:10px; min-height:44px; padding:0 14px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:.88rem; }
.sec-abtn:hover { background:var(--gl); color:var(--gd); }
.sec-abtn.danger { border-color:#b42318; color:#b42318; } .sec-abtn.danger:hover { background:#fde2e0; }
.sec-last { font-size:.72rem; color:#6b7280; margin-top:3px; }
.sec-info { display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-top:18px; }
.sec-info > div { background:#fff; border:1px solid #d9e6de; border-left:4px solid var(--g); border-radius:12px; padding:14px 16px; font-size:.88rem; }
.sec-info h3 { font-size:.95rem; font-weight:800; color:var(--gd); margin:0 0 6px; }
.sec-info p { margin:0 0 6px; } .sec-info p:last-child { margin:0; }
.sec-toast { position:fixed; left:50%; bottom:18px; transform:translateX(-50%); background:#111827; color:#fff; padding:10px 16px; border-radius:12px; font-size:.88rem; z-index:2000; max-width:92vw; display:none; }
.sec-toast.err { background:#b42318; }
@media (max-width: 991.98px) {
  .sec-tablewrap { background:transparent; border:0; }
  .sec-table, .sec-table tbody, .sec-table tr, .sec-table td { display:block; }
  .sec-table thead { display:none; }
  .sec-table tr { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:14px; margin-bottom:14px; }
  .sec-table td { border:0; padding:8px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; min-height:48px; }
  .sec-table td::before { content:attr(data-label); font-size:.74rem; font-weight:800; color:#6b7280; text-transform:uppercase; letter-spacing:.04em; flex:0 0 38%; }
  .sec-table td.sec-first { display:block; border-bottom:1px solid #e6efe9; padding-bottom:12px; margin-bottom:4px; }
  .sec-table td.sec-first::before, .sec-table td.sec-actions::before { display:none; }
  .sec-table td.sec-actions { display:block; padding-top:10px; }
  .sec-act { display:grid; grid-template-columns:1fr; gap:10px; }
  .sec-abtn { justify-content:center; min-height:48px; width:100%; }
  .sec-select { min-height:48px; flex:1 1 auto; }
  .sec-info { grid-template-columns:1fr; }
}
</style>
CSS;
ob_start();
?>
<div class="sec-wrap" id="secWrap" data-csrf="<?php echo $h(csrf_token()); ?>" data-url="switch.php">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="text-muted"><?php echo $notReady ? '' : count($rows) . ' secretar' . (count($rows) === 1 ? 'y' : 'ies'); ?></span>
    <div class="d-flex gap-2 flex-wrap">
        <?php $blogWaiting = (new SecretaryBlogService($pdo))->waitingCount(); ?>
        <a href="tasks.php" class="sec-abtn"><i class="fas fa-list-check"></i> Secretary tasks</a>
        <a href="diary.php" class="sec-abtn"><i class="fas fa-door-open"></i> Diary</a>
        <a href="activity.php" class="sec-abtn"><i class="fas fa-clock-rotate-left"></i> Activity</a>
        <a href="enquiries.php" class="sec-abtn"><i class="fas fa-inbox"></i> Enquiries</a>
        <a href="blog-approvals.php" class="sec-abtn"><i class="fas fa-newspaper"></i> Blog approvals<?php echo $blogWaiting > 0 ? ' (' . (int) $blogWaiting . ' waiting)' : ''; ?></a>
        <a href="create.php" class="sec-btn"><i class="fas fa-user-plus"></i> Add secretary</a>
    </div>
</div>
<?php if ($notReady): ?>
    <div class="alert alert-warning mt-3">The secretary tables are not in the database yet. Run the database update (migrations 122, 123 and 124) first.</div>
<?php elseif (!$rows): ?>
    <div class="alert alert-light border mt-3">No secretaries yet. Tap <strong>Add secretary</strong> to add the first one.</div>
<?php else: ?>
<div class="sec-tablewrap">
<table class="sec-table">
    <thead><tr><th>Secretary</th><th>ID number</th><th>Status</th><th>Blog approval</th><th>Calendar</th><th>ID card</th><th>Enquiries</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $sw = $r['switches']; $rid = (int) $r['id']; $name = (string) $r['full_name']; ?>
    <tr data-id="<?php echo $rid; ?>">
        <td class="sec-first" data-label="Secretary">
            <div class="sec-who">
                <?php if (!empty($r['photo_path'])): ?><img class="sec-av" src="<?php echo $h($r['photo_path']); ?>" alt="">
                <?php else: ?><div class="sec-av" aria-hidden="true"><?php echo $h($initials($name)); ?></div><?php endif; ?>
                <div style="min-width:0">
                    <div class="sec-name"><?php echo $h($name); ?><?php if ($r['suspended']): ?> <span class="sec-pill suspended">Suspended</span><?php endif; ?><?php if ($r['status'] === 'on_leave'): ?> <span class="sec-pill on_leave">On leave</span><?php endif; ?></div>
                    <div class="sec-meta"><?php echo $h($r['job_title']); ?> · <?php echo $h($branches[$r['branch']] ?? $r['branch']); ?></div>
                    <div class="sec-meta"><?php echo $h($r['email']); ?><?php echo !empty($r['added_by_name']) ? ' · Added by ' . $h($r['added_by_name']) : ''; ?></div>
                    <div class="sec-last" data-last><?php if ($r['last_change']): ?>Last change by <?php echo $h($r['last_change']['by_name']); ?> · <?php echo $h(date('j M, g:i a', strtotime((string) $r['last_change']['changed_at']))); ?><?php endif; ?></div>
                </div>
            </div>
        </td>
        <td data-label="ID number"><span class="sec-meta" style="font-weight:700;color:#111827"><?php echo $h($r['staff_no']); ?></span></td>
        <td data-label="Status">
            <select class="sec-select" data-key="status" data-prev="<?php echo $h($r['status']); ?>" aria-label="Status of <?php echo $h($name); ?>">
                <?php foreach ($statuses as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo $r['status'] === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="Blog approval">
            <label class="sec-swlab"><input type="checkbox" class="sec-sw" data-key="blogs_approval" data-on="on" data-off="off" data-text-on="Needed" data-text-off="Not needed" <?php echo ($sw['blogs_approval'] ?? 'off') === 'on' ? 'checked' : ''; ?> aria-label="Blog approval for <?php echo $h($name); ?>"><span data-text><?php echo ($sw['blogs_approval'] ?? 'off') === 'on' ? 'Needed' : 'Not needed'; ?></span></label>
        </td>
        <td data-label="Calendar">
            <select class="sec-select" data-key="staff_calendar" data-prev="<?php echo $h($sw['staff_calendar'] ?? 'off'); ?>" aria-label="Calendar level for <?php echo $h($name); ?>">
                <?php foreach (['edit' => 'Full', 'view' => 'View only', 'off' => 'Off'] as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo ($sw['staff_calendar'] ?? 'off') === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?>
            </select>
        </td>
        <td data-label="ID card">
            <label class="sec-swlab"><input type="checkbox" class="sec-sw" data-key="id_card" data-on="on" data-off="off" data-text-on="On" data-text-off="Off" <?php echo ($sw['id_card'] ?? 'off') === 'on' ? 'checked' : ''; ?> aria-label="ID card for <?php echo $h($name); ?>"><span data-text><?php echo ($sw['id_card'] ?? 'off') === 'on' ? 'On' : 'Off'; ?></span></label>
        </td>
        <td data-label="Enquiries">
            <label class="sec-swlab"><input type="checkbox" class="sec-sw" data-key="enquiries" data-on="on" data-off="off" data-text-on="On" data-text-off="Off" <?php echo ($sw['enquiries'] ?? 'off') === 'on' ? 'checked' : ''; ?> aria-label="Enquiries for <?php echo $h($name); ?>"><span data-text><?php echo ($sw['enquiries'] ?? 'off') === 'on' ? 'On' : 'Off'; ?></span></label>
        </td>
        <td class="sec-actions" data-label="Actions">
            <div class="sec-act">
                <a class="sec-abtn" href="create.php?id=<?php echo $rid; ?>"><i class="fas fa-pen"></i> Edit</a>
                <form method="POST" onsubmit="return confirm('Make a new temporary password and email it to her? Her old password stops working and she must choose a new one at her next sign-in.');">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?php echo $rid; ?>">
                    <button type="submit" class="sec-abtn"><i class="fas fa-key"></i> Reset password</button>
                </form>
                <form method="POST" onsubmit="return confirm('<?php echo $r['suspended'] ? 'Lift the suspension so she can sign in again?' : 'Suspend her? She cannot sign in until you lift it.'; ?>');">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo $r['suspended'] ? 'unsuspend' : 'suspend'; ?>"><input type="hidden" name="id" value="<?php echo $rid; ?>">
                    <button type="submit" class="sec-abtn <?php echo $r['suspended'] ? '' : 'danger'; ?>"><i class="fas fa-<?php echo $r['suspended'] ? 'lock-open' : 'ban'; ?>"></i> <?php echo $r['suspended'] ? 'Lift suspension' : 'Suspend'; ?></button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<div class="sec-info">
    <div><h3>Calendar levels</h3>
        <p><strong>Full</strong>: the same as a tutor. She adds her own birthday and her own events, is in the birthday gifts list, and gets the same notices.</p>
        <p><strong>View only</strong>: she sees events and adds her own birthday. She cannot add, change or delete events, and is not in the gifts list.</p>
        <p><strong>Off</strong>: no access to the Staff Calendar.</p></div>
    <div><h3>Blog approval</h3>
        <p><strong>Needed</strong>: her blog posts wait for you to approve them before they are published.</p>
        <p><strong>Not needed</strong>: her posts are published straight away. Use this only for a secretary you trust.</p></div>
    <div><h3>Never deleted</h3>
        <p>A secretary is never deleted. When she leaves, set her to <strong>Left</strong>: she loses access at once, and her blogs, enquiries and history stay.</p>
        <p><strong>On leave</strong> is only a badge: she can still sign in and use everything her switches allow.</p>
        <p><strong>Suspend</strong> also blocks her login at once, and you can lift it later.</p></div>
</div>
<div class="sec-toast" id="secToast" role="status" aria-live="polite"></div>
</div>
<script>
(function () {
  var wrap = document.getElementById('secWrap'), toast = document.getElementById('secToast'), tm;
  if (!wrap) return;
  function say(msg, bad) { toast.textContent = msg; toast.className = 'sec-toast' + (bad ? ' err' : ''); toast.style.display = 'block'; clearTimeout(tm); tm = setTimeout(function () { toast.style.display = 'none'; }, bad ? 5000 : 2500); }
  function save(el, value, undo) {
    var tr = el.closest('tr'), body = new URLSearchParams({ csrf_token: wrap.dataset.csrf, id: tr.dataset.id, key: el.dataset.key, value: value });
    el.disabled = true;
    fetch(wrap.dataset.url, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Could not save. Please reload the page.' }; }); })
      .then(function (d) {
        el.disabled = false;
        if (!d.ok) { undo(); say(d.error || 'Could not save.', true); return; }
        if (el.dataset.key === 'status') { say('Saved.'); if (d.changed) location.reload(); return; }
        var last = tr.querySelector('[data-last]'); if (last && d.changed) last.textContent = 'Last change by ' + d.by + ' · ' + d.at;
        say('Saved.');
      })
      .catch(function () { el.disabled = false; undo(); say('No connection. Nothing was saved.', true); });
  }
  wrap.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.classList.contains('sec-sw')) {
      var on = el.checked, t = el.parentNode.querySelector('[data-text]');
      t.textContent = on ? el.dataset.textOn : el.dataset.textOff;
      save(el, on ? el.dataset.on : el.dataset.off, function () { el.checked = !on; t.textContent = !on ? el.dataset.textOn : el.dataset.textOff; });
    } else if (el.classList.contains('sec-select')) {
      var prev = el.dataset.prev;
      if (el.dataset.key === 'status' && el.value === 'left' && !confirm('Set her to Left? She loses access at once. Her records are kept and she is never deleted.')) { el.value = prev; return; }
      var now = el.value;
      save(el, now, function () { el.value = prev; el.dataset.prev = prev; });
      el.dataset.prev = now;
    }
  });
})();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
