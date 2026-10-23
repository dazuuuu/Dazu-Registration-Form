<?php
// Tutor: every student across every assignment of a course (spec 8.4), or one student's full progress (?student=).
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$courses = $am->tutorCourses($tutorId);
$hasNoCourse = (bool) array_filter($am->listAll($tutorId), fn($a) => !$a['course_id']);
$courseId = isset($_GET['course']) && $_GET['course'] !== '' ? (int) $_GET['course'] : (int) (array_key_first($courses) ?? 0);
$studentId = (int) ($_GET['student'] ?? 0);
$legend = ['graded', 'submitted', 'returned', 'in_progress', 'open', 'locked', 'closed', 'extension', 'skipped'];
$cellLink = fn(array $item) => $item['sub_id'] && $item['submitted_at'] ? '../submissions/grade.php?id=' . (int) $item['sub_id'] : 'view.php?id=' . (int) $item['id'];

if ($studentId) {
    if (!$am->isTutorStudent($tutorId, $studentId)) asg_back('progress.php', null, 'That student is not one of yours.');
    $student = $am->student($studentId);
    $items = $am->chain($studentId, $tutorId, null, true);
    $page_title = 'Progress: ' . $student['full_name'];
} else {
    $grid = $am->progress($tutorId, $courseId);
    $filter = $_GET['status'] ?? '';
    $rows = $grid['rows'];
    if ($filter !== '' && isset(AssignmentModel::STATES[$filter])) {
        $rows = array_filter($rows, fn($r) => (bool) array_filter($r['cells'], fn($c) => $c['state'] === $filter));
    }
    if (($_GET['sort'] ?? '') === 'least') {
        uasort($rows, fn($x, $y) => ($x['total'] ? $x['done'] / $x['total'] : 0) <=> ($y['total'] ? $y['done'] / $y['total'] : 0));
    }
    $page_title = 'Student progress';
}
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>

<?php if ($studentId): ?>
<div class="card-tutor p-3 p-md-4">
    <h5 class="mb-1"><?php echo asg_e($student['full_name']); ?></h5>
    <div class="small text-muted mb-3"><?php echo asg_e($student['registration_no']); ?> · <?php echo count($items); ?> assignment<?php echo count($items) === 1 ? '' : 's'; ?> from you</div>
    <?php if (!$items): ?><p class="text-muted mb-0">No published assignments have been given to this student yet.</p><?php endif; ?>
    <?php $group = null; foreach ($items as $it): if ($group !== (int) $it['course_id']): $group = (int) $it['course_id']; ?>
        <div class="asg-section"><?php echo asg_e($it['course_title'] ?: 'No course'); ?></div>
    <?php endif; ?>
        <div class="d-flex flex-wrap align-items-center gap-3 py-2 border-bottom">
            <?php echo asg_square($it, $cellLink($it)); ?>
            <div class="flex-grow-1" style="min-width:180px"><div class="fw-semibold">A<?php echo (int) $it['index']; ?>: <?php echo asg_e($it['title']); ?></div>
                <div class="small text-muted"><?php echo asg_e(AssignmentModel::dueText($it['due'])); ?><?php echo $it['submitted_at'] ? ' · sent ' . asg_e(AssignmentModel::local($it['submitted_at'], 'D j M, H:i')) : ''; ?></div></div>
            <?php echo asg_chip($it['state'], $it['state'] === 'graded' ? 'Graded ' . AssignmentModel::resultText($it) : null); ?>
            <a href="view.php?id=<?php echo (int) $it['id']; ?>" class="btn btn-sm btn-outline-secondary">Assignment</a>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="card-tutor p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h5 class="mb-0 d-inline">Student progress</h5>
            <span class="small text-muted ms-2"><?php echo asg_e($courses[$courseId] ?? 'No course'); ?> · <?php echo count($grid['rows']); ?> students · <?php echo count($grid['assignments']); ?> assignments</span></div>
        <form class="d-flex flex-wrap gap-2" method="GET">
            <select name="course" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === $courseId ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?>
                <?php if ($hasNoCourse): ?><option value="0" <?php echo $courseId === 0 ? 'selected' : ''; ?>>No course</option><?php endif; ?>
            </select>
            <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Any status</option>
                <?php foreach ($legend as $st): ?><option value="<?php echo $st; ?>" <?php echo $filter === $st ? 'selected' : ''; ?>>Has: <?php echo asg_e(AssignmentModel::STATES[$st][0]); ?></option><?php endforeach; ?>
            </select>
            <select name="sort" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <option value="">Sort by name</option><option value="least" <?php echo ($_GET['sort'] ?? '') === 'least' ? 'selected' : ''; ?>>Least progress first</option>
            </select>
        </form>
    </div>
    <div class="asg-legend mb-3">
        <?php foreach ($legend as $st): ?><span><?php echo asg_square(['state' => $st, 'index' => '', 'score' => null] + ['grading_mode' => 'marks', 'max_score' => 0, 'grade_label' => '']); ?><?php echo asg_e(AssignmentModel::STATES[$st][0]); ?></span><?php endforeach; ?>
    </div>
    <?php if (!$grid['assignments']): ?>
        <p class="text-muted mb-0">No published assignments in this course yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table asg-grid align-middle mb-0">
            <thead><tr><th>Student</th>
                <?php foreach ($grid['assignments'] as $n => $ga): ?><th><a href="view.php?id=<?php echo (int) $ga['id']; ?>" class="text-decoration-none text-muted" title="<?php echo asg_e($ga['title']); ?>">A<?php echo $n + 1; ?></a></th><?php endforeach; ?>
                <th>Done</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $uid => $r): ?>
                <tr>
                    <td><a href="?student=<?php echo (int) $uid; ?>" class="fw-semibold text-dark text-decoration-none"><?php echo asg_e($r['student']['full_name']); ?></a></td>
                    <?php foreach ($grid['assignments'] as $ga): $c = $r['cells'][(int) $ga['id']] ?? null; ?>
                        <td><?php echo $c ? asg_square($c, $cellLink($c)) : '<span class="text-muted small" title="Not given to this student">—</span>'; ?></td>
                    <?php endforeach; ?>
                    <td style="min-width:110px"><div class="d-flex align-items-center gap-2"><div class="asg-bar flex-grow-1"><i style="width:<?php echo $r['total'] ? round($r['done'] / $r['total'] * 100) : 0; ?>%"></i></div>
                        <span class="small fw-bold"><?php echo (int) $r['done']; ?>/<?php echo (int) $r['total']; ?></span></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted fst-italic mt-2 mb-0">Click any square to open that submission. Click a name to see one student's full progress. Hover a square for the marks.</p>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
