<?php
// public/students/payments/index.php
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

Middleware::requireStudent();

$pdo          = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);
$userId       = (int) SessionModel::get('user_id');

$reg = $studentModel->getByUserId($userId);
if (!$reg) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}

$studentModel->ensurePortalStatus($reg);
$reg = $studentModel->getByUserId($userId);
$portal_locked = (bool) $reg['portal_locked'];

if ($portal_locked) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}

$payments   = $paymentModel->listByRegistration((int) $reg['id']);
$hasPending = $paymentModel->hasPending((int) $reg['id']);
$nextAmount = $studentModel->nextInstallmentAmount($reg);
$totalPaid  = $paymentModel->getTotalApproved((int) $reg['id']);
$totalOwed  = $studentModel->totalOwed($reg);
$balance    = max(0, $totalOwed - $totalPaid);
$error      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please refresh and try again.';
    } else {
        $controller = new StudentController();
        $result = $controller->submitPayment(
            (int) $reg['id'],
            $userId,
            $_POST['mpesa_code'] ?? '',
            $_POST['payer_reference'] ?? ''
        );
        if ($result['ok']) {
            SessionModel::setFlash('success', 'Payment submitted. Your portal will update once admin verifies the payment.');
            header('Location: ' . url('students/payments/index.php'));
            exit();
        }
        $error = $result['error'];
    }
}

$page_title = 'Payments';
ob_start();
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="label">Total Paid</div>
            <div class="value" style="font-size:1.2rem;">KSh <?php echo number_format($totalPaid); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="label">Balance Remaining</div>
            <div class="value" style="font-size:1.2rem;color:<?php echo $balance > 0 ? '#dc2626' : '#16a34a'; ?>">
                <?php echo $balance > 0 ? 'KSh ' . number_format($balance) : 'Fully Paid'; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="label">Next Installment</div>
            <div class="value" style="font-size:1.2rem;"><?php echo $nextAmount > 0 ? 'KSh ' . number_format($nextAmount) : '—'; ?></div>
        </div>
    </div>
</div>

<?php if ($nextAmount > 0 && !$hasPending): ?>
<div class="card-student">
    <div class="card-head"><i class="fas fa-mobile-alt me-2"></i>Submit Payment</div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="alert alert-light border mb-3">
            <strong>Amount:</strong> KSh <?php echo number_format($nextAmount); ?><br>
            <strong>Student ID:</strong> <?php echo htmlspecialchars($reg['registration_no']); ?>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">M-Pesa Confirmation Code</label>
                    <input type="text" name="mpesa_code" class="form-control" required placeholder="e.g. QGH7XXXXXX">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Name / Phone used to pay</label>
                    <input type="text" name="payer_reference" class="form-control" required
                           value="<?php echo htmlspecialchars($reg['full_name'] . ', ' . $reg['phone']); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-brand mt-3">
                <i class="fas fa-paper-plane me-1"></i> Submit Payment
            </button>
        </form>
    </div>
</div>
<?php elseif ($hasPending): ?>
<div class="alert alert-info">
    <i class="fas fa-hourglass-half me-2"></i> You have a payment awaiting admin verification.
</div>
<?php endif; ?>

<div class="card-student">
    <div class="card-head"><i class="fas fa-history me-2"></i>Payment History</div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <p class="text-muted p-4 mb-0">No payments recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
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
                        <td>
                            <span class="badge bg-<?php echo ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$p['status']] ?? 'secondary'; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($p['status'] === 'approved' && !empty($p['receipt_no'])): ?>
                                <a href="<?php echo url('students/payments/receipt.php?id=' . (int)$p['id']); ?>"
                                   class="btn btn-sm btn-outline-secondary" target="_blank" title="View receipt">
                                    <i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($p['receipt_no']); ?>
                                </a>
                                <a href="<?php echo url('students/payments/receipt.php?id=' . (int)$p['id'] . '&download=1'); ?>"
                                   class="btn btn-sm btn-outline-success" title="Download receipt">
                                    <i class="fas fa-download"></i>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
