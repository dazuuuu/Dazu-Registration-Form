<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\EmployeeModel;

Middleware::requireEmployee();

$pdo = Connection::getInstance()->getConnection();
$employees = new EmployeeModel($pdo);
$userId = (int)$_SESSION['user_id'];
$emp = $employees->isInstalled() ? $employees->getByUserId($userId) : null;
if (!$emp) {
    http_response_code(404);
    exit('No employee record was found for this account. Please contact the administrator.');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            $up = upload_image($_FILES['photo'], __DIR__ . '/../../uploads/employees', url('uploads/employees'));
            if ($up['ok']) $photoPath = $up['path']; else $error = $up['error'];
        }
        if (!$error) {
            $employees->updateProfile($userId, (string)($_POST['phone'] ?? ''), (string)($_POST['bio'] ?? ''), $photoPath);
            SessionModel::setFlash('success', 'Profile saved.');
            header('Location: index.php');
            exit();
        }
    }
    $emp = $employees->getByUserId($userId);
}

$page_title = 'My profile';
ob_start();
?>
<h4 class="mb-4">My profile</h4>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="row g-3">
<div class="col-lg-8"><div class="card-staff"><div class="head">Details</div><div class="p-3">
<form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" value="<?php echo htmlspecialchars($emp['full_name']); ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Role</label><input class="form-control" value="<?php echo htmlspecialchars($emp['job_title']); ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?php echo htmlspecialchars($emp['email']); ?>" disabled></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="30" value="<?php echo htmlspecialchars((string)($emp['phone'] ?? '')); ?>"></div>
    <div class="col-12"><label class="form-label">Profile picture</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
    <div class="col-12"><label class="form-label">About me</label><textarea name="bio" rows="3" class="form-control"><?php echo htmlspecialchars((string)($emp['bio'] ?? '')); ?></textarea></div>
</div>
<button class="btn btn-brand mt-3">Save profile</button>
<a href="<?php echo url('auth/change_password.php'); ?>" class="btn btn-outline-secondary mt-3">Change password</a>
<div class="text-muted small mt-2">Your name, role and salary are set by the administrator.</div>
</form>
</div></div></div>
<div class="col-lg-4"><div class="card-staff"><div class="head">Photo</div><div class="p-3 text-center">
    <?php if (!empty($emp['photo_path'])): ?><img src="<?php echo htmlspecialchars($emp['photo_path']); ?>" class="rounded img-fluid" style="max-height:240px" alt="">
    <?php else: ?><div class="text-muted">No picture yet.</div><?php endif; ?>
</div></div></div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/employee/layout.php';
