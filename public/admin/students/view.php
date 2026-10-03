<?php
// Admin: one student's record: details, fees, payments, tutor, and sign-in details. Shows paid and balance only, never fees.
// Admin can edit name/email/phone, record manual payments, and add/transfer courses.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';
require_once __DIR__ . '/../../../app/v1/services/ReceiptGenerator.php';
require_once __DIR__ . '/../../../app/v1/services/CourseChangeService.php';
require_once __DIR__ . '/../../../app/v1/helpers/study_location.php';

use Models\Connection;
use Models\RegistrationModel;
use Models\PayrollModel;
use Models\PaymentModel;
use Controllers\StudentController;

Middleware::requireAdmin();

/**
 * Build a receipt for this payment if it doesn't have one yet, then email it to the student.
 * @return array{ok:bool, message?:string}
 */
/** Parent/guardian contact lives in sms_students (not registrations) — create the row if this student doesn't have one yet. */
function upsert_parent_contact(\PDO $pdo, array $reg, string $parentPhone, string $parentEmail): void {
    $st = $pdo->prepare('SELECT id FROM sms_students WHERE registration_id = :rid LIMIT 1');
    $st->execute([':rid' => $reg['id']]);
    $smsId = $st->fetchColumn();
    if ($smsId) {
        $pdo->prepare('UPDATE sms_students SET parent_phone = :pp, parent_email = :pe WHERE id = :id')
            ->execute([':pp' => $parentPhone ?: null, ':pe' => $parentEmail ?: null, ':id' => $smsId]);
    } else {
        $pdo->prepare(
            "INSERT INTO sms_students (name, email, phone, course_id, cost, paid, status, user_id, registration_id, parent_phone, parent_email)
             VALUES (:name, :email, :phone, :course_id, :cost, 0, 'active', :user_id, :rid, :pp, :pe)"
        )->execute([
            ':name' => $reg['full_name'], ':email' => $reg['email'], ':phone' => $reg['phone'],
            ':course_id' => $reg['course_id'], ':cost' => $reg['course_fee'], ':user_id' => $reg['user_id'] ?: null,
            ':rid' => $reg['id'], ':pp' => $parentPhone ?: null, ':pe' => $parentEmail ?: null,
        ]);
    }
}

