<?php
// public/auth/student_register.php
require_once __DIR__ . '/../../app/init.php';
require_once __DIR__ . '/../../app/helpers/uploads.php';
require_once __DIR__ . '/../../app/models/Connection.php';
require_once __DIR__ . '/../../app/models/CourseModel.php';
require_once __DIR__ . '/../../app/models/RegistrationModel.php';
require_once __DIR__ . '/../../app/services/StudentNotifier.php';

use Models\Connection;
use Models\CourseModel;
use Models\RegistrationModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$registrationModel = new RegistrationModel($pdo);

$courses = $courseModel->getAll(true); // active only
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please refresh and try again.';
    } else {
        $idDocPath = null;

        // PDF-only upload for ID / result slip — separate validation from upload_image(),
        // which only accepts images. A driving-license-style ID photo wouldn't fit that.
        if (empty($_FILES['id_document']['name'])) {
            $error = 'Please upload your ID or result slip as a PDF.';
        } else {
            $file = $_FILES['id_document'];
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
                $error = 'The document upload failed, please try again.';
            } elseif (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                $error = 'The document must be under 5 MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if ($mime !== 'application/pdf') {
                    $error = 'Only PDF files are accepted for the ID/result slip.';
                } else {
                    $destDir = __DIR__ . '/../uploads/registrations/documents';
                    if (!is_dir($destDir)) mkdir($destDir, 0775, true);
                    $name = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
                    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $name)) {
                        $error = 'Could not save the uploaded document.';
                    } else {
                        $idDocPath = '/Registration-System/public/uploads/registrations/documents/' . $name;
                    }
                }
            }
        }

        if (!$error) {
            $result = $registrationModel->submit([
                'full_name'         => $_POST['full_name'] ?? '',
                'email'             => $_POST['email'] ?? '',
                'phone'             => $_POST['phone'] ?? '',
                'course_id'         => $_POST['course_id'] ?? 0,
                'study_mode'        => $_POST['study_mode'] ?? '',
                'payment_plan'      => $_POST['payment_plan'] ?? '',
                'id_document_path'  => $idDocPath,
                'payment_reference' => $_POST['payment_reference'] ?? '',
            ], $courseModel);

            if ($result['ok']) {
                $reg = $registrationModel->getById($result['id']);
                if ($reg) {
                    $notifier = new StudentNotifier($pdo);
                    $notifier->sendRegistrationReceived($reg);
                }
                $success = "Thank you — your registration has been received. We've sent a confirmation to your email, and your Instructor will be in touch once it's approved.";
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration — Dazu Hub Institute</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    body { background:#f5f6f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
    .reg-wrap { max-width: 760px; margin: 40px auto; }
    .reg-card { border:none; border-radius:14px; box-shadow:0 4px 24px rgba(0,0,0,0.06); }
    .reg-card .card-header { background:#0D9488; color:#fff; border-radius:14px 14px 0 0; padding:24px; }
    .fee-summary { background:#f1f6f5; border-radius:10px; padding:16px 20px; font-size:14px; }
    .fee-summary .total { font-size:1.4rem; font-weight:700; color:#0D9488; }
    .course-fee-hint { font-size:12px; color:#888; }
</style>
</head>
<body>
<div class="reg-wrap">
    <div class="card reg-card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Student Registration</h4>
            <small>Dazu Hub Institute</small>
        </div>
        <div class="card-body p-4">

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if (empty($courses)): ?>
                    <div class="alert alert-warning">No courses are open for registration right now. Please check back soon.</div>
                <?php else: ?>

                <form method="POST" enctype="multipart/form-data" id="regForm">
                    <?php echo csrf_field(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Course</label>
                            <select name="course_id" id="courseSelect" class="form-select" required>
                                <option value="">Select a course</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo (int) $c['id']; ?>"
                                        data-fee="<?php echo (float) $c['course_fee']; ?>"
                                        data-duration="<?php echo (int) $c['duration_months']; ?>">
                                        <?php echo htmlspecialchars($c['title']); ?>
                                        (<?php echo (int) $c['duration_months']; ?> months — KSh <?php echo number_format($c['course_fee']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label d-block">Study Mode</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="study_mode" id="modeOnline" value="online" required>
                                <label class="form-check-label" for="modeOnline">Online</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="study_mode" id="modePhysical" value="physical" required>
                                <label class="form-check-label" for="modePhysical">Physical (Student ID issued)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Payment Plan</label>
                            <select name="payment_plan" id="planSelect" class="form-select" required>
                                <option value="monthly">Monthly installments</option>
                                <option value="3months">3-Month plan</option>
                                <option value="full">Pay in full</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ID / Result Slip (PDF only)</label>
                            <input type="file" name="id_document" class="form-control" accept="application/pdf" required>
                        </div>

                        <div class="col-12">
                            <div class="fee-summary" id="feeSummary">
                                <div class="d-flex justify-content-between">
                                    <span>Registration fee</span>
                                    <strong id="regFeeLine">KSh 1,000</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Course fee</span>
                                    <strong id="courseFeeLine">—</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span id="installmentLabel">First installment</span>
                                    <strong id="installmentLine">—</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Pay now (registration + first payment)</span>
                                    <span class="total" id="totalDueLine">KSh 1,000</span>
                                </div>
                                <p class="course-fee-hint mb-0 mt-1">
                                    Already registered with us before? Submit anyway — admin checks for existing
                                    student records and will waive the registration fee if applicable.
                                </p>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Paybill Details — NCBA</label>
                            <div class="alert alert-info mb-2">
                                Paybill: <strong>[NCBA Paybill No.]</strong> &middot;
                                Business No: <strong>[Business No.]</strong> &middot;
                                Account No: <strong>[Account No.]</strong>
                            </div>
                            <label class="form-label">Name / Number / M-Pesa Code used to pay</label>
                            <input type="text" name="payment_reference" class="form-control" required
                                   placeholder="e.g. John Mwangi, 07XXXXXXXX or M-Pesa code QGH7XXXX">
                            <small class="text-muted">Our admin team manually confirms this against the NCBA statement before approving your registration.</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-paper-plane me-1"></i> Submit Registration
                        </button>
                    </div>
                </form>

                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Live fee preview — purely cosmetic, the authoritative calculation always happens
// server-side in RegistrationModel::submit(). Never trust this for the actual charge.
(function () {
    var courseSelect = document.getElementById('courseSelect');
    var planSelect = document.getElementById('planSelect');
    var modeRadios = document.querySelectorAll('input[name="study_mode"]');

    function fmt(n) {
        return 'KSh ' + Math.round(n).toLocaleString();
    }

    function recalc() {
        var opt = courseSelect.options[courseSelect.selectedIndex];
        var fee = opt ? parseFloat(opt.getAttribute('data-fee')) || 0 : 0;
        var duration = opt ? parseInt(opt.getAttribute('data-duration')) || 1 : 1;
        var plan = planSelect.value;

        var mode = 'online';
        modeRadios.forEach(function (r) { if (r.checked) mode = r.value; });
        var regFee = mode === 'physical' ? 1500 : 1000;

        var count = plan === 'monthly' ? duration : (plan === '3months' ? 3 : 1);
        var installment = fee > 0 ? fee / count : 0;
        var label = plan === 'full' ? 'Full course fee' : 'First installment of ' + count;

        document.getElementById('regFeeLine').textContent = fmt(regFee);
        document.getElementById('courseFeeLine').textContent = fee > 0 ? fmt(fee) : '—';
        document.getElementById('installmentLabel').textContent = label;
        document.getElementById('installmentLine').textContent = fee > 0 ? fmt(installment) : '—';
        document.getElementById('totalDueLine').textContent = fmt(regFee + installment);
    }

    courseSelect.addEventListener('change', recalc);
    planSelect.addEventListener('change', recalc);
    modeRadios.forEach(function (r) { r.addEventListener('change', recalc); });
    recalc();
})();
</script>
</body>
</html>