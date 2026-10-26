<?php
// Tutor: the capstone list for all courses and intakes. Open now, Edit, Track, Close, Results, Copy to next intake.
require __DIR__ . '/../../components/capstone/tutor_boot.php';

use Models\AssignmentModel;

$courses = $am->tutorCourses($tutorId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back('index.php');
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'open_now':
            $r = cap_open_now($pdo, $id, $tutorId, $me);
            AssignmentNotifier::flush();
            $r['ok'] ? asg_back('index.php', 'It is open now. ' . $r['told'] . ' student' . ($r['told'] === 1 ? ' was' : 's were') . ' told.') : asg_back('index.php', null, $r['error']);
        case 'close':
            $r = cap_close($pdo, $id, $tutorId, $me);
            $r['ok'] ? asg_back('index.php?tab=closed', 'Closed. Students can no longer change their work. Marks and history are kept.') : asg_back('index.php', null, $r['error']);
        case 'copy_next':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if (!isset($courses[$courseId])) asg_back('index.php?tab=closed', null, 'Choose one of your courses to copy into.');
            $new = cap_copy($pdo, $id, $tutorId, $me, $courseId, (int) ($_POST['intake_id'] ?? 0) ?: null);
            $new ? asg_back('edit.php?id=' . $new, 'Copied as a new draft. Everything is copied except the dates, the students, the files students sent and the marks. Set them now.') : asg_back('index.php', null, 'Capstone not found.');
        case 'use_template':
            $new = cap_copy($pdo, $id, $tutorId, $me);
            $new ? asg_back('edit.php?id=' . $new, 'New draft from your capstone. Choose the course, the dates and the students.') : asg_back('index.php', null, 'Choose one of your capstones to copy.');
    }
    asg_back('index.php');
}

