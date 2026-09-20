<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\PayrollModel;
use Models\PayrollCalculator;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);

if (!$payroll->isInstalled()) {
    SessionModel::setFlash('error', 'Payroll has not been set up yet. Apply the pending update (migration 047) first.');
    header('Location: index.php');
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$tutor = $payroll->getTutor($id);
if (!$tutor) {
    SessionModel::setFlash('error', 'Tutor not found.');
    header('Location: index.php');
    exit();
}

$def = PayrollCalculator::DEFAULT_MONTHLY_RATE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: tutor.php?id=' . $id);
        exit();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_payment') {
        $r = $payroll->addPayment(
            $id,
            $_POST['amount'] ?? '',
            (string)($_POST['month'] ?? ''),
            (string)($_POST['paid_on'] ?? ''),
            isset($_POST['note']) ? (string)$_POST['note'] : null,
            (int)($_SESSION['user_id'] ?? 0)
        );
        if ($r['ok']) {
            $after = $payroll->getLedger($id);
            $bal = $after['balance'];
            SessionModel::setFlash('success', 'Payment recorded. ' . ($bal > 0
                ? 'Balance still owed: ' . PayrollCalculator::money($bal) . '.'
                : ($bal < 0 ? 'Fully paid, with a credit of ' . PayrollCalculator::money(abs($bal)) . ' carried forward.' : 'Fully paid up.')));
        } else {
            SessionModel::setFlash('error', $r['error']);
        }
    } elseif ($action === 'delete_payment') {
        $ok = $payroll->deletePayment((int)($_POST['payment_id'] ?? 0), $id);
        SessionModel::setFlash($ok ? 'success' : 'error', $ok
            ? 'Payment removed and the balance updated.'
            : 'That payment could not be removed. Payments the tutor has already confirmed are locked.');
    } elseif ($action === 'set_rate') {
        $selected = array_map('intval', (array)($_POST['user_ids'] ?? []));
        $current = array_map('intval', array_column($payroll->getCurrentStudents($id), 'user_id'));
        $valid = array_values(array_intersect($selected, $current));
        if (!$valid) {
            SessionModel::setFlash('error', 'Select at least one student first.');
        } elseif (($_POST['rate_mode'] ?? '') === 'default') {
            $payroll->setStudentRates($id, $valid, null);
            SessionModel::setFlash('success', count($valid) . ' student(s) set back to the default rate of ' . PayrollCalculator::money($def) . '.');
        } else {
            $rate = $_POST['rate'] ?? '';
            if (!is_numeric($rate) || (float)$rate < 0 || (float)$rate > 1000000) {
                SessionModel::setFlash('error', 'Enter a valid monthly rate (0 to 1,000,000).');
            } else {
                $payroll->setStudentRates($id, $valid, (float)$rate);
                SessionModel::setFlash('success', count($valid) . ' student(s) now on ' . PayrollCalculator::money((float)$rate) . ' per month. Everyone else stays on their current rate.');
            }
        }
    }
    header('Location: tutor.php?id=' . $id);
    exit();
}

$ledger = $payroll->getLedger($id);
$asOf = $ledger['as_of'];
$students = $ledger['current_students'];
$former = $ledger['former_students'];
$payments = $ledger['payments'];

$monthlyTotal = 0.0; $courseTotal = 0.0; $earnedSoFar = 0.0;
foreach ($students as $s) {
    if ($s['earning_now']) $monthlyTotal += $s['rate'];
    $courseTotal += $s['total_for_course'];
    $earnedSoFar += $s['earned_to_date'];
}

// Months the payment can be recorded against: every month in the ledger, plus this month. Newest first.
$monthChoices = array_column($ledger['months'], 'month');
if (!in_array($asOf, $monthChoices, true)) $monthChoices[] = $asOf;
rsort($monthChoices);

$statusOf = function (array $s) use ($asOf): string {
    if ($s['months'] <= 0) return 'No approved course';
    if ($s['paused_now']) return 'Salary paused' . (!empty($s['pause']) ? ' (' . strtolower(\Models\PayrollModel::PAUSE_KINDS[$s['pause']['kind']]) . ')' : ' (tutor away)');
    if ($s['start_month'] > $asOf) return 'Starts ' . PayrollCalculator::monthLabel($s['start_month']);
    if ($s['months_remaining'] <= 0) return 'Course finished';
    return 'Month ' . $s['months_elapsed'] . ' of ' . $s['months'];
};

