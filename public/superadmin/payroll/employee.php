<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/EmployeeNotifier.php';

use Models\Connection;
use Models\EmployeeModel;
use Models\PayrollCalculator;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$employees = new EmployeeModel($pdo);
$id = (int)($_GET['id'] ?? 0);
$emp = $employees->isInstalled() ? $employees->getById($id) : null;
if (!$emp) {
    SessionModel::setFlash('error', 'Employee not found.');
    header('Location: index.php#employees');
    exit();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $back = 'employee.php?id=' . $id;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    if ($action === 'add_payment') {
        $r = $employees->addPayment($id, $_POST['amount'] ?? '', (string)($_POST['month'] ?? ''), (string)($_POST['paid_on'] ?? ''),
            isset($_POST['note']) ? (string)$_POST['note'] : null, (int)($_SESSION['user_id'] ?? 0));
        if ($r['ok']) {
            $bal = $employees->getLedger($employees->getById($id))['balance'];
            SessionModel::setFlash('success', 'Payment recorded. ' . ($bal > 0 ? 'Balance still owed: ' . PayrollCalculator::money($bal) . '.'
                : ($bal < 0 ? 'Paid ahead by ' . PayrollCalculator::money(abs($bal)) . '.' : 'Fully paid up.')));
        } else {
            SessionModel::setFlash('error', $r['error']);
        }
        header('Location: ' . $back); exit();
    }
    if ($action === 'delete_payment') {
        $ok = $employees->deletePayment((int)($_POST['payment_id'] ?? 0), $id);
        SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'Payment removed and the balance updated.' : 'That payment could not be removed.');
        header('Location: ' . $back); exit();
    }
    if ($action === 'send_credentials') {
        $temp = $employees->resetPassword($id);
        $mail = (new EmployeeNotifier())->sendCredentials($emp, (string)$temp);
        if ($mail['ok']) $employees->markCredentialsSent($id);
        SessionModel::setFlash($mail['ok'] ? 'success' : 'error', $mail['ok'] ? 'New sign-in details emailed.'
            : 'Password reset but the email failed (' . htmlspecialchars($mail['message']) . '). Temporary password: <strong>' . htmlspecialchars((string)$temp) . '</strong>');
        header('Location: ' . $back); exit();
    }
    if ($action === 'update') {
        $r = $employees->update($id, [
            'full_name' => $_POST['full_name'] ?? '', 'job_title' => $_POST['job_title'] ?? '', 'monthly_salary' => $_POST['monthly_salary'] ?? '',
            'pay_day' => $_POST['pay_day'] ?? '', 'start_date' => $_POST['start_date'] ?? '', 'end_date' => $_POST['end_date'] ?? '',
            'phone' => $_POST['phone'] ?? '', 'is_active' => isset($_POST['is_active']),
        ]);
        if ($r['ok']) { SessionModel::setFlash('success', 'Employee updated.'); header('Location: ' . $back); exit(); }
        $error = $r['error'];
        $emp = array_merge($emp, $_POST, ['full_name' => $_POST['full_name'] ?? $emp['full_name']]);
    }
}

$ledger = $employees->getLedger($emp);
$currentMonth = PayrollCalculator::currentMonth();
$monthChoices = array_column($ledger['months'], 'month');
if (!in_array($currentMonth, $monthChoices, true)) $monthChoices[] = $currentMonth;
rsort($monthChoices);
$bal = $ledger['balance'];

