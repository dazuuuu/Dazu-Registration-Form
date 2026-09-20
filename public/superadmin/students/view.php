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

use Models\Connection;
use Models\RegistrationModel;
use Models\PayrollModel;
use Controllers\StudentController;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
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
    if ($action === 'assign_tutor' && $payroll->isInstalled() && $reg['user_id']) {
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
$pay = $pdo->prepare("SELECT amount, status, payer_reference, COALESCE(reviewed_at, paid_at) AS d FROM payments WHERE registration_id = ? ORDER BY d DESC");
$pay->execute([$id]);
$payments = $pay->fetchAll();
$paid = 0.0;
foreach ($payments as $p) if ($p['status'] === 'approved') $paid += (float)$p['amount'];
$fee = (float)$reg['course_fee'] + ($reg['registration_fee_waived'] ? 0 : (float)$reg['registration_fee']);
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
                <div class="col-sm-6"><div class="text-muted">Course</div><div><?php echo htmlspecialchars((string)($reg['course_title'] ?? '')); ?></div></div>
                <div class="col-sm-6"><div class="text-muted">Tutor</div><div><?php echo $myTutors ? htmlspecialchars(implode(', ', array_column($myTutors, 'full_name'))) : '<span class="text-muted">None yet</span>'; ?></div></div>
            </div>
            <?php if ($sms): ?><a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo url('superadmin/sms/students/view.php?id=' . $sms); ?>">Open fee record</a><?php endif; ?>
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
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
