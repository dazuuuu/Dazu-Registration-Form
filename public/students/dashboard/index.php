<?php
// public/students/dashboard/index.php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';

use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;
use Models\RegistrationModel;
use Models\DashboardModel;
use Controllers\StudentController;

Middleware::requireStudent();

$pdo          = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);
$userId       = (int) SessionModel::get('user_id');

$reg = $studentModel->getByUserId($userId);
if (!$reg) {
    SessionModel::setFlash('error', 'No approved registration found for your account. Please contact admin.');
    header('Location: ' . url('auth/logout.php'));
    exit();
}

$justLocked = $studentModel->ensurePortalStatus($reg);
if ($justLocked) {
    $reg = $studentModel->getByUserId($userId);
    $notifier = new StudentNotifier($pdo);
    $amountDue = $studentModel->nextInstallmentAmount($reg);
    $notifier->sendPortalLocked($reg, $amountDue);
}

$portal_locked = (bool) $reg['portal_locked'];
$totalPaid     = $paymentModel->getTotalApproved((int) $reg['id']);
$totalOwed     = $studentModel->totalOwed($reg);
$balance       = max(0, $totalOwed - $totalPaid);
$hasPending    = $paymentModel->hasPending((int) $reg['id']);
$nextAmount    = $studentModel->nextInstallmentAmount($reg);
$installments  = RegistrationModel::calculateInstallments(
    (float) $reg['course_fee'],
    $reg['payment_plan'],
    (int) $reg['duration_months']
);
$learnStats = (new DashboardModel($pdo))->studentStats((int)$reg['id'], (int)$reg['course_id']);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $portal_locked) {
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
            SessionModel::setFlash('success', 'Payment submitted successfully. You will receive a confirmation email once admin verifies your payment.');
            header('Location: ' . url('students/dashboard/index.php'));
            exit();
        }
        $error = $result['error'];
    }
}

$planLabels = [
    'monthly'  => 'Monthly Installments',
    '3months'  => '3-Month Plan',
    'full'     => 'Full Payment',
];

$page_title = 'Dashboard';
ob_start();
?>

<?php if ($portal_locked): ?>

<div class="locked-banner">
    <i class="fas fa-lock d-block"></i>
    <h4 class="mb-2">Portal Access Suspended</h4>
    <p class="mb-0 opacity-90">Your next installment of <strong>KSh <?php echo number_format($nextAmount); ?></strong> is due.
    Submit your M-Pesa payment code below to restore access.</p>
</div>

<?php if ($hasPending): ?>
    <div class="alert alert-info">
        <i class="fas fa-hourglass-half me-2"></i>
        You have a payment awaiting admin verification. Your portal will unlock once it is approved.
    </div>
<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card-student">
        <div class="card-head"><i class="fas fa-mobile-alt me-2"></i>Submit School Fees Payment</div>
        <div class="card-body">
            <div class="alert alert-light border mb-3">
                <strong>Amount due:</strong> KSh <?php echo number_format($nextAmount); ?><br>
                <strong>Paybill:</strong> NCBA Paybill &middot; Account: Your Student ID (<?php echo htmlspecialchars($reg['registration_no']); ?>)
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="mb-3">
                    <label class="form-label">M-Pesa Confirmation Code</label>
                    <input type="text" name="mpesa_code" class="form-control" required
                           placeholder="e.g. QGH7XXXXXX">
                </div>
                <div class="mb-3">
                    <label class="form-label">Name / Phone used to pay</label>
                    <input type="text" name="payer_reference" class="form-control" required
                           placeholder="e.g. John Mwangi, 07XXXXXXXX"
                           value="<?php echo htmlspecialchars($reg['full_name'] . ', ' . $reg['phone']); ?>">
                </div>
                <button type="submit" class="btn btn-brand w-100 py-2">
                    <i class="fas fa-paper-plane me-1"></i> Submit Payment for Verification
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php else: ?>

