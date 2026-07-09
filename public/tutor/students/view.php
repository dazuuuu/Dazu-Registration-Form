<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProjectModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\ProgressModel;
use Models\CurriculumModel;
use Models\SubmissionModel;
use Models\ProjectModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$regId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT r.*, c.title AS course_title FROM registrations r JOIN courses c ON r.course_id=c.id WHERE r.id=:id AND r.status='approved'");
$stmt->execute([':id'=>$regId]);
$student = $stmt->fetch();
if (!$student || !$tutorModel->tutorTeachesCourse((int)$tutor['id'], (int)$student['course_id'])) {
    header('Location: index.php'); exit();
}

$progress = (new ProgressModel($pdo))->getProgress($regId, (int)$student['course_id']);
$submissions = (new SubmissionModel($pdo))->getForRegistration($regId);
$studentProjects = [];
try {
    $studentProjects = (new ProjectModel($pdo))->getStudentProjectsByRegistration($regId);
} catch (Throwable $e) {
    $studentProjects = [];
}
$canPostProject = !empty($student['can_post_project']);
$page_title = $student['full_name'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-3">← All students</a>
<div class="card-tutor mb-3"><div class="head">Student Info</div><div class="p-3">
<strong><?php echo htmlspecialchars($student['full_name']); ?></strong> (<?php echo htmlspecialchars($student['registration_no']); ?>)<br>
<?php echo htmlspecialchars($student['email']); ?> · <?php echo htmlspecialchars($student['course_title']); ?>
</div></div>
<div class="card-tutor mb-3"><div class="head">Unit Progress</div><div class="p-0">
<table class="table mb-0"><thead class="table-light"><tr><th>Unit</th><th>Test</th><th>Project</th><th>Status</th></tr></thead><tbody>
<?php foreach ($progress as $p): ?>
<tr>
<td><?php echo htmlspecialchars($p['title']); ?></td>
<td><?php echo $p['test_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
<td><?php echo $p['project_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
<td><?php echo $p['completed_at'] ? '<span class="badge bg-success">Complete</span>' : ($p['is_unlocked'] ? '<span class="badge bg-primary">In progress</span>' : '<span class="badge bg-secondary">Locked</span>'); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<div class="card-tutor mb-3">
    <div class="head d-flex justify-content-between align-items-center">
        <span>Portfolio Projects</span>
        <div class="form-check form-switch m-0">
            <input class="form-check-input" type="checkbox" id="canPostProject" <?php echo $canPostProject ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="canPostProject">Allow student to post projects</label>
        </div>
    </div>
    <div class="p-3">
        <p class="text-muted small mb-3">When enabled, this student can submit projects from their portal. You approve submissions before they appear publicly under Student Projects.</p>
        <?php if (empty($studentProjects)): ?>
            <p class="text-muted mb-0">No student project submissions yet.</p>
        <?php else: ?>
            <div class="list-group list-group-flush border rounded">
                <?php foreach ($studentProjects as $project): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2" id="student-project-<?php echo (int) $project['id']; ?>">
                    <div>
                        <strong><?php echo htmlspecialchars($project['small_title']); ?></strong>
                        <div class="small text-muted">
                            Year <?php echo htmlspecialchars($project['year_of_study'] ?? '—'); ?>
                            · <?php echo $project['status'] === 'published' ? 'Published' : 'Pending review'; ?>
                        </div>
                    </div>
                    <?php if ($project['status'] === 'draft'): ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" onclick="reviewStudentProject(<?php echo (int) $project['id']; ?>, 'approve')">Approve</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="reviewStudentProject(<?php echo (int) $project['id']; ?>, 'reject')">Reject</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="card-tutor"><div class="head">Submissions</div>
<?php if (empty($submissions)): ?><p class="p-3 text-muted mb-0">No submissions yet.</p>
<?php else: ?><div class="list-group list-group-flush">
<?php foreach ($submissions as $sub): ?>
<div class="list-group-item d-flex justify-content-between">
<span><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars($sub['item_title']); ?>
<span class="badge bg-<?php echo $sub['status']==='graded'?'success':'warning'; ?> ms-2"><?php echo $sub['status']; ?></span>
<?php if ($sub['marks']!==null): ?> — <?php echo $sub['marks']; ?> marks<?php endif; ?>
</span>
<?php if ($sub['status']==='submitted'): ?><a href="<?php echo url('tutor/submissions/index.php#sub-' . (int)$sub['id']); ?>" class="btn btn-sm btn-brand">Grade</a><?php endif; ?>
</div>
<?php endforeach; ?>
</div><?php endif; ?>
</div>

<script>
document.getElementById('canPostProject')?.addEventListener('change', async (e) => {
    try {
        const response = await fetch(url('api/tutor/toggle_project_permission.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                registration_id: <?php echo (int) $regId; ?>,
                allowed: e.target.checked ? 1 : 0
            })
        });
        const result = await response.json();
        if (!result.success) {
            alert(result.message || 'Could not update permission');
            e.target.checked = !e.target.checked;
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred.');
        e.target.checked = !e.target.checked;
    }
});

async function reviewStudentProject(projectId, action) {
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
            location.reload();
        } else {
            alert(result.message || 'Action failed');
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred.');
    }
}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
