<?php
// Superadmin: account statement for a period. Every money line in date order with a running balance, totals by
// type and branch, profit or loss. Print it (or save as PDF from the print dialog), or download CSV.
require __DIR__ . '/_boot.php';

use Models\ExpenseModel;

[$from, $to, $label] = acc_range();
$branchF = isset(AccountingService::BRANCHES[$_GET['branch'] ?? '']) ? $_GET['branch'] : '';
$lines = $accInstalled ? $acc->lines($from, $to) : [];
if ($branchF) $lines = array_values(array_filter($lines, fn($l) => $l['branch'] === $branchF));
$sum = AccountingService::summarise($lines);

if (($_GET['export'] ?? '') === 'csv' && $accInstalled) {
    $ex->log($me, 'export', 'statement', null, ['from' => $from, 'to' => $to, 'branch' => $branchF]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dazu-statement-' . $from . '-to-' . $to . ($branchF ? '-' . $branchF : '') . '.csv"');
    $out = fopen('php://output', 'w');
    $safe = fn(array $row) => array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) && !is_numeric($v) ? "'" . $v : $v, $row);
    fputcsv($out, ['Date', 'Type', 'Details', 'Branch', 'Money in', 'Money out', 'Running balance'], ',', '"', '');
    $run = 0.0;
    foreach ($lines as $l) {
        $run += $l['kind'] === 'in' ? $l['amount'] : -$l['amount'];
        fputcsv($out, $safe([$l['date'], $l['type'], $l['title'], AccountingService::BRANCHES[$l['branch']], $l['kind'] === 'in' ? number_format($l['amount'], 2, '.', '') : '',
                             $l['kind'] === 'out' ? number_format($l['amount'], 2, '.', '') : '', number_format($run, 2, '.', '')]), ',', '"', '');
    }
    fputcsv($out, ['', '', 'Totals', '', number_format($sum['in'], 2, '.', ''), number_format($sum['out'], 2, '.', ''), number_format($sum['net'], 2, '.', '')], ',', '"', '');
    fputcsv($out, ['', '', $sum['result'], '', '', '', number_format(abs($sum['net']), 2, '.', '')], ',', '"', '');
    exit();
}

$q = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['from' => $from, 'to' => $to, 'branch' => $branchF], $over), 'strlen'));
$page_title = 'Accounting · Statement';
$sa_subtitle = 'Every shilling in and out for a period, with a running balance.';
$extra_css = '<style>@media print{.sa-topbar,.sa-bottom,.no-print,nav,header{display:none!important}.card{border:0!important}body{background:#fff!important}}</style>';
ob_start();
echo '<div class="no-print">' . acc_tabs('statement.php', acc_pending($pdo)) . '</div>';
?>
<?php if (!$accInstalled): ?><div class="alert alert-warning">Run System Updates first (migration 084).</div><?php else: ?>
<form class="d-flex flex-wrap gap-2 align-items-end mb-3 no-print" method="GET">
    <div><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?php echo $e($from); ?>"></div>
    <div><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?php echo $e($to); ?>"></div>
    <div><label class="form-label small mb-0">Branch</label><select name="branch" class="form-select form-select-sm"><option value="">All branches</option>
        <?php foreach (AccountingService::BRANCHES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $branchF === $k ? 'selected' : ''; ?>><?php echo $e($l); ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-sm btn-outline-primary">Show</button>
    <a href="<?php echo $e($q(['export' => 'csv'])); ?>" class="btn btn-sm btn-outline-secondary ms-auto"><i class="fas fa-download me-1"></i>Download CSV</a>
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print or save as PDF</button>
</form>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
        <div><h5 class="mb-0">Dazu Training Institute · Statement</h5><div class="text-muted small"><?php echo $e(date('j M Y', strtotime($from)) . ' – ' . date('j M Y', strtotime($to))); ?> · <?php echo $e($branchF ? AccountingService::BRANCHES[$branchF] : 'All branches'); ?></div></div>
        <div class="text-end small text-muted">Printed <?php echo $e(date('j M Y, H:i')); ?></div>
    </div>
    <div class="row g-2 mb-3 small">
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted">Money in</div><div class="fw-bold fs-5"><?php echo $e(ExpenseModel::money($sum['in'])); ?></div></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted">Money out</div><div class="fw-bold fs-5"><?php echo $e(ExpenseModel::money($sum['out'])); ?></div></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted"><?php echo $e($sum['result']); ?></div><div class="fw-bold fs-5 <?php echo $sum['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $e(ExpenseModel::money(abs($sum['net']))); ?></div></div></div>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Date</th><th>Type</th><th>Details</th><th>Branch</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
        <?php if (!$lines): ?><tr><td colspan="7" class="text-muted p-3">No money moved in this period.</td></tr><?php endif; ?>
        <?php $run = 0.0; foreach ($lines as $l): $run += $l['kind'] === 'in' ? $l['amount'] : -$l['amount']; ?>
            <tr><td class="text-nowrap small"><?php echo $e(date('j M Y', strtotime($l['date']))); ?></td><td class="small"><?php echo $e($l['type']); ?></td><td class="small"><?php echo $e($l['title']); ?></td>
                <td class="small"><?php echo $e(AccountingService::BRANCHES[$l['branch']]); ?></td>
                <td class="text-end"><?php echo $l['kind'] === 'in' ? $e(ExpenseModel::money($l['amount'])) : ''; ?></td>
                <td class="text-end"><?php echo $l['kind'] === 'out' ? $e(ExpenseModel::money($l['amount'])) : ''; ?></td>
                <td class="text-end <?php echo $run < 0 ? 'text-danger' : ''; ?>"><?php echo $e(ExpenseModel::money($run)); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold"><td colspan="4">Totals</td><td class="text-end"><?php echo $e(ExpenseModel::money($sum['in'])); ?></td><td class="text-end"><?php echo $e(ExpenseModel::money($sum['out'])); ?></td>
            <td class="text-end <?php echo $sum['net'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $e(ExpenseModel::money($sum['net'])); ?></td></tr></tfoot>
    </table></div>
    <div class="row g-3 small">
        <div class="col-md-6"><div class="fw-semibold mb-1">By type</div><?php foreach ($sum['by_type'] as $t => $v): ?><div class="d-flex justify-content-between border-bottom py-1"><span><?php echo $e($t); ?></span><span><?php echo $e(ExpenseModel::money($v)); ?></span></div><?php endforeach; ?></div>
        <div class="col-md-6"><div class="fw-semibold mb-1">By branch (in / out)</div><?php foreach ($sum['by_branch'] as $b => $v): if (!$v['in'] && !$v['out']) continue; ?><div class="d-flex justify-content-between border-bottom py-1"><span><?php echo $e(AccountingService::BRANCHES[$b]); ?></span><span><?php echo $e(ExpenseModel::money($v['in']) . ' / ' . ExpenseModel::money($v['out'])); ?></span></div><?php endforeach; ?></div>
    </div>
</div></div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
