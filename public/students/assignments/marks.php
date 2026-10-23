<?php
// Student: My marks. Course average from released marks only (rules in AssignmentAverage). The student's own marks only.
require __DIR__ . '/../../components/assignments/student_boot.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentAverage.php';

use Models\AssignmentModel;

$courses = AssignmentAverage::forStudent($pdo, $uid);
$filter = in_array($_GET['kind'] ?? '', array_keys(AssignmentAverage::KINDS), true) ? $_GET['kind'] : '';
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
$page_title = 'My marks';
ob_start();
?>
<div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">My assignments</a>
    <a href="marks.php" class="btn btn-sm btn-brand" aria-current="page">My marks</a>
</div>

<?php if (!$courses): ?>
<div class="card-student"><div class="as-empty"><i class="fas fa-chart-simple"></i>No marks yet. Your marks show here when your tutor releases them.</div></div>
<?php endif; ?>

<?php foreach ($courses as $cid => $c):
    $pct = $c['average'] === null ? 0 : (int) round($c['average']); ?>
<div class="card-student p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <div class="asg-ring" style="--p:<?php echo $pct; ?>;width:84px;height:84px;margin:0;font-weight:700" role="img" aria-label="Course average <?php echo asg_e($c['display']); ?>">
            <span><?php echo $c['average'] === null ? '–' : $pct . '%'; ?></span></div>
        <div>
            <div class="small text-muted"><?php echo asg_e($c['course']); ?></div>
            <div class="fw-semibold"><?php echo $c['counted'] ? 'Average of ' . (int) $c['counted'] . ' marked assignment' . ($c['counted'] === 1 ? '' : 's') : 'No marks yet'; ?></div>
            <div class="small fw-semibold" style="color:#44A024">
                <?php echo implode(' · ', array_map(fn($k, $l) => $l . ' ' . ($c['by_kind'][$k] === null ? '–' : (int) round($c['by_kind'][$k]) . '%'), array_keys(AssignmentAverage::KINDS), AssignmentAverage::KINDS)); ?></div>
        </div>
    </div>

    <div class="d-flex gap-1 mb-2">
        <?php foreach (['' => 'All'] + AssignmentAverage::KINDS as $k => $l): ?>
            <a href="?kind=<?php echo $k; ?>" class="btn btn-sm <?php echo $filter === $k ? 'btn-brand' : 'btn-light'; ?>"><?php echo $l; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-2">
        <tbody>
        <?php foreach ($c['items'] as $it): if ($filter && $it['kind'] !== $filter) continue; if (!$it['released']) continue; ?>
            <tr>
                <td><div class="fw-semibold"><?php echo asg_e($it['title']); ?></div>
                    <div class="small text-muted"><?php echo asg_e(AssignmentAverage::KINDS[$it['kind']] ?? ucfirst($it['kind'])); ?><?php echo $it['late'] ? ' · Late' : ''; ?></div></td>
                <?php if ($it['counts']): ?>
                    <td class="text-end fw-bold text-nowrap"><?php echo $fmt($it['score']); ?> / <?php echo $fmt($it['max_score']); ?></td>
                    <td style="min-width:90px"><div class="asg-bar"><i style="width:<?php echo (int) round($it['percent']); ?>%"></i></div></td>
                    <td class="text-end small text-muted"><?php echo (int) round($it['percent']); ?>%</td>
                <?php else: ?>
                    <td class="text-end fw-bold"><?php echo asg_e($it['pass_result'] === 'pass' ? 'Pass' : ($it['pass_result'] === 'not_yet' ? 'Not yet' : 'Reviewed')); ?></td>
                    <td colspan="2" class="text-end small text-muted">Not in average</td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="d-flex justify-content-between fw-bold"><span>Course average</span><span style="color:#44A024"><?php echo asg_e($c['display']); ?></span></div>
    <div class="small text-muted">"Pass or Not yet" and "Feedback only" work does not count in the average.</div>

    <?php if ($c['not_counted']): ?>
    <div class="asg-section mt-3">Not counted yet</div>
    <?php foreach ($c['not_counted'] as $n): ?>
        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <div><div class="fw-semibold small"><?php echo asg_e($n['title']); ?></div>
                <div class="small text-muted"><?php echo $n['reason'] === 'held' ? 'Marks out ' . asg_e(AssignmentModel::local($n['release_at'], 'D j M, H:i')) . ' EAT' : 'Waiting for your tutor'; ?></div></div>
            <span class="asg-chip <?php echo $n['reason'] === 'held' ? 'st-submitted' : 'st-returned'; ?>"><?php echo $n['reason'] === 'held' ? 'Held' : 'To grade'; ?></span>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="asg-fb"><i class="fas fa-circle-info me-1"></i>Your average uses only marks your tutor has released. Late work is not reduced. Missing work is not counted as zero. If you resubmit, your newest mark is used. Only you and your tutor see these marks.</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
