<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Controllers\TutorController;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
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
            ], $_POST['student_ids'] ?? []);
            if ($r['ok']) { SessionModel::setFlash('success','Tutor updated.'); header('Location: view.php?id='.$id); exit(); }
            $error = $r['error'];
        }
    }
    $tutor = $tutorModel->getById($id);
}

$students = $tutorModel->getRegisteredStudents();
$assignedStudentIds = $tutorModel->getAssignedStudentIds($id);
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
    <div class="col-12"><label class="form-label">Assigned Students <span class="badge bg-primary ms-1"><?php echo count($assignedStudentIds); ?></span></label>
        <input type="text" class="form-control mb-2" id="studentFilter" placeholder="Filter students by name, email or course...">
        <select name="student_ids[]" id="studentSelect" class="form-select" multiple size="8">
            <?php foreach ($students as $s): ?>
                <option value="<?php echo (int)$s['user_id']; ?>" data-search="<?php echo htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['email'] . ' ' . $s['course_titles'])); ?>" <?php echo in_array((int)$s['user_id'], $assignedStudentIds, true) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($s['full_name']); ?> — <?php echo htmlspecialchars($s['email']); ?><?php echo $s['course_titles'] ? ' (' . htmlspecialchars($s['course_titles']) . ')' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple students. A tutor can have as many students as needed.</small>
        <?php if (empty($students)): ?><p class="text-muted small mb-0 mt-1">No registered students yet.</p><?php endif; ?>
    </div>
    <?php if (!empty($tutor['courses'])): ?>
    <div class="col-12">
        <label class="form-label mb-1">Courses (derived from assigned students)</label><br>
        <?php foreach ($tutor['courses'] as $c): ?>
            <span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($c['title']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <script>
    document.getElementById('studentFilter')?.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#studentSelect option').forEach(function (opt) {
            opt.hidden = q !== '' && (opt.dataset.search || '').indexOf(q) === -1;
        });
    });
    </script>
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
