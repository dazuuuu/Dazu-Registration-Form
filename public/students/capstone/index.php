<?php
// Student: my capstone(s). A capstone appears here only after the tutor scheduled or opened it. Before it opens, only the date shows.
require __DIR__ . '/../../components/capstone/student_boot.php';

use Models\AssignmentModel;

require_once __DIR__ . '/../../../app/v1/services/AssignmentAverage.php';
$list = cap_student_capstones($pdo, $uid);
$avg = AssignmentAverage::forStudent($pdo, $uid);
$page_title = 'Capstone';
ob_start();
?>
<div class="mb-3">
    <h4 class="mb-1">Capstone</h4>
    <div class="text-muted small">Your final project. It is done after your lessons end.</div>
</div>

<?php
// Course complete: the transcript and the letter of recommendation (released when the four conditions are true).
$doneCourses = [];
foreach ($list as $c) {
    $cid = (int) $c['course_id'];
    if (isset($doneCourses[$cid])) continue;
    $rel = cap_release_state($pdo, $uid, $cid);
    if ($rel['transcript']) $doneCourses[$cid] = $c['course_title'];
}
foreach ($doneCourses as $cid => $ct): ?>
    <div class="card-tutor p-3 p-md-4 mb-3" style="border:2px solid #44A024">
        <div class="fw-bold"><i class="fas fa-award text-success me-1"></i>Your course is complete: <?php echo asg_e($ct); ?></div>
        <div class="small text-muted mb-2">Your transcript and your letter of recommendation are ready.</div>
        <a class="btn btn-brand btn-sm" href="<?php echo asg_e(url('capstone/transcript.php?course=' . $cid)); ?>">Open my transcript</a>
        <a class="btn btn-outline-dark btn-sm" target="_blank" href="<?php echo asg_e(url('capstone/letter.php?course=' . $cid . '&dl=1')); ?>"><i class="fas fa-download me-1"></i>Download my letter of recommendation</a>
    </div>
<?php endforeach; ?>

<?php if (!$list): ?>
    <div class="card-tutor p-4 text-center text-muted"><i class="fas fa-graduation-cap fa-2x mb-2"></i><div>Nothing here yet. Your tutor will tell you when your capstone is ready.</div></div>
<?php endif; ?>

<?php foreach ($list as $c):
    $open = cap_is_open_for($c);
    $row = cap_for_student($pdo, (int) $c['id'], $uid);
    $att = $open ? cap_latest_attempt($pdo, (int) $c['id'], $uid) : null;
    $due = $open ? cap_due_for($c) : null;
    $daysLeft = $due ? (int) ceil((strtotime($due . ' UTC') - time()) / 86400) : null;
    $state = $open ? cap_state($c, $att) : $c['status'];
?>
    <?php if (!$open && $c['status'] !== 'closed'): ?>
        <div class="card-tutor p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="fw-bold fs-5"><i class="fas fa-lock me-2 text-muted"></i>Your capstone is not open yet</div>
                    <div class="mt-1"><?php echo $c['opens_at'] ? 'Your capstone opens on <strong>' . asg_e(cap_local($c['opens_at'], 'l j F Y, H:i')) . ' EAT</strong>.' : 'Your tutor will open it for you.'; ?></div>
                    <div class="small text-muted mt-1"><?php echo asg_e($c['course_title']); ?></div>
                </div>
                <?php echo cap_chip('scheduled'); ?>
            </div>
            <hr>
            <div class="row g-3 small">
                <div class="col-md-6"><div class="fw-semibold mb-1">How it works</div>
                    <ul class="ps-3 mb-0"><li>It is your final project, marked out of 100. The pass mark is 50.</li><li>Once you send it, it is final unless your tutor returns it, and only twice.</li><li>You will see the project brief and what to send when it opens.</li></ul></div>
                <div class="col-md-6"><div class="fw-semibold mb-1">Your course so far</div>
                    <?php $cs = $avg[(int) $c['course_id']] ?? null; ?>
                    <div><?php echo $cs ? 'Average from your assignments: <strong>' . asg_e($cs['display']) . '</strong> (' . (int) $cs['counted'] . ' marked)' : 'No marks yet.'; ?></div>
                    <a href="<?php echo asg_e(url('students/assignments/index.php')); ?>" class="small">See my assignments</a></div>
            </div>
            <div class="mt-3"><a class="btn btn-outline-dark btn-sm" href="view.php?id=<?php echo (int) $c['id']; ?>">Open</a></div>
        </div>
    <?php else: ?>
        <a href="view.php?id=<?php echo (int) $c['id']; ?>" class="text-decoration-none text-reset">
            <div class="asg-card is-open">
                <div class="num"><i class="fas fa-graduation-cap"></i></div>
                <div class="flex-grow-1">
                    <div class="k">CAPSTONE</div>
                    <div class="t"><?php echo asg_e($c['title']); ?></div>
                    <div class="s"><?php echo asg_e($c['course_title']); ?> · <?php echo $due ? 'Final due ' . asg_e(cap_local($due, 'D j M, H:i')) . ' EAT' : ''; ?><?php echo $daysLeft !== null && $c['status'] === 'open' && !in_array($state, ['to_mark', 'marked'], true) ? ' · ' . ($daysLeft >= 0 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left' : 'past the due date') : ''; ?></div>
                </div>
                <?php echo cap_chip($state); ?>
            </div>
        </a>
    <?php endif; ?>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
