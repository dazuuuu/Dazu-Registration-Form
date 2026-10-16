<?php
// public/superadmin/registrations/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\RegistrationModel;

Middleware::requireSuperPortal();

$pdo     = Connection::getInstance()->getConnection();
$regModel = new RegistrationModel($pdo);

$status = in_array($_GET['status'] ?? '', ['pending','approved','rejected']) ? $_GET['status'] : 'pending';

// Delete applications straight from the list, no review needed (pending and rejected only; approved students have accounts).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_selected') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } else {
        $ids = isset($_POST['single']) ? [(int) $_POST['single']] : array_map('intval', (array) ($_POST['ids'] ?? []));
        $deleted = 0;
        foreach (array_unique($ids) as $rid) {
            $reg = $rid > 0 ? $regModel->getById($rid) : null;
            if ($reg && in_array($reg['status'], ['pending', 'rejected'], true) && !empty($regModel->delete($rid)['ok'])) {
                $deleted++;
            }
        }
        SessionModel::setFlash($deleted ? 'success' : 'error', $deleted ? $deleted . ' application' . ($deleted === 1 ? '' : 's') . ' deleted.' : 'Nothing was deleted. Select at least one pending application.');
    }
    header('Location: ?status=' . $status);
    exit();
}
$rows   = $regModel->listByStatus($status);

// Count badges for each tab
$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE status = :s");
    $st->execute([':s' => $s]);
    $counts[$s] = (int) $st->fetchColumn();
}

$page_title  = 'Student Registrations';
$breadcrumbs = [['label' => 'Registrations', 'active' => true]];

ob_start();
?>
<ul class="nav nav-tabs mb-4">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $status === $key ? 'active' : ''; ?>"
               href="?status=<?php echo $key; ?>">
                <?php echo $label; ?>
                <?php if ($counts[$key] > 0): ?>
                    <span class="badge <?php echo $key === 'pending' ? 'bg-warning text-dark' : ($key === 'approved' ? 'bg-success' : 'bg-danger'); ?> ms-1">
                        <?php echo $counts[$key]; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="input-group mb-3" style="max-width:420px">
    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
    <input type="search" id="regSearch" class="form-control" placeholder="Search by name, email or course…" autocomplete="off" aria-label="Search applicants">
</div>

<?php $canDelete = $status !== 'approved'; ?>
<form method="POST" id="regListForm">
<?php echo csrf_field(); ?>
<input type="hidden" name="action" value="delete_selected">
<?php if ($canDelete && !empty($rows)): ?>
<div class="d-flex align-items-center gap-2 mb-2">
    <button type="submit" class="btn btn-sm btn-danger" id="delSelected" disabled onclick="return confirm('Delete the selected applications? This cannot be undone.');"><i class="fas fa-trash me-1"></i>Delete selected <span id="delCount"></span></button>
    <span class="small text-muted">Tick the applications you don't need to review, then delete them.</span>
</div>
<?php endif; ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if ($canDelete): ?><th style="width:34px"><input type="checkbox" class="form-check-input" id="selAll" aria-label="Select all"></th><?php endif; ?>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Mode</th>
                        <th>Plan</th>
                        <th>Reg. Fee</th>
                        <th>Course Fee</th>
                        <th>Submitted</th>
                        <?php if ($status === 'approved'): ?><th>Reg. No.</th><?php endif; ?>
                        <?php if ($status === 'rejected'): ?><th>Reason</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">
                        No <?php echo $status; ?> registrations.
                    </td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr class="reg-row" data-search="<?php echo htmlspecialchars(strtolower($r['full_name'] . ' ' . $r['email'] . ' ' . ($r['course_title'] ?? '') . ' ' . ($r['registration_no'] ?? ''))); ?>">
                        <?php if ($canDelete): ?><td><input type="checkbox" class="form-check-input row-sel" name="ids[]" value="<?php echo (int)$r['id']; ?>" aria-label="Select"></td><?php endif; ?>
                        <td>
                            <?php echo htmlspecialchars($r['full_name']); ?>
                            <?php if ($r['possible_returning_student']): ?>
                                <span class="badge bg-info ms-1" title="Possible returning student">↩ Returning?</span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                        <td><?php echo $r['study_mode'] === 'physical' ? '🏫 Physical' : '💻 Online'; ?></td>
                        <td><?php echo htmlspecialchars($r['payment_plan']); ?></td>
                        <td>
                            <?php if ($r['registration_fee_waived']): ?>
                                <span class="text-decoration-line-through text-muted">KSh <?php echo number_format($r['registration_fee']); ?></span>
                                <span class="badge bg-secondary ms-1">Waived</span>
                            <?php else: ?>
                                KSh <?php echo number_format($r['registration_fee']); ?>
                            <?php endif; ?>
                        </td>
                        <td>KSh <?php echo number_format($r['course_fee']); ?></td>
                        <td><small><?php echo date('d M Y', strtotime($r['created_at'])); ?></small></td>
                        <?php if ($status === 'approved'): ?>
                            <td><strong><?php echo htmlspecialchars($r['registration_no']); ?></strong></td>
                        <?php endif; ?>
                        <?php if ($status === 'rejected'): ?>
                            <td><small class="text-danger"><?php echo htmlspecialchars($r['rejection_reason']); ?></small></td>
                        <?php endif; ?>
                        <td>
                            <a href="<?php echo url('superadmin/registrations/view.php?id=' . (int)$r['id']); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <?php echo $status === 'pending' ? 'Review' : 'View'; ?>
                            </a>
                            <?php if ($canDelete): ?>
                            <button type="submit" name="single" value="<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-danger ms-1" title="Delete" onclick="return confirm('Delete this application? This cannot be undone.');"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                    <tr id="regNoMatch" style="display:none"><td colspan="10" class="text-center text-muted py-4">No applicants match your search.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</form>
<script>
(function () {
  var input = document.getElementById('regSearch'), empty = document.getElementById('regNoMatch');
  if (!input) return;
  var rows = Array.prototype.slice.call(document.querySelectorAll('.reg-row'));
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (r) {
      var hit = !q || r.getAttribute('data-search').indexOf(q) !== -1;
      r.style.display = hit ? '' : 'none';
      if (hit) shown++;
    });
    if (empty) empty.style.display = rows.length && !shown ? '' : 'none';
  });
})();
(function () {
  var all = document.getElementById('selAll'), btn = document.getElementById('delSelected'), cnt = document.getElementById('delCount');
  if (!all) return;
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.row-sel'));
  function sync() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (btn) btn.disabled = n === 0;
    if (cnt) cnt.textContent = n ? '(' + n + ')' : '';
    all.checked = boxes.length > 0 && n === boxes.length;
  }
  all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';