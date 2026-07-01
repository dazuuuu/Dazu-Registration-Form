<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/TutorModel.php';
require_once __DIR__ . '/../../../app/models/SubmissionModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$students = $tutorModel->getStudentsForTutor((int)$tutor['id']);
$pending = (new SubmissionModel($pdo))->getPendingForTutor((int)$tutor['id']);

$page_title = 'Dashboard';
ob_start();
?>
<h4 class="mb-4">Welcome, <?php echo htmlspecialchars($tutorModel->fullName($tutor)); ?></h4>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">Assigned Courses</div><div class="fs-3 fw-bold text-success"><?php echo count($tutor['courses']); ?></div></div></div>
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">Students</div><div class="fs-3 fw-bold text-success"><?php echo count($students); ?></div></div></div>
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">Pending Submissions</div><div class="fs-3 fw-bold text-warning"><?php echo count($pending); ?></div></div></div>
</div>
<?php if ($pending): ?>
<div class="card-tutor"><div class="head">Submissions awaiting grading</div><div class="p-3">
<a href="/Registration-System/public/tutor/submissions/index.php" class="btn btn-brand">Review <?php echo count($pending); ?> submission(s)</a>
</div></div>
<?php endif; ?>
<div class="card-tutor"><div class="head">My Courses</div><div class="list-group list-group-flush">
<?php foreach ($tutor['courses'] as $c): ?>
<a href="/Registration-System/public/tutor/courses/curriculum.php?course_id=<?php echo (int)$c['id']; ?>" class="list-group-item list-group-item-action">
    <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($c['title']); ?> — Manage curriculum
</a>
<?php endforeach; ?>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
