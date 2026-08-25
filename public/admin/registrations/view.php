<?php
// public/admin/registrations/view.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/contact.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/RegistrationController.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/RegistrationFormGenerator.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;
use Controllers\RegistrationController;
use Controllers\StudentController;

Middleware::requireAdmin();

$pdo     = Connection::getInstance()->getConnection();
$regModel = new RegistrationModel($pdo);

$id  = (int) ($_GET['id'] ?? 0);
$reg = $regModel->getById($id);
if (!$reg) {
    SessionModel::setFlash('error', 'Registration not found.');
    header('Location: ' . url('admin/registrations/index.php'));
    exit();
}

if (($_GET['action'] ?? '') === 'download_form') {
    $pdf = RegistrationFormGenerator::pdf($reg);
    if ($pdf === null) {
        SessionModel::setFlash('error', 'Could not generate the registration form PDF. Run composer install to enable Dompdf.');
        header('Location: ' . url('admin/registrations/view.php?id=' . $id));
        exit();
    }
    $fileName = RegistrationFormGenerator::fileName($reg);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
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
$credentials = null;
$storedCreds = SessionModel::get('student_credentials');
if (is_array($storedCreds) && (int) ($storedCreds['registration_id'] ?? 0) === $id) {
    $credentials = $storedCreds;
    SessionModel::delete('student_credentials');
}

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
                SessionModel::set('student_credentials', [
                    'registration_id' => $id,
                    'reg_no'        => $result['reg_no'],
                    'login_email'   => $result['login_email'],
                    'username'      => $result['reg_no'],
                    'temp_password' => $result['temp_password'],
                    'login_url'     => url('auth/login.php'),
                ]);
                SessionModel::setFlash('success',
                    'Registration approved. Student account is <strong>active</strong>. Copy the login details below and share with the student (WhatsApp, in person, etc.).'
                );
                header('Location: ' . url('admin/registrations/view.php?id=' . $id));
                exit();
            }
            $error = $result['error'];

        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            $result = $controller->reject($id, $reason, $adminId);

            if ($result['ok']) {
                SessionModel::setFlash('success', 'Registration rejected.');
                header('Location: ' . url('admin/registrations/view.php?id=' . $id));
                exit();
            }
            $error = $result['error'];

        } elseif ($action === 'reset_credentials' && $reg['status'] === 'approved') {
            $studentCtrl = new StudentController();
            $result = $studentCtrl->sendCredentials($id);
            if ($result['ok']) {
                $credentials = [
                    'registration_id' => $id,
                    'reg_no'          => $result['username'],
                    'login_email'     => $result['login_email'],
                    'username'        => $result['username'],
                    'temp_password'   => $result['temp_password'],
                    'login_url'       => url('auth/login.php'),
                ];
                SessionModel::setFlash('success', 'New password generated. Share the login details below with the student.');
            } else {
                $error = $result['error'];
            }

        } elseif ($action === 'delete') {
            $result = $controller->delete($id);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Registration deleted' . ($reg['status'] === 'approved' ? ' and student account removed' : '') . '.');
                header('Location: ' . url('admin/registrations/index.php?status=pending'));
                exit();
            }
            $error = $result['error'];

        } elseif ($action === 'send_credentials' && $reg['status'] === 'approved') {
            $studentCtrl = new StudentController();
            $result = $studentCtrl->sendCredentials($id);
            if ($result['ok']) {
                $credentials = [
                    'registration_id' => $id,
                    'reg_no'          => $result['username'],
                    'login_email'     => $result['login_email'],
                    'username'        => $result['username'],
                    'temp_password'   => $result['temp_password'],
                    'login_url'       => url('auth/login.php'),
                ];
                SessionModel::setFlash('success', 'Login details generated. Share them with the student manually.');
            } else {
                $error = $result['error'];
            }
        } elseif ($action === 'email_registration_form') {
            $notifier = new StudentNotifier($pdo, null, true);
            $result = $notifier->sendRegistrationForm($reg);
            if (!empty($result['ok'])) {
                SessionModel::setFlash('success', 'Registration form emailed to ' . htmlspecialchars($reg['email']) . '.');
                header('Location: ' . url('admin/registrations/view.php?id=' . $id));
                exit();
            }
            $error = 'Could not email registration form: ' . ($result['message'] ?? 'Unknown error.');
        }

        // Refresh after action attempt
        $reg = $regModel->getById($id);
    }
}

