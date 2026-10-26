<?php
// Admin / HR: one intern — placement, status, final evaluation switch, documents, attendance corrections,
// data export and deletion (Kenya Data Protection Act), and the activity log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$id = (int) ($_GET['id'] ?? 0);
$intern = $id ? in_get($db, $id) : null;
if (!$intern) {
    header('Location: ' . url('admin/interns/index.php'));
    exit;
}
$self = url('admin/interns/view.php?id=' . $id);

if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="intern_' . $id . '_data_' . date('Ymd') . '.json"');
    echo json_encode(in_export($db, $id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        switch ($_POST['act'] ?? '') {
            case 'save':
                $r = in_update($db, $id, $_POST);
                $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Intern saved.' : $r['error'];
                break;
            case 'doc':
                $r = in_document_add($db, $id, (string) ($_POST['category'] ?? ''), (string) ($_POST['title'] ?? ''), $_FILES['file'] ?? []);
                if ($r['ok']) in_notify_intern($db, $intern, 'intern_doc', 'New document: ' . (trim((string) $_POST['title']) ?: 'document'), 'Open Documents to read it.', 'intern/documents/index.php', false);
                $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Document uploaded.' : $r['error'];
                break;
            case 'doc_delete':
                in_document_delete($db, (int) $_POST['doc_id'], $id);
                $_SESSION['success_msg'] = 'Document deleted.';
                break;
            case 'attendance':
                $r = in_attendance_correct($db, $id, (string) ($_POST['work_date'] ?? ''), $_POST);
                $_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Attendance corrected.' : $r['error'];
                break;
            case 'delete':
                if (trim((string) ($_POST['confirm_name'] ?? '')) === trim($intern['full_name'])) {
                    in_delete($db, $id);
                    $_SESSION['success_msg'] = 'All of ' . $intern['full_name'] . '\'s data was deleted.';
                    header('Location: ' . url('admin/interns/index.php'));
                    exit;
                }
                $_SESSION['error_msg'] = 'Type the intern\'s full name exactly to delete their data.';
                break;
        }
    }
    header('Location: ' . $self);
    exit;
}

