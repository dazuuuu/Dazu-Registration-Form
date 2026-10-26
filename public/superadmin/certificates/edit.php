<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

Middleware::requireSuperPortal();

$id = (int) ($_GET['id'] ?? 0);
$db = \Models\Connection::getInstance()->getConnection();
$certModel = new \Models\CertificateModel($db);
$cert = $id ? $certModel->getCertificateById($id) : null;

if (!$cert) {
    header("Location: " . url('superadmin/certificates/index.php'));
    exit;
}

$self = url('superadmin/certificates/edit.php?id=' . $id);
$page_title = 'Edit Certificate: ' . $cert['certificate_no'];

require __DIR__ . '/../../components/certificates/recommendation_actions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
        header('Location: ' . $self);
        exit;
    }

    if (($_POST['action'] ?? '') === 'refresh') {
        // Re-read name, course and tutor from the student's record
        $st = $db->prepare("SELECT r.full_name, r.user_id, c.title FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.id = ?");
        $st->execute([$cert['registration_id']]);
        if ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
            $tutor = cert_assigned_tutor($db, $r['user_id'] ? (int) $r['user_id'] : null);
            $certModel->updateCertificate($id, [
                'snapshot_name'   => trim($r['full_name']),
                'snapshot_course' => $r['title'],
                'tutor_id'        => $tutor['user_id'] ?? null, // certificates.tutor_id references users.id
                'tutor_name'      => $tutor['full_name'] ?? null,
                'ceo_name'        => CERT_DIRECTOR_NAME,
            ]);
            $_SESSION['success_msg'] = 'Name, course and tutor refreshed from the student record.';
        }
        header('Location: ' . $self);
        exit;
    }

    $status = in_array($_POST['status'] ?? '', ['being_prepared', 'awaiting_director_signature', 'issued', 'collected', 'withdrawn'], true)
        ? $_POST['status'] : $cert['status'];
    // A capstone course: the certificate can only be issued while all four conditions are still true (server-side, also for a direct POST).
    if (in_array($status, ['issued', 'collected'], true) && !in_array($cert['status'], ['issued', 'collected'], true)) {
        $can = cert_can_issue($db, (int) $id);
        if (!$can['ok']) {
            $_SESSION['error_msg'] = 'The certificate cannot be issued yet: ' . $can['reason'];
            header('Location: ' . $self);
            exit;
        }
    }
    $updateData = [
        'status'                   => $status,
        'snapshot_duration_months' => max(1, (int) ($_POST['snapshot_duration_months'] ?? $cert['snapshot_duration_months'])),
        'taught_skills'            => trim($_POST['taught_skills'] ?? ''),
    ];

    if (!empty($_FILES['tutor_signature']['name'])) {
        $sig = cert_save_signature($_FILES['tutor_signature']);
        if ($sig) $updateData['tutor_signature_path'] = $sig;
    }
    if (!empty($_FILES['director_signature']['name'])) {
        $sig = cert_save_signature($_FILES['director_signature']);
        if ($sig) $updateData['director_signature_path'] = $sig;
    }

    if (in_array($status, ['issued', 'collected'], true) && empty($cert['issued_at'])) {
        $updateData['issued_at'] = date('Y-m-d H:i:s');
    }

    $certModel->updateCertificate($id, $updateData);
    $_SESSION['success_msg'] = "Certificate details updated successfully.";
    if (!empty($_FILES['student_photo']['name'])) {
        $photo = cert_save_student_photo($db, (int) $cert['user_id'], $_FILES['student_photo']);
        if (!$photo['ok']) {
            $_SESSION['error_msg'] = 'Photo not saved: ' . $photo['error'];
        }
    }
    header('Location: ' . $self);
    exit;
}

