<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$tab = $_GET['tab'] ?? 'overview';
$overview = $sms->financeOverview();
$studentData = $tab === 'students' ? $sms->financeStudents() : null;
$projectRows = $tab === 'projects' ? $sms->financeProjects() : null;

$page_title = 'SmS Finance';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Finance', 'active' => true],
];

ob_start();
?>
<h4 class="mb-3">Finance <small class="text-muted">(read-only)</small></h4>

<ul class="nav nav-tabs mb-3">
  <?php foreach (['overview' => 'Overview', 'students' => 'Students', 'projects' => 'Projects'] as $key => $label): ?>
  <li class="nav-item">
    <a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= url('admin/sms/finance/index.php?tab='.$key) ?>"><?= $label ?></a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Students</div><div class="card-body">
    <p>Total cost: <strong><?= sms_money((float)$overview['students']['c']) ?></strong></p>
    <p>Total paid: <strong class="text-success"><?= sms_money((float)$overview['students']['p']) ?></strong></p>
    <p class="mb-0">Outstanding: <strong class="text-danger"><?= sms_money((float)$overview['students']['c'] - (float)$overview['students']['p']) ?></strong></p>
  </div></div></div>
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Projects</div><div class="card-body">
    <p>Total cost: <strong><?= sms_money((float)$overview['projects']['c']) ?></strong></p>
    <p>Total paid: <strong class="text-success"><?= sms_money((float)$overview['projects']['p']) ?></strong></p>
    <p class="mb-0">Outstanding: <strong class="text-danger"><?= sms_money((float)$overview['projects']['c'] - (float)$overview['projects']['p']) ?></strong></p>
  </div></div></div>
</div>

<?php elseif ($tab === 'students'): ?>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Monthly collections</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead><tr><th>Month</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($studentData['monthly'] as $m): ?>
      <tr><td><?= htmlspecialchars($m['ym']) ?></td><td><?= sms_money((float)$m['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($studentData['monthly'])): ?><tr><td colspan="2" class="text-muted text-center">No payments yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div></div></div>
</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th>Student</th><th>Course</th><th>Cost</th><th>Paid</th><th>Balance</th></tr></thead><tbody>
  <?php foreach ($studentData['rows'] as $s): ?>
  <tr>
    <td><a href="<?= url('admin/sms/students/view.php?id='.(int)$s['id']) ?>"><?= htmlspecialchars($s['name']) ?></a></td>
    <td><?= htmlspecialchars($s['course_title'] ?? '—') ?></td>
    <td><?= sms_money((float)$s['cost']) ?></td>
    <td><?= sms_money((float)$s['paid']) ?></td>
    <td class="<?= (float)$s['balance'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= sms_money((float)$s['balance']) ?></td>
  </tr>
  <?php endforeach; ?>
</tbody></table>
</div></div>

<?php else: ?>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0"><thead class="table-light"><tr><th>Project</th><th>Cost</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($projectRows as $p): ?>
  <tr>
    <td><a href="<?= url('admin/sms/projects/view.php?id='.(int)$p['id']) ?>"><?= htmlspecialchars($p['title']) ?></a></td>
    <td><?= sms_money((float)$p['cost']) ?></td>
    <td><?= sms_money((float)$p['paid']) ?></td>
    <td class="<?= (float)$p['balance'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= sms_money((float)$p['balance']) ?></td>
    <td><?= htmlspecialchars($p['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (empty($projectRows)): ?><tr><td colspan="5" class="text-muted text-center py-4">No projects.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
