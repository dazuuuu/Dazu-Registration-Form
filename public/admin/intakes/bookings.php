<?php
// Admin: Intake Bookings. People who booked a slot: filters, convert to enrolment, message, notes, move, cancel, export,
// follow-up list, waiting list and a small report (per intake, conversion rate, by source).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/services/IntakeService.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/intakes/bookings.php');
$page_title = 'Intake Bookings';
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!ik_installed($db)) {
    $content = '<div class="alert alert-warning">Intake Booking is not installed yet. Run System Updates (migration 079).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

$intakes = ik_intakes($db, true);
$courses = (new \Models\CourseModel($db))->getAll(false);
$courseNames = array_column($courses, 'title', 'id');
$fIntake = (int) ($_GET['intake'] ?? 0);
if (!isset($_GET['intake'])) {   // default: the next intake people are booking for
    foreach ($intakes as $i) if (in_array($i['status'], ['booking_open', 'early_access', 'enrol_open'], true)) { $fIntake = (int) $i['id']; if ($i['status'] !== 'enrol_open') break; }
}
$fCourse = (int) ($_GET['course'] ?? 0);
$fStatus = in_array($_GET['status'] ?? '', array_keys(IK_STATUS_LABELS), true) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$tab = in_array($_GET['tab'] ?? '', ['all', 'followup', 'waiting', 'report'], true) ? $_GET['tab'] : 'all';
$qs = fn(array $over = []) => '?' . http_build_query(array_filter(array_merge(['intake' => $fIntake, 'course' => $fCourse, 'status' => $fStatus, 'q' => $q, 'tab' => $tab], $over), fn($v) => $v !== '' && $v !== 0 && $v !== null) + ['intake' => $over['intake'] ?? $fIntake]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $go = $self . $qs();
    if (!csrf_verify($_POST['csrf_token'] ?? null)) { $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.'; header('Location: ' . $go); exit; }
    $b = ik_booking($db, (int) ($_POST['id'] ?? 0));
    $act = (string) ($_POST['action'] ?? '');
    $res = ['ok' => false, 'error' => 'Booking not found.'];
    if ($b) {
        switch ($act) {
            case 'convert':
                $res = ik_admin_convert($db, $b);
                if ($res['ok']) $res['msg'] = $b['full_name'] . ' is now a student (' . $res['registration_no'] . '). The registration number email is on its way.';
                break;
            case 'contacted':
                if (in_array($b['status'], ['booked'], true)) $db->prepare("UPDATE intake_bookings SET status = 'contacted' WHERE id = ?")->execute([(int) $b['id']]);
                $db->prepare('UPDATE intake_bookings SET notes = ?, called_on = ? WHERE id = ?')->execute([mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000) ?: null,
                    preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['called_on'] ?? '')) ? $_POST['called_on'] : null, (int) $b['id']]);
                ik_log_booking($db, (int) $b['id'], ik_actor(), 'note', 'Called on ' . ($_POST['called_on'] ?? '—') . ': ' . mb_substr((string) ($_POST['notes'] ?? ''), 0, 300));
                $res = ['ok' => true, 'msg' => 'Note saved.'];
                break;
            case 'message':
                $subject = trim((string) ($_POST['subject'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));
                if ($subject === '' || $body === '') { $res = ['ok' => false, 'error' => 'Write a subject and a message.']; break; }
                ik_queue($db, $b['email'], $b['full_name'], $subject, ik_email_html('Hello ' . ik_e(ik_first($b['full_name'])) . ',', nl2br(ik_e($body)), [], [['Manage my booking', ik_manage_url($b)]]));
                if ($b['status'] === 'booked') $db->prepare("UPDATE intake_bookings SET status = 'contacted' WHERE id = ?")->execute([(int) $b['id']]);
                ik_log_booking($db, (int) $b['id'], ik_actor(), 'messaged', $subject);
                $res = ['ok' => true, 'msg' => 'Email sent to ' . $b['email'] . '.'];
                break;
            case 'move':
                $res = ik_move($db, $b, (int) ($_POST['intake_id'] ?? 0));
                if ($res['ok']) $res['msg'] = 'Booking moved. ' . $b['full_name'] . ' has been told by email.';
                break;
            case 'cancel':
                $res = ik_cancel($db, $b, 'Cancelled by the office' . (trim((string) ($_POST['reason'] ?? '')) !== '' ? ': ' . trim((string) $_POST['reason']) : ''));
                if ($res['ok']) $res['msg'] = 'Booking cancelled.';
                break;
        }
    } elseif ($act === 'waitlist_remove') {
        $db->prepare("UPDATE intake_waiting_list SET status = 'removed' WHERE id = ?")->execute([(int) ($_POST['wid'] ?? 0)]);
        $res = ['ok' => true, 'msg' => 'Removed from the waiting list.'];
    }
    $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? ($res['msg'] ?? 'Done.') : $res['error'];
    header('Location: ' . $go);
    exit;
}

