<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$students = $sms->listStudents($search ?: null, $status);

$page_title = 'SmS Students';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Students</h4>
  <a href="<?= url('admin/sms/students/form.php') ?>" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add student</a>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Search name, email, phone, course"></div>
  <div class="col-md-3">
    <select name="status" class="form-select form-select-sm">
      <?php foreach (['all','active','completed','withdrawn'] as $st): ?>
      <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filter</button></div>
</form>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover mb-0">
  <thead class="table-light"><tr><th>Name</th><th>Reg. No.</th><th>Course</th><th>Cost</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($students as $s):
    $bal = SmsModuleService::balance((float)$s['cost'], (float)$s['paid']);
  ?>
    <tr>
      <td><strong><?= htmlspecialchars($s['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($s['email'] ?? '') ?></small></td>
      <td><small class="text-muted"><?= htmlspecialchars($s['registration_no'] ?? '—') ?></small></td>
      <td><?= htmlspecialchars($s['course_title'] ?? '—') ?></td>
      <td><?= sms_money((float)$s['cost']) ?></td>
      <td><?= sms_money((float)$s['paid']) ?></td>
      <td class="<?= $bal > 0 ? 'text-danger fw-semibold' : '' ?>"><?= sms_money($bal) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($s['status']) ?></span></td>
      <td><a href="<?= url('admin/sms/students/view.php?id='.(int)$s['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
