<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

Middleware::requireSuperPortal();
$page_title = 'Certificates';

$db = \Models\Connection::getInstance()->getConnection();
$certModel = new \Models\CertificateModel($db);

// Reissue one or several issued certificates: built again with today's wording, settings and layout; same certificate
// number, registration number and QR code; the old version goes to certificate_history with who and when.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reissue') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';
        require_once __DIR__ . '/../../../app/v1/helpers/certificate_v2.php';
        $ids = !empty($_POST['one']) ? [(int) $_POST['one']] : array_map('intval', (array) ($_POST['ids'] ?? []));
        $done = 0; $errs = [];
        foreach (array_unique(array_filter($ids)) as $cid) {
            $r = cert_reissue($db, $cid, (int) ($_SESSION['user_id'] ?? 0));
            $r['ok'] ? $done++ : $errs[] = $r['error'];
        }
        if ($done) $_SESSION['success_msg'] = $done . ' certificate' . ($done > 1 ? 's' : '') . ' reissued. Same numbers and QR codes; the old versions are kept in the history.';
        if ($errs) $_SESSION['error_msg'] = implode(' ', $errs);
        if (!$done && !$errs) $_SESSION['error_msg'] = 'Tick the certificates to reissue first.';
    }
    header('Location: ' . url('superadmin/certificates/index.php'));
    exit;
}

// Generate a certificate (from the search modal here, or from a student's page).
// Only allowed when the student's fee balance is zero — see cert_eligibility().
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id'])) {
    $back = $_POST['return_to'] ?? '';
    $back = (is_string($back) && str_starts_with($back, url('superadmin/'))) ? $back : url('superadmin/certificates/index.php');
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        header('Location: ' . $back);
        exit;
    }
    $res = cert_create_for_registration($db, (int) $_POST['registration_id']);
    if ($res['ok']) {
        // Generated = issued: the PDF goes straight to the student's email and appears in their portal.
        cert_issue($db, (int) $res['id']);
        $mail = cert_send_email($db, (int) $res['id']);
        $_SESSION[$mail['ok'] ? 'success_msg' : 'error_msg'] = 'Certificate generated and issued — it is now in the student portal. ' . $mail['message'];
        header('Location: ' . url('superadmin/certificates/edit.php?id=' . $res['id']));
    } elseif (!empty($res['id'])) {
        $_SESSION['error_msg'] = $res['error'];
        header('Location: ' . url('superadmin/certificates/edit.php?id=' . $res['id']));
    } else {
        $_SESSION['error_msg'] = 'Cannot generate certificate: ' . $res['error'];
        if (str_contains($back, '/students/')) { SessionModel::setFlash('error', $_SESSION['error_msg']); unset($_SESSION['error_msg']); }
        header('Location: ' . $back);
    }
    exit;
}

try {
    $certificates = $certModel->getCertificates();
} catch (\PDOException $e) {
    // certificates table missing on this database — ask for the System Update instead of crashing
    $certificates = [];
    $_SESSION['error_msg'] = 'The certificates table is not installed yet. Run Admin → System Updates (migration 057) and reload this page.';
}

ob_start();
?>
<div class="container-fluid py-4">
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success_msg']); unset($_SESSION['success_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-certificate text-success me-2"></i> Certificates Management</h2>
        <div>
            <a href="<?php echo url('verify/'); ?>" target="_blank" class="btn btn-outline-secondary me-2"><i class="fas fa-qrcode me-2"></i>Verify Page</a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="fas fa-plus me-2"></i>Generate Certificate</button>
        </div>
    </div>

    <form method="POST" id="reissueForm" onsubmit="return confirm('Reissue the chosen certificate(s) with the current wording, settings and layout? Numbers and QR codes stay the same; the old versions are kept in the history.')">
    <?php echo csrf_field(); ?><input type="hidden" name="action" value="reissue">
    <div class="d-flex justify-content-end mb-2"><button class="btn btn-outline-success btn-sm"><i class="fas fa-rotate me-1"></i>Reissue selected</button></div>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:32px"><input type="checkbox" class="form-check-input" title="Select all issued" onclick="document.querySelectorAll('.rs-pick').forEach(function(c){c.checked=this.checked}.bind(this))"></th>
                            <th class="ps-2">Certificate No</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Issued At</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certificates)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                <p class="mb-0">No certificates generated yet.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <?php $isIssued = in_array($cert['status'], ['issued', 'collected'], true); ?>
                                <td class="ps-3"><?php if ($isIssued): ?><input type="checkbox" class="form-check-input rs-pick" name="ids[]" value="<?php echo (int) $cert['id']; ?>"><?php endif; ?></td>
                                <td class="ps-2 fw-bold text-success"><?php echo htmlspecialchars($cert['certificate_no']); ?></td>
                                <td><?php echo htmlspecialchars($cert['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($cert['course_name']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-secondary';
                                    if ($cert['status'] == 'issued') $badgeClass = 'bg-success';
                                    if ($cert['status'] == 'being_prepared') $badgeClass = 'bg-warning text-dark';
                                    if ($cert['status'] == 'awaiting_director_signature') $badgeClass = 'bg-info text-dark';
                                    if ($cert['status'] == 'withdrawn') $badgeClass = 'bg-danger';
                                    if ($cert['status'] == 'collected') $badgeClass = 'bg-primary';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($cert['status'] === 'collected' ? 'Picked Up' : ucwords(str_replace('_', ' ', $cert['status']))); ?></span>
                                </td>
                                <td><?php echo $cert['issued_at'] ? date('M j, Y', strtotime($cert['issued_at'])) : '-'; ?></td>
                                <td class="text-end pe-4">
                                    <a href="<?php echo url('superadmin/certificates/preview.php?id=' . $cert['id']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View PDF"><i class="fas fa-eye"></i></a>
                                    <a href="<?php echo url('superadmin/certificates/edit.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-primary" title="Edit/Prepare"><i class="fas fa-edit"></i></a>
                                    <a href="<?php echo url('certificates/text.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-success" title="Text under the name"><i class="fas fa-pen"></i></a>
                                    <?php if ($isIssued): ?><button class="btn btn-sm btn-outline-dark" name="one" value="<?php echo (int) $cert['id']; ?>" title="Reissue with the current wording and settings"><i class="fas fa-rotate"></i></button><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </form>
</div>

<?php require __DIR__ . '/../../components/certificates/generate_modal.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
