<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;
use Models\TutorCourseModel;
use Controllers\TutorController;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutor = (new TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
$tc = new TutorCourseModel($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $ctrl = new TutorController();
    $subId = (int) $_POST['submission_id'];
    $r =($_POST['kind'] ?? '') === 'module'
        ? $ctrl->gradeModuleSubmission($subId, (int) $_SESSION['user_id'], (float) $_POST['marks'], trim($_POST['feedback'] ?? ''))
        : $ctrl->gradeSubmission($subId, (int) $_SESSION['user_id'], (float) $_POST['marks'], trim($_POST['feedback'] ?? ''));
    if ($r['ok']) { SessionModel::setFlash('success', 'Graded. Student notified by email.'); header('Location: index.php'); exit(); }
    $error = $r['error'];
}

$moduleSubs = $tc->pendingForTutor((int) $tutor['user_id']);
$legacySubs = (new SubmissionModel($pdo))->getPendingForTutor((int) $tutor['id']);
$page_title = 'Grade Submissions';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (!$moduleSubs && !$legacySubs): ?><div class="card-tutor"><p class="p-3 text-muted mb-0">No submissions awaiting grading.</p></div><?php endif; ?>

<?php foreach ($moduleSubs as $sub): ?>
<div class="card-tutor mb-3" id="sub-<?php echo (int) $sub['id']; ?>">
    <div class="head d-flex justify-content-between flex-wrap gap-2">
        <span><?php echo htmlspecialchars($sub['module_title']); ?> · <?php echo htmlspecialchars($sub['item_title']); ?></span>
        <span class="small"><?php echo htmlspecialchars(TutorCourseModel::ANSWER_MODES[$sub['answer_mode']] ?? ''); ?></span>
    </div>
    <div class="p-3">
        <p class="mb-1"><strong><?php echo htmlspecialchars($sub['full_name']); ?></strong> (<?php echo htmlspecialchars($sub['registration_no']); ?>) · <?php echo htmlspecialchars($sub['course_title']); ?></p>
        <p class="small text-muted">Submitted <?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?></p>
        <?php if ($sub['question_text']): ?><div class="small text-muted mb-2" style="white-space:pre-wrap"><strong>Question:</strong> <?php echo htmlspecialchars($sub['question_text']); ?></div><?php endif; ?>
        <div class="bg-light p-3 rounded mb-3">
            <?php if ($sub['answer_text']): ?><div style="white-space:pre-wrap"><?php echo htmlspecialchars($sub['answer_text']); ?></div><?php endif; ?>
            <?php if ($sub['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($sub['file_path'])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-download me-1"></i>Download submitted file</a><?php endif; ?>
            <?php if ($sub['github_url']): ?><a href="<?php echo htmlspecialchars($sub['github_url']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-dark mt-2"><i class="fab fa-github me-1"></i><?php echo htmlspecialchars($sub['github_url']); ?></a><?php endif; ?>
        </div>
        <form method="POST"><?php echo csrf_field(); ?>
            <input type="hidden" name="submission_id" value="<?php echo (int) $sub['id']; ?>"><input type="hidden" name="kind" value="module">
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label">Marks (out of <?php echo (int) ($sub['max_score'] ?: 10); ?>)</label>
                    <input type="number" step="0.5" min="0" max="<?php echo (int) ($sub['max_score'] ?: 10); ?>" name="marks" class="form-control" required></div>
                <div class="col-md-9"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
            </div>
            <button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i>Submit grade</button>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php foreach ($legacySubs as $sub): ?>
<div class="card-tutor mb-3" id="sub-<?php echo (int) $sub['id']; ?>">
<div class="head"><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars((string) $sub['item_title']); ?></div>
<div class="p-3">
<p><strong>Student:</strong> <?php echo htmlspecialchars($sub['full_name']); ?> (<?php echo htmlspecialchars($sub['registration_no']); ?>)</p>
<p><strong>Course:</strong> <?php echo htmlspecialchars($sub['course_title']); ?></p>
<div class="bg-light p-3 rounded mb-3"><?php echo nl2br(htmlspecialchars($sub['answer_text'] ?? '')); ?>
<?php if ($sub['file_path']): ?><br><a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank">Download attachment</a><?php endif; ?>
</div>
<form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="submission_id" value="<?php echo (int) $sub['id']; ?>">
<div class="row g-2">
<div class="col-md-3"><label class="form-label">Marks</label><input type="number" step="0.01" name="marks" class="form-control" required></div>
<div class="col-md-9"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
</div>
<button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i> Submit Grade</button>
</form>
</div></div>
<?php endforeach; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
