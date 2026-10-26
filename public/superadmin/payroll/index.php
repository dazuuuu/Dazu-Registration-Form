<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';

use Models\Connection;
use Models\PayrollModel;
use Models\EmployeeModel;
use Models\PayrollCalculator;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
$employeeModel = new EmployeeModel($pdo);
$installed = $payroll->isInstalled();

if ($installed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revert_current_month') {
    require_once __DIR__ . '/../../../app/v1/models/Session.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } else {
        $n = $payroll->revertCurrentMonthPayments();
        SessionModel::setFlash($n ? 'success' : 'error', $n
            ? $n . ' tutor payment(s) moved to ' . PayrollCalculator::monthLabel(PayrollCalculator::previousMonth()) . '.'
            : 'No tutor payments were recorded against this month.');
    }
    header('Location: index.php');
    exit();
}
$wrongMonthPayments = 0;
if ($installed) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM tutor_payments WHERE paid_for_month >= ?');
    $st->execute([PayrollCalculator::currentMonth() . '-01']);
    $wrongMonthPayments = (int) $st->fetchColumn();
}
$tutors = $installed ? $payroll->listTutorSummaries() : [];
$employees = $employeeModel->isInstalled() ? $employeeModel->listWithBalances() : [];

$num = fn(float $v): string => number_format($v, abs($v - round($v)) < 0.005 ? 0 : 2);
$balanceClass = fn(float $b): string => $b > 0 ? 'xl-owed' : ($b < 0 ? 'xl-credit' : 'xl-clear');

