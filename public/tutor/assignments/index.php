<?php
// Tutor: every assignment, grouped by course, in the order students take them. Drag rows to reorder.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back('index.php');
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'reorder':
            $ids = array_filter(array_map('intval', explode(',', (string) ($_POST['order'] ?? ''))));
            $own = array_map(fn($a) => (int) $a['id'], $am->listAll($tutorId));
            $am->reorder($tutorId, array_values(array_intersect($ids, $own)), $me);
            asg_back('index.php', 'New order saved. Students who already opened an assignment keep it.');
        case 'delete':
            if (!$am->getForTutor($id, $tutorId)) asg_back('index.php');
            $r = $am->deleteOrArchive($id, $me, true);
            asg_back('index.php', $r === 'deleted' ? 'Assignment deleted.' : 'Students have already submitted work, so the assignment was archived instead of deleted.');
        case 'restore':
            if ($am->getForTutor($id, $tutorId)) $am->setArchived($id, false, $me);
            asg_back('index.php?archived=1', 'Assignment restored.');
    }
    asg_back('index.php');
}

$showArchived = !empty($_GET['archived']);
$courses = $am->tutorCourses($tutorId);
$groups = [];
foreach ($am->listAll($tutorId, $showArchived) as $a) {
    if ($showArchived !== ($a['status'] === 'archived')) continue;
    $groups[(int) $a['course_id']]['title'] = $a['course_title'] ?: 'No course';
    $groups[(int) $a['course_id']]['items'][] = $a;
}
$page_title = 'Assignments';
ob_start();
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="small text-muted">Assignments are taken in order. The next one opens for a student when they submit the one before it.</div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="progress.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-table-cells me-1"></i>Student progress</a>
        <a href="?<?php echo $showArchived ? '' : 'archived=1'; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-box-archive me-1"></i><?php echo $showArchived ? 'Back to current' : 'Archived'; ?></a>
        <a href="edit.php" class="btn btn-brand btn-sm"><i class="fas fa-plus me-1"></i>New assignment</a>
    </div>
</div>

<?php if (!$courses): ?>
<div class="alert alert-info">No students are assigned to you yet, so there is no course to set work for. An admin assigns students to tutors.</div>
<?php endif; ?>

<?php if (!$groups): ?>
<div class="card-tutor"><div class="as-empty"><i class="fas fa-file-pen"></i><?php echo $showArchived ? 'No archived assignments.' : 'No assignments yet. Press New assignment to create the first one.'; ?></div></div>
<?php endif; ?>

<?php foreach ($groups as $cid => $g): ?>
<div class="card-tutor mb-3">
    <div class="head d-flex justify-content-between flex-wrap gap-2">
        <span><i class="fas fa-book-open me-1"></i><?php echo asg_e($g['title']); ?> <span class="small fw-normal">· <?php echo count($g['items']); ?> assignment<?php echo count($g['items']) > 1 ? 's' : ''; ?></span></span>
        <?php if (!$showArchived): ?><a class="small" href="progress.php?course=<?php echo $cid; ?>">Progress grid →</a><?php endif; ?>
    </div>
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th style="width:32px"></th><th>#</th><th>Assignment</th><th>Due</th><th>Students</th><th>Work</th><th></th></tr></thead>
        <tbody data-reorder="<?php echo $showArchived ? '' : '1'; ?>">
        <?php $n = 0; foreach ($g['items'] as $a): $n++; ?>
            <tr data-id="<?php echo (int) $a['id']; ?>" <?php echo $showArchived ? '' : 'draggable="true"'; ?>>
                <td><?php if (!$showArchived): ?><i class="fas fa-grip-vertical asg-drag" title="Drag to reorder"></i><?php endif; ?></td>
                <td class="fw-semibold">A<?php echo $n; ?></td>
                <td style="min-width:200px">
                    <a href="view.php?id=<?php echo (int) $a['id']; ?>" class="fw-semibold text-decoration-none"><?php echo asg_e($a['title']); ?></a>
                    <?php if ($a['status'] === 'draft'): ?><span class="badge bg-secondary ms-1">DRAFT</span><?php endif; ?>
                    <div class="small text-muted"><?php echo asg_e($a['types_text']); ?> · <?php echo asg_e(AssignmentModel::GRADING_MODES[$a['grading_mode']]); ?><?php echo $a['grading_mode'] === 'marks' ? ' out of ' . (int) $a['max_score'] : ''; ?></div>
                </td>
                <td class="small"><?php echo $a['due_at'] ? asg_e(AssignmentModel::local($a['due_at'])) : '—'; ?></td>
                <td><?php echo (int) $a['recipients']; ?></td>
                <td class="small">
                    <?php if ($a['awaiting']): ?><a href="../submissions/index.php?assignment=<?php echo (int) $a['id']; ?>" class="asg-chip st-submitted text-decoration-none"><i class="fas fa-inbox"></i><?php echo (int) $a['awaiting']; ?> to grade</a><?php endif; ?>
                    <?php if ($a['requests']): ?><a href="../submissions/index.php?tab=requests" class="asg-chip st-extension text-decoration-none"><i class="fas fa-hand"></i><?php echo (int) $a['requests']; ?> request<?php echo $a['requests'] > 1 ? 's' : ''; ?></a><?php endif; ?>
                    <div class="text-muted"><?php echo (int) $a['submitted']; ?> submitted · <?php echo (int) $a['graded']; ?> graded</div>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($showArchived): ?>
                        <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary">Restore</button></form>
                    <?php else: ?>
                        <a href="view.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-brand">Open</a>
                        <a href="edit.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endforeach; ?>

<form method="POST" id="reorderForm" class="d-none"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reorder"><input type="hidden" name="order"></form>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
// Drag rows within a course to change the order, then save it.
document.querySelectorAll('tbody[data-reorder="1"]').forEach(function (body) {
    var dragging = null;
    body.addEventListener('dragstart', function (e) { dragging = e.target.closest('tr'); dragging.classList.add('asg-dragging'); e.dataTransfer.effectAllowed = 'move'; });
    body.addEventListener('dragend', function () {
        if (!dragging) return;
        dragging.classList.remove('asg-dragging'); dragging = null;
        var ids = Array.prototype.map.call(body.querySelectorAll('tr[data-id]'), function (r) { return r.dataset.id; });
        var f = document.getElementById('reorderForm'); f.order.value = ids.join(','); f.submit();
    });
    body.addEventListener('dragover', function (e) {
        e.preventDefault();
        var over = e.target.closest('tr');
        if (!over || over === dragging || !dragging) return;
        var r = over.getBoundingClientRect();
        body.insertBefore(dragging, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
    });
});
</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
