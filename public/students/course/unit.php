<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/StudentModel.php';
require_once __DIR__ . '/../../../app/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/models/TutorModel.php';
require_once __DIR__ . '/../../../app/services/TutorNotifier.php';
require_once __DIR__ . '/../../../app/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\CurriculumModel;
use Models\ProgressModel;
use Models\SubmissionModel;
use Models\TutorModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$curriculum = new CurriculumModel($pdo);
$progressModel = new ProgressModel($pdo);
$submissionModel = new SubmissionModel($pdo);
$tutorModel = new TutorModel($pdo);
$userId = (int)$_SESSION['user_id'];
$unitId = (int)($_GET['id'] ?? 0);

$reg = $studentModel->getByUserId($userId);
if (!$reg || $reg['portal_locked']) { header('Location: index.php'); exit(); }

$unit = $curriculum->getUnitById($unitId);
if (!$unit || (int)$unit['course_id'] !== (int)$reg['course_id']) { header('Location: index.php'); exit(); }
if (!$progressModel->isUnitUnlocked((int)$reg['id'], $unitId)) {
    SessionModel::setFlash('error', 'This unit is locked. Complete the previous unit first.');
    header('Location: index.php'); exit();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $type = $_POST['submission_type'] ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);
    $filePath = null;
    if (!empty($_FILES['file']['name'])) {
        $dest = __DIR__ . '/../../uploads/submissions';
        if (!is_dir($dest)) mkdir($dest, 0775, true);
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fname = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest.'/'.$fname)) {
            $filePath = '/Registration-System/public/uploads/submissions/'.$fname;
        }
    }
    $result = $submissionModel->submit((int)$reg['id'], $type, $targetId, trim($_POST['answer_text']??''), $filePath);
    if ($result['ok']) {
        $tutors = $tutorModel->getForCourse((int)$reg['course_id']);
        $notifier = new TutorNotifier();
        $sub = $submissionModel->getById($result['id']);
        foreach ($tutors as $t) {
            $t['email'] = $pdo->query("SELECT email FROM users WHERE id=".(int)$t['user_id'])->fetchColumn();
            $notifier->sendSubmissionAlert($t, $sub, $reg['full_name']);
        }
        SessionModel::setFlash('success', 'Submitted! Your tutor will grade it.');
        header('Location: unit.php?id='.$unitId); exit();
    }
    $error = $result['error'];
}

$subUnits = $curriculum->getSubUnits($unitId);
$tests = $curriculum->getTests($unitId);
$projects = $curriculum->getProjects($unitId);
$unitProgress = $progressModel->getUnitProgress((int)$reg['id'], $unitId);
$page_title = $unit['title'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-3">← Back to course</a>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card-student mb-3"><div class="card-head"><?php echo htmlspecialchars($unit['title']); ?></div>
<div class="card-body"><?php echo nl2br(htmlspecialchars($unit['description']??'')); ?></div></div>

<?php foreach ($subUnits as $su): ?>
<div class="card-student mb-3"><div class="card-head"><?php echo htmlspecialchars($su['title']); ?></div><div class="card-body">
<?php foreach ($curriculum->getTopics((int)$su['id']) as $topic): ?>
<div class="mb-3 pb-3 border-bottom">
<h6><?php echo htmlspecialchars($topic['title']); ?></h6>
<div><?php echo nl2br(htmlspecialchars($topic['content']??'')); ?></div>
</div>
<?php endforeach; ?>
</div></div>
<?php endforeach; ?>

<?php foreach ($tests as $test):
    $sub = $submissionModel->getSubmission((int)$reg['id'], 'test', (int)$test['id']);
?>
<div class="card-student mb-3 border-warning"><div class="card-head"><i class="fas fa-file-alt me-2"></i>Test: <?php echo htmlspecialchars($test['title']); ?></div>
<div class="card-body">
<p><?php echo nl2br(htmlspecialchars($test['instructions']??$test['description']??'')); ?></p>
<?php if ($sub && $sub['status']==='graded'): ?>
<div class="alert alert-success">Graded: <strong><?php echo $sub['marks']; ?></strong> / <?php echo $test['max_marks']; ?> marks<br><?php echo nl2br(htmlspecialchars($sub['feedback']??'')); ?></div>
<?php elseif ($sub): ?>
<div class="alert alert-info">Submitted — awaiting tutor grading.</div>
<?php else: ?>
<form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<input type="hidden" name="submission_type" value="test"><input type="hidden" name="target_id" value="<?php echo (int)$test['id']; ?>">
<textarea name="answer_text" class="form-control mb-2" rows="4" placeholder="Your answers..." required></textarea>
<input type="file" name="file" class="form-control mb-2">
<button class="btn btn-brand">Submit Test</button>
</form>
<?php endif; ?>
</div></div>
<?php endforeach; ?>

<?php foreach ($projects as $proj):
    $sub = $submissionModel->getSubmission((int)$reg['id'], 'project', (int)$proj['id']);
?>
<div class="card-student mb-3 border-primary"><div class="card-head"><i class="fas fa-project-diagram me-2"></i>Project: <?php echo htmlspecialchars($proj['title']); ?></div>
<div class="card-body">
<p><?php echo nl2br(htmlspecialchars($proj['instructions']??$proj['description']??'')); ?></p>
<?php if ($sub && $sub['status']==='graded'): ?>
<div class="alert alert-success">Graded: <strong><?php echo $sub['marks']; ?></strong> / <?php echo $proj['max_marks']; ?> marks<br><?php echo nl2br(htmlspecialchars($sub['feedback']??'')); ?></div>
<?php elseif ($sub): ?>
<div class="alert alert-info">Submitted — awaiting tutor grading.</div>
<?php else: ?>
<form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<input type="hidden" name="submission_type" value="project"><input type="hidden" name="target_id" value="<?php echo (int)$proj['id']; ?>">
<textarea name="answer_text" class="form-control mb-2" rows="4" placeholder="Describe your project or paste link..." required></textarea>
<input type="file" name="file" class="form-control mb-2">
<button class="btn btn-brand">Submit Project</button>
</form>
<?php endif; ?>
</div></div>
<?php endforeach; ?>

<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