$cert['tutor_name'] = $cert['tutor_name'] ?: null;
$ro = 'form-control-plaintext fw-semibold border rounded px-3 bg-light';
ob_start();
?>
<div class="container-fluid py-4">
    <?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION[$key]); unset($_SESSION[$key]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; endforeach; ?>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <a href="<?php echo url('superadmin/certificates/index.php'); ?>" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-2"></i>Back to Certificates
            </a>
            <h2 class="mb-0"><i class="fas fa-certificate text-success me-2"></i> Prepare Certificate: <?php echo htmlspecialchars($cert['certificate_no']); ?></h2>
        </div>
        <a href="<?php echo url('superadmin/certificates/preview.php?id=' . $cert['id']); ?>" target="_blank" class="btn btn-outline-primary">
            <i class="fas fa-eye me-2"></i>Open PDF
        </a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">From the student record</h5>
                        <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="refresh">
                            <button class="btn btn-sm btn-outline-secondary" title="Re-read name, course and assigned tutor"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
                        </form>
                    </div>
                    <div class="row g-3 mb-2">
                        <div class="col-md-6"><label class="form-label small text-muted mb-1">Student name</label><div class="<?php echo $ro; ?>"><?php echo htmlspecialchars($cert['snapshot_name']); ?></div></div>
                        <div class="col-md-6"><label class="form-label small text-muted mb-1">Course</label><div class="<?php echo $ro; ?>"><?php echo htmlspecialchars($cert['snapshot_course']); ?></div></div>
                        <div class="col-md-6"><label class="form-label small text-muted mb-1">Course tutor (assigned tutor)</label>
                            <div class="<?php echo $ro; ?> <?php echo $cert['tutor_name'] ? '' : 'text-danger'; ?>"><?php echo htmlspecialchars($cert['tutor_name'] ?? 'No tutor assigned — assign one, then Refresh'); ?></div></div>
                        <div class="col-md-6"><label class="form-label small text-muted mb-1">Director</label><div class="<?php echo $ro; ?>"><?php echo htmlspecialchars($cert['ceo_name'] ?: CERT_DIRECTOR_NAME); ?></div></div>
                    </div>
                    <p class="small text-muted mb-0"><i class="fas fa-qrcode me-1"></i>Verification link in QR code:
                        <a href="<?php echo htmlspecialchars(cert_verify_url($cert['qr_code_hash'])); ?>" target="_blank" class="text-break"><?php echo htmlspecialchars(cert_verify_url($cert['qr_code_hash'])); ?></a>
                        <br><span class="text-muted">Status is for your own tracking — the verify page shows the student's name, course and photo whatever the status (only <strong>Withdrawn</strong> certificates are flagged).</span>
                    </p>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3">Send certificate</h5>
                    <?php
                    $certDownloadUrl = url('superadmin/certificates/preview.php?id=' . $cert['id'] . '&print=1');
                    $certEmailUrl = url('superadmin/certificates/email.php');
                    $certDefaultEmail = $cert['email'] ?? '';
                    $st = $db->prepare('SELECT phone, email FROM registrations WHERE id = ?');
                    $st->execute([$cert['registration_id']]);
                    $certReg = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $certDefaultEmail = $certReg['email'] ?? $certDefaultEmail;
                    $certDefaultPhone = $certReg['phone'] ?? '';
                    require __DIR__ . '/../../components/certificates/share_actions.php';
                    ?>
                    <?php if ($cert['status'] === 'withdrawn'): ?><div class="small text-danger mt-2">This certificate is withdrawn — its WhatsApp/PDF link no longer works.</div><?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Programme duration (months)</label>
                                <input type="number" min="1" max="120" name="snapshot_duration_months" class="form-control" value="<?php echo (int) $cert['snapshot_duration_months']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['being_prepared' => 'Being Prepared', 'awaiting_director_signature' => 'Awaiting Director Signature', 'issued' => 'Issued', 'collected' => 'Picked Up', 'withdrawn' => 'Withdrawn'] as $v => $l): ?>
                                        <option value="<?php echo $v; ?>" <?php echo $cert['status'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Programme text</label>
                            <div class="small text-muted mb-2">The certificate always reads: <em>“Has successfully completed a <?php echo (int) $cert['snapshot_duration_months']; ?>-month programme of study in the <strong><?php echo htmlspecialchars($cert['snapshot_course']); ?></strong> at Dazu Training Institute”</em>, followed by your text below.</div>
                            <textarea name="taught_skills" rows="4" class="form-control" placeholder="and has met all the requirements of the programme. The programme covered Network Security, Ethical Hacking Fundamentals, Threat Detection, Incident Response and Python for Security."><?php echo htmlspecialchars($cert['taught_skills'] ?? ''); ?></textarea>
                            <div class="form-text">Start with a lowercase word (e.g. “and has met…”) to continue the sentence. Keep it to about 3 lines so it fits the layout.</div>
                        </div>

                        <h6 class="mt-4">Student photo <span class="text-muted small fw-normal">(shown on the verify page; the student can also upload one from their portal)</span></h6>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <?php if ($photo = cert_student_photo($db, (int) $cert['user_id'])): ?>
                                <img src="<?php echo htmlspecialchars(settings_asset_url($photo)); ?>" alt="Student photo" style="width:64px;height:76px;object-fit:cover" class="rounded border">
                            <?php endif; ?>
                            <input type="file" name="student_photo" class="form-control" accept="image/jpeg,image/png,image/webp" <?php echo $cert['user_id'] ? '' : 'disabled title="Student has no portal account yet"'; ?>>
                        </div>

                        <h6 class="mt-4">Signatures <span class="text-muted small fw-normal">(optional — PNG with transparent background works best; leave blank to sign by hand)</span></h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small">Tutor signature</label>
                                <?php if ($cert['tutor_signature_path']): ?>
                                    <div class="mb-2"><img src="<?php echo htmlspecialchars(url($cert['tutor_signature_path'])); ?>" alt="Tutor Signature" style="max-height: 50px;" class="border p-1 bg-white"></div>
                                <?php endif; ?>
                                <input type="file" name="tutor_signature" class="form-control" accept="image/png,image/jpeg">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Director signature</label>
                                <?php if ($cert['director_signature_path']): ?>
                                    <div class="mb-2"><img src="<?php echo htmlspecialchars(url($cert['director_signature_path'])); ?>" alt="Director Signature" style="max-height: 50px;" class="border p-1 bg-white"></div>
                                <?php endif; ?>
                                <input type="file" name="director_signature" class="form-control" accept="image/png,image/jpeg">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <?php require __DIR__ . '/../../components/certificates/recommendation_panel.php'; ?>
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-light"><h5 class="mb-0">Preview</h5></div>
                <div class="card-body p-2">
                    <iframe src="<?php echo url('superadmin/certificates/preview.php?id=' . $cert['id']) . '&v=' . urlencode((string) $cert['updated_at']); ?>#view=FitH&toolbar=0" style="width:100%;aspect-ratio:297/210;border:0;" title="Certificate preview"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
