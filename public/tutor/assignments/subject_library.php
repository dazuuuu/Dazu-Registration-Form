<?php
// Tutor: a reusable "subject library". Create a subject/unit once (e.g. "Networking Basics") and push
// it into any number of your own courses, instead of recreating the same subject by hand in each one.
// Pushing reuses subj_create() for every course, so numbering, logging and name-clash rules are
// identical to adding a subject the normal way in subjects.php.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/SubjectService.php';

$courses = $am->tutorCourses($tutorId);
$self = 'subject_library.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply = function (array $r) use ($self): void {
        asg_back($self, !empty($r['ok']) ? ($r['msg'] ?? 'Saved.') : null, empty($r['ok']) ? ($r['error'] ?? 'Could not save.') : null);
    };
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $reply(['ok' => false, 'error' => 'Security check failed. Please refresh and try again.']);
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'create':
            $r = subj_tpl_create($pdo, $tutorId, $_POST);
            $reply($r + ['msg' => 'Subject added to your library.']);
        case 'update':
            $r = subj_tpl_update($pdo, $tutorId, $id, $_POST);
            $reply($r + ['msg' => 'Saved.']);
        case 'remove':
            $r = subj_tpl_remove($pdo, $tutorId, $id);
            $reply($r + ['msg' => 'Removed from your library.']);
        case 'push':
            $tpl = subj_tpl_get($pdo, $id, $tutorId);
            if (!$tpl) $reply(['ok' => false, 'error' => 'Subject not found in your library.']);
            $courseIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['course_ids'] ?? [])), fn($c) => isset($courses[$c]))));
            if (!$courseIds) $reply(['ok' => false, 'error' => 'Choose at least one course to push this subject into.']);
            $r = subj_tpl_push($pdo, $tutorId, $me, $tpl, $courseIds);
            $msg = $r['pushed'] . ' course' . ($r['pushed'] === 1 ? '' : 's') . ' updated.';
            if ($r['skipped']) $msg .= ' ' . $r['skipped'] . ' skipped (already has a subject with that name).';
            $reply(['ok' => $r['pushed'] > 0, 'msg' => $msg, 'error' => $r['pushed'] > 0 ? null : 'Nothing was pushed. ' . ($r['errors'][0] ?? '')]);
    }
    $reply(['ok' => false, 'error' => 'Nothing to do.']);
}

$templates = subj_tpl_list($pdo, $tutorId);
$page_title = 'Subject Library';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <h5 class="mb-1">Subject Library</h5>
    <p class="small text-muted mb-0">Create a subject once here, then push it into as many of your courses as share it (e.g. Month 1 to Month 3 of the same program),
        instead of recreating it by hand in <a href="subjects.php">Subjects</a> every time. Pushing adds a normal subject to each course you pick; after that it behaves exactly
        like any subject added there, and changing it later (in Subjects) does not need to match the library again.</p>
</div>

<?php if (!$templates): ?>
<div class="card-tutor p-3 p-md-4 mb-3"><p class="small text-muted mb-0">Your library is empty. Add your first reusable subject below.</p></div>
<?php else: ?>
<?php foreach ($templates as $t): ?>
<div class="card-tutor p-3 p-md-4 mb-3">
    <form method="POST" class="mb-3"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control form-control-sm" maxlength="120" required value="<?php echo asg_e($t['name']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control form-control-sm" maxlength="20" placeholder="DS-02" value="<?php echo asg_e((string) $t['code']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Weight</label><input name="weight" type="number" step="0.5" min="0.5" max="100" class="form-control form-control-sm" placeholder="default" value="<?php echo $t['weight'] === null ? '' : asg_e((string) (float) $t['weight']); ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" class="form-control form-control-sm" value="<?php echo (int) $t['monthly_expected']; ?>"></div>
            <div class="col-6 col-md-2 d-flex gap-1 justify-content-md-end">
                <button name="action" value="update" class="btn btn-brand btn-sm">Save</button>
                <button name="action" value="remove" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this subject from your library? Courses it was already pushed into keep it.')">Remove</button>
            </div>
        </div>
    </form>
    <?php if ($courses): ?>
    <form method="POST" class="border-top pt-2">
        <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>"><input type="hidden" name="action" value="push">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <label class="small fw-semibold mb-0"><input type="checkbox" onclick="this.closest('form').querySelectorAll('.push-chk').forEach(c=>c.checked=this.checked)"> Select all my courses</label>
            <button class="btn btn-success btn-sm">Push to selected courses</button>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($courses as $cid => $ct): ?>
            <label class="d-flex align-items-center gap-1 border rounded px-2 py-1 small">
                <input type="checkbox" class="push-chk" name="course_ids[]" value="<?php echo (int) $cid; ?>"><?php echo asg_e($ct); ?>
            </label>
            <?php endforeach; ?>
        </div>
    </form>
    <?php else: ?>
    <p class="small text-muted mb-0 border-top pt-2">You have no courses yet, so there is nothing to push this into.</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<form method="POST" class="card-tutor p-3 p-md-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
    <h6 class="fw-bold">Add a subject to your library</h6>
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control" maxlength="120" required placeholder="e.g. Networking Basics"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control" maxlength="20" placeholder="DS-02"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Weight</label><input name="weight" type="number" step="0.5" min="0.5" max="100" class="form-control" placeholder="default"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" value="1" class="form-control"></div>
        <div class="col-6 col-md-2"><button class="btn btn-brand w-100">Add</button></div>
    </div>
    <div class="small text-muted mt-2">This only saves it to your library. It is not added to any course until you push it.</div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
