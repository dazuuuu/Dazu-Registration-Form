<?php
// Supervisor workspace ("My interns"): only the interns this admin supervises.
// Tasks (create, review submissions), reports, evaluations, attendance corrections, skills and messages.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
$id = (int) ($_GET['id'] ?? 0);
$intern = $id ? in_get($db, $id) : null;
if ($intern && (int) $intern['supervisor_id'] !== $me) {
    $_SESSION['error_msg'] = 'Only ' . ($intern['supervisor_name'] ?: 'the intern\'s supervisor') . ' can supervise this intern. HR can change the supervisor on the intern\'s page.';
    header('Location: ' . url('admin/interns/supervise.php'));
    exit;
}
$self = url('admin/interns/supervise.php' . ($intern ? '?id=' . $id : ''));

if ($intern && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $self;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $r = ['ok' => true];
        $ok = 'Saved.';
        switch ($_POST['act'] ?? '') {
            case 'task':
                $r = in_task_create($db, $id, $_POST, $_FILES['files'] ?? []);
                $ok = 'Task created and sent to ' . $intern['first_name'] . '.';
                break;
            case 'review':
                $taskId = (int) $_POST['task_id'];
                if (!in_task($db, $taskId, $id)) { $r = ['ok' => false, 'error' => 'Task not found.']; break; }
                $r = in_task_review($db, $taskId, (string) ($_POST['decision'] ?? 'comment'), (string) ($_POST['comment'] ?? ''));
                $ok = 'Done.';
                $back = $self . '&task=' . $taskId;
                break;
            case 'report':
                $st = $db->prepare('SELECT 1 FROM intern_reports WHERE id = ? AND intern_id = ?');
                $st->execute([(int) $_POST['report_id'], $id]);
                $r = $st->fetchColumn() ? in_report_review($db, (int) $_POST['report_id'], (string) $_POST['decision'], (string) ($_POST['comment'] ?? '')) : ['ok' => false, 'error' => 'Report not found.'];
                $ok = 'Report reviewed.';
                $back = $self . '#reports';
                break;
            case 'eval':
                $r = in_eval_save($db, $id, (string) ($_POST['kind'] ?? ''), $_POST, true);
                $ok = !empty($_POST['release']) ? 'Evaluation released to the intern.' : 'Evaluation saved as a draft.';
                $back = $self . '#evaluations';
                break;
            case 'attendance':
                $r = in_attendance_correct($db, $id, (string) ($_POST['work_date'] ?? ''), $_POST);
                $ok = 'Attendance corrected.';
                break;
            case 'skills':
                $db->prepare('UPDATE interns SET skills = ? WHERE id = ?')->execute([mb_substr(trim((string) ($_POST['skills'] ?? '')), 0, 2000) ?: null, $id]);
                in_audit($db, $id, 'skills_updated');
                break;
            case 'message':
                $r = in_message_send($db, $intern, (string) ($_POST['body'] ?? ''));
                $ok = 'Message sent.';
                $back = $self . '#messages';
                break;
        }
        $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? $ok . (!empty($r['warnings']) ? ' Some files were not saved: ' . implode('; ', $r['warnings']) : '') : $r['error'];
    }
    header('Location: ' . $back);
    exit;
}

$page_title = 'My interns';
$breadcrumbs = [['label' => 'Interns', 'url' => url('admin/interns/index.php')], ['label' => $intern ? $intern['full_name'] : 'My interns', 'active' => true]];
$inTab = 'supervise';
$stars = function (?int $n) {
    $n = (int) $n;
    return '<span style="color:#f59e0b;letter-spacing:2px">' . str_repeat('★', $n) . '<span style="color:#d1d5db">' . str_repeat('★', 5 - $n) . '</span></span>';
};
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<?php if (!$intern):
    $mine = in_list($db, $me);
