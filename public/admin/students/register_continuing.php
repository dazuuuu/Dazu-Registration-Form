<?php
// public/admin/students/register_continuing.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/ContinuingStudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\CourseModel;
use Models\PaymentModel;
use Models\RegistrationModel;
use Controllers\ContinuingStudentController;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$courseModel = new CourseModel($pdo);
$paymentModel = new PaymentModel($pdo);

$students = $studentModel->listApproved();
$courses = array_values(array_filter($courseModel->getAll(), static fn($c) => !empty($c['is_active'])));

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $controller = new ContinuingStudentController();
        $result = $controller->register([
            'prior_registration_id'   => (int) ($_POST['prior_registration_id'] ?? 0),
            'course_id'               => (int) ($_POST['course_id'] ?? 0),
            'study_mode'              => $_POST['study_mode'] ?? 'online',
            'payment_plan'            => $_POST['payment_plan'] ?? 'monthly',
            'current_learning_month'  => (int) ($_POST['current_learning_month'] ?? 1),
            'installments_paid'       => (int) ($_POST['installments_paid'] ?? 0),
            'amount_paid_prior'       => (float) ($_POST['amount_paid_prior'] ?? 0),
            'registration_fee_waived' => !empty($_POST['registration_fee_waived']),
            'portal_locked'           => !empty($_POST['portal_locked']),
            'fee_balance_note'        => trim($_POST['fee_balance_note'] ?? ''),
            'payment_reference'       => trim($_POST['payment_reference'] ?? ''),
        ], (int) SessionModel::get('user_id'));

        if ($result['ok']) {
            SessionModel::setFlash('success', 'Continuing student enrolled as <strong>' . htmlspecialchars($result['reg_no']) . '</strong>.');
            header('Location: ' . url('admin/students/view.php?id=' . (int) $result['registration_id']));
            exit();
        }
        $error = $result['error'];
    }
}

$page_title = 'Register Continuing Student';
$breadcrumbs = [
    ['label' => 'Students', 'url' => url('admin/students/index.php')],
    ['label' => 'Register Continuing Student', 'active' => true],
];

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Use this form for students who are already learning at Dazu. Assign their course, current learning month
            (unlocks reading materials for previous topics), and record any prior payments or outstanding balance.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header fw-semibold">
                <i class="fas fa-user-plus me-2"></i>Continuing Student Enrollment
            </div>
            <div class="card-body">
                <form method="POST" id="continuingForm">
                    <?php echo csrf_field(); ?>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Select existing student <span class="text-danger">*</span></label>
                        <select name="prior_registration_id" class="form-select" required id="priorStudent">
                            <option value="">— Choose student —</option>
                            <?php foreach ($students as $s):
                                $paid = $paymentModel->getTotalApproved((int) $s['id']);
                                $owed = $studentModel->totalOwed($s);
                                $bal = max(0, $owed - $paid);
                            ?>
                            <option value="<?php echo (int) $s['id']; ?>"
                                    data-course="<?php echo htmlspecialchars($s['course_title']); ?>"
                                    data-reg="<?php echo htmlspecialchars($s['registration_no']); ?>">
                                <?php echo htmlspecialchars($s['registration_no'] . ' — ' . $s['full_name'] . ' (' . $s['course_title'] . ')'); ?>
                                <?php if ($bal > 0): ?> — Bal KSh <?php echo number_format($bal); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only students with an approved portal account appear here.</small>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Course <span class="text-danger">*</span></label>
                            <select name="course_id" class="form-select" required id="courseSelect">
                                <option value="">— Select course —</option>
                                <?php foreach ($courses as $c): ?>
                                <option value="<?php echo (int) $c['id']; ?>"
                                        data-months="<?php echo (int) $c['duration_months']; ?>"
                                        data-fee="<?php echo (float) $c['course_fee']; ?>">
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
                            <select name="payment_plan" class="form-select" id="paymentPlan">
                                <option value="monthly">Monthly</option>
                                <option value="3months">3 Months</option>
                                <option value="full">Full</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Current learning month <span class="text-danger">*</span></label>
                            <input type="number" name="current_learning_month" class="form-control" value="1" min="1" max="24" required id="learningMonth">
                            <small class="text-muted">Month 2 unlocks topics from months 1–2 for reading.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Installments already paid</label>
                            <input type="number" name="installments_paid" class="form-control" value="0" min="0" max="24" id="installmentsPaid">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total amount already paid (KSh)</label>
                            <input type="number" name="amount_paid_prior" class="form-control" value="0" min="0" step="0.01" id="amountPaidPrior">
                            <small class="text-muted">Creates a receipt in payment history.</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Payment reference / notes</label>
                        <input type="text" name="payment_reference" class="form-control"
                               placeholder="e.g. M-Pesa codes or prior payment summary">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Fee balance note (optional)</label>
                        <textarea name="fee_balance_note" class="form-control" rows="2"
                                  placeholder="e.g. Owes KSh 5,000 from last term"></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="registration_fee_waived" id="waiveReg" value="1" checked>
                                <label class="form-check-label" for="waiveReg">Waive registration fee (recommended for continuing students)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="portal_locked" id="portalLocked" value="1">
                                <label class="form-check-label" for="portalLocked">Lock portal until outstanding balance is cleared</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i> Enroll Continuing Student
                        </button>
                        <a href="<?php echo url('admin/students/index.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
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
