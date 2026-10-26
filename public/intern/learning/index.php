<?php
// Learning: modules assigned to the intern — videos, PDFs, links, quizzes and assignments — with completion %.
// The training certificate unlocks at 100%.
require __DIR__ . '/../_boot.php';

$self = url('intern/learning/index.php');
if (intern_post_ok()) {
    $r = in_learning_complete($db, $intern, (int) ($_POST['item_id'] ?? 0), $_POST);
    SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Done' . ($r['score'] !== null ? ' — you scored ' . $r['score'] . '%' : '') . '.' : $r['error']));
    header('Location: ' . $self . '#item-' . (int) ($_POST['item_id'] ?? 0));
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$modules = in_modules($db, (int) $intern['id']);
$training = in_training_percent($modules);
$icons = ['video' => 'fa-circle-play', 'pdf' => 'fa-file-pdf', 'quiz' => 'fa-circle-question', 'assignment' => 'fa-file-pen', 'link' => 'fa-link'];
$page_title = 'Learning';
$page_subtitle = $training . '% of your training done';
ob_start();
?>
<div class="ip-card d-flex flex-wrap gap-3 align-items-center">
    <div class="flex-grow-1"><h3>Training progress</h3><div class="ip-bar" style="height:10px"><div style="width:<?php echo $training; ?>%"></div></div></div>
    <div class="fs-3 fw-bold"><?php echo $training; ?>%</div>
    <?php if ($training >= 100 && $modules): ?>
        <a class="btn btn-success" href="<?php echo url('intern/learning/certificate.php'); ?>" target="_blank"><i class="fas fa-award me-1"></i>Download certificate</a>
    <?php else: ?>
        <div class="ip-pill g"><i class="fas fa-lock me-1"></i>Certificate unlocks at 100%</div>
    <?php endif; ?>
</div>
<?php if (!$modules): ?><div class="ip-card ip-muted">No learning modules yet. HR assigns them to you.</div><?php endif; ?>
<?php foreach ($modules as $m): ?>
<div class="ip-card">
    <div class="ip-head"><h3><?php echo in_e($m['title']); ?></h3><span class="fw-semibold"><?php echo $m['percent']; ?>%</span></div>
    <?php if ($m['description']): ?><p class="ip-muted"><?php echo nl2br(in_e($m['description'])); ?></p><?php endif; ?>
    <div class="ip-bar mb-3"><div style="width:<?php echo $m['percent']; ?>%"></div></div>
    <?php foreach ($m['items'] as $it): $done = (bool) $it['done_at']; $open = $it['file_path'] ? in_file_url('learn', (int) $it['id']) : $it['url']; ?>
        <div class="border rounded-3 p-3 mb-2" id="item-<?php echo (int) $it['id']; ?>">
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <i class="fas <?php echo $icons[$it['type']]; ?> text-success"></i>
                <span class="fw-semibold flex-grow-1"><?php echo in_e($it['title']); ?></span>
                <?php if ($done): ?><span class="ip-pill g">✓ Done<?php echo $it['score'] !== null ? ' · ' . (int) $it['score'] . '%' : ''; ?></span><?php endif; ?>
            </div>
            <?php if ($it['instructions']): ?><div class="small mt-1" style="white-space:pre-line"><?php echo in_e($it['instructions']); ?></div><?php endif; ?>
            <?php if (in_array($it['type'], ['video', 'pdf', 'link'], true)): ?>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <?php if ($open): ?><a class="btn btn-sm btn-outline-success" href="<?php echo in_e($open); ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square me-1"></i>Open</a><?php endif; ?>
                    <?php if (!$done): ?><form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>"><button class="btn btn-sm btn-success">Mark as done</button></form><?php endif; ?>
                </div>
            <?php elseif ($it['type'] === 'quiz' && !$done): $qs = json_decode((string) $it['quiz_json'], true) ?: []; ?>
                <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                    <?php foreach ($qs as $qi => $q): ?>
                        <div class="mb-2"><div class="small fw-semibold"><?php echo ($qi + 1) . '. ' . in_e($q['q']); ?></div>
                            <?php foreach ($q['options'] as $oi => $o): ?><label class="form-check small"><input type="radio" class="form-check-input" name="q[<?php echo $qi; ?>]" value="<?php echo $oi; ?>" required> <?php echo in_e($o); ?></label><?php endforeach; ?></div>
                    <?php endforeach; ?>
                    <button class="btn btn-sm btn-success">Submit answers</button>
                </form>
            <?php elseif ($it['type'] === 'assignment' && !$done): ?>
                <?php if ($open): ?><a class="small d-inline-block mt-1" href="<?php echo in_e($open); ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip me-1"></i>Assignment file</a><?php endif; ?>
                <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="item_id" value="<?php echo (int) $it['id']; ?>">
                    <textarea name="answer" class="form-control form-control-sm mb-2" rows="3" placeholder="Your answer, or a link to your work" required></textarea>
                    <button class="btn btn-sm btn-success">Hand in</button></form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!$m['items']): ?><div class="ip-muted">No items in this module yet.</div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
