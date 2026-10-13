<?php
// Tutor: grade one submission (spec 8.3). Left: exactly what the student sent (any version). Right: marks, rubric, feedback.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$subId = (int) ($_GET['id'] ?? 0);
$ctx = $am->submissionForGrader($subId, $tutorId);
if (!$ctx) asg_back('index.php', null, 'Submission not found.');
$self = 'grade.php?id=' . $subId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? 'draft';
    $next = $action === 'publish_next';
    if ($next) $action = 'publish';
    if (!in_array($action, ['draft', 'publish', 'return'], true)) asg_back($self);
    $r = $am->grade($ctx, [
        'score' => $_POST['score'] ?? '', 'criteria' => (array) ($_POST['criteria'] ?? []), 'pass_result' => $_POST['pass_result'] ?? '',
        'why' => $_POST['why'] ?? '', 'well' => $_POST['well'] ?? '', 'improve' => $_POST['improve'] ?? '',
    ], $action, $me);
    if (!$r['ok']) {
        $error = $r['error'];
    } else {
        $fresh = $am->submissionById($subId);
        if ($action !== 'draft') (new AssignmentNotifier($pdo, $am))->result($ctx['a'], $fresh, $action === 'return' ? 'returned' : ($r['updated'] ? 'updated' : 'published'));
        $msg = ['draft' => 'Grade saved as a draft. The student cannot see it yet.', 'publish' => $r['updated'] ? 'Result updated. The student has been told.' : 'Result published to the student.',
                'return' => 'Work returned to the student for changes.'][$action];
        if ($next) {
            $n = $am->nextAwaiting($tutorId, $subId);
            asg_back($n ? 'grade.php?id=' . $n : 'index.php', $msg . ($n ? '' : ' Nothing else is awaiting grading.'));
        }
        asg_back($self, $msg);
    }
}