$tabs = ['all' => 'All', 'open' => 'Open', 'scheduled' => 'Scheduled', 'drafts' => 'Drafts', 'closed' => 'Closed'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';
$rows = cap_list_for_tutor($pdo, $tutorId, $tab);
$all = cap_list_for_tutor($pdo, $tutorId, 'all');
$intakes = [];
try {
    require_once __DIR__ . '/../../../app/v1/services/IntakeService.php';
    if (ik_installed($pdo)) foreach (ik_intakes($pdo) as $i) $intakes[(int) $i['id']] = $i['name'];
} catch (Throwable $e) {
}
$toMark = cap_to_mark_count($pdo, $tutorId);
$page_title = 'Capstone';
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-1">Capstone</h4>
        <div class="text-muted small">The final project of a course. Set it up early: students see nothing until you open it.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($all): ?><button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#tplModal"><i class="fas fa-copy me-1"></i>Use a template</button><?php endif; ?>
        <a href="edit.php" class="btn btn-brand btn-sm"><i class="fas fa-plus me-1"></i>New capstone</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="asg-tabs d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($tabs as $k => $label): ?>
                <a class="btn btn-sm <?php echo $tab === $k ? 'btn-dark' : 'btn-outline-dark'; ?>" href="index.php?tab=<?php echo $k; ?>"><?php echo asg_e($label); ?></a>
            <?php endforeach; ?>
            <?php if ($toMark): ?><span class="asg-chip st-submitted ms-auto"><i class="fas fa-clipboard-check"></i><?php echo $toMark; ?> to mark</span><?php endif; ?>
        </div>

        <?php if (!$rows): ?>
            <div class="card-tutor p-4 text-center text-muted">
                <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                <div><?php echo $tab === 'all' ? 'You have no capstones yet.' : 'Nothing here.'; ?></div>
                <a href="edit.php" class="btn btn-brand btn-sm mt-3">Set up your first capstone</a>
            </div>
        <?php endif; ?>

        <?php foreach ($rows as $c): ?>
            <div class="card-tutor p-3 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold"><?php echo asg_e($c['title']); ?> <?php echo cap_chip($c['status']); ?></div>
                        <div class="small text-muted">
                            <?php echo asg_e($c['course_title'] ?: 'No course'); ?><?php echo !empty($c['intake_name']) ? ' · ' . asg_e($c['intake_name']) : ''; ?>
                            · <?php if ($c['status'] === 'scheduled' && $c['opens_at']): ?>Opens <?php echo asg_e(cap_local($c['opens_at'], 'D j M, H:i')); ?> EAT
                              <?php elseif ($c['status'] === 'draft'): ?>Hidden from students
                              <?php else: ?>Final due <?php echo asg_e($c['due_at'] ? cap_local($c['due_at'], 'D j M, H:i') : 'not set'); ?> EAT<?php endif; ?>
                            · <?php echo $c['mode'] === 'team' ? 'Team' : 'Individual'; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-5"><?php echo (int) $c['sent']; ?> / <?php echo (int) $c['total']; ?></div>
                        <div class="small text-muted">sent / students</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($c['status'] !== 'open'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Open it now? Students will see it and be told.')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="open_now"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <button class="btn btn-brand btn-sm"><i class="fas fa-lock-open me-1"></i>Open now</button></form>
                    <?php endif; ?>
                    <a class="btn btn-outline-dark btn-sm" href="edit.php?id=<?php echo (int) $c['id']; ?>">Edit</a>
                    <a class="btn btn-outline-dark btn-sm" href="track.php?id=<?php echo (int) $c['id']; ?>">Track</a>
                    <?php if ($c['status'] === 'open'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Close the capstone? Students can no longer change their work.')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <button class="btn btn-outline-danger btn-sm">Close</button></form>
                    <?php endif; ?>
                    <a class="btn btn-outline-dark btn-sm" href="results.php?id=<?php echo (int) $c['id']; ?>">Results</a>
                    <?php if ($c['status'] === 'closed'): ?>
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#copyModal" data-id="<?php echo (int) $c['id']; ?>"
                                data-title="<?php echo asg_e($c['title']); ?>"><i class="fas fa-copy me-1"></i>Copy to next intake</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-4">
        <div class="cap-rules mb-3">
            <div class="fw-bold mb-1"><i class="fas fa-scale-balanced me-1"></i>The rules (fixed)</div>
            <ul class="mb-2 ps-3">
                <li>Marked out of <strong>100</strong>: Project, Documentation, Presentation, Time keeping.</li>
                <li>Pass mark <strong>50</strong>.</li>
                <li>Once sent, it is final unless you return it.</li>
                <li>You can return it <strong>2 times</strong>, always with instructions.</li>
                <li>A failed capstone can be retaken up to <strong>5 times</strong>, and the student <strong>pays again</strong>.</li>
                <li>The admin sees only a status word.</li>
            </ul>
            <div class="cap-bands"><span>Distinction 70+</span><span>Credit 60–69</span><span>Pass 50–59</span><span class="f">Fail below 50</span></div>
        </div>
        <div class="card-tutor p-3 small text-muted">
            Also in your portal: open <strong>Class Calendar</strong> to see presentation slots, and <strong>My Students</strong> to mark a course finished.
        </div>
    </div>
</div>

<!-- Use a template: copy one of your capstones as a new draft -->
<div class="modal fade" id="tplModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="use_template">
    <div class="modal-header"><h5 class="modal-title">Use a template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Start from one of your capstones. The brief, instructions, what students send, milestones and marks are copied. Dates, students and marks are not.</p>
        <select name="id" class="form-select" required>
            <?php foreach ($all as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo asg_e($c['title'] . ' (' . ($c['course_title'] ?: 'no course') . ')'); ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="modal-footer"><button class="btn btn-brand">Make a draft</button></div>
</form></div></div>

<!-- Copy a closed capstone to the next intake -->
<div class="modal fade" id="copyModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="copy_next"><input type="hidden" name="id" id="copyId">
    <div class="modal-header"><h5 class="modal-title">Copy to the next intake</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted" id="copyTitle"></p>
        <label class="form-label small fw-semibold">Course</label>
        <select name="course_id" class="form-select mb-2" required>
            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>"><?php echo asg_e($ct); ?></option><?php endforeach; ?>
        </select>
        <?php if ($intakes): ?>
        <label class="form-label small fw-semibold">Intake</label>
        <select name="intake_id" class="form-select"><option value="">Not set</option>
            <?php foreach ($intakes as $iid => $in): ?><option value="<?php echo (int) $iid; ?>"><?php echo asg_e($in); ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <p class="small text-muted mt-3 mb-0">Everything is copied except the dates, the students, the files students sent and the marks.</p>
    </div>
    <div class="modal-footer"><button class="btn btn-brand">Copy as a draft</button></div>
</form></div></div>
<script>
document.getElementById('copyModal').addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget; document.getElementById('copyId').value = b.dataset.id; document.getElementById('copyTitle').textContent = b.dataset.title;
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
