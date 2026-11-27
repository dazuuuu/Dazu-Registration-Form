<?php
// Superadmin: create/rename/hide the expense categories used on the Record expenses sheet.
// Categories show as cards everywhere (no dropdowns). Table: migration 142.
require __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accInstalled) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) acc_back('expense_categories.php', null, 'Security check failed. Please try again.');
    if (($_POST['action'] ?? '') === 'add') {
        $r = $ex->addCategory((string) ($_POST['name'] ?? ''), (string) ($_POST['icon'] ?? ''), $me);
        $r['ok'] ? acc_back('expense_categories.php', 'Category saved.') : acc_back('expense_categories.php', null, $r['error']);
    }
    if (($_POST['action'] ?? '') === 'remove') {
        $r = $ex->removeCategory((int) ($_POST['id'] ?? 0), $me);
        $r['ok'] ? acc_back('expense_categories.php', $r['note'] ?? 'Category removed.') : acc_back('expense_categories.php', null, $r['error']);
    }
    acc_back('expense_categories.php', null);
}

$cats = $accInstalled ? $ex->categories(false) : [];
$page_title = 'Accounting · Expense categories';
$sa_subtitle = 'The categories that show as cards when recording an expense.';
ob_start();
echo acc_tabs('expense_categories.php', acc_pending($pdo));
?>
<?php if (!$accInstalled): ?><div class="alert alert-warning">Run System Updates first (migration 142).</div><?php else: ?>
<div class="row g-3">
<div class="col-lg-4">
    <div class="card"><div class="card-header fw-semibold">Add a category</div><div class="card-body">
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add">
            <label class="form-label small fw-semibold">Name</label>
            <input name="name" class="form-control form-control-sm mb-2" maxlength="100" required placeholder="e.g. Staff welfare">
            <label class="form-label small fw-semibold">Icon <span class="text-muted fw-normal">(optional — a Font Awesome name, e.g. fa-mug-hot)</span></label>
            <input name="icon" class="form-control form-control-sm mb-3" maxlength="40" placeholder="fa-receipt">
            <button class="btn btn-primary btn-sm w-100">Save category</button>
        </form>
    </div></div>
</div>
<div class="col-lg-8">
    <div class="card"><div class="card-header fw-semibold">Categories</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
            <?php foreach ($cats as $c): ?>
                <div class="border rounded-3 p-3 text-center position-relative <?php echo $c['is_active'] ? '' : 'bg-light text-muted'; ?>" style="width:140px">
                    <?php if ($c['id'] && $c['is_active']): ?>
                        <form method="POST" onsubmit="return confirm('Remove this category?')" class="position-absolute top-0 end-0 m-1">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <button class="btn btn-sm btn-link text-danger p-0" style="font-size:.9rem" aria-label="Remove">&times;</button>
                        </form>
                    <?php endif; ?>
                    <div class="mb-2"><i class="fas <?php echo $e($c['icon']); ?> fa-lg"></i></div>
                    <div class="small fw-semibold"><?php echo $e($c['name']); ?></div>
                    <?php if (!$c['is_active']): ?><div class="small text-muted">hidden</div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$cats): ?><div class="text-muted small p-3">No categories yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
