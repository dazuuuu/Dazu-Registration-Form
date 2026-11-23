<?php
// Tutor: reset one or more courses (optional tool). Every published assignment of the tutor in each chosen course is sent again as new
// (same content, same order) to the chosen students, with new deadlines, and counts in progress from now. The old ones are archived:
// the work and marks stay on record but no longer count. Optionally, new assignments are added after them in every chosen course.
// Completed and paused students are never included (they are no longer on the tutor's list).
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

$problem = null;
$loadProblem = null;
$courses = [];
$courseStudents = [];
$courseLive = [];
try {
    $courses = $am->tutorCourses($tutorId);
    $allLive = $am->listAll($tutorId);
    foreach ($courses as $cid => $ct) {
        $cid = (int) $cid;
        $courseLive[$cid] = array_values(array_filter($allLive, fn($a) => (int) $a['course_id'] === $cid && $a['status'] === 'published'));
        $courseStudents[$cid] = $am->tutorStudents($tutorId, $cid);
    }
} catch (Throwable $e) {
    // A blank page here (GET, before anything is rendered) is worse than a visible error: show one and log it.
    error_log('reset course (load): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $loadProblem = $e->getMessage();
    $courses = [];
}
$liveIn = fn(int $cid): array => $courseLive[$cid] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back('reset.php');
    try {
        if (!method_exists($am, 'resetCourse')) throw new RuntimeException('The server is missing the latest app/v1/models/AssignmentModel.php. Upload it, then try again.');
        $chosenCourses = array_values(array_intersect(array_map('intval', (array) ($_POST['courses'] ?? [])), array_keys($courses)));
        if (!$chosenCourses) asg_back('reset.php', null, 'Tick at least one course.');
        if (strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'RESET') asg_back('reset.php', null, 'Type RESET in the box to confirm.');
        $first = AssignmentModel::toUtc((string) ($_POST['first_due'] ?? ''));
        if (!$first || AssignmentModel::ts($first) <= time()) asg_back('reset.php', null, 'Set the first deadline in the future.');
        $gap = max(0, min(30, (int) ($_POST['gap_days'] ?? 0)));
        $ticked = array_flip(array_map('strval', (array) ($_POST['students'] ?? [])));   // "course:user"

        // Optional new assignments (title required; rows left empty are ignored).
        $extra = [];
        foreach ((array) ($_POST['new_title'] ?? []) as $i => $t) {
            $t = trim((string) $t);
            if ($t === '') continue;
            $type = (string) ($_POST['new_type'][$i] ?? 'link');
            $extra[] = ['title' => mb_substr($t, 0, 200), 'instructions' => trim((string) ($_POST['new_text'][$i] ?? '')),
                        'type' => in_array($type, ['link', 'text', 'file'], true) ? $type : 'link', 'max' => max(1, min(1000, (int) ($_POST['new_max'][$i] ?? 100)))];
        }

        $notifier = new AssignmentNotifier($pdo, $am);
        $done = [];
        foreach ($chosenCourses as $cid) {
            $students = array_values(array_filter(array_keys($am->tutorStudents($tutorId, $cid)), fn($u) => isset($ticked[$cid . ':' . $u])));
            if (!$students) continue;
            $n = count($liveIn($cid));
            $due = fn(int $i) => gmdate('Y-m-d H:i:s', AssignmentModel::ts($first) + $i * $gap * 86400);
            $dues = [];
            for ($i = 0; $i < $n; $i++) $dues[] = $due($i);
            $r = $n ? $am->resetCourse($tutorId, $cid, $dues, $students, $me) : ['new' => [], 'uids' => $students];
            // New assignments go after the reset ones, with the next deadlines.
            foreach ($extra as $k => $x) {
                $saved = $am->save($tutorId, null, [
                    'course_id' => $cid, 'title' => $x['title'], 'description' => '', 'details' => null, 'instructions' => $x['instructions'],
                    'due_at' => $due($n + $k), 'sequential_lock' => 1, 'close_after_due' => 0, 'max_extension_days' => 7,
                    'submit_link' => $x['type'] === 'link' ? 1 : 0, 'submit_text' => $x['type'] === 'text' ? 1 : 0, 'submit_images' => 0, 'submit_file' => $x['type'] === 'file' ? 1 : 0,
                    'link_sites' => null, 'grading_mode' => 'marks', 'max_score' => $x['max'], 'pass_mark_percent' => 50,
                ], [], [], $students, $me);
                $am->publish($saved['id'], $me);
                $r['new'][] = (int) $saved['id'];
            }
            if (!$r['new']) continue;
            // One email per student (the first assignment); the rest show in their list and unlock in order.
            $notifier->published($am->get($r['new'][0]), $students);
            foreach (array_slice($r['new'], 1) as $nid) $am->markNotified($nid, $students);
            foreach ($students as $uid) {
                PortalNotifications::notify($pdo, $uid, 'assignment', 'Your assignments were reset: ' . $courses[$cid],
                    count($r['new']) . ' assignments to do. First deadline ' . AssignmentModel::local($dues[0] ?? $due(0), 'D j M, H:i') . '.', 'students/assignments/index.php');
            }
            $done[] = $courses[$cid] . ' (' . count($r['new']) . ' assignments, ' . count($students) . ' students)';
        }
        PortalNotifications::flush();
        if (!$done) asg_back('reset.php', null, 'Nothing was reset: tick at least one student in a chosen course.');
        asg_back('index.php?tab=all', 'Reset done: ' . implode('; ', $done) . '. The old assignments are archived (their marks are kept but no longer count).');
    } catch (Throwable $e) {
        error_log('reset course: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $problem = $e->getMessage();
    }
}

$defaultDue = (new DateTime('now', new DateTimeZone(AssignmentModel::TZ)))->modify('+7 days')->format('Y-m-d') . 'T17:00';
$pre = (int) ($_GET['course'] ?? 0);
$page_title = 'Reset courses';
ob_start();
?>
<a href="index.php?tab=all" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<?php if ($problem): ?><div class="alert alert-danger"><strong>The reset did not finish.</strong> Courses listed before the problem may already be reset; check Assignments. Details: <?php echo asg_e($problem); ?></div><?php endif; ?>
<?php if ($loadProblem): ?><div class="alert alert-danger"><strong>Could not load your courses.</strong> <?php echo asg_e($loadProblem); ?></div><?php endif; ?>
<style>
.rs-c{border:1px solid #dfe7e3;border-radius:12px;padding:10px 12px;margin-bottom:8px}
.rs-c summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px}.rs-c summary::-webkit-details-marker{display:none}
.rs-new{border:1px dashed #b9cfc4;border-radius:12px;padding:10px;margin-bottom:8px}
</style>
<form method="POST" class="card-tutor p-3 p-md-4"><?php echo csrf_field(); ?>
    <h5 class="mb-1"><i class="fas fa-rotate-left me-2 text-warning"></i>Reset courses</h5>
    <p class="small text-muted mb-3">Optional. Send all the assignments of one or more courses again as new, with new deadlines, so students do them again and they count in progress.
        The old assignments are archived: what students handed in and the marks stay on record, but no longer count.</p>

    <h6 class="fw-bold">1. Courses</h6>
    <?php if (!$courses): ?><p class="small text-muted">You have no current students, so there is no course to reset.</p><?php endif; ?>
    <?php foreach ($courses as $cid => $ct): $live = $liveIn((int) $cid); $studs = $courseStudents[(int) $cid] ?? []; ?>
    <details class="rs-c" <?php echo $pre === (int) $cid ? 'open' : ''; ?>>
        <summary><input type="checkbox" class="form-check-input rs-course" name="courses[]" value="<?php echo (int) $cid; ?>" <?php echo $pre === (int) $cid ? 'checked' : ''; ?> onclick="event.stopPropagation()">
            <span class="fw-semibold flex-grow-1"><?php echo asg_e($ct); ?></span>
            <span class="small text-muted"><?php echo count($live); ?> assignment<?php echo count($live) === 1 ? '' : 's'; ?> · <?php echo count($studs); ?> student<?php echo count($studs) === 1 ? '' : 's'; ?></span>
            <i class="fas fa-chevron-down small text-muted"></i></summary>
        <div class="mt-2 small">
            <?php if ($live): ?><div class="text-muted mb-1">Sent again:</div><ol class="mb-2"><?php foreach ($live as $a): ?><li><?php echo asg_e($a['title']); ?></li><?php endforeach; ?></ol>
            <?php else: ?><div class="text-muted mb-2">No published assignments here. Only the new assignments below (if any) will be sent.</div><?php endif; ?>
            <div class="text-muted mb-1">Students (untick anyone to leave out):</div>
            <?php foreach ($studs as $uid => $s): ?><label class="d-block"><input type="checkbox" class="form-check-input me-1" name="students[]" value="<?php echo (int) $cid . ':' . (int) $uid; ?>" checked> <?php echo asg_e($s['full_name']); ?> <span class="text-muted"><?php echo asg_e($s['registration_no']); ?></span></label><?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>

    <h6 class="fw-bold mt-4">2. Deadlines</h6>
    <div class="row g-3 mb-2">
        <div class="col-md-5"><label class="form-label small fw-semibold mb-0">First deadline (EAT)</label>
            <input type="datetime-local" name="first_due" class="form-control" required value="<?php echo asg_e($_POST['first_due'] ?? $defaultDue); ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Days between deadlines</label>
            <input type="number" name="gap_days" class="form-control" min="0" max="30" value="<?php echo (int) ($_POST['gap_days'] ?? 7); ?>">
            <div class="small text-muted">0 = all due on the same day.</div></div>
    </div>

    <h6 class="fw-bold mt-4">3. New assignments <span class="fw-normal text-muted small">(optional, added after the others in every ticked course)</span></h6>
    <div id="rsNew">
        <div class="rs-new">
            <div class="row g-2">
                <div class="col-md-6"><input name="new_title[]" class="form-control" maxlength="200" placeholder="Title, e.g. Build a login page"></div>
                <div class="col-6 col-md-3"><select name="new_type[]" class="form-select"><option value="link">Submit a link</option><option value="text">Type an answer</option><option value="file">Upload a file</option></select></div>
                <div class="col-6 col-md-3"><div class="input-group"><span class="input-group-text">Marks</span><input name="new_max[]" type="number" min="1" max="1000" value="100" class="form-control"></div></div>
                <div class="col-12"><textarea name="new_text[]" class="form-control" rows="2" placeholder="What students must do (questions, instructions)"></textarea></div>
            </div>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-success mb-4" id="rsAdd"><i class="fas fa-plus me-1"></i>Add another new assignment</button>

    <h6 class="fw-bold">4. Confirm</h6>
    <div class="d-flex gap-2 flex-wrap"><input name="confirm" class="form-control" style="max-width:200px" autocomplete="off" required placeholder="Type RESET">
        <button class="btn btn-warning fw-semibold" <?php echo $courses ? '' : 'disabled'; ?>><i class="fas fa-rotate-left me-1"></i>Reset ticked courses</button></div>
</form>
<script>
document.getElementById('rsAdd').onclick = function () {
    var box = document.getElementById('rsNew'), c = box.firstElementChild.cloneNode(true);
    c.querySelectorAll('input[name="new_title[]"],textarea').forEach(function (i) { i.value = ''; });
    box.appendChild(c);
};
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
