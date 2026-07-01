<?php
// public/admin/dashboard.php
require_once __DIR__ . '/../../app/v1/config/paths.php';
require_once __DIR__ . '/../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../app/v1/helpers/functions.php';
require_once __DIR__ . '/../../app/v1/models/DashboardModel.php';

use Models\DashboardModel;

if (!isLoggedIn()) redirect(url('admin/portal.php'));
if (($_SESSION['role_id'] ?? 99) > 2) redirect(url('auth/login.php'));

$dash = new DashboardModel($pdo);
$stats = $dash->adminStats();

$page_title = 'Dashboard';
$breadcrumbs = [['label' => 'Dashboard', 'active' => true]];
ob_start();
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Students</div><div class="fs-2 fw-bold text-success"><?php echo $stats['students']; ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Tutors</div><div class="fs-2 fw-bold text-primary"><?php echo $stats['tutors']; ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Active Courses</div><div class="fs-2 fw-bold text-info"><?php echo $stats['courses']; ?></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-muted small">Pending Approvals</div><div class="fs-2 fw-bold text-warning"><?php echo $stats['pending_regs']; ?></div>
    </div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Fees Collected</div><div class="card-body">
        <div class="fs-4 fw-bold text-success">KSh <?php echo number_format($stats['total_collected']); ?></div>
        <small class="text-muted">Outstanding balance: KSh <?php echo number_format($stats['total_outstanding']); ?></small>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Payment Queue</div><div class="card-body">
        <div class="fs-4 fw-bold"><?php echo $stats['pending_pay']; ?> <small class="fs-6 text-muted">pending</small></div>
        <a href="<?php echo url('admin/payments/index.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">Review payments</a>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Locked Portals</div><div class="card-body">
        <div class="fs-4 fw-bold text-danger"><?php echo $stats['portal_locked']; ?></div>
        <small class="text-muted">Students overdue on installments</small>
    </div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Registrations by Status</div><div class="card-body"><canvas id="chartRegs" height="200"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Students per Course</div><div class="card-body"><canvas id="chartCourses" height="200"></canvas></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Monthly Revenue (approved payments)</div><div class="card-body"><canvas id="chartRevenue" height="120"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Quick Links</div><div class="card-body d-grid gap-2">
        <a href="<?php echo url('admin/registrations/index.php?status=pending'); ?>" class="btn btn-warning btn-sm">Pending Registrations</a>
        <a href="<?php echo url('admin/students/index.php'); ?>" class="btn btn-success btn-sm">Students</a>
        <a href="<?php echo url('admin/tutors/index.php'); ?>" class="btn btn-primary btn-sm">Tutors</a>
        <a href="<?php echo url('admin/courses/index.php'); ?>" class="btn btn-info btn-sm">Courses</a>
    </div></div></div>
</div>

<?php
$regLabels = json_encode(array_keys($stats['registrations_by_status']));
$regData = json_encode(array_values($stats['registrations_by_status']));
$courseLabels = json_encode(array_column($stats['students_by_course'], 'title'));
$courseData = json_encode(array_map('intval', array_column($stats['students_by_course'], 'cnt')));
$revLabels = json_encode(array_column($stats['monthly_revenue'], 'ym'));
$revData = json_encode(array_map('floatval', array_column($stats['monthly_revenue'], 'total')));

$extra_js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
  var green = '#2E7D32';
  new Chart(document.getElementById('chartRegs'), { type: 'doughnut', data: {
    labels: $regLabels, datasets: [{ data: $regData, backgroundColor: ['#f59e0b','#22c55e','#ef4444'] }]
  }, options: { plugins: { legend: { position: 'bottom' }}}});
  new Chart(document.getElementById('chartCourses'), { type: 'bar', data: {
    labels: $courseLabels, datasets: [{ label: 'Students', data: $courseData, backgroundColor: green }]
  }, options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }}}});
  new Chart(document.getElementById('chartRevenue'), { type: 'line', data: {
    labels: $revLabels, datasets: [{ label: 'KSh', data: $revData, borderColor: green, fill: true, backgroundColor: 'rgba(46,125,50,0.15)', tension: 0.3 }]
  }, options: { scales: { y: { beginAtZero: true }}}});
});
</script>
JS;
$content = ob_get_clean();
include __DIR__ . '/../templates/admin/layout.php';
