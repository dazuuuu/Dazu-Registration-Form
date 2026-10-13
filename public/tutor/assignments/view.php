<?php
// Tutor: one assignment and its students (spec 8.5). Grade, decide requests, unlock, extend for one student, skip, export CSV.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$a = $am->getForTutor($id, $tutorId);
if (!$a) asg_back('index.php', null, 'Assignment not found.');
$self = 'view.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $r = $am->deleteOrArchive($id, $me, false);
        $r ? asg_back('index.php', 'Assignment deleted.') : asg_back($self, null, 'Students have already submitted work, so it cannot be deleted. Use Archive instead.');
    }
    if ($action === 'archive') {
        $am->setArchived($id, true, $me);
        asg_back('index.php', 'Assignment archived. Its submissions and grades are kept.');
    }
    $uid = (int) ($_POST['student'] ?? 0);
    if (!in_array($uid, $am->recipientIds($id), true) || !$am->isTutorStudent($tutorId, $uid)) asg_back($self, null, 'That student is not on this assignment.');
    $notifier = new AssignmentNotifier($pdo, $am);
    if ($action === 'unlock' || $action === 'extend') {
        $due = AssignmentModel::toUtc($_POST['due'] ?? '');
        if (!$due || AssignmentModel::ts($due) <= time()) asg_back($self, null, 'Pick a new due date in the future.');
        $action === 'unlock' ? $am->unlock($id, $uid, $due, $me) : $am->setStudentDue($id, $uid, $due, $me, 'extend');
        $notifier->decided($a, $uid, $action === 'unlock' ? 'unlock' : 'extension', true, $due, trim((string) ($_POST['note'] ?? '')) ?: null);
        asg_back($self, ($action === 'unlock' ? 'Unlocked' : 'Due date changed') . ' for ' . $am->student($uid)['full_name'] . '. They have been emailed.');
    }
    if ($action === 'skip') {
        $opened = $am->skip($a, $uid, $me);
        $notifier->unlocked($uid, $opened);
        asg_back($self, 'Skipped for ' . $am->student($uid)['full_name'] . '. Their next assignment is open.');
    }
    asg_back($self);
}

$rows = $am->assignmentStudents($a);
$counts = array_count_values(array_map(fn($r) => $r['item']['state'], $rows));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assignment-' . $id . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Registration no', 'Status', 'Submitted (EAT)', 'Version', 'Late', 'Marks', 'Out of', 'Result', 'Content', 'Due for student (EAT)'], ",", "\"", "");
    foreach ($rows as $r) {
        $s = $r['sub'];
        $i = $r['item'];
        fputcsv($out, [
            $r['student']['full_name'], $r['student']['registration_no'], AssignmentModel::STATES[$i['state']][0],
            $s && $s['submitted_at'] ? AssignmentModel::local($s['submitted_at'], 'Y-m-d H:i') : '', $s['version'] ?? '',
            !empty($s['is_late']) ? 'Yes' : '', $i['state'] === 'graded' && $a['grading_mode'] === 'marks' ? (float) $s['score'] : '',
            $a['grading_mode'] === 'marks' ? (int) $a['max_score'] : '', $i['state'] === 'graded' ? (string) $s['grade_label'] : '',
            AssignmentModel::contentSummary($s), AssignmentModel::local($i['due'], 'Y-m-d H:i'),
        ], ",", "\"", "");
    }
    exit;
}

