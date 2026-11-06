<?php
// Admin: bookings (simple enrolment, Book now). Search and filter by opening, course, status and payment status. A booking with a payment code
// waits here for the office: check the payment by hand, then Confirm (the student gets a receipt) or Reject (with a reason: the booking is
// cancelled, the seat released and the student told why). A waiting person can be offered a slot. Cancel, delete, export CSV.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/EnrolmentService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
if (!en_installed($db)) {
    header('Location: ' . url('admin/intakes/index.php'));
    exit;
}
$e = 'en_e';
$f = ['opening' => (int) ($_GET['opening'] ?? 0), 'course' => (int) ($_GET['course'] ?? 0),
      'status' => in_array($_GET['status'] ?? '', array_keys(EN_STATUS_LABELS), true) ? $_GET['status'] : '',
      'pay' => in_array($_GET['pay'] ?? '', array_keys(EN_PAY_LABELS), true) ? $_GET['pay'] : '', 'q' => trim((string) ($_GET['q'] ?? ''))];
$self = url('admin/intakes/bookings.php') . '?' . http_build_query(array_filter($f));
$me = (int) ($_SESSION['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please refresh and try again.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $b = en_booking($db, $id);
        $a = $_POST['action'] ?? '';
        $res = null;
        if ($b && $a === 'pay_confirm') { $res = en_pay_confirm($db, $b, $me); if ($res['ok']) $res['msg'] = 'Payment confirmed. ' . $b['full_name'] . ' has been sent a receipt.'; }
        elseif ($b && $a === 'pay_reject') { $res = en_pay_reject($db, $b, $me, (string) ($_POST['reason'] ?? '')); if ($res['ok']) $res['msg'] = 'Payment rejected and the booking cancelled. ' . $b['full_name'] . ' has been told why.'; }
        elseif ($b && $a === 'offer') { en_offer($db, $b); $res = ['ok' => true, 'msg' => 'Slot offered. ' . $b['full_name'] . ' has been emailed and can pay the booking fee.']; }
        elseif ($b && $a === 'cancelled') { $res = en_cancel($db, $b, 'Cancelled by the office'); if ($res['ok']) $res['msg'] = 'Booking cancelled.'; }
        elseif ($a === 'delete') { en_delete_booking($db, $id); $res = ['ok' => true, 'msg' => 'Booking deleted.']; }
        if ($res) SessionModel::setFlash($res['ok'] ? 'success' : 'error', $e($res['ok'] ? $res['msg'] : $res['error']));
    }
    header('Location: ' . $self);
    exit;
}

$rows = en_bookings($db, $f);
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    $safe = fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v;
    fputcsv($out, ['Booking', 'Date', 'Name', 'Email', 'Phone', 'Course', 'Intake', 'Mode', 'Campus', 'Class time', 'Status', 'Payment', 'Paid by', 'Payment code', 'Amount', 'Source', 'Note'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [$r['reference'], $r['created_at'], $r['full_name'], $r['email'], $r['phone'], $r['course_title'], $r['opening_title'], $r['study_mode'], $r['campus'], $r['class_time'],
                                        EN_STATUS_LABELS[$r['status']] ?? $r['status'], EN_PAY_LABELS[$r['payment_status']] ?? $r['payment_status'], $r['pay_method'], $r['payment_reference'], $r['payment_amount'], $r['source'], $r['note']]), ',', '"', '');
    }
    exit;
}
$courses = (new \Models\CourseModel($db))->getAll(true);
$openings = en_openings($db);
$payWait = (int) $db->query("SELECT COUNT(*) FROM enrolment_bookings WHERE status = 'payment_received'")->fetchColumn();
$cls = ['confirmed' => 'success', 'payment_received' => 'warning text-dark', 'waiting' => 'secondary', 'converted' => 'primary', 'expired' => 'dark', 'cancelled' => 'danger'];
$payCls = ['unpaid' => 'secondary', 'paid' => 'success', 'waived' => 'info'];
$page_title = 'Bookings';
$breadcrumbs = [['label' => 'Enrolment', 'url' => url('admin/intakes/index.php')], ['label' => 'Bookings', 'active' => true]];
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-0">Bookings</h4><div class="text-muted small"><?php echo count($rows); ?> shown</div></div>
    <div class="d-flex gap-2"><a href="<?php echo $e(url('admin/intakes/index.php')); ?>" class="btn btn-outline-secondary btn-sm">← Enrolment</a>
        <a href="<?php echo $e($self . '&export=csv'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a></div>
