<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';

use Models\Connection;
use Models\EmployeeModel;
use Models\PayrollCalculator;

Middleware::requireEmployee();

$pdo = Connection::getInstance()->getConnection();
$employees = new EmployeeModel($pdo);
$emp = $employees->isInstalled() ? $employees->getByUserId((int)$_SESSION['user_id']) : null;
if (!$emp) {
    http_response_code(404);
    exit('No employee record was found for this account. Please contact the administrator.');
}
$pay = $employees->getLedger($emp);
$bal = $pay['balance'];
$profileIncomplete = empty($emp['photo_path']) || empty($emp['phone']);

$page_title = 'Dashboard';
ob_start();
$pwaPortal = 'employee'; require __DIR__ . '/../../components/pwa/banner.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
    <?php if (!empty($emp['photo_path'])): ?>
        <img src="<?php echo htmlspecialchars($emp['photo_path']); ?>" class="rounded-circle" width="64" height="64" style="object-fit:cover" alt="">
    <?php else: ?>
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width:64px;height:64px;font-size:1.5rem"><?php echo htmlspecialchars(strtoupper(substr($emp['full_name'], 0, 1))); ?></div>
    <?php endif; ?>
    <div>
        <h4 class="mb-0">Welcome, <?php echo htmlspecialchars($emp['full_name']); ?></h4>
        <div class="text-muted"><?php echo htmlspecialchars($emp['job_title']); ?> · Dazu Training Institute</div>
    </div>
</div>

<?php if ($profileIncomplete): ?>
    <div class="alert alert-info">Your profile is not complete yet. <a href="<?php echo url('employee/profile/index.php'); ?>">Add your photo and phone number</a>.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card-staff p-3 h-100"><div class="text-muted small">Monthly salary</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money((float)$emp['monthly_salary']); ?></div>
        <div class="text-muted small">Paid on day <?php echo (int)$emp['pay_day']; ?><?php echo $pay['next_pay_date'] ? ' · next ' . date('d M Y', strtotime($pay['next_pay_date'])) : ''; ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-staff p-3 h-100"><div class="text-muted small">Earned so far</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($pay['earned_to_date']); ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-staff p-3 h-100"><div class="text-muted small">Paid to you</div><div class="fs-4 fw-bold text-success"><?php echo PayrollCalculator::money($pay['paid_to_date']); ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card-staff p-3 h-100 <?php echo $bal > 0 ? 'border-danger' : ''; ?>"><div class="text-muted small"><?php echo $bal < 0 ? 'Paid in advance' : 'Still owed to you'; ?></div><div class="fs-4 fw-bold <?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo PayrollCalculator::money(abs($bal)); ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7"><div class="card-staff"><div class="head">Month by month</div>
    <?php if (empty($pay['months'])): ?><p class="text-muted p-3 mb-0">Nothing earned yet. Your first salary is earned on the first pay day after you start.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Month</th><th class="text-end">Brought forward</th><th class="text-end">Earned</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
        <tbody><?php foreach (array_reverse($pay['months']) as $row): ?>
            <tr><td><?php echo PayrollCalculator::monthLabel($row['month']); ?></td>
                <td class="text-end"><?php echo PayrollCalculator::money($row['brought_forward']); ?></td><td class="text-end"><?php echo PayrollCalculator::money($row['earned']); ?></td>
                <td class="text-end text-success"><?php echo PayrollCalculator::money($row['paid']); ?></td>
                <td class="text-end fw-bold <?php echo $row['balance'] > 0 ? 'text-danger' : ''; ?>"><?php echo PayrollCalculator::money($row['balance']); ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?></div></div>
    <div class="col-lg-5"><div class="card-staff"><div class="head">Payments received</div>
    <?php if (empty($pay['payments'])): ?><p class="text-muted p-3 mb-0">No payments recorded yet.</p><?php else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>For month</th><th class="text-end">Amount</th></tr></thead>
        <tbody><?php foreach ($pay['payments'] as $p): ?>
            <tr><td><?php echo date('d M Y', strtotime($p['paid_on'])); ?><?php if (!empty($p['note'])): ?><div class="text-muted small"><?php echo htmlspecialchars($p['note']); ?></div><?php endif; ?></td>
                <td><?php echo PayrollCalculator::monthLabel($p['paid_for_month']); ?></td><td class="text-end"><?php echo PayrollCalculator::money((float)$p['amount']); ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?></div></div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/employee/layout.php';
