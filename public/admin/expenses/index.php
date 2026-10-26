<?php
// Admin: ask the superadmin for money to buy things for the branch, then upload the receipt.
// One request at a time: a new one can be sent only when the last is declined or its receipt is in.
// Real branch admins only (not tutors using granted admin rights). Tables: migration 084.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/ExpenseModel.php';
require_once __DIR__ . '/../../../app/v1/services/ReceiptReader.php';

use Models\ExpenseModel;

Middleware::requireAdmin();
if (!empty($_SESSION['tutor_admin']) || (int) ($_SESSION['role_id'] ?? 0) !== 2) {
    SessionModel::setFlash('error', 'Expense requests are for branch admins.');
    header('Location: ' . url('admin/dashboard.php'));
    exit();
}
$me = (int) $_SESSION['user_id'];
$ex = new ExpenseModel($pdo);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$back = function (?string $ok, ?string $err = null) {
    if ($ok) SessionModel::setFlash('success', htmlspecialchars($ok));
    if ($err) SessionModel::setFlash('error', htmlspecialchars($err));
    header('Location: index.php');
    exit();
};

try {
    $branch = $ex->branchOf($me);
} catch (Throwable $t) {
    $branch = null;
    $notInstalled = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notInstalled)) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $back(null, 'Security check failed. Please refresh and try again.');
    $action = $_POST['action'] ?? '';
    if ($action === 'request') {
        $items = ExpenseModel::cleanItems((array) ($_POST['item'] ?? []), (array) ($_POST['qty'] ?? []), (array) ($_POST['price'] ?? []));
        $r = $ex->createRequest($me, $items, (string) ($_POST['where_buy'] ?? ''),
            ExpenseModel::neededOn((string) ($_POST['when'] ?? ''), (string) ($_POST['needed_date'] ?? '')), (string) ($_POST['note'] ?? ''));
        $r['ok'] ? $back('Request sent to the superadmin. You will see the decision here.') : $back(null, $r['error']);
    }
    if ($action === 'receipt') {
        $req = $ex->openRequest($me);
        if (!$req || $req['status'] !== 'approved') $back(null, 'There is no approved request waiting for a receipt.');
        $stored = ExpenseModel::storeReceipt($_FILES['receipt'] ?? []);
        if (!$stored['ok']) $back(null, $stored['error']);
        $detected = ReceiptReader::amountFromFile($stored['abs'], $stored['mime']);
        $typed = trim((string) ($_POST['spent'] ?? ''));
        $spent = $typed !== '' && is_numeric(str_replace(',', '', $typed)) ? round((float) str_replace(',', '', $typed), 2) : null;
        $r = $ex->closeWithReceipt((int) $req['id'], $me, $stored, (string) ($_FILES['receipt']['name'] ?? 'receipt'), $detected, $spent);
        if (!$r['ok']) {
            @unlink($stored['abs']);
            $back(null, $r['error']);
        }
        $back('Receipt saved. Spent ' . ExpenseModel::money($r['spent']) . ($r['source'] === 'detected' ? ' (read from the receipt)' : '') . '. '
            . ($r['balance'] >= 0 ? 'Balance left: ' . ExpenseModel::money($r['balance']) . '.' : 'Spent ' . ExpenseModel::money(-$r['balance']) . ' more than was given.')
            . ' You can send a new request now.');
    }
    $back(null);
}

$open = empty($notInstalled) ? $ex->openRequest($me) : null;
$history = empty($notInstalled) ? $ex->requests('', $me, 30) : [];
$page_title = 'Expense requests';
$breadcrumbs = [['label' => 'Expense requests', 'active' => true]];
ob_start();
?>
<?php if (!empty($notInstalled)): ?>
    <div class="alert alert-warning">This page needs the latest System Update (migration 084). Ask the superadmin to run it.</div>
<?php elseif (!$branch): ?>
    <div class="alert alert-warning">Your account has no branch yet. Ask the superadmin to set Thika or Nairobi for you (Admins page).</div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-7">
<?php if ($open && $open['status'] === 'pending'): ?>
    <div class="card"><div class="card-body">
        <h5 class="mb-1">Your request is waiting for approval</h5>
        <div class="small text-muted mb-3">Sent <?php echo $e(date('D j M Y, H:i', strtotime($open['created_at']))); ?> · <?php echo $e(ExpenseModel::BRANCHES[$open['branch']]); ?> branch</div>
        <?php require __DIR__ . '/_items.php'; ?>
        <p class="small text-muted mb-0">You can send a new request once this one is decided and, if approved, its receipt is uploaded.</p>
    </div></div>
<?php elseif ($open && $open['status'] === 'approved'): ?>
    <div class="card border-info"><div class="card-body">
        <h5 class="mb-1">Approved: upload the receipt</h5>
        <div class="mb-3">You were given <strong><?php echo $e(ExpenseModel::money((float) $open['amount_given'])); ?></strong>
            <?php echo $open['decision_note'] ? '<div class="small text-muted">Superadmin: ' . $e($open['decision_note']) . '</div>' : ''; ?></div>
        <?php require __DIR__ . '/_items.php'; ?>
        <form method="POST" enctype="multipart/form-data" class="mt-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="receipt">
            <label class="form-label fw-semibold">Receipt <span class="text-muted fw-normal small">(PDF or photo, up to 10 MB)</span></label>
            <input type="file" name="receipt" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" required>
            <label class="form-label fw-semibold">How much was spent? <span class="text-muted fw-normal small">(leave empty to read it from a PDF receipt)</span></label>
            <div class="input-group mb-2" style="max-width:260px"><span class="input-group-text">KES</span><input name="spent" class="form-control" inputmode="decimal" placeholder="e.g. 4,850"></div>
            <div class="small text-muted mb-3">We can read the total from a PDF receipt or e-receipt. For a photo of a paper receipt, type the amount.</div>
            <button class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload receipt</button>
        </form>
    </div></div>
