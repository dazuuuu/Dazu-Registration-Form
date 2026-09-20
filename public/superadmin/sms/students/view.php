<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$id = (int) ($_GET['id'] ?? 0);
$student = $sms->getStudent($id);
if (!$student) {
    sms_flash('error', 'Student not found.');
    sms_redirect('superadmin/sms/students/index.php');
}

if (isset($_GET['delete']) && $_GET['delete'] === '1') {
    $sms->deleteStudent($id);
    sms_flash('success', 'Student deleted.');
    sms_redirect('superadmin/sms/students/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email_documents_pack']) || isset($_POST['email_admission'])) {
        $result = $sms->emailStudentDocumentsPack($id);
        if (!empty($result['ok'])) {
            sms_flash('success', 'Official documents (Admission Letter, Fee Structure & Course Brochure) emailed to ' . ($result['email'] ?? $student['email']) . '.');
        } else {
            sms_flash('error', $result['error'] ?? 'Could not email documents.');
        }
        sms_redirect('superadmin/sms/students/view.php?id=' . $id);
    }
    if (isset($_POST['email_latest_receipt'])) {
        $to = ($_POST['email_to'] ?? 'student') === 'parent' ? 'parent' : 'student';
        $result = $sms->emailLatestReceipt($id, $to);
        if (!empty($result['ok'])) {
            sms_flash('success', 'Receipt ' . ($result['receipt_no'] ?? '') . ' emailed to ' . ($result['email'] ?? '') . '.');
        } else {
            sms_flash('error', $result['error'] ?? 'Could not email receipt.');
        }
        sms_redirect('superadmin/sms/students/view.php?id=' . $id);
    }
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
        sms_redirect('superadmin/sms/students/view.php?id=' . $id);
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
            $to = ($_POST['email_to'] ?? '') === 'parent' ? ($student['parent_email'] ?? '') : ($student['email'] ?? '');
            if ($to) {
                $sms->emailReceipt($student, $r, false, $to);
                sms_flash('success', 'Receipt ' . htmlspecialchars($r['receipt_no']) . ' emailed to ' . htmlspecialchars($to) . '.');
            } else {
                sms_flash('error', 'Recipient email is missing.');
            }
        }
    }
    sms_redirect('superadmin/sms/students/view.php?id=' . $id);
}

$payments = $sms->studentPayments($id);
$receipts = $sms->studentReceipts($id);
$balance = SmsModuleService::balance((float)$student['cost'], (float)$student['paid']);
$hasPortalAccount = !empty($student['registration_id']) && !empty($student['user_id']);
$canResend = !empty($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL);

