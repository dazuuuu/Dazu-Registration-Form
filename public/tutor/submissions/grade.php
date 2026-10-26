<?php
// Tutor: grade one submission (spec 8.3). Left: exactly what the student sent (any version). Right: marks, rubric, feedback.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/LinkChecker.php';

use Models\AssignmentModel;

$subId = (int) ($_GET['id'] ?? 0);
$ctx = $am->submissionForGrader($subId, $tutorId);
if (!$ctx) asg_back('index.php', null, 'Submission not found.');
// Group work is graded on the first member's submission; the others get the same mark.
$owner = $am->workOwner((int) $ctx['a']['id'], (int) $ctx['sub']['student_user_id']);
if ($owner !== (int) $ctx['sub']['student_user_id'] && ($os = $am->submission((int) $ctx['a']['id'], $owner))) {
    header('Location: grade.php?id=' . (int) $os['id']);
    exit();
}
$groupMates = array_diff($am->groupMembers((int) $ctx['a']['id'], $owner), [$owner]);
$self = 'grade.php?id=' . $subId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? 'draft';
    // Saved comments (the tutor's own comment bank).
    if ($action === 'add_snippet') { $am->addSnippet($tutorId, (string) ($_POST['snippet'] ?? '')); asg_back($self . '#feedback', 'Added to your saved comments.'); }
    if ($action === 'del_snippet') { $am->deleteSnippet($tutorId, (int) ($_POST['snippet_id'] ?? 0)); asg_back($self . '#feedback'); }
    // Link check: check again, or ask the student to fix access. Never changes the sent time, Late or the mark.
    if ($action === 'recheck_links' || $action === 'ask_fix') {
        $lc = new LinkChecker($pdo);
        $res = [];
        foreach (AssignmentModel::workLinks($ctx['sub']['links'], $ctx['sub']['answers']) as $u) {
            $c = $lc->check($u, $ctx['a']['link_sites'], $me, $subId, $tutor['github_username'] ?? null);
            if (!in_array($c['status'], ['not_checked', 'not_allowed'], true)) $res[] = $c;
        }
        $am->saveLinkResult($subId, $res);
        $bad = array_values(array_filter($res, fn($c) => $c['bad']));
        if ($action === 'ask_fix' && $bad) {
            (new AssignmentNotifier($pdo, $am))->linkFix($ctx['a'], (int) $ctx['sub']['student_user_id'], $bad[0]['host'], $bad[0]['fix']);
            $am->log((int) $ctx['a']['id'], $me, (int) $ctx['sub']['student_user_id'], 'link_fix_asked', ['host' => $bad[0]['host']]);
            asg_back($self, 'The student was asked to fix access (dashboard notice and email).');
        }
        asg_back($self, $bad ? 'Checked again: the link still needs access.' : 'Checked again: the link works now.');
    }
    $next = $action === 'publish_next';
    if ($next) $action = 'publish';
    if (!in_array($action, ['draft', 'publish', 'return'], true)) asg_back($self);
    if ($action === 'return' && !AssignmentModel::allowsReturn($ctx['a'])) asg_back($self, null, 'Resubmission is turned off for this assignment.');
    $held = $action === 'publish' && AssignmentModel::gradesHeld($ctx['a']);
    $r = $am->grade($ctx, [
        'score' => $_POST['score'] ?? '', 'criteria' => (array) ($_POST['criteria'] ?? []), 'pass_result' => $_POST['pass_result'] ?? '',
        'why' => $_POST['why'] ?? '', 'well' => $_POST['well'] ?? '', 'improve' => $_POST['improve'] ?? '',
        'return_items' => preg_split('/\R/', (string) ($_POST['improve'] ?? '')),
        'resubmit_by' => ($rb = AssignmentModel::toUtc($_POST['resubmit_by'] ?? '')) && AssignmentModel::ts($rb) > time() ? $rb : null,
    ], $action, $me);
    if ($r['ok'] && !empty($_POST['save_snippet']) && trim((string) ($_POST['why'] ?? '')) !== '') $am->addSnippet($tutorId, (string) $_POST['why']);
    if (!$r['ok']) {
        $error = $r['error'];
    } else {
        $fresh = $am->submissionById($subId);
        foreach ($groupMates ? $am->mirrorGroupGrade((int) $ctx['a']['id'], $owner) : [] as $mateSub) {
            if ($action !== 'draft' && !$held) (new AssignmentNotifier($pdo, $am))->result($ctx['a'], $am->submissionById($mateSub), $action === 'return' ? 'returned' : 'published');
        }
        // Held grades: the student is told at the release time (cron or "Release grades now"), not now.
        if ($action !== 'draft' && !$held) (new AssignmentNotifier($pdo, $am))->result($ctx['a'], $fresh, $action === 'return' ? 'returned' : ($r['updated'] ? 'updated' : 'published'));
        $msg = ['draft' => 'Grade saved as a draft. The student cannot see it yet.',
                'publish' => $held ? 'Grade saved. The student will see it on ' . AssignmentModel::local($ctx['a']['release_grades_at']) . ' EAT.' : ($r['updated'] ? 'Result updated. The student has been told.' : 'Result published to the student.'),
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
// Recheck before marking: links are checked again when the tutor opens the work (cached 10 minutes).
$linkRes = [];
if ((int) ($a['check_links'] ?? 1)) {
    $lc = new LinkChecker($pdo);
    foreach (array_slice(AssignmentModel::workLinks((array) ($snap['links'] ?? []), array_column((array) ($snap['answers'] ?? []), 'answer')), 0, 4) as $u) {
        $c = $lc->check($u, $a['link_sites'], $me, $subId, $tutor['github_username'] ?? null);
        if (!in_array($c['status'], ['not_checked', 'not_allowed'], true)) $linkRes[$u] = $c;
    }
    if ($linkRes && $vNo === (int) $s['version']) $am->saveLinkResult($subId, array_values($linkRes));
}
$snippets = $am->snippets($tutorId);
$nextUngraded = $am->nextAwaiting($tutorId, $subId);
$page_title = 'Grade: ' . $student['full_name'];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <a href="index.php" class="btn btn-link btn-sm ps-0">‹ Submissions</a>
    <?php if ($nextUngraded): ?><a href="grade.php?id=<?php echo (int) $nextUngraded; ?>" class="btn btn-sm btn-brand">Next ungraded →</a><?php endif; ?>
</div>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo asg_e($error); ?></div><?php endif; ?>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor p-3 p-md-4">
        <h5 class="mb-1">Grade: <?php echo asg_e($student['full_name']); ?><?php if ($groupMates): ?> <span class="small text-muted fw-normal">and group: <?php echo asg_e(implode(', ', array_map(fn($m) => $am->student($m)['full_name'], $groupMates))); ?></span><?php endif; ?></h5>
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
            <div class="asg-box d-flex justify-content-between align-items-center gap-2 flex-wrap"><span class="text-break"><?php echo asg_e($link); ?></span>
                <span class="d-flex gap-2 align-items-center">
                <?php if ($lr = $linkRes[$link] ?? null): ?>
                    <span class="asg-chip <?php echo $lr['status'] === 'works' ? 'st-graded' : ($lr['bad'] ? 'lc-bad' : 'st-open'); ?>"><?php echo asg_e($lr['status'] === 'works' ? 'Link works · anyone can view' : ($lr['bad'] ? 'Needs access' : 'Could not check')); ?></span>
                <?php endif; ?>
                <?php echo asg_link($link, 'Open', 'btn btn-sm btn-outline-dark'); ?></span></div>
            <?php if (!empty($lr) && $lr['bad']): ?>
                <div class="asg-lc asg-lc-warn"><div><?php echo asg_e($lr['title']); ?>. <?php echo asg_e($lr['message']); ?>
                    <div class="text-muted">This does not mark the work Late and does not change the grade. The student keeps their original sent time.</div></div></div>
                <div class="d-flex gap-2 mb-2">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="ask_fix"><button class="btn btn-sm btn-brand"><i class="fas fa-bell me-1"></i>Ask student to fix access</button></form>
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="recheck_links"><button class="btn btn-sm btn-outline-secondary">Check again</button></form>
                </div>
            <?php endif; ?>
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
        <?php if (!empty($snap['code'])): ?><div class="asg-section">Code box</div><pre class="asg-code p-3 small"><code><?php echo asg_e($snap['code']); ?></code></pre><?php endif; ?>
        <?php if (!empty($snap['audio'])): ?><div class="asg-section">Audio answer</div><audio controls preload="none" class="w-100" src="<?php echo asg_e(asg_files_url('aud', $subId)); ?>"></audio><?php endif; ?>
        <?php if ((int) ($a['submit_quiz'] ?? 0) && ($qq = $am->quizQuestions((int) $a['id']))): $qs = $am->quizScore((int) $a['id'], $subId); ?>
            <div class="asg-section">Quiz (marked by the system)</div><div class="asg-box small"><strong><?php echo (float) $qs['got']; ?> of <?php echo (float) $qs['of']; ?> points</strong>
                <?php if ($a['grading_mode'] === 'marks' && $qs['of'] > 0): ?> · <?php echo round($qs['got'] / $qs['of'] * (float) $a['max_score'], 2); ?> of <?php echo (int) $a['max_score']; ?> marks<?php endif; ?></div>
        <?php endif; ?>
        <?php if ($s['own_work_ack'] !== null && $s['own_work_ack'] !== ''): ?><div class="small mt-2 text-success"><i class="fas fa-check me-1"></i>Student ticked: "This is my own work"</div><?php endif; ?>
        <?php if (!empty($s['ai_note'])): ?><div class="asg-section">AI use, in the student's words</div><div class="asg-box small" style="white-space:pre-wrap"><?php echo asg_e($s['ai_note']); ?></div><?php endif; ?>
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

        <label class="asg-section d-block" id="feedback">Why this mark (required)</label>
        <textarea name="why" id="whyIn" class="form-control mb-1" rows="3" required><?php echo asg_e((string) $val('why', $s['feedback_why'])); ?></textarea>
        <?php if ($snippets): ?><div class="small text-muted">Saved comments, click to add</div>
        <div class="d-flex flex-wrap gap-1 mb-1"><?php foreach ($snippets as $sn): ?><span class="asg-pill m-0" role="button" tabindex="0" data-snip="<?php echo asg_e($sn['text']); ?>"><?php echo asg_e(mb_strimwidth($sn['text'], 0, 40, '…')); ?>
            <button form="delSnip<?php echo (int) $sn['id']; ?>" class="btn btn-link p-0 ms-1 small text-muted" aria-label="Remove saved comment">×</button></span><?php endforeach; ?></div><?php endif; ?>
        <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="save_snippet" value="1" id="saveSnip"><label class="form-check-label" for="saveSnip">Add this to my saved comments</label></div>
        <label class="asg-section d-block">What went well</label>
        <textarea name="well" class="form-control mb-2" rows="2"><?php echo asg_e((string) $val('well', $s['feedback_well'])); ?></textarea>
        <label class="asg-section d-block">What to improve <span id="impReq">(required)</span></label>
        <textarea name="improve" class="form-control mb-3" rows="3" placeholder="One clear action per line"><?php echo asg_e((string) $val('improve', $s['feedback_improve'])); ?></textarea>

        <?php if (AssignmentModel::gradesHeld($a)): ?>
        <div class="alert alert-info small py-2"><i class="fas fa-lock me-1"></i>Grades held until <?php echo asg_e(AssignmentModel::local($a['release_grades_at'])); ?> EAT. Students see them then.</div>
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-6"><button name="action" value="draft" class="btn btn-outline-dark w-100" formnovalidate>Save draft</button></div>
            <?php if (AssignmentModel::allowsReturn($a)): ?>
            <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text">Resubmit by (optional)</span><input type="datetime-local" name="resubmit_by" class="form-control"></div></div>
            <div class="col-6"><button name="action" value="return" class="btn btn-outline-danger w-100" onclick="return confirm('Send the work back to the student for changes?')">Return</button></div>
            <?php endif; ?>
            <div class="col-12"><button name="action" value="publish" class="btn btn-brand w-100"><?php echo $s['status'] === 'graded' ? 'Update published result' : 'Publish result to student'; ?></button></div>
            <div class="col-12"><button name="action" value="publish_next" class="btn btn-outline-success w-100">Publish and open next submission</button></div>
        </div>
        <p class="small text-muted mt-2 mb-0">Save draft keeps the grade private. <?php echo $s['status'] === 'graded' ? 'Changing a published result tells the student, and the old grade is kept in history.' : 'The student sees nothing until you publish.'; ?></p>
    </form>
    <?php foreach ($snippets as $sn): ?><form method="POST" id="delSnip<?php echo (int) $sn['id']; ?>" class="d-none"><?php echo csrf_field(); ?><input type="hidden" name="action" value="del_snippet"><input type="hidden" name="snippet_id" value="<?php echo (int) $sn['id']; ?>"></form><?php endforeach; ?>
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
    // Saved comments: one click adds the text to "Why this mark".
    var why = document.getElementById('whyIn');
    document.querySelectorAll('[data-snip]').forEach(function (el) {
        function add(e) { if (e.target.tagName === 'BUTTON') return; why.value = (why.value ? why.value.replace(/\s+$/, '') + ' ' : '') + el.dataset.snip; why.focus(); }
        el.addEventListener('click', add); el.addEventListener('keydown', function (e) { if (e.key === 'Enter') add(e); });
    });
})();
</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>if (window.hljs) document.querySelectorAll('pre.asg-code code').forEach(function (c) { hljs.highlightElement(c); });</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
