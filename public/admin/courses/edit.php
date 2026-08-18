<?php
// public/admin/courses/edit.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseCategoryModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';

use Models\Connection;
use Models\CourseModel;
use Models\CourseCategoryModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);
try {
    $categories = $categoryModel->getAll(false);
} catch (Throwable $e) {
    $categories = [];
}

$id = (int) ($_GET['id'] ?? 0);
$course = $courseModel->getById($id);
if (!$course) {
    SessionModel::setFlash('error', 'Course not found.');
    header('Location: ' . url('admin/courses/index.php'));
    exit();
}
// Free and paid courses are separate admin pages, not one form with a toggle —
// send a free course here (e.g. an old bookmark) to its own edit page instead.
if (!empty($course['is_free'])) {
    header('Location: ' . url('admin/free-courses/edit.php?id=' . $id));
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
            $feeItems = course_fee_structure_from_post($_POST);
            $feeTotal = course_fee_structure_total($feeItems);
            $result = $courseModel->update($id, [
                'title'           => $_POST['title'] ?? '',
                'abbreviation'    => $_POST['abbreviation'] ?? '',
                'category_id'     => $_POST['category_id'] ?? null,
                'description'     => $_POST['description'] ?? '',
                'requirements'    => $_POST['requirements'] ?? '',
                'overview'        => $_POST['overview'] ?? '',
                'duration_months' => $_POST['duration_months'] ?? 0,
                'weeks_of_study'  => $_POST['weeks_of_study'] ?? null,
                'days_of_study'   => $_POST['days_of_study'] ?? null,
                'hours_of_study'  => $_POST['hours_of_study'] ?? null,
                'study_schedule'  => $_POST['study_schedule'] ?? null,
                'course_fee'      => $feeTotal > 0 ? $feeTotal : ($_POST['course_fee'] ?? 0),
                'fee_structure_items' => $feeItems,
                'is_free'         => false,
                'cover_image'     => $coverPath,
            ]);

            if ($result['ok']) {
                $notified = 0;
                try {
                    // No job queue in this codebase — sending is synchronous, so give it
                    // room to work through the whole student roster before PHP's default
                    // 30s execution limit kills the request mid-broadcast.
                    set_time_limit(0);
                    require_once __DIR__ . '/../../../app/v1/services/CourseUpdateNotifier.php';
                    $updatedCourse = $courseModel->getById($id);
                    if ($updatedCourse) {
                        $notifier = new \CourseUpdateNotifier($pdo, true);
                        $notifyResult = $notifier->notifyStudentsOfUpdate($updatedCourse);
                        $notified = $notifyResult['sent'] ?? 0;
                    }
                } catch (\Throwable $e) {
                    error_log('[admin/courses/edit] course update notification failed: ' . $e->getMessage());
                }
                SessionModel::setFlash('success', "Course updated successfully. Notified {$notified} student(s).");
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
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int)($course['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Study duration</label>
                    <p class="text-muted small mb-2">Enter <strong>at least one</strong> — hours only, days only, weeks only, or months only are all valid. Leave a field blank if it does not apply.</p>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hours of study</label>
                    <input type="number" name="hours_of_study" class="form-control" min="1" step="1"
                           placeholder="e.g. 1" value="<?php echo htmlspecialchars($course['hours_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Days of study</label>
                    <input type="number" name="days_of_study" class="form-control" min="1" step="1"
                           placeholder="e.g. 2" value="<?php echo htmlspecialchars($course['days_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Weeks of study</label>
                    <input type="number" name="weeks_of_study" class="form-control" min="1" step="1"
                           placeholder="Optional" value="<?php echo htmlspecialchars($course['weeks_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Months of study</label>
                    <input type="number" name="duration_months" class="form-control" min="1" max="36" step="1"
                           placeholder="e.g. 6" value="<?php echo htmlspecialchars((int)$course['duration_months'] > 0 ? $course['duration_months'] : ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Study schedule <span class="text-muted small">(optional notes)</span></label>
                    <input type="text" name="study_schedule" class="form-control" maxlength="150"
                           placeholder="e.g. Mon, Wed, Fri evenings" value="<?php echo htmlspecialchars($course['study_schedule'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course Fee (KSh)</label>
                    <input type="number" name="course_fee" class="form-control" min="1" step="1" required
                           value="<?php echo htmlspecialchars($course['course_fee']); ?>">
                    <small class="text-muted">Filled automatically from the fee structure total when line items are entered.</small>
                </div>

                <?php
                $fee_items = $_SERVER['REQUEST_METHOD'] === 'POST'
                    ? course_fee_structure_from_post($_POST)
                    : course_fee_structure_items($course);
                include __DIR__ . '/../../components/admin/course_fee_structure_fields.php';
                ?>
                <?php if ((int) $course['id'] > 0): ?>
                    <div class="col-12">
                        <a href="<?php echo htmlspecialchars(course_fee_structure_download_url((int) $course['id'], true)); ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download me-1"></i> Download fee structure
                        </a>
                    </div>
                <?php endif; ?>

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
                <div class="col-12">
                    <label class="form-label">Requirements <span class="text-muted small">(one per line, optional)</span></label>
                    <textarea name="requirements" class="form-control" rows="3" placeholder="e.g.&#10;A laptop&#10;A good and stable internet connection"><?php echo htmlspecialchars($course['requirements'] ?? ''); ?></textarea>
                    <small class="text-muted">Leave blank to show the default: "A laptop" and "A good and stable internet connection".</small>
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