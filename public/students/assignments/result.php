<?php
// Student: result and feedback (spec 8.8). Shown only after the tutor publishes, and only the student's own.
require __DIR__ . '/../../components/assignments/student_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$item = $am->itemForStudent($uid, $id);
if (!$item || $item['state'] !== 'graded') asg_back($item ? 'view.php?id=' . $id : 'index.php', null, 'Marks show only after your tutor publishes the result.');
$a = $am->get($id);
$sub = $am->submission($id, $uid);
$grader = $sub['graded_by'] ? $am->tutorByUser((int) $sub['graded_by']) : null;
$rubric = $a['grading_mode'] === 'marks' ? $am->rubric($id) : [];
$scores = $am->criterionScores((int) $sub['id']);
$history = $am->versions((int) $sub['id'], 'grade');
$latestSubmission = $am->versions((int) $sub['id'], 'submission')[0]['data'] ?? [];
$next = array_values(array_filter($am->chain($uid, (int) $a['tutor_id'], (int) $a['course_id']),
    fn($x) => $x['index'] > $item['index'] && in_array($x['state'], ['open', 'in_progress', 'extension', 'returned'], true)));
$pct = $a['grading_mode'] === 'marks' && (float) $a['max_score'] > 0 ? round((float) $sub['score'] / (float) $a['max_score'] * 100) : null;
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
$improve = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $sub['feedback_improve']))));
$page_title = 'Result';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">‹ My assignments</a>
<div class="card-student p-3 p-md-4">
    <h4 class="mb-2">Assignment <?php echo (int) $item['index']; ?>: <?php echo asg_e($a['title']); ?></h4>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <?php echo asg_chip('graded'); ?>
        <span class="small text-muted">by <?php echo asg_e($grader['full_name'] ?? 'your tutor'); ?> on <?php echo asg_e(AssignmentModel::local($sub['published_at'], 'D j M Y')); ?>
            <?php echo count($history) > 1 ? ' · updated (version ' . count($history) . ')' : ''; ?></span>
    </div>
    <div class="row g-4">
        <div class="col-md-5">
            <div class="p-3 text-center" style="background:var(--g-50,#eef8e9);border-radius:16px">
                <?php if ($a['grading_mode'] === 'marks'): ?>
                    <div class="asg-ring" style="--p:<?php echo (int) $pct; ?>" role="img" aria-label="<?php echo $fmt($sub['score']); ?> out of <?php echo (int) $a['max_score']; ?>">
                        <b><?php echo $fmt($sub['score']); ?></b><span class="small text-muted">out of <?php echo (int) $a['max_score']; ?></span></div>
                    <span class="asg-chip st-graded mt-3"><?php echo asg_e($sub['grade_label']); ?> · pass mark <?php echo (int) $a['pass_mark_percent']; ?>%</span>
                <?php else: ?>
                    <div class="py-4"><i class="fas <?php echo $sub['pass_result'] === 'not_yet' ? 'fa-rotate-right' : 'fa-circle-check'; ?> fa-3x mb-2" style="color:var(--g,#44A024)"></i>
                        <div class="fs-3 fw-bold"><?php echo asg_e($sub['grade_label']); ?></div></div>
                <?php endif; ?>
            </div>
            <?php if ($rubric): ?>
                <div class="asg-section">How your marks add up</div>
                <?php foreach ($rubric as $c): $sc = $scores[(int) $c['id']] ?? null; $w = (float) $c['max_marks'] > 0 && $sc ? (float) $sc['score'] / (float) $c['max_marks'] * 100 : 0; ?>
                    <div class="mb-2"><div class="d-flex justify-content-between small"><span><?php echo asg_e($c['name']); ?></span><strong><?php echo $sc ? $fmt($sc['score']) : '–'; ?> / <?php echo $fmt($c['max_marks']); ?></strong></div>
                        <div class="asg-bar"><i style="width:<?php echo round($w); ?>%"></i></div>
                        <?php if (!empty($sc['comment'])): ?><div class="small text-muted"><?php echo asg_e($sc['comment']); ?></div><?php endif; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="col-md-7">
            <div class="asg-fb dark"><div class="h">Why you got this <?php echo $a['grading_mode'] === 'marks' ? 'mark' : 'result'; ?></div><div style="white-space:pre-wrap"><?php echo asg_e((string) $sub['feedback_why']); ?></div></div>
            <?php if ($sub['feedback_well']): ?><div class="asg-fb"><div class="h" style="color:var(--g-d,#36851b)">What went well</div><div style="white-space:pre-wrap"><?php echo asg_e($sub['feedback_well']); ?></div></div><?php endif; ?>
            <?php if ($improve): ?><div class="asg-fb" style="border-left-color:var(--g-d,#36851b)"><div class="h" style="color:var(--g-d,#36851b)">What to improve</div>
                <?php if (count($improve) > 1): ?><ol class="mb-0 ps-3"><?php foreach ($improve as $line): ?><li><?php echo asg_e(preg_replace('/^\d+[.)]\s*/', '', $line)); ?></li><?php endforeach; ?></ol>
                <?php else: ?><div><?php echo asg_e($improve[0]); ?></div><?php endif; ?></div><?php endif; ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
        <span class="small text-muted fst-italic me-auto">Marks show only after your tutor publishes the result.</span>
        <button class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#mySub">View my submission</button>
        <?php if ($next): ?><a href="view.php?id=<?php echo (int) $next[0]['id']; ?>" class="btn btn-brand">Next assignment</a><?php endif; ?>
    </div>
    <div class="collapse mt-3" id="mySub"><div class="asg-box">
        <?php foreach ((array) ($latestSubmission['links'] ?? []) as $l): ?><div><?php echo asg_link($l, $l, 'text-break'); ?></div><?php endforeach; ?>
        <?php foreach ((array) ($latestSubmission['answers'] ?? []) as $n => $ans): ?><div class="small fw-semibold mt-2"><?php echo asg_e($ans['question']); ?></div><div class="small" style="white-space:pre-wrap"><?php echo asg_e($ans['answer']); ?></div><?php endforeach; ?>
        <?php foreach ((array) ($latestSubmission['files'] ?? []) as $f): ?><a class="asg-pill mt-2" target="_blank" href="<?php echo asg_e(asg_files_url('sub', (int) $f['id'], $f['kind'] === 'file')); ?>"><i class="fas <?php echo $f['kind'] === 'image' ? 'fa-image' : 'fa-file'; ?>"></i><?php echo asg_e($f['name']); ?></a><?php endforeach; ?>
        <?php if (!empty($latestSubmission['legacy_text'])): ?><div class="small" style="white-space:pre-wrap"><?php echo asg_e($latestSubmission['legacy_text']); ?></div><?php endif; ?>
    </div></div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
