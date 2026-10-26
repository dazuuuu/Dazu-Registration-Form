<?php
// Superadmin: decide requests. Branch purchase requests (approve with the amount given, or decline) and tutor share
// corrections (unassign a student, student completed, correct a balance), which change payroll only on approval.
require __DIR__ . '/_boot.php';

use Models\ExpenseModel;
use Models\PayrollRequestModel;
use Models\PayrollCalculator;

$prm = new PayrollRequestModel($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accInstalled) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) acc_back('requests.php', null, 'Security check failed. Please try again.');
    $id = (int) ($_POST['id'] ?? 0);
    $approve = ($_POST['decision'] ?? '') === 'approve';
    if (($_POST['action'] ?? '') === 'expense') {
        $given = trim((string) ($_POST['amount_given'] ?? ''));
        $r = $ex->decide($id, $approve, $given !== '' ? (float) str_replace(',', '', $given) : null, (string) ($_POST['note'] ?? ''), $me);
        if ($r['ok']) {
            $req = $ex->request($id);
            try {
                require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
                PortalNotifications::notify($pdo, (int) $req['admin_user_id'], 'expense',
                    $approve ? 'Expense request approved: ' . ExpenseModel::money((float) $req['amount_given']) . ' given' : 'Expense request declined',
                    $approve ? 'Upload the receipt after buying.' : (string) ($req['decision_note'] ?? ''), 'admin/expenses/index.php');
            } catch (Throwable $t) {
            }
        }
        $r['ok'] ? acc_back('requests.php', $approve ? 'Approved. The admin will upload the receipt.' : 'Declined.') : acc_back('requests.php', null, $r['error']);
    }
    if (($_POST['action'] ?? '') === 'payroll') {
        $r = $prm->decide($id, $approve, (string) ($_POST['note'] ?? ''), $me);
        $ex->log($me, $approve ? 'approve' : 'decline', 'payroll_request', $id, ['applied' => $r['applied'] ?? null]);
        $r['ok'] ? acc_back('requests.php#tutors', $approve ? 'Approved and applied' . (!empty($r['applied']) ? ': balance changed by ' . ExpenseModel::money($r['applied']) : '') . '.' : 'Declined.')
                 : acc_back('requests.php#tutors', null, $r['error']);
    }
    acc_back('requests.php', null);
}

$reqs = $accInstalled ? $ex->requests() : [];
$tutorReqs = $accInstalled && $prm->isInstalled() ? $prm->list() : [];
$page_title = 'Accounting · Requests';
$sa_subtitle = 'Purchases the branches asked for, and corrections tutors asked for.';
ob_start();
echo acc_tabs('requests.php', acc_pending($pdo));
?>
<?php if (!$accInstalled): ?><div class="alert alert-warning">Run System Updates first (migration 084).</div><?php else: ?>
<div class="card mb-3"><div class="card-header fw-semibold">Branch purchase requests</div>
<?php if (!$reqs): ?><div class="card-body text-muted small">No requests yet.</div><?php endif; ?>
<div class="list-group list-group-flush">
<?php foreach ($reqs as $r): [$lbl, $cls] = ExpenseModel::STATUS[$r['status']]; ?>
    <div class="list-group-item py-3">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <div><strong><?php echo $e(ExpenseModel::BRANCHES[$r['branch']]); ?></strong> · <?php echo $e($r['email']); ?>
                <div class="small text-muted">Sent <?php echo $e(date('D j M Y, H:i', strtotime($r['created_at']))); ?> · needed <?php echo $e(date('D j M', strtotime($r['needed_on']))); ?> · at <?php echo $e($r['where_buy']); ?></div></div>
            <span class="badge bg-<?php echo $cls; ?> align-self-start"><?php echo $e($lbl); ?></span>
        </div>
        <div class="table-responsive"><table class="table table-sm mb-2">
            <tbody><?php foreach ($r['items'] as $it): ?><tr><td><?php echo $e($it['item']); ?></td><td class="text-end"><?php echo (int) $it['qty']; ?> × <?php echo $e(ExpenseModel::money((float) $it['price'])); ?></td><td class="text-end"><?php echo $e(ExpenseModel::money((float) $it['total'])); ?></td></tr><?php endforeach; ?></tbody>
            <tfoot><tr><th colspan="2">Estimated</th><th class="text-end"><?php echo $e(ExpenseModel::money((float) $r['estimated_total'])); ?></th></tr></tfoot>
        </table></div>
        <?php if ($r['note']): ?><div class="small mb-2">Note: <?php echo $e($r['note']); ?></div><?php endif; ?>
        <?php if ($r['status'] === 'pending'): ?>
            <form method="POST" class="d-flex flex-wrap gap-2 align-items-center"><?php echo csrf_field(); ?><input type="hidden" name="action" value="expense"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <div class="input-group input-group-sm" style="max-width:220px"><span class="input-group-text">Give KES</span><input name="amount_given" class="form-control" inputmode="decimal" value="<?php echo $e((float) $r['estimated_total']); ?>"></div>
                <input name="note" class="form-control form-control-sm" style="max-width:260px" placeholder="Note to the admin (optional)">
                <button name="decision" value="approve" class="btn btn-sm btn-success">Approve</button>
                <button name="decision" value="decline" class="btn btn-sm btn-outline-danger" onclick="return confirm('Decline this request?')">Decline</button>
            </form>
        <?php else: ?>
            <div class="small">
                <?php if ($r['amount_given'] !== null): ?>Given <strong><?php echo $e(ExpenseModel::money((float) $r['amount_given'])); ?></strong><?php endif; ?>
                <?php if ($r['spent_amount'] !== null): ?> · spent <strong><?php echo $e(ExpenseModel::money((float) $r['spent_amount'])); ?></strong>
                    <span class="text-muted">(<?php echo $r['spent_source'] === 'detected' ? 'read from the receipt' : 'typed by the admin'; ?><?php echo $r['detected_amount'] !== null && $r['spent_source'] === 'manual' ? '; receipt shows ' . $e(ExpenseModel::money((float) $r['detected_amount'])) : ''; ?>)</span>
                    · balance <strong class="<?php echo $r['balance'] < 0 ? 'text-danger' : ''; ?>"><?php echo $e(ExpenseModel::money((float) $r['balance'])); ?></strong><?php endif; ?>
                <?php if ($r['receipt_path']): ?> · <a href="<?php echo $e(url('admin/expenses/file.php?id=' . (int) $r['id'])); ?>" target="_blank">Receipt</a><?php endif; ?>
                <?php if ($r['decision_note']): ?><div class="text-muted">Your note: <?php echo $e($r['decision_note']); ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div></div>

