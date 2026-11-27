<?php
// Superadmin: today's expense-recording sheet. Click a category card, then fill an excel-style
// table of rows (item, branch, amount) and save them all at once. The sheet always shows only
// today's rows, so it naturally starts blank again the next day — nothing is deleted, yesterday's
// rows still live in View expenses. No dropdowns: category and branch are both picked as cards.
require __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accInstalled) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) acc_back('record.php', null, 'Security check failed. Please try again.');
    if (($_POST['action'] ?? '') === 'save_rows') {
        $cat = (string) ($_POST['category'] ?? '');
        $r = $ex->addDailyRows($cat, (array) ($_POST['title'] ?? []), (array) ($_POST['branch'] ?? []), (array) ($_POST['amount'] ?? []), $me);
        $r['count'] > 0 ? acc_back('record.php?cat=' . urlencode($cat), $r['count'] . ' row(s) saved to today\'s sheet.')
            : acc_back('record.php?cat=' . urlencode($cat), null, 'Nothing to save — fill in at least one item and amount.');
    }
    if (($_POST['action'] ?? '') === 'remove_row') {
        $res = $ex->stopOrDelete((int) ($_POST['id'] ?? 0), $me);
        acc_back('record.php?cat=' . urlencode((string) ($_POST['cat'] ?? '')), $res === 'deleted' ? 'Row removed.' : null,
            $res === 'deleted' ? null : 'Could not remove that row.');
    }
    acc_back('record.php', null);
}

$cats = $accInstalled ? $ex->categories() : [];
$selected = $accInstalled ? $ex->validCategoryCode((string) ($_GET['cat'] ?? '')) : '';
$todayCountByCat = [];
if ($accInstalled) {
    foreach ($ex->todayEntries() as $row) $todayCountByCat[$row['category']] = ($todayCountByCat[$row['category']] ?? 0) + 1;
}
$selectedRows = $accInstalled && isset($_GET['cat']) ? $ex->todayEntries($selected) : [];
$page_title = 'Accounting · Record expenses';
$sa_subtitle = "Today's sheet — " . $e(date('j M Y'));
ob_start();
echo acc_tabs('record.php', acc_pending($pdo));
?>
<?php if (!$accInstalled): ?><div class="alert alert-warning">Run System Updates first (migrations 084 and 142).</div><?php else: ?>

<div class="card mb-3"><div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span>1. Pick a category</span><a href="expense_categories.php" class="small">Manage categories</a>
</div>
<div class="card-body">
    <div class="d-flex flex-wrap gap-2">
    <?php foreach ($cats as $c): ?>
        <a href="record.php?cat=<?php echo urlencode($c['code']); ?>" class="text-decoration-none">
            <div class="border rounded-3 p-3 text-center cat-card <?php echo $selected === $c['code'] ? 'border-primary bg-primary-subtle' : ''; ?>" style="width:140px">
                <div class="mb-2"><i class="fas <?php echo $e($c['icon']); ?> fa-lg"></i></div>
                <div class="small fw-semibold text-dark"><?php echo $e($c['name']); ?></div>
                <?php if (!empty($todayCountByCat[$c['code']])): ?><div class="small text-muted"><?php echo (int) $todayCountByCat[$c['code']]; ?> today</div><?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
    <?php if (!$cats): ?><div class="text-muted small p-3">No categories yet. <a href="expense_categories.php">Add one</a>.</div><?php endif; ?>
    </div>
</div></div>

<?php if ($selected && isset($_GET['cat'])): ?>
<div class="card">
<div class="card-header fw-semibold">2. Record under "<?php echo $e($ex->categoryName($selected)); ?>" — today</div>
<div class="card-body">
    <form method="POST">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_rows"><input type="hidden" name="category" value="<?php echo $e($selected); ?>">
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-2" id="sheetTable">
            <thead><tr><th>Item</th><th style="width:220px">Branch</th><th style="width:140px">Amount (KES)</th><th></th></tr></thead>
            <tbody>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <tr>
                    <td><input name="title[]" class="form-control form-control-sm" maxlength="200" placeholder="What was it?"></td>
                    <td>
                        <div class="btn-group btn-group-sm branch-pick" role="group">
                        <?php foreach (\Models\ExpenseModel::BRANCHES as $bk => $bl): ?>
                            <input type="radio" class="btn-check" name="branch_<?php echo $i; ?>" value="<?php echo $e($bk); ?>" id="b<?php echo $i; ?>_<?php echo $e($bk); ?>" <?php echo $bk === 'general' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="b<?php echo $i; ?>_<?php echo $e($bk); ?>"><?php echo $e($bl); ?></label>
                        <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="branch[]" class="branch-hidden" value="general">
                    </td>
                    <td><input name="amount[]" class="form-control form-control-sm" inputmode="decimal" placeholder="0"></td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger p-0 del" aria-label="Remove">&times;</button></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addRow">+ Add another row</button>
        <div><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Save to today's sheet</button></div>
    </form>

    <hr class="my-4">
    <h6 class="fw-semibold">Already recorded today under this category</h6>
    <?php if (!$selectedRows): ?><div class="text-muted small">Nothing recorded yet today.</div><?php endif; ?>
    <div class="table-responsive"><table class="table table-sm mb-0">
    <?php foreach ($selectedRows as $row): ?>
        <tr><td><?php echo $e($row['title']); ?></td><td class="small text-muted"><?php echo $e(\Models\ExpenseModel::BRANCHES[$row['branch']] ?? $row['branch']); ?></td>
            <td class="text-end"><?php echo $e(\Models\ExpenseModel::money((float) $row['amount'])); ?></td>
            <td class="text-end">
                <form method="POST" onsubmit="return confirm('Remove this row?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove_row">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>"><input type="hidden" name="cat" value="<?php echo $e($selected); ?>">
                    <button class="btn btn-sm btn-link text-danger p-0">Remove</button></form>
            </td></tr>
    <?php endforeach; ?>
    </table></div>
</div></div>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
(function () {
    var t = document.getElementById('sheetTable');
    if (!t) return;
    function wireBranchPick(row) {
        var group = row.querySelector('.branch-pick'), hidden = row.querySelector('.branch-hidden');
        if (!group || !hidden) return;
        group.addEventListener('change', function (e) { if (e.target.type === 'radio') hidden.value = e.target.value; });
    }
    t.querySelectorAll('tbody tr').forEach(wireBranchPick);
    t.addEventListener('click', function (e) {
        if (e.target.classList.contains('del') && t.querySelectorAll('tbody tr').length > 1) e.target.closest('tr').remove();
    });
    document.getElementById('addRow').addEventListener('click', function () {
        var idx = t.querySelectorAll('tbody tr').length;
        var r = t.querySelector('tbody tr').cloneNode(true);
        r.querySelectorAll('input[type="text"], input:not([type])').forEach(function (i) { i.value = ''; });
        r.querySelectorAll('.branch-pick input').forEach(function (radio, j) {
            var newName = 'branch_' + idx, newId = 'b' + idx + '_' + radio.value;
            radio.name = newName; radio.id = newId; radio.checked = radio.value === 'general';
            var lbl = radio.nextElementSibling; if (lbl) lbl.setAttribute('for', newId);
        });
        r.querySelector('.branch-hidden').value = 'general';
        r.querySelectorAll('[name="title[]"], [name="amount[]"]').forEach(function (i) { i.value = ''; });
        t.querySelector('tbody').appendChild(r);
        wireBranchPick(r);
    });
})();
</script>
JS;
require __DIR__ . '/../../templates/superadmin/layout.php';
