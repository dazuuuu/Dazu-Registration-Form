<?php
// Room & Visitors (secretary portal): the shared diary. Add a room booking or a visitor, mark a visitor Arrived or Left on the day, cancel your OWN entry. No delete. The server refuses a double booking.
// Calendar events with a room in their location show here as read-only bookings. Checked on every request: an active secretary (On leave is fine) with the Room & Visitors switch on (secretary_require).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryDiaryService.php';

$sec = secretary_require('room_visitors');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryDiaryService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$me = secretary_person_name($db, (int) $sec['user_id']);
$filter = isset(SecretaryDiaryService::FILTERS[$_GET['f'] ?? '']) ? (string) $_GET['f'] : 'today';

$errors = []; $in = []; $openSheet = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    if (!in_array($act, ['add_room', 'add_visitor', 'cancel', 'arrived', 'left'], true)) {   // no delete, and nothing else, is accepted
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Not allowed. Diary entries are cancelled, never deleted.');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'The security check failed. Please try again.';
        $in = $_POST;
    } elseif ($act === 'add_room' || $act === 'add_visitor') {
        [$d, $errors] = $act === 'add_room' ? $svc->validateRoom($_POST, $me) : $svc->validateVisitor($_POST);
        if (!$errors) {
            $r = $act === 'add_room' ? $svc->addRoom($sec, $d) : $svc->addVisitor($sec, $d);
            if (!empty($r['ok'])) {
                SessionModel::setFlash('success', $act === 'add_room' ? 'Room booked.' : 'Visitor added.');
                header('Location: index.php?f=' . ($act === 'add_room' ? 'rooms' : 'visitors'));
                exit();
            }
            $errors[] = $r['error'];
        }
        $in = $_POST; $openSheet = $act;
    } else {
        $r = $act === 'cancel' ? $svc->cancel($sec, (int) ($_POST['id'] ?? 0)) : $svc->mark($sec, (int) ($_POST['id'] ?? 0), $act);
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? ($act === 'cancel' ? 'Cancelled.' : 'Saved.') : $h($r['error'] ?? 'Could not save.'));
        header('Location: index.php?f=' . $filter);
        exit();
    }
}

$notReady = false; $rows = []; $rooms = [];
try { $rows = $svc->rows($filter, (int) $sec['id']); $rooms = $svc->roomNames(); } catch (Throwable $e) { $notReady = true; }
$today = SecretaryDiaryService::today();
$dayLabel = static fn(string $d): string => $d === SecretaryDiaryService::today() ? 'Today' : date('D j M', strtotime($d));
$val = static fn(string $k, string $d = '') => $h($GLOBALS['in'][$k] ?? $d);

