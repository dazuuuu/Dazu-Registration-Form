<?php
// A teaching topic from a tutor: topic, subtopics, date and time, duration, notes, resources and the FIRST NAMES of the
// students only. "Mark as taught" sends a short report to the tutor. The tutor's rating shows here and in Assessments.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

$topicId = (int) ($_GET['id'] ?? 0);
$t = it_topic_for_intern($db, (int) $intern['id'], $topicId);
if (!$t) {
    SessionModel::setFlash('error', 'Topic not found.');
    header('Location: ' . url('intern/tasks/index.php?view=list'));
    exit;
}
$self = url('intern/tasks/teaching.php?id=' . $topicId);
if (intern_post_ok()) {
    $r = it_mark_taught($db, $intern, $topicId, $_POST);
    SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Marked as taught. Your report was sent to the tutor.' : $r['error']));
    header('Location: ' . $self);
    exit;
}
$stars = fn(?int $n) => '<span class="ip-stars">' . str_repeat('★', (int) $n) . '<span class="off">' . str_repeat('★', 5 - (int) $n) . '</span></span>';
$page_title = $t['title'];
$page_subtitle = 'Teaching · ' . $t['tutor_name'];
ob_start();
?>
<a href="<?php echo url('intern/tasks/index.php?view=list'); ?>#teaching" class="btn btn-link btn-sm ps-0">← My Tasks</a>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="ip-card">
            <div class="d-flex gap-2 align-items-center mb-2"><span class="ip-pill d">Teaching</span><h3 class="me-auto"><?php echo in_e($t['title']); ?></h3>
                <span class="ip-pill <?php echo $t['status'] === 'taught' ? 'g' : 'n'; ?>"><?php echo $t['status'] === 'taught' ? 'Taught' : 'Planned'; ?></span></div>
            <div class="ip-muted mb-3"><i class="far fa-calendar me-1"></i><?php echo $t['starts_at'] ? date('l j M Y, g:i A', strtotime($t['starts_at'])) : 'Date to be set'; ?>
                <?php echo $t['duration_min'] ? ' · ' . (int) $t['duration_min'] . ' minutes' : ''; ?> · Tutor: <?php echo in_e($t['tutor_name']); ?></div>
            <?php if ($t['subtopics']): ?><div class="fw-semibold small">Subtopics</div><ul class="small"><?php foreach (array_filter(array_map('trim', preg_split('/\r?\n/', $t['subtopics']))) as $s): ?><li><?php echo in_e($s); ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php if ($t['notes']): ?><div class="fw-semibold small">Notes from the tutor</div><p class="small" style="white-space:pre-line"><?php echo in_e($t['notes']); ?></p><?php endif; ?>
            <?php if ($t['resources'] || $t['file_path']): ?><div class="fw-semibold small">Resources</div>
                <?php foreach (array_filter(preg_split('/\r?\n/', (string) $t['resources'])) as $l): ?><div class="small"><i class="fas fa-link me-1 text-success"></i><a href="<?php echo in_e($l); ?>" target="_blank" rel="noopener"><?php echo in_e($l); ?></a></div><?php endforeach; ?>
                <?php if ($t['file_path']): ?><div class="small"><i class="fas fa-paperclip me-1 text-success"></i><a target="_blank" href="<?php echo in_e(in_file_url('teach', (int) $t['id'])); ?>"><?php echo in_e($t['file_name']); ?></a></div><?php endif; ?>
            <?php endif; ?>
            <div class="fw-semibold small mt-3">Students in the group</div>
            <div><?php foreach ($t['first_names'] as $n): ?><span class="ip-tag"><?php echo in_e($n); ?></span><?php endforeach; ?></div>
            <div class="ip-muted mt-2">You help teach. The tutor stays in charge of these students, their marks and certificates.</div>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($t['status'] === 'planned' && $t['assignment_status'] === 'active'): ?>
        <form method="POST" class="ip-card"><?php echo csrf_field(); ?>
            <h3 class="mb-3">Mark as taught</h3>
            <label class="form-label small fw-semibold">What did you cover? *</label><textarea name="covered" class="form-control mb-2" rows="3" required></textarea>
            <label class="form-label small fw-semibold">What went well?</label><textarea name="went_well" class="form-control mb-2" rows="2"></textarea>
            <label class="form-label small fw-semibold">Challenges</label><textarea name="challenges" class="form-control mb-2" rows="2"></textarea>
            <label class="form-label small fw-semibold">Questions from students</label><textarea name="questions" class="form-control mb-3" rows="2"></textarea>
            <button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Mark as taught</button>
        </form>
        <?php elseif ($t['status'] === 'taught'): ?>
        <div class="ip-card">
            <h3 class="mb-2">Your report</h3>
            <?php foreach (['covered' => 'Covered', 'went_well' => 'Went well', 'challenges' => 'Challenges', 'questions' => 'Questions from students'] as $k => $l): if ($t[$k]): ?>
                <div class="small mb-2"><strong><?php echo $l; ?>:</strong> <span style="white-space:pre-line"><?php echo in_e($t[$k]); ?></span></div><?php endif; endforeach; ?>
            <h3 class="mb-2 mt-3">Tutor's rating</h3>
            <?php if ($t['preparation'] === null): ?><div class="ip-muted">The tutor has not rated this session yet.</div>
            <?php else: ?>
                <?php foreach (['preparation' => 'Preparation', 'clarity' => 'Clarity', 'confidence' => 'Confidence'] as $k => $l): ?>
                    <div class="d-flex justify-content-between small"><span><?php echo $l; ?></span><?php echo $stars((int) $t[$k]); ?></div><?php endforeach; ?>
                <?php if ($t['rating_comment']): ?><div class="small mt-2 p-2 bg-light rounded-3">“<?php echo in_e($t['rating_comment']); ?>”</div><?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?><div class="ip-card ip-muted">This teaching assignment has ended.</div><?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
