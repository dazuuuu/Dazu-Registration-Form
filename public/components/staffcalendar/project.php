<?php
// public/components/staffcalendar/project.php — the project page (BOTH portals): progress ring, timeline, checklist of tasks, people, added by.
// boot.php checks the login and the real role first. Who may do what is checked again on the server for every action (StaffProjects):
//  the owner and the super admin add, edit, delete and give tasks and change any task; a person given a task can tick it or change its status; everyone else only views.
$page_title = 'Project';
$sa_custom_header = true;
require __DIR__ . '/boot.php';
require_once __DIR__ . '/../../../app/v1/services/StaffProjects.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$calUrl = url($sc_portal . '/staffcalendar/index.php');
if (!$sc_ready || !spj_installed($db)) {
    $content = '<div class="alert alert-warning">Projects are not installed yet. Ask the office to run System Updates (migration 118).</div>';
    return;
}
$id = (int) ($_GET['id'] ?? 0);
$now = scal_now();
$pj = $id > 0 ? spj_view($db, $actor, $id, $now) : null;
if (!$pj) {
    http_response_code(404);
    $content = '<div class="alert alert-warning">That project was not found. <a href="' . $e($calUrl) . '">Back to the Staff Calendar</a></div>';
    return;
}
$page_title = $pj['title'];
$formUrl = url($sc_portal . '/staffcalendar/action.php');
$st = $pj['stats'];
$people = array_values(sbd_people($db));
$ava = function (array $p, string $cls) use ($e): string { return '<span class="' . $cls . '">' . $e($p['initials']) . ($p['photo'] !== '' ? '<img src="' . $e($p['photo']) . '" alt="" onerror="this.remove()">' : '') . '</span>'; };
$fmtD = fn(string $d) => $d === '' ? '' : (new DateTime($d, scal_tz()))->format('D j M');
$stateCls = ['On track' => 'ok', 'Behind' => 'bad', 'Done' => 'done'][$st['state']];
$circ = 2 * M_PI * 68;
$n = max(1, count($pj['days']));
$tasksJson = [];
foreach ($pj['tasks'] as $t) $tasksJson[$t['id']] = ['id' => $t['id'], 't' => $t['t'], 'who' => $t['who'], 'start' => $t['start'], 'due' => $t['due'], 'status' => $t['status']];

