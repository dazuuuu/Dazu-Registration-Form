<?php
// Admin: the capstone retake fee per course, and retakes waiting for payment. The admin sees only that a retake is waiting
// (who, which course, the fee): never a capstone mark, comment or file.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CapstoneStatus.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$adminId = (int) ($_SESSION['user_id'] ?? 0);
$installed = true;
try { $db->query('SELECT 1 FROM capstone_retake_fees LIMIT 1'); } catch (Throwable $e) { $installed = false; }
$flash = null;

if ($installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $flash = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'fee') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $raw = trim((string) ($_POST['amount'] ?? ''));
        $ok = $db->prepare('SELECT 1 FROM courses WHERE id = ?');
        $ok->execute([$courseId]);
        if (!$ok->fetchColumn()) {
            $flash = ['danger', 'Course not found.'];
        } elseif ($raw !== '' && (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 1000000)) {
            $flash = ['danger', 'Enter the amount in shillings, for example 5000. Leave it empty to stop retakes for this course.'];
        } else {
            $db->prepare('INSERT INTO capstone_retake_fees (course_id, amount, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_by = VALUES(updated_by)')
               ->execute([$courseId, $raw === '' ? null : round((float) $raw, 2), $adminId]);
            $db->prepare('INSERT INTO capstone_events (event, actor_user_id, details) VALUES (?, ?, ?)')->execute(['retake_fee_set', $adminId, json_encode(['course' => $courseId, 'amount' => $raw === '' ? null : (float) $raw])]);
            $flash = ['success', $raw === '' ? 'Retake fee removed. Retakes are blocked for this course until an amount is set.' : 'Retake fee saved.'];
        }
    } elseif (($_POST['action'] ?? '') === 'paid') {
        // Record the retake fee as paid: the retake opens for the student. Kept apart from course fees.
        require_once __DIR__ . '/../../../app/v1/services/CapstoneService.php';
        require_once __DIR__ . '/../../../app/v1/services/AssignmentNotifier.php';
        $r = cap_retake_paid($db, (int) ($_POST['attempt'] ?? 0), (float) ($_POST['amount'] ?? 0), (string) ($_POST['reference'] ?? ''), $adminId);
        AssignmentNotifier::flush();
        $flash = $r['ok'] ? ['success', $r['opened'] ? 'Payment recorded. The retake is open for the student.' : 'Payment recorded. The retake opens when every team member has paid.'] : ['danger', $r['error']];
    }
}

$courses = $installed ? $db->query("SELECT c.id, c.title, f.amount FROM courses c LEFT JOIN capstone_retake_fees f ON f.course_id = c.id WHERE c.is_active = 1 ORDER BY c.title")->fetchAll(PDO::FETCH_ASSOC) : [];
$waiting = $installed ? cap_retakes_waiting($db) : [];
$page_title = 'Capstone retakes';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Capstone retakes', 'active' => true]];
ob_start();
?>
<div class="container-fluid py-4">
    <h3 class="mb-1"><i class="fas fa-graduation-cap text-success me-2"></i>Capstone retakes</h3>
    <p class="text-muted">A student who does not pass the capstone can retake it, up to 5 times, and pays again each time. Set the retake fee per course. A retake cannot start until an amount is set.</p>
    <?php if (!$installed): ?><div class="alert alert-warning">Run System Updates (migration 088) first.</div><?php endif; ?>
    <?php if ($flash): ?><div class="alert alert-<?php echo $flash[0]; ?>"><?php echo htmlspecialchars($flash[1]); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-header bg-white border-0 pt-3"><h6 class="mb-0">Retake fee per course</h6></div>
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Course</th><th style="width:230px">Retake fee (KES)</th></tr></thead><tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr><td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fee"><input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>">
                                <input name="amount" class="form-control form-control-sm" inputmode="decimal" placeholder="Not set" value="<?php echo $c['amount'] !== null ? htmlspecialchars((string) (float) $c['amount']) : ''; ?>">
                                <button class="btn btn-success btn-sm">Save</button></form></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$courses): ?><tr><td colspan="2" class="text-muted text-center py-3">No courses.</td></tr><?php endif; ?>
                    </tbody></table></div></div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-header bg-white border-0 pt-3"><h6 class="mb-0">Retakes waiting for payment</h6></div>
                <div class="card-body pt-2">
                    <?php if (!$waiting): ?><div class="text-muted small">No retake is waiting for payment.</div><?php endif; ?>
                    <?php foreach ($waiting as $w): ?>
                        <form method="POST" class="border rounded-3 p-3 mb-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="paid"><input type="hidden" name="attempt" value="<?php echo (int) $w['attempt_id']; ?>">
                            <div class="fw-semibold"><?php echo htmlspecialchars($w['student']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($w['course']); ?> · Retake <?php echo (int) $w['attempt_no'] - 1; ?> of 5 · <span class="badge bg-warning text-dark">Waiting for payment</span></div>
                            <div class="row g-2 mt-1"><div class="col-5"><input name="amount" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) $w['amount']); ?>" inputmode="decimal"></div>
                                <div class="col-4"><input name="reference" class="form-control form-control-sm" placeholder="M-Pesa code"></div>
                                <div class="col-3"><button class="btn btn-success btn-sm w-100" onclick="return confirm('Record this retake payment? The retake opens for the student.')">Record paid</button></div></div>
                        </form>
                    <?php endforeach; ?>
                    <div class="small text-muted mt-2">Retake payments are kept apart from course fees: they never change a student's fee balance.</div>
                </div></div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