<div class="card" id="tutors"><div class="card-header fw-semibold">Tutor share corrections</div>
<?php if (!$tutorReqs): ?><div class="card-body text-muted small">No requests from tutors.</div><?php endif; ?>
<div class="list-group list-group-flush">
<?php foreach ($tutorReqs as $q): ?>
    <div class="list-group-item py-3">
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <div><strong><?php echo $e($q['tutor_name']); ?></strong> · <?php echo $e(PayrollRequestModel::TYPES[$q['type']]); ?>
                <?php if ($q['student_name']): ?>: <?php echo $e($q['student_name']); ?><?php endif; ?>
                <?php if ($q['type'] === 'complete'): ?> on <?php echo $e(date('j M Y', strtotime($q['completed_on']))); ?><?php endif; ?>
                <?php if ($q['type'] === 'adjust'): ?> by <?php echo $e(ExpenseModel::money((float) $q['amount'])); ?><?php endif; ?>
                <div class="small text-muted">Asked <?php echo $e(date('j M Y, H:i', strtotime($q['created_at']))); ?> · "<?php echo $e($q['reason']); ?>"</div></div>
            <span class="badge bg-<?php echo ['pending' => 'warning', 'approved' => 'success', 'declined' => 'secondary', 'cancelled' => 'light text-dark'][$q['status']]; ?> align-self-start"><?php echo $e(ucfirst($q['status'])); ?></span>
        </div>
        <?php if ($q['status'] === 'pending'): $pv = $prm->preview($q); ?>
            <div class="small my-2"><?php
                if ($q['type'] === 'unassign') echo 'Approving takes the student off this tutor and removes ' . $e(ExpenseModel::money(-$pv)) . ' still unpaid for them. Months already paid stay paid.';
                elseif ($q['type'] === 'complete') echo 'Approving stops this student\'s shares after ' . $e(PayrollCalculator::monthLabel($q['completed_on'])) . '.';
                else echo 'Approving changes the tutor\'s balance by ' . $e(ExpenseModel::money($pv)) . ' this month.'; ?></div>
            <form method="POST" class="d-flex flex-wrap gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="payroll"><input type="hidden" name="id" value="<?php echo (int) $q['id']; ?>">
                <input name="note" class="form-control form-control-sm" style="max-width:300px" placeholder="Note to the tutor (optional)">
                <button name="decision" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Approve and apply this change?')">Approve</button>
                <button name="decision" value="decline" class="btn btn-sm btn-outline-danger">Decline</button>
            </form>
        <?php elseif ($q['status'] === 'approved' && (float) $q['applied_amount'] !== 0.0): ?>
            <div class="small text-muted">Balance changed by <?php echo $e(ExpenseModel::money((float) $q['applied_amount'])); ?>.</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
