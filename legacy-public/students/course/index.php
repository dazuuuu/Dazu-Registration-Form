<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';

use Models\Connection;
use Models\StudentModel;
use Models\TutorModel;
use Models\CurriculumModel;
use Models\ProgressModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$tutorModel = new TutorModel($pdo);
$curriculum = new CurriculumModel($pdo);
$progressModel = new ProgressModel($pdo);
$userId = (int)$_SESSION['user_id'];

$reg = $studentModel->getByUserId($userId);
if (!$reg) { header('Location: ' . url('students/dashboard/index.php')); exit(); }
$portal_locked = (bool)$reg['portal_locked'];

$tutors = $tutorModel->getForCourse((int)$reg['course_id']);
$units = $curriculum->getCourseTree((int)$reg['course_id']);
$progress = $progressModel->getProgress((int)$reg['id'], (int)$reg['course_id']);
if (empty($progress) && !empty($units)) {
    $progressModel->initializeForRegistration((int)$reg['id'], (int)$reg['course_id']);
    $progress = $progressModel->getProgress((int)$reg['id'], (int)$reg['course_id']);
}
$progressMap = [];
foreach ($progress as $p) $progressMap[(int)$p['unit_id']] = $p;

$portal_locked = (bool)$reg['portal_locked'];
$page_title = 'My Course';
ob_start();
?>

<?php if (!empty($tutors)): ?>
<div class="card-student mb-4"><div class="card-head"><i class="fas fa-chalkboard-teacher me-2"></i>Your Tutor(s)</div><div class="card-body">
<div class="row g-3">
<?php foreach ($tutors as $t): ?>
<div class="col-md-6 d-flex gap-3 align-items-start">
    <?php if (!empty($t['photo_path'])): ?>
        <img src="<?php echo htmlspecialchars($t['photo_path']); ?>" class="rounded-circle" width="72" height="72" style="object-fit:cover;">
    <?php else: ?>
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:72px;height:72px;"><i class="fas fa-user fa-2x"></i></div>
    <?php endif; ?>
    <div>
        <strong class="fs-5"><?php echo htmlspecialchars(trim(($t['first_name']??'').' '.($t['last_name']??'')) ?: 'Tutor'); ?></strong>
        <?php if ($t['bio']): ?><p class="text-muted small mb-1"><?php echo nl2br(htmlspecialchars($t['bio'])); ?></p><?php endif; ?>
        <?php if ($t['phone']): ?><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($t['phone']); ?></small><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div></div></div>
<?php else: ?>
<div class="alert alert-info">A tutor will be assigned to your course soon.</div>
<?php endif; ?>

<div class="card-student"><div class="card-head"><i class="fas fa-book-open me-2"></i>Course Units — <?php echo htmlspecialchars($reg['course_title']); ?></div><div class="card-body p-0">
<?php if (empty($units)): ?>
<p class="p-4 text-muted mb-0">Course content is being prepared by your tutor.</p>
<?php else: ?>
<div class="list-group list-group-flush">
<?php foreach ($units as $i => $unit):
    $pid = (int)$unit['id'];
    $p = $progressMap[$pid] ?? null;
    $unlocked = $p && $p['is_unlocked'];
    $completed = $p && $p['completed_at'];
?>
<div class="list-group-item d-flex justify-content-between align-items-center <?php echo !$unlocked ? 'bg-light text-muted' : ''; ?>">
    <div>
        <?php if (!$unlocked): ?><i class="fas fa-lock me-2"></i>
        <?php elseif ($completed): ?><i class="fas fa-check-circle text-success me-2"></i>
        <?php else: ?><i class="fas fa-play-circle text-primary me-2"></i><?php endif; ?>
        <strong>Unit <?php echo $i+1; ?>:</strong> <?php echo htmlspecialchars($unit['title']); ?>
        <?php if ($completed): ?><span class="badge bg-success ms-2">Completed</span>
        <?php elseif ($unlocked): ?><span class="badge bg-primary ms-2">Available</span>
        <?php else: ?><span class="badge bg-secondary ms-2">Locked — complete previous unit</span><?php endif; ?>
    </div>
    <?php if ($unlocked && !$portal_locked): ?>
    <a href="unit.php?id=<?php echo $pid; ?>" class="btn btn-sm btn-brand">Open</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></div>

<?php $content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
