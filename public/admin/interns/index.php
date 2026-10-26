<?php
// Admin / HR: every intern, and "Add intern" (creates the sign-in account and emails the login details).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/interns/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $res = in_create($db, $_POST);
        if ($res['ok']) {
            $_SESSION['success_msg'] = 'Intern added. ' . ($res['emailed'] ? 'The login details were emailed.' : 'Email is not working — share this temporary password yourself: ' . $res['password']);
            header('Location: ' . url('admin/interns/view.php?id=' . $res['id']));
            exit;
        }
        $_SESSION['error_msg'] = 'Not added: ' . $res['error'];
    }
    header('Location: ' . $self);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['active', 'completed', 'suspended', 'withdrawn'], true) ? $_GET['status'] : '';
$interns = in_list($db, null, $q, $status);
$departments = in_departments($db, true);
$supervisors = in_supervisors($db);
$tableMissing = !$interns && !$departments && $db->query("SHOW TABLES LIKE 'interns'")->rowCount() === 0;
$page_title = 'Interns';
$breadcrumbs = [['label' => 'Interns', 'active' => true]];
$inTab = 'index';
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<?php if ($tableMissing): ?><div class="alert alert-warning">The intern tables are not installed yet. Run <a href="<?php echo url('admin/system/updates.php'); ?>">System Updates</a> (migration 075).</div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2 flex-wrap" method="GET">
        <input type="search" name="q" value="<?php echo in_e($q); ?>" class="form-control" placeholder="Search name, email, institution…" style="min-width:240px">
        <select name="status" class="form-select" style="width:auto" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['active', 'completed', 'suspended', 'withdrawn'] as $s): ?><option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
    </form>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#inNew"><i class="fas fa-user-plus me-1"></i>Add intern</button>
</div>

<div class="collapse <?php echo $interns ? '' : 'show'; ?>" id="inNew">
    <form method="POST" class="in-card">
        <?php echo csrf_field(); ?>
        <h5><i class="fas fa-user-plus text-success me-2"></i>New intern</h5>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Full name *</label><input name="full_name" class="form-control" required maxlength="150"></div>
            <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="30"></div>
            <div class="col-md-4"><label class="form-label">Department</label>
                <select name="department_id" class="form-select"><option value="">—</option>
                    <?php foreach ($departments as $d): ?><option value="<?php echo (int) $d['id']; ?>"><?php echo in_e($d['name']); ?></option><?php endforeach; ?></select>
                <?php if (!$departments): ?><div class="form-text"><a href="<?php echo url('admin/interns/departments.php'); ?>">Add departments first</a></div><?php endif; ?></div>
            <div class="col-md-4"><label class="form-label">Supervisor</label>
                <select name="supervisor_id" class="form-select"><option value="">—</option>
                    <?php foreach ($supervisors as $s): ?><option value="<?php echo (int) $s['id']; ?>"><?php echo in_e($s['name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">End date</label><input type="date" name="end_date" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">University / college</label><input name="institution" class="form-control" maxlength="150"></div>
            <div class="col-md-4"><label class="form-label">Course</label><input name="course" class="form-control" maxlength="150"></div>
            <div class="col-md-2"><label class="form-label">Year</label><input name="year_of_study" class="form-control" maxlength="20" placeholder="e.g. 3rd"></div>
            <div class="col-md-2"><label class="form-label">Student ID</label><input name="student_id_no" class="form-control" maxlength="50"></div>
        </div>
        <div class="text-end mt-3"><button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Create account &amp; email login</button></div>
    </form>
</div>

<div class="in-card p-0">
    <?php if (!$interns): ?><div class="text-center text-muted py-5">No interns<?php echo $q || $status ? ' match your search' : ' yet'; ?>.</div>
    <?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Intern</th><th>Department</th><th>Supervisor</th><th>Period</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($interns as $i): ?>
            <tr>
                <td class="ps-3"><div class="fw-semibold"><?php echo in_e($i['full_name']); ?></div><div class="small text-muted"><?php echo in_e($i['email']); ?><?php echo $i['institution'] ? ' · ' . in_e($i['institution']) : ''; ?></div></td>
                <td><?php echo in_e($i['department'] ?? '—'); ?></td>
                <td><?php echo in_e($i['supervisor_name'] ?? '—'); ?></td>
                <td class="small"><?php echo $i['start_date'] ? in_e(date('j M Y', strtotime($i['start_date']))) . ' → ' . ($i['end_date'] ? in_e(date('j M Y', strtotime($i['end_date']))) : '…') : '—'; ?></td>
                <td><span class="in-pill <?php echo in_e($i['status']); ?>"><?php echo ucfirst($i['status']); ?></span></td>
                <td class="text-end pe-3"><a class="btn btn-sm btn-outline-primary" href="<?php echo url('admin/interns/view.php?id=' . (int) $i['id']); ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
