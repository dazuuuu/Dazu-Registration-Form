<?php
// Admin: slot bookings (simple enrolment). Search and filter, give a waiting booking a slot (the student is emailed),
// cancel, delete, and export CSV.
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
$f = ['opening' => (int) ($_GET['opening'] ?? 0), 'course' => (int) ($_GET['course'] ?? 0),
      'status' => in_array($_GET['status'] ?? '', ['confirmed', 'waiting', 'cancelled'], true) ? $_GET['status'] : '', 'q' => trim((string) ($_GET['q'] ?? ''))];
$self = url('admin/intakes/bookings.php') . '?' . http_build_query(array_filter($f));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please refresh and try again.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $a = $_POST['action'] ?? '';
        if ($a === 'delete') { en_delete_booking($db, $id); SessionModel::setFlash('success', 'Booking deleted.'); }
        elseif ($a === 'confirmed') { en_set_booking($db, $id, 'confirmed'); SessionModel::setFlash('success', 'Slot given. The student has been emailed.'); }
        elseif (in_array($a, ['waiting', 'cancelled'], true)) { en_set_booking($db, $id, $a); SessionModel::setFlash('success', 'Booking updated.'); }
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
    fputcsv($out, ['Booking', 'Date', 'Name', 'Email', 'Phone', 'Course', 'Intake', 'Mode', 'Campus', 'Status', 'Note'], ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [$r['reference'], $r['created_at'], $r['full_name'], $r['email'], $r['phone'], $r['course_title'], $r['opening_title'],
                                        $r['study_mode'], $r['campus'], $r['status'], $r['note']]), ',', '"', '');
    }
    exit;
}
$courses = (new \Models\CourseModel($db))->getAll(true);
$openings = en_openings($db);
$labels = ['confirmed' => ['Booked', 'success'], 'waiting' => ['Waiting for a slot', 'warning text-dark'], 'cancelled' => ['Cancelled', 'secondary']];
$page_title = 'Bookings';
$breadcrumbs = [['label' => 'Enrolment', 'url' => url('admin/intakes/index.php')], ['label' => 'Bookings', 'active' => true]];
ob_start();
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-0">Bookings</h4><div class="text-muted small"><?php echo count($rows); ?> shown</div></div>
    <div class="d-flex gap-2"><a href="<?php echo en_e(url('admin/intakes/index.php')); ?>" class="btn btn-outline-secondary btn-sm">← Enrolment</a>
        <a href="<?php echo en_e($self . '&export=csv'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a></div>
</div>
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-3"><select name="opening" class="form-select form-select-sm"><option value="">All enrolments</option><?php foreach ($openings as $o): ?><option value="<?php echo (int) $o['id']; ?>" <?php echo $f['opening'] === (int) $o['id'] ? 'selected' : ''; ?>><?php echo en_e($o['title'] . ' · ' . ($o['course_id'] ? $o['course_title'] : 'All courses')); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="course" class="form-select form-select-sm"><option value="">All courses</option><?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo $f['course'] === (int) $c['id'] ? 'selected' : ''; ?>><?php echo en_e($c['title']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Any status</option><?php foreach ($labels as $k => [$l]): ?><option value="<?php echo $k; ?>" <?php echo $f['status'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input name="q" class="form-control form-control-sm" placeholder="Name, email, phone or booking no." value="<?php echo en_e($f['q']); ?>"></div>
    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Booking</th><th>Student</th><th>Course</th><th>Studies</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-muted p-4">No bookings yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$lbl, $cls] = $labels[$r['status']]; ?>
        <tr>
            <td><strong><?php echo en_e($r['reference']); ?></strong><div class="small text-muted"><?php echo en_e(date('j M Y, H:i', strtotime($r['created_at']))); ?></div></td>
            <td><?php echo en_e($r['full_name']); ?><div class="small text-muted"><?php echo en_e($r['email']); ?> · <?php echo en_e($r['phone']); ?></div>
                <?php if ($r['note']): ?><div class="small">“<?php echo en_e($r['note']); ?>”</div><?php endif; ?></td>
            <td><?php echo en_e($r['course_title']); ?><div class="small text-muted"><?php echo en_e($r['opening_title']); ?></div></td>
            <td class="small"><?php echo $r['study_mode'] === 'physical' ? 'Physical · ' . en_e(ucfirst((string) $r['campus'])) : 'Online'; ?></td>
            <td><span class="badge bg-<?php echo $cls; ?>"><?php echo $lbl; ?></span></td>
            <td class="text-end text-nowrap">
                <form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <?php if ($r['status'] === 'waiting'): ?><button name="action" value="confirmed" class="btn btn-sm btn-success">Give a slot</button><?php endif; ?>
                    <?php if ($r['status'] !== 'cancelled'): ?><button name="action" value="cancelled" class="btn btn-sm btn-outline-secondary">Cancel</button>
                    <?php else: ?><button name="action" value="waiting" class="btn btn-sm btn-outline-secondary">Restore</button><?php endif; ?>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete booking <?php echo en_e($r['reference']); ?>? This cannot be undone.')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                    <button name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