$requests = [];
foreach ($am->requests($tutorId, 'pending', $id) as $q) $requests[(int) $q['student_user_id']] = $q;
$num = $am->number($a);
$page_title = 'Assignment ' . $num;
$defaultUnlock = AssignmentModel::localInput(gmdate('Y-m-d H:i:s', time() + 48 * 3600));
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4 class="mb-1">Assignment <?php echo $num; ?>: <?php echo asg_e($a['title']); ?>
                <?php if ($a['status'] === 'draft'): ?><span class="badge bg-secondary align-middle">DRAFT</span><?php endif; ?></h4>
            <div class="small text-muted">Due <?php echo asg_e(AssignmentModel::local($a['due_at'])); ?> · <?php echo asg_e($a['course_title'] ?: 'No course'); ?> · <?php echo asg_e($a['types_text']); ?> ·
                <?php echo $a['grading_mode'] === 'marks' ? 'Marks out of ' . (int) $a['max_score'] : asg_e(AssignmentModel::GRADING_MODES[$a['grading_mode']]); ?>
                · <?php echo $a['sequential_lock'] ? 'Opens in order' : 'Always open'; ?> · <?php echo $a['close_after_due'] ? 'Closes at the due time' : 'Late work accepted'; ?></div>
        </div>
        <div class="d-flex gap-2 align-items-start flex-wrap">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-dark btn-sm">Edit</a>
            <a href="<?php echo $self; ?>&export=csv" class="btn btn-outline-dark btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
            <?php if ($a['status'] === 'draft'): ?><a href="edit.php?id=<?php echo $id; ?>" class="btn btn-brand btn-sm">Publish…</a><?php endif; ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php foreach (['submitted' => 'submitted', 'graded' => 'graded', 'returned' => 'returned', 'in_progress' => 'in progress', 'open' => 'open', 'locked' => 'locked', 'closed' => 'closed', 'extension' => 'extension asked', 'skipped' => 'skipped'] as $st => $lbl): ?>
            <?php if (!empty($counts[$st])) echo asg_chip($st, $counts[$st] . ' ' . $lbl); ?>
        <?php endforeach; ?>
        <?php if (!$rows): ?><span class="small text-muted">No students on this assignment yet.</span><?php endif; ?>
    </div>
</div>

