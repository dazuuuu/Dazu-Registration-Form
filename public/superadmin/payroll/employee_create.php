<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/EmployeeNotifier.php';

use Models\Connection;
use Models\EmployeeModel;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$employees = new EmployeeModel($pdo);
if (!$employees->isInstalled()) {
    SessionModel::setFlash('error', 'Employees have not been set up yet. Apply the pending update (migration 048) first.');
    header('Location: index.php');
    exit();
}

$error = null;
$old = ['pay_day' => 28, 'start_date' => date('Y-m-d')];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } else {
        $r = $employees->create($_POST);
        if ($r['ok']) {
            $emp = $employees->getById($r['employee_id']);
            $msg = 'Employee created.';
            if (isset($_POST['send_email'])) {
                $mail = (new EmployeeNotifier())->sendCredentials($emp, $r['temp_password']);
                if ($mail['ok']) {
                    $employees->markCredentialsSent($r['employee_id']);
                    $msg .= ' Sign-in details were emailed.';
                } else {
                    $msg .= ' The email could not be sent (' . htmlspecialchars($mail['message']) . '). Give them this temporary password yourself: <strong>' . htmlspecialchars($r['temp_password']) . '</strong>';
                }
            } else {
                $msg .= ' Temporary password (shown once): <strong>' . htmlspecialchars($r['temp_password']) . '</strong>';
            }
            SessionModel::setFlash('success', $msg);
            header('Location: employee.php?id=' . (int)$r['employee_id']);
            exit();
        }
        $error = $r['error'];
    }
}
$v = fn(string $k) => htmlspecialchars((string)($old[$k] ?? ''), ENT_QUOTES);

$page_title = 'Add Employee';
$breadcrumbs = [['label' => 'Salaries', 'url' => 'index.php#employees'], ['label' => 'Add employee', 'active' => true]];
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="POST"><?php echo csrf_field(); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Full name *</label><input name="full_name" class="form-control" value="<?php echo $v('full_name'); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email * <span class="text-muted small">(they sign in with this)</span></label><input type="email" name="email" class="form-control" value="<?php echo $v('email'); ?>" required></div>
    <div class="col-md-6"><label class="form-label">Role *</label><input name="job_title" class="form-control" list="roles" maxlength="100" placeholder="e.g. Accountant" value="<?php echo $v('job_title'); ?>" required>
        <datalist id="roles"><option>Accountant</option><option>Receptionist</option><option>Cleaner</option><option>Security guard</option><option>Driver</option><option>Administrator</option><option>IT support</option><option>Marketing</option></datalist></div>
    <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="30" value="<?php echo $v('phone'); ?>"></div>
    <div class="col-md-4"><label class="form-label">Monthly salary (KES) *</label><input type="number" name="monthly_salary" class="form-control" min="0" step="0.01" value="<?php echo $v('monthly_salary'); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Paid on day of month *</label><input type="number" name="pay_day" class="form-control" min="1" max="31" value="<?php echo $v('pay_day'); ?>" required>
        <div class="form-text">Day 31 means the last day of shorter months.</div></div>
    <div class="col-md-4"><label class="form-label">Start date *</label><input type="date" name="start_date" class="form-control" value="<?php echo $v('start_date'); ?>" required>
        <div class="form-text">Salary is earned on each pay day from this date.</div></div>
    <div class="col-12"><div class="form-check"><input type="checkbox" name="send_email" value="1" class="form-check-input" id="se" checked><label for="se" class="form-check-label">Email their sign-in details</label></div></div>
</div>
<button class="btn btn-primary mt-3"><i class="fas fa-user-plus me-1"></i>Create employee</button>
<a href="index.php#employees" class="btn btn-light mt-3">Cancel</a>
</form>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
