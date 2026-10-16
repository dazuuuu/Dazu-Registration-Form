<?php
// Assessments: self-assessment and skills assessment (filled by the intern), mid-term and final evaluations from the
// supervisor (shown once released; the final stays closed until HR opens it), and the tutor's teaching ratings.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

$self = url('intern/assessments/index.php');
if (intern_post_ok()) {
    $r = in_eval_save($db, (int) $intern['id'], (string) ($_POST['kind'] ?? ''), $_POST, false);
    SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Submitted. Thank you.' : $r['error']));
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$evals = in_evaluations($db, (int) $intern['id']);
$taught = array_filter(it_topics_for_intern($db, (int) $intern['id'], false), fn($t) => $t['status'] === 'taught');
$stars = fn(?int $n) => '<span class="ip-stars">' . str_repeat('★', (int) $n) . '<span class="off">' . str_repeat('★', 5 - (int) $n) . '</span></span>';
$help = ['self' => 'How well are you doing? Be honest — it helps your supervisor support you.', 'skills' => 'Rate your skills now, and write which ones you want to improve.'];
$page_title = 'Assessments';
ob_start();
?>
<div class="row g-3">
    <?php foreach (INTERN_EVAL_KINDS as $kind => $label): $ev = $evals[$kind] ?? null; $mine = in_array($kind, ['self', 'skills'], true); ?>
    <div class="col-lg-6">
        <div class="ip-card h-100">
            <div class="ip-head"><h3><?php echo $label; ?></h3>
                <?php if ($kind === 'final' && empty($intern['final_eval_open'])): ?><span class="ip-pill n">Not yet open</span>
                <?php elseif ($ev && $ev['status'] === 'released'): ?><span class="ip-pill b">Released</span>
                <?php elseif ($ev && $ev['status'] === 'submitted'): ?><span class="ip-pill g">Submitted</span>
                <?php else: ?><span class="ip-pill o"><?php echo $mine ? 'To do' : 'Not yet released'; ?></span><?php endif; ?></div>
            <?php if ($mine && (!$ev || $ev['status'] === 'draft')): ?>
                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="kind" value="<?php echo $kind; ?>">
                    <p class="ip-muted"><?php echo $help[$kind]; ?></p>
                    <?php foreach (INTERN_EVAL_CRITERIA as $c => $l): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2"><span class="small fw-semibold"><?php echo $l; ?></span>
                            <select name="<?php echo $c; ?>" class="form-select form-select-sm" style="width:auto" required><option value="">Choose</option>
                                <?php for ($n = 1; $n <= 5; $n++): ?><option value="<?php echo $n; ?>"><?php echo str_repeat('★', $n); ?> (<?php echo $n; ?>)</option><?php endfor; ?></select></div>
                    <?php endforeach; ?>
                    <textarea name="feedback" class="form-control form-control-sm mb-2" rows="3" placeholder="<?php echo $kind === 'self' ? 'What went well, and what you want to do better' : 'Skills to improve and how'; ?>"></textarea>
                    <button class="btn btn-sm btn-success">Submit</button></form>
            <?php elseif ($ev && ($mine || $ev['status'] === 'released')): ?>
                <?php foreach (INTERN_EVAL_CRITERIA as $c => $l): ?><div class="d-flex justify-content-between mb-1 small"><span><?php echo $l; ?></span><?php echo $stars((int) $ev[$c]); ?></div><?php endforeach; ?>
                <div class="d-flex justify-content-between border-top pt-2 mt-2"><strong>Overall</strong><span><b class="text-success"><?php echo in_eval_score($ev); ?></b> / 5</span></div>
                <?php if ($ev['feedback']): ?><div class="bg-light rounded-3 p-2 mt-2 small" style="white-space:pre-line">“<?php echo in_e($ev['feedback']); ?>”</div><?php endif; ?>
            <?php else: ?>
                <div class="ip-muted"><?php echo $kind === 'final' && empty($intern['final_eval_open']) ? 'HR opens the final evaluation near the end of your internship.' : 'Your supervisor writes this. You will be notified when it is released.'; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($taught): ?>
<div class="ip-card mt-3" id="teaching">
    <h3 class="mb-3">Teaching ratings from tutors</h3>
    <?php foreach ($taught as $t): ?>
        <div class="border rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between flex-wrap gap-2"><strong><?php echo in_e($t['title']); ?></strong><span class="ip-muted"><?php echo in_e($t['tutor_name']); ?> · <?php echo date('j M', strtotime($t['taught_at'])); ?></span></div>
            <?php if ($t['preparation'] === null): ?><div class="ip-muted">Not rated yet.</div>
            <?php else: foreach (['preparation' => 'Preparation', 'clarity' => 'Clarity', 'confidence' => 'Confidence'] as $k => $l): ?>
                <div class="d-flex justify-content-between small"><span><?php echo $l; ?></span><?php echo $stars((int) $t[$k]); ?></div>
            <?php endforeach; if ($t['rating_comment']): ?><div class="small mt-1">“<?php echo in_e($t['rating_comment']); ?>”</div><?php endif; endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
