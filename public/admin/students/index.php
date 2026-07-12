<?php
// public/admin/students/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';

use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;

Middleware::requireAdmin();

$pdo          = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);
$students     = $studentModel->listApproved();

$page_title  = 'Students';
$breadcrumbs = [['label' => 'Students', 'active' => true]];

ob_start();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="fas fa-user-graduate me-2"></i>Enrolled Students</span>
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo url('admin/students/register_continuing.php'); ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-user-plus me-1"></i> Register Continuing Student
            </a>
            <span class="badge bg-secondary"><?php echo count($students); ?> total</span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <p class="text-muted p-4 mb-0">No approved students yet. Approve registrations from the Pending Approvals page.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Month</th>
                        <th>Plan</th>
                        <th>Portal</th>
                        <th>Credentials</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s):
                        $totalPaid = $paymentModel->getTotalApproved((int) $s['id']);
                        $totalOwed = $studentModel->totalOwed($s);
                        $balance   = max(0, $totalOwed - $totalPaid);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($s['registration_no']); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($s['full_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($s['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($s['course_title']); ?></td>
                        <td>
                            <?php
                            $month = (int) ($s['current_learning_month'] ?? 1);
                            echo 'M' . $month;
                            if (!empty($s['enrollment_type']) && $s['enrollment_type'] === 'continuing'): ?>
                                <br><small class="text-muted">Continuing</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($s['payment_plan']); ?></span></td>
                        <td>
                            <?php if ($s['portal_locked']): ?>
                                <span class="badge bg-danger">Locked</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                            <?php if ($balance > 0): ?>
                                <br><small class="text-muted">Bal: KSh <?php echo number_format($balance); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['credentials_sent_at']): ?>
                                <small class="text-success" title="<?php echo $s['credentials_sent_at']; ?>">
                                    <i class="fas fa-check"></i> Sent
                                </small>
                            <?php else: ?>
                                <small class="text-warning">Not sent</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo url('admin/students/view.php?id=' . (int)$s['id']); ?>"
                               class="btn btn-sm btn-outline-primary">View</a>
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