function send_or_regenerate_receipt(\PDO $pdo, PaymentModel $paymentModel, int $paymentId, bool $force = false): array {
    $payment = $paymentModel->getById($paymentId);
    if (!$payment) {
        return ['ok' => false, 'message' => 'Payment not found.'];
    }
    if ($force || empty($payment['receipt_html'])) {
        $totalPaid = $paymentModel->getTotalApproved((int)$payment['registration_id']);
        $receiptNo = $payment['receipt_no'] ?: $paymentModel->generateReceiptNo($paymentId);
        $html = \ReceiptGenerator::generate($payment, array_merge($payment, ['receipt_no' => $receiptNo]), $totalPaid);
        $pdo->prepare('UPDATE payments SET receipt_no = :no, receipt_html = :html WHERE id = :id')
            ->execute([':no' => $receiptNo, ':html' => $html, ':id' => $paymentId]);
        $payment = $paymentModel->getById($paymentId);
    }
    $notifier = new StudentNotifier($pdo, null, true);
    return $notifier->sendPaymentApproved($payment, $payment, (string)$payment['receipt_html']);
}

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
$courseSvc = new CourseChangeService($pdo);
$payments_m = new PaymentModel($pdo);
$regModel = new RegistrationModel($pdo);
$id = (int)($_GET['id'] ?? 0);
$reg = $id ? $regModel->getById($id) : null;
if (!$reg || $reg['status'] !== 'approved') {
    SessionModel::setFlash('error', 'Student not found.');
    header('Location: ' . url('admin/students/index.php')); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = 'view.php?id=' . $id;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'update_contact') {
        $r = $regModel->updateContact($id, $_POST['full_name'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '', $_POST['secondary_phone'] ?? '');
        if ($r['ok']) {
            $reg = $regModel->getById($id);
            upsert_parent_contact($pdo, $reg, trim($_POST['parent_phone'] ?? ''), trim($_POST['parent_email'] ?? ''));
        }
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Student details updated.' : htmlspecialchars($r['error']));
    } elseif ($action === 'record_payment') {
        $enrols0 = $courseSvc->enrolments($reg);
        $targetReg = (int)($_POST['payment_reg_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $valid = false;
        foreach ($enrols0 as $e) if ((int)$e['id'] === $targetReg) $valid = true;
        if (!$valid) {
            SessionModel::setFlash('error', 'Choose which course this payment is for.');
        } elseif ($amount <= 0) {
            SessionModel::setFlash('error', 'Enter an amount greater than zero.');
        } else {
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $paymentId = $payments_m->recordManual($targetReg, $amount, $_POST['reference'] ?? '', $adminId, $_POST['notes'] ?? null);
            $mail = send_or_regenerate_receipt($pdo, $payments_m, $paymentId);
            $msg = 'Payment of ' . number_format($amount) . ' recorded.';
            $msg .= !empty($mail['ok']) ? ' Receipt emailed to the student.' : ' The receipt email did NOT send (' . htmlspecialchars((string)($mail['message'] ?? 'unknown error')) . ').';
            SessionModel::setFlash(!empty($mail['ok']) ? 'success' : 'warning', $msg);
        }
    } elseif ($action === 'send_receipt') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $belongs = false;
        foreach ($courseSvc->enrolments($reg) as $e) if ((int)$e['id'] === (int)($payments_m->getById($paymentId)['registration_id'] ?? -1)) $belongs = true;
        if (!$belongs) {
            SessionModel::setFlash('error', 'Payment not found for this student.');
        } else {
            $mail = send_or_regenerate_receipt($pdo, $payments_m, $paymentId);
            SessionModel::setFlash(!empty($mail['ok']) ? 'success' : 'error', !empty($mail['ok']) ? 'Receipt emailed to the student.' : 'Could not send the receipt: ' . htmlspecialchars((string)($mail['message'] ?? 'unknown error')));
        }
    } elseif ($action === 'edit_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $newAmount = (float)($_POST['new_amount'] ?? 0);
        $existing = $payments_m->getById($paymentId);
        $belongs = false;
        foreach ($courseSvc->enrolments($reg) as $e) if ((int)$e['id'] === (int)($existing['registration_id'] ?? -1)) $belongs = true;
        if (!$belongs) {
            SessionModel::setFlash('error', 'Payment not found for this student.');
        } else {
            $r = $payments_m->correctAmount($paymentId, $newAmount, (int)($_SESSION['user_id'] ?? 0));
            if (!$r['ok']) {
                SessionModel::setFlash('error', htmlspecialchars($r['error']));
            } else {
                $mail = send_or_regenerate_receipt($pdo, $payments_m, $paymentId, true);
                $msg = 'Payment corrected: KES ' . number_format($r['old_amount']) . ' → KES ' . number_format($r['new_amount']) . '. Balances updated.';
                $msg .= !empty($mail['ok']) ? ' Corrected receipt emailed to the student.' : ' The receipt email did NOT send (' . htmlspecialchars((string)($mail['message'] ?? 'unknown error')) . ').';
                SessionModel::setFlash(!empty($mail['ok']) ? 'success' : 'warning', $msg);
            }
        }
    } elseif ($action === 'add_course' || $action === 'transfer_course') {
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
    } elseif ($action === 'edit_reg_no') {
        $newRegNo = trim($_POST['new_reg_no'] ?? '');
        $regId = (int)($_POST['edit_reg_id'] ?? 0);
        
        if (empty($newRegNo)) {
            SessionModel::setFlash('error', 'Registration number cannot be empty.');
        } else {
            try {
                $pdo->beginTransaction();
                
                $st = $pdo->prepare('UPDATE registrations SET registration_no = ? WHERE id = ?');
                $st->execute([$newRegNo, $regId]);
                
                if ($regId === $id && !empty($reg['user_id'])) {
                    $uSt = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
                    $uSt->execute([$newRegNo, $reg['user_id']]);
                }
                
                $pdo->commit();
                SessionModel::setFlash('success', 'Registration number updated to ' . htmlspecialchars($newRegNo));
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() == 23000) {
                    SessionModel::setFlash('error', 'That registration number is already in use by another student.');
                } else {
                    SessionModel::setFlash('error', 'Could not update registration number.');
                }
            }
        }
    }
    header('Location: ' . $self); exit();
}

