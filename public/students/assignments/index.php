<?php
// Student: My assignments (spec 8.6), one block per course and tutor, in order, with locks; plus results and average.
require __DIR__ . '/../../components/assignments/student_boot.php';

use Models\AssignmentModel;

$groups = [];
foreach ($am->chain($uid) as $it) {
    $key = $it['tutor_id'] . '-' . (int) $it['course_id'];
    if (!isset($groups[$key])) {
        $t = $am->tutorById((int) $it['tutor_id']);
        $groups[$key] = ['course' => $it['course_title'] ?: 'My course', 'tutor' => $t['full_name'] ?? 'Your tutor', 'items' => []];
    }
    $groups[$key]['items'][] = $it;
}
$page_title = 'My assignments';
ob_start();
?>
<?php if (!$groups): ?>
<div class="card-student"><div class="as-empty"><i class="fas fa-file-pen"></i>No assignments yet. When your tutor gives you one, it appears here and you get an email.</div></div>
<?php endif; ?>

<?php foreach ($groups as $g):
    $done = count(array_filter($g['items'], fn($x) => in_array($x['state'], ['submitted', 'returned', 'graded'], true)));
    $total = count($g['items']);
    $marks = array_filter($g['items'], fn($x) => $x['state'] === 'graded' && $x['grading_mode'] === 'marks' && $x['score'] !== null && (float) $x['max_score'] > 0);
    $avg = $marks ? round(array_sum(array_map(fn($x) => (float) $x['score'] / (float) $x['max_score'] * 100, $marks)) / count($marks)) : null; ?>
<div class="card-student p-3 p-md-4 mb-3">
    <h5 class="mb-0">My assignments</h5>
    <div class="small text-muted mb-2"><?php echo asg_e($g['course']); ?> &nbsp;|&nbsp; Tutor: <?php echo asg_e($g['tutor']); ?></div>
    <div class="asg-bigbar mb-3" role="progressbar" aria-valuenow="<?php echo $done; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total; ?>">
        <div style="width:<?php echo $total ? max(14, round($done / $total * 100)) : 0; ?>%"><?php echo $done; ?> of <?php echo $total; ?> done</div></div>

    <?php foreach ($g['items'] as $it):
        $st = $it['state'];
        $href = 'view.php?id=' . (int) $it['id'];
        $cls = ['open' => 'is-open', 'in_progress' => 'is-open', 'extension' => 'is-open', 'returned' => 'is-open', 'locked' => 'is-locked', 'closed' => 'is-closed'][$st] ?? ''; ?>
        <div class="asg-card <?php echo $cls; ?>">
            <?php if ($st === 'locked'): ?>
                <div class="num"><i class="fas fa-lock" aria-hidden="true"></i></div>
                <div class="flex-grow-1"><div class="k">Assignment <?php echo (int) $it['index']; ?></div>
                    <div class="s">Submit Assignment <?php echo (int) $it['prev_index']; ?> to unlock this one.</div></div>
                <span class="small fw-bold text-muted">LOCKED</span>
            <?php else: ?>
                <div class="num <?php echo in_array($st, ['graded', 'submitted'], true) ? 'done' : ''; ?>">
                    <?php echo in_array($st, ['graded', 'submitted'], true) ? '<i class="fas fa-check"></i>' : ($st === 'closed' ? '<i class="fas fa-lock"></i>' : ($st === 'skipped' ? '<i class="fas fa-forward"></i>' : (int) $it['index'])); ?></div>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="k">Assignment <?php echo (int) $it['index']; ?></div>
                    <div class="t text-truncate"><?php echo asg_e($it['title']); ?></div>
                    <div class="s"><?php
                        switch ($st) {
                            case 'graded': echo 'Graded ' . asg_e(AssignmentModel::local($it['result_published_at'], 'D j M')); break;
                            case 'submitted': echo 'Submitted ' . asg_e(AssignmentModel::local($it['submitted_at'], 'D j M')) . '. Waiting for your tutor to grade it.'; break;
                            case 'returned': echo 'Your tutor asked for changes. Read the comments and resubmit.'; break;
                            case 'closed': echo 'The due date has passed. Ask your tutor to unlock it.'; break;
                            case 'skipped': echo 'Your tutor skipped this one for you.'; break;
                            case 'extension': echo asg_e(AssignmentModel::dueText($it['due'])) . ' · Extension asked, waiting for your tutor.'; break;
                            default: echo asg_e(AssignmentModel::dueText($it['due'])) . ($it['late'] ? ' · Late work is still accepted.' : '') . ($st === 'in_progress' ? ' · Draft saved.' : '');
                        } ?></div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                    <?php if ($st === 'graded'): ?>
                        <span class="asg-chip st-graded"><?php echo asg_e(AssignmentModel::resultText($it)); ?></span>
                        <a href="result.php?id=<?php echo (int) $it['id']; ?>" class="btn btn-sm btn-brand">View result</a>
                    <?php elseif ($st === 'submitted'): ?>
                        <?php echo asg_chip('submitted'); ?><a href="<?php echo $href; ?>" class="btn btn-sm btn-outline-secondary">Open</a>
                    <?php elseif ($st === 'returned'): ?>
                        <?php echo asg_chip('returned'); ?><a href="<?php echo $href; ?>" class="btn btn-sm btn-brand">Resubmit</a>
                    <?php elseif ($st === 'closed'): ?>
                        <?php echo asg_chip('closed'); ?><a href="<?php echo $href; ?>" class="btn btn-sm btn-outline-danger">Ask to unlock</a>
                    <?php elseif ($st === 'skipped'): ?>
                        <?php echo asg_chip('skipped'); ?>
                    <?php else: ?>
                        <?php if ($st === 'extension'): echo asg_chip('extension'); elseif ($it['due'] && !$it['late']): ?><a href="<?php echo $href; ?>#extension" class="btn btn-sm btn-outline-success">Request extension</a><?php endif; ?>
                        <a href="<?php echo $href; ?>" class="btn btn-sm btn-brand">Open</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <p class="small text-muted fst-italic mb-0">A locked assignment shows no details. It opens as soon as the one before it is submitted.</p>

    <?php $results = array_filter($g['items'], fn($x) => $x['state'] === 'graded'); if ($results): ?>
    <details class="mt-3">
        <summary class="fw-semibold">My results<?php echo $avg !== null ? ' · average ' . $avg . '%' : ''; ?></summary>
        <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>Assignment</th><th>Result</th><th></th></tr></thead>
            <tbody><?php foreach ($results as $r): ?>
                <tr><td>A<?php echo (int) $r['index']; ?>: <?php echo asg_e($r['title']); ?></td><td><?php echo asg_e(AssignmentModel::resultText($r)); ?></td>
                    <td class="text-end"><a href="result.php?id=<?php echo (int) $r['id']; ?>">View</a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
