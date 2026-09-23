<?php
// Superadmin: one student's record: fees, payments, tutor, and sign-in details. Standalone (nothing shared with the admin portal).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';
require_once __DIR__ . '/../../../app/v1/services/CourseChangeService.php';
require_once __DIR__ . '/../../../app/v1/helpers/study_location.php';

use Models\Connection;
use Models\RegistrationModel;
use Models\PayrollModel;
use Controllers\StudentController;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
$courseSvc = new CourseChangeService($pdo);
$id = (int)($_GET['id'] ?? 0);
$reg = $id ? (new RegistrationModel($pdo))->getById($id) : null;
if (!$reg || $reg['status'] !== 'approved') {
    SessionModel::setFlash('error', 'Student not found.');
    header('Location: index.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = 'view.php?id=' . $id;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'add_course' || $action === 'transfer_course') {
        $enrols = $courseSvc->enrolments($reg);
        $targetCourse = (int)($_POST['course_id'] ?? 0);
        if ($action === 'add_course') {
            $r = $courseSvc->addCourse($reg, $targetCourse, (int)($_SESSION['user_id'] ?? 0), $_POST['study_mode'] ?? null, $_POST['study_location'] ?? null);
        } else {
            $from = (int)($_POST['from_reg_id'] ?? 0);
            $fromReg = null;
            foreach ($enrols as $e) if ((int)$e['id'] === $from) $fromReg = $e;
            $r = $fromReg ? $courseSvc->transfer($fromReg, $targetCourse, (int)($_SESSION['user_id'] ?? 0), $_POST['study_mode'] ?? null, $_POST['study_location'] ?? null) : ['ok' => false, 'error' => 'Enrolment not found.'];
        }
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', htmlspecialchars($r['ok'] ? $r['message'] : $r['error']));
    } elseif ($action === 'set_study') {
        $target = (int)($_POST['study_reg_id'] ?? 0);
        $ok = false;
        foreach ($courseSvc->enrolments($reg) as $e) if ((int)$e['id'] === $target) $ok = true;
        if ($ok && (($_POST['study_mode'] ?? '') === 'online' || (($_POST['study_mode'] ?? '') === 'physical' && isset(study_locations()[$_POST['study_location'] ?? ''])))) {
            study_placement_set($pdo, $target, $_POST['study_mode'], $_POST['study_location']);
            SessionModel::setFlash('success', 'Mode of study and location updated.');
        } else {
            SessionModel::setFlash('error', 'Choose a mode of study, and a location (Thika or Nairobi) for physical classes.');
        }
    } elseif ($action === 'assign_tutor' && $payroll->isInstalled() && $reg['user_id']) {
        $tid = (int)($_POST['tutor_id'] ?? 0);
        $n = $tid > 0 ? $payroll->assignStudents($tid, [(int)$reg['user_id']]) : 0;
        SessionModel::setFlash($n ? 'success' : 'error', $n ? 'Tutor assigned.' : 'Choose a tutor who is not already assigned.');
    } elseif ($action === 'send_credentials') {
        $r = (new StudentController())->sendCredentials($id);
        if ($r['ok']) {
            $mail = (new StudentNotifier($pdo, null, true))->sendCredentials($reg, $r['temp_password']);
            $msg = !empty($mail['ok'])
                ? 'New sign-in details emailed to ' . htmlspecialchars($reg['email']) . '.'
                : 'The email did NOT send (' . htmlspecialchars((string)($mail['message'] ?? 'unknown error')) . '). Give the student these details yourself. Temporary password: <strong>' . htmlspecialchars($r['temp_password']) . '</strong>';
            SessionModel::setFlash(!empty($mail['ok']) ? 'success' : 'warning', $msg);
        } else {
            SessionModel::setFlash('error', $r['error']);
        }
    }
    header('Location: ' . $self); exit();
}

