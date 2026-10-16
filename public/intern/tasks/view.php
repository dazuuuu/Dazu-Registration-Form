<?php
// One task: details, attachments, the history of submissions and supervisor comments, and the submit / re-submit form.
require __DIR__ . '/../_boot.php';

$taskId = (int) ($_GET['id'] ?? 0);
$task = in_task($db, $taskId, (int) $intern['id']);   // only the intern's own tasks
if (!$task) {
    SessionModel::setFlash('error', 'Task not found.');
    header('Location: ' . url('intern/tasks/index.php'));
    exit;
}
$self = url('intern/tasks/view.php?id=' . $taskId);
if (intern_post_ok()) {
    if (($_POST['act'] ?? '') === 'start') {
        in_task_start($db, $intern, $taskId) && SessionModel::setFlash('success', 'Task started. Good luck!');
    } else {
        $r = in_task_submit($db, $intern, $taskId, $_POST, $_FILES['files'] ?? []);
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Work submitted. Your supervisor will review it.' . ($r['warnings'] ? ' Not saved: ' . implode('; ', $r['warnings']) : '') : $r['error']));
    }
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$files = in_task_files($db, $taskId);
$attach = array_filter($files, fn($f) => !$f['submission_id']);
$bySub = [];
foreach ($files as $f) if ($f['submission_id']) $bySub[(int) $f['submission_id']][] = $f;
$thread = in_task_thread($db, $taskId);
$canSubmit = in_array($task['status'], ['todo', 'in_progress', 'revision'], true);
$pill = ['todo' => 'n', 'in_progress' => 'b', 'submitted' => 'o', 'revision' => 'r', 'completed' => 'g'];
$page_title = $task['title'];
$page_subtitle = 'My Tasks';
ob_start();
?>
<a href="<?php echo url('intern/tasks/index.php'); ?>" class="btn btn-link btn-sm ps-0">← All tasks</a>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="ip-card">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <h3 class="me-auto"><?php echo in_e($task['title']); ?></h3>
                <span class="ip-pill <?php echo ['normal' => 'n', 'medium' => 'o', 'high' => 'r'][$task['priority']]; ?>"><?php echo INTERN_PRIORITIES[$task['priority']]; ?></span>
                <span class="ip-pill <?php echo $pill[$task['status']]; ?>"><?php echo INTERN_TASK_STATUSES[$task['status']]; ?></span>
            </div>
            <div class="ip-muted mb-3"><?php echo $task['deadline'] ? 'Due ' . date('l j M Y', strtotime($task['deadline'])) . ' · ' : ''; ?>Assigned by <?php echo in_e($task['assigned_by_name']); ?></div>
            <?php if ($task['description']): ?><p style="white-space:pre-line"><?php echo in_e($task['description']); ?></p><?php endif; ?>
            <?php if ($attach): ?><div class="fw-semibold small mb-1">Attachments</div>
                <?php foreach ($attach as $f): ?><div class="small"><i class="fas fa-paperclip text-success me-1"></i><a target="_blank" href="<?php echo in_e(in_file_url('task', (int) $f['id'])); ?>"><?php echo in_e($f['name']); ?></a></div><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($task['status'] === 'todo'): ?>
                <form method="POST" class="mt-3"><?php echo csrf_field(); ?><input type="hidden" name="act" value="start"><button class="btn btn-outline-success"><i class="fas fa-play me-1"></i>Start task</button></form>
            <?php endif; ?>
        </div>
        <?php if ($canSubmit): ?>
        <form method="POST" enctype="multipart/form-data" class="ip-card" id="submit"><?php echo csrf_field(); ?><input type="hidden" name="act" value="submit">
            <h3 class="mb-3"><?php echo $task['status'] === 'revision' ? 'Fix and submit again' : 'Your submission'; ?></h3>
            <div class="mb-2"><label class="form-label small fw-semibold"><i class="fas fa-upload me-1"></i>Upload file</label><input type="file" name="files[]" multiple class="form-control"></div>
            <div class="mb-2"><label class="form-label small fw-semibold"><i class="fas fa-link me-1"></i>Paste link</label><input type="url" name="link" class="form-control" placeholder="https://…"></div>
            <div class="mb-3"><label class="form-label small fw-semibold"><i class="fas fa-pen me-1"></i>Write notes</label><textarea name="notes" class="form-control" rows="4" placeholder="What you did, and anything your supervisor should know"></textarea></div>
            <button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Submit work</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="ip-card">
            <h3 class="mb-3">History</h3>
            <?php if (!$thread): ?><div class="ip-muted">Nothing yet.</div><?php endif; ?>
            <?php foreach ($thread as $m): ?>
                <div class="mb-3 p-2 rounded-3 <?php echo $m['kind'] === 'revision' ? 'bg-danger-subtle' : ($m['kind'] === 'submission' ? 'bg-light' : 'bg-success-subtle'); ?>">
                    <div class="small fw-semibold"><?php echo ['submission' => 'You submitted', 'revision' => 'Needs revision', 'approved' => 'Approved', 'comment' => 'Supervisor comment'][$m['kind']]; ?>
                        <span class="ip-muted fw-normal">· <?php echo date('j M, g:i A', strtotime($m['created_at'])); ?></span></div>
                    <?php if ($m['body']): ?><div class="small" style="white-space:pre-line"><?php echo in_e($m['body']); ?></div><?php endif; ?>
                    <?php if ($m['link']): ?><div class="small"><a href="<?php echo in_e($m['link']); ?>" target="_blank" rel="noopener"><?php echo in_e($m['link']); ?></a></div><?php endif; ?>
                    <?php if ($m['kind'] === 'submission') foreach ($bySub[(int) $m['id']] ?? [] as $f): ?><div class="small"><i class="fas fa-paperclip me-1"></i><a target="_blank" href="<?php echo in_e(in_file_url('task', (int) $f['id'])); ?>"><?php echo in_e($f['name']); ?></a></div><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
