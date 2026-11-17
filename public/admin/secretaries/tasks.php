<?php
// Admin: Secretary tasks. Give a task to ONE secretary (title, optional description, due date and time), see every task with its status, edit it, open a ticked one again, delete it.
// ONLY the normal admin (secretary_admin_require): the super admin, secretaries and everyone else get a plain 403. A secretary can only tick her own task and add a note.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryTaskService.php';

use Models\Connection;

$adminId = secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$svc = new SecretaryTaskService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$errors = []; $in = [];
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'The security check failed. Please try again.';
        $in = $_POST;
    } elseif ($act === 'create' || $act === 'update') {
        [$d, $errors] = $svc->validate($_POST);
        if (!$errors) {
            $r = $act === 'create' ? $svc->give($adminId, $d) : $svc->update($adminId, $id, $d);
            if (!empty($r['ok'])) { SessionModel::setFlash('success', $act === 'create' ? 'Task given. The secretary was told.' : 'Task saved.'); header('Location: tasks.php'); exit(); }
            $errors[] = $r['error'];
        }
        $in = $_POST; if ($act === 'update') $editId = $id;
    } else {
        $r = $act === 'delete' ? $svc->delete($adminId, $id) : ($act === 'reopen' ? $svc->reopen($adminId, $id) : ['ok' => false, 'error' => 'Unknown action.']);
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? ($act === 'delete' ? 'Task deleted.' : 'Task opened again.') : $h($r['error'] ?? 'Could not save.'));
        header('Location: tasks.php');
        exit();
    }
}

$notReady = false; $tasks = []; $people = [];
$fSec = (int) ($_GET['s'] ?? 0); $fSt = (string) ($_GET['st'] ?? '');
try { $tasks = $svc->listAll($fSec, $fSt); $people = $svc->assignable(); } catch (Throwable $e) { $notReady = true; }
$editing = null;
if ($editId) { $editing = $svc->get($editId); }
$f = static function (string $k, $d = '') use (&$in, &$editing) {
    if ($in) return $in[$k] ?? $d;
    if ($editing) { return ['title' => $editing['title'], 'description' => $editing['description'], 'due_date' => substr((string) $editing['due_at'], 0, 10), 'due_time' => substr((string) $editing['due_at'], 11, 5), 'secretary_id' => $editing['secretary_id']][$k] ?? $d; }
    return $d;
};

