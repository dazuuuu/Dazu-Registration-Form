<?php
// Admin: the subjects of each course, with the month of the course each belongs to. They are saved with tutor_id 0, so every tutor of the
// course sees them in the Subject box of their assignments and monthly projects (and can move older assignments onto them).
// Only the subject SETUP is here: no mark is read or typed on this page (marks are always worked out from the assignment marks).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/TranscriptService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Course subjects';
$breadcrumbs = [['label' => 'Courses', 'url' => url('admin/courses/index.php')], ['label' => 'Subjects', 'active' => true]];

$ready = true;
try { $db->query('SELECT month_no FROM course_subjects LIMIT 0'); } catch (Throwable $x) { $ready = false; }
if (!$ready) {
    $content = '<div class="alert alert-warning">Run <a href="' . $e(url('admin/system/updates.php')) . '">System Updates</a> first (update 135: course subjects).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

$courses = $db->query("SELECT id, title FROM courses ORDER BY title")->fetchAll(PDO::FETCH_KEY_PAIR);
$courseId = (int) ($_GET['course'] ?? $_POST['course_id'] ?? 0);
if (!isset($courses[$courseId])) $courseId = 0;
$self = url('admin/courses/subjects.php') . ($courseId ? '?course=' . $courseId : '');
$me = (int) ($_SESSION['user_id'] ?? 0);

$log = function (?int $sid, string $event, array $details) use ($db, $courseId, $me): void {
    try {
        $db->prepare('INSERT INTO course_subject_events (subject_id, course_id, assignment_id, actor_user_id, event, details) VALUES (?, ?, NULL, ?, ?, ?)')
           ->execute([$sid, $courseId, $me, $event, json_encode($details + ['by' => 'admin'])]);
    } catch (Throwable $x) {
    }
};
$back = function (?string $ok, ?string $err = null) use ($self, $e): void {
    if ($ok) SessionModel::setFlash('success', $e($ok));
    if ($err) SessionModel::setFlash('error', $e($err));
    header('Location: ' . $self);
    exit;
};
$clean = function (array $in): array {
    $name = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')));
    $code = strtoupper(trim((string) ($in['code'] ?? '')));
    $month = (int) ($in['month_no'] ?? 0);
    $monthly = max(1, min(12, (int) ($in['monthly_expected'] ?? 1)));
    $err = null;
    if ($name === '' || mb_strlen($name) > 120) $err = 'Give the subject a name (up to 120 characters).';
    elseif ($code !== '' && !preg_match('/^[A-Z0-9._-]{1,20}$/', $code)) $err = 'The short code can use letters, numbers, dash and dot only (up to 20).';
    elseif ($month < 1 || $month > 36) $err = 'Choose the month of the course (1 to 36).';
    return [['name' => $name, 'code' => $code ?: null, 'month_no' => $month, 'monthly_expected' => $monthly], $err];
};
$own = function (int $id) use ($db, $courseId): ?array {
    $st = $db->prepare('SELECT * FROM course_subjects WHERE id = ? AND course_id = ? AND tutor_id = 0');
    $st->execute([$id, $courseId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
};
$usage = function (int $id) use ($db): int {
    $st = $db->prepare('SELECT COUNT(*) FROM tutor_assignments WHERE subject_id = ?');
    $st->execute([$id]);
    return (int) $st->fetchColumn();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $back(null, 'Security check failed. Please refresh and try again.');
    if (!$courseId) $back(null, 'Choose a course first.');
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'copy') {
        $fromCourseId = (int) ($_POST['from_course'] ?? 0);
        if (!$fromCourseId || !isset($courses[$fromCourseId]) || $fromCourseId === $courseId) $back(null, 'Choose a different course to copy from.');
        $ids = array_values(array_unique(array_map('intval', (array) ($_POST['subject_ids'] ?? []))));
        if (!$ids) $back(null, 'Choose at least one subject to copy.');
        $copied = 0; $skipped = 0;
        foreach ($ids as $sid) {
            $srcSt = $db->prepare('SELECT * FROM course_subjects WHERE id = ? AND course_id = ? AND tutor_id = 0');
            $srcSt->execute([$sid, $fromCourseId]);
            $src = $srcSt->fetch(PDO::FETCH_ASSOC);
            if (!$src) continue;
            $dup = $db->prepare('SELECT COUNT(*) FROM course_subjects WHERE course_id = ? AND tutor_id = 0 AND LOWER(name) = LOWER(?)');
            $dup->execute([$courseId, $src['name']]);
            if ($dup->fetchColumn()) { $skipped++; continue; }
            $db->prepare('INSERT INTO course_subjects (course_id, tutor_id, name, code, weight, monthly_expected, month_no, sort_order, is_active, updated_at) VALUES (?, 0, ?, ?, NULL, ?, ?, ?, 1, ?)')
               ->execute([$courseId, $src['name'], $src['code'], $src['monthly_expected'], $src['month_no'], $src['month_no'], gmdate('Y-m-d H:i:s')]);
            $newId = (int) $db->lastInsertId();
            tr_number_one_subject($db, $newId, $courseId);
            $log($newId, 'subject_copied', ['name' => $src['name'], 'from_course_id' => $fromCourseId]);
            $copied++;
        }
        $msg = $copied . ' subject' . ($copied === 1 ? '' : 's') . ' copied.' . ($skipped ? ' ' . $skipped . ' skipped (already exists in this course).' : '');
        $back($copied ? $msg : null, $copied ? null : 'Nothing was copied. ' . ($skipped ? $skipped . ' already existed in this course.' : 'No matching subjects found.'));
    }
    if ($action === 'create' || $action === 'update') {
        [$d, $err] = $clean($_POST);
        if ($err) $back(null, $err);
        $dup = $db->prepare('SELECT COUNT(*) FROM course_subjects WHERE course_id = ? AND tutor_id = 0 AND LOWER(name) = LOWER(?) AND id <> ?');
        $dup->execute([$courseId, $d['name'], $id]);
        if ($dup->fetchColumn()) $back(null, 'This course already has a subject with that name.');
        if ($action === 'create') {
            $db->prepare('INSERT INTO course_subjects (course_id, tutor_id, name, code, weight, monthly_expected, month_no, sort_order, is_active, updated_at) VALUES (?, 0, ?, ?, NULL, ?, ?, ?, 1, ?)')
               ->execute([$courseId, $d['name'], $d['code'], $d['monthly_expected'], $d['month_no'], $d['month_no'], gmdate('Y-m-d H:i:s')]);
            $newId = (int) $db->lastInsertId();
            tr_number_one_subject($db, $newId, $courseId);   // its transcript number (101, 102, ...)
            $log($newId, 'subject_created', $d);
            $back('Subject added. Tutors of this course can use it now.');
        }
        if (!$own($id)) $back(null, 'Subject not found.');
        $db->prepare('UPDATE course_subjects SET name = ?, code = ?, monthly_expected = ?, month_no = ?, sort_order = ?, updated_at = ? WHERE id = ? AND tutor_id = 0')
           ->execute([$d['name'], $d['code'], $d['monthly_expected'], $d['month_no'], $d['month_no'], gmdate('Y-m-d H:i:s'), $id]);
        $log($id, 'subject_changed', $d);
        $back('Subject saved.');
    }
    $s = $own($id);
    if (!$s) $back(null, 'Subject not found.');
    if ($action === 'active' || $action === 'inactive') {
        $db->prepare('UPDATE course_subjects SET is_active = ?, updated_at = ? WHERE id = ? AND tutor_id = 0')->execute([$action === 'active' ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);
        $log($id, $action === 'active' ? 'subject_activated' : 'subject_inactive', ['name' => $s['name']]);
        $back($action === 'active' ? 'Subject is active again.' : 'Subject set inactive. Its past work is kept.');
    }
    if ($action === 'remove') {
        $n = $usage($id);
        if ($n) $back(null, 'This subject is used by ' . $n . ' assignment' . ($n === 1 ? '' : 's') . ', so it cannot be removed. Rename it or set it inactive.');
        $db->prepare('DELETE FROM course_subjects WHERE id = ? AND tutor_id = 0')->execute([$id]);
        $log($id, 'subject_removed', ['name' => $s['name']]);
        $back('Subject removed.');
    }
    $back(null, 'Nothing to do.');
}

$subjects = [];
if ($courseId) {
    $st = $db->prepare('SELECT * FROM course_subjects WHERE course_id = ? AND tutor_id = 0 ORDER BY COALESCE(month_no, 99), sort_order, id');
    $st->execute([$courseId]);
    $subjects = $st->fetchAll(PDO::FETCH_ASSOC);
}
$counts = $db->query('SELECT course_id, COUNT(*) FROM course_subjects WHERE tutor_id = 0 GROUP BY course_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$byMonth = [];
foreach ($subjects as $s) $byMonth[(int) $s['month_no']][] = $s;
$nextMonth = $subjects ? max(array_map(fn($s) => (int) $s['month_no'], $subjects)) : 1;

$fromId = (int) ($_GET['from'] ?? 0);
if (!isset($courses[$fromId]) || $fromId === $courseId) $fromId = 0;
$fromSubjects = [];
if ($courseId && $fromId) {
    $st = $db->prepare('SELECT * FROM course_subjects WHERE course_id = ? AND tutor_id = 0 ORDER BY COALESCE(month_no, 99), sort_order, id');
    $st->execute([$fromId]);
    $fromSubjects = $st->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>
<div class="card mb-3"><div class="card-body">
    <h5 class="mb-1">Course subjects</h5>
    <p class="text-muted small mb-3">Add the subjects of each course and the month they are taught in. Every tutor of the course picks from these when they set an
        assignment or a monthly project, and the subject marks build the student's transcript. Tutors can also move their older assignments onto these subjects.</p>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
        <div><label class="form-label small fw-semibold mb-0" for="courseSel">Course</label>
            <select name="course" id="courseSel" class="form-select" onchange="this.form.submit()"><option value="">Choose a course…</option>
                <?php foreach ($courses as $cid => $ct): ?><option value="<?= (int) $cid ?>" <?= (int) $cid === $courseId ? 'selected' : '' ?>><?= $e($ct) ?><?= !empty($counts[$cid]) ? ' (' . (int) $counts[$cid] . ')' : '' ?></option><?php endforeach; ?></select></div>
        <noscript><button class="btn btn-outline-dark">Show</button></noscript>
    </form>
</div></div>

<?php if ($courseId): ?>
<div class="card mb-3"><div class="card-body">
    <h6 class="fw-bold mb-3">Subjects in <?= $e($courses[$courseId]) ?></h6>
    <?php if (!$subjects): ?><p class="text-muted small">No subjects yet. Add the first one below.</p><?php endif; ?>
    <?php foreach ($byMonth as $m => $list): ?>
        <div class="small fw-bold text-success text-uppercase mt-3 mb-1">Month <?= (int) $m ?></div>
        <?php foreach ($list as $s): $used = $usage((int) $s['id']); ?>
        <form method="POST" class="border rounded p-2 mb-2<?= (int) $s['is_active'] ? '' : ' bg-light' ?>"><?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control form-control-sm" maxlength="120" required value="<?= $e($s['name']) ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control form-control-sm" maxlength="20" value="<?= $e((string) $s['code']) ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Month of course</label><input name="month_no" type="number" min="1" max="36" class="form-control form-control-sm" required value="<?= (int) $s['month_no'] ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" class="form-control form-control-sm" value="<?= (int) $s['monthly_expected'] ?>"></div>
                <div class="col-6 col-md-2"><button name="action" value="update" class="btn btn-success btn-sm w-100">Save</button></div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">
                <span class="text-muted"><?= $used ?> assignment<?= $used === 1 ? '' : 's' ?> use this subject<?= $s['subject_no'] ?? null ? ' · transcript no. ' . (int) $s['subject_no'] : '' ?></span>
                <?php if (!(int) $s['is_active']): ?><span class="badge bg-secondary">INACTIVE</span><button name="action" value="active" class="btn btn-outline-secondary btn-sm">Make active</button>
                <?php else: ?><button name="action" value="inactive" class="btn btn-outline-secondary btn-sm">Set inactive</button><?php endif; ?>
                <?php if ($used === 0): ?><button name="action" value="remove" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this subject?')">Remove</button><?php endif; ?>
            </div>
        </form>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h6 class="fw-bold mb-1">Copy subjects from another course</h6>
    <p class="text-muted small mb-3">Some courses share the same subjects (e.g. Month 1 to Month 3). Pick the course to copy from, choose which subjects to bring in, and they are added here with the same month, short code and monthly-project count. A subject already in this course (same name) is skipped.</p>
    <form method="GET" class="d-flex gap-2 flex-wrap align-items-end mb-3">
        <input type="hidden" name="course" value="<?= $courseId ?>">
        <div><label class="form-label small fw-semibold mb-0" for="fromSel">Copy from</label>
            <select name="from" id="fromSel" class="form-select" onchange="this.form.submit()">
                <option value="">Choose a course…</option>
                <?php foreach ($courses as $cid => $ct): if ((int) $cid === $courseId) continue; ?>
                <option value="<?= (int) $cid ?>" <?= (int) $cid === $fromId ? 'selected' : '' ?>><?= $e($ct) ?><?= !empty($counts[$cid]) ? ' (' . (int) $counts[$cid] . ')' : '' ?></option>
                <?php endforeach; ?>
            </select></div>
        <noscript><button class="btn btn-outline-dark">Show</button></noscript>
    </form>
    <?php if ($fromId && !$fromSubjects): ?><p class="text-muted small mb-0"><?= $e($courses[$fromId]) ?> has no subjects yet.</p><?php endif; ?>
    <?php if ($fromSubjects): ?>
    <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="action" value="copy"><input type="hidden" name="from_course" value="<?= $fromId ?>">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="small fw-semibold mb-0"><input type="checkbox" onclick="this.closest('form').querySelectorAll('.copy-chk').forEach(c=>c.checked=this.checked)"> Select all</label>
            <button class="btn btn-success btn-sm">Copy selected subjects</button>
        </div>
        <?php foreach ($fromSubjects as $s): ?>
        <label class="d-flex align-items-center gap-2 border rounded p-2 mb-1 small">
            <input type="checkbox" class="copy-chk" name="subject_ids[]" value="<?= (int) $s['id'] ?>">
            <span class="fw-semibold"><?= $e($s['name']) ?></span>
            <span class="text-muted">Month <?= (int) $s['month_no'] ?><?= $s['code'] ? ' · ' . $e($s['code']) : '' ?></span>
        </label>
        <?php endforeach; ?>
    </form>
    <?php endif; ?>
</div></div>

<form method="POST" class="card"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="course_id" value="<?= $courseId ?>"><input type="hidden" name="action" value="create">
    <h6 class="fw-bold">Add a subject</h6>
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label small mb-0">Name</label><input name="name" class="form-control" maxlength="120" required placeholder="e.g. Python Programming"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Short code</label><input name="code" class="form-control" maxlength="20" placeholder="PY-01"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Month of course</label><input name="month_no" type="number" min="1" max="36" class="form-control" required value="<?= (int) $nextMonth ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Monthly projects</label><input name="monthly_expected" type="number" min="1" max="12" value="1" class="form-control"></div>
        <div class="col-6 col-md-2"><button class="btn btn-success w-100">Add</button></div>
    </div>
    <div class="small text-muted mt-2">Month of course: the month students study it (Month 1 = their first month). Monthly projects: how many monthly projects the subject has before its mark is final.</div>
</div></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