</div>
<?php if ($payWait): ?><div class="alert alert-warning py-2 d-flex justify-content-between align-items-center"><span><i class="fas fa-bell me-1"></i><strong><?php echo $payWait; ?></strong> payment<?php echo $payWait === 1 ? '' : 's'; ?> waiting to be confirmed. Check each payment, then press Confirm or Reject.</span>
    <a class="btn btn-sm btn-warning" href="<?php echo $e(url('admin/intakes/bookings.php?status=payment_received')); ?>">Show them</a></div><?php endif; ?>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-2"><select name="opening" class="form-select form-select-sm"><option value="">All openings</option><?php foreach ($openings as $o): ?><option value="<?php echo (int) $o['id']; ?>" <?php echo $f['opening'] === (int) $o['id'] ? 'selected' : ''; ?>><?php echo $e($o['title']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="course" class="form-select form-select-sm"><option value="">All courses</option><?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo $f['course'] === (int) $c['id'] ? 'selected' : ''; ?>><?php echo $e($c['title']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Any status</option><?php foreach (EN_STATUS_LABELS as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $f['status'] === $k ? 'selected' : ''; ?>><?php echo $e($l); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="pay" class="form-select form-select-sm"><option value="">Any payment</option><?php foreach (EN_PAY_LABELS as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $f['pay'] === $k ? 'selected' : ''; ?>><?php echo $e($l); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input name="q" class="form-control form-control-sm" placeholder="Name, phone, booking or code" value="<?php echo $e($f['q']); ?>"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Booking</th><th>Student</th><th>Course</th><th>Studies</th><th>Status</th><th>Payment</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-muted p-4">No bookings match.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $st = $r['status']; ?>
        <tr>
            <td><strong><?php echo $e($r['reference']); ?></strong><div class="small text-muted"><?php echo $e(date('j M Y, H:i', strtotime($r['created_at']))); ?></div>
                <?php if ($r['source']): ?><div class="small text-muted"><i class="fas fa-share-nodes me-1"></i><?php echo $e($r['source']); ?></div><?php endif; ?></td>
            <td><?php echo $e($r['full_name']); ?><div class="small text-muted"><?php echo $e($r['email']); ?> · <?php echo $e($r['phone']); ?><?php echo $r['phone2'] ? ' / ' . $e($r['phone2']) : ''; ?></div>
                <?php if ($r['note']): ?><div class="small">“<?php echo $e($r['note']); ?>”</div><?php endif; ?></td>
            <td><?php echo $e($r['course_title']); ?><div class="small text-muted"><?php echo $e($r['opening_title']); ?></div></td>
            <td class="small"><?php echo $r['study_mode'] === 'physical' ? 'Physical · ' . $e(ucfirst((string) $r['campus'])) : 'Online'; ?><?php echo $r['class_time'] ? '<div class="text-muted">' . $e(EN_CLASS_TIMES[$r['study_mode']][$r['class_time']] ?? $r['class_time']) . '</div>' : ''; ?></td>
            <td><span class="badge bg-<?php echo $cls[$st] ?? 'secondary'; ?>"><?php echo $e(EN_STATUS_LABELS[$st] ?? $st); ?></span>
                <?php if ($st === 'waiting' && $r['offered_at']): ?><div class="small text-muted">slot offered</div><?php endif; ?>
                <?php if ($st === 'confirmed' && $r['invited_at']): ?><div class="small text-muted">invited <?php echo $e(date('j M', strtotime($r['invited_at']))); ?> · held until <?php echo $e(date('j M, g:i A', strtotime($r['hold_until']))); ?><?php echo $r['reminder_sent_at'] ? ' · reminded' : ''; ?></div><?php endif; ?>
                <?php if ($st === 'expired'): ?><div class="small text-muted">seat released <?php echo $e(date('j M, g:i A', strtotime($r['hold_until']))); ?></div><?php endif; ?>
                <?php if ($st === 'converted' && $r['converted_registration_id']): ?><div class="small text-muted">student #<?php echo (int) $r['converted_registration_id']; ?></div><?php endif; ?>
                <?php if ($st === 'cancelled' && $r['reject_reason']): ?><div class="small text-muted">Rejected: <?php echo $e($r['reject_reason']); ?></div><?php endif; ?></td>
            <td class="small"><?php if ($r['payment_reference']): ?><span class="badge bg-<?php echo $payCls[$r['payment_status']] ?? 'secondary'; ?>"><?php echo $e(EN_PAY_LABELS[$r['payment_status']] ?? $r['payment_status']); ?></span>
                <div class="mt-1"><span class="text-muted"><?php echo $e(strtoupper((string) $r['pay_method'])); ?></span> <code><?php echo $e($r['payment_reference']); ?></code></div>
                <div class="text-muted"><?php echo $e(en_money((float) $r['payment_amount'])); ?> · <?php echo $r['fee_kind'] === 'registration_fee' ? 'registration fee' : 'towards course fees'; ?></div>
                <?php else: ?><span class="text-muted"><?php echo $st === 'waiting' ? 'no payment yet' : '—'; ?></span><?php endif; ?></td>
            <td class="text-end text-nowrap">
                <?php if ($st === 'payment_received'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Confirm that you received <?php echo $e(en_money((float) $r['payment_amount'])); ?> (code <?php echo $e($r['payment_reference']); ?>)? The student gets a receipt.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button name="action" value="pay_confirm" class="btn btn-sm btn-success">Confirm</button></form>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rej<?php echo (int) $r['id']; ?>">Reject</button>
                <?php endif; ?>
                <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <?php if ($st === 'waiting' && !$r['offered_at']): ?><button name="action" value="offer" class="btn btn-sm btn-success">Offer a slot</button><?php endif; ?>
                    <?php if (!in_array($st, ['cancelled', 'payment_received', 'converted', 'expired'], true)): ?><button name="action" value="cancelled" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Cancel this booking? The seat is released.')">Cancel</button><?php endif; ?>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete booking <?php echo $e($r['reference']); ?>? This cannot be undone.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php foreach ($rows as $r): if ($r['status'] !== 'payment_received') continue; ?>
<div class="modal fade" id="rej<?php echo (int) $r['id']; ?>" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="pay_reject">
    <div class="modal-header"><h5 class="modal-title">Reject payment · <?php echo $e($r['reference']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><p class="small mb-2"><?php echo $e($r['full_name']); ?> paid by <strong><?php echo $e(strtoupper((string) $r['pay_method'])); ?></strong>, code <code><?php echo $e($r['payment_reference']); ?></code>, <?php echo $e(en_money((float) $r['payment_amount'])); ?>.
        The booking will be cancelled, the seat released, and the student told the reason and how to try again.</p>
        <label class="form-label small fw-semibold">Reason (the student will read this)</label>
        <textarea name="reason" class="form-control" rows="2" required maxlength="255" placeholder="e.g. We could not find this code in our M-Pesa statement"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button><button class="btn btn-danger">Reject and cancel booking</button></div>
</form></div></div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