ob_start();
?>
<style>
.pj{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--ink:#1d1b1c;--mut:#6b7067;--line:#e6e9e4;--red:#C93A2E;color:var(--ink);font-family:inherit;max-width:1400px}
.pj *{box-sizing:border-box}.pj [hidden]{display:none!important}
.pj-top{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin:4px 0 16px}
.pj-top h1{font-size:1.9rem;font-weight:800;margin:0;display:flex;gap:14px;align-items:center;flex-wrap:wrap}
.pj-pillp{background:#373435;color:#fff;border-radius:999px;padding:5px 14px;font-size:.84rem;font-weight:700;display:inline-flex;gap:7px;align-items:center}
.pj-acts{display:flex;gap:10px;flex-wrap:wrap}
.pj-btn{background:#fff;color:var(--ink);border:1px solid var(--line);border-radius:14px;padding:10px 20px;font-weight:700;font-size:.9rem;cursor:pointer;text-decoration:none;font-family:inherit;display:inline-flex;gap:8px;align-items:center;justify-content:center}
.pj-btn:hover{background:var(--gl);color:var(--gd)}.pj-btn.go{background:var(--g);border-color:var(--g);color:#fff}.pj-btn.go:hover{background:var(--gd);color:#fff}.pj-btn.danger{color:#b42318;border-color:#f1c9c4}
.pj-grid{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:20px;align-items:start}.pj-main,.pj-side{min-width:0}
.pj-card{background:#fff;border-radius:20px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:22px 26px;margin-bottom:20px}
.pj-card h2{font-size:1.15rem;font-weight:700;margin:0}
.pj-sum{display:flex;gap:26px;align-items:center;flex-wrap:wrap}
.pj-ring{position:relative;width:160px;height:160px;flex:none}.pj-ring svg{width:100%;height:100%}.pj-ring b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800}
.pj-sum small{display:block;color:var(--mut);font-size:.84rem;text-transform:uppercase;letter-spacing:.04em}
.pj-big{font-size:2rem;font-weight:800;line-height:1.15;margin:2px 0 6px}.pj-sum p{margin:2px 0;color:var(--mut);font-size:.9rem}
.pj-sum .grow{flex:1;min-width:230px}
.pj-state{border-radius:999px;padding:6px 16px;font-weight:800;font-size:.86rem;white-space:nowrap}.pj-state.ok{background:var(--gl);color:var(--gd)}.pj-state.bad{background:#fdeceb;color:var(--red)}.pj-state.done{background:var(--g);color:#fff}
.pj-tlh{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:baseline;margin-bottom:12px}.pj-tlh span{color:var(--mut);font-size:.86rem}
.pj-tlwrap{overflow-x:auto;padding-bottom:6px}
.pj-tl{position:relative;display:grid;grid-template-columns:repeat(var(--n),minmax(96px,1fr));grid-auto-rows:minmax(40px,auto);row-gap:10px;min-width:calc(var(--n) * 96px)}
.pj-th{font-size:.82rem;font-weight:600;color:#4b524a;border-bottom:1px solid var(--line);border-left:1px solid var(--line);padding:6px 10px;grid-row:1;align-self:end}
.pj-th.today{color:var(--gd);border-bottom:2px solid var(--g)}
.pj-bar{border-radius:10px;color:#fff;font-weight:600;font-size:.84rem;line-height:1.25;padding:8px 11px;white-space:normal;word-break:break-word;align-self:center}
.pj-bar.done{opacity:.55;text-decoration:line-through}.pj-bar.none{font-weight:500}
.pj-today{position:absolute;top:30px;bottom:0;width:0;border-left:2px dashed var(--g);pointer-events:none}
.pj-ch{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px}
.pj-task{display:grid;grid-template-columns:30px minmax(0,1fr) 150px 96px 124px auto;gap:12px;align-items:center;padding:13px 0;border-top:1px solid var(--line)}
.pj-ck{width:24px;height:24px;accent-color:var(--g);cursor:pointer}.pj-ck:disabled{cursor:not-allowed;opacity:.6}
.pj-tt{font-size:1.02rem;word-break:break-word}.pj-tt.done{text-decoration:line-through;color:var(--mut)}
.pj-wh{display:flex;gap:8px;align-items:center;color:#4b524a;font-size:.92rem;min-width:0}
.pj-av{width:34px;height:34px;border-radius:50%;background:#373435;color:#fff;font-size:.68rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:none;position:relative;overflow:hidden}
.pj-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.pj-due{color:var(--mut);font-size:.88rem}.pj-due.over{color:var(--red);font-weight:700}
.pj-pill,select.pj-st{border-radius:999px;padding:5px 12px;font-size:.8rem;font-weight:700;border:0;font-family:inherit;text-align:center;appearance:none;-webkit-appearance:none}
select.pj-st{cursor:pointer;padding-right:12px}
.st-todo{background:#eee;color:#555}.st-in_progress{background:#fdf0d5;color:#9a6700}.st-done{background:var(--gl);color:var(--gd)}
.pj-ic{border:0;background:transparent;color:var(--mut);cursor:pointer;width:30px;height:30px;border-radius:50%}.pj-ic:hover{background:#f1f3ef;color:var(--ink)}
.pj-note{color:var(--mut);font-size:.86rem;margin-top:12px}
.pj-side h3{font-size:.74rem;letter-spacing:.14em;font-weight:700;color:var(--mut);text-transform:uppercase;margin:0 0 12px}
.pj-mini{background:#373435;color:#fff;border-radius:10px;padding:5px 12px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:.86rem}.pj-mini span{margin-left:auto;background:var(--g);border-radius:999px;padding:0 9px;font-size:.7rem;font-weight:800;line-height:18px}
.pj-side p{color:var(--mut);font-size:.88rem;margin:10px 0 0;line-height:1.45}
.pj-pp{display:flex;gap:12px;align-items:center;padding:8px 0}.pj-pp b{flex:1;font-weight:500;font-size:1rem}.pj-pp small{color:var(--mut)}
.pj-who{display:flex;gap:14px;align-items:center}.pj-who .pj-av{width:48px;height:48px;font-size:.9rem}.pj-who b{display:block;font-size:1.05rem}.pj-who small{color:var(--mut)}
dialog.pj-dlg{border:0;border-radius:18px;padding:22px 24px;width:min(480px,calc(100vw - 24px));box-shadow:0 20px 60px rgba(0,0,0,.35);color:var(--ink)}
dialog.pj-dlg::backdrop{background:rgba(15,25,18,.5)}
.pj-dlg h4{font-size:1.15rem;font-weight:700;margin:0 0 12px}
.pj-dlg label{display:block;font-size:.8rem;color:var(--mut);margin:10px 0 4px}
.pj-dlg input,.pj-dlg select{width:100%;border:1px solid #d8ddd5;border-radius:10px;padding:9px 12px;font-family:inherit;font-size:.95rem;background:#fff}
.pj-dlg .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pj-dlg .row{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}
.pj-err{background:#fdeceb;color:var(--red);border-radius:10px;padding:8px 12px;font-size:.88rem;margin-bottom:8px}
@media (max-width:991.98px){.pj-grid{grid-template-columns:minmax(0,1fr)}}
@media (max-width:767.98px){
  .pj-top h1{font-size:1.45rem}.pj-card{padding:16px;border-radius:16px}.pj-acts{width:100%}.pj-acts .pj-btn{flex:1}
  .pj-sum{flex-direction:column;align-items:flex-start}.pj-ring{width:130px;height:130px;align-self:center}.pj-big{font-size:1.6rem}
  .pj-task{grid-template-columns:30px minmax(0,1fr) auto;grid-template-areas:"ck tt ac" ". wh wh" ". due st";row-gap:6px;gap:8px}
  .pj-task .pj-ck{grid-area:ck}.pj-task .pj-tt{grid-area:tt}.pj-task .pj-wh{grid-area:wh}.pj-task .pj-due{grid-area:due;align-self:center}.pj-task .pj-stc{grid-area:st;justify-self:end}.pj-task .pj-ac{grid-area:ac}
  .pj-dlg .g2{grid-template-columns:1fr}
}
</style>
<div class="pj">
    <div class="pj-top">
        <h1><span class="pj-pillp"><i class="fas fa-diagram-project"></i> Project</span><?php echo $e($pj['title']); ?></h1>
        <div class="pj-acts">
            <?php if ($pj['can_manage']): ?><a class="pj-btn" href="<?php echo $e($calUrl . '?' . http_build_query(['view' => 'month', 'd' => $pj['start'], 'edit' => $pj['id']])); ?>"><i class="fas fa-pen"></i> Edit</a><?php endif; ?>
            <a class="pj-btn" href="<?php echo $e($calUrl . '?' . http_build_query(['view' => 'month', 'd' => $pj['start']])); ?>">Open in calendar</a>
        </div>
    </div>
    <?php if ($pj['description'] !== ''): ?><p style="color:var(--mut);margin:-6px 0 16px;white-space:pre-line"><?php echo $e($pj['description']); ?></p><?php endif; ?>

    <div class="pj-grid">
    <div class="pj-main">
        <section class="pj-card pj-sum" aria-label="Project progress">
            <div class="pj-ring" role="img" aria-label="<?php echo (int) $st['percent']; ?> percent done">
                <svg viewBox="0 0 160 160"><circle cx="80" cy="80" r="68" fill="none" stroke="#ececea" stroke-width="16"/>
                    <circle cx="80" cy="80" r="68" fill="none" stroke="#048C44" stroke-width="16" stroke-linecap="round" stroke-dasharray="<?php echo round($circ * $st['percent'] / 100, 2); ?> <?php echo round($circ, 2); ?>" transform="rotate(-90 80 80)"/></svg>
                <b><?php echo (int) $st['percent']; ?>%</b>
            </div>
            <div class="grow"><small>Project progress</small><div class="pj-big"><?php echo (int) $st['done']; ?> of <?php echo (int) $st['total']; ?> tasks done</div>
                <p><?php echo $e($pj['range_text']); ?> · Owner: <?php echo $e($pj['owner']); ?></p>
                <p>Progress is worked out from the ticked checklist items.</p></div>
            <span class="pj-state <?php echo $stateCls; ?>"><?php echo $e($st['state']); ?></span>
        </section>

        <section class="pj-card" aria-label="Timeline">
            <div class="pj-tlh"><h2>Timeline</h2><span><?php echo $e($pj['today_text']); ?></span></div>
            <div class="pj-tlwrap"><div class="pj-tl" style="--n:<?php echo $n; ?>">
                <?php foreach ($pj['days'] as $i => $dy): ?><div class="pj-th<?php echo $dy['today'] ? ' today' : ''; ?>" style="grid-column:<?php echo $i + 1; ?>"><?php echo $e($dy['dow'] . ' ' . $dy['day']); ?></div><?php endforeach; ?>
                <?php foreach ($pj['bars'] as $i => $b): ?><div class="pj-bar<?php echo ($b['done'] ? ' done' : '') . ($b['none'] ? ' none' : ''); ?>" style="grid-column:<?php echo (int) $b['from']; ?> / <?php echo (int) $b['to'] + 1; ?>;grid-row:<?php echo $i + 2; ?>;background:<?php echo $e($b['color']); ?>" title="<?php echo $e($b['title']); ?>"><?php echo $e($b['title']); ?></div><?php endforeach; ?>
                <?php if ($pj['today_idx'] !== null): ?><i class="pj-today" style="left:calc(<?php echo $pj['today_idx']; ?> / <?php echo $n; ?> * 100% + 1px)" title="Today"></i><?php endif; ?>
            </div></div>
            <?php if (!$pj['bars']): ?><p class="pj-note">No tasks yet.</p><?php endif; ?>
            <?php if ($pj['cap']): ?><p class="pj-note">Only the first 62 days are drawn.</p><?php endif; ?>
        </section>

        <section class="pj-card" aria-label="Checklist">
            <div class="pj-ch"><h2>Checklist</h2><?php if ($pj['can_manage']): ?><button type="button" class="pj-btn" id="pj-add"><i class="fas fa-plus"></i> Add task</button><?php endif; ?></div>
            <?php if (!$pj['tasks']): ?><p class="pj-note" style="margin-top:0">No tasks yet.<?php echo $pj['can_manage'] ? ' Use “Add task”.' : ''; ?></p><?php endif; ?>
            <?php foreach ($pj['tasks'] as $t): $who = $t['who'] > 0 ? ['initials' => $t['initials'], 'photo' => $t['photo']] : null; ?>
            <div class="pj-task" data-task="<?php echo (int) $t['id']; ?>">
                <input type="checkbox" class="pj-ck" data-task="<?php echo (int) $t['id']; ?>" aria-label="Done: <?php echo $e($t['t']); ?>"<?php echo $t['done'] ? ' checked' : ''; ?><?php echo $t['can_status'] ? '' : ' disabled'; ?>>
                <span class="pj-tt<?php echo $t['done'] ? ' done' : ''; ?>"><?php echo $e($t['t']); ?></span>
                <span class="pj-wh"><?php if ($who): echo $ava($who, 'pj-av'); ?><?php echo $e($t['who_first']); else: ?><span style="color:var(--mut)">Nobody yet</span><?php endif; ?></span>
                <span class="pj-due<?php echo $t['overdue'] ? ' over' : ''; ?>"><?php echo $e($fmtD($t['due'])); ?></span>
                <span class="pj-stc"><?php if ($t['can_status']): ?><select class="pj-st st-<?php echo $e($t['status']); ?>" data-task="<?php echo (int) $t['id']; ?>" aria-label="Status"><?php foreach (SPJ_STATUS as $k => $l): ?><option value="<?php echo $e($k); ?>"<?php echo $k === $t['status'] ? ' selected' : ''; ?>><?php echo $e($l); ?></option><?php endforeach; ?></select><?php else: ?><span class="pj-pill st-<?php echo $e($t['status']); ?>"><?php echo $e($t['status_label']); ?></span><?php endif; ?></span>
                <span class="pj-ac"><?php if ($pj['can_manage']): ?><button type="button" class="pj-ic pj-edit" data-task="<?php echo (int) $t['id']; ?>" aria-label="Edit task"><i class="fas fa-pen"></i></button><?php endif; ?></span>
            </div>
            <?php endforeach; ?>
            <p class="pj-note">Each person is notified when a task is given to them, and again the day before it is due.</p>
            <div class="pj-err" id="pj-msg" role="alert" hidden></div>
        </section>
    </div>
    <aside class="pj-side">
        <section class="pj-card"><h3>On the calendar it looks like</h3><div class="pj-mini"><?php echo $e($pj['title']); ?><span><?php echo (int) $st['percent']; ?>%</span></div>
            <p>A long bar across the days, with the progress on it. Click it to open this page.</p></section>
        <section class="pj-card"><h3>People</h3>
            <?php if (!$pj['people']): ?><p style="margin:0">Nobody has a task yet.</p><?php endif; ?>
            <?php foreach ($pj['people'] as $p): ?><div class="pj-pp"><?php echo $ava($p, 'pj-av'); ?><b><?php echo $e($p['name']); ?></b><small><?php echo (int) $p['count']; ?> task<?php echo $p['count'] === 1 ? '' : 's'; ?></small></div><?php endforeach; ?></section>
        <section class="pj-card"><h3>Added by</h3><div class="pj-who"><?php echo $ava(['initials' => $pj['owner_initials'], 'photo' => $pj['owner_photo']], 'pj-av'); ?><div><b><?php echo $e($pj['owner']); ?></b><small><?php echo $e($pj['added_at']); ?></small></div></div></section>
    </aside>
    </div>

    <?php if ($pj['can_manage']): ?>
    <dialog class="pj-dlg" id="pj-dlg" aria-labelledby="pj-dt">
        <form id="pj-form" method="post" action="<?php echo $e($formUrl); ?>"><?php echo csrf_field(); ?>
            <input type="hidden" name="op" value="task_save"><input type="hidden" name="event" value="<?php echo (int) $pj['id']; ?>"><input type="hidden" name="task" value="0"><input type="hidden" name="return_project" value="<?php echo (int) $pj['id']; ?>">
            <h4 id="pj-dt">Add task</h4>
            <div class="pj-err" id="pj-err" role="alert" hidden></div>
            <label for="pj-t">Task</label><input type="text" id="pj-t" name="title" maxlength="200" required autocomplete="off">
            <label for="pj-w">Given to</label><select id="pj-w" name="who"><option value="0">Nobody yet</option><?php foreach ($people as $pp): ?><option value="<?php echo (int) $pp['id']; ?>"><?php echo $e($pp['name']); ?></option><?php endforeach; ?></select>
            <div class="g2"><div><label for="pj-s">Starts (optional)</label><input type="date" id="pj-s" name="start"></div><div><label for="pj-d">Due</label><input type="date" id="pj-d" name="due"></div></div>
            <label for="pj-z">Status</label><select id="pj-z" name="status"><?php foreach (SPJ_STATUS as $k => $l): ?><option value="<?php echo $e($k); ?>"><?php echo $e($l); ?></option><?php endforeach; ?></select>
            <div class="row"><button type="button" class="pj-btn danger" id="pj-del" hidden>Delete task</button><button type="button" class="pj-btn" id="pj-cancel">Cancel</button><button type="submit" class="pj-btn go" id="pj-save">Save</button></div>
        </form>
    </dialog>
    <?php endif; ?>
</div>
<form id="pj-tok" style="display:none"><?php echo csrf_field(); ?></form>
<script>
(function () {
  var FORMURL = <?php echo json_encode($formUrl); ?>, EVENT = <?php echo (int) $pj['id']; ?>, TASKS = <?php echo json_encode($tasksJson, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  function $(id) { return document.getElementById(id); }
  var CSRF = document.querySelector('#pj-tok [name=csrf_token]').value, msg = $('pj-msg');
  function post(fd, onErr) {
    fd.append('csrf_token', CSRF); fd.append('event', EVENT); fd.append('return_project', EVENT);
    return fetch(FORMURL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); })
      .then(function (d) { if (d.ok) window.location.href = d.go; else onErr(d.error); })
      .catch(function () { onErr('Could not reach the server. Check your connection.'); });
  }
  function shown(t) { msg.textContent = t; msg.hidden = false; }
  function setStatus(id, status, revert) {   // a tick or a status change saves at once; the server checks who is asking
    var fd = new FormData(); fd.append('op', 'task_status'); fd.append('task', id); fd.append('status', status);
    post(fd, function (e) { shown(e); revert(); });
  }
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.classList.contains('pj-ck')) { var was = !t.checked; t.disabled = true; setStatus(t.getAttribute('data-task'), t.checked ? 'done' : 'todo', function () { t.checked = was; t.disabled = false; }); }
    if (t.classList.contains('pj-st')) { var old = t.getAttribute('data-old') || ''; setStatus(t.getAttribute('data-task'), t.value, function () { location.reload(); }); }
  });
  var dlg = $('pj-dlg'); if (!dlg) return;
  var f = $('pj-form');
  function openT(t) {
    $('pj-err').hidden = true; f.elements['task'].value = t ? t.id : 0; $('pj-dt').textContent = t ? 'Edit task' : 'Add task';
    f.elements['title'].value = t ? t.t : ''; f.elements['who'].value = t ? t.who : 0; f.elements['start'].value = t ? t.start : ''; f.elements['due'].value = t ? t.due : ''; f.elements['status'].value = t ? t.status : 'todo';
    $('pj-del').hidden = !t; dlg.showModal(); f.elements['title'].focus();
  }
  $('pj-add').addEventListener('click', function () { openT(null); });
  document.addEventListener('click', function (e) { var b = e.target.closest('.pj-edit'); if (b) openT(TASKS[b.getAttribute('data-task')]); });
  $('pj-cancel').addEventListener('click', function () { dlg.close(); });
  f.addEventListener('submit', function (e) {
    e.preventDefault(); var b = $('pj-save'); b.disabled = true;
    var fd = new FormData(f); fd.delete('csrf_token'); fd.delete('event'); fd.delete('return_project');
    post(fd, function (er) { $('pj-err').textContent = er; $('pj-err').hidden = false; b.disabled = false; });
  });
  $('pj-del').addEventListener('click', function () {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    var fd = new FormData(); fd.append('op', 'task_delete'); fd.append('task', f.elements['task'].value);
    post(fd, function (er) { $('pj-err').textContent = er; $('pj-err').hidden = false; });
  });
})();
</script>
<?php
$content = ob_get_clean();