?>
    <?php if (!$mine): ?><div class="in-card text-muted">You do not supervise any interns yet. HR chooses the supervisor on each intern's page.</div><?php endif; ?>
    <div class="row g-3">
        <?php foreach ($mine as $i):
            $tasks = in_tasks($db, (int) $i['id']);
            $waiting = count(array_filter($tasks, fn($t) => $t['status'] === 'submitted'));
            $reports = count(array_filter(in_reports($db, (int) $i['id']), fn($r) => $r['status'] === 'pending'));
            $msgs = in_unread_messages($db, $i, false); ?>
        <div class="col-md-6 col-xl-4"><a class="in-card d-block text-decoration-none text-dark h-100" href="<?php echo url('admin/interns/supervise.php?id=' . (int) $i['id']); ?>">
            <div class="fw-semibold fs-6"><?php echo in_e($i['full_name']); ?> <span class="in-pill <?php echo in_e($i['status']); ?> ms-1"><?php echo ucfirst($i['status']); ?></span></div>
            <div class="small text-muted mb-2"><?php echo in_e($i['department'] ?? 'No department'); ?> · <?php echo in_e($i['institution'] ?? ''); ?></div>
            <div class="d-flex gap-3 small">
                <span><strong><?php echo $waiting; ?></strong> to review</span><span><strong><?php echo $reports; ?></strong> reports</span><span><strong><?php echo $msgs; ?></strong> messages</span>
            </div>
        </a></div>
        <?php endforeach; ?>
    </div>
<?php else:
    $tasks = in_tasks($db, $id);
    $openTask = (int) ($_GET['task'] ?? 0);
    $reports = in_reports($db, $id);
    $evals = in_evaluations($db, $id);
    $att = in_attendance_stats($db, $intern);
    $prog = in_progress($db, $intern);
    $messages = in_messages($db, $id);
    in_messages_mark_read($db, $intern, false);
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div><h2 class="h4 mb-0"><?php echo in_e($intern['full_name']); ?></h2>
        <div class="small text-muted"><?php echo in_e($intern['department'] ?? ''); ?> · Attendance <?php echo $att['rate']; ?>% · Progress <?php echo $prog['overall']; ?>% · Tasks <?php echo $prog['task_done']; ?>/<?php echo $prog['task_total']; ?></div></div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo url('admin/interns/supervise.php'); ?>">← All my interns</a>
