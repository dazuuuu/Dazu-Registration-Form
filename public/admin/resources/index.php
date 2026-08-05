<?php
// public/admin/resources/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/ResourceModel.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\ResourceModel;
use Models\StudentModel;
use Models\TutorModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$resourceModel = new ResourceModel($pdo);
$studentModel = new StudentModel($pdo);
$tutorModel = new TutorModel($pdo);
$adminId = (int) SessionModel::get('user_id');

$pendingPhotos = $resourceModel->listPendingPhotos();
$allPhotos = $resourceModel->listSubmittedPhotos();
$students = $studentModel->listApproved();
$tutors = $tutorModel->listAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve_photo' || $action === 'reject_photo') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $status = $action === 'approve_photo' ? 'approved' : 'rejected';
            if ($resourceModel->updatePhotoStatus($uid, $status)) {
                SessionModel::setFlash('success', 'Photo marked as ' . $status . '.');
                header('Location: ' . url('admin/resources/index.php'));
                exit();
            }
            $error = 'Could not update photo status.';
        } elseif ($action === 'upload_document') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $docType = $_POST['doc_type'] ?? '';
            $regId = !empty($_POST['registration_id']) ? (int) $_POST['registration_id'] : null;
            if ($uid <= 0 || empty($_FILES['document']['name'])) {
                $error = 'Select a user and PDF file.';
            } else {
                $result = $resourceModel->uploadDocument($uid, $docType, $_FILES['document'], $adminId, $regId);
                if ($result['ok']) {
                    SessionModel::setFlash('success', ucfirst(str_replace('_', ' ', $docType)) . ' uploaded. User can download from Resources.');
                    header('Location: ' . url('admin/resources/index.php'));
                    exit();
                }
                $error = $result['error'];
            }
        }
        $pendingPhotos = $resourceModel->listPendingPhotos();
        $allPhotos = $resourceModel->listSubmittedPhotos();
    }
}

$page_title = 'Resources & ID Photos';
$breadcrumbs = [['label' => 'Resources', 'active' => true]];

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="fas fa-camera me-2"></i>Submitted Profile Photos</span>
                <?php if (count($pendingPhotos) > 0): ?>
                    <span class="badge bg-warning text-dark"><?php echo count($pendingPhotos); ?> pending</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($allPhotos)): ?>
                    <p class="text-muted p-4 mb-0">No profile photos submitted yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Photo</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allPhotos as $p):
                                $photoUrl = settings_asset_url($p['profile_photo_path']);
                                $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($name ?: $p['username']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($p['email']); ?></small>
                                </td>
                                <td><?php echo ucfirst($p['role_name']); ?></td>
                                <td>
                                    <?php if ($photoUrl): ?>
                                        <a href="<?php echo htmlspecialchars($photoUrl); ?>" target="_blank" rel="noopener">
                                            <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;">
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo $p['profile_photo_submitted_at'] ? date('d M Y H:i', strtotime($p['profile_photo_submitted_at'])) : '—'; ?></small></td>
                                <td><span class="badge bg-<?php echo ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$p['profile_photo_status']] ?? 'secondary'; ?>"><?php echo ucfirst($p['profile_photo_status']); ?></span></td>
                                <td>
                                    <?php if ($p['profile_photo_status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="approve_photo">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$p['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="reject_photo">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$p['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject"><i class="fas fa-times"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="fas fa-file-pdf me-2"></i>Upload ID Card or Certificate</div>
            <div class="card-body">
                <p class="text-muted small">Upload PDF documents for a student or tutor. They will appear on their Resources page for download.</p>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload_document">
                    <div class="mb-3">
                        <label class="form-label">User type</label>
                        <select class="form-select" id="userType">
                            <option value="student">Student</option>
                            <option value="tutor">Tutor</option>
                        </select>
                    </div>
                    <div class="mb-3" id="studentSelect">
                        <label class="form-label">Student</label>
                        <select name="user_id" class="form-select" id="studentUserId">
                            <option value="">— Select student —</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?php echo (int)($s['user_id'] ?? 0); ?>" data-reg="<?php echo (int)$s['id']; ?>">
                                <?php echo htmlspecialchars($s['registration_no'] . ' — ' . $s['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="registration_id" id="registrationId" value="">
                    </div>
                    <div class="mb-3 d-none" id="tutorSelect">
                        <label class="form-label">Tutor</label>
                        <select class="form-select" id="tutorUserId">
                            <option value="">— Select tutor —</option>
                            <?php foreach ($tutors as $t): ?>
                            <option value="<?php echo (int)$t['user_id']; ?>">
                                <?php echo htmlspecialchars(trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: $t['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document type</label>
                        <select name="doc_type" class="form-select" required>
                            <option value="id_card">ID Card (PDF)</option>
                            <option value="certificate">Certificate (PDF)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PDF file</label>
                        <input type="file" name="document" class="form-control" accept="application/pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-1"></i> Upload Document</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var userType = document.getElementById('userType');
    var studentSelect = document.getElementById('studentSelect');
    var tutorSelect = document.getElementById('tutorSelect');
    var studentUserId = document.getElementById('studentUserId');
    var tutorUserId = document.getElementById('tutorUserId');
    var registrationId = document.getElementById('registrationId');
    if (!userType) return;

    function syncUserSelect() {
        var isStudent = userType.value === 'student';
        studentSelect.classList.toggle('d-none', !isStudent);
        tutorSelect.classList.toggle('d-none', isStudent);
        studentUserId.disabled = !isStudent;
        tutorUserId.disabled = isStudent;
        if (isStudent) {
            studentUserId.name = 'user_id';
            tutorUserId.name = '';
        } else {
            tutorUserId.name = 'user_id';
            studentUserId.name = '';
            registrationId.value = '';
        }
    }
    userType.addEventListener('change', syncUserSelect);
    studentUserId.addEventListener('change', function () {
        var opt = studentUserId.options[studentUserId.selectedIndex];
        registrationId.value = opt ? (opt.getAttribute('data-reg') || '') : '';
    });
    syncUserSelect();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
