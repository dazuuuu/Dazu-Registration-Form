<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\PayrollModel;
use Models\PayrollCalculator;
use Controllers\TutorController;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$id = (int)($_GET['id'] ?? 0);
$tutor = $tutorModel->getById($id);
if (!$tutor) { SessionModel::setFlash('error','Tutor not found.'); header('Location: index.php'); exit(); }

$payroll = new PayrollModel($pdo);
$installed = $payroll->isInstalled();
$today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
$self = 'view.php?id=' . $id;

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $error = 'Security check failed.';
    else {
        $action = $_POST['action'] ?? '';
        $flash = function (bool $ok, string $msg) use ($self) {
            SessionModel::setFlash($ok ? 'success' : 'error', $msg);
            header('Location: ' . $self . '#students'); exit();
        };
        if ($action === 'delete_tutor') {
            $r = $tutorModel->delete($id);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Tutor deleted.' : $r['error']);
            header('Location: ' . ($r['ok'] ? 'index.php' : 'view.php?id=' . $id)); exit();
        }
        if ($action === 'permissions') {
            require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
            $r = tutor_permissions_set($pdo, $id, ($_POST['access'] ?? '') === 'admin', (int) ($_SESSION['user_id'] ?? 0));
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', htmlspecialchars($r['message']));
            header('Location: ' . $self . '#permissions'); exit();
        }
        if ($action === 'send_credentials') {
            $r = (new TutorController())->sendCredentials($id);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Credentials emailed.' : ($r['error'] ?? 'Failed'));
            header('Location: view.php?id='.$id); exit();
        }
        $studentActions = ['assign_students', 'unassign', 'move', 'start_pause', 'end_pause', 'reassign_former'];
        if (in_array($action, $studentActions, true)) {
            if (!$installed) $flash(false, 'Apply the pending system update (migration 048) first.');
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($action === 'assign_students') {
                $n = $payroll->assignStudents($id, (array)($_POST['student_ids'] ?? []));
                $flash($n > 0, $n > 0 ? $n . ' student(s) assigned.' : 'Select at least one student to assign.');
            } elseif ($action === 'unassign') {
                $ok = $payroll->unassignStudent($id, $uid);
                $flash($ok, $ok ? 'Student unassigned. What the tutor already earned for them is kept, and you can reassign them below.' : 'That student is not assigned to this tutor.');
            } elseif ($action === 'move') {
                $r = $payroll->moveStudent($id, $uid, (int)($_POST['to_tutor'] ?? 0));
                $flash($r['ok'], $r['ok'] ? 'Student moved to the other tutor.' : $r['error']);
            } elseif ($action === 'start_pause') {
                $kind = (string)($_POST['kind'] ?? '');
                $r = $payroll->startPause($id, $uid > 0 ? $uid : null, $kind, (string)($_POST['start'] ?? ''),
                    (string)($_POST['end'] ?? ''), (string)($_POST['note'] ?? ''));
                $flash($r['ok'], $r['ok'] ? 'Salary paused (' . strtolower(PayrollModel::PAUSE_KINDS[$kind]) . '). It stays paused until you resume it.' : $r['error']);
            } elseif ($action === 'end_pause') {
                $ok = $payroll->endPause((int)($_POST['pause_id'] ?? 0), $id);
                $flash($ok, $ok ? 'Resumed. Salary runs again from today.' : 'That pause is already over.');
            } elseif ($action === 'reassign_former') {
                $r = $payroll->reassignFormer($id, (int)($_POST['history_id'] ?? 0));
                $flash($r['ok'], $r['ok'] ? 'Student reassigned to this tutor.' : $r['error']);
            }
        }
        $photoPath = $tutor['photo_path'];
        if (!empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/tutors', url('uploads/tutors'));
            if ($up['ok']) $photoPath = $up['path']; else $error = $up['error'];
        }
        if (!$error) {
            $r = $tutorModel->update($id, [
                'full_name'  => $_POST['full_name'] ?? '',
                'phone'      => $_POST['phone'] ?? '',
                'bio'        => $_POST['bio'] ?? '',
                'photo_path' => $photoPath,
                'is_active'  => isset($_POST['is_active']),
            ]);
            if ($r['ok']) { SessionModel::setFlash('success','Tutor updated.'); header('Location: view.php?id='.$id); exit(); }
            $error = $r['error'];
        }
    }
    $tutor = $tutorModel->getById($id);
}

