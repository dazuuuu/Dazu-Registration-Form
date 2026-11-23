<?php
// public/admin/courses/brochure.php — the site-wide default course brochure (PDF).
// Served to visitors on any course page that has no brochure of its own; see course_brochure_url()
// and course_brochure_absolute_path() in app/v1/helpers/course_fee_structure.php.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_fee_structure.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\CourseModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$settings = new SettingModel($pdo);
$self = url('admin/courses/brochure.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self);
        exit();
    }
    if (empty($_FILES['brochure']['name'])) {
        SessionModel::setFlash('error', 'Choose a PDF to upload.');
        header('Location: ' . $self);
        exit();
    }
    $upload = upload_pdf(
        $_FILES['brochure'],
        __DIR__ . '/../../uploads/courses/brochures',
        'uploads/courses/brochures'
    );
    if (!$upload['ok']) {
        SessionModel::setFlash('error', $upload['error']);
        header('Location: ' . $self);
        exit();
    }
    $old = $settings->get('default_course_brochure');
    if ($old) {
        $oldAbs = course_brochure_resolve_abs($old);
        if ($oldAbs) {
            @unlink($oldAbs);
        }
    }
    $settings->set('default_course_brochure', $upload['path']);
    SessionModel::setFlash('success', 'Default brochure saved.');
    header('Location: ' . $self);
    exit();
}

if (($_GET['action'] ?? '') === 'remove') {
    if (!csrf_verify($_GET['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self);
        exit();
    }
    $old = $settings->get('default_course_brochure');
    if ($old) {
        $oldAbs = course_brochure_resolve_abs($old);
        if ($oldAbs) {
            @unlink($oldAbs);
        }
    }
    $settings->set('default_course_brochure', null);
    SessionModel::setFlash('success', 'Default brochure removed.');
    header('Location: ' . $self);
    exit();
}

$currentPath = $settings->get('default_course_brochure');
$currentUrl = $currentPath ? settings_asset_url($currentPath) : null;

$courseModel = new CourseModel($pdo);
$missingCount = 0;
try {
    foreach ($courseModel->getAll(false) as $c) {
        if (empty($c['brochure_path'])) {
            $missingCount++;
        }
    }
} catch (Throwable $e) {
    $missingCount = 0;
}

$page_title = 'Brochure';
$breadcrumbs = [
    ['label' => 'Courses', 'url' => url('admin/courses/index.php')],
    ['label' => 'Brochure', 'active' => true],
];
ob_start();
?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Default course brochure</h5></div>
    <div class="card-body">
        <p class="text-muted small">
            Some courses don't have their own brochure uploaded on their edit page. Upload one PDF here and it is used
            as the fallback: the "Download course brochure" button on those courses' pages serves this file instead
            of showing nothing.
            <?php if ($missingCount > 0): ?>
                <strong><?php echo (int) $missingCount; ?> course<?php echo $missingCount === 1 ? '' : 's'; ?></strong>
                currently have no brochure of their own and would use this default.
            <?php endif; ?>
        </p>

        <?php if ($currentUrl): ?>
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="<?php echo htmlspecialchars($currentUrl); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-file-pdf me-1"></i>Current default brochure
                </a>
                <a href="<?php echo htmlspecialchars($self . '?action=remove&csrf_token=' . urlencode(csrf_token())); ?>"
                   class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove the default brochure? Courses with no brochure of their own will show no download button again.');">
                    <i class="fas fa-trash me-1"></i>Remove
                </a>
            </div>
        <?php else: ?>
            <p class="small text-muted">No default brochure uploaded yet.</p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Upload a new default brochure (PDF)</label>
                <input type="file" name="brochure" class="form-control" accept="application/pdf" required>
            </div>
            <button class="btn btn-success"><i class="fas fa-upload me-1"></i>Save default brochure</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