$departments = in_departments($db);
$supervisors = in_supervisors($db);
$docs = in_documents($db, $id);
$att = in_attendance_stats($db, $intern);
$period = in_period($intern);
$audit = [];
try {
    $st = $db->prepare("SELECT l.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS actor
                        FROM intern_audit_log l LEFT JOIN users u ON u.id = l.actor_id LEFT JOIN user_profiles p ON p.user_id = u.id
                        WHERE l.intern_id = ? ORDER BY l.id DESC LIMIT 40");
    $st->execute([$id]);
    $audit = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
$page_title = $intern['full_name'];
$breadcrumbs = [['label' => 'Interns', 'url' => url('admin/interns/index.php')], ['label' => $intern['full_name'], 'active' => true]];
$inTab = 'index';
$v = fn($k) => in_e($intern[$k] ?? '');
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div><h2 class="h4 mb-0"><?php echo $v('full_name'); ?> <span class="in-pill <?php echo $v('status'); ?> ms-1"><?php echo ucfirst($intern['status']); ?></span></h2>
        <div class="text-muted small"><?php echo $v('email'); ?><?php echo $period['week'] ? ' · Week ' . $period['week'] . ($period['weeks'] ? ' of ' . $period['weeks'] : '') : ''; ?></div></div>
    <div class="d-flex gap-2">
        <?php if ((int) $intern['supervisor_id'] === (int) $_SESSION['user_id']): ?><a class="btn btn-outline-success" href="<?php echo url('admin/interns/supervise.php?id=' . $id); ?>"><i class="fas fa-user-check me-1"></i>Supervise</a><?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?php echo $self; ?>&export=1"><i class="fas fa-file-export me-1"></i>Export data</a>
    </div>
</div>

<div class="row g-3">
<div class="col-lg-7">
    <form method="POST" class="in-card">
        <?php echo csrf_field(); ?><input type="hidden" name="act" value="save">
        <h5>Placement &amp; profile</h5>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Department</label><select name="department_id" class="form-select"><option value="">—</option>
                <?php foreach ($departments as $d): ?><option value="<?php echo (int) $d['id']; ?>" <?php echo (int) $intern['department_id'] === (int) $d['id'] ? 'selected' : ''; ?>><?php echo in_e($d['name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Supervisor</label><select name="supervisor_id" class="form-select"><option value="">—</option>
                <?php foreach ($supervisors as $s): ?><option value="<?php echo (int) $s['id']; ?>" <?php echo (int) $intern['supervisor_id'] === (int) $s['id'] ? 'selected' : ''; ?>><?php echo in_e($s['name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Start</label><input type="date" name="start_date" value="<?php echo $v('start_date'); ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">End</label><input type="date" name="end_date" value="<?php echo $v('end_date'); ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">
                <?php foreach (['active', 'completed', 'suspended', 'withdrawn'] as $s): ?><option value="<?php echo $s; ?>" <?php echo $intern['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" value="<?php echo $v('phone'); ?>" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">University / college</label><input name="institution" value="<?php echo $v('institution'); ?>" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Course</label><input name="course" value="<?php echo $v('course'); ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Year</label><input name="year_of_study" value="<?php echo $v('year_of_study'); ?>" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Student ID</label><input name="student_id_no" value="<?php echo $v('student_id_no'); ?>" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Skills acquired <span class="text-muted">(comma-separated)</span></label><input name="skills" value="<?php echo $v('skills'); ?>" class="form-control"></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" name="final_eval_open" value="1" class="form-check-input" <?php echo $intern['final_eval_open'] ? 'checked' : ''; ?>>
                <span class="form-check-label">Final evaluation is open (the supervisor can write it and the intern can see it once released)</span></label></div>
        </div>
        <div class="small text-muted mt-2">Suspended or withdrawn turns the sign-in off. Emergency contact: <?php echo $v('emergency_name') ?: '—'; ?> <?php echo $v('emergency_phone'); ?></div>
        <div class="text-end mt-2"><button class="btn btn-success">Save</button></div>
    </form>

    <div class="in-card">
        <h5>Documents</h5>
        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
            <?php echo csrf_field(); ?><input type="hidden" name="act" value="doc">
            <div class="col-md-3"><select name="category" class="form-select form-select-sm"><?php foreach (INTERN_DOC_CATEGORIES as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><input name="title" class="form-control form-control-sm" placeholder="Title (e.g. Internship agreement)"></div>
            <div class="col-md-3"><input type="file" name="file" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Upload</button></div>
        </form>
        <?php if (!$docs): ?><div class="text-muted small">No documents yet.</div><?php endif; ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($docs as $d): ?>
            <li class="list-group-item d-flex flex-wrap gap-2 align-items-center px-0">
                <span class="in-pill normal"><?php echo in_e(INTERN_DOC_CATEGORIES[$d['category']] ?? $d['category']); ?></span>
                <span class="flex-grow-1"><?php echo in_e($d['title']); ?> <span class="small text-muted">· <?php echo in_e($d['uploader']); ?> · <?php echo date('j M Y', strtotime($d['created_at'])); ?></span></span>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo in_e(in_file_url('doc', (int) $d['id'])); ?>"><i class="fas fa-eye"></i></a>
                <form method="POST" class="m-0" onsubmit="return confirm('Delete this document?')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="doc_delete"><input type="hidden" name="doc_id" value="<?php echo (int) $d['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="col-lg-5">
    <div class="in-card">
        <h5>Attendance</h5>
        <div class="row g-2 text-center mb-3">
            <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Rate</div><div class="fw-bold fs-5 text-success"><?php echo $att['rate']; ?>%</div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Late</div><div class="fw-bold fs-5 text-warning"><?php echo $att['late']; ?></div></div></div>
            <div class="col-4"><div class="border rounded p-2"><div class="small text-muted">Absent</div><div class="fw-bold fs-5 text-danger"><?php echo $att['absent']; ?></div></div></div>
        </div>
        <form method="POST" class="row g-2 align-items-end">
            <?php echo csrf_field(); ?><input type="hidden" name="act" value="attendance">
            <div class="col-6"><label class="form-label small mb-1">Date</label><input type="date" name="work_date" class="form-control form-control-sm" max="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="col-6"><label class="form-label small mb-1">Status</label><select name="status" class="form-select form-select-sm"><?php foreach (INTERN_ATT_STATUSES as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?></select></div>
            <div class="col-6"><label class="form-label small mb-1">Clock in</label><input type="time" name="clock_in" class="form-control form-control-sm"></div>
            <div class="col-6"><label class="form-label small mb-1">Clock out</label><input type="time" name="clock_out" class="form-control form-control-sm"></div>
            <div class="col-8"><input name="note" class="form-control form-control-sm" placeholder="Reason for the correction"></div>
            <div class="col-4"><label class="form-check small mb-0"><input type="checkbox" name="excused" value="1" class="form-check-input"> Excused</label></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-success">Save correction</button></div>
        </form>
    </div>

    <div class="in-card">
        <h5>Activity log</h5>
        <?php if (!$audit): ?><div class="text-muted small">Nothing yet.</div><?php endif; ?>
        <ul class="list-unstyled small mb-0" style="max-height:280px;overflow:auto">
            <?php foreach ($audit as $l): ?><li class="mb-1"><span class="text-muted"><?php echo date('j M, H:i', strtotime($l['created_at'])); ?></span> · <?php echo in_e($l['actor'] ?? 'System'); ?> — <?php echo in_e(str_replace('_', ' ', $l['action'])); ?><?php echo $l['detail'] ? ': ' . in_e($l['detail']) : ''; ?></li><?php endforeach; ?>
        </ul>
    </div>

    <form method="POST" class="in-card border border-danger" onsubmit="return confirm('Delete ALL of this intern\'s data and their sign-in? This cannot be undone.')">
        <?php echo csrf_field(); ?><input type="hidden" name="act" value="delete">
        <h5 class="text-danger">Delete intern data</h5>
        <p class="small text-muted">For when the internship has ended (Kenya Data Protection Act). Export first if you need a copy. Removes their account, tasks, attendance, reports, messages and files.</p>
        <input name="confirm_name" class="form-control form-control-sm mb-2" placeholder="Type “<?php echo $v('full_name'); ?>” to confirm">
        <button class="btn btn-sm btn-danger">Delete everything</button>
    </form>
</div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
