<?php
// public/admin/courses/edit.php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);

$id = (int) ($_GET['id'] ?? 0);
$course = $courseModel->getById($id);
if (!$course) {
    SessionModel::setFlash('error', 'Course not found.');
    header('Location: ' . url('admin/courses/index.php'));
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please try again.';
    } else {
        $coverPath = null;

        if (!empty($_FILES['cover_image']['name'])) {
            $upload = upload_image(
                $_FILES['cover_image'],
                __DIR__ . '/../../uploads/courses/covers',
                url('uploads/courses/covers')
            );
            if (!$upload['ok']) {
                $error = $upload['error'];
            } else {
                $coverPath = $upload['path'];
            }
        }

        if (!$error) {
            $result = $courseModel->update($id, [
                'title'           => $_POST['title'] ?? '',
                'abbreviation'    => $_POST['abbreviation'] ?? '',
                'description'     => $_POST['description'] ?? '',
                'overview'        => $_POST['overview'] ?? '',
                'duration_months' => $_POST['duration_months'] ?? 0,
                'course_fee'      => $_POST['course_fee'] ?? 0,
                'cover_image'     => $coverPath,
            ]);

            if ($result['ok']) {
                SessionModel::setFlash('success', 'Course updated successfully.');
                header('Location: ' . url('admin/courses/index.php'));
                exit();
            }
            $error = $result['error'];
        }
        // Refresh local copy so the form reflects what was just attempted
        $course = array_merge($course, $_POST);
    }
}

$page_title = 'Edit Course';
$breadcrumbs = [
    ['label' => 'Courses', 'url' => url('admin/courses/index.php')],
    ['label' => 'Edit', 'active' => true],
];

ob_start();
?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Edit Course</h5></div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ((int) $course['last_sequence'] > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <?php echo (int) $course['last_sequence']; ?> student ID(s) have already been issued under this course's
                abbreviation. Changing the abbreviation will <strong>not</strong> rename IDs already issued.
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Course Title</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo htmlspecialchars($course['title']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Abbreviation</label>
                    <input type="text" name="abbreviation" class="form-control" required maxlength="10"
                           value="<?php echo htmlspecialchars($course['abbreviation']); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Duration (months)</label>
                    <input type="number" name="duration_months" class="form-control" min="1" max="36" required
                           value="<?php echo htmlspecialchars($course['duration_months']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course Fee (KSh)</label>
                    <input type="number" name="course_fee" class="form-control" min="1" step="1" required
                           value="<?php echo htmlspecialchars($course['course_fee']); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Cover Image</label>
                    <?php if (!empty($course['cover_image'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo htmlspecialchars(settings_asset_url($course['cover_image'])); ?>"
                                 style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted">Leave blank to keep the current image.</small>
                </div>

                <div class="col-12">
                    <label class="form-label">Overview</label>
                    <textarea name="overview" class="form-control" rows="2"><?php echo htmlspecialchars($course['overview']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Full Description</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars($course['description']); ?></textarea>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Course</button>
                <a href="<?php echo url('admin/courses/index.php'); ?>" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';