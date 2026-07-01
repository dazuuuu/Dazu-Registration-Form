<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/TutorController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;
use Models\CurriculumModel;
use Controllers\TutorController;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$submissionModel = new SubmissionModel($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $r = (new TutorController())->gradeSubmission((int)$_POST['submission_id'], (int)$_SESSION['user_id'], (float)$_POST['marks'], trim($_POST['feedback']??''));
    if ($r['ok']) { SessionModel::setFlash('success','Graded. Student notified by email.'); header('Location: index.php'); exit(); }
    $error = $r['error'];
}

$pending = $submissionModel->getPendingForTutor((int)$tutor['id']);
$page_title = 'Grade Submissions';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if (empty($pending)): ?><p class="text-muted">No submissions awaiting grading.</p>
<?php else: foreach ($pending as $sub): ?>
<div class="card-tutor mb-3" id="sub-<?php echo (int)$sub['id']; ?>">
<div class="head"><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars($sub['item_title']); ?></div>
<div class="p-3">
<p><strong>Student:</strong> <?php echo htmlspecialchars($sub['full_name']); ?> (<?php echo htmlspecialchars($sub['registration_no']); ?>)</p>
<p><strong>Course:</strong> <?php echo htmlspecialchars($sub['course_title']); ?></p>
<div class="bg-light p-3 rounded mb-3"><?php echo nl2br(htmlspecialchars($sub['answer_text']??'')); ?>
<?php if ($sub['file_path']): ?><br><a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank">Download attachment</a><?php endif; ?>
</div>
<form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="submission_id" value="<?php echo (int)$sub['id']; ?>">
<div class="row g-2">
<div class="col-md-3"><label class="form-label">Marks</label><input type="number" step="0.01" name="marks" class="form-control" required></div>
<div class="col-md-9"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="2"></textarea></div>
</div>
<button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i> Submit Grade</button>
</form>
</div></div>
<?php endforeach; endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
