<?php
// Partial — included by other pages, never opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
// public/tutor/dashboard/payroll_section.php
// Payroll block shown on the tutor dashboard. Included from index.php, which provides $pdo and $tutor.
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';

use Models\PayrollModel;
use Models\PayrollCalculator;

$payrollModel = new PayrollModel($pdo);
if (!$payrollModel->isInstalled()) {
    return; // payroll migration not applied yet: show nothing rather than an error
}
$pay = $payrollModel->getLedger((int)$tutor['id']);
$pendingPayments = array_values(array_filter($pay['payments'], fn($p) => empty($p['confirmed_at'])));
$confirmUrl = url('tutor/payroll/confirm.php');
$remaining = $pay['balance'];
?>
<div class="card-tutor mt-4" id="payroll">
    <div class="head">Payroll</div>
    <div class="p-3">
        <?php if (!empty($pay['tutor_status'])): ?>
            <div class="alert alert-warning py-2 small">You are marked <strong><?php echo strtolower(PayrollModel::PAUSE_KINDS[$pay['tutor_status']['kind']]); ?></strong> since <?php echo date('d M Y', strtotime($pay['tutor_status']['start_date'])); ?>, so no salary adds up until you are back.</div>
        <?php endif; ?>
        <?php $pausedStudents = array_filter($pay['current_students'], fn($s) => !empty($s['pause']) && $s['pause']['state'] === 'active'); if ($pausedStudents): ?>
            <div class="small text-muted mb-2">Salary paused for: <?php echo htmlspecialchars(implode(', ', array_column($pausedStudents, 'student_name'))); ?>.</div>
        <?php endif; ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Earned so far</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($pay['earned_to_date']); ?></div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Paid to you</div><div class="fs-4 fw-bold text-success"><?php echo PayrollCalculator::money($pay['paid_to_date']); ?></div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100 <?php echo $remaining > 0 ? 'border-danger' : ''; ?>"><div class="text-muted small"><?php echo $remaining < 0 ? 'Paid in advance' : 'Remaining to be paid'; ?></div><div class="fs-4 fw-bold <?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo PayrollCalculator::money(abs($remaining)); ?></div></div></div>
        </div>
        <?php $due = $pay['due_month']; $dueLabel = PayrollCalculator::monthLabel($due['month']); ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small">Students on payroll</div><div class="fs-4 fw-bold"><?php echo count($pay['current_students']); ?></div><div class="text-muted small"><?php echo (int)$pay['active_students']; ?> earning this month</div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small"><?php echo $dueLabel; ?> pay</div>
                <div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($due['paid']); ?></div>
                <div class="small"><?php if ($due['earned'] <= 0 && $due['paid'] <= 0): ?><span class="text-muted">Nothing earned</span>
                    <?php elseif ($pay['due_now'] <= 0): ?><span class="badge bg-success">Paid</span>
                    <?php elseif ($due['paid'] > 0): ?><span class="badge bg-warning text-dark">Part paid</span> <?php echo PayrollCalculator::money($pay['due_now']); ?> left
                    <?php else: ?><span class="badge bg-danger">Not paid</span> <?php echo PayrollCalculator::money($pay['due_now']); ?> due<?php endif; ?></div></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="text-muted small"><?php echo PayrollCalculator::monthLabel($pay['as_of']); ?> (this month)</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($pay['this_month']['earned']); ?></div><div class="small"><span class="badge bg-secondary">Not paid yet</span> <span class="text-muted">paid after the month ends</span></div></div></div>
        </div>
        <p class="text-muted small mb-3">You are paid for each month after it ends: a payment made in <?php echo PayrollCalculator::monthLabel($pay['as_of']); ?> is for <?php echo $dueLabel; ?>. Anything not yet paid is added to the next month.</p>

        <?php if ($pendingPayments): ?>
        <div class="alert alert-warning">
            <div class="fw-semibold mb-2">Payments waiting for your confirmation</div>
            <?php foreach ($pendingPayments as $p): ?>
                <form method="POST" action="<?php echo $confirmUrl; ?>" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                    <span>
                        <strong><?php echo PayrollCalculator::money((float)$p['amount']); ?></strong>
                        for <?php echo PayrollCalculator::monthLabel($p['paid_for_month']); ?>,
                        paid on <?php echo htmlspecialchars(date('d M Y', strtotime($p['paid_on']))); ?>
                        <?php if (!empty($p['note'])): ?><span class="text-muted small">(<?php echo htmlspecialchars($p['note']); ?>)</span><?php endif; ?>
                    </span>
                    <button type="submit" class="btn btn-sm btn-brand">I received this</button>
                </form>
            <?php endforeach; ?>
            <?php if (count($pendingPayments) > 1): ?>
                <form method="POST" action="<?php echo $confirmUrl; ?>" class="mt-2"><?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="all">
                    <button type="submit" class="btn btn-sm btn-outline-success">Confirm all <?php echo count($pendingPayments); ?> payments</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pay['months'])): ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Month</th><th class="text-end">Brought forward</th><th class="text-end">Earned</th><th class="text-end">Paid</th><th class="text-end">Remaining</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($pay['months']) as $row): ?>
                    <tr>
                        <td><?php echo PayrollCalculator::monthLabel($row['month']); ?></td>
                        <td class="text-end"><?php echo PayrollCalculator::money($row['brought_forward']); ?></td>
                        <td class="text-end"><?php echo PayrollCalculator::money($row['earned']); ?></td>
                        <td class="text-end text-success"><?php echo PayrollCalculator::money($row['paid']); ?></td>
                        <td class="text-end fw-bold <?php echo $row['balance'] > 0 ? 'text-danger' : ''; ?>"><?php echo PayrollCalculator::money($row['balance']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-muted mb-3">Nothing earned yet. You start earning in the month a student is assigned to you.</p>
        <?php endif; ?>

        <?php if (!empty($pay['payments'])): ?>
        <div class="fw-semibold small mb-1">Payments from the institute</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Date paid</th><th>For month</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($pay['payments'] as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($p['paid_on']))); ?></td>
                        <td><?php echo PayrollCalculator::monthLabel($p['paid_for_month']); ?></td>
                        <td class="text-end"><?php echo PayrollCalculator::money((float)$p['amount']); ?></td>
                        <td><?php echo $p['confirmed_at']
                            ? '<span class="badge bg-success">Confirmed</span>'
                            : '<span class="badge bg-warning text-dark">Waiting for you</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
