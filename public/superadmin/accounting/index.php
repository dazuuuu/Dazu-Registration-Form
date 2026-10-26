<?php
// Superadmin: Accounting overview. Money in (school fees) and out (tutor shares, salaries, expenses) for a period,
// profit or loss, each branch (Thika, Nairobi, Online), what was spent most on, a 12-month trend, and balances.
require __DIR__ . '/_boot.php';

use Models\ExpenseModel;

[$from, $to, $label] = acc_range();
$m = fn($v) => ExpenseModel::money((float) $v);
if ($accInstalled) {
    $sum = AccountingService::summarise($acc->lines($from, $to));
    $trend = $acc->trend(12);
    $bal = $acc->balances();
}
$branches = ['thika', 'nairobi', 'online', 'general', 'unset'];
$page_title = 'Accounting';
$sa_subtitle = 'Money in and out, profit or loss, and how each branch is doing.';
ob_start();
echo acc_tabs('index.php', acc_pending($pdo));
?>
<?php if (!$accInstalled): ?>
<div class="alert alert-warning">Run System Updates first (migration 084: expenses and accounting).</div>
<?php else: ?>
<form class="d-flex flex-wrap gap-2 align-items-end mb-3" method="GET">
    <div><label class="form-label small mb-0">Month</label><input type="month" name="month" class="form-control form-control-sm" value="<?php echo $e(substr($from, 0, 7)); ?>"></div>
    <button class="btn btn-sm btn-outline-primary">Show month</button>
    <span class="text-muted small mx-2">or</span>
    <div><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?php echo $e($_GET['from'] ?? ''); ?>"></div>
    <div><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?php echo $e($_GET['to'] ?? ''); ?>"></div>
    <button class="btn btn-sm btn-outline-primary">Show range</button>
    <a href="statement.php?from=<?php echo $e($from); ?>&amp;to=<?php echo $e($to); ?>" class="btn btn-sm btn-primary ms-auto"><i class="fas fa-file-invoice-dollar me-1"></i>Statement for this period</a>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Money in · <?php echo $e($label); ?></div><div class="fs-4 fw-bold"><?php echo $e($m($sum['in'])); ?></div><div class="small text-muted">School fees</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Money out</div><div class="fs-4 fw-bold"><?php echo $e($m($sum['out'])); ?></div><div class="small text-muted">Shares, salaries, expenses</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 <?php echo $sum['net'] < 0 ? 'border-danger' : 'border-success'; ?>"><div class="card-body"><div class="small text-muted"><?php echo $e($sum['result']); ?></div>
        <div class="fs-4 fw-bold <?php echo $sum['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><i class="fas <?php echo $sum['net'] < 0 ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up'; ?> me-1"></i><?php echo $e($m(abs($sum['net']))); ?></div>
        <div class="small text-muted">Money in minus money out</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">Owed to tutors and staff now</div><div class="fs-4 fw-bold"><?php echo $e($m($bal['tutors'] + $bal['employees'])); ?></div><div class="small text-muted">Unpaid shares and salaries</div></div></div></div>
</div>

<div class="row g-3 mb-3">
<div class="col-lg-7">
    <div class="card h-100"><div class="card-header fw-semibold">Branches · <?php echo $e($label); ?></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Branch</th><th class="text-end">Earned</th><th class="text-end">Spent</th><th class="text-end">Profit or loss</th></tr></thead>
        <tbody>
        <?php $spentMost = null; $max = 0; foreach (['thika', 'nairobi', 'online'] as $b) if ($sum['by_branch'][$b]['out'] > $max) { $max = $sum['by_branch'][$b]['out']; $spentMost = $b; } ?>
        <?php foreach ($branches as $b): $r = $sum['by_branch'][$b]; if ($b === 'unset' && !$r['in'] && !$r['out']) continue; ?>
            <tr><td><?php echo $e(AccountingService::BRANCHES[$b]); ?><?php echo $b === $spentMost ? ' <span class="badge bg-warning text-dark">Spent the most</span>' : ''; ?>
                    <?php echo $b === 'general' ? '<div class="small text-muted">Tutor shares, salaries, institute-wide costs</div>' : ''; ?></td>
                <td class="text-end"><?php echo $e($m($r['in'])); ?></td><td class="text-end"><?php echo $e($m($r['out'])); ?></td>
                <td class="text-end fw-semibold <?php echo $r['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $r['net'] < 0 ? 'Loss ' : 'Profit '; ?><?php echo $e($m(abs($r['net']))); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold"><td>All</td><td class="text-end"><?php echo $e($m($sum['in'])); ?></td><td class="text-end"><?php echo $e($m($sum['out'])); ?></td>
            <td class="text-end <?php echo $sum['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $e($sum['result'] . ' ' . $m(abs($sum['net']))); ?></td></tr></tfoot>
    </table></div>
    <?php if ($sum['by_branch']['unset']['in'] > 0): ?><div class="card-footer small text-muted">"Branch not set": fees from students whose study location is empty. Set Thika or Nairobi on the student to count them for a branch.</div><?php endif; ?>
    </div>
