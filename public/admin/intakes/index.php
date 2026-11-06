<?php
// Admin: Enrolment. The openings (intakes) with their dates, seats and override; close, reopen or delete them; and the settings for Book now:
// the booking fee, the payment details, the phone that gets payment messages, and "Hide the Enroll form when enrolment is closed".
// All times are East Africa Time. The stage of an opening (Upcoming, Bookings open, Enrolment open, Closed) is worked out by itself.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/EnrolmentService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$page_title = 'Enrolment';
$breadcrumbs = [['label' => 'Enrolment', 'active' => true]];
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if (!en_installed($db)) {
    $content = '<div class="alert alert-warning">Run <a href="' . $e(url('admin/system/updates.php')) . '">System Updates</a> first (migration 086: simple enrolment).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}
$feeReady = true;
try { $db->query('SELECT opens_at, override_mode FROM enrolment_openings LIMIT 0'); } catch (Throwable $x) { $feeReady = false; }
if (!$feeReady) {
    $content = '<div class="alert alert-warning">Run <a href="' . $e(url('admin/system/updates.php')) . '">System Updates</a> first (migration 101: Book now and the booking fee).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}
$self = url('admin/intakes/index.php');
$back = function (?string $ok, ?string $err = null, string $q = '') use ($self, $e) {
    if ($ok) SessionModel::setFlash('success', $e($ok));
    if ($err) SessionModel::setFlash('error', $e($err));
    header('Location: ' . $self . $q);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $back(null, 'Security check failed. Please refresh and try again.');
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'save':
            $r = en_open($db, $_POST, (int) $_SESSION['user_id']);
            $r['ok'] ? $back(!empty($r['updated']) ? 'Saved. Course pages changed at once.' : 'Opening created. Course pages changed at once.', null, '?id=' . $r['id']) : $back(null, $r['error'], $id ? '?id=' . $id : '?new=1');
        case 'close':
            en_set_status($db, $id, false);
            $back('Closed. Course pages show Book now only.');
        case 'reopen':
            en_set_status($db, $id, true);
            $back('Reopened.');
        case 'delete':
            en_delete_opening($db, $id);
            $back('The opening and its bookings were deleted.');
        case 'settings':
            $r = en_cfg_save($db, $_POST);
            $r['ok'] ? $back('Settings saved. The Book now form shows them at once.') : $back(null, $r['error']);
    }
    $back(null);
}

$courses = (new \Models\CourseModel($db))->getAll(true);
$openings = en_openings($db);
$cfg = en_cfg($db);
$hide = en_hide_form($db);
$byId = array_column($openings, null, 'id');
$selId = (int) ($_GET['id'] ?? 0);
$sel = $selId ? ($byId[$selId] ?? null) : null;
$isNew = !$sel;
$sug = isset($_GET['suggest']);   // suggested dates for January 2027, which the admin can edit
$dtv = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
$val = function (string $k) use ($sel, $sug) {
    if ($sel) return (string) ($sel[$k] ?? '');
    if ($sug) return ['title' => 'January 2027', 'bookings_open_at' => '2026-11-01 08:00:00', 'opens_at' => '2027-01-01 08:00:00', 'closes_at' => '2027-01-31 23:59:00', 'classes_start' => '2027-01-18'][$k] ?? '';   // early access starts 30 Dec 8:00 AM (48 hours before)
    return '';
};
$pill = ['upcoming' => 'bg-secondary', 'bookings' => 'bg-primary', 'early' => 'bg-warning text-dark', 'open' => 'bg-success', 'closed' => 'bg-danger'];   // Bootstrap 5.1
$openNow = array_filter($openings, fn($o) => $o['stage'] === 'open');
$payWait = (int) $db->query("SELECT COUNT(*) FROM enrolment_bookings WHERE status = 'payment_received'")->fetchColumn();
$seatsFor = function (int $oid) use ($db) {
    $st = $db->prepare('SELECT course_id, seats FROM enrolment_opening_seats WHERE opening_id = ?');
    $st->execute([$oid]);
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
};
$mine = $sel ? $seatsFor((int) $sel['id']) : [];
ob_start();
?>
<style>
.en-card{background:#fff;border:1px solid #e7e9e4;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.en-card .hd{padding:14px 18px;border-bottom:1px solid #eef0ec;font-weight:700}
.en-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#7b8078;font-weight:700}
.en-table td{vertical-align:middle;font-size:.86rem}.en-table tr.on td{background:#f3faef}
.en-banner{border-radius:10px;padding:10px 14px;font-weight:600;font-size:.9rem}
</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-0">Enrolment</h4><div class="text-muted small">Intakes for all courses or one course. Students book now, pay the booking fee, then enroll when enrolment opens. All times are East Africa Time (EAT).</div></div>
    <div class="d-flex gap-2">
        <a href="<?php echo $e(url('admin/intakes/bookings.php')); ?>" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>All bookings<?php echo $payWait ? ' <span class="badge bg-warning text-dark">' . $payWait . ' to confirm</span>' : ''; ?></a>
        <a href="<?php echo $e($self . '?new=1'); ?>" class="btn btn-success"><i class="fas fa-plus me-1"></i>New opening</a>
    </div>
</div>
<?php if ($openNow): ?>
    <div class="en-banner mb-3" style="background:#eaf6e4;color:#2f7a14"><i class="fas fa-lock-open me-1"></i>Right now enrolment is <strong>OPEN</strong> (<?php echo $e(implode(', ', array_column($openNow, 'title'))); ?>). Course pages show the Enroll form, and Book now for the next intake.</div>
<?php else: ?>
    <div class="en-banner mb-3" style="background:#fdf3dc;color:#8a5a00"><i class="fas fa-lock me-1"></i>Right now enrolment is <strong>CLOSED</strong>. <?php echo $hide ? 'Course pages hide the Enroll form and show Book now only.' : 'Course pages still show the Enroll form, because "Hide the Enroll form when enrolment is closed" is off.'; ?></div>
<?php endif; ?>
<div class="row g-3">
<div class="col-xl-7">
    <div class="en-card mb-3"><div class="table-responsive"><table class="table en-table mb-0">
        <thead><tr><th class="ps-3">Intake</th><th>Stage</th><th>Dates</th><th>Bookings</th><th></th></tr></thead><tbody>
        <?php if (!$openings): ?><tr><td colspan="5" class="text-center text-muted py-4">No openings yet. Create the first one, for example <a href="<?php echo $e($self . '?new=1&suggest=1'); ?>">January 2027 with suggested dates</a>.</td></tr><?php endif; ?>
        <?php foreach ($openings as $o): $on = $sel && (int) $sel['id'] === (int) $o['id']; ?>
            <tr class="<?php echo $on ? 'on' : ''; ?>">
                <td class="ps-3"><div class="fw-semibold"><?php echo $e($o['title']); ?></div><div class="small text-muted"><?php echo $o['course_title'] ? $e($o['course_title']) : 'All courses'; ?></div></td>
                <td><span class="badge rounded-pill <?php echo $pill[$o['stage']]; ?>"><?php echo $e(EN_STAGE_LABELS[$o['stage']]); ?></span>
                    <?php if ($o['override_mode'] !== 'auto'): ?><div class="small text-danger mt-1">Forced <?php echo $e($o['override_mode']); ?></div><?php endif; ?></td>
                <td class="small"><?php if ($o['bookings_open_at']): ?>Bookings open <?php echo $e(en_date($o['bookings_open_at'], 'j M Y')); ?><br><?php endif; ?>
                    <?php if ($o['opens_at']): ?>Early access <?php echo $e(en_date(en_early_start($o, en_early_hours($db)), 'j M Y, g:i A')); ?><br>Enrolment opens <?php echo $e(en_date($o['opens_at'], 'j M Y, g:i A')); ?><br><?php endif; ?>
                    <?php if ($o['closes_at']): ?>Closes <?php echo $e(en_date($o['closes_at'], 'j M Y')); ?><br><?php endif; ?>
                    <?php if ($o['classes_start']): ?>Classes start <?php echo $e(en_date($o['classes_start'], 'j M Y')); ?><?php endif; ?>
                    <?php if (!$o['bookings_open_at'] && !$o['opens_at'] && !$o['closes_at'] && !$o['classes_start']): ?><span class="text-muted">Older opening (no dates)</span><?php endif; ?></td>
                <td class="small"><?php echo (int) $o['confirmed']; ?> confirmed<?php echo $o['converted'] ? ' · ' . (int) $o['converted'] . ' enrolled' : ''; ?><?php echo $o['payment_waiting'] ? ' · <strong class="text-warning">' . (int) $o['payment_waiting'] . ' to confirm</strong>' : ''; ?><?php echo $o['waiting'] ? ' · ' . (int) $o['waiting'] . ' waiting' : ''; ?>
                    <?php if ($o['seats'] !== null): ?><div class="text-muted"><?php echo (int) $o['seats']; ?> seats per course</div><?php endif; ?></td>
                <td class="text-end pe-3 text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(url('admin/intakes/bookings.php?opening=' . (int) $o['id'])); ?>">Bookings</a>
                    <a class="btn btn-sm <?php echo $on ? 'btn-success' : 'btn-outline-secondary'; ?>" href="<?php echo $e($self . '?id=' . (int) $o['id']); ?>">Edit</a>
                    <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                        <?php if ($o['stage'] === 'closed'): ?><button name="action" value="reopen" class="btn btn-sm btn-outline-success">Reopen</button>
                        <?php else: ?><button name="action" value="close" class="btn btn-sm btn-outline-danger" onclick="return confirm('Close this opening now? Course pages will show Book now only.')">Close now</button><?php endif; ?></form>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this opening and all its bookings? This cannot be undone.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                </td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>

    <div class="en-card">
        <div class="hd"><i class="fas fa-coins me-1 text-success"></i>Book now settings</div>
        <form method="POST" class="p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="settings">
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="hf" name="hide_form" value="1" <?php echo $hide ? 'checked' : ''; ?>>
                <label class="form-check-label fw-semibold" for="hf">Hide the Enroll form when enrolment is closed</label>
                <div class="small text-muted">On by default. Closed courses show "Hello, our intake has closed..." and Book now only. Nobody can enroll through a direct link either. Staff can always register a student by hand. Off: the Apply form works as it does today.</div></div>
            <div class="row g-2">
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Booking fee (KES)</label><input name="booking_fee" class="form-control form-control-sm" required value="<?php echo $e((float) $cfg['booking_fee'] == (int) $cfg['booking_fee'] ? (int) $cfg['booking_fee'] : $cfg['booking_fee']); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Institution phone (payment messages)</label><input name="notify_phone" class="form-control form-control-sm" required value="<?php echo $e($cfg['notify_phone']); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Early access (hours before enrolment opens)</label><input type="number" min="1" max="720" name="early_hours" class="form-control form-control-sm" value="<?php echo (int) $cfg['early_hours']; ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Seat held for (days after the invite)</label><input type="number" min="1" max="60" name="hold_days" class="form-control form-control-sm" value="<?php echo (int) $cfg['hold_days']; ?>"></div>
                <div class="col-12 small text-muted">The hold must be longer than early access. A reminder goes 24 hours before the hold ends; if the person has not enrolled by then the booking expires and the seat is released (to the waiting list first). Each opening can also have its own early-access start.</div>
                <div class="col-12 small text-muted">Not refundable. Physical students: it is their registration fee. Online students: it is taken off their course fees. There is no automatic payment check yet: you confirm every payment by hand.</div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">M-Pesa</label><select name="mpesa_type" class="form-select form-select-sm"><option value="paybill" <?php echo $cfg['mpesa_type'] === 'paybill' ? 'selected' : ''; ?>>Paybill</option><option value="till" <?php echo $cfg['mpesa_type'] === 'till' ? 'selected' : ''; ?>>Till number</option></select></div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Number</label><input name="mpesa_number" maxlength="20" class="form-control form-control-sm" value="<?php echo $e($cfg['mpesa_number']); ?>"></div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Account name</label><input name="mpesa_account" maxlength="60" class="form-control form-control-sm" value="<?php echo $e($cfg['mpesa_account']); ?>"></div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Bank</label><input name="bank_name" maxlength="80" class="form-control form-control-sm" value="<?php echo $e($cfg['bank_name']); ?>"></div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Account name</label><input name="bank_account_name" maxlength="80" class="form-control form-control-sm" value="<?php echo $e($cfg['bank_account_name']); ?>"></div>
                <div class="col-sm-4"><label class="form-label small fw-semibold mb-1">Account number</label><input name="bank_account_number" maxlength="40" class="form-control form-control-sm" value="<?php echo $e($cfg['bank_account_number']); ?>"></div>
            </div>
            <button class="btn btn-success btn-sm mt-3">Save settings</button>
        </form>
    </div>
</div>

<div class="col-xl-5">
    <div class="en-card">
        <div class="hd"><?php echo $isNew ? '<i class="fas fa-plus me-1 text-success"></i>New opening' : '<i class="fas fa-pen me-1 text-success"></i>Edit ' . $e($sel['title']); ?></div>
        <form method="POST" class="p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo $isNew ? 0 : (int) $sel['id']; ?>">
            <div class="row g-2">
                <div class="col-12"><label class="form-label small fw-semibold mb-1">Name</label><input name="title" class="form-control form-control-sm" required maxlength="120" placeholder="January 2027" value="<?php echo $e($val('title')); ?>"></div>
                <div class="col-12"><label class="form-label small fw-semibold mb-1">Course</label>
                    <select name="course_id" class="form-select form-select-sm"><option value="">All courses</option><?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo $sel && (int) $sel['course_id'] === (int) $c['id'] ? 'selected' : ''; ?>><?php echo $e($c['title']); ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Bookings open</label><input type="datetime-local" name="bookings_open_at" class="form-control form-control-sm" value="<?php echo $e($dtv($val('bookings_open_at'))); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Early access starts <span class="text-muted fw-normal">(empty = <?php echo (int) $cfg['early_hours']; ?> hours before enrolment opens)</span></label><input type="datetime-local" name="early_access_at" class="form-control form-control-sm" value="<?php echo $e($dtv($val('early_access_at'))); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Enrolment opens</label><input type="datetime-local" name="opens_at" class="form-control form-control-sm" value="<?php echo $e($dtv($val('opens_at'))); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Enrolment closes <span class="text-muted fw-normal">(end of the intake)</span></label><input type="datetime-local" name="closes_at" class="form-control form-control-sm" value="<?php echo $e($dtv($val('closes_at'))); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Classes start</label><input type="date" name="classes_start" class="form-control form-control-sm" value="<?php echo $e($val('classes_start')); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Seats per course <span class="text-muted fw-normal">(empty = no limit)</span></label><input type="number" min="1" name="seats" class="form-control form-control-sm" value="<?php echo $e($val('seats')); ?>"></div>
                <div class="col-sm-6"><label class="form-label small fw-semibold mb-1">Override <span class="text-muted fw-normal">(emergencies)</span></label>
                    <select name="override_mode" class="form-select form-select-sm"><?php $ov = $sel['override_mode'] ?? 'auto'; foreach (['auto' => 'Automatic (by the dates)', 'open' => 'Force open', 'closed' => 'Force closed'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $ov === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><details <?php echo $mine ? 'open' : ''; ?>><summary class="small fw-semibold text-success" style="cursor:pointer">A different number of seats for one course</summary>
                    <div class="table-responsive mt-2" style="max-height:220px"><table class="table table-sm small mb-0"><thead><tr><th>Course</th><th style="width:110px">Seats</th></tr></thead><tbody>
                    <?php foreach ($courses as $c): $cid = (int) $c['id']; ?><tr><td><?php echo $e($c['title']); ?></td><td><input type="number" min="1" name="course_seats[<?php echo $cid; ?>]" class="form-control form-control-sm" value="<?php echo $e($mine[$cid] ?? ''); ?>" placeholder="same"></td></tr><?php endforeach; ?>
                    </tbody></table></div></details></div>
            </div>
            <div class="small text-muted mt-2">Stage: <strong>Upcoming</strong> before bookings open, <strong>Bookings open</strong> until early access starts, <strong>Early access</strong> (only people who booked and paid can enroll) until enrolment opens, <strong>Enrolment open</strong> until it closes, then <strong>Closed</strong>. Leave the opening and booking dates empty for an older-style opening that is simply open or closed.</div>
            <button class="btn btn-success btn-sm mt-3 w-100"><i class="fas fa-save me-1"></i><?php echo $isNew ? 'Create opening' : 'Save changes'; ?></button>
        </form>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
