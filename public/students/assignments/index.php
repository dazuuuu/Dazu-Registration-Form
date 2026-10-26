<?php
// Student: My assignments (spec 8.6), one block per course and tutor, in order, with locks; plus results and average.
require __DIR__ . '/../../components/assignments/student_boot.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentAverage.php';

use Models\AssignmentModel;

$filters = ['all' => 'All', 'todo' => 'To do', 'submitted' => 'Submitted', 'returned' => 'Returned', 'graded' => 'Graded'];
$filter = isset($filters[$_GET['f'] ?? '']) ? $_GET['f'] : 'all';
$kindF = isset(AssignmentModel::KINDS[$_GET['kind'] ?? '']) ? $_GET['kind'] : '';
$bucket = fn(array $x) => in_array($x['state'], ['open', 'in_progress', 'extension'], true) ? 'todo' : ($x['state'] === 'graded' ? 'graded' : ($x['state'] === 'returned' ? 'returned' : ($x['state'] === 'submitted' ? 'submitted' : 'other')));
$everything = $am->chain($uid);
$fCount = array_count_values(array_map($bucket, $everything));
$doneAll = count(array_filter($everything, fn($x) => in_array($x['state'], ['submitted', 'returned', 'graded'], true)));
$dueSoon = array_values(array_filter($everything, fn($x) => in_array($x['state'], ['open', 'in_progress', 'extension', 'returned'], true) && $x['due'] && AssignmentModel::ts($x['due']) > time() && AssignmentModel::ts($x['due']) < time() + 7 * 86400));
usort($dueSoon, fn($x, $y) => strcmp((string) $x['due'], (string) $y['due']));
$heldNotes = [];
foreach ($everything as $x) if (!empty($x['marks_held'])) $heldNotes[$x['release_grades_at']][] = (($x['kind'] ?? 'daily') === 'weekly' ? 'Week ' : 'Assignment ') . $x['index'];
/** "6 hours left" / "2 days left". */
$left = function (?string $due): string {
    $sec = AssignmentModel::ts($due) - time();
    if (!$due || $sec <= 0) return '';
    return $sec < 3600 ? max(1, (int) floor($sec / 60)) . ' minutes left' : ($sec < 86400 ? (int) floor($sec / 3600) . ' hours left' : (int) floor($sec / 86400) . ' days left');
};
$groups = [];
foreach ($everything as $it) {
    if ($filter !== 'all' && $bucket($it) !== $filter) continue;
    if ($kindF && ($it['kind'] ?? 'daily') !== $kindF) continue;
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
<div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-sm btn-brand" aria-current="page">My assignments</a>
    <a href="marks.php" class="btn btn-sm btn-outline-secondary">My marks</a>
</div>
<?php if ($everything): $pc = (int) round($doneAll / count($everything) * 100); ?>
<div class="row g-3 mb-3">
    <div class="col-md-5"><div class="card-student p-3 h-100 d-flex align-items-center gap-3">
        <div class="asg-ring" style="--p:<?php echo $pc; ?>;width:72px;height:72px;margin:0;font-weight:700"><span><?php echo $doneAll; ?>/<?php echo count($everything); ?></span></div>
        <div class="small"><div class="fw-semibold"><?php echo $doneAll; ?> of <?php echo count($everything); ?> assignments done</div>
            <?php if (!empty($fCount['returned'])): ?><div><?php echo (int) $fCount['returned']; ?> returned for you</div><?php endif; ?>
            <div><?php echo count($dueSoon); ?> due this week</div></div>
    </div></div>
    <div class="col-md-7"><div class="card-student p-3 h-100"><div class="fw-semibold mb-1">Due soon</div>
        <?php if (!$dueSoon): ?><div class="small text-muted">Nothing due in the next 7 days.</div><?php endif; ?>
        <?php foreach (array_slice($dueSoon, 0, 4) as $d): ?>
            <a href="view.php?id=<?php echo (int) $d['id']; ?>" class="d-flex justify-content-between small py-1 border-bottom text-decoration-none text-dark">
                <span><?php echo asg_e($d['title']); ?><span class="text-muted"> · <?php echo asg_e(AssignmentModel::dueText($d['due'])); ?></span></span><span class="asg-chip st-returned"><?php echo asg_e($left($d['due'])); ?></span></a>
        <?php endforeach; ?>
    </div></div>
</div>
<?php foreach ($heldNotes as $at => $names): ?>
    <div class="asg-fb mb-3"><i class="fas fa-circle-info me-1"></i>Marks for <?php echo asg_e(implode(' and ', $names)); ?> will show on <strong><?php echo asg_e(AssignmentModel::local($at, 'D j M, H:i')); ?></strong> EAT.</div>
<?php endforeach; ?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php foreach ($filters as $k => $lbl): ?><a href="?f=<?php echo $k; ?>&amp;kind=<?php echo $kindF; ?>" class="btn btn-sm <?php echo $filter === $k ? 'btn-brand' : 'btn-light'; ?>"><?php echo $lbl; ?><?php echo $k !== 'all' && !empty($fCount[$k]) ? ' ' . (int) $fCount[$k] : ''; ?></a><?php endforeach; ?>
    <span class="mx-1"></span>
    <?php foreach (['' => 'Daily and weekly'] + AssignmentModel::KINDS as $k => $lbl): ?><a href="?f=<?php echo $filter; ?>&amp;kind=<?php echo $k; ?>" class="btn btn-sm <?php echo $kindF === $k ? 'btn-dark' : 'btn-light'; ?>"><?php echo $lbl; ?></a><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card-student p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><div class="fw-semibold">Hand in work from class</div><div class="small text-muted">Send your tutor a GitHub link, Google Drive link or document, even if it is not listed below.</div></div>
    <a href="submit.php" class="btn btn-brand btn-sm"><i class="fas fa-paper-plane me-1"></i>Submit work</a>
</div>
<?php if (!$groups): ?>
<div class="card-student"><div class="as-empty"><i class="fas fa-file-pen"></i>No assignments set yet. When your tutor gives you one, it appears here and you get an email.</div></div>
<?php endif; ?>

<?php foreach ($groups as $g):
    $done = count(array_filter($g['items'], fn($x) => in_array($x['state'], ['submitted', 'returned', 'graded'], true)));
    $total = count($g['items']);
    $avgR = AssignmentAverage::compute($g['items']);
    $avg = $avgR['average'] === null ? null : (int) round($avgR['average']); ?>
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
                <div class="flex-grow-1"><div class="k"><?php echo ($it['kind'] ?? 'daily') === 'weekly' ? 'Week' : 'Assignment'; ?> <?php echo (int) $it['index']; ?></div>
                    <div class="s">Submit Assignment <?php echo (int) $it['prev_index']; ?> to unlock this one.</div></div>
                <span class="small fw-bold text-muted">LOCKED</span>
            <?php else: ?>
                <div class="num <?php echo in_array($st, ['graded', 'submitted'], true) ? 'done' : ''; ?>">
                    <?php echo in_array($st, ['graded', 'submitted'], true) ? '<i class="fas fa-check"></i>' : ($st === 'closed' ? '<i class="fas fa-lock"></i>' : ($st === 'skipped' ? '<i class="fas fa-forward"></i>' : (int) $it['index'])); ?></div>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="k"><?php echo ($it['kind'] ?? 'daily') === 'weekly' ? 'Week' : 'Assignment'; ?> <?php echo (int) $it['index']; ?></div>
                    <div class="t text-truncate"><?php echo asg_e($it['title']); ?></div>
                    <div class="s"><?php
                        switch ($st) {
                            case 'graded': echo 'Graded ' . asg_e(AssignmentModel::local($it['result_published_at'], 'D j M')); break;
                            case 'submitted': echo 'Submitted ' . asg_e(AssignmentModel::local($it['submitted_at'], 'D j M')) . '. Waiting for your tutor to grade it.'; break;
                            case 'returned': echo 'Your tutor asked for changes. Read the comments and resubmit.'; break;
                            case 'closed': echo 'The due date has passed. Ask your tutor to unlock it.'; break;
                            case 'skipped': echo 'Your tutor skipped this one for you.'; break;
                            case 'extension': echo asg_e(AssignmentModel::dueText($it['due'])) . ' · Extension asked, waiting for your tutor.'; break;
                            default: echo asg_e(AssignmentModel::dueText($it['due'])) . ($it['late'] ? ' · Late work is still accepted.' : ($left($it['due']) ? ' · ' . asg_e($left($it['due'])) : '')) . ($st === 'in_progress' ? ' · Draft saved.' : '');
                        } ?></div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                    <?php if ($st === 'graded'): ?>
                        <span class="asg-chip st-graded"><?php echo asg_e(AssignmentModel::resultText($it)); ?></span>
                        <a href="result.php?id=<?php echo (int) $it['id']; ?>" class="btn btn-sm btn-brand">View result</a>
                    <?php elseif ($st === 'submitted'): ?>
                        <?php echo asg_chip('submitted', !empty($it['is_late']) ? 'Submitted · Late' : (!empty($it['marks_held']) ? 'Submitted · marks not out yet' : null)); ?><a href="<?php echo $href; ?>" class="btn btn-sm btn-outline-secondary">Open</a>
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
