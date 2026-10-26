<?php
// Tutor: set up a capstone (new or edit). Sections: the capstone, example project, what students send, when students see it,
// who does it, milestones and time keeping, marks out of 100, returns and retakes (fixed).
require __DIR__ . '/../../components/capstone/tutor_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$cap = $id ? cap_get_for_tutor($pdo, $id, $tutorId) : null;
if ($id && !$cap) asg_back('index.php', null, 'Capstone not found.');
$courses = $am->tutorCourses($tutorId);
$locked = $cap && cap_has_attempts($pdo, $id);
$hasMarks = $cap && cap_has_marks($pdo, $id);

// Students of the tutor (all courses), with their intake when known.
$myStudents = $am->tutorStudents($tutorId);
$intakeOf = [];
try {
    $q = $pdo->query("SELECT r.user_id, b.intake_id FROM registrations r JOIN intake_bookings b ON b.id = r.booking_id WHERE r.status = 'approved'");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $intakeOf[(int) $r['user_id']] = (int) $r['intake_id'];
} catch (Throwable $e) {
}
$intakes = [];
try {
    require_once __DIR__ . '/../../../app/v1/services/IntakeService.php';
    if (ik_installed($pdo)) foreach (ik_intakes($pdo) as $i) $intakes[(int) $i['id']] = $i['name'];
} catch (Throwable $e) {
}

