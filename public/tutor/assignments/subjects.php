<?php
// Tutor: the subjects of a course (add, rename, reorder, weight, monthly projects, inactive, remove). A subject mark is never typed here:
// it is worked out from the assignment marks (SubjectService::subjectMarks). Also answers the "New subject" box on the assignment form (JSON).
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/SubjectService.php';

$courses = $am->tutorCourses($tutorId);     // the tutor's own courses only
$courseId = (int) ($_GET['course'] ?? $_POST['course_id'] ?? (count($courses) === 1 ? array_key_first($courses) : 0));
$self = 'subjects.php' . ($courseId ? '?course=' . $courseId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = ($_POST['action'] ?? '') === 'create_ajax';
    $reply = function (array $r) use ($ajax, $self): void {
        if ($ajax) { header('Content-Type: application/json'); echo json_encode($r); exit; }
        asg_back($self, !empty($r['ok']) ? ($r['msg'] ?? 'Saved.') : null, empty($r['ok']) ? ($r['error'] ?? 'Could not save.') : null);
    };
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $reply(['ok' => false, 'error' => 'Security check failed. Please refresh and try again.']);
    if (!isset($courses[$courseId])) $reply(['ok' => false, 'error' => 'Choose one of your courses.']);
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'create_ajax':
        case 'create':
            $r = subj_create($pdo, $tutorId, $me, $courseId, $_POST);
            if ($r['ok']) {
                $s = subj_get_own($pdo, $r['id'], $tutorId);
                $r['label'] = $s['name'] . ($s['code'] ? ' (' . $s['code'] . ')' : '');
                $r['msg'] = 'Subject added.';
            }
            $reply($r);
        case 'update':   $r = subj_update($pdo, $tutorId, $me, $id, $_POST);           $reply($r + ['msg' => 'Subject saved.']);
        case 'up':       $r = subj_move($pdo, $tutorId, $me, $id, -1);                  $reply($r + ['msg' => 'Order saved.']);
        case 'down':     $r = subj_move($pdo, $tutorId, $me, $id, 1);                   $reply($r + ['msg' => 'Order saved.']);
        case 'inactive': $r = subj_set_active($pdo, $tutorId, $me, $id, false);         $reply($r + ['msg' => 'Subject set inactive. Its past work and marks are kept.']);
        case 'active':   $r = subj_set_active($pdo, $tutorId, $me, $id, true);          $reply($r + ['msg' => 'Subject is active again.']);
        case 'remove':   $r = subj_remove($pdo, $tutorId, $me, $id);                    $reply($r + ['msg' => 'Subject removed.']);
    }
    $reply(['ok' => false, 'error' => 'Nothing to do.']);
}

$subjects = $courseId && isset($courses[$courseId]) ? subj_list($pdo, $courseId, $tutorId) : [];
$weights = $courseId && isset($courses[$courseId]) ? subj_effective_weights($pdo, $courseId, $tutorId) : [];
$defaultWeight = $courseId && isset($courses[$courseId]) ? subj_default_weight($pdo, $courseId, $tutorId) : 0;
$page_title = 'Subjects';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <h5 class="mb-1">Subjects</h5>
    <p class="small text-muted mb-3">A subject is a part of what you teach, for example Python Programming. Set them up once, then pick one on every assignment and monthly project.
        The subject mark is worked out by itself from the marks under it. Nobody types it.</p>
    <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
        <div><label class="form-label small fw-semibold mb-0" for="courseSel">Course</label>
            <select name="course" id="courseSel" class="form-select" onchange="this.form.submit()"><option value="">Choose a course…</option>
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === $courseId ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?></select></div>
        <noscript><button class="btn btn-outline-dark">Show</button></noscript>
    </form>
</div>

<?php if ($courseId && isset($courses[$courseId])): ?>
<div class="card-tutor p-3 p-md-4 mb-3">
    <h6 class="fw-bold">Your subjects in <?php echo asg_e($courses[$courseId]); ?></h6>
    <?php if (!$subjects): ?><p class="small text-muted">No subjects yet. Until you add one, assignments in this course do not need a subject.</p><?php endif; ?>
    <?php foreach ($subjects as $i => $s): $used = subj_usage_count($pdo, (int) $s['id']); ?>
    <form method="POST" class="border rounded p-2 mb-2<?php echo (int) $s['is_active'] ? '' : ' bg-light'; ?>"><?php echo csrf_field(); ?>
        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>"><input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control form-control-sm" maxlength="120" required value="<?php echo asg_e($s['name']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control form-control-sm" maxlength="20" placeholder="DS-02" value="<?php echo asg_e((string) $s['code']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Weight <?php echo $s['weight'] === null ? '(default ' . asg_e((string) ($weights[(int) $s['id']] ?? $defaultWeight)) . ')' : ''; ?></label>
                <input name="weight" type="number" step="0.5" min="0.5" max="100" class="form-control form-control-sm" placeholder="default" value="<?php echo $s['weight'] === null ? '' : asg_e((string) (float) $s['weight']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" class="form-control form-control-sm" value="<?php echo (int) $s['monthly_expected']; ?>"></div>
            <div class="col-6 col-md-2 d-flex gap-1 flex-wrap justify-content-md-end">
                <button name="action" value="update" class="btn btn-brand btn-sm">Save</button>
                <button name="action" value="up" class="btn btn-light btn-sm" title="Move up" <?php echo $i === 0 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up"></i></button>
                <button name="action" value="down" class="btn btn-light btn-sm" title="Move down" <?php echo $i === count($subjects) - 1 ? 'disabled' : ''; ?>><i class="fas fa-arrow-down"></i></button>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">
            <span class="text-muted"><?php echo $used; ?> assignment<?php echo $used === 1 ? '' : 's'; ?> use this subject</span>
            <?php if (!(int) $s['is_active']): ?><span class="badge bg-secondary">INACTIVE</span><button name="action" value="active" class="btn btn-outline-secondary btn-sm">Make active</button>
            <?php else: ?><button name="action" value="inactive" class="btn btn-outline-secondary btn-sm">Set inactive</button><?php endif; ?>
            <?php if ($used === 0): ?><button name="action" value="remove" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this subject?')">Remove</button>
            <?php else: ?><span class="text-muted">It is in use, so it can be renamed or set inactive but not removed.</span><?php endif; ?>
        </div>
    </form>
    <?php endforeach; ?>
</div>

<form method="POST" class="card-tutor p-3 p-md-4"><?php echo csrf_field(); ?><input type="hidden" name="course_id" value="<?php echo $courseId; ?>"><input type="hidden" name="action" value="create">
    <h6 class="fw-bold">Add a subject</h6>
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control" maxlength="120" required placeholder="e.g. Python Programming"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control" maxlength="20" placeholder="DS-02"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Weight</label><input name="weight" type="number" step="0.5" min="0.5" max="100" class="form-control" placeholder="default <?php echo asg_e((string) $defaultWeight); ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" value="1" class="form-control"></div>
        <div class="col-6 col-md-2"><button class="btn btn-brand w-100">Add</button></div>
    </div>
    <div class="small text-muted mt-2">Weight is empty by default: subjects share equally what is left after the capstone. Monthly projects is how many monthly projects the subject has. Until that many are marked and released, its mark shows as In progress.</div>
</form>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
