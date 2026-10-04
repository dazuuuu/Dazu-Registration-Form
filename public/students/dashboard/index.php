<?php
// public/students/dashboard/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
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

// Fetch Tutor, Timeline, and Announcements
$stmt = $pdo->prepare("SELECT ts.*,
                              COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS tutor_name,
                              COALESCE(NULLIF(t.phone, ''), up.phone) AS tutor_phone, u.email AS tutor_email
                       FROM tutor_students ts
                       JOIN tutors t ON ts.tutor_id = t.id
                       JOIN users u ON u.id = t.user_id
                       LEFT JOIN user_profiles up ON up.user_id = u.id
                       WHERE ts.user_id = ? ORDER BY ts.assigned_at DESC LIMIT 1");
$stmt->execute([$userId]);
$tutorData = $stmt->fetch(\PDO::FETCH_ASSOC);

$announcements = [];
if ($tutorData) {
    $stmt = $pdo->prepare("SELECT * FROM tutor_announcements WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $announcements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Mark as read after fetching for popup logic
    $pdo->prepare("UPDATE tutor_announcements SET is_read = 1 WHERE student_id = ?")->execute([$userId]);
}

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
            SessionModel::setFlash('success', 'Payment submitted. Your portal will update once admin verifies the payment.');
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
$pwaPortal = 'students'; require __DIR__ . '/../../components/pwa/banner.php';
$docsLink = url('students/documents/index.php'); require __DIR__ . '/../../components/documents/missing_banner.php';
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
    <div class="col-md-4"><div class="card-student h-100"><div class="card-body text-center py-4 d-flex flex-column justify-content-center">
        <div style="font-size:3rem;font-weight:700;color:var(--brand);"><?php echo $learnStats['progress_pct']; ?>%</div>
        <div class="text-muted">Units completed (<?php echo $learnStats['units_done']; ?> / <?php echo $learnStats['units_total']; ?>)</div>
        
        <?php if ($tutorData && $tutorData['timeline_days'] > 0): 
            $startDate = new DateTime($tutorData['start_date']);
            $endDate = clone $startDate;
            $endDate->modify('+' . $tutorData['timeline_days'] . ' days');
            $now = new DateTime();
            $daysLeft = $now->diff($endDate)->format('%r%a');
            $timelinePct = min(100, max(0, ($tutorData['timeline_days'] - $daysLeft) / $tutorData['timeline_days'] * 100));
        ?>
        <hr class="my-3 border-light">
        <div class="text-start">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Study Timeline</span>
                <span class="<?php echo $daysLeft < 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo $daysLeft < 0 ? 'Overdue by ' . abs((int)$daysLeft) . ' days' : $daysLeft . ' days remaining'; ?></span>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-<?php echo $daysLeft < 10 ? 'warning' : 'info'; ?>" style="width: <?php echo $timelinePct; ?>%"></div>
            </div>
            <div class="text-muted small mt-1 text-center">Goal: <?php echo $endDate->format('d M Y'); ?></div>
        </div>
        <?php endif; ?>

        <a href="<?php echo url('students/course/index.php'); ?>" class="btn btn-brand mt-auto w-100">Continue learning</a>
    </div></div></div>
</div>

<?php if ($tutorData): ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-student h-100">
            <div class="card-head bg-light"><i class="fas fa-chalkboard-teacher me-2 text-brand"></i>Your Tutor</div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <div class="student-avatar" style="width:50px;height:50px;font-size:20px;">
                            <?php echo substr($tutorData['tutor_name'] ?? 'T', 0, 1); ?>
                        </div>
                    </div>
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($tutorData['tutor_name']); ?></h5>
                        <p class="mb-0 text-muted small"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($tutorData['tutor_email']); ?></p>
                        <p class="mb-0 text-muted small"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($tutorData['tutor_phone']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($announcements)): ?>
    <div class="col-md-6">
        <div class="card-student h-100">
            <div class="card-head bg-light d-flex justify-content-between align-items-center">
                <span><i class="fas fa-bullhorn me-2 text-warning"></i>Announcements</span>
                <span class="badge bg-danger rounded-pill"><?php echo count(array_filter($announcements, fn($a) => $a['is_read'] == 0)); ?> New</span>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="list-group-item <?php echo $ann['is_read'] == 0 ? 'bg-light' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 <?php echo $ann['is_read'] == 0 ? 'fw-bold' : ''; ?>"><?php echo htmlspecialchars($ann['title']); ?></h6>
                                <small class="text-muted"><?php echo date('M d', strtotime($ann['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 small text-muted"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php 
// Show popup for unread announcements
$unreadCount = count(array_filter($announcements, fn($a) => $a['is_read'] == 0));
if ($unreadCount > 0):
?>
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header bg-warning bg-opacity-10 border-0">
                    <h5 class="modal-title text-warning-dark fw-bold"><i class="fas fa-bell me-2"></i>You have <?php echo $unreadCount; ?> new announcement<?php echo $unreadCount > 1 ? 's' : ''; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php foreach (array_filter($announcements, fn($a) => $a['is_read'] == 0) as $ann): ?>
                        <div class="mb-3 border-bottom pb-2">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($ann['title']); ?> <small class="text-muted float-end"><?php echo date('M d', strtotime($ann['created_at'])); ?></small></h6>
                            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-brand w-100" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>
    <?php $extra_js = ($extra_js ?? '') . "<script>document.addEventListener('DOMContentLoaded', function() { var myModal = new bootstrap.Modal(document.getElementById('announcementModal')); myModal.show(); });</script>"; ?>
<?php endif; ?>
<?php endif; ?>

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

<?php if (!$portal_locked): ?>
<div class="card-student">
    <div class="card-head"><i class="fas fa-share-nodes me-2"></i>Share This Course</div>
    <div class="card-body">
        <p class="text-muted small mb-3">Invite friends to join <strong><?php echo htmlspecialchars($reg['course_title']); ?></strong>. They'll see the course details and can register directly.</p>
        <?php
        $share_url = course_share_url((int) $reg['course_id'], $reg['registration_no'] ?? null);
        $share_title = 'Join me at Dazu Training Institute — ' . ($reg['course_title'] ?? 'tech course');
        $input_id = 'studentCourseShare';
        ?>
        <div class="input-group mb-2">
            <input type="text" class="form-control form-control-sm" id="<?php echo $input_id; ?>" readonly value="<?php echo htmlspecialchars($share_url); ?>">
            <button type="button" class="btn btn-brand btn-sm dh-share-copy" data-target="<?php echo $input_id; ?>"><i class="fas fa-copy"></i> Copy</button>
            <a href="https://wa.me/?text=<?php echo rawurlencode(course_share_invite_text(['title' => $reg['course_title'] ?? 'tech course', 'overview' => ''], $share_url, $share_title)); ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
        </div>
        <a href="<?php echo htmlspecialchars($share_url); ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-external-link-alt me-1"></i> Preview share page
        </a>
    </div>
</div>
<script>
document.querySelectorAll('.dh-share-copy').forEach(function(btn){
    btn.addEventListener('click', function(){
        var inp = document.getElementById(btn.getAttribute('data-target'));
        if(!inp) return;
        inp.select();
        (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.resolve(document.execCommand('copy')))
            .then(function(){ btn.innerHTML='<i class="fas fa-check"></i> Copied'; setTimeout(function(){ btn.innerHTML='<i class="fas fa-copy"></i> Copy'; }, 2000); });
    });
});
</script>
<?php endif; ?>

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