$fullName = $tutorModel->fullName($tutor);
$ledger = $installed ? $payroll->getLedger($id) : null;
$assignedIds = $tutorModel->getAssignedStudentIds($id);
$available = array_values(array_filter($tutorModel->getRegisteredStudents(), fn($s) => !in_array((int)$s['user_id'], $assignedIds, true)));
$otherTutors = $installed ? array_values(array_filter($payroll->listTutorNames(), fn($t) => (int)$t['id'] !== $id)) : [];

$pauseBadge = function (?array $pause, ?array $s = null): string {
    if (!$pause) {
        if ($s && $s['months'] > 0 && $s['months_remaining'] <= 0 && $s['months_elapsed'] > 0) return '<span class="badge bg-dark">Course finished</span>';
        return '<span class="badge bg-success">Active</span>';
    }
    $cls = ['pause' => 'bg-warning text-dark', 'break' => 'bg-info text-dark', 'holiday' => 'bg-secondary'][$pause['kind']];
    $out = '<span class="badge ' . $cls . '">' . htmlspecialchars(PayrollModel::PAUSE_KINDS[$pause['kind']]) . '</span>';
    $out .= '<div class="text-muted small">' . ($pause['state'] === 'scheduled' ? 'from ' : 'since ') . date('d M', strtotime($pause['start_date']));
    $out .= $pause['end_date'] ? ' · back ' . date('d M', strtotime($pause['end_date'])) : '';
    $out .= '</div>';
    if (!empty($pause['note'])) $out .= '<div class="text-muted small fst-italic">' . htmlspecialchars($pause['note']) . '</div>';
    return $out;
};