$page_title  = 'Review Registration';
$breadcrumbs = [
    ['label' => 'Registrations', 'url' => url('admin/registrations/index.php?status=') . $reg['status']],
    ['label' => $reg['full_name'], 'active' => true],
];

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($credentials): ?>
<?php
$waUrl = student_login_whatsapp_url($reg['phone'] ?? '', $reg['full_name'] ?? '', $credentials);
?>
<div class="alert alert-success border-success mb-4">
    <h5 class="alert-heading"><i class="fas fa-user-check me-2"></i>Student login details — share manually</h5>
    <p class="mb-2 small text-muted">No email is sent. Copy these details or open WhatsApp to send them to the student. The account is active now.</p>
    <div class="bg-white text-dark rounded p-3 font-monospace small" id="studentCredBox">
        <div><strong>Student ID:</strong> <?php echo htmlspecialchars($credentials['reg_no'] ?? ''); ?></div>
        <div><strong>Login email:</strong> <?php echo htmlspecialchars($credentials['login_email'] ?? ''); ?></div>
        <div><strong>Username:</strong> <?php echo htmlspecialchars($credentials['username'] ?? $credentials['reg_no'] ?? ''); ?></div>
        <div><strong>Password:</strong> <span id="credPassword"><?php echo htmlspecialchars($credentials['temp_password'] ?? ''); ?></span></div>
        <div><strong>Login page:</strong> <?php echo htmlspecialchars($credentials['login_url'] ?? url('auth/login.php')); ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-success" id="copyStudentCreds">
            <i class="fas fa-copy me-1"></i> Copy all details
        </button>
        <?php if ($waUrl): ?>
        <a href="<?php echo htmlspecialchars($waUrl); ?>" class="btn btn-sm btn-success" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp me-1"></i> Send via WhatsApp
        </a>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    var btn = document.getElementById('copyStudentCreds');
    var box = document.getElementById('studentCredBox');
    if (!btn || !box) return;
    btn.addEventListener('click', function(){
        var text = box.innerText;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){ btn.innerHTML = '<i class="fas fa-check me-1"></i> Copied'; });
        }
    });
})();
</script>
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

        <?php if (!empty($reg['is_free'])): ?>
        <!-- Why they should be selected (free courses skip payment) -->
        <div class="card mb-4">
            <div class="card-header fw-semibold"><i class="fas fa-star text-warning me-1"></i> Why They Should Be Selected</div>
            <div class="card-body">
                <?php if (!empty($reg['free_course_reason'])): ?>
                    <p class="mb-0" style="white-space:pre-wrap;"><?php echo htmlspecialchars($reg['free_course_reason']); ?></p>
                <?php else: ?>
                    <span class="text-muted">No answer on record.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
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
        <?php endif; ?>

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

        <div class="card border-info mb-4">
            <div class="card-header bg-info text-dark fw-semibold">
                <i class="fas fa-file-pdf me-1"></i> Registration form
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Download or email the branded registration form using the student's enrolment email.</p>
                <a href="<?php echo url('admin/registrations/view.php?id=' . (int)$id . '&action=download_form'); ?>"
                   class="btn btn-outline-primary w-100 mb-2">
                    <i class="fas fa-download me-1"></i> Download form
                </a>
                <form method="POST" onsubmit="return confirm('Email the registration form to <?php echo htmlspecialchars($reg['email'], ENT_QUOTES); ?>?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="email_registration_form">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-envelope me-1"></i> Email to student
                    </button>
                </form>
            </div>
        </div>

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
                        <?php if (!empty($reg['registration_no'])): ?>
                            This enrolment already has registration number
                            <strong><?php echo htmlspecialchars($reg['registration_no']); ?></strong>.
                            Approval creates the student portal account and keeps that number.
                        <?php else: ?>
                            On approval, a student account is created and the next registration number is issued
                            (starting from <strong>DH050/<?php echo date('y'); ?></strong>).
                        <?php endif; ?>
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
                        <small class="text-muted">Stored for your records only — not emailed to the student.</small>
                    </div>
                    <button type="submit" class="btn btn-danger w-100"
                            onclick="return confirm('Reject this registration?');">
                        <i class="fas fa-times me-1"></i> Reject Registration
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-secondary mt-4">
            <div class="card-header fw-semibold">Delete application</div>
            <div class="card-body">
                <p class="text-muted small">Remove this pending application permanently.</p>
                <form method="POST" onsubmit="return confirm('Delete this registration permanently?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>

            <?php if ($reg['status'] === 'approved' && $reg['user_id']): ?>
            <div class="card border-primary mb-4">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="fas fa-key me-1"></i> Student account
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">Account is <strong class="text-success">active</strong>. Students log in with their <strong>email</strong> and the password you share below.</p>
                    <ul class="small mb-3">
                        <li>Email: <code><?php echo htmlspecialchars($reg['email']); ?></code></li>
                        <li>Student ID: <code><?php echo htmlspecialchars($reg['registration_no']); ?></code></li>
                    </ul>
                    <form method="POST" class="mb-2" onsubmit="return confirm('Generate a new password for this student?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_credentials">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync me-1"></i> Reset password &amp; show new login details
                        </button>
                    </form>
                    <a href="<?php echo url('admin/students/view.php?id=' . (int)$id); ?>"
                       class="btn btn-link btn-sm ps-0">
                        <i class="fas fa-user-graduate me-1"></i> Open student profile
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white fw-semibold">Delete registration</div>
                <div class="card-body">
                    <p class="text-muted small">
                        Permanently removes this registration<?php echo $reg['status'] === 'approved' ? ' and deletes the student login account' : ''; ?>.
                        Use if approval was a mistake.
                    </p>
                    <form method="POST" onsubmit="return confirm('Delete this registration permanently? This cannot be undone.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-trash me-1"></i> Delete registration
                        </button>
                    </form>
                </div>
            </div>

        <?php endif; ?>

        <!-- Back link -->
        <div class="mt-3">
            <a href="<?php echo url('admin/registrations/index.php?status=' . $reg['status']); ?>"
               class="btn btn-link btn-sm ps-0">
                <i class="fas fa-arrow-left me-1"></i> Back to <?php echo ucfirst($reg['status']); ?> list
            </a>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
