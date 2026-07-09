<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProjectModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentProjectController.php';

use Models\Connection;
use Models\StudentModel;

Middleware::requireStudent();

$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$projectController = new StudentProjectController($pdo);
$userId = (int) $_SESSION['user_id'];

$reg = $studentModel->getByUserId($userId);
if (!$reg) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}

$portal_locked = (bool) ($reg['portal_locked'] ?? false);
$canPost = !empty($reg['can_post_project']);
$myProjects = [];

try {
    require_once __DIR__ . '/../../../app/v1/models/ProjectModel.php';
    $projectModel = new ProjectModel($pdo);
    $myProjects = $projectModel->getStudentProjectsByRegistration((int) $reg['id']);
} catch (Throwable $e) {
    $schemaError = 'Project submissions are not available yet. Ask admin to run migration 021_student_projects.sql.';
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$portal_locked) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (!$canPost) {
        $error = 'Your tutor has not enabled project submissions for your account yet.';
    } else {
        $result = $projectController->createSubmission($reg, $_POST, $_FILES);
        if ($result['success']) {
            SessionModel::setFlash('success', $result['message']);
            header('Location: ' . url('students/projects/index.php'));
            exit();
        }
        $error = $result['message'];
    }
}

$page_title = 'My Projects';
ob_start();
?>

<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">My Projects</h1>
            <p class="text-muted mb-0">Submit your portfolio work for tutor review. Approved projects appear on the public Projects page under <strong>Student Projects</strong>.</p>
        </div>
        <a href="<?php echo url('students/dashboard/index.php'); ?>" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
    </div>

    <?php if (!empty($schemaError)): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($schemaError); ?></div>
    <?php endif; ?>

    <?php if ($flash = SessionModel::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($portal_locked): ?>
        <div class="alert alert-warning">Your portal is locked. Complete your payment to submit projects.</div>
    <?php elseif (!$canPost): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Project submissions are disabled. Ask your tutor to enable posting from your student profile.
        </div>
    <?php else: ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-semibold">Submit a New Project</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Your Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($reg['full_name']); ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year of Study *</label>
                            <input type="text" name="year_of_study" class="form-control" placeholder="e.g. Year 1, 2025" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Project Title *</label>
                            <input type="text" name="project_title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Project Details *</label>
                            <textarea name="details" class="form-control" rows="5" required placeholder="Describe what you built, tools used, and outcomes."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project Link</label>
                            <input type="url" name="project_link" class="form-control" placeholder="https://github.com/... or live demo URL">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Skillset</label>
                            <input type="text" name="skillset" class="form-control" placeholder="HTML, CSS, JavaScript, PHP">
                            <div class="form-text">Comma-separated skills</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project Cover Image *</label>
                            <input type="file" name="cover_image" class="form-control" accept="image/*" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gallery Images</label>
                            <input type="file" name="gallery_images[]" class="form-control" accept="image/*" multiple>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-4">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Review
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Your Submissions</div>
        <div class="card-body p-0">
            <?php if (empty($myProjects)): ?>
                <p class="text-muted p-3 mb-0">No project submissions yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Project</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myProjects as $project): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['small_title']); ?></strong>
                                    <?php if (!empty($project['project_link'])): ?>
                                        <div><a href="<?php echo htmlspecialchars($project['project_link']); ?>" target="_blank" rel="noopener">View link</a></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($project['year_of_study'] ?? '—'); ?></td>
                                <td>
                                    <?php if ($project['status'] === 'published'): ?>
                                        <span class="badge bg-success">Published</span>
                                    <?php elseif ($project['status'] === 'draft'): ?>
                                        <span class="badge bg-warning text-dark">Pending review</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($project['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($project['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