$page_title = $fullName;
$breadcrumbs = [['label'=>'Tutors','url'=>'index.php'],['label'=>$fullName,'active'=>true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Student status, moving and payroll need the latest system update. Open <strong>System → Updates</strong> and apply it (migration 048), then reload this page.</div><?php endif; ?>
<div class="row g-4">
<div class="col-lg-8">
<div class="card mb-4 border-danger"><div class="card-header fw-semibold text-danger">Delete tutor</div><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="small text-muted">Removes this tutor and their sign-in. Their assigned students stay enrolled and become unassigned, and the tutor's payroll history is removed. To just stop them teaching, untick Active in Edit Tutor instead.</span>
    <form method="POST" onsubmit="return confirm('Delete this tutor permanently? This cannot be undone.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_tutor"><button class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete tutor</button></form>
</div></div>
<div class="card mb-4"><div class="card-header fw-semibold">Edit Tutor</div><div class="card-body">
<form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?php echo htmlspecialchars($fullName); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?php echo htmlspecialchars($tutor['email']); ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($tutor['phone'] ?? ''); ?>"></div>
    <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*">
        <?php if ($tutor['photo_path']): ?><img src="<?php echo htmlspecialchars($tutor['photo_path']); ?>" class="mt-2 rounded" height="80"><?php endif; ?>
    </div>
    <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($tutor['bio'] ?? ''); ?></textarea></div>
    <?php if (!empty($tutor['courses'])): ?>
    <div class="col-12">
        <label class="form-label mb-1">Courses (derived from assigned students)</label><br>
        <?php foreach ($tutor['courses'] as $c): ?>
            <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($c['title']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" <?php echo $tutor['is_active'] ? 'checked' : ''; ?>><label for="active">Active</label></div></div>
</div>
<button type="submit" class="btn btn-primary mt-3">Save Changes</button>
</form>
</div></div>

<?php if ($installed): $current = $ledger['current_students']; $former = $ledger['former_students']; ?>
<div class="card mb-4" id="students">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Assigned students <span class="badge bg-primary ms-1"><?php echo count($current); ?></span></span>
        <span class="small text-muted"><?php echo (int)$ledger['active_students']; ?> earning this month</span>
    </div>
    <div class="card-body p-0">
    <?php if (empty($current)): ?>
        <p class="text-muted p-4 mb-0">No students assigned yet. Add some below.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Student</th><th>Course</th><th>Salary status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($current as $s): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($s['student_name']); ?></strong>
                    <div class="text-muted small">Since <?php echo date('d M Y', strtotime($s['assigned_at'])); ?></div></td>
                <td class="small"><?php if (!empty($s['course_title'])): ?><span class="badge bg-light text-dark border"><?php echo htmlspecialchars((string)$s['abbreviation']); ?></span> <?php echo htmlspecialchars($s['course_title']); ?><div class="text-muted">Month <?php echo (int)$s['months_elapsed']; ?> of <?php echo (int)$s['months']; ?></div><?php else: ?><span class="text-muted">No approved course</span><?php endif; ?></td>
                <td><?php echo $pauseBadge($s['pause'], $s); ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($s['pause']): ?>
                    <form method="POST" class="d-inline"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="end_pause"><input type="hidden" name="pause_id" value="<?php echo (int)$s['pause']['id']; ?>">
                        <button class="btn btn-sm btn-success" title="Salary runs again from today"><i class="fas fa-play me-1"></i>Resume</button>
                    </form>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-warning js-status" data-user="<?php echo (int)$s['user_id']; ?>" data-name="<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>"><i class="fas fa-pause me-1"></i>Pause / break</button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary js-move" data-user="<?php echo (int)$s['user_id']; ?>" data-name="<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>"><i class="fas fa-user-times me-1"></i>Unassign / move</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Assign students</div>
    <div class="card-body">
    <?php if (empty($available)): ?>
        <p class="text-muted mb-0">Every registered student is already assigned to this tutor.</p>
    <?php else: ?>
    <form method="POST" id="assignForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="assign_students">
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="search" class="form-control" id="studentFilter" placeholder="Search student name, email or course..." autocomplete="off">
        </div>
        <div class="assign-sheet">
        <table class="table table-sm mb-0" id="assignTable">
            <thead><tr><th style="width:38px"></th><th>Student</th><th>Course</th><th>Duration</th><th>Email</th></tr></thead>
            <tbody>
            <?php foreach ($available as $s): ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['email'] . ' ' . $s['course_titles'] . ' ' . str_replace(['|', '||'], ' ', (string)$s['course_rows']))); ?>">
                    <td><input type="checkbox" class="form-check-input" name="student_ids[]" value="<?php echo (int)$s['user_id']; ?>"></td>
                    <?php $cr = array_map(fn($x) => array_pad(explode('|', $x, 2), 2, ''), array_filter(explode('||', (string)$s['course_rows']))); ?>
                    <td class="fw-semibold"><?php echo htmlspecialchars($s['full_name']); ?></td>
                    <td class="small"><?php echo htmlspecialchars(implode(', ', array_column($cr, 0))); ?></td>
                    <td class="small"><?php echo htmlspecialchars(implode(', ', array_filter(array_column($cr, 1)))); ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($s['email']); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr id="assignEmpty" style="display:none"><td colspan="5" class="text-muted text-center py-3">No student matches that search.</td></tr>
            </tbody>
        </table>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <button class="btn btn-primary" id="assignBtn" disabled><i class="fas fa-user-plus me-1"></i>Assign selected (<span id="assignCount">0</span>)</button>
            <small class="text-muted" id="assignHint">Showing 3 of <?php echo count($available); ?> students. Type a name, course or duration to find the rest, then click a row to select it.</small>
        </div>
    </form>
    <?php endif; ?>
    </div>
</div>

<?php if (!empty($former)): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Previously assigned <span class="text-muted fw-normal small">(what the tutor earned for them is kept)</span></div>
    <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Student</th><th>Course</th><th>Removed</th><th class="text-end">Earned</th><th></th></tr></thead>
        <tbody>
        <?php $seen = []; foreach ($former as $f): $isBack = in_array((int)$f['user_id'], $assignedIds, true); ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$f['student_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars((string)($f['course_title'] ?? '')); ?></td>
                <td><?php echo date('d M Y', strtotime($f['removed_at'])); ?></td>
                <td class="text-end"><?php echo PayrollCalculator::money($f['earned_to_date']); ?></td>
                <td class="text-end">
                    <?php if ($isBack): ?><span class="badge bg-success">Assigned again</span>
                    <?php elseif (!isset($seen[$f['user_id']])): $seen[$f['user_id']] = true; ?>
                    <form method="POST" class="d-inline"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reassign_former"><input type="hidden" name="history_id" value="<?php echo (int)$f['id']; ?>">
                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-undo me-1"></i>Reassign back</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
<div class="col-lg-4">
<?php require __DIR__ . '/../../components/tutors/permission_card.php'; ?>
<?php $docUserId = (int) ($tutor['user_id'] ?? 0); require __DIR__ . '/../../components/documents/admin_card.php'; ?>
<?php if ($installed): $ts = $ledger['tutor_status']; ?>
<div class="card mb-4 border-<?php echo $ts ? 'warning' : 'success'; ?>"><div class="card-header fw-semibold">Tutor availability</div><div class="card-body">
    <div class="mb-2"><?php echo $pauseBadge($ts); ?></div>
    <?php if ($ts): ?>
        <p class="small text-muted">Salary is not adding up for any of this tutor's students while they are away.</p>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="end_pause"><input type="hidden" name="pause_id" value="<?php echo (int)$ts['id']; ?>">
            <button class="btn btn-success w-100"><i class="fas fa-play me-1"></i>Tutor is back — resume salary</button>
        </form>
    <?php else: ?>
        <p class="small text-muted">Mark the tutor on break or holiday. Salary stops adding up for all their students until you resume it.</p>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="start_pause">
            <select name="kind" class="form-select form-select-sm mb-2"><option value="break">On break</option><option value="holiday">On holiday</option></select>
            <label class="form-label small mb-0">From</label><input type="date" name="start" value="<?php echo $today; ?>" class="form-control form-control-sm mb-2" required>
            <label class="form-label small mb-0">Expected back <span class="text-muted">(optional)</span></label><input type="date" name="end" class="form-control form-control-sm mb-2">
            <input type="text" name="note" maxlength="255" class="form-control form-control-sm mb-2" placeholder="Note (optional)">
            <button class="btn btn-warning w-100"><i class="fas fa-umbrella-beach me-1"></i>Start break / holiday</button>
        </form>
    <?php endif; ?>
</div></div>
<?php if ((int)($_SESSION['role_id'] ?? 99) === 1): ?><a href="<?php echo url('superadmin/payroll/tutor.php?id=' . $id); ?>" class="btn btn-outline-primary w-100 mb-4"><i class="fas fa-wallet me-1"></i>Salary and payments</a><?php endif; ?>
<?php endif; ?>
<div class="card border-primary"><div class="card-header bg-primary text-white">Credentials</div><div class="card-body">
<p class="small text-muted">Reset password and email login details.</p>
<?php if ($tutor['credentials_sent_at']): ?><p class="small text-success">Last sent: <?php echo date('d M Y H:i', strtotime($tutor['credentials_sent_at'])); ?></p><?php endif; ?>
<form method="POST" onsubmit="return confirm('Send new credentials?');"><?php echo csrf_field(); ?>
<input type="hidden" name="action" value="send_credentials">
<button class="btn btn-primary w-100"><i class="fas fa-envelope me-1"></i> Email Credentials</button>
</form>
</div></div>
</div>
</div>

<?php if ($installed): ?>
<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="start_pause"><input type="hidden" name="user_id" id="statusUser">
    <div class="modal-header"><h5 class="modal-title">Pause salary: <span id="statusName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">While paused, no salary adds up for this student and their course months stop counting. When you resume, the rest of the course runs on and the tutor earns for the months left.</p>
        <label class="form-label">Status</label>
        <select name="kind" class="form-select mb-3"><option value="pause">Paused</option><option value="break">Student on break</option><option value="holiday">Student on holiday</option></select>
        <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label">From</label><input type="date" name="start" value="<?php echo $today; ?>" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Expected back <span class="text-muted">(optional)</span></label><input type="date" name="end" class="form-control"></div>
        </div>
        <input type="text" name="note" maxlength="255" class="form-control" placeholder="Note (optional)">
        <div class="form-text">A month counts as paused when the pause covers at least half of it.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Pause salary</button></div>
</form></div></div>

<div class="modal fade" id="moveModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Unassign or move: <span id="moveName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <form method="POST" class="mb-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="move"><input type="hidden" name="user_id" class="moveUser">
            <label class="form-label fw-semibold">Move to another tutor</label>
            <div class="input-group">
                <select name="to_tutor" class="form-select" required>
                    <option value="">Choose a tutor…</option>
                    <?php foreach ($otherTutors as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary">Move</button>
            </div>
            <div class="form-text">The new tutor earns for the months of the course that are left. This tutor keeps what they already earned.</div>
        </form>
        <form method="POST" onsubmit="return confirm('Unassign this student from the tutor?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="unassign"><input type="hidden" name="user_id" class="moveUser">
            <label class="form-label fw-semibold">Or just unassign</label>
            <div><button class="btn btn-outline-danger">Unassign student</button></div>
            <div class="form-text">Salary for this student stops. You can reassign them from the "Previously assigned" list.</div>
        </form>
    </div>
</div></div></div>
<?php endif; ?>
<?php
$extra_css = '<style>
.assign-sheet{border:1px solid #b8c2cc;max-height:340px;overflow:auto}
.assign-sheet th,.assign-sheet td{border-right:1px solid #d5dbe1;border-bottom:1px solid #d5dbe1;vertical-align:middle}
.assign-sheet thead th{position:sticky;top:0;background:#eef1f3;z-index:1}
.assign-sheet tbody tr[data-search]{cursor:pointer}
.assign-sheet tbody tr[data-search]:hover td{background:#f5faf6}
.assign-sheet tbody tr.sel td{background:#e1f0e6}
</style>';
$extra_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
    var f = document.getElementById("studentFilter");
    if (f) {
        var rows = Array.prototype.slice.call(document.querySelectorAll("#assignTable tbody tr[data-search]")), LIMIT = 3;
        var count = document.getElementById("assignCount"), btn = document.getElementById("assignBtn");
        function refresh() {
            var q = f.value.toLowerCase().trim(), shown = 0;
            rows.forEach(function (r) {
                var ok = (q === "" || r.dataset.search.indexOf(q) !== -1) && shown < LIMIT;
                if (ok) shown++;
                r.style.display = ok ? "" : "none";
            });
            document.getElementById("assignEmpty").style.display = shown ? "none" : "";
            var n = rows.filter(function (r) { return r.querySelector("input").checked; }).length;
            count.textContent = n; btn.disabled = n === 0;
            rows.forEach(function (r) { r.classList.toggle("sel", r.querySelector("input").checked); });
        }
        f.addEventListener("input", refresh);
        rows.forEach(function (r) { r.addEventListener("click", function (e) {
            if (e.target.tagName !== "INPUT") { var c = r.querySelector("input"); c.checked = !c.checked; }
            refresh();
        }); });
        refresh();
    }
    function open(id) { return new bootstrap.Modal(document.getElementById(id)); }
    document.querySelectorAll(".js-status").forEach(function (b) { b.addEventListener("click", function () {
        document.getElementById("statusUser").value = b.dataset.user;
        document.getElementById("statusName").textContent = b.dataset.name;
        open("statusModal").show();
    }); });
    document.querySelectorAll(".js-move").forEach(function (b) { b.addEventListener("click", function () {
        document.querySelectorAll(".moveUser").forEach(function (i) { i.value = b.dataset.user; });
        document.getElementById("moveName").textContent = b.dataset.name;
        open("moveModal").show();
    }); });
});
</script>';
$content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
