<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

Middleware::requireAdmin();
$page_title = 'Certificates';

$db = \Models\Connection::getInstance()->getConnection();
$certModel = new \Models\CertificateModel($db);

// Generate a certificate (from the search modal here, or from a student's page).
// Only allowed when the student's fee balance is zero — see cert_eligibility().
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id'])) {
    $back = $_POST['return_to'] ?? '';
    $back = (is_string($back) && str_starts_with($back, url('admin/'))) ? $back : url('admin/certificates/index.php');
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
        header('Location: ' . url('admin/certificates/edit.php?id=' . $res['id']));
    } elseif (!empty($res['id'])) {
        $_SESSION['error_msg'] = $res['error'];
        header('Location: ' . url('admin/certificates/edit.php?id=' . $res['id']));
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

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Certificate No</th>
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
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                <p class="mb-0">No certificates generated yet.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-success"><?php echo htmlspecialchars($cert['certificate_no']); ?></td>
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
                                    <a href="<?php echo url('admin/certificates/preview.php?id=' . $cert['id']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View PDF"><i class="fas fa-eye"></i></a>
                                    <a href="<?php echo url('admin/certificates/edit.php?id=' . $cert['id']); ?>" class="btn btn-sm btn-outline-primary" title="Edit/Prepare"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../components/certificates/generate_modal.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
