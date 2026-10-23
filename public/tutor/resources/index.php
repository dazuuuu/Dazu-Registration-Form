<?php
// public/tutor/resources/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ResourceModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

use Models\Connection;
use Models\TutorModel;
use Models\ResourceModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$resourceModel = new ResourceModel($pdo);
$userId = (int) SessionModel::get('user_id');

$tutor = $tutorModel->getByUserId($userId);
if (!$tutor) {
    header('Location: ' . url('tutor/dashboard/index.php'));
    exit();
}

$profile = $resourceModel->getProfile($userId) ?? [];
$documents = $resourceModel->listDocumentsForUser($userId);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } elseif (($_POST['action'] ?? '') === 'upload_photo') {
        if (empty($_FILES['profile_photo']['name'])) {
            $error = 'Please select a profile photo.';
        } else {
            $result = $resourceModel->submitProfilePhoto($userId, $_FILES['profile_photo']);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Profile photo submitted for ID review.');
                header('Location: ' . url('tutor/resources/index.php'));
                exit();
            }
            $error = $result['error'];
        }
    }
    $profile = $resourceModel->getProfile($userId) ?? [];
}

$photoUrl = !empty($profile['profile_photo_path']) ? settings_asset_url($profile['profile_photo_path']) : null;
$photoStatus = $profile['profile_photo_status'] ?? 'none';

$idDoc = null;
$certDoc = null;
foreach ($documents as $doc) {
    if ($doc['doc_type'] === 'id_card' && !$idDoc) $idDoc = $doc;
    if ($doc['doc_type'] === 'certificate' && !$certDoc) $certDoc = $doc;
}

// ID card and certificate status (the photo itself is uploaded on My ID & signature only).
$tidT = staff_tutor($pdo, $userId);
$tidOk = $tidT && tid_installed($pdo);
$tidIdStatus = $tidOk ? tid_id_status($pdo, $tidT) : 'not_created';
$tidPhoto = $tidOk ? (idc_student_photo_file($pdo, $userId) ? ($tidT['photo_status'] === 'none' ? 'ok' : $tidT['photo_status']) : 'none') : 'none';
$tidHasSig = $tidT && sig_abs($tidT['signature_path']);
$tidLink = url('tutor/profile/index.php');
$tidTone = ['ok' => 'success', 'approved' => 'success', 'needs_update' => 'warning text-dark', 'requested' => 'danger', 'none' => 'secondary',
            'preview_ready' => 'success', 'sent_to_print' => 'primary', 'printed' => 'dark', 'not_created' => 'secondary'];

$page_title = 'Resources';
ob_start();
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-camera me-2"></i>Profile Photo (for ID)</div>
            <div class="card-body">
                <p class="text-muted small">Your passport photo for the staff ID card is uploaded, checked and previewed in one place: <strong>My ID &amp; signature</strong>.</p>
                <?php if ($tidPhoto !== 'none'): ?>
                    <div class="text-center mb-3"><img src="<?php echo htmlspecialchars(url('id_cards/tutor_photo.php?u=' . $userId)); ?>" alt="Profile" class="rounded" style="width:128px;aspect-ratio:4/5;object-fit:cover;border:3px solid #44A024"></div>
                <?php endif; ?>
                <p class="small mb-3">Status: <span class="badge bg-<?php echo $tidTone[$tidPhoto]; ?>"><?php echo TID_PHOTO_LABELS[$tidPhoto]; ?></span></p>
                <a href="<?php echo htmlspecialchars($tidLink . '#tid-photo'); ?>" class="btn btn-success"><i class="fas fa-id-badge me-1"></i>Go to My ID &amp; signature</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-folder-open me-2"></i>My Documents</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-id-card me-2 text-success"></i>Tutor ID Card</span>
                        <span class="d-flex gap-2 align-items-center">
                            <span class="badge bg-<?php echo $tidTone[$tidIdStatus]; ?>"><?php echo TID_ID_LABELS[$tidIdStatus]; ?></span>
                            <?php if ($idDoc): ?><a href="<?php echo url('tutor/resources/download.php?id=' . (int)$idDoc['id']); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i> Download</a><?php endif; ?>
                            <a href="<?php echo htmlspecialchars($tidLink . '#tid-card'); ?>" class="btn btn-sm btn-outline-secondary">View preview</a>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-certificate me-2 text-success"></i>Certificate</span>
                        <span class="d-flex gap-2 align-items-center">
                            <span class="badge bg-<?php echo $tidHasSig ? 'success' : 'warning text-dark'; ?>"><?php echo $tidHasSig ? 'Sample ready' : 'Needs signature'; ?></span>
                            <?php if ($certDoc): ?><a href="<?php echo url('tutor/resources/download.php?id=' . (int)$certDoc['id']); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i> Download</a><?php endif; ?>
                            <a href="<?php echo htmlspecialchars($tidLink . '#tid-certificate'); ?>" class="btn btn-sm btn-outline-secondary">View preview</a>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
