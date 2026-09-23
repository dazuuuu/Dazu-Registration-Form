<?php
// public/admin/courses/classes.php — classify each course as a Certificate or Diploma course.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/course_duration.php';
require_once __DIR__ . '/../../../app/v1/helpers/registration_number.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\CourseModel;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$self = url('superadmin/courses/classes.php');
$hasColumn = true;
try {
    $pdo->query('SELECT qualification FROM courses LIMIT 1');
} catch (Throwable $e) {
    $hasColumn = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif (!$hasColumn) {
        SessionModel::setFlash('error', 'Apply the pending system update first.');
    } elseif (($_POST['action'] ?? '') === 'fix_numbers') {
        $n = registration_number_resync($pdo);
        SessionModel::setFlash('success', $n ? $n . ' registration number' . ($n === 1 ? '' : 's') . ' corrected.' : 'Every registration number already matches its course.');
    } else {
        foreach ((array) ($_POST['qualification'] ?? []) as $courseId => $q) {
            $courseModel->setQualification((int) $courseId, (string) $q);
        }
        SessionModel::setFlash('success', 'Classes saved.');
    }
    header('Location: ' . $self);
    exit();
}

$courses = $courseModel->getAll(false);
$auto = fn(array $c): string => course_effective_months($c) < 18 ? 'certificate' : 'diploma';

$page_title = 'Classes';
$sa_subtitle = 'Certificate or Diploma, per course.';
ob_start();
?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Classes</h5></div>
    <div class="card-body">
        <p class="text-muted small">Mark each course as a Certificate or a Diploma course. Courses left on Automatic are a Certificate when they run under 18 months and a Diploma from 18 months up.</p>
        <form method="POST"><?php echo csrf_field(); ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Course</th><th>Length</th><th style="min-width:260px">Class</th></tr></thead>
                    <tbody>
                    <?php foreach ($courses as $c): $cur = (string) ($c['qualification'] ?? ''); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td class="text-muted small"><?php echo course_effective_months($c); ?> month<?php echo course_effective_months($c) === 1 ? '' : 's'; ?></td>
                            <td>
                                <select name="qualification[<?php echo (int) $c['id']; ?>]" class="form-select form-select-sm" <?php echo $hasColumn ? '' : 'disabled'; ?>>
                                    <option value="">Automatic (<?php echo ucfirst($auto($c)); ?>)</option>
                                    <option value="certificate" <?php echo $cur === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                                    <option value="diploma" <?php echo $cur === 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$courses): ?><tr><td colspan="3" class="text-muted text-center py-4">No courses yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn btn-success" <?php echo $hasColumn && $courses ? '' : 'disabled'; ?>><i class="fas fa-save me-1"></i>Save classes</button>
        </form>
        <hr>
        <form method="POST" class="d-flex align-items-center gap-3 flex-wrap"><?php echo csrf_field(); ?><input type="hidden" name="action" value="fix_numbers">
            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-wrench me-1"></i>Fix registration numbers</button>
            <span class="small text-muted">Corrects every student's number to match their course (DCH / DDH).</span>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/superadmin/layout.php';