$tutors = $payroll->isInstalled() ? array_values(array_filter($payroll->listTutorNames(), fn($t) => $t['is_active'])) : [];
$myTutors = ($payroll->isInstalled() && $reg['user_id']) ? $payroll->getStudentTutors((int)$reg['user_id']) : [];
$enrols = $courseSvc->enrolments($reg);
$available = $courseSvc->availableCourses($enrols);
$regIds = array_map(fn($e) => (int)$e['id'], $enrols);
$pay = $pdo->query("SELECT amount, status, payer_reference, registration_id, COALESCE(reviewed_at, paid_at) AS d FROM payments WHERE registration_id IN (" . implode(',', $regIds) . ") ORDER BY d DESC");
$payments = $pay->fetchAll();
$fee = 0.0; $paid = 0.0;
foreach ($enrols as &$e) {
    $e['fee'] = (float)$e['course_fee'] + ($e['registration_fee_waived'] ? 0 : (float)$e['registration_fee']);
    $e['bal'] = max(0, $e['fee'] - (float)$e['paid']);
    $fee += $e['fee']; $paid += (float)$e['paid'];
}
unset($e);
$bal = max(0, $fee - $paid);
$sms = null;
try {
    $st = $pdo->prepare('SELECT id FROM sms_students WHERE registration_id = ? LIMIT 1');
    $st->execute([$id]);
    $sms = (int)$st->fetchColumn() ?: null;
} catch (Throwable $e) { $sms = null; }
$k = fn($n): string => 'KES ' . number_format((float)$n, 0);

