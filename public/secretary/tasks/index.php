<?php
// My Tasks (secretary portal): HER OWN tasks, given by the admin. She can tick a task and add a short note. She cannot create, edit or delete tasks (any such request is refused), and she never sees another
// secretary's task. Checked on every request: an active secretary (On leave is fine) with the My Tasks switch on (secretary_require).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryTaskService.php';

$sec = secretary_require('tasks');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryTaskService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    if (!in_array($act, ['tick', 'note'], true)) {   // creating, editing or deleting a task is the admin's alone
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Not allowed. Only the admin creates, changes or deletes tasks.');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'The security check failed. Please try again.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $r = $act === 'tick' ? $svc->tick($sec, $id, (string) ($_POST['note'] ?? '')) : $svc->addNote($sec, $id, (string) ($_POST['note'] ?? ''));
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? ($act === 'tick' ? 'Ticked. The admin was told.' : 'Note added.') : $h($r['error'] ?? 'Could not save.'));
    }
    header('Location: index.php');
    exit();
}

$notReady = false; $tasks = [];
try { $tasks = $svc->forSecretary((int) $sec['id']); } catch (Throwable $e) { $notReady = true; }
$open = count(array_filter($tasks, fn($t) => $t['status'] === 'open'));
$dt = static fn(string $x): string => date('D j M, g:i a', strtotime($x));
$day = static function (string $x): string { $d = date('Y-m-d', strtotime($x)); $t = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d'); return $d === $t ? 'today' : date('D j M', strtotime($x)); };

$page_title = 'My Tasks';
$sp_active = 'tasks';
ob_start();
?>
<style>
.st-box { background:#fff; border-radius:26px; padding:24px 28px; max-width:900px; }
.st-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border-bottom:1px solid var(--line); padding-bottom:14px; }
.st-head h2 { font-size:1.3rem; font-weight:800; margin:0; } .st-head p { margin:4px 0 0; color:var(--mut); }
.st-open { background:var(--gl); color:var(--gd); font-weight:800; font-size:.85rem; padding:6px 14px; border-radius:99px; white-space:nowrap; }
.st-task { border-bottom:1px solid var(--line); padding:6px 0; } .st-task:last-of-type { border-bottom:0; }
.st-row { display:flex; align-items:center; gap:14px; padding:12px 0; }
.st-tick { width:44px; height:44px; flex:0 0 auto; border-radius:12px; border:2px solid #cbd5d0; background:#fff; display:flex; align-items:center; justify-content:center; color:#fff; cursor:pointer; font-size:1rem; padding:0; }
.st-tick:hover { border-color:var(--g); } .st-tick.on { background:var(--g); border-color:var(--g); cursor:default; }
.st-main { flex:1; min-width:0; } .st-main b { display:block; font-weight:600; overflow-wrap:anywhere; } .st-main small { color:var(--mut); }
.st-done .st-main b { text-decoration:line-through; color:#8a948f; }
.st-pill { font-size:.8rem; font-weight:800; padding:5px 14px; border-radius:99px; white-space:nowrap; background:#eceeea; color:#4a524e; } .st-pill.today { background:#fff4d6; color:#8a6100; } .st-pill.done { background:var(--gl); color:var(--gd); } .st-pill.late { background:#fde2e0; color:#b42318; }
.st-more summary { list-style:none; cursor:pointer; color:var(--gd); font-weight:700; font-size:.85rem; min-height:44px; display:flex; align-items:center; padding-left:58px; } .st-more summary::-webkit-details-marker { display:none; }
.st-pane { padding:6px 0 14px 58px; } .st-pane p { margin:0 0 8px; overflow-wrap:anywhere; white-space:pre-wrap; }
.st-note { background:#f7faf8; border-radius:12px; padding:8px 12px; margin-bottom:6px; font-size:.9rem; } .st-note b { font-weight:700; } .st-note small { color:var(--mut); margin-left:6px; }
.st-form textarea { width:100%; border:1px solid #cbd5d0; border-radius:12px; padding:10px 12px; min-height:70px; margin:6px 0; font-size:1rem; }
.st-btn { background:var(--g); color:#fff; border:0; border-radius:12px; min-height:46px; padding:0 18px; font-weight:800; cursor:pointer; } .st-btn:hover { background:var(--gd); } .st-btn.ghost { background:#fff; color:var(--gd); border:1px solid var(--g); }
.st-foot { color:var(--mut); font-size:.92rem; margin-top:14px; }
@media (max-width: 991.98px) { .st-box { padding:16px; border-radius:20px; } .st-row { flex-wrap:wrap; } .st-main { flex:1 1 60%; } .st-pane, .st-more summary { padding-left:0; } .st-btn { width:100%; min-height:50px; margin-top:4px; } }
</style>
<?php if ($notReady): ?><div class="alert alert-warning">The tasks tables are not in the database yet. Ask the admin to run the database update (migrations 129 to 132).</div><?php else: ?>
<section class="st-box">
    <div class="st-head"><div><h2>My Tasks</h2><p>Given by the admin · they also show on your Staff Calendar</p></div><span class="st-open"><?php echo (int) $open; ?> open</span></div>
    <?php if (!$tasks): ?><div class="text-center text-muted py-4"><i class="fas fa-list-check fa-2x mb-2" style="color:#b9c7be"></i><br><strong>No tasks yet</strong><br>Tasks the admin gives you will show here.</div><?php endif; ?>
    <?php foreach ($tasks as $t): [$pill, $cls] = SecretaryTaskService::pill($t); $done = $t['status'] === 'done'; $tid = (int) $t['id']; ?>
    <div class="st-task <?php echo $done ? 'st-done' : ''; ?>">
        <div class="st-row">
            <?php if ($done): ?><span class="st-tick on" aria-label="Done"><i class="fas fa-check"></i></span>
            <?php else: ?><form method="POST" style="margin:0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="tick"><input type="hidden" name="id" value="<?php echo $tid; ?>">
                <button type="submit" class="st-tick" aria-label="Tick <?php echo $h($t['title']); ?> as done" title="Tick as done"></button></form><?php endif; ?>
            <div class="st-main"><b><?php echo $h($t['title']); ?></b>
                <small><?php echo $done ? 'Done ' . $h($dt((string) $t['done_at'])) : 'Due ' . $h($day((string) $t['due_at'])) . ' · ' . $h(date('g:i a', strtotime((string) $t['due_at']))); ?> · given by <?php echo $h($t['given_by_name']); ?></small></div>
            <span class="st-pill <?php echo $h($cls); ?>"><?php echo $h($pill); ?></span>
        </div>
        <details class="st-more"><summary><i class="fas fa-chevron-down me-1"></i> Details and notes<?php echo $t['notes'] ? ' (' . count($t['notes']) . ')' : ''; ?></summary>
            <div class="st-pane">
                <?php if (!empty($t['description'])): ?><p><?php echo $h($t['description']); ?></p><?php endif; ?>
                <?php foreach ($t['notes'] as $n): ?><div class="st-note"><b><?php echo $h($n['author_name']); ?></b><small><?php echo $h($dt((string) $n['created_at'])); ?></small><div><?php echo $h($n['body']); ?></div></div><?php endforeach; ?>
                <form method="POST" class="st-form"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $tid; ?>">
                    <label class="small fw-bold" for="n<?php echo $tid; ?>">Add a short note</label><textarea id="n<?php echo $tid; ?>" name="note" maxlength="500" placeholder="What did you do?"></textarea>
                    <button class="st-btn ghost" type="submit" name="action" value="note">Add note</button>
                    <?php if (!$done): ?><button class="st-btn" type="submit" name="action" value="tick">Tick as done<?php echo ' (with this note)'; ?></button><?php endif; ?></form>
            </div>
        </details>
    </div>
    <?php endforeach; ?>
    <p class="st-foot">You can tick a task and add a short note. You cannot create or delete tasks, only the admin does. You get a reminder the day before it is due, and on the day at 8:00 am.</p>
</section>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
