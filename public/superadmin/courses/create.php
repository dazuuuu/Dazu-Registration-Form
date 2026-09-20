<?php
// public/admin/courses/create.php
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

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);
try {
    $categories = $categoryModel->getAll(false);
} catch (Throwable $e) {
    $categories = [];
}

$error = null;
$old = ['title' => '', 'abbreviation' => '', 'description' => '', 'requirements' => '', 'overview' => '', 'duration_months' => '', 'weeks_of_study' => '', 'days_of_study' => '', 'hours_of_study' => '', 'study_schedule' => '', 'semester_count' => '', 'course_fee' => '', 'category_id' => '', 'show_on_homepage' => 0];
$fee_modes = ['online' => [], 'physical' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please try again.';
    } else {
        $old = $_POST;
        $fee_modes = course_fee_structure_modes_from_post($_POST);
        $coverPath = null;
        $brochurePath = null;

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

        if (!$error && !empty($_FILES['brochure']['name'])) {
            $brochure = upload_pdf(
                $_FILES['brochure'],
                __DIR__ . '/../../uploads/courses/brochures',
                'uploads/courses/brochures'
            );
            if (!$brochure['ok']) {
                $error = $brochure['error'];
            } else {
                $brochurePath = $brochure['path'];
            }
        }

        if (!$error) {
            $feeTotal = course_fee_structure_catalog_total($fee_modes, $_POST['course_fee'] ?? 0);
            $result = $courseModel->create([
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
                'semester_count'  => $_POST['semester_count'] ?? 1,
                'course_fee'      => $feeTotal > 0 ? $feeTotal : ($_POST['course_fee'] ?? 0),
                'fee_structure_modes' => $fee_modes,
                'is_free'         => false,
                'cover_image'     => $coverPath,
                'brochure_path'   => $brochurePath,
                'show_on_homepage' => isset($_POST['show_on_homepage']) ? 1 : 0,
            ], SessionModel::get('user_id'));

            if ($result['ok']) {
                SessionModel::setFlash('success', 'Course created successfully.');
                header('Location: ' . url('superadmin/courses/index.php'));
                exit();
            }
            $error = $result['error'];
        }
    }
}

$page_title = 'Add Course';
$breadcrumbs = [
    ['label' => 'Courses', 'url' => url('superadmin/courses/index.php')],
    ['label' => 'Add Course', 'active' => true],
];

ob_start();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add Course</h5>
        <div class="d-flex gap-2">
            <a href="<?php echo url('superadmin/courses/categories.php'); ?>" class="btn btn-sm btn-outline-secondary">Manage Categories</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Course Title</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo htmlspecialchars($old['title']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Abbreviation</label>
                    <input type="text" name="abbreviation" class="form-control" required maxlength="10"
                           placeholder="e.g. PY" value="<?php echo htmlspecialchars($old['abbreviation']); ?>">
                    <small class="text-muted">Used in student ID numbers (e.g. DH-PY01/2026). Choose carefully — changing it later won't update IDs already issued.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int) $cat['id']; ?>" <?php echo (string)($old['category_id'] ?? '') === (string)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($categories)): ?>
                        <small class="text-muted"><a href="<?php echo url('superadmin/courses/categories.php'); ?>">Create categories</a> for homepage tabs.</small>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label">Study duration</label>
                    <p class="text-muted small mb-2">Enter <strong>at least one</strong> — a course can be hours only (e.g. 1 hr), days only (e.g. 2 days), weeks only, or months only. Leave a field blank if it does not apply.</p>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hours of study</label>
                    <input type="number" name="hours_of_study" class="form-control" min="1" step="1"
                           placeholder="e.g. 1" value="<?php echo htmlspecialchars($old['hours_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Days of study</label>
                    <input type="number" name="days_of_study" class="form-control" min="1" step="1"
                           placeholder="e.g. 2" value="<?php echo htmlspecialchars($old['days_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Weeks of study</label>
                    <input type="number" name="weeks_of_study" class="form-control" min="1" step="1"
                           placeholder="Optional" value="<?php echo htmlspecialchars($old['weeks_of_study'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Months of study</label>
                    <input type="number" name="duration_months" class="form-control" min="1" max="36" step="1"
                           placeholder="e.g. 6" value="<?php echo htmlspecialchars($old['duration_months'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Study schedule <span class="text-muted small">(optional notes)</span></label>
                    <input type="text" name="study_schedule" class="form-control" maxlength="150"
                           placeholder="e.g. Mon, Wed, Fri evenings" value="<?php echo htmlspecialchars($old['study_schedule'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Number of semesters</label>
                    <input type="number" name="semester_count" class="form-control" min="1" max="40" step="1"
                           placeholder="e.g. 6" value="<?php echo htmlspecialchars($old['semester_count'] ?? ''); ?>">
                    <small class="text-muted">Used to calculate per-semester fees when the per-semester field is blank.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course Fee (KSh)</label>
                    <input type="number" name="course_fee" class="form-control" min="1" step="1" required
                           value="<?php echo htmlspecialchars($old['course_fee'] ?? ''); ?>">
                    <small class="text-muted">Filled automatically from the fee structure total when line items are entered.</small>
                </div>

                <?php include __DIR__ . '/../../components/superadmin/course_fee_structure_fields.php'; ?>

                <div class="col-12">
                    <label class="form-label">Cover Image</label>
                    <input type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="col-12">
                    <label class="form-label">Course brochure (PDF)</label>
                    <input type="file" name="brochure" class="form-control" accept="application/pdf">
                    <small class="text-muted">Optional. Students can download this from the course page.</small>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch p-3 bg-light rounded border">
                        <input class="form-check-input" type="checkbox" name="show_on_homepage" id="show_on_homepage" value="1"
                               <?php echo !empty($old['show_on_homepage']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark ms-2" for="show_on_homepage">
                            <i class="fas fa-home text-primary me-1"></i> Show this course on the front-page (Homepage)
                        </label>
                        <div class="form-text text-muted ps-1">When enabled, this course is featured directly on the front-page. All other courses appear when visitors click "View All Courses".</div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Overview</label>
                    <textarea name="overview" class="form-control" rows="2" placeholder="One or two lines shown on the course card"><?php echo htmlspecialchars($old['overview']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Full Description</label>
                    <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars($old['description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Requirements <span class="text-muted small">(one per line, optional)</span></label>
                    <textarea name="requirements" class="form-control" rows="3" placeholder="e.g.&#10;A laptop&#10;A good and stable internet connection"><?php echo htmlspecialchars($old['requirements'] ?? ''); ?></textarea>
                    <small class="text-muted">Leave blank to show the default: "A laptop" and "A good and stable internet connection".</small>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Course</button>
                <a href="<?php echo url('superadmin/courses/index.php'); ?>" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
