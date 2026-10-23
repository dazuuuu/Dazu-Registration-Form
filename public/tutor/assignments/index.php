<?php
// Tutor: every assignment, grouped by course, in the order students take them. Drag rows to reorder.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$courses = $am->tutorCourses($tutorId);
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
        case 'duplicate':
        case 'save_template':
        case 'use_template':
            $tpl = $_POST['action'] === 'use_template';
            $new = $am->copyAssignment($id, $tutorId, null, $_POST['action'] === 'save_template', $me, $_POST['action'] === 'duplicate' ? ' (copy)' : '');
            if (!$new) asg_back('index.php', null, 'Assignment not found.');
            if ($_POST['action'] === 'save_template') asg_back('index.php?tab=templates', 'Saved as a template.');
            asg_back('edit.php?id=' . $new, $tpl ? 'New draft from your template. Set the due date and who sees it.' : 'Copied as a new draft. Set the due date and who sees it.');
        case 'use_shared':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            $new = $am->fromSharedTemplate($id, $tutorId, isset($courses[$courseId]) ? $courseId : null, $me);
            $new ? asg_back('edit.php?id=' . $new, 'New draft from the Dazu template.') : asg_back('index.php', null, 'Template not found.');
        case 'copy_next':
            $courseId = (int) ($_POST['course_id'] ?? 0);
            if (!isset($courses[$courseId])) asg_back('index.php?tab=closed', null, 'Choose one of your courses to copy into.');
            $n = 0;
            foreach (array_map('intval', (array) ($_POST['ids'] ?? [])) as $cid) if ($am->copyAssignment($cid, $tutorId, $courseId, false, $me)) $n++;
            asg_back('index.php?tab=drafts', $n . ' assignment' . ($n === 1 ? '' : 's') . ' copied as drafts. Set the due dates and who sees them.');
        case 'release_now':
            $a = $am->getForTutor($id, $tutorId);
            if ($a && AssignmentModel::gradesHeld($a)) {
                $am->releaseGradesNow($id, $me);
                $n = (new AssignmentNotifier($pdo, $am))->gradesReleased($am->get($id));
                asg_back('index.php', 'Grades released. ' . $n . ' student' . ($n === 1 ? ' was' : 's were') . ' told.');
            }
            asg_back('index.php');
        case 'archive':
            if ($am->getForTutor($id, $tutorId)) $am->setArchived($id, true, $me);
            asg_back('index.php', 'Archived. Nothing was deleted; students no longer see it in their to-do.');
        case 'restore':
            if ($am->getForTutor($id, $tutorId)) $am->setArchived($id, false, $me);
            asg_back('index.php?tab=archived', 'Assignment restored.');
    }
    asg_back('index.php');
}

