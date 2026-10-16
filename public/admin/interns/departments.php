<?php
// Admin / HR: intern departments.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/interns/departments.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null;
        $id = (int) ($_POST['id'] ?? 0);
        try {
            if (($_POST['act'] ?? '') === 'toggle') {
                $db->prepare('UPDATE intern_departments SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
                $_SESSION['success_msg'] = 'Department updated.';
            } elseif ($name === '') {
                $_SESSION['error_msg'] = 'Enter a department name.';
            } elseif ($id) {
                $db->prepare('UPDATE intern_departments SET name = ?, description = ? WHERE id = ?')->execute([$name, $desc, $id]);
                $_SESSION['success_msg'] = 'Department saved.';
            } else {
                $db->prepare('INSERT INTO intern_departments (name, description) VALUES (?, ?)')->execute([$name, $desc]);
                $_SESSION['success_msg'] = 'Department added.';
            }
            in_audit($db, null, 'department_saved', $name);
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = (int) ($e->errorInfo[1] ?? 0) === 1062 ? 'A department with that name already exists.' : 'Could not save. Run System Updates (migration 075) first.';
        }
    }
    header('Location: ' . $self);
    exit;
}

$departments = in_departments($db);
$page_title = 'Intern departments';
$breadcrumbs = [['label' => 'Interns', 'url' => url('admin/interns/index.php')], ['label' => 'Departments', 'active' => true]];
$inTab = 'departments';
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" class="in-card">
            <?php echo csrf_field(); ?>
            <h5>Add a department</h5>
            <input name="name" class="form-control mb-2" placeholder="e.g. Cyber Security" required maxlength="120">
            <input name="description" class="form-control mb-2" placeholder="Short description (optional)" maxlength="255">
            <button class="btn btn-success">Add department</button>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="in-card p-0">
            <?php if (!$departments): ?><div class="text-center text-muted py-5">No departments yet.</div><?php endif; ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($departments as $d): ?>
                <li class="list-group-item p-3">
                    <form method="POST" class="row g-2 align-items-center">
                        <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
                        <div class="col-md-4"><input name="name" value="<?php echo in_e($d['name']); ?>" class="form-control form-control-sm" required></div>
                        <div class="col-md-4"><input name="description" value="<?php echo in_e($d['description']); ?>" class="form-control form-control-sm"></div>
                        <div class="col-md-4 d-flex gap-1 justify-content-end align-items-center">
                            <span class="small text-muted me-1"><?php echo (int) $d['interns']; ?> interns</span>
                            <button class="btn btn-sm btn-outline-success">Save</button>
                            <button name="act" value="toggle" class="btn btn-sm <?php echo $d['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success'; ?>"><?php echo $d['is_active'] ? 'Hide' : 'Show'; ?></button>
                        </div>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
