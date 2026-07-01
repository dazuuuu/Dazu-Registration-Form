<?php
// public/admin/students/view.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;
use Models\RegistrationModel;
use Controllers\StudentController;

Middleware::requireAdmin();

$pdo          = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);

$id  = (int) ($_GET['id'] ?? 0);
$reg = $studentModel->getById($id);

if (!$reg || $reg['status'] !== 'approved') {
    SessionModel::setFlash('error', 'Student not found.');
    header('Location: ' . url('admin/students/index.php'));
    exit();
}

$payments  = $paymentModel->listByRegistration($id);
$totalPaid = $paymentModel->getTotalApproved($id);
$totalOwed = $studentModel->totalOwed($reg);
$balance   = max(0, $totalOwed - $totalPaid);
$installments = RegistrationModel::calculateInstallments(
    (float) $reg['course_fee'],
    $reg['payment_plan'],
    (int) $reg['duration_months']
);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $action = $_POST['action'] ?? '';
        $controller = new StudentController();

        if ($action === 'send_credentials') {
            $result = $controller->sendCredentials($id);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Login credentials have been emailed to the student.');
                header('Location: /Registration-System/public/admin/students/view.php?id=' . $id);
                exit();
            }
            $error = $result['error'];
        } elseif ($action === 'unlock_portal') {
            $studentModel->unlockPortal($id);
            SessionModel::setFlash('success', 'Student portal unlocked.');
            header('Location: /Registration-System/public/admin/students/view.php?id=' . $id);
            exit();
        } elseif ($action === 'lock_portal') {
            $studentModel->lockPortal($id);
            SessionModel::setFlash('success', 'Student portal locked.');
            header('Location: /Registration-System/public/admin/students/view.php?id=' . $id);
            exit();
        }
    }
    $reg = $studentModel->getById($id);
}

$page_title  = $reg['full_name'];
$breadcrumbs = [
    ['label' => 'Students', 'url' => url('admin/students/index.php')],
    ['label' => $reg['full_name'], 'active' => true],
];

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="alert alert-<?php echo $reg['portal_locked'] ? 'danger' : 'success'; ?> d-flex align-items-center gap-3">
            <i class="fas fa-<?php echo $reg['portal_locked'] ? 'lock' : 'unlock'; ?> fa-2x"></i>
            <div>
                <strong>Portal Status: <?php echo $reg['portal_locked'] ? 'Locked' : 'Active'; ?></strong>
                <?php if ($reg['next_payment_due']): ?>
                    <br><small>Next payment due: <?php echo date('d M Y', strtotime($reg['next_payment_due'])); ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Student Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Student ID</small>
                        <strong><?php echo htmlspecialchars($reg['registration_no']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Username</small>
                        <strong><?php echo htmlspecialchars($reg['username']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Full Name</small>
                        <?php echo htmlspecialchars($reg['full_name']); ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Email</small>
                        <a href="mailto:<?php echo htmlspecialchars($reg['email']); ?>"><?php echo htmlspecialchars($reg['email']); ?></a>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Phone</small>
                        <?php echo htmlspecialchars($reg['phone']); ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Study Mode</small>
                        <?php echo ucfirst($reg['study_mode']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Course & Payments</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Course</small>
                        <strong><?php echo htmlspecialchars($reg['course_title']); ?></strong>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Payment Plan</small>
                        <?php echo htmlspecialchars($reg['payment_plan']); ?>
                        (<?php echo (int) $reg['installments_paid']; ?> / <?php echo $installments['count']; ?> paid)
                    </div>
                </div>
                <table class="table table-sm table-bordered mb-0">
                    <tr><td>Total Owed</td><td class="text-end">KSh <?php echo number_format($totalOwed); ?></td></tr>
                    <tr><td>Total Paid</td><td class="text-end text-success">KSh <?php echo number_format($totalPaid); ?></td></tr>
                    <tr class="fw-bold"><td>Balance</td><td class="text-end <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo $balance > 0 ? 'KSh ' . number_format($balance) : 'Fully Paid'; ?>
                    </td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Payment History</div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                    <p class="text-muted p-3 mb-0">No payments recorded.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($p['paid_at'])); ?></td>
                                <td>KSh <?php echo number_format($p['amount']); ?></td>
                                <td><?php echo htmlspecialchars($p['payer_reference']); ?></td>
                                <td><span class="badge bg-<?php echo ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$p['status']]; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                <td><?php echo $p['receipt_no'] ? htmlspecialchars($p['receipt_no']) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white fw-semibold">
                <i class="fas fa-envelope me-1"></i> Send Credentials
            </div>
            <div class="card-body">
                <p class="text-muted small">Generate a new temporary password and email login credentials to the student.</p>
                <?php if ($reg['credentials_sent_at']): ?>
                    <p class="small text-success mb-3">
                        <i class="fas fa-check"></i> Last sent: <?php echo date('d M Y H:i', strtotime($reg['credentials_sent_at'])); ?>
                    </p>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Send new login credentials to this student?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="send_credentials">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-1"></i> Email Credentials
                    </button>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Portal Controls</div>
            <div class="card-body d-grid gap-2">
                <?php if ($reg['portal_locked']): ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="unlock_portal">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-unlock me-1"></i> Unlock Portal
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('Lock this student portal?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="lock_portal">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-lock me-1"></i> Lock Portal
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <a href="<?php echo url('admin/registrations/view.php?id=' . (int)$id); ?>"
           class="btn btn-link btn-sm ps-0">
            <i class="fas fa-arrow-left me-1"></i> View Registration
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