$tutors = $payroll->isInstalled() ? array_values(array_filter($payroll->listTutorNames(), fn($t) => $t['is_active'])) : [];
$myTutors = ($payroll->isInstalled() && $reg['user_id']) ? $payroll->getStudentTutors((int)$reg['user_id']) : [];
$enrols = $courseSvc->enrolments($reg);
$available = $courseSvc->availableCourses($enrols);
$regIds = array_map(fn($e) => (int)$e['id'], $enrols);
$pay = $pdo->query("SELECT id, amount, status, payer_reference, registration_id, receipt_no, receipt_html, COALESCE(reviewed_at, paid_at) AS d FROM payments WHERE registration_id IN (" . implode(',', $regIds) . ") ORDER BY d DESC");
$payments = $pay->fetchAll();
$fee = 0.0; $paid = 0.0;
foreach ($enrols as &$e) {
    $e['fee'] = (float)$e['course_fee'] + ($e['registration_fee_waived'] ? 0 : (float)$e['registration_fee']);
    $e['bal'] = max(0, $e['fee'] - (float)$e['paid']);
    $fee += $e['fee']; $paid += (float)$e['paid'];
}
unset($e);
$bal = max(0, $fee - $paid);
$k = fn($n): string => 'KES ' . number_format((float)$n, 0);

$parentStmt = $pdo->prepare('SELECT parent_phone, parent_email FROM sms_students WHERE registration_id = :rid LIMIT 1');
$parentStmt->execute([':rid' => $id]);
$parent = $parentStmt->fetch() ?: ['parent_phone' => '', 'parent_email' => ''];

