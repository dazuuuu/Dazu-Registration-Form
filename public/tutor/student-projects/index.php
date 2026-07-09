<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProjectModel.php';

use Models\Connection;
use Models\TutorModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$projectModel = new ProjectModel($pdo);
$tutor = $tutorModel->getByUserId((int) $_SESSION['user_id']);

if (!$tutor) {
    header('Location: ' . url('tutor/dashboard/index.php'));
    exit();
}

$pending = [];
try {
    $pending = $projectModel->getPendingStudentProjectsForTutor((int) $tutor['id']);
} catch (Throwable $e) {
    $schemaError = 'Run migration 021_student_projects.sql to enable student project reviews.';
}

$page_title = 'Student Project Reviews';
ob_start();
?>

<a href="<?php echo url('tutor/dashboard/index.php'); ?>" class="btn btn-link btn-sm ps-0 mb-3">← Dashboard</a>

<h1 class="h4 mb-3">Student Project Reviews</h1>
<p class="text-muted">Approve student submissions to publish them under the <strong>Student Projects</strong> category on the public projects page.</p>

<?php if (!empty($schemaError)): ?>
    <div class="alert alert-warning"><?php echo htmlspecialchars($schemaError); ?></div>
<?php endif; ?>

<?php if (empty($pending)): ?>
    <div class="card-tutor"><div class="p-4 text-muted mb-0">No pending student projects.</div></div>
<?php else: ?>
    <div class="card-tutor">
        <div class="list-group list-group-flush">
            <?php foreach ($pending as $project): ?>
            <div class="list-group-item" id="project-<?php echo (int) $project['id']; ?>">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <strong><?php echo htmlspecialchars($project['small_title']); ?></strong>
                        <div class="text-muted small">
                            <?php echo htmlspecialchars($project['full_name']); ?>
                            · <?php echo htmlspecialchars($project['course_title']); ?>
                            · Year <?php echo htmlspecialchars($project['year_of_study'] ?? '—'); ?>
                        </div>
                        <?php if (!empty($project['skillset'])): ?>
                            <div class="small mt-1"><strong>Skills:</strong> <?php echo htmlspecialchars($project['skillset']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($project['project_link'])): ?>
                            <div class="small"><a href="<?php echo htmlspecialchars($project['project_link']); ?>" target="_blank" rel="noopener">Project link</a></div>
                        <?php endif; ?>
                        <p class="mb-0 mt-2 small"><?php echo nl2br(htmlspecialchars(mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 220, '…'))); ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (!empty($project['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($project['cover_image']); ?>" alt="" style="width:88px;height:66px;object-fit:cover;border-radius:8px;">
                        <?php endif; ?>
                        <div class="d-flex flex-column gap-2">
                            <button type="button" class="btn btn-sm btn-success" onclick="reviewProject(<?php echo (int) $project['id']; ?>, 'approve')">Approve</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="reviewProject(<?php echo (int) $project['id']; ?>, 'reject')">Reject</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
async function reviewProject(projectId, action) {
    const label = action === 'approve' ? 'approve and publish' : 'reject and remove';
    if (!confirm(`Are you sure you want to ${label} this project?`)) {
        return;
    }

    try {
        const response = await fetch(url('api/tutor/student_project_action.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ project_id: projectId, action })
        });
        const result = await response.json();
        if (result.success) {
            document.getElementById('project-' + projectId)?.remove();
            if (!document.querySelector('.list-group-item')) {
                location.reload();
            }
        } else {
            alert(result.message || 'Action failed');
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred.');
    }
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
