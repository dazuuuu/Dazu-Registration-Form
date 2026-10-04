<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

use Models\Connection;
use Models\TutorModel;
use Models\TutorCourseModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$students = $tutorModel->getStudentsForTutor((int)$tutor['id']);
$tc = new TutorCourseModel($pdo);

$certs = [];
if ($students) {
    $ids = implode(',', array_map(fn($s) => (int) $s['id'], $students));
    $certs = $pdo->query("SELECT registration_id, certificate_no FROM certificates WHERE status <> 'withdrawn' AND registration_id IN ($ids)")->fetchAll(\PDO::FETCH_KEY_PAIR);
}
$page_title = 'My Students';
ob_start();
?>
<div class="card-tutor"><div class="head">Assigned Students (<?php echo count($students); ?>)</div>
<?php if (empty($students)): ?><p class="p-3 text-muted mb-0">No students have been assigned to you yet.</p>
<?php else: ?>
<div class="table-responsive"><table class="table mb-0 align-middle">
<thead class="table-light"><tr><th>Student</th><th>Course</th><th>Modules</th><th>Grade</th><th>Balance</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($students as $s):
    $p = $tc->progressFor($s);
    $bal = cert_student_balance($pdo, (int) $s['id']);
?>
<tr>
<td><?php echo htmlspecialchars($s['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($s['registration_no']); ?></small></td>
<td><?php echo htmlspecialchars($s['course_title']); ?></td>
<td style="min-width:130px">
    <?php if ($p['total']): ?>
        <div class="small"><?php echo $p['done']; ?> / <?php echo $p['total']; ?></div>
        <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:<?php echo $p['percent']; ?>%"></div></div>
    <?php else: ?><span class="small text-muted">No modules yet</span><?php endif; ?>
</td>
<td><?php echo $p['grade_percent'] !== null ? $p['grade_percent'] . '%' : '—'; ?></td>
<td class="<?php echo $bal > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $bal > 0 ? 'KES ' . number_format($bal) : 'Cleared'; ?></td>
<td>
    <?php if (isset($certs[$s['id']])): ?><span class="badge bg-success"><i class="fas fa-certificate me-1"></i>Certified</span>
    <?php elseif (!empty($s['studies_completed_at'])): ?><span class="badge bg-info text-dark">Studies completed</span>
    <?php else: ?><span class="badge bg-secondary">In progress</span><?php endif; ?>
</td>
<td><a href="view.php?id=<?php echo (int)$s['id']; ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
