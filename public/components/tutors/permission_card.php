<?php
// "Permissions" card on a tutor's admin/superadmin page. Expects $pdo, $tutor (tutors row incl. can_admin) and $id.
// The page handles POST action=permissions through tutor_permissions_set().
require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
$permCanAdmin = !empty($tutor['can_admin']);
$permBy = null;
if ($permCanAdmin && !empty($tutor['admin_granted_by'])) {
    $st = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $st->execute([(int) $tutor['admin_granted_by']]);
    $permBy = $st->fetchColumn() ?: null;
}
?>
<div class="card mb-4 <?php echo $permCanAdmin ? 'border-dark' : ''; ?>" id="permissions">
    <div class="card-header fw-semibold"><i class="fas fa-user-lock me-1"></i>Permissions</div>
    <div class="card-body">
        <?php if (!tutor_permissions_can_manage()): ?>
            <p class="mb-0 small"><strong><?php echo $permCanAdmin ? 'Tutor + Admin portal' : 'Tutor portal only'; ?></strong><br>
                <span class="text-muted">Only an admin or the superadmin can change this.</span></p>
        <?php else: ?>
        <form method="POST" class="m-0">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="permissions">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="access" value="tutor" id="accTutor" <?php echo $permCanAdmin ? '' : 'checked'; ?>>
                <label class="form-check-label" for="accTutor"><strong>Tutor portal only</strong><br>
                    <span class="small text-muted">Their students, course builder, submissions and all tutor features.</span></label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="access" value="admin" id="accAdmin" <?php echo $permCanAdmin ? 'checked' : ''; ?>>
                <label class="form-check-label" for="accAdmin"><strong>Tutor + Admin portal</strong><br>
                    <span class="small text-muted">Everything above, plus the full admin portal (not the superadmin portal). They choose a portal when signing in and can switch any time.</span></label>
            </div>
            <button class="btn btn-sm btn-dark w-100">Save permissions</button>
        </form>
        <?php endif; ?>
        <?php if ($permCanAdmin && !empty($tutor['admin_granted_at'])): ?>
            <div class="small text-muted mt-2">Admin rights given <?php echo date('d M Y', strtotime($tutor['admin_granted_at'])); ?><?php echo $permBy ? ' by ' . htmlspecialchars($permBy) : ''; ?>.</div>
        <?php endif; ?>
    </div>
</div>
