<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';
require_once __DIR__ . '/../../../app/v1/services/RecommendationService.php';

Middleware::requireStudent();
$page_title = 'My Certificates';

$db = \Models\Connection::getInstance()->getConnection();
$userId = (int) $_SESSION['user_id'];
$self = url('students/certificates/index.php');

// Passport photo (shown on the public verify page of the student's certificates).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['photo']['name'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $res = cert_save_student_photo($db, $userId, $_FILES['photo']);
        $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'Photo updated.' : $res['error'];
    }
    header('Location: ' . $self);
    exit;
}

// Every certificate of this student except withdrawn ones (statuses are only for the admin's tracking).
$stmt = $db->prepare(
    "SELECT c.*, cr.title AS course_name, r.email AS reg_email, r.phone AS reg_phone
     FROM certificates c
     JOIN registrations r ON c.registration_id = r.id
     JOIN courses cr ON r.course_id = cr.id
     WHERE r.user_id = ? AND c.status <> 'withdrawn'
     ORDER BY COALESCE(c.issued_at, c.created_at) DESC"
);
$stmt->execute([$userId]);
$certificates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
$photo = cert_student_photo($db, $userId);

ob_start();
?>
<div class="mb-4 d-flex justify-content-between align-items-center">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-certificate text-success me-2"></i> My Certificates</h1>
</div>

<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_SESSION[$key]); unset($_SESSION[$key]); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; endforeach; ?>

<?php try { require __DIR__ . '/../../components/certificates/apply_panel.php'; } catch (Throwable $e) { error_log('[apply panel] ' . $e->getMessage()); } ?>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
        <?php if ($photo): ?>
            <img src="<?php echo htmlspecialchars(settings_asset_url($photo)); ?>" alt="Your photo" style="width:72px;height:86px;object-fit:cover" class="rounded border">
        <?php else: ?>
            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width:72px;height:86px"><i class="fas fa-user fa-2x"></i></div>
        <?php endif; ?>
        <div class="flex-grow-1" style="min-width:220px">
            <h5 class="mb-1">Your certificate photo</h5>
            <p class="text-muted small mb-2">A clear passport-style photo. It is shown when someone verifies your certificate.</p>
            <form method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
                <?php echo csrf_field(); ?>
                <input type="file" name="photo" class="form-control form-control-sm" style="max-width:280px" accept="image/jpeg,image/png,image/webp" required>
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-upload me-1"></i><?php echo $photo ? 'Change photo' : 'Upload photo'; ?></button>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <?php if (empty($certificates)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 text-center p-5">
                <i class="fas fa-award fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Certificates Yet</h4>
                <p class="text-muted mb-0">Complete your courses and graduate to earn your certificates!</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($certificates as $cert): ?>
            <div class="col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-body text-center p-4">
                        <div class="mb-3"><i class="fas fa-medal fa-3x text-warning"></i></div>
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($cert['course_name']); ?></h4>
                        <?php if ($cert['issued_at']): ?><p class="text-muted small mb-3">Issued: <?php echo date('F j, Y', strtotime($cert['issued_at'])); ?></p><?php endif; ?>
                        <a href="<?php echo url('admin/certificates/preview.php?id=' . $cert['id']); ?>" target="_blank" class="btn btn-sm btn-link mb-2"><i class="fas fa-eye me-1"></i>View</a>
                        <?php if ($letter = lor_get_by_reg($db, (int) $cert['registration_id'])): $lorUrl = url('letters/recommendation.php?id=' . (int) $letter['id']); ?>
                            <div class="border rounded-3 p-2 mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-center bg-light">
                                <span class="small fw-semibold"><i class="fas fa-file-signature text-success me-1"></i>Letter of recommendation</span>
                                <a href="<?php echo htmlspecialchars($lorUrl); ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye me-1"></i>View</a>
                                <a href="<?php echo htmlspecialchars($lorUrl . '&dl=1'); ?>" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i>Download</a>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-center">
                            <?php
                            $certDownloadUrl = url('admin/certificates/preview.php?id=' . $cert['id'] . '&print=1');
                            $certEmailUrl = url('students/certificates/email.php');
                            $certDefaultEmail = $cert['reg_email'] ?: ($_SESSION['email'] ?? '');
                            $certDefaultPhone = $cert['reg_phone'] ?? '';
                            require __DIR__ . '/../../components/certificates/share_actions.php';
                            ?>
                        </div>
                    </div>
                    <div class="card-footer bg-light border-0 text-center text-muted small py-2">
                        No: <?php echo htmlspecialchars($cert['certificate_no']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