$page_title = $tutor['full_name'] . ' — Payroll';
$breadcrumbs = [
    ['label' => 'Payroll', 'url' => 'index.php'],
    ['label' => $tutor['full_name'], 'active' => true],
];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h5 class="mb-0"><i class="fas fa-wallet me-2"></i><?php echo htmlspecialchars($tutor['full_name']); ?></h5>
        <div class="text-muted small"><?php echo htmlspecialchars($tutor['email']); ?> · Payroll up to <?php echo PayrollCalculator::monthLabel($asOf); ?></div>
    </div>
    <a href="<?php echo url('admin/tutors/view.php?id=' . $id); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-edit me-1"></i> Assign, pause or move students</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Students</div><div class="fs-4 fw-bold text-primary"><?php echo count($students); ?></div><div class="text-muted small"><?php echo (int)$ledger['active_students']; ?> earning this month</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Earned to date</div><div class="fs-4 fw-bold"><?php echo PayrollCalculator::money($ledger['earned_to_date']); ?></div><div class="text-muted small">This month: <?php echo PayrollCalculator::money($ledger['this_month']['earned']); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid to date</div><div class="fs-4 fw-bold text-success"><?php echo PayrollCalculator::money($ledger['paid_to_date']); ?></div><?php if ($ledger['pending_confirmation'] > 0): ?><div class="small text-warning"><?php echo (int)$ledger['pending_confirmation']; ?> awaiting tutor confirmation</div><?php endif; ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-<?php echo $ledger['balance'] > 0 ? 'danger' : 'success'; ?>"><div class="card-body"><div class="text-muted small"><?php echo $ledger['balance'] < 0 ? 'Credit (overpaid)' : 'Balance owed'; ?></div><div class="fs-4 fw-bold <?php echo $ledger['balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo PayrollCalculator::money(abs($ledger['balance'])); ?></div><div class="text-muted small">Includes any unpaid earlier months</div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header fw-semibold">Record a payment</div>
            <div class="card-body">
                <form method="POST"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_payment">
                    <div class="mb-3">
                        <label class="form-label">Amount paid (KES)</label>
                        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="e.g. 25000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deduct from month</label>
                        <select name="month" class="form-select">
                            <?php foreach ($monthChoices as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $m === $asOf ? 'selected' : ''; ?>><?php echo PayrollCalculator::monthLabel($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date paid</label>
                        <input type="date" name="paid_on" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note <span class="text-muted">(optional)</span></label>
                        <input type="text" name="note" class="form-control" maxlength="255" placeholder="e.g. M-Pesa code">
                    </div>
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i> Record payment</button>
                    <div class="text-muted small mt-2">Whatever is not paid stays owed and carries forward to the next month automatically.</div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header fw-semibold">Month by month</div>
            <div class="card-body p-0">
                <?php if (empty($ledger['months'])): ?>
                    <p class="text-muted p-4 mb-0">Nothing earned yet. Earnings start in the month a student is assigned to this tutor.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Month</th><th class="text-end">Brought forward</th><th class="text-end">Earned</th><th class="text-end">Paid</th><th class="text-end">Balance owed</th></tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($ledger['months']) as $row): ?>
                            <tr <?php echo $row['month'] === $asOf ? 'class="table-active"' : ''; ?>>
                                <td><?php echo PayrollCalculator::monthLabel($row['month']); ?> <span class="text-muted small">(<?php echo (int)$row['active_students']; ?> students)</span></td>
                                <td class="text-end"><?php echo PayrollCalculator::money($row['brought_forward']); ?></td>
                                <td class="text-end"><?php echo PayrollCalculator::money($row['earned']); ?></td>
                                <td class="text-end text-success"><?php echo PayrollCalculator::money($row['paid']); ?></td>
                                <td class="text-end fw-bold <?php echo $row['balance'] > 0 ? 'text-danger' : ''; ?>"><?php echo PayrollCalculator::money($row['balance']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Students and rates</div>
    <form method="POST" id="rateForm"><?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="set_rate">
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
                <p class="text-muted p-4 mb-0">No students assigned to this tutor. <a href="<?php echo url('admin/tutors/view.php?id=' . $id); ?>">Assign students</a>.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                            <th>Student</th><th>Course</th><th>Starts</th><th class="text-center">Months</th>
                            <th class="text-end">Rate / month</th><th class="text-end">Course total</th><th class="text-end">Earned so far</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><input type="checkbox" name="user_ids[]" value="<?php echo (int)$s['user_id']; ?>" class="form-check-input student-check"></td>
                            <td><strong><?php echo htmlspecialchars($s['student_name']); ?></strong><?php if (!empty($s['registration_no'])): ?><div class="text-muted small"><?php echo htmlspecialchars($s['registration_no']); ?></div><?php endif; ?></td>
                            <td><?php if (!empty($s['course_title'])): ?><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['abbreviation']); ?></span> <span class="small"><?php echo htmlspecialchars($s['course_title']); ?></span><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                            <td><?php echo PayrollCalculator::monthLabel($s['start_month']); ?></td>
                            <td class="text-center"><?php echo (int)$s['months']; ?></td>
                            <td class="text-end"><?php echo PayrollCalculator::money($s['rate']); ?><?php if ($s['is_custom']): ?> <span class="badge bg-info text-dark">Custom</span><?php endif; ?></td>
                            <td class="text-end"><?php echo PayrollCalculator::money($s['total_for_course']); ?></td>
                            <td class="text-end"><?php echo PayrollCalculator::money($s['earned_to_date']); ?></td>
                            <td class="small"><?php echo htmlspecialchars($statusOf($s)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total per month (students earning now)</th>
                            <th class="text-end"><?php echo PayrollCalculator::money($monthlyTotal); ?></th>
                            <th class="text-end"><?php echo PayrollCalculator::money($courseTotal); ?></th>
                            <th class="text-end"><?php echo PayrollCalculator::money($earnedSoFar); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="p-3 border-top d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label small mb-1">Monthly rate for the selected students (KES)</label>
                    <input type="number" name="rate" class="form-control" min="0" max="1000000" step="0.01" placeholder="e.g. 7000" style="width:210px">
                </div>
                <button type="submit" name="rate_mode" value="custom" class="btn btn-primary">Apply to selected</button>
                <button type="submit" name="rate_mode" value="default" formnovalidate class="btn btn-outline-secondary">Reset selected to <?php echo PayrollCalculator::money($def); ?></button>
                <div class="text-muted small w-100">Tick one or several students. Everyone you don't tick keeps their current rate. The new rate applies to that student's whole course.</div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Payments recorded</div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <p class="text-muted p-4 mb-0">No payments recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Date paid</th><th>For month</th><th class="text-end">Amount</th><th>Note</th><th>Tutor</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($p['paid_on']))); ?></td>
                        <td><?php echo PayrollCalculator::monthLabel($p['paid_for_month']); ?></td>
                        <td class="text-end fw-semibold"><?php echo PayrollCalculator::money((float)$p['amount']); ?></td>
                        <td class="small"><?php echo htmlspecialchars((string)$p['note']); ?></td>
                        <td>
                            <?php if ($p['confirmed_at']): ?>
                                <span class="badge bg-success">Confirmed <?php echo htmlspecialchars(date('d M Y', strtotime($p['confirmed_at']))); ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Awaiting confirmation</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (!$p['confirmed_at']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this payment? The balance owed will go back up.');"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_payment">
                                <input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove this payment"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($ledger['pauses'])): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Pauses, breaks and holidays <span class="text-muted fw-normal small">(no salary adds up during these)</span></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Who</th><th>Type</th><th>From</th><th>Back</th><th>Note</th></tr></thead>
            <tbody>
            <?php foreach ($ledger['pauses'] as $p): ?>
                <tr>
                    <td><?php echo $p['user_id'] === null ? '<strong>Tutor</strong>' : htmlspecialchars((string)$p['student_name']); ?></td>
                    <td><?php echo htmlspecialchars(\Models\PayrollModel::PAUSE_KINDS[$p['kind']]); ?></td>
                    <td><?php echo date('d M Y', strtotime($p['start_date'])); ?></td>
                    <td><?php echo $p['end_date'] ? date('d M Y', strtotime($p['end_date'])) : '<span class="badge bg-warning text-dark">Still paused</span>'; ?></td>
                    <td class="small"><?php echo htmlspecialchars((string)$p['note']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($former)): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Students taken off this tutor <span class="text-muted fw-normal small">(what they earned is kept)</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Student</th><th>Course</th><th>Assigned</th><th>Removed</th><th class="text-center">Months earned</th><th class="text-end">Rate / month</th><th class="text-end">Earned</th></tr></thead>
                <tbody>
                <?php foreach ($former as $f): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$f['student_name']); ?></td>
                        <td class="small"><?php echo htmlspecialchars((string)($f['abbreviation'] ?? '')); ?> <?php echo htmlspecialchars((string)($f['course_title'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($f['assigned_at']))); ?></td>
                        <td><?php echo htmlspecialchars(date('d M Y', strtotime($f['removed_at']))); ?></td>
                        <td class="text-center"><?php echo (int)$f['months_elapsed']; ?></td>
                        <td class="text-end"><?php echo PayrollCalculator::money($f['rate']); ?></td>
                        <td class="text-end"><?php echo PayrollCalculator::money($f['earned_to_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$extra_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
    var all = document.getElementById("selectAll");
    if (!all) return;
    all.addEventListener("change", function () {
        document.querySelectorAll(".student-check").forEach(function (c) { c.checked = all.checked; });
    });
});
</script>';
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
