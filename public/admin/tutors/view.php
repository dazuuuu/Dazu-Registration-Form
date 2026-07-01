<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\CourseModel;
use Controllers\TutorController;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$courseModel = new CourseModel($pdo);
$id = (int)($_GET['id'] ?? 0);
$tutor = $tutorModel->getById($id);
if (!$tutor) { SessionModel::setFlash('error','Tutor not found.'); header('Location: index.php'); exit(); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $error = 'Security check failed.';
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'send_credentials') {
            $r = (new TutorController())->sendCredentials($id);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Credentials emailed.' : ($r['error'] ?? 'Failed'));
            header('Location: view.php?id='.$id); exit();
        }
        $photoPath = $tutor['photo_path'];
        if (!empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/tutors', url('uploads/tutors'));
            if ($up['ok']) $photoPath = $up['path']; else $error = $up['error'];
        }
        if (!$error) {
            $r = $tutorModel->update($id, [
                'full_name'  => $_POST['full_name'] ?? '',
                'phone'      => $_POST['phone'] ?? '',
                'bio'        => $_POST['bio'] ?? '',
                'photo_path' => $photoPath,
                'is_active'  => isset($_POST['is_active']),
            ], $_POST['course_ids'] ?? []);
            if ($r['ok']) { SessionModel::setFlash('success','Tutor updated.'); header('Location: view.php?id='.$id); exit(); }
            $error = $r['error'];
        }
    }
    $tutor = $tutorModel->getById($id);
}

$courses = $courseModel->getAll(true);
$assignedIds = array_column($tutor['courses'], 'id');
$fullName = $tutorModel->fullName($tutor);

$page_title = $fullName;
$breadcrumbs = [['label'=>'Tutors','url'=>'index.php'],['label'=>$fullName,'active'=>true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="row g-4">
<div class="col-lg-8">
<div class="card mb-4"><div class="card-header fw-semibold">Edit Tutor</div><div class="card-body">
<form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?php echo htmlspecialchars($fullName); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?php echo htmlspecialchars($tutor['email']); ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($tutor['phone'] ?? ''); ?>"></div>
    <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*">
        <?php if ($tutor['photo_path']): ?><img src="<?php echo htmlspecialchars($tutor['photo_path']); ?>" class="mt-2 rounded" height="80"><?php endif; ?>
    </div>
    <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($tutor['bio'] ?? ''); ?></textarea></div>
    <div class="col-12"><label class="form-label">Assigned Courses</label>
        <select name="course_ids[]" class="form-select" multiple size="6">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo in_array($c['id'], $assignedIds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="active" <?php echo $tutor['is_active'] ? 'checked' : ''; ?>><label for="active">Active</label></div></div>
</div>
<button type="submit" class="btn btn-primary mt-3">Save Changes</button>
</form>
</div></div>
</div>
<div class="col-lg-4">
<div class="card border-primary"><div class="card-header bg-primary text-white">Credentials</div><div class="card-body">
<p class="small text-muted">Reset password and email login details.</p>
<?php if ($tutor['credentials_sent_at']): ?><p class="small text-success">Last sent: <?php echo date('d M Y H:i', strtotime($tutor['credentials_sent_at'])); ?></p><?php endif; ?>
<form method="POST" onsubmit="return confirm('Send new credentials?');"><?php echo csrf_field(); ?>
<input type="hidden" name="action" value="send_credentials">
<button class="btn btn-primary w-100"><i class="fas fa-envelope me-1"></i> Email Credentials</button>
</form>
</div></div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