// Filtered bookings.
$where = ['1=1'];
$args = [];
if ($fIntake) { $where[] = 'b.intake_id = ?'; $args[] = $fIntake; }
if ($fCourse) { $where[] = 'b.course_id = ?'; $args[] = $fCourse; }
if ($fStatus) { $where[] = 'b.status = ?'; $args[] = $fStatus; }
if ($tab === 'followup') $where[] = "b.status IN ('booked','contacted','invited','expired')";
if ($q !== '') { $where[] = '(b.full_name LIKE ? OR b.number LIKE ? OR b.email LIKE ? OR b.phone LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
$sql = 'SELECT b.*, c.title AS course_title, i.name AS intake_name, i.short_label, r.registration_no
        FROM intake_bookings b JOIN courses c ON c.id = b.course_id JOIN intakes i ON i.id = b.intake_id LEFT JOIN registrations r ON r.id = b.converted_registration_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($tab === 'followup' ? 'b.called_on IS NOT NULL, b.called_on, b.created_at' : 'b.created_at DESC') . ' LIMIT 500';
$st = $db->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="intake-bookings-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Booking number', 'Name', 'Email', 'Phone', 'Secondary phone', 'Course', 'Intake', 'Classes', 'Status', 'Booked (EAT)', 'Source', 'Called on', 'Notes', 'Registration no'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, [$r['number'], $r['full_name'], $r['email'], $r['phone'], $r['phone2'], $r['course_title'], $r['intake_name'], ik_time_label($r), IK_STATUS_LABELS[$r['status']],
            ik_fmt($db, ik_db_ts($db, $r['created_at']), 'Y-m-d H:i'), $r['source'], $r['called_on'], $r['notes'], $r['registration_no']], ',', '"', '');
    }
    exit;
}

// Number cards (for the chosen intake/course).
$cw = ['1=1']; $ca = [];
if ($fIntake) { $cw[] = 'intake_id = ?'; $ca[] = $fIntake; }
if ($fCourse) { $cw[] = 'course_id = ?'; $ca[] = $fCourse; }
$cnt = $db->prepare('SELECT status, COUNT(*) FROM intake_bookings WHERE ' . implode(' AND ', $cw) . ' GROUP BY status');
$cnt->execute($ca);
$by = $cnt->fetchAll(PDO::FETCH_KEY_PAIR);
$booked = array_sum(array_intersect_key($by, array_flip(IK_ACTIVE)));
// Seats left: for the chosen course, or the fullest course of the intake (seats are per course).
$seatsLeft = null; $seatsOf = null; $seatsCourse = null;
if ($fIntake && isset($intakes[$fIntake])) {
    $ci = $intakes[$fIntake];
    foreach ($fCourse ? [$fCourse] : array_map('intval', array_keys($courseNames)) as $cid) {
        if (!ik_applies($db, $ci, $cid)) continue;
        $s = ik_seats($db, $ci, $cid);
        if ($s['limit'] === null) continue;
        if ($seatsLeft === null || $s['left'] < $seatsLeft) { $seatsLeft = $s['left']; $seatsOf = $s['limit']; $seatsCourse = $courseNames[$cid] ?? ''; }
    }
}
$pill = ['booked' => 'bg-warning text-dark', 'contacted' => 'bg-info text-dark', 'invited' => 'bg-primary', 'converted' => 'bg-success', 'expired' => 'bg-secondary', 'cancelled' => 'bg-danger'];   // Bootstrap 5.1
$when = function (string $ts) use ($db) {
    $d = ik_fmt($db, ik_db_ts($db, $ts), 'Y-m-d');
    $t = ik_fmt($db, time(), 'Y-m-d');
    return $d === $t ? 'Today' : ($d === ik_fmt($db, time() - 86400, 'Y-m-d') ? 'Yesterday' : ik_fmt($db, ik_db_ts($db, $ts), 'j M'));
};
$bookable = fn(int $courseId) => ik_bookable_for($db, $courseId);