$page_title = 'Secretary tasks';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Secretary tasks', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.tk { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; }
.tk-card { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:18px; margin-bottom:16px; }
.tk-grid { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; } .tk-grid .w2 { grid-column:span 2; } .tk-grid .w4 { grid-column:1 / -1; }
.tk label { display:block; font-size:.8rem; font-weight:700; margin-bottom:4px; } .tk input, .tk select, .tk textarea { width:100%; min-height:44px; border:1px solid #cbd5d0; border-radius:10px; padding:8px 10px; background:#fff; }
.tk-btn { background:var(--g); color:#fff; border:0; border-radius:12px; min-height:46px; padding:0 20px; font-weight:800; cursor:pointer; } .tk-btn:hover { background:var(--gd); } .tk-btn.ghost { background:#fff; color:var(--gd); border:1px solid var(--g); } .tk-btn.danger { background:#fff; color:#b42318; border:1px solid #b42318; }
.tk-t { width:100%; border-collapse:collapse; } .tk-t th { font-size:.72rem; text-transform:uppercase; color:#6b7570; text-align:left; padding:10px 8px; border-bottom:1px solid #e6efe9; } .tk-t td { padding:12px 8px; border-bottom:1px solid #eef3ef; vertical-align:middle; } .tk-t small { display:block; color:#6b7570; }
.tk-pill { font-size:.75rem; font-weight:800; padding:4px 12px; border-radius:99px; background:#eceeea; color:#4a524e; } .tk-pill.done { background:var(--gl); color:var(--gd); } .tk-pill.late { background:#fde2e0; color:#b42318; } .tk-pill.today { background:#fff4d6; color:#8a6100; }
.tk-act { display:flex; gap:6px; flex-wrap:wrap; } .tk-act form { margin:0; } .tk-act .tk-btn { min-height:40px; padding:0 12px; font-size:.82rem; }
@media (max-width: 767.98px) { .tk-grid { grid-template-columns:1fr; } .tk-grid .w2, .tk-grid .w4 { grid-column:auto; } .tk-t, .tk-t tbody, .tk-t tr, .tk-t td { display:block; } .tk-t thead { display:none; } .tk-t tr { border:1px solid #e6efe9; border-radius:14px; padding:10px; margin-bottom:10px; } .tk-t td { border:0; padding:5px 0; } .tk-btn { width:100%; min-height:48px; } .tk-act .tk-btn { flex:1; min-height:46px; } }
</style>
CSS;
ob_start();
?>
<div class="tk">
<?php if ($notReady): ?><div class="alert alert-warning">The tasks tables are not in the database yet. Run the database update (migrations 129 to 132) first.</div><?php else: ?>
<?php if ($errors): ?><div class="alert alert-danger"><?php echo $h(implode(' ', $errors)); ?></div><?php endif; ?>
<form method="POST" class="tk-card" action="tasks.php" autocomplete="off" novalidate>
    <?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>"><input type="hidden" name="id" value="<?php echo (int) ($editing['id'] ?? 0); ?>">
    <h2 class="h5 fw-bold mb-3" style="color:#037A3B"><?php echo $editing ? 'Edit task' : 'Give a task'; ?></h2>
    <div class="tk-grid">
        <div class="w2"><label for="t_sec">Secretary *</label>
            <?php if ($editing): ?><input id="t_sec" value="<?php echo $h($people[(int) $editing['secretary_id']] ?? 'Secretary'); ?>" disabled><input type="hidden" name="secretary_id" value="<?php echo (int) $editing['secretary_id']; ?>">
            <?php else: ?><select id="t_sec" name="secretary_id" required><option value="">Choose a secretary</option><?php foreach ($people as $sid => $nm): ?><option value="<?php echo (int) $sid; ?>" <?php echo (int) $f('secretary_id') === (int) $sid ? 'selected' : ''; ?>><?php echo $h($nm); ?></option><?php endforeach; ?></select><?php endif; ?></div>
        <div class="w2"><label for="t_title">Title *</label><input id="t_title" name="title" maxlength="200" required value="<?php echo $h($f('title')); ?>"></div>
        <div class="w4"><label for="t_desc">Description (optional)</label><textarea id="t_desc" name="description" rows="2" maxlength="2000"><?php echo $h($f('description')); ?></textarea></div>
        <div class="w2"><label for="t_date">Due date *</label><input id="t_date" type="date" name="due_date" required value="<?php echo $h($f('due_date')); ?>"></div>
        <div class="w2"><label for="t_time">Due time *</label><input id="t_time" type="time" name="due_time" required value="<?php echo $h($f('due_time', '17:00')); ?>"></div>
    </div>
    <div class="mt-3 d-flex gap-2 flex-wrap"><button class="tk-btn" type="submit"><i class="fas fa-<?php echo $editing ? 'floppy-disk' : 'paper-plane'; ?> me-1"></i> <?php echo $editing ? 'Save task' : 'Give task'; ?></button><?php if ($editing): ?><a class="tk-btn ghost d-inline-flex align-items-center text-decoration-none" href="tasks.php">Cancel</a><?php endif; ?></div>
</form>

<div class="tk-card">
    <form method="GET" class="d-flex gap-2 flex-wrap mb-3" action="tasks.php">
        <select name="s" style="max-width:240px" aria-label="Secretary"><option value="">All secretaries</option><?php foreach ($people as $sid => $nm): ?><option value="<?php echo (int) $sid; ?>" <?php echo $fSec === (int) $sid ? 'selected' : ''; ?>><?php echo $h($nm); ?></option><?php endforeach; ?></select>
        <select name="st" style="max-width:180px" aria-label="Status"><option value="">Any status</option><option value="open" <?php echo $fSt === 'open' ? 'selected' : ''; ?>>Open</option><option value="done" <?php echo $fSt === 'done' ? 'selected' : ''; ?>>Done</option></select>
        <button class="tk-btn ghost" type="submit">Filter</button>
    </form>
    <?php if (!$tasks): ?><div class="text-muted text-center py-3">No tasks yet.</div><?php else: ?>
    <table class="tk-t"><thead><tr><th>Task</th><th>Secretary</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($tasks as $t): [$pill, $cls] = SecretaryTaskService::pill($t); ?>
        <tr><td><strong><?php echo $h($t['title']); ?></strong><?php if ($t['description']): ?><small><?php echo $h(mb_strimwidth((string) $t['description'], 0, 120, '…')); ?></small><?php endif; ?><small>given by <?php echo $h($t['given_by_name']); ?><?php echo (int) $t['note_count'] ? ' · ' . (int) $t['note_count'] . ' note(s)' : ''; ?></small></td>
            <td><?php echo $h($t['secretary_name']); ?></td><td><?php echo $h(date('D j M Y, g:i a', strtotime((string) $t['due_at']))); ?></td>
            <td><span class="tk-pill <?php echo $h($cls); ?>"><?php echo $h($pill); ?></span><?php if ($t['status'] === 'done'): ?><small>ticked <?php echo $h(date('D j M, g:i a', strtotime((string) $t['done_at']))); ?></small><?php endif; ?></td>
            <td><div class="tk-act"><a class="tk-btn ghost d-inline-flex align-items-center text-decoration-none" href="tasks.php?edit=<?php echo (int) $t['id']; ?>">Edit</a>
                <?php if ($t['status'] === 'done'): ?><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><button class="tk-btn ghost" type="submit">Open again</button></form><?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this task? This cannot be undone.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><button class="tk-btn danger" type="submit">Delete</button></form></div></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