$page_title = 'Room & Visitors';
$sp_active = 'room_visitors';
ob_start();
require __DIR__ . '/../../components/secretary/enquiries_css.php';   // the sheet styles (se-sheet, se-form, se-grid) are shared
?>
<style>
.rv-box { background:#fff; border-radius:26px; padding:24px 28px; max-width:980px; }
.rv-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
.rv-head h2 { font-size:1.3rem; font-weight:800; margin:0; } .rv-head p { margin:4px 0 0; color:var(--mut); }
.rv-filters { display:flex; gap:8px; overflow-x:auto; padding:14px 0 8px; margin:0 -4px; -webkit-overflow-scrolling:touch; scrollbar-width:none; } .rv-filters::-webkit-scrollbar { display:none; }
.rv-filters a { flex:0 0 auto; display:inline-flex; align-items:center; min-height:44px; padding:0 18px; border-radius:99px; background:#eceeea; color:#2b332f; font-weight:700; margin:0 4px; } .rv-filters a.on { background:var(--gl); color:var(--gd); }
.rv-day { font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--mut); padding:16px 0 4px; border-top:1px solid var(--line); margin-top:8px; }
.rv-item { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--line); } .rv-item:last-child { border-bottom:0; }
.rv-ico { width:40px; height:40px; flex:0 0 auto; border-radius:12px; background:var(--gl); color:var(--gd); display:flex; align-items:center; justify-content:center; }
.rv-ico.cal { background:#eef2fb; color:#2f3f94; }
.rv-main { flex:1; min-width:0; } .rv-main b { display:block; font-weight:700; overflow-wrap:anywhere; } .rv-main small { color:var(--mut); overflow-wrap:anywhere; }
.rv-chip { font-size:.78rem; font-weight:800; padding:5px 13px; border-radius:99px; background:#eceeea; color:#4a524e; white-space:nowrap; } .rv-chip.expected { background:#fff4d6; color:#8a6100; } .rv-chip.arrived { background:var(--gl); color:var(--gd); } .rv-chip.cancelled { background:#fde2e0; color:#b42318; }
.rv-cancelled .rv-main b { text-decoration:line-through; color:#8a948f; }
.rv-act { display:flex; gap:8px; flex-wrap:wrap; } .rv-act form { margin:0; }
.rv-btn { border:1px solid var(--g); background:#fff; color:var(--gd); border-radius:12px; min-height:44px; padding:0 14px; font-weight:700; cursor:pointer; font-size:.88rem; } .rv-btn:hover { background:var(--gl); } .rv-btn.danger { border-color:#c8761a; color:#9a5200; } .rv-btn.solid { background:var(--g); color:#fff; }
.rv-foot { color:var(--mut); font-size:.92rem; margin-top:16px; }
.rv-tabs { display:flex; gap:8px; margin-bottom:14px; } .rv-tabs button { flex:1; min-height:48px; border:1px solid #cbd5d0; background:#fff; border-radius:12px; font-weight:800; } .rv-tabs button.on { background:var(--g); border-color:var(--g); color:#fff; }
.rv-pane { display:none; } .rv-pane.on { display:block; }
@media (max-width: 991.98px) { .rv-box { padding:16px; border-radius:20px; } .rv-item { flex-wrap:wrap; } .rv-act { width:100%; } .rv-act .rv-btn { flex:1; min-height:48px; } }
</style>
<?php if ($notReady): ?><div class="alert alert-warning">The diary tables are not in the database yet. Ask the admin to run the database update (migrations 129 to 132).</div><?php else: ?>
<section class="rv-box">
    <div class="rv-head"><div><h2>Room and Visitors</h2><p>The diary for Nairobi House and Thika</p></div><button type="button" class="se-add" data-open-sheet><i class="fas fa-plus"></i> Add to diary</button></div>
    <div class="rv-filters" role="tablist" aria-label="Filter the diary">
        <?php foreach (SecretaryDiaryService::FILTERS as $k => $l): ?><a href="index.php?f=<?php echo $h($k); ?>" class="<?php echo $filter === $k ? 'on' : ''; ?>"><?php echo $h($l); ?></a><?php endforeach; ?>
    </div>
    <?php if ($errors && !$openSheet): ?><div class="alert alert-danger"><?php echo $h(implode(' ', $errors)); ?></div><?php endif; ?>
    <?php if (!$rows): ?><div class="text-center text-muted py-4"><i class="fas fa-door-open fa-2x mb-2" style="color:#b9c7be"></i><br><strong>Nothing here</strong><br>Room bookings and visitors will show here.</div><?php endif; ?>
    <?php $lastDay = ''; foreach ($rows as $r): if ($r['date'] !== $lastDay): $lastDay = $r['date']; ?><div class="rv-day"><?php echo $h($dayLabel($r['date'])); ?></div><?php endif; ?>
    <div class="rv-item <?php echo $r['status'] === 'cancelled' ? 'rv-cancelled' : ''; ?>">
        <span class="rv-ico <?php echo $r['source'] === 'calendar' ? 'cal' : ''; ?>"><i class="fas <?php echo $r['source'] === 'calendar' ? 'fa-calendar-days' : ($r['kind'] === 'room' ? 'fa-door-open' : 'fa-user'); ?>"></i></span>
        <div class="rv-main"><b><?php echo $h($r['title']); ?></b><small><?php echo $h($r['sub']); ?></small></div>
        <span class="rv-chip <?php echo $h($r['status']); ?>"><?php echo $h($r['label']); ?></span>
        <?php if ($r['can_arrive'] || $r['can_leave'] || $r['can_cancel']): ?>
        <div class="rv-act">
            <?php foreach ([['can_arrive', 'arrived', 'Arrived', 'solid'], ['can_leave', 'left', 'Left', 'solid'], ['can_cancel', 'cancel', 'Cancel', 'danger']] as [$flag, $a, $label, $cls]): if (!$r[$flag]) continue; ?>
            <form method="POST"<?php echo $a === 'cancel' ? ' onsubmit="return confirm(\'Cancel this entry? It stays in the diary as cancelled.\');"' : ''; ?>><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo $h($a); ?>"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <button type="submit" class="rv-btn <?php echo $h($cls); ?>"><?php echo $h($label); ?></button></form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <p class="rv-foot">A room cannot be booked twice at the same time. Visitors are marked Arrived and Left on the day. Bookings that come from the Staff Calendar cannot be changed here.</p>
</section>

<div class="se-sheet<?php echo $openSheet ? ' open' : ''; ?>" id="seSheet" role="dialog" aria-modal="true" aria-labelledby="rvTitle">
    <div class="se-form">
        <div class="se-form-h"><h2 id="rvTitle">Add to diary</h2><button type="button" class="se-x" data-close-sheet aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <?php if ($errors && $openSheet): ?><div class="alert alert-danger"><?php echo $h(implode(' ', $errors)); ?></div><?php endif; ?>
        <div class="rv-tabs" role="tablist"><button type="button" data-tab="room" class="<?php echo $openSheet !== 'add_visitor' ? 'on' : ''; ?>">Room booking</button><button type="button" data-tab="visitor" class="<?php echo $openSheet === 'add_visitor' ? 'on' : ''; ?>">Visitor</button></div>
        <form method="POST" action="index.php" class="rv-pane <?php echo $openSheet !== 'add_visitor' ? 'on' : ''; ?>" data-pane="room" autocomplete="off" novalidate>
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="add_room">
            <div class="se-grid">
                <div><label for="r_room">Room *</label><input id="r_room" name="room" list="rvRooms" maxlength="80" required value="<?php echo $val('room'); ?>" placeholder="For example Room 406B">
                    <datalist id="rvRooms"><?php foreach ($rooms as $rm): ?><option value="<?php echo $h($rm); ?>"><?php endforeach; ?></datalist></div>
                <div><label for="r_branch">Branch *</label><select id="r_branch" name="branch"><?php foreach (SecretaryDiaryService::BRANCHES as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo ($in['branch'] ?? 'nairobi') === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?></select></div>
                <div><label for="r_date">Date *</label><input id="r_date" name="entry_date" type="date" required value="<?php echo $val('entry_date', $today); ?>"></div>
                <div><label for="r_by">Booked by</label><input id="r_by" name="booked_by" maxlength="120" value="<?php echo $val('booked_by', $me); ?>"></div>
                <div><label for="r_start">Start time *</label><input id="r_start" name="start_time" type="time" required value="<?php echo $val('start_time'); ?>"></div>
                <div><label for="r_end">End time *</label><input id="r_end" name="end_time" type="time" required value="<?php echo $val('end_time'); ?>"></div>
                <div class="full"><label for="r_purpose">Purpose *</label><input id="r_purpose" name="purpose" maxlength="300" required value="<?php echo $val('purpose'); ?>"></div>
            </div>
            <div class="se-form-f"><button type="submit" class="se-save"><i class="fas fa-check me-1"></i> Book the room</button><button type="button" class="se-cancel" data-close-sheet>Cancel</button></div>
        </form>
        <form method="POST" action="index.php" class="rv-pane <?php echo $openSheet === 'add_visitor' ? 'on' : ''; ?>" data-pane="visitor" autocomplete="off" novalidate>
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="add_visitor">
            <div class="se-grid">
                <div><label for="v_name">Visitor’s name *</label><input id="v_name" name="person_name" maxlength="120" required value="<?php echo $val('person_name'); ?>"></div>
                <div><label for="v_sees">Who they come to see *</label><input id="v_sees" name="sees_who" maxlength="120" required value="<?php echo $val('sees_who'); ?>"></div>
                <div><label for="v_date">Date *</label><input id="v_date" name="entry_date" type="date" required value="<?php echo $val('entry_date', $today); ?>"></div>
                <div><label for="v_time">Time expected *</label><input id="v_time" name="start_time" type="time" required value="<?php echo $val('start_time'); ?>"></div>
                <div class="full"><label for="v_purpose">Purpose *</label><input id="v_purpose" name="purpose" maxlength="300" required value="<?php echo $val('purpose'); ?>"></div>
            </div>
            <div class="se-form-f"><button type="submit" class="se-save"><i class="fas fa-check me-1"></i> Add the visitor</button><button type="button" class="se-cancel" data-close-sheet>Cancel</button></div>
        </form>
    </div>
</div>
<script>
(function () {
  var sheet = document.getElementById('seSheet'); if (!sheet) return;
  function open() { sheet.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function close() { sheet.classList.remove('open'); document.body.style.overflow = ''; }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-open-sheet]')) open(); if (e.target.closest('[data-close-sheet]')) close(); if (e.target === sheet) close();
    var tb = e.target.closest('[data-tab]'); if (tb) { sheet.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('on', b === tb); }); sheet.querySelectorAll('[data-pane]').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === tb.dataset.tab); }); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  if (sheet.classList.contains('open')) document.body.style.overflow = 'hidden';
})();
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