<?php elseif ($branch): ?>
    <div class="card"><div class="card-body">
        <h5 class="mb-1">Request money for a purchase</h5>
        <div class="small text-muted mb-3"><?php echo $e(ExpenseModel::BRANCHES[$branch]); ?> branch · the superadmin approves it and says how much you get.</div>
        <form method="POST" id="reqForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="request">
            <label class="form-label fw-semibold">What do you want to buy?</label>
            <div class="table-responsive"><table class="table table-sm align-middle mb-1" id="itemTable">
                <thead><tr><th>Item</th><th style="width:80px">Qty</th><th style="width:140px">Price each (KES)</th><th style="width:110px" class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <tr><td><input name="item[]" class="form-control form-control-sm" maxlength="150" <?php echo $i === 0 ? 'required' : ''; ?>></td>
                        <td><input name="qty[]" type="number" min="1" value="1" class="form-control form-control-sm"></td>
                        <td><input name="price[]" class="form-control form-control-sm" inputmode="decimal"></td>
                        <td class="text-end small line-total">0</td>
                        <td><button type="button" class="btn btn-sm btn-link text-danger p-0 del" aria-label="Remove">×</button></td></tr>
                <?php endfor; ?>
                </tbody>
            </table></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addItem">+ Add another item</button>
            <div class="d-flex justify-content-end fw-bold mb-3">Estimated total: <span class="ms-2" id="grand">KES 0</span></div>
            <label class="form-label fw-semibold">Where will you buy it?</label>
            <input name="where_buy" class="form-control mb-3" maxlength="200" required placeholder="e.g. Naivas Thika, Luthuli Avenue, Jumia">
            <label class="form-label fw-semibold">When do you need it?</label>
            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <div class="form-check"><input class="form-check-input" type="radio" name="when" value="today" id="wToday" checked><label class="form-check-label" for="wToday">Today</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="when" value="tomorrow" id="wTom"><label class="form-check-label" for="wTom">Tomorrow</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="when" value="date" id="wDate"><label class="form-check-label" for="wDate">On</label></div>
                <input type="date" name="needed_date" class="form-control form-control-sm w-auto" min="<?php echo $e(ExpenseModel::today()); ?>">
            </div>
            <label class="form-label fw-semibold">Note <span class="text-muted fw-normal small">(optional)</span></label>
            <textarea name="note" class="form-control mb-3" rows="2" maxlength="2000"></textarea>
            <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send request</button>
        </form>
    </div></div>
<?php endif; ?>
</div>

<div class="col-lg-5">
    <div class="card"><div class="card-header fw-semibold">My requests</div>
        <?php if (!$history): ?><div class="card-body text-muted small">No requests yet.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($history as $h): [$lbl, $cls] = ExpenseModel::STATUS[$h['status']]; ?>
            <div class="list-group-item small">
                <div class="d-flex justify-content-between"><strong><?php echo $e(implode(', ', array_column($h['items'], 'item'))); ?></strong><span class="badge bg-<?php echo $cls; ?>"><?php echo $e($lbl); ?></span></div>
                <div class="text-muted"><?php echo $e(date('j M Y', strtotime($h['created_at']))); ?> · estimate <?php echo $e(ExpenseModel::money((float) $h['estimated_total'])); ?>
                    <?php if ($h['amount_given'] !== null): ?> · given <?php echo $e(ExpenseModel::money((float) $h['amount_given'])); ?><?php endif; ?>
                    <?php if ($h['spent_amount'] !== null): ?> · spent <?php echo $e(ExpenseModel::money((float) $h['spent_amount'])); ?> · balance <?php echo $e(ExpenseModel::money((float) $h['balance'])); ?><?php endif; ?></div>
                <?php if ($h['receipt_path']): ?><a href="file.php?id=<?php echo (int) $h['id']; ?>" target="_blank">View receipt</a><?php endif; ?>
                <?php if ($h['status'] === 'declined' && $h['decision_note']): ?><div>Reason: <?php echo $e($h['decision_note']); ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
(function () {
    var t = document.getElementById('itemTable');
    if (!t) return;
    function money(v) { return 'KES ' + Math.round(v).toLocaleString(); }
    function sum() {
        var g = 0;
        t.querySelectorAll('tbody tr').forEach(function (r) {
            var q = parseFloat(r.querySelector('[name="qty[]"]').value) || 0, p = parseFloat((r.querySelector('[name="price[]"]').value || '').replace(/,/g, '')) || 0;
            r.querySelector('.line-total').textContent = Math.round(q * p).toLocaleString(); g += q * p;
        });
        document.getElementById('grand').textContent = money(g);
    }
    t.addEventListener('input', sum);
    t.addEventListener('click', function (e) { if (e.target.classList.contains('del') && t.querySelectorAll('tbody tr').length > 1) { e.target.closest('tr').remove(); sum(); } });
    document.getElementById('addItem').addEventListener('click', function () {
        var r = t.querySelector('tbody tr').cloneNode(true);
        r.querySelectorAll('input').forEach(function (i) { i.value = i.name === 'qty[]' ? '1' : ''; i.required = false; });
        t.querySelector('tbody').appendChild(r); sum();
    });
    sum();
})();
</script>
JS;
require __DIR__ . '/../../templates/admin/layout.php';