$page_title = $reg['full_name'];
$sa_subtitle = htmlspecialchars((string)$reg['registration_no']) . ' · ' . htmlspecialchars((string)($reg['course_title'] ?? ''));
$sa_actions = '<a class="sa-btn-ghost" href="index.php"><i class="fas fa-table me-1"></i>All students</a>';
ob_start();
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Total fees</div><div class="fs-4 fw-bold"><?php echo $k($fee); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid</div><div class="fs-4 fw-bold text-success"><?php echo $k($paid); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 <?php echo $bal > 0 ? 'border-danger' : ''; ?>"><div class="card-body"><div class="text-muted small">Balance</div><div class="fs-4 fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $k($bal); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Registered</div><div class="fs-6 fw-bold"><?php echo !empty($reg['created_at']) ? date('d M Y', strtotime($reg['created_at'])) : '—'; ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header fw-semibold">Details</div><div class="card-body">
            <div class="row g-2 small">
                <div class="col-sm-6"><div class="text-muted">Email</div><div class="text-break"><?php echo htmlspecialchars((string)$reg['email']); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Phone</div><div><?php echo htmlspecialchars((string)$reg['phone']); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Courses</div><div><?php echo htmlspecialchars(implode(', ', array_column($enrols, 'course_title'))); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Tutor</div><div><?php echo $myTutors ? htmlspecialchars(implode(', ', array_column($myTutors, 'full_name'))) : '<span class="text-muted">None yet</span>'; ?></div></div>
            </div>
            <?php if ($sms): ?><a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo url('superadmin/sms/students/view.php?id=' . $sms); ?>">Open fee record</a><?php endif; ?>
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Courses
            <?php if ($available): ?><button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCourseModal"><i class="fas fa-plus me-1"></i>Add course</button><?php endif; ?></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Course</th><th class="text-end">Fee</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th></th></tr></thead>
                <tbody><?php foreach ($enrols as $e): ?><tr>
                    <td><?php echo htmlspecialchars((string)$e['course_title']); ?><div class="text-muted small"><?php echo htmlspecialchars((string)$e['registration_no']); ?></div>
                        <?php if ($e['fee_balance_note'] && strncmp($e['fee_balance_note'], 'Transferred', 11) === 0): ?><div class="text-muted small"><?php echo htmlspecialchars($e['fee_balance_note']); ?></div><?php endif; ?></td>
                    <td class="text-end"><?php echo number_format($e['fee']); ?></td><td class="text-end"><?php echo number_format((float)$e['paid']); ?></td>
                    <td class="text-end fw-bold <?php echo $e['bal'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($e['bal']); ?></td>
                    <td class="text-end"><?php if ($available): ?><button class="btn btn-sm btn-outline-primary js-transfer" data-from="<?php echo (int)$e['id']; ?>" data-title="<?php echo htmlspecialchars((string)$e['course_title'], ENT_QUOTES); ?>" data-bs-toggle="modal" data-bs-target="#transferModal">Transfer</button><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </div>
        <div class="card mb-3"><div class="card-header fw-semibold">Where they learn</div><div class="card-body">
            <?php if (!study_location_supported($pdo)): ?><p class="small text-muted mb-0">Apply the pending system update to record learning locations.</p><?php else: foreach ($enrols as $e): ?>
            <form method="POST" class="row g-2 align-items-end mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_study"><input type="hidden" name="study_reg_id" value="<?php echo (int)$e['id']; ?>">
                <div class="col-12 small fw-semibold"><?php echo htmlspecialchars((string)$e['course_title']); ?></div>
                <?php echo study_placement_fields($e, true, 'col-6 col-sm-4'); ?>
                <div class="col-12 col-sm-4"><button class="btn btn-sm btn-outline-primary w-100">Save</button></div>
            </form>
            <?php endforeach; endif; ?>
        </div></div>
        <div class="card"><div class="card-header fw-semibold">Payments</div>
            <?php if (!$payments): ?><div class="card-body text-muted">No payments recorded.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($payments as $p): ?><tr><td><?php echo $p['d'] ? date('d M Y', strtotime($p['d'])) : '—'; ?></td><td><?php echo htmlspecialchars((string)$p['payer_reference']); ?></td><td class="text-end"><?php echo number_format((float)$p['amount']); ?></td><td><?php echo ucfirst((string)$p['status']); ?></td></tr><?php endforeach; ?></tbody>
            </table></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <?php if ($tutors): ?>
        <div class="card mb-3"><div class="card-header fw-semibold">Assign a tutor</div><div class="card-body">
            <form method="POST" class="input-group"><?php echo csrf_field(); ?><input type="hidden" name="action" value="assign_tutor">
                <select name="tutor_id" class="form-select" required><option value="">Choose a tutor…</option><?php foreach ($tutors as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option><?php endforeach; ?></select>
                <button class="btn btn-primary">Assign</button>
            </form>
        </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-header fw-semibold">Sign-in</div><div class="card-body">
            <p class="small text-muted">Resets the password and emails new sign-in details. If the email fails you are shown the password to hand over.</p>
            <form method="POST" onsubmit="return confirm('Reset the password and email new sign-in details?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="send_credentials">
                <button class="btn btn-outline-secondary w-100"><i class="fas fa-key me-1"></i>Send credentials</button></form>
        </div></div>
    </div>
</div>
<?php if ($available): ?>
<div class="modal fade" id="addCourseModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_course">
    <div class="modal-header"><h5 class="modal-title">Add a course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small text-muted">Enrols <?php echo htmlspecialchars($reg['full_name']); ?> in another course on the same account. That course's fee is added to their balance.</p>
        <select name="course_id" class="form-select" required><option value="">Choose a course…</option><?php foreach ($available as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?> — KES <?php echo number_format((float)$c['course_fee']); ?></option><?php endforeach; ?></select></div>
    <div class="row g-2 px-3 pb-3"><?php echo study_placement_fields($reg, true, 'col-6'); ?></div>
    <div class="modal-footer"><button class="btn btn-success">Add course</button></div></form></div></div>
<div class="modal fade" id="transferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content" onsubmit="return confirm('Transfer this student? Their fee will change to the new course and progress restarts at month 1.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="transfer_course"><input type="hidden" name="from_reg_id" id="trFrom">
    <div class="modal-header"><h5 class="modal-title">Transfer course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small text-muted">Move from <strong id="trTitle"></strong> to another course. Payments already made are kept; the fee becomes the new course's fee.</p>
        <select name="course_id" class="form-select" required><option value="">Transfer to…</option><?php foreach ($available as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?> — KES <?php echo number_format((float)$c['course_fee']); ?></option><?php endforeach; ?></select></div>
    <div class="row g-2 px-3 pb-3">
        <?php echo study_placement_fields([], false, 'col-6', true); ?>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Transfer</button></div></form></div></div>
<script>document.querySelectorAll('.js-transfer').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('trFrom').value = b.dataset.from; document.getElementById('trTitle').textContent = b.dataset.title; }); });</script>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