</div>
<div class="col-lg-5">
    <div class="card h-100"><div class="card-header fw-semibold">Where the money went</div><div class="card-body">
        <?php $out = $sum['by_type']; unset($out['School fees']); arsort($out); ?>
        <?php if (!$out): ?><div class="text-muted small">Nothing spent in this period.</div><?php endif; ?>
        <?php foreach ($out as $type => $v): $pc = $sum['out'] > 0 ? round($v / $sum['out'] * 100) : 0; ?>
            <div class="d-flex justify-content-between small"><span><?php echo $e($type); ?></span><span class="fw-semibold"><?php echo $e($m($v)); ?> · <?php echo $pc; ?>%</span></div>
            <div class="progress mb-2" style="height:8px" role="img" aria-label="<?php echo $e($type . ' ' . $pc . '%'); ?>"><div class="progress-bar" style="width:<?php echo $pc; ?>%;background:#5b5fd6"></div></div>
        <?php endforeach; ?>
        <?php if ($sum['by_category']): ?>
            <div class="small fw-semibold mt-3 mb-1">Expenses by kind</div>
            <?php foreach (array_slice($sum['by_category'], 0, 6, true) as $c => $v): ?>
                <div class="d-flex justify-content-between small border-bottom py-1"><span><?php echo $e(ExpenseModel::CATEGORIES[$c] ?? $c); ?></span><span><?php echo $e($m($v)); ?></span></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div></div>
</div>
</div>

<div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between flex-wrap gap-2"><span>Last 12 months</span>
    <span class="small fw-normal"><span class="d-inline-block me-1 align-middle" style="width:10px;height:10px;border-radius:2px;background:#44A024"></span>Money in
        <span class="d-inline-block ms-3 me-1 align-middle" style="width:10px;height:10px;border-radius:2px;background:#5b5fd6"></span>Money out</span></div>
