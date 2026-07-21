<?php
// public/admin/students/onboard.php — Manual onboarding for existing learners
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/ManualOnboardController.php';
require_once __DIR__ . '/../../../app/v1/controllers/ContinuingStudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\CourseModel;
use Models\PaymentModel;
use Controllers\ManualOnboardController;
use Controllers\ContinuingStudentController;

Middleware::requireAdmin();

$tab = ($_GET['tab'] ?? 'new') === 'existing' ? 'existing' : 'new';
$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$courseModel = new CourseModel($pdo);
$paymentModel = new PaymentModel($pdo);

$students = $studentModel->listApproved();
$courses = array_values(array_filter($courseModel->getAll(), static fn($c) => !empty($c['is_active'])));

$error = null;
$credentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $mode = $_POST['onboard_mode'] ?? 'new';
        $adminId = (int) SessionModel::get('user_id');
        $payload = [
            'course_id'               => (int) ($_POST['course_id'] ?? 0),
            'study_mode'              => $_POST['study_mode'] ?? 'online',
            'payment_plan'            => $_POST['payment_plan'] ?? 'monthly',
            'current_learning_month'  => (int) ($_POST['current_learning_month'] ?? 1),
            'installments_paid'       => (int) ($_POST['installments_paid'] ?? 0),
            'amount_paid_prior'       => (float) ($_POST['amount_paid_prior'] ?? 0),
            'registration_fee_waived' => !empty($_POST['registration_fee_waived']),
            'portal_locked'           => !empty($_POST['portal_locked']),
            'fee_balance_note'        => trim($_POST['fee_balance_note'] ?? ''),
            'fee_status'              => $_POST['fee_status'] ?? 'current',
            'payment_reference'       => trim($_POST['payment_reference'] ?? ''),
        ];

        if ($mode === 'existing') {
            $payload['prior_registration_id'] = (int) ($_POST['prior_registration_id'] ?? 0);
            $result = (new ContinuingStudentController())->register($payload, $adminId);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Continuing student enrolled as <strong>' . htmlspecialchars($result['reg_no']) . '</strong>.');
                header('Location: ' . url('admin/students/view.php?id=' . (int) $result['registration_id']));
                exit();
            }
            $error = $result['error'];
            $tab = 'existing';
        } else {
            $payload['full_name'] = trim($_POST['full_name'] ?? '');
            $payload['email'] = trim($_POST['email'] ?? '');
            $payload['phone'] = trim($_POST['phone'] ?? '');
            $result = (new ManualOnboardController())->onboardNew($payload, $adminId);
            if ($result['ok']) {
                $credentials = [
                    'reg_no'        => $result['reg_no'],
                    'login_email'   => $result['login_email'],
                    'username'      => $result['reg_no'],
                    'temp_password' => $result['temp_password'],
                ];
                SessionModel::setFlash('success', 'Student onboarded as <strong>' . htmlspecialchars($result['reg_no']) . '</strong>. Share login credentials below.');
                header('Location: ' . url('admin/students/view.php?id=' . (int) $result['registration_id']));
                exit();
            }
            $error = $result['error'];
            $tab = 'new';
        }
    }
}

$page_title = 'Onboard Student';
$breadcrumbs = [
    ['label' => 'Students', 'url' => url('admin/students/index.php')],
    ['label' => 'Onboard Student', 'active' => true],
];

$feeStatuses = [
    'current'      => 'Current — up to date',
    'partial'      => 'Partial — some balance remaining',
    'overdue'      => 'Overdue — payment past due',
    'paid_in_full' => 'Paid in full',
];

ob_start();
?>

<div class="alert alert-info">
    <i class="fas fa-user-plus me-2"></i>
    Manually onboard students who are <strong>already learning</strong> at Dazu. Assign their course, learning month,
    financial status, and prior payments so they can continue in the portal.
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'new' ? 'active' : ''; ?>" href="?tab=new">
            <i class="fas fa-user me-1"></i> New — no portal account yet
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'existing' ? 'active' : ''; ?>" href="?tab=existing">
            <i class="fas fa-user-check me-1"></i> Existing — already has portal account
        </a>
    </li>
</ul>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="onboard_mode" value="<?php echo $tab; ?>">

            <?php if ($tab === 'existing'): ?>
            <div class="mb-4">
                <label class="form-label fw-semibold">Select enrolled student <span class="text-danger">*</span></label>
                <select name="prior_registration_id" class="form-select" required>
                    <option value="">— Choose student —</option>
                    <?php foreach ($students as $s):
                        $paid = $paymentModel->getTotalApproved((int) $s['id']);
                        $owed = $studentModel->totalOwed($s);
                        $bal = max(0, $owed - $paid);
                    ?>
                    <option value="<?php echo (int) $s['id']; ?>">
                        <?php echo htmlspecialchars($s['registration_no'] . ' — ' . $s['full_name'] . ' (' . $s['course_title'] . ')'); ?>
                        <?php if ($bal > 0): ?> — Bal KSh <?php echo number_format($bal); ?><?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select" required id="courseSelect">
                        <option value="">— Select course —</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>" data-months="<?php echo (int) $c['duration_months']; ?>">
                            <?php echo htmlspecialchars($c['title']); ?> (<?php echo (int) $c['duration_months']; ?> months)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Study mode</label>
                    <select name="study_mode" class="form-select">
                        <option value="online">Online</option>
                        <option value="physical">Physical</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Payment plan</label>
                    <select name="payment_plan" class="form-select">
                        <option value="monthly">Monthly</option>
                        <option value="3months">3 Months</option>
                        <option value="full">Full</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Learning month <span class="text-danger">*</span></label>
                    <input type="number" name="current_learning_month" class="form-control" value="1" min="1" max="24" required id="learningMonth">
                    <small class="text-muted">Unlocks topics for months 1 through this month.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Installments paid</label>
                    <input type="number" name="installments_paid" class="form-control" value="0" min="0" max="24">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Amount paid so far (KSh)</label>
                    <input type="number" name="amount_paid_prior" class="form-control" value="0" min="0" step="0.01">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Financial status</label>
                    <select name="fee_status" class="form-select">
                        <?php foreach ($feeStatuses as $val => $label): ?>
                        <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Payment reference / notes</label>
                    <input type="text" name="payment_reference" class="form-control" placeholder="M-Pesa codes or payment summary">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Fee balance note</label>
                    <input type="text" name="fee_balance_note" class="form-control" placeholder="e.g. Owes KSh 5,000 balance">
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="registration_fee_waived" id="waiveReg" value="1" checked>
                        <label class="form-check-label" for="waiveReg">Waive registration fee</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="portal_locked" id="portalLocked" value="1">
                        <label class="form-check-label" for="portalLocked">Lock portal until balance cleared</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check me-1"></i> Onboard Student
            </button>
            <a href="<?php echo url('admin/students/index.php'); ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<script>
(function () {
    var courseSelect = document.getElementById('courseSelect');
    var learningMonth = document.getElementById('learningMonth');
    if (!courseSelect || !learningMonth) return;
    courseSelect.addEventListener('change', function () {
        var opt = courseSelect.options[courseSelect.selectedIndex];
        var months = parseInt(opt.getAttribute('data-months') || '12', 10);
        learningMonth.max = months;
        if (parseInt(learningMonth.value, 10) > months) learningMonth.value = months;
    });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
