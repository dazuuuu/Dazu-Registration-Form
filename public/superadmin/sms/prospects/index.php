<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_reminders'])) {
    $r = $sms->runProspectReminders();
    sms_flash('success', 'Reminders sent: ' . $r['today'] . ' due today, ' . $r['two_day'] . ' two-day.');
    sms_redirect('superadmin/sms/prospects/index.php');
}

if (isset($_GET['delete'])) {
    $sms->deleteProspect((int) $_GET['delete']);
    sms_flash('success', 'Prospect deleted.');
    sms_redirect('superadmin/sms/prospects/index.php');
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$prospects = $sms->listProspects($search ?: null, $status);
$summary = $sms->prospectSummary();

$page_title = 'SmS Prospects';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('superadmin/dashboard.php#sms-alerts')],
    ['label' => 'Prospects', 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Prospects</h4>
  <div class="d-flex gap-2">
    <form method="post"><button type="submit" name="run_reminders" value="1" class="btn btn-sm btn-outline-primary">Run reminders</button></form>
    <a href="<?= url('superadmin/sms/prospects/form.php') ?>" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add prospect</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ([
    ['Total', $summary['total'], 'secondary'],
    ['Open', $summary['open'], 'info'],
    ['Due today', $summary['dueToday'], 'warning'],
    ['Due in 2 days', $summary['due2'], 'danger'],
  ] as [$label, $val, $color]): ?>
  <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-2">
    <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
    <div class="fs-4 fw-bold text-<?= $color ?>"><?= (int) $val ?></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Search name, phone, email, course"></div>
  <div class="col-md-3">
    <select name="status" class="form-select form-select-sm">
      <?php foreach (['all','open','converted','lost'] as $st): ?>
      <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Name</th><th>Interest</th><th>Follow-up</th><th>Status</th><th>Contact</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($prospects as $p):
    $dueClass = '';
    if ($p['status'] === 'open' && !empty($p['follow_up_date'])) {
        if ($p['follow_up_date'] === date('Y-m-d')) $dueClass = 'table-warning';
        elseif ($p['follow_up_date'] < date('Y-m-d')) $dueClass = 'table-danger';
    }
  ?>
    <tr class="<?= $dueClass ?>">
      <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($p['intake_or_course'] ?? '') ?></small></td>
      <td><?= htmlspecialchars($p['interest_type']) ?></td>
      <td><?= htmlspecialchars($p['follow_up_date'] ?? '—') ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span></td>
      <td class="small"><?= htmlspecialchars($p['phone'] ?? '') ?><br><?= htmlspecialchars($p['email'] ?? '') ?></td>
      <td class="text-nowrap">
        <a href="<?= url('superadmin/sms/prospects/form.php?id='.(int)$p['id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
        <a href="<?= url('superadmin/sms/prospects/index.php?delete='.(int)$p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this prospect?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($prospects)): ?>
    <tr><td colspan="6" class="text-muted text-center py-4">No prospects found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/superadmin/layout.php';
