<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/TutorModel.php';
require_once __DIR__ . '/../../../app/models/CourseModel.php';
require_once __DIR__ . '/../../../app/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\CourseModel;
use Controllers\TutorController;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$courseModel = new CourseModel($pdo);
$courses = $courseModel->getAll(true);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/tutors', '/Registration-System/public/uploads/tutors');
            if (!$up['ok']) $error = $up['error'];
            else $photoPath = $up['path'];
        }
        if (!$error) {
            $ctrl = new TutorController();
            $result = $ctrl->createAndEmail([
                'full_name'   => $_POST['full_name'] ?? '',
                'email'       => $_POST['email'] ?? '',
                'phone'       => $_POST['phone'] ?? '',
                'bio'         => $_POST['bio'] ?? '',
                'photo_path'  => $photoPath,
            ], $_POST['course_ids'] ?? [], isset($_POST['send_email']));
            if ($result['ok']) {
                $msg = 'Tutor created.';
                if (!empty($result['email_sent'])) $msg .= ' Login credentials emailed.';
                elseif (!empty($result['email_error'])) $msg .= ' Warning: ' . $result['email_error'];
                SessionModel::setFlash('success', $msg);
                header('Location: /Registration-System/public/admin/tutors/view.php?id=' . $result['tutor_id']);
                exit();
            }
            $error = $result['error'];
        }
    }
}

$page_title = 'Add Tutor';
$breadcrumbs = [['label'=>'Tutors','url'=>'index.php'],['label'=>'Add','active'=>true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="POST" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
    <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" class="form-control" rows="3"></textarea></div>
    <div class="col-12"><label class="form-label">Assign Courses (select multiple)</label>
        <select name="course_ids[]" class="form-select" multiple size="6">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple courses.</small>
    </div>
    <div class="col-12"><div class="form-check">
        <input class="form-check-input" type="checkbox" name="send_email" value="1" id="sendEmail" checked>
        <label class="form-check-label" for="sendEmail">Email login credentials to tutor</label>
    </div></div>
</div>
<button type="submit" class="btn btn-success mt-3"><i class="fas fa-save me-1"></i> Create Tutor</button>
<a href="index.php" class="btn btn-link">Cancel</a>
</form>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
