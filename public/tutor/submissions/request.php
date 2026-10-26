<?php
// Tutor: decide one extension or unlock request (spec 8.9). Approving sets that student's own due date.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$reqId = (int) ($_GET['id'] ?? 0);
$q = $am->request($reqId);
$a = $q ? $am->getForTutor((int) $q['assignment_id'], $tutorId) : null;
if (!$q || !$a || !$am->isTutorStudent($tutorId, (int) $q['student_user_id'])) asg_back('index.php?tab=requests', null, 'Request not found.');
$self = 'request.php?id=' . $reqId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $approve = ($_POST['decision'] ?? '') === 'approve';
    $due = AssignmentModel::toUtc($_POST['due'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    $r = $am->decide($reqId, $tutorId, $approve, $due, $note, $me);
    if (!$r['ok']) asg_back($self, null, $r['error']);
    (new AssignmentNotifier($pdo, $am))->decided($a, (int) $q['student_user_id'], $q['type'], $approve, $due, $note ?: null);
    asg_back('index.php?tab=requests', ($approve ? 'Approved' : 'Declined') . '. The student has been emailed.');
}

$student = $am->student((int) $q['student_user_id']);
$rec = $am->recipient((int) $a['id'], (int) $q['student_user_id']);
$origDue = $rec['current_due_at'] ?: $a['due_at'];
$suggest = $q['type'] === 'extension' && $q['requested_due_at'] && AssignmentModel::ts($q['requested_due_at']) > time()
    ? $q['requested_due_at'] : gmdate('Y-m-d H:i:s', time() + 48 * 3600);
$page_title = $q['type'] === 'unlock' ? 'Unlock request' : 'Extension request';
ob_start();
?>
<a href="index.php?tab=requests" class="btn btn-link btn-sm ps-0 mb-2">‹ Requests</a>
<div class="row"><div class="col-lg-6">
<div class="card-tutor p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?php echo asg_e($page_title); ?></h5>
        <?php echo $q['status'] === 'pending' ? '<span class="asg-chip st-extension"><i class="fas fa-hourglass-half"></i>Pending</span>' : asg_chip($q['status'] === 'approved' ? 'submitted' : 'closed', ucfirst($q['status'])); ?>
    </div>
    <div class="fw-semibold"><?php echo asg_e($student['full_name']); ?></div>
    <div class="small text-muted mb-3"><?php echo asg_e($a['course_title'] ?: 'No course'); ?></div>
    <dl class="row small mb-2">
        <dt class="col-4 text-muted fw-normal">Assignment</dt><dd class="col-8 fw-semibold"><a href="../assignments/view.php?id=<?php echo (int) $a['id']; ?>"><?php echo asg_e($a['title']); ?></a></dd>
        <dt class="col-4 text-muted fw-normal">Original due</dt><dd class="col-8 fw-semibold"><?php echo asg_e(AssignmentModel::local($origDue)); ?></dd>
        <?php if ($q['type'] === 'extension'): ?><dt class="col-4 text-muted fw-normal">Asks for</dt><dd class="col-8 fw-semibold"><?php echo asg_e(AssignmentModel::local($q['requested_due_at'])); ?></dd><?php endif; ?>
        <dt class="col-4 text-muted fw-normal">Asked on</dt><dd class="col-8"><?php echo asg_e(AssignmentModel::local($q['created_at'])); ?></dd>
    </dl>
    <p class="small"><?php if ($q['reason_code']): ?>Reason: <?php echo asg_e(AssignmentModel::REASONS[$q['reason_code']] ?? 'Other'); ?>. <?php endif; ?><?php echo nl2br(asg_e((string) $q['reason_text'])); ?></p>

    <?php if ($q['status'] === 'pending'): ?>
    <form method="POST"><?php echo csrf_field(); ?>
        <label class="form-label small">Approve until <span class="text-muted">(East Africa Time)</span></label>
        <input type="datetime-local" name="due" class="form-control mb-2" value="<?php echo asg_e(AssignmentModel::localInput($suggest)); ?>">
        <label class="form-label small">Note to the student <span class="text-muted">(required if you decline)</span></label>
        <textarea name="note" class="form-control mb-3" rows="2"></textarea>
        <div class="row g-2">
            <div class="col-5"><button name="decision" value="decline" class="btn btn-outline-danger w-100" onclick="var n=this.form.note;if(!n.value.trim()){alert('Write a short note so the student knows why.');n.focus();return false;}">Decline</button></div>
            <div class="col-7"><button name="decision" value="approve" class="btn btn-brand w-100">Approve</button></div>
        </div>
        <?php if ($q['type'] === 'extension'): ?><p class="small text-muted mt-2 mb-0">If you decline and the original due time has passed, the assignment closes for this student at once.</p><?php endif; ?>
    </form>
    <?php else: ?>
        <p class="small text-muted mb-0">Decided <?php echo asg_e(AssignmentModel::local($q['decided_at'])); ?><?php echo $q['new_due_at'] ? ', new due date ' . asg_e(AssignmentModel::local($q['new_due_at'])) : ''; ?>.
            <?php echo $q['decision_note'] ? 'Note: ' . asg_e($q['decision_note']) : ''; ?></p>
    <?php endif; ?>
</div>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