$errors = [];
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($posted) {
    asg_csrf_or_back('edit.php' . ($id ? '?id=' . $id : ''));
    $action = $_POST['action'] ?? 'save_draft';
    $vis = $_POST['visibility'] ?? 'hidden';
    if ($cap && in_array($cap['status'], ['open', 'closed'], true)) $vis = $cap['status'] === 'open' ? 'now' : 'hidden';   // moved by Open now / Close only
    elseif ($action === 'save_draft') $vis = 'hidden';
    elseif ($vis === 'hidden') $errors[] = 'Choose "Open now" or "Opens on a date and time", or press Save draft to keep it hidden.';

    $linkRaw = trim((string) ($_POST['example_link'] ?? ''));
    $exLink = $linkRaw === '' ? '' : asg_clean_link($linkRaw, true);
    if ($linkRaw !== '' && !$exLink) $errors[] = 'The example link must start with https://';

    $dels = [];
    foreach (CAP_DELIVERABLES as $kind => [$label, $type]) {
        if (!empty($_POST['del'][$kind])) $dels[] = ['kind' => $kind, 'label' => $label, 'item_type' => $type, 'is_required' => !empty($_POST['req'][$kind]) ? 1 : 0];
    }
    foreach ((array) ($_POST['custom_label'] ?? []) as $i => $lbl) {
        $lbl = mb_substr(trim((string) $lbl), 0, 120);
        if ($lbl === '') continue;
        $dels[] = ['kind' => 'custom', 'label' => $lbl, 'item_type' => ($_POST['custom_type'][$i] ?? 'link') === 'file' ? 'file' : 'link', 'is_required' => !empty($_POST['custom_req'][$i]) ? 1 : 0];
    }
    $mss = [];
    foreach ((array) ($_POST['ms_title'] ?? []) as $i => $t) {
        if (trim((string) $t) === '' && trim((string) ($_POST['ms_due'][$i] ?? '')) === '') continue;
        $mss[] = ['id' => (int) ($_POST['ms_id'][$i] ?? 0), 'title' => (string) $t, 'kind' => $_POST['ms_kind'][$i] ?? 'work',
                  'due_at' => AssignmentModel::toUtc((string) ($_POST['ms_due'][$i] ?? '')), 'marks' => (float) ($_POST['ms_marks'][$i] ?? 0)];
    }
    $team = ($_POST['mode'] ?? '') === 'team';
    $studs = [];
    foreach ((array) ($_POST['sel'] ?? []) as $uid => $_) {
        $studs[] = ['uid' => (int) $uid, 'team_no' => $team ? (int) ($_POST['team'][$uid] ?? 0) : null, 'own_due_at' => AssignmentModel::toUtc((string) ($_POST['own_due'][$uid] ?? '')) ?: null];
    }
    $d = [
        'course_id' => (int) ($_POST['course_id'] ?? 0), 'intake_id' => (int) ($_POST['intake_id'] ?? 0) ?: null, 'title' => trim((string) ($_POST['title'] ?? '')),
        'brief' => trim((string) ($_POST['brief'] ?? '')), 'instructions' => trim((string) ($_POST['instructions'] ?? '')), 'requirements' => trim((string) ($_POST['requirements'] ?? '')),
        'example_link' => $exLink ?: null, 'mode' => $_POST['mode'] ?? 'individual', 'team_size' => (int) ($_POST['team_size'] ?? 3), 'visibility' => $vis,
        'opens_at' => AssignmentModel::toUtc((string) ($_POST['opens_local'] ?? '')), 'due_at' => AssignmentModel::toUtc((string) ($_POST['due_local'] ?? '')),
        'w_project' => (int) ($_POST['w_project'] ?? 0), 'w_docs' => (int) ($_POST['w_docs'] ?? 0), 'w_present' => (int) ($_POST['w_present'] ?? 0), 'w_time' => (int) ($_POST['w_time'] ?? 0),
        'weight' => (float) ($_POST['weight'] ?? 5), 'deliverables' => $dels, 'milestones' => $mss, 'students' => $studs,
    ];
    if ($cap) {
        // A capstone that is already open or closed keeps its dates unless the tutor changed them here.
        if ($cap['status'] !== 'draft' && $d['opens_at'] === null) $d['opens_at'] = $cap['opens_at'];
    }
    // Example file (kept private)
    $exFile = null;
    if (!$errors && !empty($_FILES['example_file']['name']) && ($_FILES['example_file']['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) {
        $res = asg_store_document($_FILES['example_file'], 'capstone/example', ['pdf', 'zip', 'docx', 'pptx'], ASG_FILE_MAX);
        if (!$res['ok']) $errors[] = $res['error']; else $exFile = [$res['path'], mb_substr(basename((string) $_FILES['example_file']['name']), 0, 200)];
    }
    if (!$errors) {
        $r = cap_save($pdo, $tutorId, $id ?: null, $d, $me);
        if ($r['ok']) {
            $newId = (int) $r['id'];
            if ($exFile) $pdo->prepare('UPDATE capstones SET example_file = ?, example_file_name = ? WHERE id = ?')->execute([$exFile[0], $exFile[1], $newId]);
            elseif (!empty($_POST['remove_example_file'])) $pdo->prepare('UPDATE capstones SET example_file = NULL, example_file_name = NULL WHERE id = ?')->execute([$newId]);
            $msg = 'Saved.';
            if (!empty($r['became_open'])) {
                $told = cap_notify_open($pdo, $newId);
                AssignmentNotifier::flush();
                $msg = 'Saved and open. ' . $told . ' student' . ($told === 1 ? ' was' : 's were') . ' told.';
            } elseif (($d['visibility'] ?? '') === 'date') {
                $msg = 'Saved. It will open on ' . AssignmentModel::local($d['opens_at'], 'D j M Y, H:i') . ' EAT. Students will see only the opening date.';
            } elseif ($d['visibility'] === 'hidden') {
                $msg = 'Draft saved. Students cannot see it.';
            }
            asg_back('edit.php?id=' . $newId, $msg);
        }
        $errors = $r['errors'];
    }
}

// Values to show: the posted ones after an error, else the saved capstone, else the defaults.
$v = function (string $k, $default = '') use ($posted, $cap) {
    if ($posted && array_key_exists($k, $_POST)) return $_POST[$k];
    return $cap[$k] ?? $default;
};
$curDels = [];
if ($cap) foreach (cap_deliverables($pdo, $id) as $r) $curDels[$r['kind'] === 'custom' ? 'custom|' . $r['label'] : $r['kind']] = $r;
$customRows = [];
if ($posted) {
    foreach ((array) ($_POST['custom_label'] ?? []) as $i => $l) if (trim((string) $l) !== '') $customRows[] = ['label' => $l, 'item_type' => ($_POST['custom_type'][$i] ?? 'link'), 'is_required' => !empty($_POST['custom_req'][$i])];
} else {
    foreach ($curDels as $k => $r) if ($r['kind'] === 'custom') $customRows[] = $r;
}
$msRows = [];
if ($posted) {
    foreach ((array) ($_POST['ms_title'] ?? []) as $i => $t) $msRows[] = ['id' => (int) ($_POST['ms_id'][$i] ?? 0), 'title' => $t, 'kind' => $_POST['ms_kind'][$i] ?? 'work', 'due' => $_POST['ms_due'][$i] ?? '', 'marks' => $_POST['ms_marks'][$i] ?? 0];
} elseif ($cap) {
    foreach (cap_milestones($pdo, $id) as $m) $msRows[] = ['id' => (int) $m['id'], 'title' => $m['title'], 'kind' => $m['kind'], 'due' => AssignmentModel::localInput($m['due_at']), 'marks' => $m['marks']];
} else {
    $msRows = [
        ['id' => 0, 'title' => 'Project proposal', 'kind' => 'work', 'due' => '', 'marks' => 3],
        ['id' => 0, 'title' => 'Progress check', 'kind' => 'work', 'due' => '', 'marks' => 3],
        ['id' => 0, 'title' => 'Final project sent', 'kind' => 'final', 'due' => '', 'marks' => 2],
        ['id' => 0, 'title' => 'Presentation on the booked day', 'kind' => 'presentation', 'due' => '', 'marks' => 2],
    ];
}
$chosen = [];
$teamOf = [];
$ownDue = [];
if ($posted) {
    foreach ((array) ($_POST['sel'] ?? []) as $uid => $_) $chosen[(int) $uid] = true;
    foreach ((array) ($_POST['team'] ?? []) as $uid => $n) $teamOf[(int) $uid] = (int) $n;
    foreach ((array) ($_POST['own_due'] ?? []) as $uid => $n) $ownDue[(int) $uid] = $n;
} elseif ($cap) {
    foreach (cap_students($pdo, $id) as $uid => $s) { $chosen[$uid] = true; $teamOf[$uid] = (int) $s['team_no']; $ownDue[$uid] = $s['own_due_at'] ? AssignmentModel::localInput($s['own_due_at']) : ''; }
}
$vis = $posted ? ($_POST['visibility'] ?? 'hidden') : ($cap ? ($cap['status'] === 'scheduled' ? 'date' : ($cap['status'] === 'open' ? 'now' : 'hidden')) : 'hidden');
$opensLocal = $posted ? ($_POST['opens_local'] ?? '') : ($cap && $cap['opens_at'] ? AssignmentModel::localInput($cap['opens_at']) : '');
$dueLocal = $posted ? ($_POST['due_local'] ?? '') : ($cap && $cap['due_at'] ? AssignmentModel::localInput($cap['due_at']) : '');
$mode = $v('mode', 'individual');
$page_title = $cap ? 'Edit capstone' : 'New capstone';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Capstone</a>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <h4 class="mb-0"><?php echo $cap ? 'Edit capstone' : 'New capstone'; ?> <?php if ($cap) echo cap_chip($cap['status']); ?></h4>
    <?php if ($cap): ?><div class="d-flex gap-2"><a class="btn btn-outline-dark btn-sm" href="track.php?id=<?php echo $id; ?>">Track</a><a class="btn btn-outline-dark btn-sm" href="slots.php?id=<?php echo $id; ?>">Presentation slots</a></div><?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><strong>Please fix this:</strong><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo asg_e($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="capForm" novalidate><?php echo csrf_field(); ?>
<div class="row g-3">
<div class="col-lg-8">

    <!-- 1 The capstone -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">1. The capstone</h6>
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label small fw-semibold">Course</label>
                <select name="course_id" id="courseSel" class="form-select" required <?php echo $locked ? 'disabled' : ''; ?>>
                    <option value="">Choose a course</option>
                    <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $v('course_id', 0) === (int) $cid ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?>
                </select><?php if ($locked): ?><input type="hidden" name="course_id" value="<?php echo (int) $cap['course_id']; ?>"><?php endif; ?></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Intake <span class="text-muted fw-normal">(optional)</span></label>
                <select name="intake_id" id="intakeSel" class="form-select"><option value="">Not set</option>
                    <?php foreach ($intakes as $iid => $in): ?><option value="<?php echo (int) $iid; ?>" <?php echo (int) $v('intake_id', 0) === (int) $iid ? 'selected' : ''; ?>><?php echo asg_e($in); ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-12"><label class="form-label small fw-semibold">Title</label><input name="title" class="form-control" maxlength="200" required value="<?php echo asg_e($v('title')); ?>" placeholder="Capstone: Build a small business website"></div>
            <div class="col-12"><label class="form-label small fw-semibold">Project brief <span class="text-muted fw-normal">(what the project is)</span></label><textarea name="brief" class="form-control" rows="4"><?php echo asg_e($v('brief')); ?></textarea></div>
            <div class="col-12"><label class="form-label small fw-semibold">Instructions <span class="text-muted fw-normal">(how to do it and how to send it)</span></label><textarea name="instructions" class="form-control" rows="4"><?php echo asg_e($v('instructions')); ?></textarea></div>
            <div class="col-12"><label class="form-label small fw-semibold">Requirements <span class="text-muted fw-normal">(one per line)</span></label><textarea name="requirements" class="form-control" rows="4" placeholder="Works on a phone&#10;At least 4 pages"><?php echo asg_e($v('requirements')); ?></textarea></div>
        </div>
    </div>

    <!-- 2 Example project -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">2. Example project <span class="text-muted fw-normal small">(optional)</span></h6>
        <div class="cap-example">
            <div class="cap-banner">EXAMPLE ONLY. DO NOT COPY THIS.</div>
            <div class="body small text-muted">Every student sees this banner on top of the example. It cannot be removed. They must tick "I understand this is only an example and I will not copy it" before the example opens.</div>
        </div>
        <div class="row g-2">
            <div class="col-md-6"><label class="form-label small fw-semibold">Example link</label><input name="example_link" class="form-control" value="<?php echo asg_e($v('example_link')); ?>" placeholder="https://github.com/you/example"></div>
            <div class="col-md-6"><label class="form-label small fw-semibold">Example file <span class="text-muted fw-normal">(PDF, ZIP, DOCX or PPTX, up to 10 MB)</span></label>
                <input type="file" name="example_file" class="form-control" accept=".pdf,.zip,.docx,.pptx">
                <?php if ($cap && $cap['example_file']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_example_file" value="1" id="rmEx"><label class="form-check-label small" for="rmEx">Remove the saved file (<?php echo asg_e($cap['example_file_name']); ?>)</label></div><?php endif; ?></div>
        </div>
        <div class="small text-muted mt-2">If a student sends a link or file that is the same as the example, you will see a warning on the marking page.</div>
    </div>

    <!-- 3 What students send -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">3. What students send</h6>
        <p class="small text-muted">Switch on what students must send. Every link goes through the link checker. Students tick "This is my own work".</p>
        <?php foreach (CAP_DELIVERABLES as $kind => [$label, $type]):
            $on = $posted ? !empty($_POST['del'][$kind]) : (!$cap ? in_array($kind, ['project_link', 'report', 'video'], true) : isset($curDels[$kind]));
            $req = $posted ? !empty($_POST['req'][$kind]) : (!$cap ? in_array($kind, ['project_link', 'report', 'video'], true) : (!empty($curDels[$kind]['is_required']))); ?>
            <div class="d-flex flex-wrap align-items-center gap-3 border-bottom py-2">
                <div class="form-check form-switch flex-grow-1"><input class="form-check-input" type="checkbox" name="del[<?php echo $kind; ?>]" value="1" id="del_<?php echo $kind; ?>" <?php echo $on ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="del_<?php echo $kind; ?>"><?php echo asg_e($label); ?></label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="req[<?php echo $kind; ?>]" value="1" id="req_<?php echo $kind; ?>" <?php echo $req ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="req_<?php echo $kind; ?>">Required</label></div>
            </div>
        <?php endforeach; ?>
        <div class="mt-3"><div class="small fw-semibold mb-1">Your own items <span class="text-muted fw-normal">(for example a database file or a design link)</span></div>
            <div id="customRows">
                <?php foreach ($customRows as $i => $cr): ?>
                    <div class="d-flex gap-2 mb-2 custom-row"><input name="custom_label[]" class="form-control form-control-sm" placeholder="Name of the item" value="<?php echo asg_e($cr['label']); ?>">
                        <select name="custom_type[]" class="form-select form-select-sm" style="max-width:110px"><option value="link" <?php echo $cr['item_type'] === 'link' ? 'selected' : ''; ?>>Link</option><option value="file" <?php echo $cr['item_type'] === 'file' ? 'selected' : ''; ?>>File</option></select>
                        <label class="small d-flex align-items-center gap-1"><input type="checkbox" name="custom_req[<?php echo $i; ?>]" value="1" <?php echo !empty($cr['is_required']) ? 'checked' : ''; ?>> Required</label>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.custom-row').remove()">×</button></div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-outline-dark btn-sm" id="addCustom"><i class="fas fa-plus me-1"></i>Add an item</button></div>
    </div>

    <!-- 5 Who does it -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">5. Who does it</h6>
        <div class="d-flex flex-wrap gap-3 mb-2">
            <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="individual" id="m1" <?php echo $mode !== 'team' ? 'checked' : ''; ?> <?php echo $locked ? 'disabled' : ''; ?>><label class="form-check-label" for="m1">Individual</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="team" id="m2" <?php echo $mode === 'team' ? 'checked' : ''; ?> <?php echo $locked ? 'disabled' : ''; ?>><label class="form-check-label" for="m2">Team <span class="small text-muted">(up to 3 students, one shared submission, the same mark for each member)</span></label></div>
            <?php if ($locked): ?><input type="hidden" name="mode" value="<?php echo asg_e($mode); ?>"><?php endif; ?>
        </div>
        <input type="hidden" name="team_size" value="3">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-dark btn-sm" id="selAll">Select all in this course</button>
            <button type="button" class="btn btn-outline-dark btn-sm" id="selIntake">Select the whole intake</button>
            <button type="button" class="btn btn-outline-dark btn-sm" id="selNone">Clear</button>
        </div>
        <?php if (!$myStudents): ?><div class="alert alert-warning small mb-0">You have no students yet. Students are assigned to you by the office.</div><?php endif; ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="studTable">
            <thead><tr><th style="width:36px"></th><th>Student</th><th class="team-col">Team no.</th><th>Own due date <span class="small text-muted fw-normal">(optional)</span></th></tr></thead>
            <tbody>
            <?php foreach ($myStudents as $uid => $s): ?>
                <tr data-course="<?php echo (int) $s['course_id']; ?>" data-intake="<?php echo (int) ($intakeOf[$uid] ?? 0); ?>">
                    <td><input type="checkbox" class="form-check-input stu" name="sel[<?php echo $uid; ?>]" value="1" <?php echo !empty($chosen[$uid]) ? 'checked' : ''; ?>></td>
                    <td><?php echo asg_e($s['full_name']); ?><div class="small text-muted"><?php echo asg_e($s['registration_no']); ?> · <?php echo asg_e($s['course_title']); ?></div></td>
                    <td class="team-col"><input type="number" min="1" max="20" class="form-control form-control-sm" style="width:80px" name="team[<?php echo $uid; ?>]" value="<?php echo !empty($teamOf[$uid]) ? (int) $teamOf[$uid] : ''; ?>"></td>
                    <td><input type="datetime-local" class="form-control form-control-sm" name="own_due[<?php echo $uid; ?>]" value="<?php echo asg_e($ownDue[$uid] ?? ''); ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
    </div>

    <!-- 6 Milestones -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">6. Milestones and time keeping</h6>
        <p class="small text-muted">Each milestone has a due date and time keeping marks. A late milestone loses its marks. The marks of all milestones must add up to the <strong>Time keeping</strong> part below.
            <em>Work</em>: the student sends a short text, link or file. <em>Final</em>: filled in when they send the capstone. <em>Presentation</em>: uses the booked slot.</p>
        <div class="small text-muted d-none d-md-grid cap-ms fw-semibold"><div>Title</div><div>Type</div><div>Due (EAT)</div><div>Marks</div><div></div></div>
        <div id="msRows">
        <?php foreach ($msRows as $m): ?>
            <div class="cap-ms ms-row"><input type="hidden" name="ms_id[]" value="<?php echo (int) $m['id']; ?>">
                <input name="ms_title[]" class="form-control form-control-sm" placeholder="Title" value="<?php echo asg_e($m['title']); ?>">
                <select name="ms_kind[]" class="form-select form-select-sm"><?php foreach (['work' => 'Work', 'final' => 'Final project', 'presentation' => 'Presentation'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $m['kind'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select>
                <input type="datetime-local" name="ms_due[]" class="form-control form-control-sm" value="<?php echo asg_e($m['due']); ?>">
                <input type="number" step="0.5" min="0" name="ms_marks[]" class="form-control form-control-sm ms-marks" value="<?php echo asg_e(cap_pretty_marks($m['marks'])); ?>">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.ms-row').remove();calc()">×</button></div>
        <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-dark btn-sm" id="addMs"><i class="fas fa-plus me-1"></i>Add a milestone</button>
        <div class="small mt-2" id="msSum"></div>
    </div>
</div>

<div class="col-lg-4">
    <!-- 4 When students see it -->
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold">4. When students see it</h6>
        <?php if ($cap && in_array($cap['status'], ['open', 'closed'], true)): ?>
            <div class="small">It is <strong><?php echo $cap['status'] === 'open' ? 'open' : 'closed'; ?></strong>. Use <em>Open now</em> or <em>Close</em> on the list to change that.</div>
        <?php else: ?>
        <div class="form-check"><input class="form-check-input" type="radio" name="visibility" value="hidden" id="v1" <?php echo $vis === 'hidden' ? 'checked' : ''; ?>><label class="form-check-label" for="v1"><strong>Hidden (draft)</strong><div class="small text-muted">Only you can see it.</div></label></div>
        <div class="form-check mt-2"><input class="form-check-input" type="radio" name="visibility" value="now" id="v2" <?php echo $vis === 'now' ? 'checked' : ''; ?>><label class="form-check-label" for="v2"><strong>Open now</strong><div class="small text-muted">Students see it and are told.</div></label></div>
        <div class="form-check mt-2"><input class="form-check-input" type="radio" name="visibility" value="date" id="v3" <?php echo $vis === 'date' ? 'checked' : ''; ?>><label class="form-check-label" for="v3"><strong>Opens on a date and time</strong><div class="small text-muted">Until then students see only "Your capstone opens on …".</div></label></div>
        <input type="datetime-local" name="opens_local" class="form-control form-control-sm mt-2" value="<?php echo asg_e($opensLocal); ?>">
        <div class="small text-muted mt-2">You can still open it early or close it by hand at any time.</div>
        <?php endif; ?>
        <hr><label class="form-label small fw-semibold">Final due date and time (EAT)</label>
        <input type="datetime-local" name="due_local" class="form-control form-control-sm" value="<?php echo asg_e($dueLocal); ?>">
    </div>

    <!-- 7 Marks -->
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold">7. Marks out of 100</h6>
        <?php foreach (CAP_PARTS as $k => $label): ?>
            <div class="d-flex justify-content-between align-items-center mb-2"><label class="small fw-semibold" for="w_<?php echo $k; ?>"><?php echo $label; ?></label>
                <input type="number" min="0" max="100" class="form-control form-control-sm part" style="width:90px" id="w_<?php echo $k; ?>" name="w_<?php echo $k; ?>" value="<?php echo (int) $v('w_' . $k, ['project' => 50, 'docs' => 20, 'present' => 20, 'time' => 10][$k]); ?>" <?php echo $hasMarks ? 'readonly' : ''; ?>></div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between align-items-center border-top pt-2"><span class="fw-semibold">Total</span><span class="cap-total" id="total">100</span></div>
        <div class="small mt-1" id="totalMsg"></div>
        <div class="small text-muted mt-2">Suggested: Project 50, Documentation 20, Presentation 20, Time keeping 10. The total must be exactly 100.</div>
        <hr><label class="form-label small fw-semibold">Weight in the course average</label>
        <input type="number" step="0.5" min="0.5" max="20" name="weight" class="form-control form-control-sm" value="<?php echo asg_e(cap_pretty_marks($v('weight', 5))); ?>">
        <div class="small text-muted mt-1">An assignment counts 1. The capstone counts more (5 is suggested).</div>
    </div>

    <!-- 8 fixed rules -->
    <div class="cap-rules mb-3">
        <div class="fw-bold mb-1">8. Returns and retakes (fixed)</div>
        You can return it <strong>2 times</strong>. A retake is allowed up to <strong>5 times</strong> and the student <strong>pays again</strong>. Pass mark <strong>50</strong>.
        <div class="cap-bands mt-2"><span>Distinction 70+</span><span>Credit 60–69</span><span>Pass 50–59</span><span class="f">Fail &lt;50</span></div>
    </div>

    <div class="cap-sticky d-grid gap-2">
        <button class="btn btn-brand" name="action" value="schedule"><i class="fas fa-calendar-check me-1"></i>Save and schedule</button>
        <button class="btn btn-outline-dark" name="action" value="save_draft">Save draft</button>
    </div>
</div>
</div>
</form>

<script>
(function () {
    var form = document.getElementById('capForm');
    function calc() {
        var p = 0, t = 0;
        form.querySelectorAll('.part').forEach(function (i) { p += parseFloat(i.value || 0); });
        document.getElementById('total').textContent = p;
        var msg = document.getElementById('totalMsg');
        msg.innerHTML = p === 100 ? '<span class="text-success">Total is 100.</span>' : '<span class="text-danger fw-semibold">The four parts must add up to 100 (now ' + p + ').</span>';
        form.querySelectorAll('.ms-marks').forEach(function (i) { t += parseFloat(i.value || 0); });
        var tk = parseFloat(document.getElementById('w_time').value || 0);
        document.getElementById('msSum').innerHTML = Math.abs(t - tk) < 0.01 ? '<span class="text-success">Milestone marks (' + t + ') match Time keeping (' + tk + ').</span>'
            : '<span class="text-danger fw-semibold">Milestone marks add up to ' + t + ' but Time keeping is ' + tk + '. They must be equal.</span>';
    }
    window.calc = calc;
    form.addEventListener('input', calc); calc();
    document.getElementById('addMs').onclick = function () {
        var d = document.createElement('div'); d.className = 'cap-ms ms-row';
        d.innerHTML = '<input type="hidden" name="ms_id[]" value="0"><input name="ms_title[]" class="form-control form-control-sm" placeholder="Title">' +
            '<select name="ms_kind[]" class="form-select form-select-sm"><option value="work">Work</option><option value="final">Final project</option><option value="presentation">Presentation</option></select>' +
            '<input type="datetime-local" name="ms_due[]" class="form-control form-control-sm"><input type="number" step="0.5" min="0" name="ms_marks[]" class="form-control form-control-sm ms-marks" value="0">' +
            '<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.ms-row\').remove();calc()">×</button>';
        document.getElementById('msRows').appendChild(d); calc();
    };
    var n = <?php echo count($customRows); ?>;
    document.getElementById('addCustom').onclick = function () {
        var d = document.createElement('div'); d.className = 'd-flex gap-2 mb-2 custom-row';
        d.innerHTML = '<input name="custom_label[]" class="form-control form-control-sm" placeholder="Name of the item"><select name="custom_type[]" class="form-select form-select-sm" style="max-width:110px"><option value="link">Link</option><option value="file">File</option></select>' +
            '<label class="small d-flex align-items-center gap-1"><input type="checkbox" name="custom_req[' + (n++) + ']" value="1"> Required</label><button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.custom-row\').remove()">×</button>';
        document.getElementById('customRows').appendChild(d);
    };
    // Students: show only the chosen course; "select the whole intake" uses the intake picked above.
    var course = document.getElementById('courseSel'), rows = document.querySelectorAll('#studTable tbody tr');
    function filter() { rows.forEach(function (r) { var ok = !course.value || r.dataset.course === course.value; r.style.display = ok ? '' : 'none'; if (!ok) r.querySelector('.stu').checked = false; }); }
    course.addEventListener('change', filter); filter();
    document.getElementById('selAll').onclick = function () { rows.forEach(function (r) { if (r.style.display !== 'none') r.querySelector('.stu').checked = true; }); };
    document.getElementById('selNone').onclick = function () { rows.forEach(function (r) { r.querySelector('.stu').checked = false; }); };
    document.getElementById('selIntake').onclick = function () {
        var iv = document.getElementById('intakeSel').value;
        if (!iv) { alert('Choose the intake first (in section 1).'); return; }
        rows.forEach(function (r) { if (r.style.display !== 'none') r.querySelector('.stu').checked = (r.dataset.intake === iv); });
    };
    function team() { var on = document.getElementById('m2').checked; document.querySelectorAll('.team-col').forEach(function (c) { c.style.display = on ? '' : 'none'; }); }
    document.getElementById('m1').addEventListener('change', team); document.getElementById('m2').addEventListener('change', team); team();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
