<?php
// public/students/resources/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/ResourceModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\ResourceModel;

Middleware::requireStudent();

$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$resourceModel = new ResourceModel($pdo);
$userId = (int) SessionModel::get('user_id');

$reg = $studentModel->getByUserId($userId);
if (!$reg) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}

$profile = $resourceModel->getProfile($userId) ?? [];
$documents = $resourceModel->listDocumentsForUser($userId, (int) $reg['id']);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } elseif (($_POST['action'] ?? '') === 'upload_photo') {
        if (empty($_FILES['profile_photo']['name'])) {
            $error = 'Please select a profile photo.';
        } else {
            $result = $resourceModel->submitProfilePhoto($userId, $_FILES['profile_photo']);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Profile photo submitted. Admin will review it for your ID card.');
                header('Location: ' . url('students/resources/index.php'));
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
    if ($doc['doc_type'] === 'id_card' && !$idDoc) {
        $idDoc = $doc;
    }
    if ($doc['doc_type'] === 'certificate' && !$certDoc) {
        $certDoc = $doc;
    }
}

$page_title = 'Resources';
ob_start();
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-student">
            <div class="card-head"><i class="fas fa-camera me-2"></i>Profile Photo (for ID)</div>
            <div class="card-body">
                <p class="text-muted small">Upload a clear passport-style photo. Admin uses this to prepare your student ID card.</p>
                <?php if ($photoUrl): ?>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Profile" class="rounded" style="max-width:160px;max-height:200px;object-fit:cover;">
                        <p class="mt-2 small">
                            Status:
                            <span class="badge bg-<?php echo ['none'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger'][$photoStatus] ?? 'secondary'; ?>">
                                <?php echo ucfirst($photoStatus); ?>
                            </span>
                        </p>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="mb-3">
                        <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                        <small class="text-muted">JPG, PNG or WebP. Max 5 MB.</small>
                    </div>
                    <button type="submit" class="btn btn-brand">
                        <i class="fas fa-upload me-1"></i> Upload Photo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-student">
            <div class="card-head"><i class="fas fa-folder-open me-2"></i>My Documents</div>
            <div class="card-body">
                <p class="text-muted small">Download your official ID card and certificate once admin uploads them.</p>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-id-card me-2 text-success"></i>Student ID Card</span>
                        <?php if ($idDoc): ?>
                            <a href="<?php echo url('students/resources/download.php?id=' . (int)$idDoc['id']); ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not available yet</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><i class="fas fa-certificate me-2 text-success"></i>Certificate</span>
                        <?php if ($certDoc): ?>
                            <a href="<?php echo url('students/resources/download.php?id=' . (int)$certDoc['id']); ?>" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not available yet</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
