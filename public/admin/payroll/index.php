<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';

use Models\Connection;
use Models\PayrollModel;
use Models\EmployeeModel;
use Models\PayrollCalculator;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
$employeeModel = new EmployeeModel($pdo);
$installed = $payroll->isInstalled();
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
$extra_css = '<style>
.xl-kpis .card-body{padding:.75rem 1rem}
.xl-shell{border:1px solid #b8c2cc;background:#fff;border-radius:4px;overflow:hidden}
.xl-formula{display:flex;align-items:center;gap:.5rem;background:#f3f6f4;border-bottom:1px solid #b8c2cc;padding:4px 8px;font-size:.8rem}
.xl-formula .xl-ref{min-width:44px;text-align:center;background:#fff;border:1px solid #c9d2d9;padding:1px 6px;font-weight:600}
.xl-formula .xl-fx{color:#188038;font-style:italic;font-weight:700;padding:0 4px;border-left:1px solid #c9d2d9;border-right:1px solid #c9d2d9}
.xl-formula .xl-val{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-height:1.2em}
.xl-scroll{overflow:auto;max-height:70vh}
table.xl{border-collapse:separate;border-spacing:0;width:100%;font-size:.86rem;font-variant-numeric:tabular-nums;min-width:720px}
table.xl th,table.xl td{border-right:1px solid #d5dbe1;border-bottom:1px solid #d5dbe1;padding:5px 10px;vertical-align:top;background:#fff}
table.xl thead th{position:sticky;background:#eef1f3;color:#4b5563;font-weight:600;z-index:2}
table.xl thead tr.xl-letters th{top:0;text-align:center;font-weight:500;font-size:.72rem;padding:2px 6px;color:#6b7280}
table.xl thead tr.xl-head th{top:23px;color:#111827;border-bottom:2px solid #9aa5b1;white-space:nowrap}
table.xl .rn{position:sticky;left:0;z-index:1;width:38px;min-width:38px;text-align:center;background:#eef1f3;color:#6b7280;font-size:.72rem;font-weight:500;padding:5px 4px}
table.xl thead .rn{z-index:3}
table.xl td.num,table.xl th.num{text-align:right;white-space:nowrap}
table.xl tbody tr:hover td{background:#f5faf6}
table.xl td.sel{outline:2px solid #188038;outline-offset:-2px;background:#e8f3ec!important}
table.xl tfoot td,table.xl tfoot th{background:#f3f6f4;font-weight:700;border-top:2px solid #9aa5b1}
table.xl a.xl-link{color:#0b57d0;font-weight:600;text-decoration:none}
table.xl a.xl-link:hover{text-decoration:underline}
.xl-names{font-size:.76rem;color:#6b7280;line-height:1.35;max-width:340px;white-space:normal}
.xl-names .xl-paused{text-decoration:line-through}
.xl-owed{color:#b3261e;font-weight:700}.xl-clear{color:#188038;font-weight:700}.xl-credit{color:#0b57d0;font-weight:700}
.xl-tabs{display:flex;gap:2px;background:#eef1f3;border-top:1px solid #b8c2cc;padding:4px 8px 0}
.xl-tab{border:1px solid #c9d2d9;border-top:0;background:#e2e7ea;padding:4px 16px;font-size:.8rem;cursor:pointer;border-radius:0 0 4px 4px}
.xl-tab.active{background:#fff;color:#188038;font-weight:700;box-shadow:inset 0 -2px 0 #188038}
.xl-sheet{display:none}.xl-sheet.active{display:block}
@media (max-width:576px){.xl-names{max-width:200px}}
</style>';
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
<div class="row g-3 mb-3 xl-kpis">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">School owes (balance)</div><div class="fs-5 fw-bold text-danger"><?php echo PayrollCalculator::money(max(0, $owedAll)); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Earned to date</div><div class="fs-5 fw-bold"><?php echo PayrollCalculator::money($earnedAll); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Paid to date</div><div class="fs-5 fw-bold text-success"><?php echo PayrollCalculator::money($paidAll); ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Tutors / employees</div><div class="fs-5 fw-bold text-primary"><?php echo count($tutors); ?> / <?php echo count($employees); ?></div></div></div></div>
</div>

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
            <tr><th class="rn">1</th><td colspan="6" class="text-muted">No tutors yet. <a href="<?php echo url('admin/tutors/create.php'); ?>">Create the first tutor</a>.</td></tr>
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
            <tr><th class="rn">1</th><td colspan="8" class="text-muted">No employees yet. <a href="employee_create.php">Add the first employee</a>.</td></tr>
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
$extra_js = '<script>
document.addEventListener("DOMContentLoaded", function () {
    var tabs = document.querySelectorAll(".xl-tab"), exp = document.getElementById("exportLink");
    function show(name) {
        document.querySelectorAll(".xl-sheet").forEach(function (s) { s.classList.toggle("active", s.id === "sheet-" + name); });
        tabs.forEach(function (t) { t.classList.toggle("active", t.dataset.sheet === name); });
        if (exp) exp.href = "export.php?sheet=" + name;
        try { history.replaceState(null, "", "#" + name); } catch (e) {}
    }
    tabs.forEach(function (t) { t.addEventListener("click", function () { show(t.dataset.sheet); }); });
    if (location.hash === "#employees") show("employees");
    var ref = document.getElementById("xlRef"), val = document.getElementById("xlVal");
    document.querySelectorAll("table.xl tbody td").forEach(function (td) {
        td.addEventListener("click", function () {
            document.querySelectorAll("td.sel").forEach(function (c) { c.classList.remove("sel"); });
            td.classList.add("sel");
            var tr = td.parentNode, row = tr.querySelector(".rn").textContent.trim();
            ref.textContent = String.fromCharCode(65 + td.cellIndex - 1) + row;
            val.textContent = td.dataset.full || td.textContent.replace(/\s+/g, " ").trim();
        });
    });
});
</script>';
$content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