$a = $ctx['a'];
$s = $ctx['sub'];
$student = $ctx['student'];
$versions = $am->versions($subId, 'submission');
$vNo = (int) ($_GET['v'] ?? ($versions[0]['version'] ?? 0));
$shown = null;
foreach ($versions as $v) if ((int) $v['version'] === $vNo) $shown = $v;
$snap = $shown['data'] ?? [];
$rubric = $a['grading_mode'] === 'marks' ? $am->rubric((int) $a['id']) : [];
$scores = $am->criterionScores($subId);
$gradeHistory = $am->versions($subId, 'grade');
$scale = $am->gradeScale();
$p = $_POST ?: [];
$val = fn(string $k, $fallback) => $p ? ($p[$k] ?? '') : $fallback;
$num = $am->number($a);
$page_title = 'Grade: ' . $student['full_name'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">‹ Submissions</a>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo asg_e($error); ?></div><?php endif; ?>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor p-3 p-md-4">
        <h5 class="mb-1">Grade: <?php echo asg_e($student['full_name']); ?></h5>
        <div class="small text-muted mb-2">Assignment <?php echo $num; ?>: <?php echo asg_e($a['title']); ?> · <?php echo asg_e($a['course_title'] ?: 'No course'); ?> ·
            Submitted <?php echo asg_e(AssignmentModel::local($shown['created_at'] ?? $s['submitted_at'], 'D j M, H:i')); ?>
            <?php if (!empty($snap['late'])): ?><span class="asg-chip st-returned ms-1"><i class="fas fa-clock"></i>Late</span><?php endif; ?></div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="asg-chip st-progress">Version <?php echo $vNo; ?> of <?php echo count($versions); ?></span>
            <?php foreach ($versions as $v): if ((int) $v['version'] === $vNo) continue; ?>
                <a href="<?php echo $self; ?>&v=<?php echo (int) $v['version']; ?>" class="btn btn-sm btn-outline-secondary">See version <?php echo (int) $v['version']; ?></a>
            <?php endforeach; ?>
            <?php echo asg_chip($s['status'] === 'graded' ? 'graded' : ($s['status'] === 'returned' ? 'returned' : 'submitted')); ?>
        </div>

        <?php foreach ((array) ($snap['links'] ?? []) as $link): ?>
            <div class="asg-section">Their link</div>
            <div class="asg-box d-flex justify-content-between align-items-center gap-2"><span class="text-break"><?php echo asg_e($link); ?></span>
                <?php echo asg_link($link, 'Open', 'btn btn-sm btn-outline-dark'); ?></div>
        <?php endforeach; ?>

        <?php foreach ((array) ($snap['answers'] ?? []) as $n => $ans): ?>
            <div class="asg-section">Answer Q<?php echo $n + 1; ?> <span class="text-dark text-transform-none fw-semibold" style="text-transform:none"><?php echo asg_e($ans['question']); ?></span></div>
            <div class="asg-box" style="white-space:pre-wrap"><?php echo asg_e($ans['answer']); ?></div>
        <?php endforeach; ?>

        <?php $imgs = array_filter((array) ($snap['files'] ?? []), fn($x) => $x['kind'] === 'image'); if ($imgs): ?>
            <div class="asg-section">Screenshots</div>
            <div class="asg-shots"><?php foreach ($imgs as $img): ?><a href="<?php echo asg_e(asg_files_url('sub', (int) $img['id'])); ?>" target="_blank" title="<?php echo asg_e($img['name']); ?>"><img src="<?php echo asg_e(asg_files_url('sub', (int) $img['id'])); ?>" alt="<?php echo asg_e($img['name']); ?>" loading="lazy"></a><?php endforeach; ?></div>
        <?php endif; ?>
        <?php $docs = array_filter((array) ($snap['files'] ?? []), fn($x) => $x['kind'] === 'file'); if ($docs): ?>
            <div class="asg-section">Files</div>
            <?php foreach ($docs as $d): ?><a class="asg-pill" href="<?php echo asg_e(asg_files_url('sub', (int) $d['id'], true)); ?>"><i class="fas fa-download"></i><?php echo asg_e($d['name']); ?> <span class="text-muted">(<?php echo asg_human_bytes((int) $d['bytes']); ?>)</span></a><?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($snap['note'])): ?><div class="asg-section">Note from the student</div><div class="asg-box" style="white-space:pre-wrap"><?php echo asg_e($snap['note']); ?></div><?php endif; ?>
        <?php if (!empty($snap['legacy_text'])): ?><div class="asg-section">Answer</div><div class="asg-box" style="white-space:pre-wrap"><?php echo asg_e($snap['legacy_text']); ?></div><?php endif; ?>
        <?php if (!empty($snap['legacy_file'])): ?><div class="asg-section">File</div><a class="asg-pill" target="_blank" rel="noopener" href="<?php echo asg_e(settings_asset_url($snap['legacy_file'])); ?>"><i class="fas fa-download"></i>Download</a><?php endif; ?>
        <p class="small text-muted fst-italic mt-3 mb-0">This is exactly what the student sent in this version.</p>
    </div>

    <?php if ($gradeHistory): ?>
    <div class="card-tutor p-3 mt-3">
        <div class="asg-section mt-0">Grade history</div>
        <?php foreach ($gradeHistory as $g): $d = $g['data']; ?>
            <div class="small border-bottom py-1"><strong><?php echo asg_e(AssignmentModel::local($g['created_at'], 'D j M Y, H:i')); ?></strong> ·
                <?php echo ($d['action'] ?? '') === 'return' ? 'Returned for changes' : 'Published ' . asg_e($d['label'] ?? '') . ($d['score'] !== null ? ' (' . (float) $d['score'] . ' / ' . (float) $d['max'] . ')' : ''); ?>
                · on version <?php echo (int) ($d['submission_version'] ?? 0); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="col-lg-5">
    <form method="POST" class="asg-panel" id="gradeForm"><?php echo csrf_field(); ?>
        <?php if ($a['grading_mode'] === 'marks'): ?>
            <h6 class="fw-bold">Marks</h6>
            <div class="d-flex align-items-center gap-2 mb-3">
                <input type="number" step="0.5" min="0" max="<?php echo (int) $a['max_score']; ?>" name="score" id="scoreIn" class="form-control form-control-lg text-center fw-bold" style="max-width:110px"
                       value="<?php echo asg_e((string) $val('score', $s['score'] !== null ? (float) $s['score'] : '')); ?>" <?php echo $rubric ? 'readonly' : ''; ?>>
                <span class="text-muted">/ <?php echo (int) $a['max_score']; ?></span>
                <span class="asg-chip st-graded ms-auto" id="labelOut" aria-live="polite"></span>
            </div>
            <?php if ($rubric): ?>
                <div class="asg-section mt-0">Rubric</div>
                <?php foreach ($rubric as $c): $cid = (int) $c['id']; ?>
                    <div class="mb-2">
                        <div class="d-flex align-items-center gap-2"><span class="flex-grow-1 small"><?php echo asg_e($c['name']); ?></span>
                            <input type="number" step="0.5" min="0" max="<?php echo (float) $c['max_marks']; ?>" name="criteria[<?php echo $cid; ?>][score]" class="form-control form-control-sm crit" style="max-width:80px"
                                   value="<?php echo asg_e((string) ($p ? ($p['criteria'][$cid]['score'] ?? '') : (isset($scores[$cid]['score']) ? (float) $scores[$cid]['score'] : ''))); ?>">
                            <span class="small text-muted">/ <?php echo (float) $c['max_marks']; ?></span></div>
                        <input name="criteria[<?php echo $cid; ?>][comment]" class="form-control form-control-sm mt-1" placeholder="Comment (optional)" maxlength="500"
                               value="<?php echo asg_e((string) ($p ? ($p['criteria'][$cid]['comment'] ?? '') : ($scores[$cid]['comment'] ?? ''))); ?>">
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php elseif ($a['grading_mode'] === 'pass'): ?>
            <h6 class="fw-bold">Result</h6>
            <div class="d-flex gap-3 mb-3">
                <?php foreach (['pass' => 'Pass', 'not_yet' => 'Not yet'] as $k => $lbl): ?>
                    <div class="form-check"><input class="form-check-input" type="radio" name="pass_result" value="<?php echo $k; ?>" id="pr<?php echo $k; ?>" <?php echo $val('pass_result', $s['pass_result']) === $k ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="pr<?php echo $k; ?>"><?php echo $lbl; ?></label></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <h6 class="fw-bold">Feedback only</h6><p class="small text-muted">No result. Write your comments and publish to mark it Reviewed.</p>
        <?php endif; ?>

        <label class="asg-section d-block">Why this mark (required)</label>
        <textarea name="why" class="form-control mb-2" rows="3" required><?php echo asg_e((string) $val('why', $s['feedback_why'])); ?></textarea>
        <label class="asg-section d-block">What went well</label>
        <textarea name="well" class="form-control mb-2" rows="2"><?php echo asg_e((string) $val('well', $s['feedback_well'])); ?></textarea>
        <label class="asg-section d-block">What to improve <span id="impReq">(required)</span></label>
        <textarea name="improve" class="form-control mb-3" rows="3" placeholder="One clear action per line"><?php echo asg_e((string) $val('improve', $s['feedback_improve'])); ?></textarea>

        <div class="row g-2">
            <div class="col-6"><button name="action" value="draft" class="btn btn-outline-dark w-100" formnovalidate>Save draft</button></div>
            <div class="col-6"><button name="action" value="return" class="btn btn-outline-danger w-100" onclick="return confirm('Send the work back to the student for changes?')">Return</button></div>
            <div class="col-12"><button name="action" value="publish" class="btn btn-brand w-100"><?php echo $s['status'] === 'graded' ? 'Update published result' : 'Publish result to student'; ?></button></div>
            <div class="col-12"><button name="action" value="publish_next" class="btn btn-outline-success w-100">Publish and open next submission</button></div>
        </div>
        <p class="small text-muted mt-2 mb-0">Save draft keeps the grade private. <?php echo $s['status'] === 'graded' ? 'Changing a published result tells the student, and the old grade is kept in history.' : 'The student sees nothing until you publish.'; ?></p>
    </form>