$tTotals = ['students' => 0, 'earned' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
foreach ($tutors as $t) {
    $tTotals['students'] += $t['student_count'];
    $tTotals['earned'] += $t['earned_to_date'];
    $tTotals['paid'] += $t['paid_to_date'];
    $tTotals['balance'] += $t['balance'];
}
$eTotals = ['salary' => 0.0, 'earned' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
foreach ($employees as $e) {
    if ($e['is_active']) $eTotals['salary'] += (float)$e['monthly_salary'];
    $eTotals['earned'] += $e['earned_to_date'];
    $eTotals['paid'] += $e['paid_to_date'];
    $eTotals['balance'] += $e['balance'];
}
$owedAll = $tTotals['balance'] + $eTotals['balance'];
$paidAll = $tTotals['paid'] + $eTotals['paid'];
$earnedAll = $tTotals['earned'] + $eTotals['earned'];

$page_title = 'Salaries';
$breadcrumbs = [['label' => 'Salaries', 'active' => true]];
$extra_css = '<link rel="stylesheet" href="' . sa_asset('css/sheet.css') . '">';
$col = fn(int $i): string => chr(64 + $i);
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-0"><i class="fas fa-wallet me-2"></i>Salaries</h5>
        <span class="text-muted small">Tutors earn <?php echo PayrollCalculator::money(PayrollCalculator::DEFAULT_MONTHLY_RATE); ?> per student per month for the length of the course. Salary stops while a student or tutor is paused, on break or on holiday. Amounts are in KES.</span>
    </div>
    <div class="d-flex gap-2">
        <a href="export.php?sheet=tutors" class="btn btn-outline-success btn-sm" id="exportLink"><i class="fas fa-file-excel me-1"></i>Export CSV</a>
        <a href="employee_create.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus me-1"></i>Add employee</a>
    </div>
</div>

<?php if (!$installed): ?>
    <div class="alert alert-warning">Payroll has not been set up on this database yet. Open <strong>System → Updates</strong> and apply the pending updates (migrations 047 and 048), then reload this page.</div>
<?php else: ?>
<?php if ($wrongMonthPayments): ?>
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><?php echo $wrongMonthPayments; ?> tutor payment(s) are recorded for <?php echo PayrollCalculator::monthLabel(PayrollCalculator::currentMonth()); ?>. Tutors are paid for a month after it ends, so these belong to <?php echo PayrollCalculator::monthLabel(PayrollCalculator::previousMonth()); ?>.</span>
    <form method="POST" onsubmit="return confirm('Move all of them to <?php echo PayrollCalculator::monthLabel(PayrollCalculator::previousMonth()); ?>?');"><?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="revert_current_month">
        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-undo me-1"></i> Move all to <?php echo PayrollCalculator::monthLabel(PayrollCalculator::previousMonth()); ?></button>
    </form>
</div>
<?php endif; ?>
<div class="row g-3 mb-3 xl-kpis">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">School owes (balance)</div><div class="fs-5 fw-bold text-danger"><?php echo PayrollCalculator::money(max(0, $owedAll)); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Earned to date</div><div class="fs-5 fw-bold"><?php echo PayrollCalculator::money($earnedAll); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid to date</div><div class="fs-5 fw-bold text-success"><?php echo PayrollCalculator::money($paidAll); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Tutors / employees</div><div class="fs-5 fw-bold text-primary"><?php echo count($tutors); ?> / <?php echo count($employees); ?></div></div></div></div>
</div>

<div class="xl-toolbar"><label class="xl-search"><i class="fas fa-magnifying-glass"></i><input type="search" data-sheet-search placeholder="Search name, role or student…" autocomplete="off"></label><span class="xl-count" data-sheet-count></span></div>
<div class="xl-shell">
    <div class="xl-formula"><span class="xl-ref" id="xlRef">A1</span><span class="xl-fx">fx</span><span class="xl-val" id="xlVal">Click a cell. Click a name to open that person and record a payment.</span></div>

    <div class="xl-sheet active" id="sheet-tutors">
    <div class="xl-scroll">
    <table class="xl">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 6; $i++) echo '<th>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head"><th class="rn"></th><th>Tutor</th><th>Assigned students</th><th>Status</th><th class="num">Earnings (total)</th><th class="num">Paid</th><th class="num">Balance</th></tr>
        </thead>
        <tbody>
        <?php if (empty($tutors)): ?>
            <tr data-norow="1"><th class="rn">1</th><td colspan="6" class="text-muted">No tutors yet. <a href="<?php echo url('superadmin/tutors/create.php'); ?>">Create the first tutor</a>.</td></tr>
        <?php endif; ?>
        <?php foreach ($tutors as $n => $t):
            $shown = array_slice($t['students'], 0, 6); $more = count($t['students']) - count($shown); ?>
            <tr>
                <th class="rn"><?php echo $n + 1; ?></th>
                <td><a class="xl-link" href="tutor.php?id=<?php echo (int)$t['id']; ?>" title="Open and record a payment"><?php echo htmlspecialchars($t['full_name']); ?></a>
                    <?php if (!$t['is_active']): ?><span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?></td>
                <td data-full="<?php echo htmlspecialchars(implode(', ', array_column($t['students'], 'name'))); ?>">
                    <strong><?php echo (int)$t['student_count']; ?></strong>
                    <?php if ($shown): ?><div class="xl-names"><?php
                        echo implode(', ', array_map(fn($s) => $s['paused'] ? '<span class="xl-paused" title="Salary paused">' . htmlspecialchars($s['name']) . '</span>' : htmlspecialchars($s['name']), $shown));
                        echo $more > 0 ? ' +' . $more . ' more' : ''; ?></div><?php endif; ?></td>
                <td><?php if ($t['status']): ?><span class="badge bg-warning text-dark"><?php echo htmlspecialchars(PayrollModel::PAUSE_KINDS[$t['status']['kind']]); ?></span><?php else: ?><span class="text-success small">Active</span><?php endif; ?></td>
                <td class="num"><?php echo $num($t['earned_to_date']); ?></td>
                <td class="num"><?php echo $num($t['paid_to_date']); ?></td>
                <td class="num <?php echo $balanceClass($t['balance']); ?>"><?php echo $num($t['balance']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($tutors): ?>
        <tfoot><tr><th class="rn"></th><td>Total</td><td><?php echo (int)$tTotals['students']; ?> students</td><td></td>
            <td class="num"><?php echo $num($tTotals['earned']); ?></td><td class="num"><?php echo $num($tTotals['paid']); ?></td><td class="num <?php echo $balanceClass($tTotals['balance']); ?>"><?php echo $num($tTotals['balance']); ?></td></tr></tfoot>
        <?php endif; ?>
    </table>
    </div>
    </div>

    <div class="xl-sheet" id="sheet-employees">
    <div class="xl-scroll">
    <table class="xl">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 8; $i++) echo '<th>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head"><th class="rn"></th><th>Employee</th><th>Role</th><th class="num">Salary / month</th><th class="num">Pay day</th><th class="num">Earnings (total)</th><th class="num">Paid</th><th class="num">Balance</th><th>Next pay</th></tr>
        </thead>
        <tbody>
        <?php if (empty($employees)): ?>
            <tr data-norow="1"><th class="rn">1</th><td colspan="8" class="text-muted">No employees yet. <a href="employee_create.php">Add the first employee</a>.</td></tr>
        <?php endif; ?>
        <?php foreach ($employees as $n => $e): ?>
            <tr>
                <th class="rn"><?php echo $n + 1; ?></th>
                <td><a class="xl-link" href="employee.php?id=<?php echo (int)$e['id']; ?>" title="Open and record a payment"><?php echo htmlspecialchars($e['full_name']); ?></a>
                    <?php if (!$e['is_active']): ?><span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?></td>
                <td><?php echo htmlspecialchars($e['job_title']); ?></td>
                <td class="num"><?php echo $num((float)$e['monthly_salary']); ?></td>
                <td class="num"><?php echo (int)$e['pay_day']; ?></td>
                <td class="num"><?php echo $num($e['earned_to_date']); ?></td>
                <td class="num"><?php echo $num($e['paid_to_date']); ?></td>
                <td class="num <?php echo $balanceClass($e['balance']); ?>"><?php echo $num($e['balance']); ?></td>
                <td class="small"><?php echo $e['next_pay_date'] ? date('d M Y', strtotime($e['next_pay_date'])) : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($employees): ?>
        <tfoot><tr><th class="rn"></th><td>Total</td><td></td><td class="num"><?php echo $num($eTotals['salary']); ?></td><td></td>
            <td class="num"><?php echo $num($eTotals['earned']); ?></td><td class="num"><?php echo $num($eTotals['paid']); ?></td><td class="num <?php echo $balanceClass($eTotals['balance']); ?>"><?php echo $num($eTotals['balance']); ?></td><td></td></tr></tfoot>
        <?php endif; ?>
    </table>
    </div>
    </div>

    <div class="xl-tabs">
        <button type="button" class="xl-tab active" data-sheet="tutors">Tutors (<?php echo count($tutors); ?>)</button>
        <button type="button" class="xl-tab" data-sheet="employees">Employees (<?php echo count($employees); ?>)</button>
    </div>
</div>
<div class="text-muted small mt-2">Earnings are what has been earned so far. Balance = Earnings − Paid, and is what the school still owes. Blue means overpaid.</div>
<?php endif; ?>
<?php
$extra_js = '<script src="' . sa_asset('js/sheet.js') . '"></script><script>
document.addEventListener("sheet:show", function (e) { var x = document.getElementById("exportLink"); if (x) x.href = "export.php?sheet=" + e.detail; });
</script>';
$content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