$tabs = ['all' => 'All', 'live' => 'Live', 'scheduled' => 'Scheduled', 'drafts' => 'Drafts', 'closed' => 'Closed', 'archived' => 'Archived', 'templates' => 'Templates'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : (!empty($_GET['archived']) ? 'archived' : 'all');
$showArchived = $tab === 'archived';
$tabOf = function (array $a): string {
    if ($a['status'] === 'archived') return 'archived';
    if ($a['status'] === 'draft') return !empty($a['publish_at']) ? 'scheduled' : 'drafts';
    return $a['due_at'] && AssignmentModel::ts($a['due_at']) <= time() ? 'closed' : 'live';
};
$all = $am->listAll($tutorId, true);
$tabCount = array_count_values(array_map($tabOf, $all));
$tabCount['templates'] = count($templatesMine = $am->listAll($tutorId, false, true));
$tabCount['all'] = count(array_filter($all, fn($x) => $x['status'] !== 'archived'));
$list = $tab === 'templates' ? $templatesMine : array_filter($all, fn($x) => $tab === 'all' ? $x['status'] !== 'archived' : $tabOf($x) === $tab);
$shared = $tab === 'templates' ? $am->sharedTemplates() : [];
$groups = [];
foreach ($list as $a) {
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
        <a href="records.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-lines me-1"></i>Student records</a>
                <a href="edit.php" class="btn btn-brand btn-sm"><i class="fas fa-plus me-1"></i>New assignment</a>
    </div>
</div>

<?php if (!$courses): ?>
<div class="alert alert-info">No students are assigned to you yet, so there is no course to set work for. An admin assigns students to tutors.</div>
<?php endif; ?>

<div class="asg-tabs d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($tabs as $k => $lbl): ?><a href="?tab=<?php echo $k; ?>" class="btn btn-sm <?php echo $tab === $k ? 'btn-brand' : 'btn-light'; ?>"><?php echo $lbl; ?><?php echo !empty($tabCount[$k]) ? ' ' . (int) $tabCount[$k] : ''; ?></a><?php endforeach; ?>
</div>
<?php if ($tab === 'templates'): ?>
<div class="card-tutor p-3 mb-3"><h6 class="fw-bold mb-1">Template library</h6><div class="small text-muted mb-2">Yours, and templates shared by Dazu. Use one to start a new draft.</div>
    <?php if (!$templatesMine && !$shared): ?><div class="small text-muted">No templates yet. Open any assignment and press Save as template.</div><?php endif; ?>
    <?php foreach ($shared as $t): ?>
        <form method="POST" class="d-flex align-items-center gap-2 py-1 border-bottom"><?php echo csrf_field(); ?><input type="hidden" name="action" value="use_shared"><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
            <span class="flex-grow-1 small fw-semibold"><?php echo asg_e($t['title']); ?> <span class="badge bg-light text-dark">Dazu</span></span>
            <select name="course_id" class="form-select form-select-sm w-auto"><?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>"><?php echo asg_e($ct); ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm btn-outline-secondary">Use</button></form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($tab === 'closed' && $list): ?>
<form method="POST" class="card-tutor p-3 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="copy_next">
    <h6 class="fw-bold mb-1">Copy to the next intake</h6><div class="small text-muted mb-2">Pick assignments to copy into a course. Due dates and who sees it are cleared so you set them again. Student work and marks are never copied.</div>
    <?php foreach ($list as $ca): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="ids[]" value="<?php echo (int) $ca['id']; ?>" id="cp<?php echo (int) $ca['id']; ?>"><label class="form-check-label" for="cp<?php echo (int) $ca['id']; ?>"><?php echo asg_e($ca['title']); ?> <span class="text-muted">· <?php echo asg_e($ca['course_title'] ?: 'No course'); ?></span></label></div><?php endforeach; ?>
    <div class="d-flex gap-2 mt-2"><select name="course_id" class="form-select form-select-sm w-auto"><?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>"><?php echo asg_e($ct); ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-brand">Copy selected</button></div>
</form>
<?php endif; ?>

<?php if (!$groups): ?>
<div class="card-tutor"><div class="as-empty"><i class="fas fa-file-pen"></i><?php echo $tab === 'all' ? 'No assignments yet. Press New assignment to create the first one.' : 'Nothing here.'; ?></div></div>
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
        <tbody data-reorder="<?php echo $tab === 'all' ? '1' : ''; ?>">
        <?php foreach ($g['items'] as $a): ?>
            <tr data-id="<?php echo (int) $a['id']; ?>" <?php echo $tab === 'all' ? 'draggable="true"' : ''; ?>>
                <td><?php if ($tab === 'all'): ?><i class="fas fa-grip-vertical asg-drag" title="Drag to reorder"></i><?php endif; ?></td>
                <td class="fw-semibold"><?php echo $tab === 'templates' ? '' : ((($a['kind'] ?? 'daily') === 'weekly' ? 'W' : 'A') . $am->number($a)); ?></td>
                <td style="min-width:200px">
                    <a href="view.php?id=<?php echo (int) $a['id']; ?>" class="fw-semibold text-decoration-none"><?php echo asg_e($a['title']); ?></a>
                    <?php if ($a['status'] === 'draft'): ?><span class="badge bg-secondary ms-1"><?php echo !empty($a['publish_at']) ? 'SCHEDULED ' . asg_e(\Models\AssignmentModel::local($a['publish_at'], 'D j M, H:i')) : 'DRAFT'; ?></span><?php endif; ?>
                    <?php if (($a['kind'] ?? 'daily') === 'weekly'): ?><span class="badge bg-light text-dark ms-1">WEEKLY</span><?php endif; ?>
                    <?php if (\Models\AssignmentModel::gradesHeld($a)): ?><span class="badge bg-info text-dark ms-1"><i class="fas fa-lock"></i> GRADES HELD</span><?php endif; ?>
                    <div class="small text-muted"><?php echo asg_e($a['types_text']); ?> · <?php echo asg_e(AssignmentModel::GRADING_MODES[$a['grading_mode']]); ?><?php echo $a['grading_mode'] === 'marks' ? ' out of ' . (int) $a['max_score'] : ''; ?></div>
                </td>
                <td class="small"><?php echo $a['due_at'] ? asg_e(AssignmentModel::local($a['due_at'])) : '—'; ?></td>
                <td><?php echo (int) $a['recipients']; ?></td>
                <td class="small">
                    <?php if ($a['awaiting']): ?><a href="../submissions/index.php?assignment=<?php echo (int) $a['id']; ?>" class="asg-chip st-submitted text-decoration-none"><i class="fas fa-inbox"></i><?php echo (int) $a['awaiting']; ?> to grade</a><?php endif; ?>
                    <?php if ($a['requests']): ?><a href="../submissions/index.php?tab=requests" class="asg-chip st-extension text-decoration-none"><i class="fas fa-hand"></i><?php echo (int) $a['requests']; ?> request<?php echo $a['requests'] > 1 ? 's' : ''; ?></a><?php endif; ?>
                    <div class="d-flex align-items-center gap-2"><div class="asg-bar flex-grow-1" style="min-width:60px"><i style="width:<?php echo $a['recipients'] ? round($a['submitted'] / $a['recipients'] * 100) : 0; ?>%"></i></div>
                        <span class="text-muted text-nowrap"><?php echo (int) $a['submitted']; ?>/<?php echo (int) $a['recipients']; ?> submitted</span></div>
                    <div class="text-muted"><?php echo (int) $a['graded']; ?> graded</div>
                </td>
                <td class="text-end text-nowrap">
                    <?php if ($showArchived): ?>
                        <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary">Restore</button></form>
                    <?php elseif ($tab === 'templates'): ?>
                        <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="use_template"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>"><button class="btn btn-sm btn-brand">Use</button></form>
                        <a href="edit.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <?php else: ?>
                        <?php if ((int) ($a['no_submission'] ?? 0) && $a['status'] === 'published'): ?><a href="marks.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-brand">Enter marks</a>
                        <?php elseif ($a['awaiting']): ?><a href="../submissions/index.php?assignment=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-brand">Grade</a><?php endif; ?>
                        <?php if ($a['status'] === 'published' && $a['recipients'] > $a['submitted'] && !(int) ($a['no_submission'] ?? 0)): ?><a href="view.php?id=<?php echo (int) $a['id']; ?>#notsent" class="btn btn-sm btn-outline-secondary">Nudge <?php echo (int) $a['recipients'] - (int) $a['submitted']; ?></a><?php endif; ?>
                        <?php if (AssignmentModel::gradesHeld($a)): ?><form method="POST" class="d-inline" onsubmit="return confirm('Release the grades now?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="release_now"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>"><button class="btn btn-sm btn-brand">Release grades now</button></form><?php endif; ?>
                        <a href="view.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-outline-secondary">Open</a>
                        <a href="edit.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <div class="dropdown d-inline"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="More">⋯</button>
                            <div class="dropdown-menu dropdown-menu-end p-1">
                                <?php foreach (['duplicate' => 'Duplicate', 'save_template' => 'Save as template', 'archive' => 'Archive'] as $act => $lbl): if ($act === 'archive' && $a['status'] === 'draft') continue; ?>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="<?php echo $act; ?>"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>"><button class="dropdown-item small"><?php echo $lbl; ?></button></form>
                                <?php endforeach; ?>
                            </div></div>
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
