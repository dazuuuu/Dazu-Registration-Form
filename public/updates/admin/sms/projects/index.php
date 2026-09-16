<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_alerts'])) {
    $r = $sms->runProjectAlerts();
    sms_flash('success', 'Project alerts: ' . $r['due'] . ' due soon, ' . $r['followups'] . ' follow-ups today.');
    sms_redirect('admin/sms/projects/index.php');
}

if (isset($_GET['delete'])) {
    $sms->deleteProject((int) $_GET['delete']);
    sms_flash('success', 'Project deleted.');
    sms_redirect('admin/sms/projects/index.php');
}

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$projects = $sms->listProjects($search ?: null, $status);

$page_title = 'SmS Projects';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Projects', 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Client projects</h4>
  <div class="d-flex gap-2">
    <form method="post"><button type="submit" name="run_alerts" value="1" class="btn btn-sm btn-outline-primary">Run alerts</button></form>
    <a href="<?= url('admin/sms/projects/form.php') ?>" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add project</a>
  </div>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Search title, client, company"></div>
  <div class="col-md-3">
    <select name="status" class="form-select form-select-sm">
      <?php foreach (['all','active','in_progress','completed','cancelled'] as $st): ?>
      <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= str_replace('_', ' ', ucfirst($st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Title</th><th>Client</th><th>Due</th><th>Cost</th><th>Paid</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($projects as $p):
    $bal = SmsModuleService::balance((float)$p['estimated_cost'], (float)$p['paid_amount']);
  ?>
    <tr>
      <td>
        <strong><?= htmlspecialchars($p['title']) ?></strong>
        <?php if (!empty($p['is_sale'])): ?><span class="badge bg-success ms-1">Sale</span><?php endif; ?>
        <br><small class="text-muted"><?= htmlspecialchars($p['client_company'] ?? '') ?></small>
      </td>
      <td><?= htmlspecialchars($p['client_name'] ?? '—') ?></td>
      <td><?= htmlspecialchars($p['due_date'] ?? '—') ?></td>
      <td><?= sms_money((float)$p['estimated_cost']) ?></td>
      <td><?= sms_money((float)$p['paid_amount']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span></td>
      <td class="text-nowrap">
        <a href="<?= url('admin/sms/projects/view.php?id='.(int)$p['id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
        <a href="<?= url('admin/sms/projects/form.php?id='.(int)$p['id']) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
        <a href="<?= url('admin/sms/projects/index.php?delete='.(int)$p['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this project?')">Delete</a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($projects)): ?>
    <tr><td colspan="7" class="text-muted text-center py-4">No projects found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
