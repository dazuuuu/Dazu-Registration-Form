<?php
require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_reminders'])) {
    $r = $sms->runProspectReminders();
    sms_flash('success', 'Prospect reminders sent: ' . $r['today'] . ' due today, ' . $r['two_day'] . ' two-day.');
    sms_redirect('admin/sms/index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_project_alerts'])) {
    $r = $sms->runProjectAlerts();
    sms_flash('success', 'Project alerts: ' . $r['due'] . ' due soon, ' . $r['followups'] . ' follow-ups today.');
    sms_redirect('admin/sms/index.php');
}

$stats = $sms->dashboardStats();
$activity = $sms->recentActivity(25);

$page_title = 'SmS Dashboard';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/sms/index.php')],
    ['label' => 'Dashboard', 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="fas fa-sms me-2"></i>SmS Dashboard</h4>
  <form method="post" class="d-flex gap-2">
    <button type="submit" name="run_reminders" value="1" class="btn btn-sm btn-outline-primary">Run prospect reminders</button>
    <button type="submit" name="run_project_alerts" value="1" class="btn btn-sm btn-outline-secondary">Run project alerts</button>
  </form>
</div>

<div class="row g-3 mb-4">
  <?php foreach ([
    ['Students', $stats['students'], 'success'],
    ['Prospects', $stats['prospects'], 'info'],
    ['Open prospects', $stats['openProspects'], 'warning'],
    ['Projects', $stats['projects'], 'primary'],
    ['Active projects', $stats['activeProjects'], 'secondary'],
  ] as [$label, $val, $color]): ?>
  <div class="col-6 col-lg"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
    <div class="fs-3 fw-bold text-<?= $color ?>"><?= (int) $val ?></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Finance snapshot</div><div class="card-body small">
    <p><strong>Students</strong> — Cost: <?= sms_money((float)$stats['stuFin']['cost']) ?>, Paid: <?= sms_money((float)$stats['stuFin']['paid']) ?>, Balance: <?= sms_money((float)$stats['stuFin']['cost'] - (float)$stats['stuFin']['paid']) ?></p>
    <p><strong>Projects</strong> — Cost: <?= sms_money((float)$stats['prjFin']['cost']) ?>, Paid: <?= sms_money((float)$stats['prjFin']['paid']) ?>, Balance: <?= sms_money((float)$stats['prjFin']['cost'] - (float)$stats['prjFin']['paid']) ?></p>
    <p class="mb-0 text-danger">Students with arrears: <strong><?= (int)$stats['stuArrears'] ?></strong></p>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Sales snapshot</div><div class="card-body">
    <div class="fs-4 fw-bold text-success"><?= sms_money($stats['salesStudents'] + $stats['salesProjects']) ?></div>
    <small class="text-muted">Students: <?= sms_money($stats['salesStudents']) ?> · Projects: <?= sms_money($stats['salesProjects']) ?></small>
  </div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Projects due within 3 days</div><div class="card-body p-0">
    <?php if (empty($stats['dueSoon'])): ?><p class="p-3 text-muted mb-0">None due soon.</p><?php else: ?>
    <table class="table table-sm mb-0"><thead><tr><th>Title</th><th>Due</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($stats['dueSoon'] as $p): ?>
      <tr><td><a href="<?= url('admin/sms/projects/view.php?id='.(int)$p['id']) ?>"><?= htmlspecialchars($p['title']) ?></a></td>
      <td><?= htmlspecialchars($p['due_date']) ?></td><td><?= htmlspecialchars($p['status']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Upcoming meetings</div><div class="card-body p-0">
    <?php if (empty($stats['meetings'])): ?><p class="p-3 text-muted mb-0">No upcoming meetings.</p><?php else: ?>
    <table class="table table-sm mb-0"><thead><tr><th>When</th><th>Project</th><th>Title</th></tr></thead><tbody>
      <?php foreach ($stats['meetings'] as $m): ?>
      <tr><td><?= htmlspecialchars($m['meeting_date']) ?></td><td><?= htmlspecialchars($m['project_title']) ?></td><td><?= htmlspecialchars($m['title']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div></div></div>
</div>

<div class="card shadow-sm"><div class="card-header fw-semibold">Recent activity</div><div class="card-body p-0">
  <table class="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>Description</th></tr></thead><tbody>
    <?php foreach ($activity as $a): ?>
    <tr>
      <td class="text-nowrap small"><?= htmlspecialchars($a['created_at']) ?></td>
      <td><span class="badge bg-light text-dark"><?= htmlspecialchars($a['activity_type']) ?></span></td>
      <td class="small"><?= htmlspecialchars($a['description']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
