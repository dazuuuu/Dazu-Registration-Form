<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$id = (int) ($_GET['id'] ?? 0);
$student = $sms->getStudent($id);
if (!$student) {
    sms_flash('error', 'Student not found.');
    sms_redirect('admin/sms/students/index.php');
}

if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    $sms->deleteStudent($id);
    sms_flash('success', 'Student deleted.');
    sms_redirect('admin/sms/students/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['regenerate_receipt'])) {
        $rid = (int) $_POST['receipt_id'];
        $sms->regenerateReceipt($rid);
        sms_flash('success', 'Receipt regenerated.');
    }
    if (isset($_POST['email_receipt'])) {
        $rid = (int) $_POST['receipt_id'];
        $r = $sms->getReceipt($rid);
        if ($r) {
            $to = $_POST['email_to'] === 'parent' ? ($student['parent_email'] ?? '') : ($student['email'] ?? '');
            $sms->emailReceipt($student, $r, false, $to ?: null);
            sms_flash('success', 'Receipt emailed.');
        }
    }
    sms_redirect('admin/sms/students/view.php?id=' . $id);
}

$payments = $sms->studentPayments($id);
$receipts = $sms->studentReceipts($id);
$balance = SmsModuleService::balance((float)$student['cost'], (float)$student['paid']);

$page_title = $student['name'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'url' => url('admin/sms/students/index.php')],
    ['label' => $student['name'], 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between mb-3">
  <div>
    <h4 class="mb-1"><?= htmlspecialchars($student['name']) ?></h4>
    <small class="text-muted"><?= htmlspecialchars($student['course_title'] ?? '') ?> · <?= htmlspecialchars($student['status']) ?></small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/sms/students/form.php?id='.$id) ?>" class="btn btn-sm btn-primary">Edit</a>
    <a href="<?= url('admin/sms/students/view.php?id='.$id.'&delete=1') ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this student?')">Delete</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Cost</div><div class="fw-bold"><?= sms_money((float)$student['cost']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Paid</div><div class="fw-bold text-success"><?= sms_money((float)$student['paid']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Balance</div><div class="fw-bold text-danger"><?= sms_money($balance) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Parent</div><div class="small"><?= htmlspecialchars($student['parent_email'] ?? '—') ?><br><?= htmlspecialchars($student['parent_phone'] ?? '') ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Payment history</div><div class="table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Amount</th><th>Notes</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?>
      <tr><td><?= htmlspecialchars($p['payment_date']) ?></td><td><?= sms_money((float)$p['amount']) ?></td><td><?= htmlspecialchars($p['notes'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div></div></div>
  <div class="col-lg-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Receipts</div><div class="table-responsive">
    <table class="table table-sm mb-0"><thead><tr><th>No.</th><th>Type</th><th>Balance</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($receipts as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['receipt_no']) ?></td>
        <td><?= htmlspecialchars($r['receipt_type']) ?></td>
        <td><?= sms_money((float)$r['balance']) ?></td>
        <td class="text-nowrap">
          <a class="btn btn-xs btn-outline-secondary btn-sm" href="<?= url('admin/sms/receipt.php?id='.(int)$r['id']) ?>">Download</a>
          <?php if ($student['phone']): ?><a class="btn btn-xs btn-success btn-sm" target="_blank" href="<?= htmlspecialchars($sms->whatsappUrl($student['phone'], $student, $r)) ?>">WA</a><?php endif; ?>
          <?php if ($student['parent_phone']): ?><a class="btn btn-xs btn-success btn-sm" target="_blank" href="<?= htmlspecialchars($sms->whatsappUrl($student['parent_phone'], $student, $r)) ?>">WA Parent</a><?php endif; ?>
          <form method="post" class="d-inline"><input type="hidden" name="receipt_id" value="<?= (int)$r['id'] ?>">
            <button name="regenerate_receipt" value="1" class="btn btn-sm btn-outline-primary">Regen</button>
            <button name="email_receipt" value="1" class="btn btn-sm btn-outline-info">Email</button>
            <input type="hidden" name="email_to" value="student">
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>
  </div></div></div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
