<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\CurriculumModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$curriculum = new CurriculumModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$courseId = (int)($_GET['course_id'] ?? 0);

if (!$tutorModel->tutorTeachesCourse((int)$tutor['id'], $courseId)) {
    SessionModel::setFlash('error', 'Course not assigned to you.');
    header('Location: index.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    if (($_POST['action'] ?? '') === 'add_unit') {
        $curriculum->createUnit($courseId, trim($_POST['title'] ?? ''), trim($_POST['description'] ?? ''));
        SessionModel::setFlash('success', 'Unit added.');
    }
    header('Location: curriculum.php?course_id='.$courseId); exit();
}

$units = $curriculum->getUnitsForCourse($courseId, false);
$courseTitle = '';
foreach ($tutor['courses'] as $c) { if ((int)$c['id'] === $courseId) $courseTitle = $c['title']; }

$page_title = 'Curriculum — ' . $courseTitle;
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?php echo htmlspecialchars($courseTitle); ?> — Units</h5>
    <a href="index.php" class="btn btn-link btn-sm">← Back</a>
</div>
<div class="card-tutor mb-4"><div class="head">Add Unit</div><div class="p-3">
<form method="POST" class="row g-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_unit">
    <div class="col-md-4"><input name="title" class="form-control" placeholder="Unit title" required></div>
    <div class="col-md-6"><input name="description" class="form-control" placeholder="Description"></div>
    <div class="col-md-2"><button class="btn btn-brand w-100">Add</button></div>
</form></div></div>
<?php if (empty($units)): ?><p class="text-muted">No units yet. Add your first unit above.</p>
<?php else: foreach ($units as $u): ?>
<div class="card-tutor"><div class="head d-flex justify-content-between">
    <span>Unit <?php echo (int)$u['sort_order'] + 1; ?>: <?php echo htmlspecialchars($u['title']); ?></span>
    <a href="unit.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-light">Edit content →</a>
</div><div class="p-3 text-muted small"><?php echo htmlspecialchars($u['description'] ?: 'No description'); ?></div></div>
<?php endforeach; endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