ob_start();
?>
<style>
.ik-card{background:#fff;border:1px solid #e7e9e4;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.03)}
.ik-num{padding:16px 18px}.ik-num .k{font-size:.8rem;color:#6b7068}.ik-num .v{font-size:1.7rem;font-weight:800;line-height:1.2}.ik-num .s{font-size:.75rem;color:#8a8f86}
.ik-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#7b8078;font-weight:700}
.ik-table td{vertical-align:middle;font-size:.86rem}
.ik-pills a{border:1px solid #d9dcd5;background:#fff;border-radius:999px;padding:5px 13px;font-size:.8rem;font-weight:600;color:#3b3f39;text-decoration:none;display:inline-block}
.ik-pills a.on{background:#44A024;border-color:#44A024;color:#fff}
.ik-tabs .nav-link{color:#5b6058;font-weight:600;font-size:.88rem}.ik-tabs .nav-link.active{color:#2f7a14;border-bottom:2px solid #44A024;background:none}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div><h1 class="h4 mb-0">Intake Bookings</h1><div class="text-muted small">People who booked a slot in an intake. Times in <?php echo $e(ik_tz_label($db)); ?>.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="<?php echo $e(url('admin/intakes/index.php')); ?>"><i class="fas fa-calendar-days me-1"></i>Intakes</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $e($self . $qs(['export' => 1])); ?>"><i class="fas fa-download me-1"></i>Export</a></div>
</div>

<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $k => $c): if (!empty($_SESSION[$k])): ?>
    <div class="alert alert-<?php echo $c; ?> py-2"><?php echo $e($_SESSION[$k]); unset($_SESSION[$k]); ?></div>
<?php endif; endforeach; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="ik-card ik-num"><div class="k">Booked</div><div class="v"><?php echo $booked; ?></div><div class="s"><?php echo $fIntake ? $e($intakes[$fIntake]['name'] ?? '') : 'all intakes'; ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="ik-card ik-num"><div class="k">Contacted</div><div class="v"><?php echo (int) ($by['contacted'] ?? 0); ?></div><div class="s">waiting for reply</div></div></div>
    <div class="col-6 col-lg-3"><div class="ik-card ik-num"><div class="k">Converted to students</div><div class="v text-success"><?php echo (int) ($by['converted'] ?? 0); ?></div><div class="s">enrolled</div></div></div>
    <div class="col-6 col-lg-3"><div class="ik-card ik-num"><div class="k">Seats left<?php echo $fIntake ? ' · ' . $e($intakes[$fIntake]['short_label'] ?: $intakes[$fIntake]['name']) : ''; ?></div>
        <div class="v" style="color:#b7791f"><?php echo $seatsLeft === null ? '—' : $seatsLeft; ?></div><div class="s text-truncate"><?php echo $seatsOf === null ? ($fIntake ? 'no seat limit' : 'choose an intake') : 'of ' . $seatsOf . ($fCourse ? '' : ' · fullest: ' . $e($seatsCourse)); ?></div></div></div>
</div>

<ul class="nav ik-tabs mb-3 border-bottom">
    <?php foreach (['all' => 'All bookings', 'followup' => 'Booked but not enrolled', 'waiting' => 'Waiting list', 'report' => 'Report'] as $k => $l): ?>
        <li class="nav-item"><a class="nav-link <?php echo $tab === $k ? 'active' : ''; ?>" href="<?php echo $e($self . $qs(['tab' => $k])); ?>"><?php echo $l; ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab !== 'report'): ?>
<form class="d-flex flex-wrap gap-2 align-items-center mb-3" method="GET">
    <input type="hidden" name="tab" value="<?php echo $e($tab); ?>">
    <div class="ik-pills d-flex flex-wrap gap-1">
        <a class="<?php echo !$fIntake ? 'on' : ''; ?>" href="<?php echo $e($self . $qs(['intake' => 0])); ?>">All intakes</a>
        <?php foreach ($intakes as $i): ?><a class="<?php echo $fIntake === (int) $i['id'] ? 'on' : ''; ?>" href="<?php echo $e($self . $qs(['intake' => (int) $i['id']])); ?>"><?php echo $e($i['name']); ?></a><?php endforeach; ?>
    </div>
    <input type="hidden" name="intake" value="<?php echo $fIntake; ?>">
    <select name="course" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()"><option value="">All courses</option>
        <?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo $fCourse === (int) $c['id'] ? 'selected' : ''; ?>><?php echo $e($c['title']); ?></option><?php endforeach; ?></select>
    <?php if ($tab === 'all'): ?><select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()"><option value="">Any status</option>
        <?php foreach (IK_STATUS_LABELS as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $fStatus === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="ms-auto d-flex gap-1"><input type="search" name="q" value="<?php echo $e($q); ?>" class="form-control form-control-sm" placeholder="Search name or booking number" style="min-width:220px"><button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button></div>
</form>
<?php endif; ?>

<?php if ($tab === 'all' || $tab === 'followup'): ?>
<div class="ik-card">
    <div class="table-responsive"><table class="table ik-table mb-0">
        <thead><tr><th class="ps-3">Name</th><th>Course</th><th>Classes</th><th>Intake</th><th>Booked</th><th>Status</th><?php if ($tab === 'followup'): ?><th>Called on</th><?php endif; ?><th class="text-end pe-3"></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?php echo $intakes ? 'No bookings match.' : 'No intakes yet. Create one on the Intakes page.'; ?></td></tr><?php endif; ?>
        <?php foreach ($rows as $r): $open = in_array($r['status'], ['booked', 'contacted', 'invited', 'expired'], true); ?>
            <tr>
                <td class="ps-3"><div class="fw-semibold"><?php echo $e($r['full_name']); ?></div><div class="small text-muted"><?php echo $e($r['number']); ?> · <?php echo $e($r['phone']); ?></div>
                    <?php if ($r['source']): ?><div class="small text-muted"><i class="fas fa-share-nodes me-1"></i><?php echo $e($r['source']); ?></div><?php endif; ?></td>
                <td><?php echo $e($r['course_title']); ?></td>
                <td class="small"><?php echo $e(ik_time_label($r)); ?></td>
                <td class="small"><?php echo $e($r['short_label'] ?: $r['intake_name']); ?></td>
                <td class="small text-muted"><?php echo $e($when($r['created_at'])); ?></td>
                <td><span class="badge rounded-pill <?php echo $pill[$r['status']]; ?>"><?php echo IK_STATUS_LABELS[$r['status']]; ?></span>
                    <?php if ($r['status'] === 'invited' && $r['hold_until']): ?><div class="small text-muted">held until <?php echo $e(ik_fmt($db, ik_ts($r['hold_until']), 'j M, g:i A')); ?></div><?php endif; ?></td>
                <?php if ($tab === 'followup'): ?><td class="small"><?php echo $r['called_on'] ? $e(date('j M', strtotime($r['called_on']))) : '<span class="text-danger">Not yet</span>'; ?></td><?php endif; ?>
                <td class="text-end pe-3 text-nowrap">
                    <?php if ($r['status'] === 'converted'): ?><span class="small text-muted">Student <?php echo $e($r['registration_no'] ?? ''); ?></span>
                    <?php elseif ($r['status'] === 'cancelled'): ?><span class="small text-muted">Cancelled</span>
                    <?php else: ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Create the student for <?php echo $e(addslashes($r['full_name'])); ?> and send the registration number email?')">
                            <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="convert">
                            <button class="btn btn-success btn-sm">Convert to enrolment</button></form>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#ikAct<?php echo (int) $r['id']; ?>">Message</button>
                    <?php endif; ?>
                    <?php if ($open || $r['status'] === 'converted'): ?>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#ikAct<?php echo (int) $r['id']; ?>" title="More"><i class="fas fa-ellipsis"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<p class="small text-muted mt-2"><i class="fas fa-shield-halved me-1"></i>Converting a booking creates the student record with the normal enrolment, sends the registration number, and marks the seat as taken.</p>

<?php foreach ($rows as $r): if ($r['status'] === 'cancelled') continue; $moves = array_filter($bookable((int) $r['course_id']), fn($i) => (int) $i['id'] !== (int) $r['intake_id']); ?>
<div class="modal fade" id="ikAct<?php echo (int) $r['id']; ?>" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><?php echo $e($r['full_name']); ?> <span class="small text-muted"><?php echo $e($r['number']); ?></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="small mb-3"><?php echo $e($r['email']); ?> · <?php echo $e($r['phone']); ?><?php echo $r['phone2'] ? ' / ' . $e($r['phone2']) : ''; ?><br><?php echo $e($r['course_title']); ?> · <?php echo $e($r['intake_name']); ?> · <?php echo $e(ik_time_label($r)); ?></div>
        <?php if ($r['status'] !== 'converted'): ?>
        <form method="POST" class="border rounded p-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="message">
            <div class="fw-semibold small mb-1"><i class="fas fa-envelope me-1 text-success"></i>Send an email</div>
            <input name="subject" class="form-control form-control-sm mb-1" required maxlength="150" value="About your booking <?php echo $e($r['number']); ?>">
            <textarea name="body" class="form-control form-control-sm mb-1" rows="3" required placeholder="Your message"></textarea>
            <button class="btn btn-success btn-sm">Send</button></form>
        <?php endif; ?>
        <form method="POST" class="border rounded p-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="contacted">
            <div class="fw-semibold small mb-1"><i class="fas fa-phone me-1 text-success"></i>Note and call</div>
            <input type="date" name="called_on" class="form-control form-control-sm mb-1" value="<?php echo $e($r['called_on'] ?: date('Y-m-d')); ?>">
            <textarea name="notes" class="form-control form-control-sm mb-1" rows="2" placeholder="What they said"><?php echo $e($r['notes']); ?></textarea>
            <button class="btn btn-outline-success btn-sm">Save note</button></form>
        <?php if (in_array($r['status'], ['booked', 'contacted', 'invited', 'expired'], true)): ?>
        <form method="POST" class="border rounded p-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="move">
            <div class="fw-semibold small mb-1"><i class="fas fa-right-left me-1 text-success"></i>Move to another intake</div>
            <?php if ($moves): ?><div class="d-flex gap-1"><select name="intake_id" class="form-select form-select-sm"><?php foreach ($moves as $m): ?><option value="<?php echo (int) $m['id']; ?>"><?php echo $e($m['name']); ?></option><?php endforeach; ?></select><button class="btn btn-outline-success btn-sm">Move</button></div>
            <?php else: ?><div class="small text-muted">No other intake is taking bookings right now.</div><?php endif; ?></form>
        <?php if ($r['status'] !== 'expired'): ?>
        <form method="POST" onsubmit="return confirm('Cancel this booking? The seat is released.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="action" value="cancel">
            <div class="d-flex gap-1"><input name="reason" class="form-control form-control-sm" placeholder="Reason (optional)"><button class="btn btn-outline-danger btn-sm text-nowrap">Cancel booking</button></div></form>
        <?php endif; endif; ?>
        <?php $lg = $db->prepare('SELECT * FROM intake_booking_log WHERE booking_id = ? ORDER BY id DESC LIMIT 15'); $lg->execute([(int) $r['id']]); $lg = $lg->fetchAll(PDO::FETCH_ASSOC); if ($lg): ?>
        <div class="small fw-semibold mt-3 mb-1">History</div>
        <ul class="small text-muted ps-3 mb-0"><?php foreach ($lg as $l): ?><li><?php echo $e(ik_fmt($db, ik_db_ts($db, $l['created_at']), 'j M, g:i A')); ?> · <strong><?php echo $e($l['action']); ?></strong> <?php echo $e($l['details']); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</div></div></div>
<?php endforeach; ?>

<?php elseif ($tab === 'waiting'):
    $ww = ["w.status = 'waiting'"]; $wa = [];
    if ($fIntake) { $ww[] = 'w.intake_id = ?'; $wa[] = $fIntake; }
    if ($fCourse) { $ww[] = 'w.course_id = ?'; $wa[] = $fCourse; }
    $wl = $db->prepare('SELECT w.*, c.title AS course_title, i.name AS intake_name FROM intake_waiting_list w JOIN courses c ON c.id = w.course_id JOIN intakes i ON i.id = w.intake_id WHERE ' . implode(' AND ', $ww) . ' ORDER BY w.id');
    $wl->execute($wa); $wl = $wl->fetchAll(PDO::FETCH_ASSOC); ?>
<div class="ik-card"><div class="table-responsive"><table class="table ik-table mb-0">
    <thead><tr><th class="ps-3">#</th><th>Name</th><th>Course</th><th>Intake</th><th>Joined</th><th></th></tr></thead><tbody>
    <?php if (!$wl): ?><tr><td colspan="6" class="text-center text-muted py-4">Nobody is waiting. When a course is full, new people join this list and get the next free seat automatically.</td></tr><?php endif; ?>
    <?php foreach ($wl as $n => $w): ?><tr><td class="ps-3"><?php echo $n + 1; ?></td><td><div class="fw-semibold"><?php echo $e($w['name']); ?></div><div class="small text-muted"><?php echo $e($w['email']); ?> · <?php echo $e($w['phone']); ?></div></td>
        <td><?php echo $e($w['course_title']); ?></td><td><?php echo $e($w['intake_name']); ?></td><td class="small text-muted"><?php echo $e($when($w['created_at'])); ?></td>
        <td class="text-end pe-3"><form method="POST" onsubmit="return confirm('Remove from the waiting list?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="waitlist_remove"><input type="hidden" name="wid" value="<?php echo (int) $w['id']; ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div></div>

<?php else:
    $per = $db->query("SELECT i.id, i.name, COUNT(b.id) AS total, SUM(b.status = 'converted') AS conv, SUM(b.status IN ('booked','contacted','invited')) AS open_n, SUM(b.status = 'expired') AS exp_n, SUM(b.status = 'cancelled') AS can_n
                       FROM intakes i LEFT JOIN intake_bookings b ON b.intake_id = i.id GROUP BY i.id ORDER BY i.enrol_opens_at")->fetchAll(PDO::FETCH_ASSOC);
    $src = $db->query("SELECT COALESCE(NULLIF(source, ''), 'Direct') AS src, COUNT(*) AS n, SUM(status = 'converted') AS conv FROM intake_bookings GROUP BY src ORDER BY n DESC")->fetchAll(PDO::FETCH_ASSOC);
    $interest = (int) $db->query('SELECT COUNT(*) FROM intake_interest_list WHERE notified_at IS NULL')->fetchColumn(); ?>
<div class="row g-3">
    <div class="col-lg-7"><div class="ik-card"><div class="p-3 fw-bold border-bottom">Bookings per intake</div><div class="table-responsive"><table class="table ik-table mb-0">
        <thead><tr><th class="ps-3">Intake</th><th>Bookings</th><th>Still open</th><th>Became students</th><th>Conversion</th><th>Expired / cancelled</th></tr></thead><tbody>
        <?php foreach ($per as $p): $rate = $p['total'] ? round($p['conv'] / $p['total'] * 100) : 0; ?>
            <tr><td class="ps-3 fw-semibold"><?php echo $e($p['name']); ?></td><td><?php echo (int) $p['total']; ?></td><td><?php echo (int) $p['open_n']; ?></td><td class="text-success fw-semibold"><?php echo (int) $p['conv']; ?></td>
                <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px;min-width:60px"><div class="progress-bar bg-success" style="width:<?php echo $rate; ?>%"></div></div><span class="small"><?php echo $rate; ?>%</span></div></td>
                <td class="small text-muted"><?php echo (int) $p['exp_n']; ?> / <?php echo (int) $p['can_n']; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$per): ?><tr><td colspan="6" class="text-center text-muted py-3">No intakes yet.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="col-lg-5"><div class="ik-card"><div class="p-3 fw-bold border-bottom">Bookings by source</div><div class="table-responsive"><table class="table ik-table mb-0">
        <thead><tr><th class="ps-3">Where they came from</th><th>Bookings</th><th>Students</th></tr></thead><tbody>
        <?php foreach ($src as $s): ?><tr><td class="ps-3"><?php echo $e($s['src']); ?></td><td><?php echo (int) $s['n']; ?></td><td><?php echo (int) $s['conv']; ?></td></tr><?php endforeach; ?>
        <?php if (!$src): ?><tr><td colspan="3" class="text-center text-muted py-3">No bookings yet.</td></tr><?php endif; ?></tbody></table></div>
        <div class="p-3 small text-muted border-top"><i class="fas fa-bell me-1"></i><?php echo $interest; ?> people asked to be told when the next intake opens.</div></div></div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
