<?php
// My Progress & Assessments (design: Dazu_Intern_Desktop_4): overall %, its four parts, skills, supervisor evaluation,
// evaluations list, reports and learning.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

$teach = it_summary_for_intern($db, (int) $intern['id']);
$prog = in_progress($db, $intern, $teach);
$period = in_period($intern);
$evals = $prog['evals'];
$shown = ($evals['final']['status'] ?? '') === 'released' ? $evals['final'] : (($evals['midterm']['status'] ?? '') === 'released' ? $evals['midterm'] : null);
$skills = array_values(array_filter(array_map('trim', explode(',', (string) $intern['skills']))));
$reports = array_slice(in_reports($db, (int) $intern['id']), 0, 4);
$modules = in_modules($db, (int) $intern['id']);
$circ = 2 * M_PI * 52;
$stars = fn(?int $n) => '<span class="ip-stars">' . str_repeat('★', (int) $n) . '<span class="off">' . str_repeat('★', 5 - (int) $n) . '</span></span>';
$evalPill = function (string $k) use ($evals, $intern) {
    $ev = $evals[$k] ?? null;
    if ($k === 'final' && empty($intern['final_eval_open'])) return '<span class="ip-pill n">Not yet open</span>';
    if (!$ev || $ev['status'] === 'draft') return in_array($k, ['self', 'skills'], true) ? '<span class="ip-pill o">To do</span>' : '<span class="ip-pill n">Not yet released</span>';
    return $ev['status'] === 'released' ? '<span class="ip-pill b">Released</span>' : '<span class="ip-pill g">Submitted</span>';
};
$page_title = 'My Progress & Assessments';
$page_subtitle = $period['start'] ? 'Internship ' . date('j M', $period['start']) . ' – ' . ($period['end'] ? date('j M Y', $period['end']) : '…') : 'Internship';
ob_start();
?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="ip-card">
            <h3 class="mb-3">Internship progress</h3>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="ip-ring" style="width:124px;height:124px;margin:0">
                    <svg width="124" height="124" viewBox="0 0 124 124"><circle cx="62" cy="62" r="52" fill="none" stroke="#eceee9" stroke-width="12"/>
                        <circle cx="62" cy="62" r="52" fill="none" stroke="#44A024" stroke-width="12" stroke-dasharray="<?php echo round($circ * $prog['overall'] / 100, 1) . ' ' . round($circ, 1); ?>"/></svg>
                    <div class="c"><b style="font-size:1.6rem"><?php echo $prog['overall']; ?>%</b></div>
                </div>
                <div class="ip-muted"><?php echo $period['week'] ? 'Week ' . $period['week'] . ($period['weeks'] ? ' of ' . $period['weeks'] : '') . '<br>' : ''; ?><?php echo $period['end'] ? 'Ends ' . date('j M Y', $period['end']) : ''; ?></div>
            </div>
            <?php foreach (['attendance' => 'Attendance', 'tasks' => 'Tasks', 'training' => 'Training', 'assessments' => 'Assessments'] as $k => $l): ?>
                <div class="mb-2"><div class="d-flex justify-content-between small"><strong><?php echo $l; ?></strong><span><?php echo $prog['parts'][$k]; ?>%</span></div><div class="ip-bar"><div style="width:<?php echo $prog['parts'][$k]; ?>%"></div></div></div>
            <?php endforeach; ?>
            <?php if ($teach['taught']): ?><div class="ip-muted mt-2"><?php echo $teach['taught']; ?> teaching session<?php echo $teach['taught'] === 1 ? ' counts' : 's count'; ?> toward your tasks.</div><?php endif; ?>
            <h3 class="mt-4 mb-2">Skills acquired</h3>
            <?php if (!$skills): ?><div class="ip-muted">Your supervisor adds skills as you learn them.</div><?php endif; ?>
            <div><?php foreach (array_slice($skills, 0, 8) as $s): ?><span class="ip-tag"><?php echo in_e($s); ?></span><?php endforeach; ?>
                <?php if (count($skills) > 8): ?><span class="ip-tag" style="background:#eef0ee;color:#4a4e47">+ <?php echo count($skills) - 8; ?> more</span><?php endif; ?></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="ip-card">
            <div class="ip-head"><h3>Supervisor evaluation</h3><?php if ($shown): ?><span class="ip-pill b"><?php echo $shown['kind'] === 'final' ? 'Final' : 'Mid-term'; ?> · released</span><?php endif; ?></div>
            <?php if (!$shown): ?><div class="ip-muted">Your supervisor's evaluation shows here once it is released.</div>
            <?php else: ?>
                <?php foreach (INTERN_EVAL_CRITERIA as $k => $l): ?><div class="d-flex justify-content-between mb-2"><strong class="small"><?php echo $l; ?></strong><?php echo $stars((int) $shown[$k]); ?></div><?php endforeach; ?>
                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2"><strong>Overall</strong><span><b class="fs-4 text-success"><?php echo in_eval_score($shown); ?></b> / 5</span></div>
                <?php if ($shown['feedback']): ?><div class="bg-light rounded-3 p-3 mt-3 small"><strong>Feedback from <?php echo in_e($intern['supervisor_name']); ?></strong><div style="white-space:pre-line">“<?php echo in_e($shown['feedback']); ?>”</div></div><?php endif; ?>
            <?php endif; ?>
            <h3 class="mt-4 mb-2">Evaluations</h3>
            <?php foreach (INTERN_EVAL_KINDS as $k => $l): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-top small"><span><?php echo $l; ?></span><?php echo $evalPill($k); ?></div>
            <?php endforeach; ?>
            <a class="btn btn-sm btn-outline-success mt-2" href="<?php echo url('intern/assessments/index.php'); ?>">Open assessments</a>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="ip-card">
            <div class="ip-head"><h3>Reports &amp; logbook</h3><a href="<?php echo url('intern/reports/index.php'); ?>#new">+ New report</a></div>
            <?php if (!$reports): ?><div class="ip-muted">No reports yet.</div><?php endif; ?>
            <?php foreach ($reports as $r): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><div class="small fw-semibold"><?php echo in_e(in_report_label($r)); ?></div>
                    <div class="ip-muted"><?php echo $r['status'] === 'rejected' && $r['supervisor_comment'] ? '“' . in_e(mb_strimwidth($r['supervisor_comment'], 0, 40, '…')) . '”' : 'Sent ' . date('j M', strtotime($r['created_at'])); ?></div></div>
                    <span class="ip-pill <?php echo ['approved' => 'g', 'pending' => 'o', 'rejected' => 'r'][$r['status']]; ?>"><?php echo $r['status'] === 'pending' ? 'Pending review' : ucfirst($r['status']); ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="ip-card">
            <h3 class="mb-3">Learning</h3>
            <?php foreach (array_slice($modules, 0, 4) as $m): ?>
                <div class="mb-3"><div class="d-flex justify-content-between small"><strong><?php echo in_e($m['title']); ?></strong><span><?php echo $m['percent']; ?>%</span></div><div class="ip-bar"><div style="width:<?php echo $m['percent']; ?>%"></div></div></div>
            <?php endforeach; ?>
            <?php if ($prog['parts']['training'] >= 100 && $modules): ?>
                <a class="d-block p-3 rounded-3 text-decoration-none" style="background:#eef7e8;color:#2f7a14" href="<?php echo url('intern/learning/certificate.php'); ?>" target="_blank"><i class="fas fa-award me-2"></i><strong>Certificate ready</strong> — download it</a>
            <?php else: ?>
                <div class="p-3 rounded-3" style="background:#eef7e8;color:#2f7a14"><i class="fas fa-award me-2"></i><strong>Certificate</strong> unlocks when training reaches 100%</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