</div>
</div>
<?php
$content = ob_get_clean();
$cfg = json_encode(['scale' => $scale, 'max' => (float) $a['max_score'], 'rubric' => (bool) $rubric, 'mode' => $a['grading_mode']]);
$extra_js = <<<JS
<script>
(function () {
    var cfg = $cfg, score = document.getElementById('scoreIn'), out = document.getElementById('labelOut'), imp = document.getElementById('impReq');
    function label(p) { return p >= cfg.scale.distinction ? 'Distinction' : p >= cfg.scale.merit ? 'Merit' : p >= cfg.scale.pass ? 'Pass' : 'Not yet'; }
    function update() {
        if (cfg.mode !== 'marks') { if (imp) imp.textContent = cfg.mode === 'feedback' ? '(optional)' : '(required unless Pass)'; return; }
        if (cfg.rubric) {
            var crit = document.querySelectorAll('.crit'), sum = 0, all = true;
            crit.forEach(function (c) { if (c.value === '') all = false; sum += parseFloat(c.value) || 0; });
            score.value = all ? sum : '';
        }
        var v = parseFloat(score.value);
        if (isNaN(v)) { out.style.visibility = 'hidden'; return; }
        var pct = cfg.max ? Math.round(v / cfg.max * 100) : 0;
        out.style.visibility = 'visible'; out.textContent = label(pct) + ' ' + pct + '%';
        imp.textContent = v >= cfg.max ? '(optional for full marks)' : '(required)';
    }
    document.getElementById('gradeForm').addEventListener('input', update); update();
})();
</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
