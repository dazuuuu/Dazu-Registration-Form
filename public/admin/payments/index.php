<?php
// public/admin/payments/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\PaymentModel;
use Controllers\StudentController;

Middleware::requireAdmin();

$pdo          = Connection::getInstance()->getConnection();
$paymentModel = new PaymentModel($pdo);
$pending      = $paymentModel->listPending();
$error        = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $action    = $_POST['action'] ?? '';
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $adminId   = (int) SessionModel::get('user_id');
        $controller = new StudentController();

        if ($action === 'approve') {
            $result = $controller->approvePayment($paymentId, $adminId);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Payment approved. Receipt <strong>' . htmlspecialchars($result['receipt_no']) . '</strong> emailed to student.');
                header('Location: ' . url('admin/payments/index.php'));
                exit();
            }
            $error = $result['error'];
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            $result = $controller->rejectPayment($paymentId, $adminId, $reason);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Payment rejected. Student has been notified.');
                header('Location: ' . url('admin/payments/index.php'));
                exit();
            }
            $error = $result['error'];
        }
        $pending = $paymentModel->listPending();
    }
}

$page_title  = 'Pending Payments';
$breadcrumbs = [['label' => 'Payments', 'active' => true]];

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="fas fa-money-bill-wave me-2"></i>Pending Payment Verifications</span>
        <?php if (count($pending) > 0): ?>
            <span class="badge bg-warning text-dark"><?php echo count($pending); ?> pending</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pending)): ?>
            <p class="text-muted p-4 mb-0">No payments awaiting verification.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Submitted</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Amount</th>
                        <th>M-Pesa Code</th>
                        <th>Payer Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                    <tr>
                        <td><?php echo date('d M Y H:i', strtotime($p['paid_at'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['registration_no'] ?? '—'); ?></strong><br>
                            <small><?php echo htmlspecialchars($p['full_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($p['course_title']); ?></td>
                        <td><strong>KSh <?php echo number_format($p['amount']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($p['mpesa_code'] ?? ''); ?></code></td>
                        <td><?php echo htmlspecialchars($p['payer_reference']); ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve this payment? A receipt will be emailed to the student.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                        data-bs-target="#rejectModal<?php echo (int) $p['id']; ?>">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>

                            <div class="modal fade" id="rejectModal<?php echo (int) $p['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Payment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="form-label">Reason for rejection</label>
                                                <textarea name="rejection_reason" class="form-control" rows="3" required
                                                          placeholder="e.g. M-Pesa code could not be verified on NCBA statement."></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Reject Payment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
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
require __DIR__ . '/../../templates/admin/layout.php';
