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
    if (isset($_POST['resend_credentials'])) {
        $forceReset = !empty($_POST['force_password_reset']);
        $result = $sms->resendStudentCredentials($id, $forceReset);
        if (!empty($result['ok'])) {
            $message = $forceReset
                ? 'A new temporary password was emailed to ' . ($result['email'] ?? $student['email']) . '. The student must choose a new password on first login.'
                : 'Account details emailed to ' . ($result['email'] ?? $student['email']) . '.';
            sms_flash('success', $message);
        } else {
            sms_flash('error', $result['error'] ?? 'Could not send account details.');
        }
        sms_redirect('admin/sms/students/view.php?id=' . $id);
    }
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
$hasPortalAccount = !empty($student['registration_id']) && !empty($student['user_id']);
$canResend = !empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL);

$page_title = $student['name'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'url' => url('admin/sms/students/index.php')],
    ['label' => $student['name'], 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><?= htmlspecialchars($student['name']) ?></h4>
    <small class="text-muted"><?= htmlspecialchars($student['course_title'] ?? '') ?> · <?= htmlspecialchars($student['status']) ?><?php if (!empty($student['registration_no'])): ?> · <?= htmlspecialchars($student['registration_no']) ?><?php endif; ?></small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canResend): ?>
    <form method="post" class="d-inline" onsubmit="return confirm('<?= $hasPortalAccount ? 'Reset this student password and email a new temporary password to ' : 'Create/send login details to ' ?><?= htmlspecialchars($student['email']) ?>?<?= $hasPortalAccount ? '\n\nTheir current password will stop working immediately.' : '' ?>');">
      <?php if ($hasPortalAccount): ?><input type="hidden" name="force_password_reset" value="1"><?php endif; ?>
      <button type="submit" name="resend_credentials" value="1" class="btn btn-sm btn-warning text-dark fw-semibold">
        <i class="fas fa-paper-plane me-1"></i><?= $hasPortalAccount ? 'Reset password & email temp password' : 'Send account details' ?>
      </button>
    </form>
    <?php endif; ?>
    <a href="<?= url('admin/sms/students/form.php?id='.$id) ?>" class="btn btn-sm btn-primary">Edit</a>
    <a href="<?= url('admin/sms/students/view.php?id='.$id.'&delete=1') ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this student?')">Delete</a>
  </div>
</div>

<div class="card shadow-sm border-warning mb-4">
  <div class="card-header bg-warning-subtle fw-semibold d-flex align-items-center gap-2">
    <i class="fas fa-user-lock text-warning"></i>
    Student portal account
  </div>
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <dl class="row mb-0 small">
          <dt class="col-sm-4">Login email</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($student['email'] ?? '—') ?></dd>
          <dt class="col-sm-4">Username</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($student['registration_no'] ?? ($hasPortalAccount ? '—' : 'Not linked yet')) ?></dd>
          <dt class="col-sm-4">Portal status</dt>
          <dd class="col-sm-8">
            <?php if ($hasPortalAccount): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Not linked</span>
              <span class="text-muted"> — click resend to create/link and email credentials</span>
            <?php endif; ?>
          </dd>
        </dl>
        <p class="text-muted small mb-0 mt-2">
          Existing portal passwords are not changed unless you explicitly reset the password.
        </p>
      </div>
      <div class="col-md-4 text-md-end">
        <?php if ($canResend): ?>
        <form method="post" onsubmit="return confirm('<?= $hasPortalAccount ? 'Reset this student password and email a new temporary password to ' : 'Create/send login details to ' ?><?= htmlspecialchars($student['email']) ?>?<?= $hasPortalAccount ? '\n\nTheir current password will stop working immediately.' : '' ?>');">
          <?php if ($hasPortalAccount): ?><input type="hidden" name="force_password_reset" value="1"><?php endif; ?>
          <button type="submit" name="resend_credentials" value="1" class="btn btn-warning text-dark fw-semibold w-100">
            <i class="fas fa-envelope me-1"></i><?= $hasPortalAccount ? 'Reset password & email temp password' : 'Send account details' ?>
          </button>
        </form>
        <?php else: ?>
        <div class="alert alert-warning small mb-0">Add a valid email address on the student profile to send account details.</div>
        <?php endif; ?>
      </div>
    </div>
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
