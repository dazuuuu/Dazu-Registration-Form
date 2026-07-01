<?php
// public/admin/registrations/view.php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/CourseModel.php';
require_once __DIR__ . '/../../../app/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/controllers/RegistrationController.php';
require_once __DIR__ . '/../../../app/models/Session.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;
use Controllers\RegistrationController;

Middleware::requireAdmin();

$pdo     = Connection::getInstance()->getConnection();
$regModel = new RegistrationModel($pdo);

$id  = (int) ($_GET['id'] ?? 0);
$reg = $regModel->getById($id);
if (!$reg) {
    SessionModel::setFlash('error', 'Registration not found.');
    header('Location: /Registration-System/public/admin/registrations/index.php');
    exit();
}

// Compute installment breakdown for display
$installments = RegistrationModel::calculateInstallments(
    (float) $reg['course_fee'],
    $reg['payment_plan'],
    (int) $reg['duration_months']
);
$lastAmount  = $installments['last_amount'];
$sameAmount  = abs($installments['amount'] - $lastAmount) < 0.01;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please refresh and try again.';
    } else {
        $action      = $_POST['action'] ?? '';
        $controller  = new RegistrationController();
        $adminId     = (int) SessionModel::get('user_id');

        if ($action === 'approve') {
            $waive  = isset($_POST['waive_reg_fee']) && $_POST['waive_reg_fee'] === '1';
            $result = $controller->approve($id, $waive, $adminId);

            if ($result['ok']) {
                // TODO Phase 2: send approval email with $result['reg_no'] + $result['temp_password']
                SessionModel::setFlash('success',
                    'Registration approved. Student ID: <strong>' . htmlspecialchars($result['reg_no']) . '</strong>. ' .
                    'Email the student their temporary password: <code>' . htmlspecialchars($result['temp_password']) . '</code>'
                );
                header('Location: /Registration-System/public/admin/registrations/view.php?id=' . $id);
                exit();
            }
            $error = $result['error'];

        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            $result = $controller->reject($id, $reason, $adminId);

            if ($result['ok']) {
                // TODO Phase 2: send rejection email with reason
                SessionModel::setFlash('success', 'Registration rejected.');
                header('Location: /Registration-System/public/admin/registrations/view.php?id=' . $id);
                exit();
            }
            $error = $result['error'];
        }

        // Refresh after action attempt
        $reg = $regModel->getById($id);
    }
}