$page_title = $student['name'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('superadmin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'url' => url('superadmin/sms/students/index.php')],
    ['label' => $student['name'], 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2 align-items-center">
  <div>
    <h4 class="mb-1 fw-bold"><?= htmlspecialchars($student['name']) ?></h4>
    <div class="text-muted small">
      <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($student['course_title'] ?? 'Course') ?></span>
      <span class="badge bg-secondary me-1"><?= htmlspecialchars($student['status']) ?></span>
      <?php if (!empty($student['registration_no'])): ?>
        <span class="badge bg-teal-subtle text-teal border" style="background:#ccfbf1;color:#0f766e;border-color:#99f6e4;">Reg: <?= htmlspecialchars($student['registration_no']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($canResend): ?>
    <form method="post" class="d-inline" onsubmit="return confirm('Email all official student documents (Admission Letter, Fee Structure & Course Brochure) to <?= htmlspecialchars($student['email']) ?>?');">
      <button type="submit" name="email_documents_pack" value="1" class="btn btn-sm btn-success fw-bold shadow-sm">
        <i class="fas fa-folder-open me-1"></i>Email All Documents (Pack)
      </button>
    </form>
    <?php if (!empty($receipts)): ?>
    <form method="post" class="d-inline" onsubmit="return confirm('Email the latest payment receipt to <?= htmlspecialchars($student['email']) ?>?');">
      <button type="submit" name="email_latest_receipt" value="1" class="btn btn-sm btn-info text-white fw-semibold shadow-sm">
        <i class="fas fa-receipt me-1"></i>Email Latest Receipt
      </button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <a href="<?= url('superadmin/sms/students/form.php?id='.$id) ?>" class="btn btn-sm btn-primary">Edit</a>
    <a href="<?= url('superadmin/sms/students/view.php?id='.$id.'&delete=1') ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this student?')">Delete</a>
  </div>
</div>

<!-- SECTION 1: OFFICIAL STUDENT DOCUMENTS (ALL AT ONCE) -->
<div class="card shadow-sm border-success mb-4">
  <div class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
    <div>
      <i class="fas fa-graduation-cap me-2"></i>Official Student Admission Documents Pack
    </div>
    <span class="badge bg-white text-success fw-bold">Admission Letter &bull; Fee Structure &bull; Course Brochure</span>
  </div>
  <div class="card-body">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <p class="small text-muted mb-2">
          Sends the complete official admission document package all at once directly to <strong><?= htmlspecialchars($student['email'] ?: 'No email set') ?></strong>.
        </p>
        <div class="row g-2 small">
          <div class="col-md-4">
            <div class="p-2 border rounded bg-light">
              <div class="fw-bold text-success"><i class="fas fa-file-alt me-1"></i> 1. Admission Letter</div>
              <div class="text-muted" style="font-size:11px;">Admission No: <strong><?= htmlspecialchars($student['registration_no'] ?: 'Auto') ?></strong></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-2 border rounded bg-light">
              <div class="fw-bold text-primary"><i class="fas fa-calculator me-1"></i> 2. Fee Structure</div>
              <div class="text-muted" style="font-size:11px;">Semester &amp; monthly breakdown</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-2 border rounded bg-light">
              <div class="fw-bold text-info"><i class="fas fa-book-open me-1"></i> 3. Course Brochure</div>
              <div class="text-muted" style="font-size:11px;">Full syllabus &amp; study schedule</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4 text-lg-end">
        <?php if ($canResend): ?>
        <form method="post" onsubmit="return confirm('Email all 3 official documents (Admission Letter, Fee Structure & Course Brochure) at once to <?= htmlspecialchars($student['email']) ?>?');">
          <button type="submit" name="email_documents_pack" value="1" class="btn btn-success fw-bold w-100 py-2 shadow-sm">
            <i class="fas fa-paper-plane me-2"></i>Resend All Documents (Pack)
          </button>
        </form>
        <div class="d-flex justify-content-lg-end gap-1 mt-2">
          <?php if (!empty($student['course_id'])): ?>
          <a href="<?= url('courses/fee-structure.php?id='.(int)$student['course_id']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" style="font-size:11px;">
            <i class="fas fa-external-link-alt me-1"></i>Fee Preview
          </a>
          <a href="<?= url('courses/brochure.php?id='.(int)$student['course_id']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" style="font-size:11px;">
            <i class="fas fa-external-link-alt me-1"></i>Brochure Preview
          </a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning small mb-0 py-2">Add a valid student email address to send documents.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- SECTION 2: PORTAL ACCOUNT -->
<div class="card shadow-sm border-warning mb-4">
  <div class="card-header bg-warning-subtle fw-semibold d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-user-lock text-warning"></i>
      Student portal account
    </div>
    <?php if ($hasPortalAccount): ?>
      <span class="badge bg-success">Active &bull; Linked</span>
    <?php else: ?>
      <span class="badge bg-secondary">Not linked</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-md-8">
        <dl class="row mb-0 small">
          <dt class="col-sm-4">Login email</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($student['email'] ?? '—') ?></dd>
          <dt class="col-sm-4">Username / Reg No</dt>
          <dd class="col-sm-8"><?= htmlspecialchars($student['registration_no'] ?? ($hasPortalAccount ? '—' : 'Not linked yet')) ?></dd>
          <dt class="col-sm-4">Sign-in Options</dt>
          <dd class="col-sm-8 text-muted">Students can sign in using either their <strong>Email</strong> or <strong>Registration Number</strong></dd>
        </dl>
      </div>
      <div class="col-md-4 text-md-end">
        <?php if ($canResend): ?>
        <form method="post" onsubmit="return confirm('<?= $hasPortalAccount ? 'Reset this student password and email a new temporary password to ' : 'Create/send login details to ' ?><?= htmlspecialchars($student['email']) ?>?<?= $hasPortalAccount ? '\n\nTheir current password will stop working immediately.' : '' ?>');">
          <?php if ($hasPortalAccount): ?><input type="hidden" name="force_password_reset" value="1"><?php endif; ?>
          <button type="submit" name="resend_credentials" value="1" class="btn btn-outline-warning text-dark fw-semibold w-100">
            <i class="fas fa-key me-1"></i><?= $hasPortalAccount ? 'Reset Password & Send New Temp Pass' : 'Create & Send Account Details' ?>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Course Cost</div><div class="fw-bold"><?= sms_money((float)$student['cost']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Paid to Date</div><div class="fw-bold text-success"><?= sms_money((float)$student['paid']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Outstanding Balance</div><div class="fw-bold text-danger"><?= sms_money($balance) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Parent / Guardian</div><div class="small"><?= htmlspecialchars($student['parent_email'] ?? '—') ?><br><?= htmlspecialchars($student['parent_phone'] ?? '') ?></div></div></div></div>
</div>

<!-- SECTION 3: PAYMENTS & RECEIPTS (RECEIPTS ON ITS OWN) -->
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold bg-light">
        <i class="fas fa-history me-1 text-secondary"></i> Payment history
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Date</th><th>Amount</th><th>Notes</th></tr></thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">No payments recorded yet.</td></tr>
            <?php else: ?>
              <?php foreach ($payments as $p): ?>
              <tr><td><?= htmlspecialchars($p['payment_date']) ?></td><td><?= sms_money((float)$p['amount']) ?></td><td><?= htmlspecialchars($p['notes'] ?? '') ?></td></tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm border-primary h-100">
      <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
        <div>
          <i class="fas fa-receipt me-1"></i> Receipts &amp; Invoicing
        </div>
        <?php if (!empty($receipts) && $canResend): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Email latest payment receipt to <?= htmlspecialchars($student['email']) ?>?');">
          <button type="submit" name="email_latest_receipt" value="1" class="btn btn-xs btn-light text-primary fw-bold" style="font-size:11px;">
            <i class="fas fa-envelope me-1"></i>Email Latest Receipt
          </button>
        </form>
        <?php endif; ?>
      </div>
      <div class="p-2 bg-primary-subtle text-primary border-bottom" style="font-size:11px;">
        <i class="fas fa-info-circle me-1"></i> Receipts are sent independently from academic documents.
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>No.</th><th>Type</th><th>Balance</th><th class="text-end">Receipt Actions</th></tr></thead>
          <tbody>
            <?php if (empty($receipts)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No receipts issued yet.</td></tr>
            <?php else: ?>
              <?php foreach ($receipts as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['receipt_no']) ?></td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['receipt_type']) ?></span></td>
                <td><?= sms_money((float)$r['balance']) ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-xs btn-outline-secondary btn-sm" href="<?= url('superadmin/sms/receipt.php?id='.(int)$r['id']) ?>" title="Download PDF">
                    <i class="fas fa-download"></i>
                  </a>
                  <?php if (!empty($student['phone'])): ?>
                    <a class="btn btn-xs btn-success btn-sm" target="_blank" href="<?= htmlspecialchars($sms->whatsappUrl($student['phone'], $student, $r)) ?>" title="WhatsApp Student">
                      <i class="fab fa-whatsapp"></i>
                    </a>
                  <?php endif; ?>
                  <?php if (!empty($student['parent_phone'])): ?>
                    <a class="btn btn-xs btn-outline-success btn-sm" target="_blank" href="<?= htmlspecialchars($sms->whatsappUrl($student['parent_phone'], $student, $r)) ?>" title="WhatsApp Parent">
                      <i class="fab fa-whatsapp"></i> Parent
                    </a>
                  <?php endif; ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="receipt_id" value="<?= (int)$r['id'] ?>">
                    <button name="regenerate_receipt" value="1" class="btn btn-sm btn-outline-primary btn-xs" title="Regenerate Receipt">
                      <i class="fas fa-sync-alt"></i>
                    </button>
                    <button name="email_receipt" value="1" class="btn btn-sm btn-primary btn-xs fw-semibold" title="Email this receipt to student" onclick="return confirm('Email receipt <?= htmlspecialchars($r['receipt_no']) ?> to <?= htmlspecialchars($student['email']) ?>?');">
                      <i class="fas fa-envelope me-1"></i>Email Receipt
                    </button>
                    <input type="hidden" name="email_to" value="student">
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/superadmin/layout.php';
