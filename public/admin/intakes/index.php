<?php
// Admin: Enrolment. Open enrolment for one course or for all courses (optional slots, start date, closing date),
// close or reopen it, or delete it. Only courses with open enrolment show a badge and a "Book a slot" button.
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

if (!en_installed($db)) {
    $content = '<div class="alert alert-warning">Run <a href="' . en_e(url('admin/system/updates.php')) . '">System Updates</a> first (migration 086: simple enrolment).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}
$back = function (?string $ok, ?string $err = null) {
    if ($ok) SessionModel::setFlash('success', en_e($ok));
    if ($err) SessionModel::setFlash('error', en_e($err));
    header('Location: ' . url('admin/intakes/index.php'));
    exit;
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $back(null, 'Security check failed. Please refresh and try again.');
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'open':
            $r = en_open($db, $_POST, (int) $_SESSION['user_id']);
            $r['ok'] ? $back(!empty($r['updated']) ? 'Enrolment was already open for that choice: its details were updated.' : 'Enrolment is open. Students can book a slot now.') : $back(null, $r['error']);
        case 'close':
            en_set_status($db, $id, false);
            $back('Enrolment closed. That course looks normal again (no badge).');
        case 'reopen':
            en_set_status($db, $id, true);
            $back('Enrolment reopened.');
        case 'delete':
            en_delete_opening($db, $id);
            $back('Enrolment and its bookings were deleted.');
    }
    $back(null);
}

$courses = (new \Models\CourseModel($db))->getAll(true);
$openings = en_openings($db);
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-0">Enrolment</h4><div class="text-muted small">Open enrolment for a course (or all courses). Students then see "Enrolment open" and can book a slot.</div></div>
    <a href="<?php echo en_e(url('admin/intakes/bookings.php')); ?>" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>All bookings</a>
</div>
<div class="row g-3">
<div class="col-lg-4">
    <div class="card"><div class="card-header fw-semibold">Open enrolment</div><div class="card-body">
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="open">
            <label class="form-label small fw-semibold">Course</label>
            <select name="course_id" class="form-select form-select-sm mb-2"><option value="0">All courses</option>
                <?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo en_e($c['title']); ?></option><?php endforeach; ?></select>
            <label class="form-label small fw-semibold">Name</label>
            <input name="title" class="form-control form-control-sm mb-2" maxlength="120" placeholder="e.g. January 2027 intake" value="<?php echo en_e('Intake ' . date('F Y', strtotime('first day of next month'))); ?>">
            <label class="form-label small fw-semibold">Slots per course <span class="text-muted fw-normal">(optional)</span></label>
            <input name="seats" type="number" min="1" class="form-control form-control-sm mb-1" placeholder="No limit">
            <div class="small text-muted mb-2">When the slots are full, students can still book. Those bookings wait for you to allocate them.</div>
            <div class="row g-2 mb-3">
                <div class="col-6"><label class="form-label small fw-semibold">Classes start <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="classes_start" class="form-control form-control-sm"></div>
                <div class="col-6"><label class="form-label small fw-semibold">Close bookings on <span class="text-muted fw-normal">(optional)</span></label><input type="date" name="closes_at" class="form-control form-control-sm" min="<?php echo date('Y-m-d'); ?>"></div>
            </div>
            <button class="btn btn-success w-100"><i class="fas fa-door-open me-1"></i>Open enrolment</button>
        </form>
    </div></div>
</div>
<div class="col-lg-8">
    <div class="card"><div class="card-header fw-semibold">Enrolments</div>
    <?php if (!$openings): ?><div class="card-body text-muted">Nothing opened yet. Every course looks normal, with no enrolment badge.</div><?php endif; ?>
    <div class="list-group list-group-flush">
    <?php foreach ($openings as $o): $isOpen = $o['status'] === 'open'; ?>
        <div class="list-group-item py-3">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <span class="badge <?php echo $isOpen ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $isOpen ? 'Open' : 'Closed'; ?></span>
                    <strong class="ms-1"><?php echo en_e($o['title']); ?></strong>
                    <div class="small mt-1"><?php echo en_e($o['course_id'] ? $o['course_title'] : 'All courses'); ?>
                        · <?php echo $o['seats'] !== null ? (int) $o['seats'] . ' slots per course' : 'no slot limit'; ?>
                        <?php echo $o['classes_start'] ? ' · classes start ' . en_e(en_date($o['classes_start'])) : ''; ?>
                        <?php echo $o['closes_at'] ? ' · ' . ($isOpen ? 'closes ' : 'closed ') . en_e(en_date($o['closes_at'])) : ''; ?></div>
                    <div class="small text-muted"><a href="<?php echo en_e(url('admin/intakes/bookings.php?opening=' . (int) $o['id'])); ?>"><?php echo (int) $o['confirmed']; ?> booked</a>
                        <?php echo $o['waiting'] ? ' · <strong class="text-warning">' . (int) $o['waiting'] . ' waiting for a slot</strong>' : ''; ?></div>
                </div>
                <div class="d-flex gap-2 align-items-start">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                        <button name="action" value="<?php echo $isOpen ? 'close' : 'reopen'; ?>" class="btn btn-sm <?php echo $isOpen ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><?php echo $isOpen ? 'Close' : 'Reopen'; ?></button></form>
                    <form method="POST" onsubmit="return confirm('Delete this enrolment and its <?php echo (int) $o['confirmed'] + (int) $o['waiting']; ?> booking(s)? This cannot be undone.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $o['id']; ?>">
                        <button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div></div>
    <div class="small text-muted mt-2">A course's own enrolment is used before an "All courses" one. Closing ends booking at once; courses go back to normal.</div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
