<?php
// Superadmin: the expense tracker. Record any cost (rent, electricity, food, what was bought...), one time or
// repeating every day / week / month, for a branch or the whole institute, with an optional receipt.
// Branch purchases appear here automatically once their receipt is uploaded.
require __DIR__ . '/_boot.php';

use Models\ExpenseModel;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accInstalled) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) acc_back('expenses.php', null, 'Security check failed. Please try again.');
    if (($_POST['action'] ?? '') === 'add') {
        $receipt = null;
        if (($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $receipt = ExpenseModel::storeReceipt($_FILES['receipt']);
            if (!$receipt['ok']) acc_back('expenses.php', null, $receipt['error']);
        }
        $r = $ex->addExpense($_POST, $receipt, $_FILES['receipt']['name'] ?? null, $me);
        if (!$r['ok'] && $receipt) @unlink($receipt['abs']);
        $r['ok'] ? acc_back('expenses.php', 'Expense saved.') : acc_back('expenses.php', null, $r['error']);
    }
    if (($_POST['action'] ?? '') === 'stop') {
        $res = $ex->stopOrDelete((int) ($_POST['id'] ?? 0), $me);
        acc_back('expenses.php', ['deleted' => 'Expense deleted.', 'stopped' => 'Stopped from today. Earlier ones still count.'][$res] ?? null,
            ['locked' => 'Branch purchases come from a receipt and cannot be removed here.', 'missing' => 'Expense not found.'][$res] ?? null);
    }
    acc_back('expenses.php', null);
}