<div class="row g-3 mb-4">
    <div class="col-md-8"><div class="card-student"><div class="card-head">Learning progress</div><div class="card-body"><canvas id="studentProgress" height="120"></canvas></div></div></div>
    <div class="col-md-4"><div class="card-student"><div class="card-body text-center py-4">
        <div style="font-size:3rem;font-weight:700;color:var(--brand);"><?php echo $learnStats['progress_pct']; ?>%</div>
        <div class="text-muted">Units completed (<?php echo $learnStats['units_done']; ?> / <?php echo $learnStats['units_total']; ?>)</div>
        <a href="<?php echo url('students/course/index.php'); ?>" class="btn btn-brand mt-3 w-100">Continue learning</a>
    </div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="label">Student ID</div>
            <div class="value" style="font-size:1rem;"><?php echo htmlspecialchars($reg['registration_no']); ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="label">Installments Paid</div>
            <div class="value"><?php echo (int) $reg['installments_paid']; ?> / <?php echo $installments['count']; ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="label">Total Paid</div>
            <div class="value" style="font-size:1.1rem;">KSh <?php echo number_format($totalPaid); ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="label">Balance</div>
            <div class="value" style="font-size:1.1rem;color:<?php echo $balance > 0 ? '#dc2626' : '#16a34a'; ?>">
                <?php echo $balance > 0 ? 'KSh ' . number_format($balance) : 'Paid'; ?>
            </div>
        </div>
    </div>
</div>

<div class="card-student">
    <div class="card-head"><i class="fas fa-book me-2"></i>My Course</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <small class="text-muted d-block">Course</small>
                <strong class="fs-5"><?php echo htmlspecialchars($reg['course_title']); ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Study Mode</small>
                <strong><?php echo $reg['study_mode'] === 'physical' ? '🏫 Physical' : '💻 Online'; ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Payment Plan</small>
                <span class="badge badge-plan"><?php echo $planLabels[$reg['payment_plan']] ?? $reg['payment_plan']; ?></span>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Course Fee</small>
                <strong>KSh <?php echo number_format($reg['course_fee']); ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Duration</small>
                <strong><?php echo (int) $reg['duration_months']; ?> months</strong>
            </div>
            <?php if ($reg['next_payment_due'] && $reg['payment_plan'] !== 'full'): ?>
            <div class="col-md-4">
                <small class="text-muted d-block">Next Payment Due</small>
                <strong class="<?php echo strtotime($reg['next_payment_due']) < time() ? 'text-danger' : ''; ?>">
                    <?php echo date('d M Y', strtotime($reg['next_payment_due'])); ?>
                </strong>
            </div>
            <?php endif; ?>
            <div class="col-12 mt-2">
                <a href="<?php echo url('students/course/index.php'); ?>" class="btn btn-brand">
                    <i class="fas fa-book-open me-1"></i> Open Course & Learning Materials
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($reg['payment_plan'] !== 'full' && $nextAmount > 0 && !$hasPending): ?>
<div class="card-student">
    <div class="card-head"><i class="fas fa-credit-card me-2"></i>Make a Payment</div>
    <div class="card-body">
        <p>Next installment: <strong>KSh <?php echo number_format($nextAmount); ?></strong></p>
        <a href="<?php echo url('students/payments/index.php'); ?>" class="btn btn-brand">
            <i class="fas fa-money-bill-wave me-1"></i> Go to Payments
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($hasPending): ?>
<div class="alert alert-warning">
    <i class="fas fa-hourglass-half me-2"></i> You have a payment awaiting admin verification.
</div>
<?php endif; ?>

<?php endif; ?>

<?php
if (!$portal_locked && $learnStats['units_total'] > 0) {
    $extra_js = ($extra_js ?? '') . '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
    new Chart(document.getElementById("studentProgress"),{type:"doughnut",data:{
      labels:["Completed","Remaining"],
      datasets:[{data:[' . (int)$learnStats['units_done'] . ',' . max(0, $learnStats['units_total'] - $learnStats['units_done']) . '],backgroundColor:["#0D9488","#e5e7eb"]}]
    },options:{plugins:{legend:{position:"bottom"}}}});
    </script>';
}
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
