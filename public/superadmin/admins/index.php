<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/AdminAccountModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/EmployeeNotifier.php';
require_once __DIR__ . '/../../../app/v1/models/ExpenseModel.php';

use Models\Connection;
use Models\AdminAccountModel;

require_once __DIR__ . '/../../../app/v1/services/EmailConflictService.php';
Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$admins = new AdminAccountModel($pdo);
$exm = new \Models\ExpenseModel($pdo);
try {
    $branchOf = $exm->branches();
} catch (Throwable $t) {
    $branchOf = null;   // migration 084 not run yet
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'index.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'create') {
        $r = $admins->create((string)($_POST['full_name'] ?? ''), (string)($_POST['email'] ?? ''), (string)($_POST['phone'] ?? ''), !empty($_POST['replace_existing']));
        if ($r['ok']) {
            if ($branchOf !== null) $exm->setBranch((int) $r['user_id'], $_POST['branch'] ?? null);
            $admin = $admins->get($r['user_id']);
            $msg = 'Admin created.';
            if (isset($_POST['send_email'])) {
                $mail = (new EmployeeNotifier())->sendAdminCredentials($admin, $r['temp_password']);
                $msg .= $mail['ok'] ? ' Sign-in details were emailed.'
                    : ' The email could not be sent (' . htmlspecialchars($mail['message']) . '). Temporary password: <strong>' . htmlspecialchars($r['temp_password']) . '</strong>';
            } else {
                $msg .= ' Temporary password (shown once): <strong>' . htmlspecialchars($r['temp_password']) . '</strong>';
            }
            SessionModel::setFlash('success', $msg);
        } else {
            SessionModel::setFlash('error', $r['error']);
        }
    } elseif ($action === 'branch' && $branchOf !== null && $admins->get($id)) {
        $exm->setBranch($id, $_POST['branch'] ?? null);
        SessionModel::setFlash('success', 'Branch saved.');
    } elseif ($action === 'toggle') {
        $target = $admins->get($id);
        $ok = $target && $admins->setActive($id, !$target['is_active']);
        SessionModel::setFlash($ok ? 'success' : 'error', $ok ? ($target['is_active'] ? 'Admin deactivated. They can no longer sign in.' : 'Admin activated.') : 'Admin not found.');
    } elseif ($action === 'reset') {
        $target = $admins->get($id);
        $temp = $target ? $admins->resetPassword($id) : null;
        if ($temp) {
            $mail = (new EmployeeNotifier())->sendAdminCredentials($target, $temp);
            SessionModel::setFlash($mail['ok'] ? 'success' : 'error', $mail['ok'] ? 'New sign-in details emailed.'
                : 'Password reset but the email failed (' . htmlspecialchars($mail['message']) . '). Temporary password: <strong>' . htmlspecialchars($temp) . '</strong>');
        } else {
            SessionModel::setFlash('error', 'Admin not found.');
        }
    }
    header('Location: ' . $back); exit();
}

$list = $admins->listAdmins();
$page_title = 'Admins';
$sa_subtitle = 'People who run the admin portal (registrations, courses, tutors, website).';
ob_start();
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-header fw-semibold">Create an admin</div><div class="card-body">
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
                <label class="form-label">Full name</label><input name="full_name" class="form-control mb-3" required>
                <label class="form-label">Email <span class="text-muted small">(they sign in with this)</span></label><input type="email" name="email" class="form-control mb-1" required inputmode="email"><div class="mb-3"><?php echo EmailConflictService::checkbox(); ?></div>
                <label class="form-label">Phone <span class="text-muted small">(optional)</span></label><input name="phone" class="form-control mb-3" maxlength="30" inputmode="tel">
                <?php if ($branchOf !== null): ?>
                <label class="form-label">Branch <span class="text-muted small">(for expense requests)</span></label>
                <select name="branch" class="form-select mb-3"><option value="">None</option><?php foreach (\Models\ExpenseModel::ADMIN_BRANCHES as $bk => $bl): ?><option value="<?php echo $bk; ?>"><?php echo $bl; ?></option><?php endforeach; ?></select>
                <?php endif; ?>
                <div class="form-check mb-3"><input type="checkbox" name="send_email" value="1" class="form-check-input" id="se" checked><label for="se" class="form-check-label">Email their sign-in details</label></div>
                <button class="btn btn-primary w-100"><i class="fas fa-user-shield me-1"></i>Create admin</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-header fw-semibold">Admins (<?php echo count($list); ?>)</div>
        <?php if (!$list): ?><div class="card-body text-muted">No admins yet.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($list as $a): ?>
            <div class="list-group-item py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="min-w-0">
                        <div class="fw-semibold"><?php echo htmlspecialchars($a['full_name']); ?>
                            <?php echo $a['is_active'] ? '<span class="badge bg-success ms-1">Active</span>' : '<span class="badge bg-secondary ms-1">Inactive</span>'; ?></div>
                        <div class="text-muted small text-break"><?php echo htmlspecialchars($a['email']); ?></div>
                        <?php if ($branchOf !== null): ?>
                        <form method="POST" class="d-flex gap-1 mt-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="branch"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <select name="branch" class="form-select form-select-sm w-auto" onchange="this.form.submit()" aria-label="Branch"><option value="">No branch</option>
                                <?php foreach (\Models\ExpenseModel::ADMIN_BRANCHES as $bk => $bl): ?><option value="<?php echo $bk; ?>" <?php echo ($branchOf[(int)$a['id']] ?? '') === $bk ? 'selected' : ''; ?>><?php echo $bl; ?> branch</option><?php endforeach; ?></select></form>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="POST" onsubmit="return confirm('Reset the password and email new sign-in details?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-key me-1"></i>Reset password</button></form>
                        <form method="POST" onsubmit="return confirm('<?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?> this admin?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                            <button class="btn btn-sm btn-outline-<?php echo $a['is_active'] ? 'danger' : 'success'; ?>"><?php echo $a['is_active'] ? 'Deactivate' : 'Activate'; ?></button></form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div></div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