$page_title = $emp['full_name'];
$breadcrumbs = [['label' => 'Salaries', 'url' => 'index.php#employees'], ['label' => $emp['full_name'], 'active' => true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="d-flex align-items-center gap-3 mb-4">
    <?php if (!empty($emp['photo_path'])): ?><img src="<?php echo htmlspecialchars($emp['photo_path']); ?>" class="rounded-circle" width="56" height="56" style="object-fit:cover" alt=""><?php endif; ?>
    <div><h5 class="mb-0"><?php echo htmlspecialchars($emp['full_name']); ?> <?php if (!$emp['is_active']): ?><span class="badge bg-secondary">Inactive</span><?php endif; ?></h5>
        <div class="text-muted small"><?php echo htmlspecialchars($emp['job_title']); ?> · <?php echo htmlspecialchars($emp['email']); ?></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Salary / month</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money((float)$emp['monthly_salary']); ?></div><div class="text-muted small">Paid on day <?php echo (int)$emp['pay_day']; ?><?php echo $ledger['next_pay_date'] ? ' · next ' . date('d M', strtotime($ledger['next_pay_date'])) : ''; ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Earned to date</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($ledger['earned_to_date']); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid to date</div><div class="fs-4 fw-bold text-success"><?php echo PayrollCalculator::money($ledger['paid_to_date']); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-<?php echo $bal > 0 ? 'danger' : 'success'; ?>"><div class="card-body"><div class="text-muted small"><?php echo $bal < 0 ? 'Paid ahead' : 'Balance owed'; ?></div><div class="fs-4 fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo PayrollCalculator::money(abs($bal)); ?></div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header fw-semibold">Record a payment</div><div class="card-body">
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_payment">
                <div class="mb-3"><label class="form-label">Amount paid (KES)</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" required></div>
                <div class="mb-3"><label class="form-label">Deduct from month</label>
                    <select name="month" class="form-select"><?php foreach ($monthChoices as $m): ?><option value="<?php echo htmlspecialchars($m); ?>" <?php echo $m === $currentMonth ? 'selected' : ''; ?>><?php echo PayrollCalculator::monthLabel($m); ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Date paid</label><input type="date" name="paid_on" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="mb-3"><label class="form-label">Note <span class="text-muted">(optional)</span></label><input type="text" name="note" class="form-control" maxlength="255" placeholder="e.g. M-Pesa code"></div>
                <button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Record payment</button>
                <div class="text-muted small mt-2">Whatever is not paid stays owed and carries forward.</div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-header fw-semibold">Month by month</div><div class="card-body p-0">
        <?php if (empty($ledger['months'])): ?><p class="text-muted p-4 mb-0">Nothing earned yet. The first salary is earned on the first pay day after the start date.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Pay day</th><th class="text-end">Brought forward</th><th class="text-end">Earned</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
            <tbody><?php foreach (array_reverse($ledger['months']) as $row): ?>
                <tr><td><?php echo PayrollCalculator::monthLabel($row['month']); ?></td><td><?php echo date('d M', strtotime($row['pay_date'])); ?></td>
                    <td class="text-end"><?php echo PayrollCalculator::money($row['brought_forward']); ?></td><td class="text-end"><?php echo PayrollCalculator::money($row['earned']); ?></td>
                    <td class="text-end text-success"><?php echo PayrollCalculator::money($row['paid']); ?></td>
                    <td class="text-end fw-bold <?php echo $row['balance'] > 0 ? 'text-danger' : ''; ?>"><?php echo PayrollCalculator::money($row['balance']); ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
        </div></div>
    </div>
</div>

<div class="card mb-4"><div class="card-header fw-semibold">Payments recorded</div><div class="card-body p-0">
<?php if (empty($ledger['payments'])): ?><p class="text-muted p-4 mb-0">No payments recorded yet.</p><?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead class="table-light"><tr><th>Date paid</th><th>For month</th><th class="text-end">Amount</th><th>Note</th><th></th></tr></thead>
    <tbody><?php foreach ($ledger['payments'] as $p): ?>
        <tr><td><?php echo date('d M Y', strtotime($p['paid_on'])); ?></td><td><?php echo PayrollCalculator::monthLabel($p['paid_for_month']); ?></td>
            <td class="text-end fw-semibold"><?php echo PayrollCalculator::money((float)$p['amount']); ?></td><td class="small"><?php echo htmlspecialchars((string)$p['note']); ?></td>
            <td class="text-end"><form method="POST" class="d-inline" onsubmit="return confirm('Remove this payment? The balance owed will go back up.');"><?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                <button class="btn btn-sm btn-outline-danger" title="Remove this payment"><i class="fas fa-trash"></i></button></form></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
</div></div>

<div class="row g-4">
<div class="col-lg-8">
<div class="card"><div class="card-header fw-semibold">Employee details</div><div class="card-body">
<form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="update">
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full name</label><input name="full_name" class="form-control" value="<?php echo htmlspecialchars($emp['full_name']); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Role</label><input name="job_title" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($emp['job_title']); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Monthly salary (KES)</label><input type="number" name="monthly_salary" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)$emp['monthly_salary']); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Paid on day</label><input type="number" name="pay_day" class="form-control" min="1" max="31" value="<?php echo (int)$emp['pay_day']; ?>" required></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="30" value="<?php echo htmlspecialchars((string)($emp['phone'] ?? '')); ?>"></div>
    <div class="col-md-6"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars(substr((string)$emp['start_date'], 0, 10)); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Last day <span class="text-muted">(if they have left)</span></label><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars(substr((string)($emp['end_date'] ?? ''), 0, 10)); ?>"></div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="act" <?php echo $emp['is_active'] ? 'checked' : ''; ?>><label for="act" class="form-check-label">Active (untick to block sign-in)</label></div></div>
</div>
<div class="alert alert-info small mt-3 mb-0">Changing the salary applies to every month, including earlier ones. To start a new salary cleanly, set the old employee's last day and add a new record.</div>
<button class="btn btn-primary mt-3">Save changes</button>
</form>
</div></div>
</div>
<div class="col-lg-4">
<div class="card border-primary"><div class="card-header bg-primary text-white">Sign-in</div><div class="card-body">
    <p class="small text-muted mb-2">Signs in on the normal login page with <strong><?php echo htmlspecialchars($emp['email']); ?></strong> and lands on the employee dashboard.</p>
    <?php if (!empty($emp['credentials_sent_at'])): ?><p class="small text-success">Last sent: <?php echo date('d M Y H:i', strtotime($emp['credentials_sent_at'])); ?></p><?php endif; ?>
    <form method="POST" onsubmit="return confirm('Reset the password and email new sign-in details?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="send_credentials">
        <button class="btn btn-primary w-100"><i class="fas fa-envelope me-1"></i>Reset password and email</button></form>
</div></div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
