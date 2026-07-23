<?php
require_once __DIR__ . '/_init.php';
sms_require_tables($pdo);
$category = $_GET['category'] ?? 'all';
$sales = $sms->listSales($category);
$summary = $sms->salesSummary();

$page_title = 'SmS Sales';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Sales', 'active' => true],
];

ob_start();
?>
<h4 class="mb-3">Sales</h4>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">Total collected</div>
    <div class="fs-3 fw-bold text-success"><?= sms_money($summary['total']) ?></div>
  </div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">From students</div>
    <div class="fs-4 fw-bold"><?= sms_money($summary['students']) ?></div>
  </div></div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <div class="text-muted small">From projects</div>
    <div class="fs-4 fw-bold"><?= sms_money($summary['projects']) ?></div>
  </div></div></div>
</div>

<form class="mb-3" method="get">
  <select name="category" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
    <?php foreach (['all' => 'All categories', 'students' => 'Students', 'projects' => 'Projects'] as $val => $label): ?>
    <option value="<?= $val ?>" <?= $category === $val ? 'selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Linked to</th></tr></thead>
  <tbody>
  <?php foreach ($sales as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['sale_date']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($s['category']) ?></span></td>
      <td><?= htmlspecialchars($s['description']) ?></td>
      <td class="fw-semibold text-success"><?= sms_money((float)$s['amount']) ?></td>
      <td class="small">
        <?php if ($s['student_id']): ?>
          <a href="<?= url('admin/sms/students/view.php?id='.(int)$s['student_id']) ?>"><?= htmlspecialchars($s['student_name'] ?? 'Student') ?></a>
        <?php elseif ($s['project_id']): ?>
          <a href="<?= url('admin/sms/projects/view.php?id='.(int)$s['project_id']) ?>"><?= htmlspecialchars($s['project_title'] ?? 'Project') ?></a>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($sales)): ?>
    <tr><td colspan="5" class="text-muted text-center py-4">No sales recorded yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
