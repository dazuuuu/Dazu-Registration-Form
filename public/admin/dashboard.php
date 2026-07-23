<?php
require_once __DIR__ . '/../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../app/v1/services/SmsModuleService.php';

use Models\DashboardModel;

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$smsService = new SmsModuleService($pdo, (int) ($_SESSION['user_id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_run_reminders'])) {
    $r = $smsService->runProspectReminders();
    $_SESSION['flash_success'] = 'Prospect reminders sent: ' . $r['today'] . ' due today, ' . $r['two_day'] . ' two-day.';
    redirect(url('admin/dashboard.php#sms-alerts'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sms_run_project_alerts'])) {
    $r = $smsService->runProjectAlerts();
    $_SESSION['flash_success'] = 'Project alerts: ' . $r['due'] . ' due soon, ' . $r['followups'] . ' follow-ups today.';
    redirect(url('admin/dashboard.php#sms-alerts'));
}

$dash = new DashboardModel($pdo);
$stats = $dash->adminStats();
$sms = $smsService->dashboardAlerts();
$page_title = 'System Overview';
$breadcrumbs = [['label' => 'Dashboard', 'active' => true]];

$money = static fn ($amount): string => 'KSh ' . number_format((float) $amount, 0);
$paymentStatus = static function (string $status): string {
    return ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'][$status] ?? 'secondary';
};
ob_start();
?>
<style>
.dashboard-hero{background:linear-gradient(135deg,#123b25,#1d7b45);color:#fff;border-radius:18px;padding:28px 30px;box-shadow:0 12px 30px rgba(21,91,50,.18)}
.dashboard-hero .subtle{color:rgba(255,255,255,.72)}.metric-card{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(20,35,25,.07);overflow:hidden}.metric-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px}.metric-label{font-size:.73rem;text-transform:uppercase;letter-spacing:.07em;color:#718096;font-weight:700}.report-card{border:0;border-radius:14px;box-shadow:0 3px 14px rgba(20,35,25,.07);height:100%}.report-card .card-header{background:#fff;border-bottom:1px solid #edf0ee;padding:17px 20px;font-weight:700}.accounting-stat{border-left:3px solid #198754;padding-left:13px}.activity-dot{width:9px;height:9px;border-radius:50%;margin-top:7px;flex:none}.compact-table td,.compact-table th{padding:.72rem .45rem;font-size:.86rem;vertical-align:middle}.quick-link{border:1px solid #e8eeea;border-radius:10px;padding:13px;display:flex;align-items:center;gap:11px;color:#263238;text-decoration:none;transition:.2s}.quick-link:hover{border-color:#198754;background:#f3fbf6;color:#146c43}.chart-wrap{position:relative;height:260px}.chart-wrap.short{height:220px}.status-pill{font-size:.7rem;text-transform:capitalize}.empty-state{color:#8b9691;font-size:.88rem;padding:20px 0;text-align:center}
</style>

<section class="dashboard-hero mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div><div class="text-uppercase fw-bold small subtle mb-2">Administration centre</div><h2 class="h3 mb-1">Good day, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?>.</h2><p class="mb-0 subtle">A live snapshot of admissions, learning operations, enquiries, and finance.</p></div>
    <div class="text-md-end"><div class="subtle small">As of <?php echo date('d M Y, h:i A'); ?></div><a class="btn btn-light btn-sm mt-2" href="<?php echo url('admin/payments/index.php'); ?>"><i class="fa-solid fa-wallet me-1"></i> Open accounting</a></div>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-success-subtle text-success"><i class="fa-solid fa-user-graduate"></i></div><div><div class="metric-label">Active students</div><div class="fs-3 fw-bold"><?php echo number_format($stats['students']); ?></div><a class="small text-success text-decoration-none" href="<?php echo url('admin/sms/students/index.php'); ?>">Student records <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-primary-subtle text-primary"><i class="fa-solid fa-chalkboard-user"></i></div><div><div class="metric-label">Active tutors</div><div class="fs-3 fw-bold"><?php echo number_format($stats['tutors']); ?></div><a class="small text-primary text-decoration-none" href="<?php echo url('admin/tutors/index.php'); ?>">Tutor directory <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-warning-subtle text-warning"><i class="fa-solid fa-clipboard-check"></i></div><div><div class="metric-label">Pending admissions</div><div class="fs-3 fw-bold"><?php echo number_format($stats['pending_regs']); ?></div><a class="small text-warning-emphasis text-decoration-none" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>">Review queue <i class="fa-solid fa-arrow-right"></i></a></div></div></div></div>
  <div class="col-6 col-xl-3"><div class="card metric-card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="metric-icon bg-danger-subtle text-danger"><i class="fa-solid fa-lock"></i></div><div><div class="metric-label">Locked portals</div><div class="fs-3 fw-bold"><?php echo number_format($stats['portal_locked']); ?></div><span class="small text-muted">Require payment follow-up</span></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3"><div class="card report-card"><div class="card-body"><div class="metric-label mb-2">Fees collected</div><div class="h4 fw-bold text-success mb-1"><?php echo $money($stats['total_collected']); ?></div><small class="text-muted">All approved payments</small></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card report-card"><div class="card-body"><div class="metric-label mb-2">This month</div><div class="h4 fw-bold mb-1"><?php echo $money($stats['month_revenue']); ?></div><small class="<?php echo $stats['month_revenue'] >= $stats['previous_month_revenue'] ? 'text-success' : 'text-danger'; ?>"><i class="fa-solid fa-chart-line me-1"></i>Previous month: <?php echo $money($stats['previous_month_revenue']); ?></small></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card report-card"><div class="card-body"><div class="metric-label mb-2">Outstanding balance</div><div class="h4 fw-bold text-danger mb-1"><?php echo $money($stats['total_outstanding']); ?></div><small class="text-muted"><?php echo number_format($stats['portal_locked']); ?> locked student portal(s)</small></div></div></div>
  <div class="col-md-6 col-xl-3"><div class="card report-card"><div class="card-body"><div class="metric-label mb-2">Collection rate</div><div class="h4 fw-bold mb-2"><?php echo number_format($stats['collection_rate'], 1); ?>%</div><div class="progress" style="height:7px"><div class="progress-bar bg-success" style="width:<?php echo min(100, $stats['collection_rate']); ?>%"></div></div><small class="text-muted d-block mt-2">Of <?php echo $money($stats['expected_fees']); ?> expected fees</small></div></div></div>
</div>

<section id="sms-alerts" class="mb-4">
  <div class="card report-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span><i class="fa-solid fa-sms text-success me-2"></i>SmS alerts</span>
      <?php if (!empty($sms['installed'])): ?>
      <div class="d-flex gap-2">
        <form method="post" class="d-inline"><button type="submit" name="sms_run_reminders" value="1" class="btn btn-sm btn-outline-primary">Run prospect reminders</button></form>
        <form method="post" class="d-inline"><button type="submit" name="sms_run_project_alerts" value="1" class="btn btn-sm btn-outline-secondary">Run project alerts</button></form>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($sms['installed'])): ?>
        <div class="alert alert-warning mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>SmS module tables are not set up yet. Run the one-time migration to enable students, prospects, and projects.</span>
          <a class="btn btn-sm btn-success" href="<?php echo url('admin/sms/migrate.php'); ?>">Run migration</a>
        </div>
      <?php else:
        $sum = $sms['summary'];
      ?>
        <div class="row g-3 mb-3">
          <div class="col-6 col-md-3"><div class="accounting-stat"><div class="metric-label">SmS students</div><div class="h5 mb-0"><?php echo number_format($sum['students']); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="accounting-stat border-danger"><div class="metric-label">With balance</div><div class="h5 mb-0 text-danger"><?php echo number_format($sum['arrears']); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="accounting-stat border-warning"><div class="metric-label">Prospects due</div><div class="h5 mb-0 text-warning"><?php echo number_format($sum['prospects_due']); ?></div></div></div>
          <div class="col-6 col-md-3"><div class="accounting-stat border-primary"><div class="metric-label">Projects due soon</div><div class="h5 mb-0 text-primary"><?php echo number_format($sum['projects_due']); ?></div></div></div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><small class="text-muted">Outstanding fees</small><div class="fw-bold text-danger"><?php echo $money($sum['outstanding']); ?></div></div>
          <div class="col-md-4"><small class="text-muted">SmS collected</small><div class="fw-bold text-success"><?php echo $money($sum['collected']); ?></div></div>
          <div class="col-md-4"><small class="text-muted">Open prospects</small><div class="fw-bold"><?php echo number_format($sum['prospects_open']); ?></div></div>
        </div>
        <?php if (!empty($sms['alerts'])): ?>
        <div class="table-responsive">
          <table class="table table-hover compact-table mb-0">
            <thead class="table-light"><tr><th>Type</th><th>Name</th><th>Detail</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($sms['alerts'] as $alert):
                $icon = ['student' => 'fa-user-graduate', 'prospect' => 'fa-user-clock', 'project' => 'fa-briefcase'][$alert['kind']] ?? 'fa-bell';
                $badge = ['danger' => 'danger', 'warning' => 'warning'][$alert['severity']] ?? 'secondary';
              ?>
              <tr>
                <td><span class="badge text-bg-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($alert['kind']); ?></span></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($alert['title']); ?></td>
                <td class="text-muted small"><?php echo htmlspecialchars($alert['detail']); ?></td>
                <td class="text-end"><a href="<?php echo url($alert['path']); ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
        <div class="empty-state mb-0">No SmS alerts right now — all balances and follow-ups are clear.</div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
          <a class="btn btn-sm btn-outline-success" href="<?php echo url('admin/sms/students/index.php'); ?>">Students</a>
          <a class="btn btn-sm btn-outline-info" href="<?php echo url('admin/sms/prospects/index.php'); ?>">Prospects</a>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo url('admin/sms/projects/index.php'); ?>">Projects</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo url('admin/sms/finance/index.php'); ?>">Finance</a>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo url('admin/sms/sales/index.php'); ?>">Sales</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="card report-card"><div class="card-header d-flex justify-content-between"><span><i class="fa-solid fa-chart-line text-success me-2"></i>Revenue trend</span><small class="text-muted fw-normal">Approved payments</small></div><div class="card-body"><div class="chart-wrap"><canvas id="chartRevenue"></canvas></div></div></div></div>
  <div class="col-lg-4"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Payment workflow</div><div class="card-body"><div class="chart-wrap short"><canvas id="chartPayments"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-user-plus text-success me-2"></i>Admissions trend</div><div class="card-body"><div class="chart-wrap short"><canvas id="chartRegistrations"></canvas></div></div></div></div>
  <div class="col-lg-6"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-book-open text-primary me-2"></i>Students by course</div><div class="card-body"><div class="chart-wrap short"><canvas id="chartCourses"></canvas></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="card report-card"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="fa-solid fa-clock-rotate-left text-success me-2"></i>Recent financial activity</span><a href="<?php echo url('admin/payments/index.php'); ?>" class="small">View payments</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 compact-table"><thead class="table-light"><tr><th>Student</th><th>Reference</th><th>Date</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody><?php if ($stats['recent_payments']): foreach ($stats['recent_payments'] as $payment): ?><tr><td class="fw-semibold"><?php echo htmlspecialchars($payment['full_name'] ?? 'Unknown student'); ?></td><td class="text-muted"><?php echo htmlspecialchars($payment['registration_no'] ?? '—'); ?></td><td><?php echo !empty($payment['payment_date']) ? date('d M Y', strtotime($payment['payment_date'])) : '—'; ?></td><td class="text-end fw-semibold"><?php echo $money($payment['amount']); ?></td><td><span class="badge text-bg-<?php echo $paymentStatus($payment['status']); ?> status-pill"><?php echo htmlspecialchars($payment['status']); ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="empty-state">No payment activity yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
  <div class="col-xl-5"><div class="card report-card"><div class="card-header d-flex justify-content-between"><span><i class="fa-solid fa-inbox text-warning me-2"></i>Enquiry inbox</span><a href="<?php echo url('admin/enquiries/index.php'); ?>" class="small">All enquiries</a></div><div class="card-body"><div class="d-flex justify-content-between mb-3"><div><div class="h4 mb-0"><?php echo number_format($stats['new_enquiries']); ?></div><small class="text-muted">new enquiries</small></div><div class="text-end"><div class="h4 mb-0"><?php echo number_format($stats['total_enquiries']); ?></div><small class="text-muted">all time</small></div></div><?php if ($stats['recent_enquiries']): foreach ($stats['recent_enquiries'] as $enquiry): ?><div class="d-flex gap-2 py-2 border-top"><div class="activity-dot bg-<?php echo $enquiry['status'] === 'new' ? 'warning' : 'success'; ?>"></div><div class="flex-grow-1"><div class="fw-semibold small"><?php echo htmlspecialchars($enquiry['name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($enquiry['service'] ?: 'General enquiry'); ?> · <?php echo date('d M', strtotime($enquiry['created_at'])); ?></div></div><span class="badge text-bg-light border text-dark status-pill align-self-center"><?php echo htmlspecialchars($enquiry['status']); ?></span></div><?php endforeach; else: ?><div class="empty-state">No enquiries received yet.</div><?php endif; ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="card report-card"><div class="card-header d-flex justify-content-between"><span><i class="fa-solid fa-address-card text-primary me-2"></i>Latest admissions</span><a href="<?php echo url('admin/registrations/index.php'); ?>" class="small">View registrations</a></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0 compact-table"><thead class="table-light"><tr><th>Applicant</th><th>Course</th><th>Submitted</th><th>Status</th></tr></thead><tbody><?php if ($stats['recent_registrations']): foreach ($stats['recent_registrations'] as $registration): ?><tr><td class="fw-semibold"><?php echo htmlspecialchars($registration['full_name']); ?></td><td><?php echo htmlspecialchars($registration['course_title'] ?? 'Not assigned'); ?></td><td class="text-muted"><?php echo date('d M Y', strtotime($registration['created_at'])); ?></td><td><span class="badge text-bg-<?php echo $registration['status'] === 'approved' ? 'success' : ($registration['status'] === 'pending' ? 'warning' : 'danger'); ?> status-pill"><?php echo htmlspecialchars($registration['status']); ?></span></td></tr><?php endforeach; else: ?><tr><td colspan="4" class="empty-state">No admissions recorded yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
  <div class="col-lg-5"><div class="card report-card"><div class="card-header"><i class="fa-solid fa-bolt text-warning me-2"></i>Management shortcuts</div><div class="card-body d-grid gap-2"><a class="quick-link" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>"><i class="fa-solid fa-user-clock text-warning"></i><span><strong>Review applications</strong><small class="d-block text-muted"><?php echo number_format($stats['pending_regs']); ?> pending approval</small></span></a><a class="quick-link" href="<?php echo url('admin/payments/index.php'); ?>"><i class="fa-solid fa-money-check-dollar text-success"></i><span><strong>Approve payments</strong><small class="d-block text-muted"><?php echo number_format($stats['pending_pay']); ?> awaiting review</small></span></a><a class="quick-link" href="<?php echo url('admin/enquiries/index.php'); ?>"><i class="fa-solid fa-envelope text-primary"></i><span><strong>Respond to enquiries</strong><small class="d-block text-muted"><?php echo number_format($stats['new_enquiries']); ?> need attention</small></span></a><a class="quick-link" href="<?php echo url('admin/sms/students/index.php'); ?>"><i class="fa-solid fa-users text-secondary"></i><span><strong>Student management</strong><small class="d-block text-muted">Accounts, access, and records</small></span></a></div></div></div>
</div>

<?php
$revenueLabels = json_encode(array_column($stats['monthly_revenue'], 'ym'));
$revenueData = json_encode(array_map('floatval', array_column($stats['monthly_revenue'], 'total')));
$paymentLabels = json_encode(array_map('ucfirst', array_keys($stats['payment_statuses'])));
$paymentData = json_encode(array_values($stats['payment_statuses']));
$registrationLabels = json_encode(array_column($stats['monthly_registrations'], 'label'));
$registrationData = json_encode(array_map('intval', array_column($stats['monthly_registrations'], 'total')));
$courseLabels = json_encode(array_column($stats['students_by_course'], 'title'));
$courseData = json_encode(array_map('intval', array_column($stats['students_by_course'], 'cnt')));
$extra_js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
  const green = '#198754', blue = '#0d6efd', yellow = '#ffc107', red = '#dc3545';
  const baseOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
  new Chart(document.getElementById('chartRevenue'), { type: 'line', data: { labels: $revenueLabels, datasets: [{ label: 'Revenue (KSh)', data: $revenueData, borderColor: green, backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: .35, pointRadius: 4 }] }, options: { ...baseOptions, scales: { y: { beginAtZero: true, ticks: { callback: value => 'KSh ' + Number(value).toLocaleString() } } } } });
  new Chart(document.getElementById('chartPayments'), { type: 'doughnut', data: { labels: $paymentLabels, datasets: [{ data: $paymentData, backgroundColor: [green, yellow, red], borderWidth: 0 }] }, options: baseOptions });
  new Chart(document.getElementById('chartRegistrations'), { type: 'bar', data: { labels: $registrationLabels, datasets: [{ label: 'Applications', data: $registrationData, backgroundColor: blue, borderRadius: 6 }] }, options: { ...baseOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
  new Chart(document.getElementById('chartCourses'), { type: 'bar', data: { labels: $courseLabels, datasets: [{ label: 'Students', data: $courseData, backgroundColor: green, borderRadius: 6 }] }, options: { ...baseOptions, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } } });
});
</script>
JS;
$content = ob_get_clean();
include __DIR__ . '/../templates/admin/layout.php';
