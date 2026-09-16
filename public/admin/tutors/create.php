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
$students = $tutorModel->getRegisteredStudents();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/tutors', url('uploads/tutors'));
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
            ], $_POST['student_ids'] ?? [], isset($_POST['send_email']));
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
    <div class="col-12"><label class="form-label">Assign Students (select multiple)</label>
        <input type="text" class="form-control mb-2" id="studentFilter" placeholder="Filter students by name, email or course...">
        <select name="student_ids[]" id="studentSelect" class="form-select" multiple size="8">
            <?php foreach ($students as $s): ?>
                <option value="<?php echo (int)$s['user_id']; ?>" data-search="<?php echo htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['email'] . ' ' . $s['course_titles'])); ?>">
                    <?php echo htmlspecialchars($s['full_name']); ?> — <?php echo htmlspecialchars($s['email']); ?><?php echo $s['course_titles'] ? ' (' . htmlspecialchars($s['course_titles']) . ')' : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple students. A tutor can have as many students as needed.</small>
        <?php if (empty($students)): ?><p class="text-muted small mb-0 mt-1">No registered students yet.</p><?php endif; ?>
    </div>
    <script>
    document.getElementById('studentFilter')?.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#studentSelect option').forEach(function (opt) {
            opt.hidden = q !== '' && (opt.dataset.search || '').indexOf(q) === -1;
        });
    });
    </script>
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