$page_title = $reg['full_name'];
$breadcrumbs = [['label' => 'Students', 'url' => url('admin/students/index.php')], ['label' => $reg['full_name'], 'active' => true]];
ob_start();
?>
<div class="row g-3 mb-3">
    <div class="col-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid</div><div class="fs-4 fw-bold text-success"><?php echo $k($paid); ?></div></div></div></div>
    <div class="col-4"><div class="card h-100 <?php echo $bal > 0 ? 'border-danger' : ''; ?>"><div class="card-body"><div class="text-muted small">Balance</div><div class="fs-4 fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $k($bal); ?></div></div></div></div>
    <div class="col-4"><div class="card h-100"><div class="card-body"><div class="text-muted small">Registered</div><div class="fs-6 fw-bold"><?php echo !empty($reg['created_at']) ? date('d M Y', strtotime($reg['created_at'])) : '—'; ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Details
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDetailsModal"><i class="fas fa-pen me-1"></i>Edit</button></div>
            <div class="card-body">
            <div class="row g-2 small">
                <div class="col-sm-6"><div class="text-muted">Full name</div><div><?php echo htmlspecialchars((string)$reg['full_name']); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Email</div><div class="text-break"><?php echo htmlspecialchars((string)$reg['email']); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Phone</div><div><?php echo htmlspecialchars((string)$reg['phone']); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Secondary phone</div><div><?php echo !empty($reg['secondary_phone']) ? htmlspecialchars((string)$reg['secondary_phone']) : '<span class="text-muted">Not provided</span>'; ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Parent/guardian phone</div><div><?php echo $parent['parent_phone'] ? htmlspecialchars($parent['parent_phone']) : '<span class="text-muted">Not provided</span>'; ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Parent/guardian email</div><div class="text-break"><?php echo $parent['parent_email'] ? htmlspecialchars($parent['parent_email']) : '<span class="text-muted">Not provided</span>'; ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Courses</div><div><?php echo htmlspecialchars(implode(', ', array_column($enrols, 'course_title'))); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Tutor</div><div><?php echo $myTutors ? htmlspecialchars(implode(', ', array_column($myTutors, 'full_name'))) : '<span class="text-muted">None yet</span>'; ?></div></div>
            </div>
        </div></div>
        <div class="card mb-3"><div class="card-header fw-semibold">Payments &amp; Receipts</div>
            <?php if (!$payments): ?><div class="card-body text-muted">No payments recorded.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th><th>Receipt</th></tr></thead>
                <tbody><?php foreach ($payments as $p): ?><tr><td><?php echo $p['d'] ? date('d M Y', strtotime($p['d'])) : '—'; ?></td><td><?php echo htmlspecialchars((string)$p['payer_reference']); ?></td><td class="text-end"><?php echo number_format((float)$p['amount']); ?></td><td><?php echo ucfirst((string)$p['status']); ?></td>
                    <td class="text-nowrap">
                        <?php if ($p['status'] === 'approved'): ?>
                            <?php if ($p['receipt_html']): ?><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars(url('admin/payments/receipt.php?id=' . (int)$p['id'])); ?>">View</a><?php endif; ?>
                            <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="send_receipt"><input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                <button class="btn btn-sm btn-outline-success"><?php echo $p['receipt_html'] ? 'Resend' : 'Create & send'; ?></button>
                            </form>
                            <button class="btn btn-sm btn-outline-warning js-edit-payment" data-id="<?php echo (int)$p['id']; ?>" data-amount="<?php echo htmlspecialchars((string)$p['amount'], ENT_QUOTES); ?>" data-ref="<?php echo htmlspecialchars((string)$p['payer_reference'], ENT_QUOTES); ?>" data-bs-toggle="modal" data-bs-target="#editPaymentModal">Edit</button>
                        <?php endif; ?>
                    </td>
                </tr><?php endforeach; ?></tbody>
            </table></div><?php endif; ?>
        </div>
        <div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">Courses
            <div>
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal"><i class="fas fa-money-bill me-1"></i>Record payment</button>
                <?php if ($available): ?><button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addCourseModal"><i class="fas fa-plus me-1"></i>Add course</button><?php endif; ?>
            </div></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Course</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th></th></tr></thead>
                <tbody><?php foreach ($enrols as $e): ?><tr>
                    <td><?php echo htmlspecialchars((string)$e['course_title']); ?>
                        <div class="text-muted small d-flex align-items-center gap-2">
                            <?php echo htmlspecialchars((string)$e['registration_no']); ?>
                            <button type="button" class="btn btn-link p-0 m-0 js-edit-reg-no" data-id="<?php echo (int)$e['id']; ?>" data-regno="<?php echo htmlspecialchars((string)$e['registration_no'], ENT_QUOTES); ?>" data-bs-toggle="modal" data-bs-target="#editRegNoModal" title="Edit Registration Number"><i class="fas fa-edit small text-secondary"></i></button>
                        </div>
                        <?php if ($e['fee_balance_note'] && strncmp($e['fee_balance_note'], 'Transferred', 11) === 0): ?><div class="text-muted small"><?php echo htmlspecialchars($e['fee_balance_note']); ?></div><?php endif; ?></td>
                    <td class="text-end"><?php echo number_format((float)$e['paid']); ?></td>
                    <td class="text-end fw-bold <?php echo $e['bal'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($e['bal']); ?></td>
                    <td class="text-end"><?php if ($available): ?><button class="btn btn-sm btn-outline-primary js-transfer" data-from="<?php echo (int)$e['id']; ?>" data-title="<?php echo htmlspecialchars((string)$e['course_title'], ENT_QUOTES); ?>" data-bs-toggle="modal" data-bs-target="#transferModal">Transfer</button><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </div>
        <?php $certArea = 'admin'; require __DIR__ . '/../../components/certificates/student_card.php'; ?>
        <div class="card mb-3"><div class="card-header fw-semibold">Where they learn</div><div class="card-body">
            <?php if (!study_location_supported($pdo)): ?><p class="small text-muted mb-0">Apply the pending system update to record learning locations.</p><?php else: foreach ($enrols as $e): ?>
            <form method="POST" class="row g-2 align-items-end mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="set_study"><input type="hidden" name="study_reg_id" value="<?php echo (int)$e['id']; ?>">
                <div class="col-12 small fw-semibold"><?php echo htmlspecialchars((string)$e['course_title']); ?></div>
                <?php echo study_placement_fields($e, true, 'col-6 col-sm-4'); ?>
                <div class="col-12 col-sm-4"><button class="btn btn-sm btn-outline-primary w-100">Save</button></div>
            </form>
            <?php endforeach; endif; ?>
        </div></div>
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
<div class="modal fade" id="editDetailsModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="update_contact">
    <div class="modal-header"><h5 class="modal-title">Edit details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-2"><label class="form-label small">Full name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars((string)$reg['full_name'], ENT_QUOTES); ?>" required></div>
        <div class="mb-2"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string)$reg['email'], ENT_QUOTES); ?>" required></div>
        <div class="mb-2"><label class="form-label small">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars((string)$reg['phone'], ENT_QUOTES); ?>" required></div>
        <div class="mb-2"><label class="form-label small">Secondary phone</label><input type="text" name="secondary_phone" class="form-control" value="<?php echo htmlspecialchars((string)($reg['secondary_phone'] ?? ''), ENT_QUOTES); ?>"></div>
        <hr>
        <p class="small text-muted mb-2">Parent/guardian details — optional, can be filled in later.</p>
        <div class="mb-2"><label class="form-label small">Parent/guardian phone</label><input type="text" name="parent_phone" class="form-control" value="<?php echo htmlspecialchars((string)$parent['parent_phone'], ENT_QUOTES); ?>"></div>
        <div class="mb-0"><label class="form-label small">Parent/guardian email</label><input type="email" name="parent_email" class="form-control" value="<?php echo htmlspecialchars((string)$parent['parent_email'], ENT_QUOTES); ?>"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div>
