<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\ProgressModel;
use Models\CurriculumModel;
use Models\SubmissionModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$regId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT r.*, c.title AS course_title FROM registrations r JOIN courses c ON r.course_id=c.id WHERE r.id=:id AND r.status='approved'");
$stmt->execute([':id'=>$regId]);
$student = $stmt->fetch();
if (!$student || !$tutorModel->tutorTeachesCourse((int)$tutor['id'], (int)$student['course_id'])) {
    header('Location: index.php'); exit();
}

$progress = (new ProgressModel($pdo))->getProgress($regId, (int)$student['course_id']);
$submissions = (new SubmissionModel($pdo))->getForRegistration($regId);
$page_title = $student['full_name'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-3">← All students</a>
<div class="card-tutor mb-3"><div class="head">Student Info</div><div class="p-3">
<strong><?php echo htmlspecialchars($student['full_name']); ?></strong> (<?php echo htmlspecialchars($student['registration_no']); ?>)<br>
<?php echo htmlspecialchars($student['email']); ?> · <?php echo htmlspecialchars($student['course_title']); ?>
</div></div>
<div class="card-tutor mb-3"><div class="head">Unit Progress</div><div class="p-0">
<table class="table mb-0"><thead class="table-light"><tr><th>Unit</th><th>Test</th><th>Project</th><th>Status</th></tr></thead><tbody>
<?php foreach ($progress as $p): ?>
<tr>
<td><?php echo htmlspecialchars($p['title']); ?></td>
<td><?php echo $p['test_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
<td><?php echo $p['project_completed'] ? '✅' : ($p['is_unlocked'] ? '⏳' : '—'); ?></td>
<td><?php echo $p['completed_at'] ? '<span class="badge bg-success">Complete</span>' : ($p['is_unlocked'] ? '<span class="badge bg-primary">In progress</span>' : '<span class="badge bg-secondary">Locked</span>'); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<div class="card-tutor"><div class="head">Submissions</div>
<?php if (empty($submissions)): ?><p class="p-3 text-muted mb-0">No submissions yet.</p>
<?php else: ?><div class="list-group list-group-flush">
<?php foreach ($submissions as $sub): ?>
<div class="list-group-item d-flex justify-content-between">
<span><?php echo ucfirst($sub['submission_type']); ?>: <?php echo htmlspecialchars($sub['item_title']); ?>
<span class="badge bg-<?php echo $sub['status']==='graded'?'success':'warning'; ?> ms-2"><?php echo $sub['status']; ?></span>
<?php if ($sub['marks']!==null): ?> — <?php echo $sub['marks']; ?> marks<?php endif; ?>
</span>
<?php if ($sub['status']==='submitted'): ?><a href="<?php echo url('tutor/submissions/index.php#sub-' . (int)$sub['id']); ?>" class="btn btn-sm btn-brand">Grade</a><?php endif; ?>
</div>
<?php endforeach; ?>
</div><?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