<div class="card-body">
    <?php
    $maxV = max(1, max(array_merge(array_column($trend, 'in'), array_column($trend, 'out'))));
    $W = 720; $H = 200; $pad = 28; $gw = ($W - $pad) / count($trend); $bw = max(4, min(18, $gw / 2 - 4));
    ?>
    <svg viewBox="0 0 <?php echo $W; ?> <?php echo $H + 24; ?>" class="w-100" role="img" aria-label="Money in and out for the last 12 months; the table below has the numbers">
        <?php foreach ([0, 0.5, 1] as $g): $y = $H - $g * ($H - 10); ?>
            <line x1="<?php echo $pad; ?>" x2="<?php echo $W; ?>" y1="<?php echo $y; ?>" y2="<?php echo $y; ?>" stroke="#e5e7eb" stroke-width="1"/>
            <text x="<?php echo $pad - 4; ?>" y="<?php echo $y + 3; ?>" font-size="9" text-anchor="end" fill="#6b7280"><?php echo $g ? round($maxV * $g / 1000) . 'k' : '0'; ?></text>
        <?php endforeach; ?>
        <?php foreach ($trend as $i => $t): $x = $pad + $i * $gw + $gw / 2; ?>
            <?php foreach ([['in', '#44A024', -$bw - 1], ['out', '#5b5fd6', 1]] as [$k, $col, $dx]): $h = $t[$k] / $maxV * ($H - 10); ?>
                <g><title><?php echo $e(date('F Y', strtotime($t['month'] . '-01')) . ' · ' . ($k === 'in' ? 'Money in ' : 'Money out ') . $m($t[$k])); ?></title>
                <rect x="<?php echo round($x + $dx, 1); ?>" y="<?php echo round($H - max($h, 0.5), 1); ?>" width="<?php echo round($bw, 1); ?>" height="<?php echo round(max($h, 0.5), 1); ?>" rx="2" fill="<?php echo $col; ?>"/></g>
            <?php endforeach; ?>
            <text x="<?php echo round($x, 1); ?>" y="<?php echo $H + 14; ?>" font-size="9" text-anchor="middle" fill="#6b7280"><?php echo $e(date('M', strtotime($t['month'] . '-01'))); ?></text>
        <?php endforeach; ?>
    </svg>
    <details class="mt-2"><summary class="small">Show as a table</summary>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Month</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Profit or loss</th></tr></thead>
            <tbody><?php foreach (array_reverse($trend) as $t): ?><tr><td><a href="?month=<?php echo $e($t['month']); ?>"><?php echo $e(date('F Y', strtotime($t['month'] . '-01'))); ?></a></td>
                <td class="text-end"><?php echo $e($m($t['in'])); ?></td><td class="text-end"><?php echo $e($m($t['out'])); ?></td>
                <td class="text-end <?php echo $t['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $t['net'] < 0 ? 'Loss ' : 'Profit '; ?><?php echo $e($m(abs($t['net']))); ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </details>
</div></div>

<div class="row g-3">
<div class="col-lg-6">
    <div class="card h-100"><div class="card-header fw-semibold">Balances remaining (today)</div>
    <div class="list-group list-group-flush small">
        <div class="list-group-item d-flex justify-content-between"><span>Owed to tutors (shares)</span><strong><?php echo $e($m($bal['tutors'])); ?></strong></div>
        <?php foreach ($bal['tutor_rows'] as $t): if (abs($t['balance']) < 0.01) continue; ?>
            <div class="list-group-item d-flex justify-content-between ps-4 text-muted"><span><?php echo $e($t['name']); ?> · <?php echo (int) $t['students']; ?> students</span><span><?php echo $e($t['balance'] < 0 ? 'Paid ahead ' . $m(-$t['balance']) : $m($t['balance'])); ?></span></div>
        <?php endforeach; ?>
        <div class="list-group-item d-flex justify-content-between"><span>Owed to employees (salaries)</span><strong><?php echo $e($m($bal['employees'])); ?></strong></div>
        <?php foreach ($bal['employee_rows'] as $t): if (abs($t['balance']) < 0.01) continue; ?>
            <div class="list-group-item d-flex justify-content-between ps-4 text-muted"><span><?php echo $e($t['name'] . ' · ' . $t['role']); ?></span><span><?php echo $e($m($t['balance'])); ?></span></div>
        <?php endforeach; ?>
        <div class="list-group-item d-flex justify-content-between"><span>School fees students still owe <span class="text-muted">(estimate)</span></span><strong><?php echo $e($m($bal['fees_owed'])); ?></strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Money given to branches, receipt not in yet</span><strong><?php echo $e($m($bal['cash_out'])); ?></strong></div>
        <div class="list-group-item d-flex justify-content-between"><span>Change left with branches after purchases</span><strong><?php echo $e($m($bal['branch_change'])); ?></strong></div>
    </div></div>
</div>
<div class="col-lg-6">
    <div class="card h-100"><div class="card-header fw-semibold">How money flows</div><div class="card-body small">
        <p class="mb-2"><strong>In:</strong> school fees, counted on the day the payment was approved, for the student's branch (Online for online students).</p>
        <p class="mb-2"><strong>Out:</strong> tutor shares (Salaries page), employee salaries (Salaries page), and expenses (Expense tracker, which also gets every branch purchase once its receipt is uploaded). Repeating expenses like rent count every period.</p>
        <p class="mb-0"><strong>Tutors are paid in shares</strong> (a monthly amount per student); <strong>employees are paid a salary</strong>. Corrections tutors ask for appear under Requests and change their balance only when you approve.</p>
    </div></div>
</div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
