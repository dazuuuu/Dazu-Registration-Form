<?php
// Tutor: reset a course. Every published assignment of the tutor in the course is sent again as new (same content, same order) to the chosen
// students, with new deadlines, and counts in student progress from now. The old ones are archived: the work and marks are kept on record,
// but no longer count. Completed and paused students are not included (they are no longer on the tutor's list).
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$courses = $am->tutorCourses($tutorId);
$courseId = (int) ($_GET['course'] ?? $_POST['course_id'] ?? (count($courses) === 1 ? array_key_first($courses) : 0));
if (!isset($courses[$courseId])) $courseId = 0;

$liveIn = function (int $cid) use ($am, $tutorId): array {
    return array_values(array_filter($am->listAll($tutorId), fn($a) => (int) $a['course_id'] === $cid && $a['status'] === 'published'));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back('reset.php');
    $self = 'reset.php?course=' . $courseId;
    if (!$courseId) asg_back('reset.php', null, 'Choose one of your courses.');
    if (($_POST['confirm'] ?? '') !== 'RESET') asg_back($self, null, 'Type RESET in the box to confirm.');
    $first = AssignmentModel::toUtc((string) ($_POST['first_due'] ?? ''));
    if (!$first || AssignmentModel::ts($first) <= time()) asg_back($self, null, 'Set the first deadline in the future.');
    $gap = max(0, min(30, (int) ($_POST['gap_days'] ?? 0)));
    $students = $am->tutorStudents($tutorId, $courseId);
    $chosen = array_values(array_intersect(array_map('intval', (array) ($_POST['students'] ?? [])), array_keys($students)));
    if (!$chosen) asg_back($self, null, 'Choose at least one student.');
    $count = count($liveIn($courseId));
    if (!$count) asg_back($self, null, 'There are no published assignments in this course to send again.');
    $dues = [];
    for ($i = 0; $i < $count; $i++) $dues[] = gmdate('Y-m-d H:i:s', AssignmentModel::ts($first) + $i * $gap * 86400);

    $r = $am->resetCourse($tutorId, $courseId, $dues, $chosen, $me);
    if ($r['new']) {
        // One email per student (the first assignment); the rest show in their list and unlock in order.
        $notifier = new AssignmentNotifier($pdo, $am);
        $notifier->published($am->get($r['new'][0]), $r['uids']);
        foreach (array_slice($r['new'], 1) as $nid) $am->markNotified($nid, $r['uids']);
        foreach ($r['uids'] as $uid) {
            PortalNotifications::notify($pdo, $uid, 'assignment', 'Your assignments were reset: ' . $courses[$courseId],
                count($r['new']) . ' assignments to do again. First deadline ' . AssignmentModel::local($dues[0], 'D j M, H:i') . '.', 'students/assignments/index.php');
        }
        PortalNotifications::flush();
    }
    asg_back('index.php?tab=all', count($r['new']) . ' assignment' . (count($r['new']) === 1 ? ' was' : 's were') . ' sent again to ' . count($r['uids'])
        . ' student' . (count($r['uids']) === 1 ? '' : 's') . '. The old ones are archived (their marks are kept but no longer count).');
}

$live = $courseId ? $liveIn($courseId) : [];
$students = $courseId ? $am->tutorStudents($tutorId, $courseId) : [];
$defaultDue = (new DateTime('now', new DateTimeZone(AssignmentModel::TZ)))->modify('+7 days')->format('Y-m-d') . 'T17:00';
$page_title = 'Reset a course';
ob_start();
?>
<a href="index.php?tab=all" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <h5 class="mb-1"><i class="fas fa-rotate-left me-2 text-success"></i>Reset a course</h5>
    <p class="small text-muted mb-3">Send every assignment of a course again as new, with new deadlines. Students do them again and they count in their progress.
        The old assignments are archived: what students handed in and the marks stay on record, but no longer count.</p>
    <form method="GET"><label class="form-label small fw-semibold mb-0" for="courseSel">Course</label>
        <select name="course" id="courseSel" class="form-select" style="max-width:420px" onchange="this.form.submit()"><option value="">Choose a course…</option>
            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === $courseId ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?></select>
        <noscript><button class="btn btn-outline-dark mt-2">Show</button></noscript></form>
</div>

<?php if ($courseId): ?>
<?php if (!$live): ?>
<div class="alert alert-info">This course has no published assignments to send again.</div>
<?php else: ?>
<form method="POST" class="card-tutor p-3 p-md-4"><?php echo csrf_field(); ?><input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
    <h6 class="fw-bold"><?php echo count($live); ?> assignment<?php echo count($live) === 1 ? '' : 's'; ?> will be sent again</h6>
    <ol class="small mb-3"><?php foreach ($live as $a): ?><li><?php echo asg_e($a['title']); ?></li><?php endforeach; ?></ol>
    <div class="row g-3 mb-3">
        <div class="col-md-5"><label class="form-label small fw-semibold mb-0">First deadline (EAT)</label>
            <input type="datetime-local" name="first_due" class="form-control" required value="<?php echo asg_e($defaultDue); ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Days between deadlines</label>
            <input type="number" name="gap_days" class="form-control" min="0" max="30" value="7">
            <div class="small text-muted">0 = all due on the same day.</div></div>
    </div>
    <h6 class="fw-bold mb-1">Students</h6>
    <div class="small text-muted mb-2">Only students still studying with you. Completed and paused students are left out.</div>
    <div class="border rounded p-2 mb-3" style="max-height:260px;overflow:auto">
        <label class="d-block small fw-semibold mb-1"><input type="checkbox" checked onclick="document.querySelectorAll('.rs-st').forEach(c => c.checked = this.checked)"> All</label>
        <?php foreach ($students as $uid => $s): ?><label class="d-block"><input type="checkbox" class="rs-st" name="students[]" value="<?php echo (int) $uid; ?>" checked> <?php echo asg_e($s['full_name']); ?> <span class="small text-muted"><?php echo asg_e($s['registration_no']); ?></span></label><?php endforeach; ?>
        <?php if (!$students): ?><span class="small text-muted">No current students in this course.</span><?php endif; ?>
    </div>
    <label class="form-label small fw-semibold mb-0">Type RESET to confirm</label>
    <div class="d-flex gap-2 flex-wrap"><input name="confirm" class="form-control" style="max-width:200px" autocomplete="off" required placeholder="RESET">
        <button class="btn btn-brand" <?php echo $students ? '' : 'disabled'; ?>><i class="fas fa-rotate-left me-1"></i>Reset and send again</button></div>
</form>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