</div>
<div class="row g-3">
<div class="col-xl-7">
    <div class="in-card" id="tasks">
        <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">Tasks</h5>
            <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#newTask"><i class="fas fa-plus me-1"></i>New task</button></div>
        <form method="POST" enctype="multipart/form-data" class="collapse border rounded-3 p-3 mb-3 <?php echo $tasks ? '' : 'show'; ?>" id="newTask">
            <?php echo csrf_field(); ?><input type="hidden" name="act" value="task">
            <div class="row g-2">
                <div class="col-12"><input name="title" class="form-control" placeholder="Task title" required maxlength="200"></div>
                <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="What to do, and what a good result looks like"></textarea></div>
                <div class="col-md-4"><select name="priority" class="form-select"><?php foreach (INTERN_PRIORITIES as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><input type="date" name="deadline" class="form-control" min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="col-md-4"><input type="file" name="files[]" multiple class="form-control"></div>
            </div>
            <button class="btn btn-success btn-sm mt-2">Create task</button>
        </form>
        <?php if (!$tasks): ?><div class="text-muted small">No tasks yet.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($tasks as $t): $open = $openTask === (int) $t['id']; ?>
            <div class="list-group-item px-0">
                <a class="d-flex gap-2 align-items-center text-decoration-none text-dark" href="<?php echo $self . '&task=' . (int) $t['id']; ?>#tasks">
                    <span class="in-pill <?php echo $t['priority']; ?>"><?php echo INTERN_PRIORITIES[$t['priority']]; ?></span>
                    <span class="flex-grow-1 fw-semibold"><?php echo in_e($t['title']); ?></span>
                    <span class="small text-muted"><?php echo $t['deadline'] ? 'Due ' . date('j M', strtotime($t['deadline'])) : ''; ?></span>
                    <span class="in-pill <?php echo $t['status']; ?>"><?php echo INTERN_TASK_STATUSES[$t['status']]; ?></span>
                </a>
                <?php if ($open): $files = in_task_files($db, (int) $t['id']); $thread = in_task_thread($db, (int) $t['id']); ?>
                <div class="border rounded-3 p-3 mt-2 bg-light">
                    <?php if ($t['description']): ?><p class="small mb-2" style="white-space:pre-line"><?php echo in_e($t['description']); ?></p><?php endif; ?>
                    <?php foreach ($files as $f): ?><div class="small"><i class="fas fa-paperclip me-1 text-success"></i><a target="_blank" href="<?php echo in_e(in_file_url('task', (int) $f['id'])); ?>"><?php echo in_e($f['name']); ?></a> <span class="text-muted"><?php echo $f['submission_id'] ? '(submitted)' : '(attachment)'; ?></span></div><?php endforeach; ?>
                    <?php foreach ($thread as $m): ?>
                        <div class="small mt-2 p-2 rounded <?php echo $m['kind'] === 'submission' ? 'bg-white border' : ($m['kind'] === 'revision' ? 'bg-danger-subtle' : 'bg-success-subtle'); ?>">
                            <strong><?php echo $m['kind'] === 'submission' ? 'Submission' : ucfirst($m['kind']); ?></strong> · <?php echo date('j M, H:i', strtotime($m['created_at'])); ?>
                            <?php if ($m['body']): ?><div style="white-space:pre-line"><?php echo in_e($m['body']); ?></div><?php endif; ?>
                            <?php if ($m['link']): ?><div><a href="<?php echo in_e($m['link']); ?>" target="_blank" rel="noopener"><?php echo in_e($m['link']); ?></a></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="act" value="review"><input type="hidden" name="task_id" value="<?php echo (int) $t['id']; ?>">
                        <textarea name="comment" class="form-control form-control-sm mb-2" rows="2" placeholder="Comment (required to send it back)"></textarea>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($t['status'] === 'submitted'): ?>
                                <button name="decision" value="approve" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                                <button name="decision" value="revision" class="btn btn-sm btn-outline-danger"><i class="fas fa-rotate-left me-1"></i>Send back</button>
                            <?php endif; ?>
                            <button name="decision" value="comment" class="btn btn-sm btn-outline-secondary">Comment only</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="in-card" id="reports">
        <h5>Reports &amp; logbook</h5>
        <?php if (!$reports): ?><div class="text-muted small">No reports yet.</div><?php endif; ?>
        <?php foreach ($reports as $r): ?>
            <details class="border rounded-3 p-2 mb-2" <?php echo $r['status'] === 'pending' ? 'open' : ''; ?>>
                <summary class="d-flex justify-content-between align-items-center"><span class="fw-semibold small"><?php echo in_e(in_report_label($r)); ?></span><span class="in-pill <?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></summary>
                <div class="small mt-2">
                    <?php foreach (['activities' => 'Activities done', 'skills' => 'Skills learned', 'challenges' => 'Challenges', 'lessons' => 'Lessons learned'] as $k => $l): if ($r[$k]): ?>
                        <div class="mb-1"><strong><?php echo $l; ?>:</strong> <span style="white-space:pre-line"><?php echo in_e($r[$k]); ?></span></div><?php endif; endforeach; ?>
                    <?php if ($r['supervisor_comment']): ?><div class="text-muted">Your comment: <?php echo in_e($r['supervisor_comment']); ?></div><?php endif; ?>
                    <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="act" value="report"><input type="hidden" name="report_id" value="<?php echo (int) $r['id']; ?>">
                        <input name="comment" class="form-control form-control-sm mb-2" placeholder="Comment (required to reject)">
                        <button name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                        <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button></form>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</div>

<div class="col-xl-5">
    <div class="in-card" id="evaluations">
        <h5>Evaluations</h5>
        <?php foreach (['self', 'skills'] as $k): $ev = $evals[$k] ?? null; ?>
            <div class="d-flex justify-content-between small mb-1"><span><?php echo INTERN_EVAL_KINDS[$k]; ?></span>
                <span><?php echo $ev && $ev['status'] !== 'draft' ? $stars((int) round(in_eval_score($ev))) . ' ' . in_eval_score($ev) : '<span class="text-muted">Not submitted</span>'; ?></span></div>
            <?php if ($ev && $ev['feedback']): ?><div class="small text-muted mb-2" style="white-space:pre-line">“<?php echo in_e($ev['feedback']); ?>”</div><?php endif; ?>
        <?php endforeach; ?>
        <?php foreach (['midterm', 'final'] as $k): $ev = $evals[$k] ?? null; $locked = $k === 'final' && empty($intern['final_eval_open']); ?>
            <form method="POST" class="border rounded-3 p-3 mt-2 <?php echo $locked ? 'opacity-50' : ''; ?>"><?php echo csrf_field(); ?><input type="hidden" name="act" value="eval"><input type="hidden" name="kind" value="<?php echo $k; ?>">
                <div class="d-flex justify-content-between mb-2"><strong><?php echo INTERN_EVAL_KINDS[$k]; ?></strong>
                    <span class="in-pill <?php echo $ev['status'] ?? 'todo'; ?>"><?php echo $locked ? 'Not yet open' : ucfirst($ev['status'] ?? 'not started'); ?></span></div>
                <?php foreach (INTERN_EVAL_CRITERIA as $c => $l): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 small"><span><?php echo $l; ?></span>
                        <select name="<?php echo $c; ?>" class="form-select form-select-sm" style="width:auto" <?php echo $locked ? 'disabled' : ''; ?>>
                            <option value="">–</option><?php for ($n = 1; $n <= 5; $n++): ?><option value="<?php echo $n; ?>" <?php echo (int) ($ev[$c] ?? 0) === $n ? 'selected' : ''; ?>><?php echo str_repeat('★', $n); ?></option><?php endfor; ?></select></div>
                <?php endforeach; ?>
                <textarea name="feedback" class="form-control form-control-sm mt-2" rows="3" placeholder="Written feedback" <?php echo $locked ? 'disabled' : ''; ?>><?php echo in_e($ev['feedback'] ?? ''); ?></textarea>
                <?php if (!$locked): ?><div class="d-flex gap-2 mt-2"><button class="btn btn-sm btn-outline-secondary">Save draft</button><button name="release" value="1" class="btn btn-sm btn-success">Save &amp; release</button></div>
                <?php else: ?><div class="small text-muted mt-2">HR opens the final evaluation on the intern's page.</div><?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>

    <form method="POST" class="in-card"><?php echo csrf_field(); ?><input type="hidden" name="act" value="skills">
        <h5>Skills acquired</h5>
        <input name="skills" value="<?php echo in_e($intern['skills']); ?>" class="form-control form-control-sm mb-2" placeholder="Nmap, Wireshark, Report writing…">
        <button class="btn btn-sm btn-outline-success">Save skills</button>
    </form>

    <form method="POST" class="in-card row g-2 mx-0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="attendance">
        <h5 class="px-0">Attendance correction</h5>
        <div class="col-6 ps-0"><input type="date" name="work_date" class="form-control form-control-sm" max="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="col-6 pe-0"><select name="status" class="form-select form-select-sm"><?php foreach (INTERN_ATT_STATUSES as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?></select></div>
        <div class="col-6 ps-0"><input type="time" name="clock_in" class="form-control form-control-sm"></div>
        <div class="col-6 pe-0"><input type="time" name="clock_out" class="form-control form-control-sm"></div>
        <div class="col-8 ps-0"><input name="note" class="form-control form-control-sm" placeholder="Reason"></div>
        <div class="col-4 pe-0"><label class="form-check small"><input type="checkbox" name="excused" value="1" class="form-check-input"> Excused</label></div>
        <div class="col-12 px-0"><button class="btn btn-sm btn-outline-success">Save</button></div>
    </form>

    <div class="in-card" id="messages">
        <h5>Messages</h5>
        <div style="max-height:320px;overflow:auto" class="mb-2" id="msgBox">
            <?php if (!$messages): ?><div class="text-muted small">No messages yet.</div><?php endif; ?>
            <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === $me; ?>
                <div class="d-flex <?php echo $mine ? 'justify-content-end' : ''; ?> mb-2"><div class="p-2 rounded-3 small <?php echo $mine ? 'bg-success text-white' : 'bg-light'; ?>" style="max-width:80%">
                    <div style="white-space:pre-line"><?php echo in_e($m['body']); ?></div><div class="opacity-75" style="font-size:.7rem"><?php echo in_e($m['sender_name']); ?> · <?php echo date('j M, H:i', strtotime($m['created_at'])); ?></div></div></div>
            <?php endforeach; ?>
        </div>
        <form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="act" value="message">
            <input name="body" class="form-control form-control-sm" placeholder="Write a message…" required maxlength="4000"><button class="btn btn-sm btn-success"><i class="fas fa-paper-plane"></i></button></form>
    </div>
</div>
</div>
<script>var b = document.getElementById('msgBox'); if (b) b.scrollTop = b.scrollHeight;</script>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
