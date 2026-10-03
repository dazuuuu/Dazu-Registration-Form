<?php
require_once __DIR__ . '/../../../app/v1/init.php';
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

$stmt = $pdo->prepare("SELECT * FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
$stmt->execute([(int)$tutor['id'], $regId]);
$ts_relation = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM tutor_announcements WHERE tutor_id = ? AND student_id = ? ORDER BY created_at DESC");
$stmt->execute([(int)$tutor['id'], $regId]);
$announcements = $stmt->fetchAll();

if (!$student || !$ts_relation) {
    header('Location: index.php'); exit();
}

$progress = (new ProgressModel($pdo))->getProgress($regId, (int)$student['course_id']);
$submissions = (new SubmissionModel($pdo))->getForRegistration($regId);
$page_title = $student['full_name'];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-link btn-sm ps-0 mb-0">← All students</a>
    <a href="../modules/index.php?course_id=<?php echo $student['course_id']; ?>&student_id=<?php echo $regId; ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-layer-group me-1"></i> Manage Course Modules
    </a>
</div>

<div class="card-tutor mb-3"><div class="head">Student Info</div><div class="p-3">
<strong><?php echo htmlspecialchars($student['full_name']); ?></strong> (<?php echo htmlspecialchars($student['registration_no']); ?>)<br>
<?php echo htmlspecialchars($student['email']); ?> · <?php echo htmlspecialchars($student['course_title']); ?>
</div></div>

<div class="card-tutor mb-3">
    <div class="head d-flex justify-content-between align-items-center">
        <span>Study Timeline</span>
    </div>
    <div class="p-3">
        <form action="timeline.php" method="POST" class="row align-items-end g-2">
            <input type="hidden" name="student_id" value="<?php echo $regId; ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($ts_relation['start_date'] ?? date('Y-m-d')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Timeline (Days)</label>
                <input type="number" name="timeline_days" class="form-control form-control-sm" placeholder="e.g. 90" value="<?php echo (int)($ts_relation['timeline_days'] ?? 0); ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-brand btn-sm w-100">Save Timeline</button>
            </div>
        </form>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
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
        
        <div class="card-tutor mb-3"><div class="head">Submissions</div>
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
    </div>
    
    <div class="col-md-6">
        <div class="card-tutor mb-3">
            <div class="head">Send Announcement</div>
            <div class="p-3">
                <form action="announce.php" method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $regId; ?>">
                    <div class="mb-2">
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="Title" required>
                    </div>
                    <div class="mb-2">
                        <textarea name="message" class="form-control form-control-sm" rows="3" placeholder="Message to student..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-brand btn-sm w-100">Send to Student</button>
                </form>
            </div>
            
            <?php if (!empty($announcements)): ?>
                <div class="list-group list-group-flush border-top">
                    <?php foreach ($announcements as $ann): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <strong class="small"><?php echo htmlspecialchars($ann['title']); ?></strong>
                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d', strtotime($ann['created_at'])); ?></small>
                            </div>
                            <p class="mb-0 small text-muted text-truncate"><?php echo htmlspecialchars($ann['message']); ?></p>
                            <small class="badge bg-<?php echo $ann['is_read'] ? 'success' : 'secondary'; ?> mt-1" style="font-size: 0.6rem;"><?php echo $ann['is_read'] ? 'Read' : 'Unread'; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
