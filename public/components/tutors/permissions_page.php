<?php
// Permissions page: every tutor and whether they also have admin rights. Expects $pdo and $permArea ('admin' | 'superadmin').
require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
$permSelf = url($permArea . '/tutors/permissions.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } else {
        $r = tutor_permissions_set($pdo, (int) ($_POST['tutor_id'] ?? 0), ($_POST['access'] ?? '') === 'admin', (int) ($_SESSION['user_id'] ?? 0));
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', htmlspecialchars($r['message']));
    }
    header('Location: ' . $permSelf);
    exit;
}

$permRows = $pdo->query(
    "SELECT t.id, t.is_active, t.can_admin, t.admin_granted_at, u.email, g.username AS granted_by,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name
     FROM tutors t JOIN users u ON u.id = t.user_id
     LEFT JOIN user_profiles up ON up.user_id = u.id
     LEFT JOIN users g ON g.id = t.admin_granted_by
     WHERE u.username NOT LIKE 'preview\\_%'
     ORDER BY t.can_admin DESC, name"
)->fetchAll(PDO::FETCH_ASSOC);
$permManage = tutor_permissions_can_manage();
$permAdmins = count(array_filter($permRows, fn($r) => $r['can_admin']));
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><i class="fas fa-user-lock me-2"></i>Tutor permissions</h1>
        <p class="text-muted small mb-0">Choose who stays tutor-only and who can also use the admin portal. <?php echo $permAdmins; ?> of <?php echo count($permRows); ?> tutors have admin rights.</p>
    </div>
</div>
<?php if (!$permManage): ?><div class="alert alert-info small">Only an admin or the superadmin can change permissions.</div><?php endif; ?>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Tutor</th><th>Access</th><th style="min-width:260px">Change</th></tr></thead>
            <tbody>
            <?php if (!$permRows): ?><tr><td colspan="3" class="text-muted">No tutors yet.</td></tr><?php endif; ?>
            <?php foreach ($permRows as $r): ?>
                <tr>
                    <td><a href="<?php echo url($permArea . '/tutors/view.php?id=' . (int) $r['id']); ?>" class="fw-semibold"><?php echo htmlspecialchars($r['name']); ?></a>
                        <?php if (!$r['is_active']): ?><span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?>
                        <div class="small text-muted"><?php echo htmlspecialchars($r['email']); ?></div></td>
                    <td><?php if ($r['can_admin']): ?>
                            <span class="badge bg-dark"><i class="fas fa-user-shield me-1"></i>Tutor + Admin</span>
                            <?php if ($r['admin_granted_at']): ?><div class="small text-muted mt-1">since <?php echo date('d M Y', strtotime($r['admin_granted_at'])); ?><?php echo $r['granted_by'] ? ' · by ' . htmlspecialchars($r['granted_by']) : ''; ?></div><?php endif; ?>
                        <?php else: ?><span class="badge bg-light text-dark border">Tutor only</span><?php endif; ?></td>
                    <td>
                        <?php if ($permManage): ?>
                        <form method="POST" class="d-flex gap-2 m-0" onsubmit="return this.access.value === 'admin' ? confirm('Give <?php echo htmlspecialchars(addslashes($r['name']), ENT_QUOTES); ?> access to the admin portal?') : true">
                            <?php echo csrf_field(); ?><input type="hidden" name="tutor_id" value="<?php echo (int) $r['id']; ?>">
                            <select name="access" class="form-select form-select-sm">
                                <option value="tutor" <?php echo $r['can_admin'] ? '' : 'selected'; ?>>Tutor portal only</option>
                                <option value="admin" <?php echo $r['can_admin'] ? 'selected' : ''; ?>>Tutor + Admin portal</option>
                            </select>
                            <button class="btn btn-sm btn-dark">Save</button>
                        </form>
                        <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