$page_title  = 'Review Registration';
$breadcrumbs = [
    ['label' => 'Registrations', 'url' => '/Registration-System/public/admin/registrations/index.php?status=' . $reg['status']],
    ['label' => $reg['full_name'], 'active' => true],
];

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- LEFT COLUMN: Student details -->
    <div class="col-lg-8">

        <!-- Status banner -->
        <div class="alert alert-<?php echo ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$reg['status']]; ?> d-flex align-items-center gap-3 mb-4">
            <i class="fas fa-<?php echo ['pending'=>'hourglass-half','approved'=>'check-circle','rejected'=>'times-circle'][$reg['status']]; ?> fa-2x"></i>
            <div>
                <strong class="d-block text-capitalize"><?php echo $reg['status']; ?></strong>
                <?php if ($reg['status'] === 'approved'): ?>
                    Student ID: <strong><?php echo htmlspecialchars($reg['registration_no']); ?></strong>
                    &middot; Approved <?php echo date('d M Y H:i', strtotime($reg['reviewed_at'])); ?>
                <?php elseif ($reg['status'] === 'rejected'): ?>
                    Reason: <?php echo htmlspecialchars($reg['rejection_reason']); ?>
                    &middot; <?php echo date('d M Y H:i', strtotime($reg['reviewed_at'])); ?>
                <?php else: ?>
                    Submitted <?php echo date('d M Y H:i', strtotime($reg['created_at'])); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Personal details -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">Personal Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Full Name</small>
                        <strong><?php echo htmlspecialchars($reg['full_name']); ?></strong>
                        <?php if ($reg['possible_returning_student']): ?>
                            <span class="badge bg-info ms-1" title="Email or phone matched an existing approved registration">
                                ↩ Possible returning student
                            </span>
                        <?php endif; ?>
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
                        <?php echo $reg['study_mode'] === 'physical' ? '🏫 Physical (Student ID card required)' : '💻 Online'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course & payment details -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">Course & Payment Breakdown</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Course</small>
                        <strong><?php echo htmlspecialchars($reg['course_title']); ?></strong>
                        <small class="text-muted">(<?php echo (int)$reg['duration_months']; ?> months)</small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Payment Plan</small>
                        <?php
                        $planLabel = [
                            'monthly'  => 'Monthly — ' . $installments['count'] . ' installments',
                            '3months'  => '3-Month — ' . $installments['count'] . ' installments',
                            'full'     => 'Full payment',
                        ][$reg['payment_plan']] ?? $reg['payment_plan'];
                        echo $planLabel;
                        ?>
                    </div>
                </div>

                <table class="table table-sm table-bordered mb-0">
                    <tbody>
                        <tr>
                            <td>Registration fee</td>
                            <td class="text-end fw-semibold">
                                <?php if ($reg['registration_fee_waived']): ?>
                                    <span class="text-decoration-line-through text-muted">KSh <?php echo number_format($reg['registration_fee']); ?></span>
                                    <span class="badge bg-secondary ms-1">Waived</span>
                                <?php else: ?>
                                    KSh <?php echo number_format($reg['registration_fee']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Total course fee</td>
                            <td class="text-end">KSh <?php echo number_format($reg['course_fee']); ?></td>
                        </tr>
                        <?php if ($installments['count'] === 1): ?>
                        <tr>
                            <td>Full course payment (1 payment)</td>
                            <td class="text-end">KSh <?php echo number_format($lastAmount); ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td>Each installment (<?php echo $installments['count'] - 1; ?> payments)</td>
                            <td class="text-end">KSh <?php echo number_format($installments['amount']); ?></td>
                        </tr>
                        <?php if (!$sameAmount): ?>
                        <tr>
                            <td>Final installment</td>
                            <td class="text-end">KSh <?php echo number_format($lastAmount); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                        <tr class="table-light fw-bold">
                            <td>First payment due (reg fee + first installment)</td>
                            <td class="text-end text-success">
                                KSh <?php
                                $regFeeActual = $reg['registration_fee_waived'] ? 0 : (float)$reg['registration_fee'];
                                echo number_format($regFeeActual + $installments['amount']);
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment reference -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">Payment Reference (Student-Submitted)</div>
            <div class="card-body">
                <div class="alert alert-light border mb-0" style="font-size:1rem;letter-spacing:.02em;">
                    <i class="fas fa-money-bill-wave text-success me-2"></i>
                    <strong><?php echo htmlspecialchars($reg['payment_reference']); ?></strong>
                </div>
                <small class="text-muted">Cross-check this against the NCBA statement before approving.</small>
            </div>
        </div>

        <!-- Uploaded document -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">Uploaded ID / Result Slip</div>
            <div class="card-body">
                <?php if (!empty($reg['id_document_path'])): ?>
                    <a href="<?php echo htmlspecialchars(settings_asset_url($reg['id_document_path'])); ?>"
                       target="_blank" class="btn btn-outline-secondary">
                        <i class="fas fa-file-pdf me-1"></i> Open PDF
                    </a>
                <?php else: ?>
                    <span class="text-muted">No document on record.</span>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Actions -->
    <div class="col-lg-4">

        <?php if ($reg['status'] === 'pending'): ?>

        <!-- APPROVE -->
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white fw-semibold">Approve Registration</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="approve">

                    <?php if ($reg['possible_returning_student']): ?>
                        <div class="alert alert-info mb-3" style="font-size:13px;">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            Email or phone matches an existing approved registration.
                            Verify manually before waiving the registration fee.
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="waive_reg_fee" value="1" id="waiveCheck">
                            <label class="form-check-label" for="waiveCheck">
                                Waive registration fee (KSh <?php echo number_format($reg['registration_fee']); ?>) — returning student confirmed
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="waive_reg_fee" value="1" id="waiveCheck">
                            <label class="form-check-label" for="waiveCheck">
                                Waive registration fee (override for returning student)
                            </label>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted small mb-3">
                        On approval, a student account is created and a registration number is issued
                        (<strong>DH-<?php echo htmlspecialchars($reg['abbreviation']); ?>XX/<?php echo date('Y'); ?></strong>).
                    </p>

                    <button type="submit" class="btn btn-success w-100"
                            onclick="return confirm('Approve this registration? This will create the student account.');">
                        <i class="fas fa-check me-1"></i> Approve & Create Account
                    </button>
                </form>
            </div>
        </div>

        <!-- REJECT -->
        <div class="card border-danger">
            <div class="card-header bg-danger text-white fw-semibold">Reject Registration</div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label class="form-label">Reason for rejection <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="e.g. Payment reference could not be verified on the NCBA statement."></textarea>
                        <small class="text-muted">This reason is recorded. You will need to communicate it to the student manually.</small>
                    </div>
                    <button type="submit" class="btn btn-danger w-100"
                            onclick="return confirm('Reject this registration?');">
                        <i class="fas fa-times me-1"></i> Reject Registration
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>
            <div class="card">
                <div class="card-body text-muted text-center py-5">
                    <i class="fas fa-lock fa-2x mb-2 d-block"></i>
                    This registration has been <?php echo $reg['status']; ?>.<br>
                    No further actions available.
                </div>
            </div>
        <?php endif; ?>

        <!-- Back link -->
        <div class="mt-3">
            <a href="/Registration-System/public/admin/registrations/index.php?status=<?php echo $reg['status']; ?>"
               class="btn btn-link btn-sm ps-0">
                <i class="fas fa-arrow-left me-1"></i> Back to <?php echo ucfirst($reg['status']); ?> list
            </a>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';