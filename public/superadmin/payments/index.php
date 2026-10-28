<?php
// public/superadmin/payments/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\PaymentModel;
use Controllers\StudentController;

Middleware::requireSuperPortal();

$pdo          = Connection::getInstance()->getConnection();
$paymentModel = new PaymentModel($pdo);
$pending      = $paymentModel->listPending();
$error        = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $action    = $_POST['action'] ?? '';
        if ($action === 'record_payment') {
            require_once __DIR__ . '/../../../app/v1/helpers/record_payment.php';
            $rid = (int) ($_POST['payment_reg_id'] ?? 0);
            $ok = $pdo->prepare("SELECT 1 FROM registrations WHERE id = ? AND status = 'approved'");
            $ok->execute([$rid]);
            [$type, $msg] = $ok->fetchColumn()
                ? sa_record_payment($pdo, $rid, (float) ($_POST['amount'] ?? 0), (string) ($_POST['reference'] ?? ''), $_POST['notes'] ?? null, (int) SessionModel::get('user_id'))
                : ['error', 'Choose a student.'];
            SessionModel::setFlash($type, $msg);
            header('Location: ' . url('superadmin/payments/index.php'));
            exit;
        }
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $adminId   = (int) SessionModel::get('user_id');
        $controller = new StudentController();

        if ($action === 'approve') {
            $result = $controller->approvePayment($paymentId, $adminId);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Payment approved. Receipt <strong>' . htmlspecialchars($result['receipt_no']) . '</strong> generated and emailed to the student. Portal unlocked.');
                header('Location: ' . url('superadmin/payments/index.php'));
                exit();
            }
            $error = $result['error'];
        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            $result = $controller->rejectPayment($paymentId, $adminId, $reason);
            if ($result['ok']) {
                SessionModel::setFlash('success', 'Payment rejected.');
                header('Location: ' . url('superadmin/payments/index.php'));
                exit();
            }
            $error = $result['error'];
        }
        $pending = $paymentModel->listPending();
    }
}

// Every payment, newest first, for the "All payments" sheet.
$all = [];
foreach (['p.mpesa_code', "'' AS mpesa_code"] as $mpesaCol) {   // older databases have no mpesa_code column
    try {
        $all = $pdo->query(
            "SELECT p.id, p.amount, p.status, p.paid_at, p.reviewed_at, p.payer_reference, $mpesaCol,
                    r.registration_no, r.full_name, c.title AS course_title
             FROM payments p LEFT JOIN registrations r ON r.id = p.registration_id LEFT JOIN courses c ON c.id = r.course_id
             ORDER BY COALESCE(p.reviewed_at, p.paid_at) DESC, p.id DESC LIMIT 500"
        )->fetchAll();
        break;
    } catch (Throwable $e) {
        $all = [];
    }
}
$approvedTotal = 0.0; $pendingTotal = 0.0;
foreach ($all as $p) {
    if ($p['status'] === 'approved') $approvedTotal += (float)$p['amount'];
    if ($p['status'] === 'pending') $pendingTotal += (float)$p['amount'];
}
$num = fn($n): string => number_format((float)$n, abs((float)$n - round((float)$n)) < 0.005 ? 0 : 2);
$statusClass = ['approved' => 'text-success', 'pending' => 'xl-pending', 'rejected' => 'text-danger'];
$col = fn(int $i): string => chr(64 + $i);

