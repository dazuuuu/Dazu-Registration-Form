<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;
use Models\DashboardModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$stats = (new DashboardModel($pdo))->tutorStats((int)$tutor['id']);
$pending = (new SubmissionModel($pdo))->getPendingForTutor((int)$tutor['id']);

$page_title = 'Dashboard';
ob_start();
?>
<h4 class="mb-4">Welcome, <?php echo htmlspecialchars($tutorModel->fullName($tutor)); ?></h4>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">Courses</div><div class="fs-3 fw-bold text-success"><?php echo $stats['courses']; ?></div></div></div>
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">Students</div><div class="fs-3 fw-bold text-primary"><?php echo $stats['students']; ?></div></div></div>
    <div class="col-md-4"><div class="card-tutor p-3"><div class="text-muted small">To Grade</div><div class="fs-3 fw-bold text-warning"><?php echo $stats['pending']; ?></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-lg-7"><div class="card-tutor"><div class="head">Student progress</div><div class="p-3"><canvas id="tutorProgress" height="180"></canvas></div></div></div>
    <div class="col-lg-5"><div class="card-tutor"><div class="head">Quick actions</div><div class="p-3 d-grid gap-2">
        <?php if ($stats['pending']): ?><a href="<?php echo url('tutor/submissions/index.php'); ?>" class="btn btn-brand">Grade <?php echo $stats['pending']; ?> submission(s)</a><?php endif; ?>
        <a href="<?php echo url('tutor/students/index.php'); ?>" class="btn btn-outline-success">My students</a>
        <a href="<?php echo url('tutor/courses/index.php'); ?>" class="btn btn-outline-secondary">Manage curriculum</a>
    </div></div></div>
</div>
<div class="card-tutor"><div class="head">My courses</div><div class="list-group list-group-flush">
<?php foreach ($tutor['courses'] as $c): ?>
<a href="<?php echo url('tutor/courses/curriculum.php?course_id=' . (int)$c['id']); ?>" class="list-group-item list-group-item-action">
    <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($c['title']); ?>
</a>
<?php endforeach; ?>
</div></div>
<?php require __DIR__ . '/payroll_section.php'; ?>
<?php
$labels = json_encode(array_map(fn($s) => $s['full_name'], $stats['students_progress']));
$done = json_encode(array_map('intval', array_column($stats['students_progress'], 'done')));
$total = json_encode(array_map('intval', array_column($stats['students_progress'], 'total')));
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
document.addEventListener("DOMContentLoaded",function(){
  var labels=' . $labels . ', done=' . $done . ', total=' . $total . ';
  if(!labels.length) return;
  new Chart(document.getElementById("tutorProgress"),{type:"bar",data:{
    labels:labels,datasets:[
      {label:"Units done",data:done,backgroundColor:"#2E7D32"},
      {label:"Total units",data:total,backgroundColor:"#e5e7eb"}
    ]},options:{scales:{y:{beginAtZero:true,ticks:{stepSize:1}}},plugins:{legend:{position:"bottom"}}}});
});
</script>';
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
