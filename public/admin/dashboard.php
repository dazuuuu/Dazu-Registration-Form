<?php
require_once __DIR__ . '/../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../app/v1/models/DashboardModel.php';

use Models\DashboardModel;

if (isLoggedIn() && (int) ($_SESSION['role_id'] ?? 99) === 1) {
    redirect(url('superadmin/dashboard.php'));
}
if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$dash = new DashboardModel($pdo);
$stats = $dash->adminStats();
$page_title = 'System Overview';
$breadcrumbs = [['label' => 'Dashboard', 'active' => true]];

ob_start();
?>
<style>
.dashboard-hero{background:linear-gradient(135deg,#123b25,#1d7b45);color:#fff;border-radius:18px;padding:28px 30px;box-shadow:0 12px 30px rgba(21,91,50,.18)}
.dashboard-hero .subtle{color:rgba(255,255,255,.72)}.metric-card{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(20,35,25,.07);overflow:hidden}.metric-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px}.metric-label{font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;color:#718096;font-weight:700}.report-card{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(20,35,25,.07);height:100%}.report-card .card-header{background:#fff;border-bottom:1px solid #edf0ee;padding:17px 20px;font-weight:700}.accounting-stat{border-left:3px solid #198754;padding-left:13px}.activity-dot{width:9px;height:9px;border-radius:50%;margin-top:7px;flex:none}.compact-table td,.compact-table th{padding:.72rem .45rem;font-size:.86rem;vertical-align:middle}.quick-link{border:1px solid #e8eeea;border-radius:10px;padding:13px;display:flex;align-items:center;gap:11px;color:#263238;text-decoration:none;transition:.2s}.quick-link:hover{border-color:#198754;background:#f3fbf6;color:#146c43}.chart-wrap{position:relative;height:260px}.chart-wrap.short{height:220px}.status-pill{font-size:.7rem;text-transform:capitalize}.empty-state{color:#8b9691;font-size:.88rem;padding:20px 0;text-align:center}
</style>

<section class="dashboard-hero mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div><div class="text-uppercase fw-bold small subtle mb-2">Administration centre</div><h2 class="h3 mb-1">Good day, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?>.</h2><p class="mb-0 subtle">A live snapshot of admissions, tutors, courses and enquiries.</p></div>
    <div class="text-md-end"><div class="subtle small">As of <?php echo date('d M Y, h:i A'); ?></div><a class="btn btn-light btn-sm mt-2" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>"><i class="fa-solid fa-clipboard-check me-1"></i> Review admissions</a></div>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-success-subtle text-success"><i class="fa-solid fa-user-graduate"></i></div><div><div class="metric-label">Active students</div><div class="fs-3 fw-bold"><?php echo number_format($stats['students']); ?></div><a class="small text-success text-decoration-none" href="<?php echo url('admin/registrations/index.php?status=approved'); ?>">Student records <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-primary-subtle text-primary"><i class="fa-solid fa-chalkboard-user"></i></div><div><div class="metric-label">Active tutors</div><div class="fs-3 fw-bold"><?php echo number_format($stats['tutors']); ?></div><a class="small text-primary text-decoration-none" href="<?php echo url('admin/tutors/index.php'); ?>">Tutor directory <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-warning-subtle text-warning"><i class="fa-solid fa-clipboard-check"></i></div><div><div class="metric-label">Pending admissions</div><div class="fs-3 fw-bold"><?php echo number_format($stats['pending_regs']); ?></div><a class="small text-warning-emphasis text-decoration-none" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>">Review queue <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-danger-subtle text-danger"><i class="fa-solid fa-envelope"></i></div><div><div class="metric-label">New enquiries</div><div class="fs-3 fw-bold"><?php echo number_format($stats['new_enquiries']); ?></div><a class="small text-danger text-decoration-none" href="<?php echo url('admin/enquiries/index.php'); ?>">Inbox <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-user-plus text-success me-2"></i>Admissions trend</div><div class="card-body"><div class="chart-wrap short"><canvas id="chartRegistrations"></canvas></div></div></div></div>
  <div class="col-lg-6"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-book-open text-primary me-2"></i>Students by course</div><div class="card-body"><div class="chart-wrap short"><canvas id="chartCourses"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12"><div class="card report-card"><div class="card-header d-flex justify-content-between"><span><i class="fa-solid fa-inbox text-warning me-2"></i>Enquiry inbox</span><a href="<?php echo url('admin/enquiries/index.php'); ?>" class="small">All enquiries</a></div><div class="card-body"><div class="d-flex justify-content-between mb-3"><div><div class="h4 mb-0"><?php echo number_format($stats['new_enquiries']); ?></div><small class="text-muted">new enquiries</small></div><div class="text-end"><div class="h4 mb-0"><?php echo number_format($stats['total_enquiries']); ?></div><small class="text-muted">all time</small></div></div><?php if ($stats['recent_enquiries']): foreach ($stats['recent_enquiries'] as $enquiry): ?><div class="d-flex gap-2 py-2 border-top"><div class="activity-dot bg-<?php echo $enquiry['status'] === 'new' ? 'warning' : 'success'; ?>"></div><div class="flex-grow-1"><div class="fw-semibold small"><?php echo htmlspecialchars($enquiry['name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($enquiry['service'] ?: 'General enquiry'); ?> · <?php echo date('d M', strtotime($enquiry['created_at'])); ?></div></div><span class="badge text-bg-light border text-dark status-pill align-self-center"><?php echo htmlspecialchars($enquiry['status']); ?></span></div><?php endforeach; else: ?><div class="empty-state">No enquiries received yet.</div><?php endif; ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card report-card"><div class="card-header d-flex justify-content-between"><span><i class="fa-solid fa-address-card text-primary me-2"></i>Latest admissions</span><a href="<?php echo url('admin/registrations/index.php'); ?>" class="small">View registrations</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 compact-table"><thead class="table-light"><tr><th>Applicant</th><th>Course</th><th>Submitted</th><th>Status</th></tr></thead><tbody><?php if ($stats['recent_registrations']): foreach ($stats['recent_registrations'] as $registration): ?><tr><td class="fw-semibold"><?php echo htmlspecialchars($registration['full_name']); ?></td><td><?php echo htmlspecialchars($registration['course_title'] ?? 'Not assigned'); ?></td><td class="text-muted"><?php echo date('d M Y', strtotime($registration['created_at'])); ?></td><td><span class="badge text-bg-<?php echo $registration['status'] === 'approved' ? 'success' : ($registration['status'] === 'pending' ? 'warning' : 'danger'); ?> status-pill"><?php echo htmlspecialchars($registration['status']); ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="empty-state">No admissions recorded yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
  <div class="col-lg-5"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-bolt text-warning me-2"></i>Management shortcuts</div><div class="card-body d-grid gap-2"><a class="quick-link" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>"><i class="fa-solid fa-user-clock text-warning"></i><span><strong>Review applications</strong><small class="d-block text-muted"><?php echo number_format($stats['pending_regs']); ?> pending approval</small></span></a><a class="quick-link" href="<?php echo url('admin/enquiries/index.php'); ?>"><i class="fa-solid fa-envelope text-primary"></i><span><strong>Respond to enquiries</strong><small class="d-block text-muted"><?php echo number_format($stats['new_enquiries']); ?> need attention</small></span></a><a class="quick-link" href="<?php echo url('admin/registrations/index.php?status=approved'); ?>"><i class="fa-solid fa-users text-secondary"></i><span><strong>Student management</strong><small class="d-block text-muted">Accounts, access, and records</small></span></a></div></div></div>
</div>

<?php
$registrationLabels = json_encode(array_column($stats['monthly_registrations'], 'label'));
$registrationData = json_encode(array_map('intval', array_column($stats['monthly_registrations'], 'total')));
$courseLabels = json_encode(array_column($stats['students_by_course'], 'title'));
$courseData = json_encode(array_map('intval', array_column($stats['students_by_course'], 'cnt')));
$extra_js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
  const green = '#198754', blue = '#0d6efd', yellow = '#ffc107', red = '#dc3545';
  const baseOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
  new Chart(document.getElementById('chartRegistrations'), { type: 'bar', data: { labels: $registrationLabels, datasets: [{ label: 'Applications', data: $registrationData, backgroundColor: blue, borderRadius: 6 }] }, options: { ...baseOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  new Chart(document.getElementById('chartCourses'), { type: 'bar', data: { labels: $courseLabels, datasets: [{ label: 'Students', data: $courseData, backgroundColor: green, borderRadius: 6 }] }, options: { ...baseOptions, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } } });
});
</script>
JS;
$content = ob_get_clean();
include __DIR__ . '/../templates/admin/layout.php';