<div class="modal fade" id="recordPaymentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="record_payment">
    <div class="modal-header"><h5 class="modal-title">Record a payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Use this for cash or other payments made outside the online payment flow. It is recorded as approved immediately.</p>
        <div class="mb-2"><label class="form-label small">Course</label>
            <select name="payment_reg_id" class="form-select" required>
                <?php foreach ($enrols as $e): ?><option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars((string)$e['course_title']); ?> (balance <?php echo $k($e['bal']); ?>)</option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label small">Amount (KES)</label><input type="number" name="amount" class="form-control" min="1" step="0.01" required></div>
        <div class="mb-2"><label class="form-label small">Reference</label><input type="text" name="reference" class="form-control" placeholder="e.g. Cash, receipt no."></div>
        <div class="mb-0"><label class="form-label small">Notes (optional)</label><input type="text" name="notes" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-success">Record payment</button></div></form></div></div>
<div class="modal fade" id="editPaymentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content" onsubmit="return confirm('Correct this payment amount? The balance and receipt will be recalculated and the corrected receipt re-emailed to the student.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="edit_payment"><input type="hidden" name="payment_id" id="epPaymentId">
    <div class="modal-header"><h5 class="modal-title">Correct payment amount</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Reference: <strong id="epRef"></strong>. Use this when a payment was recorded incorrectly (e.g. 20,000 entered instead of 30,000). Balances and the receipt update automatically, and the corrected receipt is emailed to the student.</p>
        <div class="mb-0"><label class="form-label small">Correct amount (KES)</label><input type="number" name="new_amount" id="epAmount" class="form-control" min="0.01" step="0.01" required></div>
    </div>
    <div class="modal-footer"><button class="btn btn-warning">Save correction</button></div></form></div></div>
<script>document.querySelectorAll('.js-edit-payment').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('epPaymentId').value = b.dataset.id; document.getElementById('epAmount').value = b.dataset.amount; document.getElementById('epRef').textContent = b.dataset.ref; }); });</script>
<?php if ($available): ?>
<div class="modal fade" id="addCourseModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_course">
    <div class="modal-header"><h5 class="modal-title">Add a course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small text-muted">Enrols <?php echo htmlspecialchars($reg['full_name']); ?> in another course on the same account. Their balance updates to include that course.</p>
        <select name="course_id" class="form-select" required><option value="">Choose a course…</option><?php foreach ($available as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select></div>
    <div class="row g-2 px-3 pb-3"><?php echo study_placement_fields($reg, true, 'col-6'); ?></div>
    <div class="modal-footer"><button class="btn btn-success">Add course</button></div></form></div></div>
<div class="modal fade" id="transferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content" onsubmit="return confirm('Transfer this student? Their balance will follow the new course and progress restarts at month 1.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="transfer_course"><input type="hidden" name="from_reg_id" id="trFrom">
    <div class="modal-header"><h5 class="modal-title">Transfer course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small text-muted">Move from <strong id="trTitle"></strong> to another course. Payments already made are kept; the balance follows the new course.</p>
        <select name="course_id" class="form-select" required><option value="">Transfer to…</option><?php foreach ($available as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?></select></div>
    <div class="row g-2 px-3 pb-3">
        <?php echo study_placement_fields([], false, 'col-6', true); ?>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Transfer</button></div></form></div></div>
<script>document.querySelectorAll('.js-transfer').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('trFrom').value = b.dataset.from; document.getElementById('trTitle').textContent = b.dataset.title; }); });</script>
<?php endif; ?>

<div class="modal fade" id="editRegNoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit_reg_no">
            <input type="hidden" name="edit_reg_id" id="editRegId">
            <div class="modal-header">
                <h5 class="modal-title">Edit Registration Number</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Overriding the registration number updates it here and updates the student's username for logging into the portal.</p>
                <div class="mb-3">
                    <label class="form-label">Registration Number</label>
                    <input type="text" class="form-control" name="new_reg_no" id="newRegNoInput" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.js-edit-reg-no').forEach(function(b) {
    b.addEventListener('click', function() {
        document.getElementById('editRegId').value = b.dataset.id;
        document.getElementById('newRegNoInput').value = b.dataset.regno;
    });
});
</script>

<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