<div class="card-tutor mb-3">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Status</th><th>Date</th><th>Note</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $uid => $r): $i = $r['item']; $s = $r['sub']; $q = $requests[$uid] ?? null; ?>
            <tr>
                <td><a href="progress.php?student=<?php echo (int) $uid; ?>&course=<?php echo (int) $a['course_id']; ?>" class="fw-semibold text-decoration-none text-dark"><?php echo asg_e($r['student']['full_name']); ?></a>
                    <div class="small text-muted"><?php echo asg_e($r['student']['registration_no']); ?></div></td>
                <td><?php echo asg_chip($i['state'], $i['state'] === 'graded' ? 'Graded ' . AssignmentModel::resultText($i + ['max_score' => $a['max_score']]) : null); ?>
                    <?php if (!empty($s['is_late'])): ?><span class="asg-chip st-returned ms-1"><i class="fas fa-clock"></i>Late</span><?php endif; ?></td>
                <td class="small">
                    <?php if ($s && $s['submitted_at']): echo asg_e(AssignmentModel::local($s['submitted_at'], 'D j M, H:i'));
                    elseif ($i['state'] === 'closed'): ?>Due date passed
                    <?php elseif ($i['state'] === 'in_progress'): ?>Draft saved
                    <?php elseif ($q): echo asg_e(AssignmentModel::local($q['created_at'], 'D j M, H:i'));
                    else: echo asg_e(AssignmentModel::dueText($i['due'])); endif; ?>
                    <?php if ($i['current_due_at']): ?><div class="text-muted">Own due: <?php echo asg_e(AssignmentModel::local($i['current_due_at'], 'D j M, H:i')); ?></div><?php endif; ?>
                </td>
                <td class="small"><?php
                    if ($q && $q['type'] === 'extension') echo 'Wants ' . asg_e(AssignmentModel::local($q['requested_due_at'], 'D j M, H:i'));
                    elseif ($q) echo 'Asked to unlock';
                    elseif ($s && $s['version'] > 1 && $s['submitted_at']) echo asg_e(AssignmentModel::contentSummary($s)) . ' · version ' . (int) $s['version'];
                    else echo asg_e(AssignmentModel::contentSummary($s)); ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($s && $s['submitted_at']): ?>
                        <a href="../submissions/grade.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm <?php echo $i['state'] === 'submitted' ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $i['state'] === 'submitted' ? 'Grade' : 'Open'; ?></a>
                    <?php endif; ?>
                    <?php if ($q): ?><a href="../submissions/request.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-sm btn-outline-success">Decide</a><?php endif; ?>
                    <?php if ($i['state'] === 'closed'): ?>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#dueModal" data-action="unlock" data-student="<?php echo (int) $uid; ?>" data-name="<?php echo asg_e($r['student']['full_name']); ?>">Unlock</button>
                    <?php elseif (in_array($i['state'], ['open', 'in_progress', 'extension'], true)): ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dueModal" data-action="extend" data-student="<?php echo (int) $uid; ?>" data-name="<?php echo asg_e($r['student']['full_name']); ?>">Extend</button>
                    <?php endif; ?>
                    <?php if (in_array($i['state'], ['closed', 'open', 'in_progress', 'extension', 'locked'], true)): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Skip this assignment for this student? Their next assignment will open.')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="skip"><input type="hidden" name="student" value="<?php echo (int) $uid; ?>">
                            <button class="btn btn-sm btn-link text-muted">Skip</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-tutor p-3">
            <div class="asg-section mt-0">Description</div><p><?php echo nl2br(asg_e((string) $a['description'])); ?></p>
            <div class="asg-section">Details</div><div class="asg-details"><?php echo $a['details'] ?: nl2br(asg_e((string) $a['instructions'])); ?></div>
            <?php if ($qs = $am->questions($id)): ?><div class="asg-section">Questions</div>
                <?php foreach ($qs as $n => $qq): ?><div class="asg-box mb-2"><strong>Q<?php echo $n + 1; ?></strong> <?php echo nl2br(asg_e($qq['text'])); ?></div><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($atts = $am->attachments($id)): ?><div class="asg-section">Attachments</div>
                <?php foreach ($atts as $att): ?><a class="asg-pill" target="_blank" rel="noopener noreferrer" href="<?php echo asg_e($att['kind'] === 'link' ? $att['url'] : asg_files_url('att', (int) $att['id'])); ?>"><i class="fas <?php echo $att['kind'] === 'link' ? 'fa-link' : ($att['kind'] === 'pdf' ? 'fa-file-pdf' : 'fa-image'); ?>"></i><?php echo asg_e($att['label']); ?></a><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($a['details'] && $a['instructions']): ?><div class="asg-section">Instructions</div><div class="asg-box"><?php echo nl2br(asg_e($a['instructions'])); ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-tutor p-3 mb-3">
            <div class="asg-section mt-0">History</div>
            <ul class="list-unstyled small mb-0" style="max-height:260px;overflow:auto">
                <?php foreach ($am->events($id, null, 40) as $ev): if ($ev['event'] === 'email') continue; ?>
                    <li class="mb-1"><span class="text-muted"><?php echo asg_e(AssignmentModel::local($ev['created_at'], 'j M H:i')); ?></span> · <?php echo asg_e(str_replace('_', ' ', $ev['event'])); ?>
                        <?php if ($ev['student_user_id']): ?>· <?php echo asg_e($am->student((int) $ev['student_user_id'])['full_name']); ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card-tutor p-3">
            <form method="POST" onsubmit="return confirm('<?php echo $am->hasSubmissions($id) ? 'Archive this assignment? Students will no longer see it. Grades are kept.' : 'Delete this assignment for good?'; ?>')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $am->hasSubmissions($id) ? 'archive' : 'delete'; ?>">
                <button class="btn btn-sm btn-outline-danger w-100"><i class="fas <?php echo $am->hasSubmissions($id) ? 'fa-box-archive' : 'fa-trash'; ?> me-1"></i><?php echo $am->hasSubmissions($id) ? 'Archive assignment' : 'Delete assignment'; ?></button>
            </form>
            <div class="small text-muted mt-2">An assignment can be deleted only while nobody has submitted. After that it is archived, never lost.</div>
        </div>
    </div>
</div>

<div class="modal fade" id="dueModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="action"><input type="hidden" name="student">
    <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label fw-semibold">New due date and time <span class="text-muted fw-normal">(East Africa Time)</span></label>
        <input type="datetime-local" name="due" class="form-control" required value="<?php echo asg_e($defaultUnlock); ?>">
        <label class="form-label fw-semibold mt-3">Note to the student <span class="text-muted fw-normal">(optional)</span></label>
        <textarea name="note" class="form-control" rows="2"></textarea>
        <div class="small text-muted mt-2">This changes the due date for this student only. They are emailed.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save</button></div>
</form></div></div>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
document.getElementById('dueModal').addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget, f = this.querySelector('form');
    f.querySelector('[name=action]').value = b.dataset.action; f.querySelector('[name=student]').value = b.dataset.student;
    this.querySelector('.modal-title').textContent = (b.dataset.action === 'unlock' ? 'Unlock for ' : 'Extend the due date for ') + b.dataset.name;
});
</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