$page_title  = 'Payments';
$sa_subtitle = 'Verify submitted payments and see every payment on record.';
$breadcrumbs = [['label' => 'Payments', 'active' => true]];
$sa_actions = '<button type="button" class="btn sa-btn-primary" data-bs-toggle="modal" data-bs-target="#saRecordPay"><i class="fas fa-plus me-1"></i>Record payment</button>';
$recStudents = $pdo->query("SELECT r.id, r.full_name, r.registration_no, c.title AS course_title, r.portal_locked FROM registrations r LEFT JOIN courses c ON c.id = r.course_id
                            WHERE r.status = 'approved' ORDER BY r.full_name")->fetchAll(PDO::FETCH_ASSOC);
$extra_css = '<link rel="stylesheet" href="' . sa_asset('css/sheet.css') . '">';
$extra_js = '<script src="' . sa_asset('js/sheet.js') . '"></script>';

ob_start();
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="xl-toolbar">
    <label class="xl-search"><i class="fas fa-magnifying-glass"></i><input type="search" data-sheet-search placeholder="Search student, course, M-Pesa code or status…" autocomplete="off"></label>
    <span class="xl-count" data-sheet-count></span>
</div>

<div class="xl-shell">
    <div class="xl-formula"><span class="xl-ref" id="xlRef">A1</span><span class="xl-fx">fx</span><span class="xl-val" id="xlVal">Click a cell to read it in full.</span></div>

    <div class="xl-sheet active" id="sheet-pending">
    <div class="xl-scroll">
    <table class="xl">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 7; $i++) echo '<th>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head"><th class="rn"></th><th>Submitted</th><th>Student</th><th>Course</th><th class="num">Amount (KES)</th><th>M-Pesa code</th><th>Payer details</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php if (empty($pending)): ?>
            <tr data-norow="1"><th class="rn">1</th><td colspan="7" class="text-muted">No payments awaiting verification.</td></tr>
        <?php endif; ?>
        <?php foreach ($pending as $n => $p): ?>
            <tr>
                <th class="rn"><?php echo $n + 1; ?></th>
                <td><?php echo date('d M Y H:i', strtotime($p['paid_at'])); ?></td>
                <td class="xl-wrap"><strong><?php echo htmlspecialchars($p['registration_no'] ?? '—'); ?></strong> <?php echo htmlspecialchars($p['full_name']); ?></td>
                <td class="xl-wrap"><?php echo htmlspecialchars($p['course_title']); ?></td>
                <td class="num fw-bold"><?php echo $num($p['amount']); ?></td>
                <td><code><?php echo htmlspecialchars($p['mpesa_code'] ?? ''); ?></code></td>
                <td class="xl-wrap"><?php echo htmlspecialchars($p['payer_reference']); ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Approve this payment? The student portal will be unlocked.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo (int) $p['id']; ?>"><i class="fas fa-times"></i> Reject</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($pending): ?><tfoot><tr><th class="rn"></th><td colspan="3">Total pending</td><td class="num"><?php echo $num($pendingTotal); ?></td><td colspan="3"><?php echo count($pending); ?> awaiting</td></tr></tfoot><?php endif; ?>
    </table>
    </div>
    </div>

    <div class="xl-sheet" id="sheet-all">
    <div class="xl-scroll">
    <table class="xl">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 7; $i++) echo '<th>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head"><th class="rn"></th><th>Date</th><th>Student</th><th>Course</th><th class="num">Amount (KES)</th><th>M-Pesa code</th><th>Reference</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (empty($all)): ?>
            <tr data-norow="1"><th class="rn">1</th><td colspan="7" class="text-muted">No payments recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($all as $n => $p): $when = $p['reviewed_at'] ?: $p['paid_at']; ?>
            <tr>
                <th class="rn"><?php echo $n + 1; ?></th>
                <td><?php echo $when ? date('d M Y', strtotime($when)) : '—'; ?></td>
                <td class="xl-wrap"><strong><?php echo htmlspecialchars((string)($p['registration_no'] ?? '')); ?></strong> <?php echo htmlspecialchars((string)($p['full_name'] ?? 'Unknown')); ?></td>
                <td class="xl-wrap"><?php echo htmlspecialchars((string)$p['course_title']); ?></td>
                <td class="num"><?php echo $num($p['amount']); ?></td>
                <td><code><?php echo htmlspecialchars((string)($p['mpesa_code'] ?? '')); ?></code></td>
                <td class="xl-wrap"><?php echo htmlspecialchars((string)$p['payer_reference']); ?></td>
                <td class="fw-semibold <?php echo $statusClass[$p['status']] ?? ''; ?>"><?php echo ucfirst((string)$p['status']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($all): ?><tfoot><tr><th class="rn"></th><td colspan="3">Approved total</td><td class="num"><?php echo $num($approvedTotal); ?></td><td colspan="3"><?php echo count($all); ?> payments</td></tr></tfoot><?php endif; ?>
    </table>
    </div>
    </div>

    <div class="xl-tabs">
        <button type="button" class="xl-tab active" data-sheet="pending">Pending (<?php echo count($pending); ?>)</button>
        <button type="button" class="xl-tab" data-sheet="all">All payments (<?php echo count($all); ?>)</button>
    </div>
</div>

<?php foreach ($pending as $p): ?>
<div class="modal fade" id="rejectModal<?php echo (int) $p['id']; ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>">
            <div class="modal-header"><h5 class="modal-title">Reject payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted mb-2"><?php echo htmlspecialchars($p['full_name']); ?> · KES <?php echo $num($p['amount']); ?></p>
                <label class="form-label">Reason for rejection</label>
                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="e.g. M-Pesa code could not be verified on the statement."></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject payment</button></div>
        </form>
    </div></div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="saRecordPay" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="record_payment">
    <div class="modal-header"><h5 class="modal-title">Record a payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label">Student and course</label>
        <input type="search" class="form-control mb-2" placeholder="Type a name or registration no." oninput="(function(q,s){q=q.toLowerCase();[...s.options].forEach(o=>{o.hidden=o.value&&!o.text.toLowerCase().includes(q)});})(this.value,document.getElementById('saRecReg'))">
        <select name="payment_reg_id" id="saRecReg" class="form-select mb-2" required>
            <option value="">Choose…</option>
            <?php foreach ($recStudents as $r): ?><option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['full_name'] . ' · ' . $r['registration_no'] . ' · ' . ($r['course_title'] ?? '') . (!empty($r['portal_locked']) ? ' (locked)' : '')); ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Amount (KES)</label>
        <input type="number" name="amount" class="form-control mb-2" min="1" step="1" inputmode="numeric" required>
        <label class="form-label">Reference (M-Pesa code, receipt no.)</label>
        <input type="text" name="reference" class="form-control mb-2" maxlength="100">
        <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
        <input type="text" name="notes" class="form-control" maxlength="255">
        <div class="form-text">Approved straight away: the receipt is emailed and the student's portal is unlocked. To lock or unlock a portal by hand, open the student (Students → name).</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Record payment</button></div>
</form></div></div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