[$from, $to, $label] = acc_range();
$list = $accInstalled ? $ex->expenses() : [];
$lines = $accInstalled ? $ex->expenseLines($from, $to) : [];
$page_title = 'Accounting · Expense tracker';
$sa_subtitle = 'Every cost of running the institute: one time or repeating.';
ob_start();
echo acc_tabs('expenses.php', acc_pending($pdo));
?>
<?php if (!$accInstalled): ?><div class="alert alert-warning">Run System Updates first (migration 084).</div><?php else: ?>
<div class="row g-3">
<div class="col-lg-4">
    <div class="card"><div class="card-header fw-semibold">Add an expense</div><div class="card-body">
        <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add">
            <label class="form-label small fw-semibold">What is it?</label><input name="title" class="form-control form-control-sm mb-2" maxlength="200" required placeholder="e.g. Rent Thika office">
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold">Kind</label><select name="category" class="form-select form-select-sm"><?php foreach (ExpenseModel::CATEGORIES as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $e($l); ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label small fw-semibold">Branch</label><select name="branch" class="form-select form-select-sm"><?php foreach (ExpenseModel::BRANCHES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $k === 'general' ? 'selected' : ''; ?>><?php echo $e($l); ?></option><?php endforeach; ?></select></div>
            </div>
            <label class="form-label small fw-semibold">Amount (KES)</label><input name="amount" class="form-control form-control-sm mb-2" inputmode="decimal" required>
            <label class="form-label small fw-semibold">How often?</label>
            <select name="repeat_every" class="form-select form-select-sm mb-2" id="repeatSel"><?php foreach (ExpenseModel::REPEATS as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $e($l); ?></option><?php endforeach; ?></select>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label small fw-semibold" id="startLbl">Date</label><input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $e(ExpenseModel::today()); ?>" required></div>
                <div class="col-6 d-none" id="endBox"><label class="form-label small fw-semibold">Until <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="end_date" class="form-control form-control-sm"></div>
            </div>
            <label class="form-label small fw-semibold">Receipt <span class="text-muted fw-normal">(optional, PDF or photo)</span></label><input type="file" name="receipt" class="form-control form-control-sm mb-2" accept=".pdf,image/*">
            <label class="form-label small fw-semibold">Note <span class="text-muted fw-normal">(optional)</span></label><textarea name="note" class="form-control form-control-sm mb-3" rows="2"></textarea>
            <button class="btn btn-primary btn-sm w-100">Save expense</button>
        </form>
    </div></div>
</div>
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-header fw-semibold">All expenses</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Expense</th><th>Branch</th><th>How often</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        <?php if (!$list): ?><tr><td colspan="5" class="text-muted small p-3">Nothing recorded yet.</td></tr><?php endif; ?>
        <?php foreach ($list as $x): $stopped = $x['repeat_every'] !== 'once' && $x['end_date'] && $x['end_date'] < ExpenseModel::today(); ?>
            <tr class="<?php echo $stopped ? 'text-muted' : ''; ?>">
                <td><div class="fw-semibold"><?php echo $e($x['title']); ?></div><div class="small text-muted"><?php echo $e(ExpenseModel::CATEGORIES[$x['category']] ?? $x['category']); ?>
                    <?php echo $x['request_id'] ? ' · branch purchase #' . (int) $x['request_id'] : ''; ?><?php echo $x['note'] ? ' · ' . $e($x['note']) : ''; ?></div></td>
                <td class="small"><?php echo $e(ExpenseModel::BRANCHES[$x['branch']]); ?></td>
                <td class="small"><?php echo $e(ExpenseModel::REPEATS[$x['repeat_every']]); ?><div class="text-muted"><?php echo $x['repeat_every'] === 'once' ? $e(date('j M Y', strtotime($x['start_date']))) : 'from ' . $e(date('j M Y', strtotime($x['start_date']))) . ($x['end_date'] ? ' to ' . $e(date('j M Y', strtotime($x['end_date']))) : ''); ?></div></td>
                <td class="text-end fw-semibold"><?php echo $e(ExpenseModel::money((float) $x['amount'])); ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($x['receipt_path']): ?><a class="btn btn-sm btn-link" target="_blank" href="<?php echo $e(url($x['request_id'] ? 'admin/expenses/file.php?id=' . (int) $x['request_id'] : 'admin/expenses/file.php?expense=' . (int) $x['id'])); ?>">Receipt</a><?php endif; ?>
                    <?php if (!$x['request_id'] && !$stopped): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo $x['repeat_every'] === 'once' ? 'Delete this expense?' : 'Stop this repeating expense from today?'; ?>')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="stop"><input type="hidden" name="id" value="<?php echo (int) $x['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><?php echo $x['repeat_every'] === 'once' ? 'Delete' : 'Stop'; ?></button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>

    <div class="card"><div class="card-header fw-semibold d-flex justify-content-between flex-wrap gap-2"><span>Counted in <?php echo $e($label); ?></span>
        <form method="GET" class="d-flex gap-2"><input type="month" name="month" class="form-control form-control-sm" value="<?php echo $e(substr($from, 0, 7)); ?>"><button class="btn btn-sm btn-outline-primary">Show</button></form></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <tbody><?php foreach ($lines as $l): ?><tr><td class="small"><?php echo $e(date('D j M', strtotime($l['date']))); ?></td><td><?php echo $e($l['title']); ?></td><td class="small"><?php echo $e(ExpenseModel::BRANCHES[$l['branch']]); ?></td><td class="text-end"><?php echo $e(ExpenseModel::money($l['amount'])); ?></td></tr><?php endforeach; ?>
        <?php if (!$lines): ?><tr><td class="text-muted small p-3">No expenses in this period.</td></tr><?php endif; ?></tbody>
        <tfoot><tr class="fw-bold"><td colspan="3">Total</td><td class="text-end"><?php echo $e(ExpenseModel::money(array_sum(array_column($lines, 'amount')))); ?></td></tr></tfoot>
    </table></div></div>
</div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
(function () {
    var s = document.getElementById('repeatSel');
    if (!s) return;
    function up() { var once = s.value === 'once'; document.getElementById('endBox').classList.toggle('d-none', once); document.getElementById('startLbl').textContent = once ? 'Date' : 'First date'; }
    s.addEventListener('change', up); up();
})();
</script>
JS;
require __DIR__ . '/../../templates/superadmin/layout.php';
