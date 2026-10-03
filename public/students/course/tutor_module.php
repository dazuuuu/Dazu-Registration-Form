<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';

use Models\Connection;
use Models\StudentModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$userId = (int)$_SESSION['user_id'];

$reg = $studentModel->getByUserId($userId);
if (!$reg || $reg['portal_locked']) { header('Location: index.php'); exit(); }

$module_id = (int)($_GET['id'] ?? 0);

// Validate module ownership (must belong to this student's tutor and course)
$stmt = $pdo->prepare("SELECT m.* FROM course_modules m 
                       JOIN tutor_students ts ON m.tutor_id = ts.tutor_id 
                       WHERE m.id = ? AND m.course_id = ? AND ts.user_id = ?");
$stmt->execute([$module_id, (int)$reg['course_id'], (int)$reg['id']]);
$module = $stmt->fetch();
if (!$module) { header('Location: index.php'); exit(); }

// Fetch content
$stmt = $pdo->prepare("SELECT * FROM module_contents WHERE module_id = ? ORDER BY created_at ASC");
$stmt->execute([$module_id]);
$contents = $stmt->fetchAll();

// Process submissions for assignments/questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $content_id = (int)$_POST['content_id'];
    $submission_text = trim($_POST['submission_text']);
    
    // Ensure content exists in this module and is of right type
    $stmt = $pdo->prepare("SELECT * FROM module_contents WHERE id = ? AND module_id = ? AND type IN ('assignment', 'question')");
    $stmt->execute([$content_id, $module_id]);
    $item = $stmt->fetch();
    
    if ($item && $submission_text) {
        $stmt = $pdo->prepare("INSERT INTO student_submissions (registration_id, submission_type, module_content_id, submitted_at, file_path, status) VALUES (?, 'assignment', ?, NOW(), ?, 'submitted')");
        $stmt->execute([(int)$reg['id'], $content_id, $submission_text]); // Wait, file_path is for text here or what? Let me check the schema.
        \Models\SessionModel::setFlash('success', 'Assignment submitted successfully.');
    }
    header("Location: tutor_module.php?id=$module_id");
    exit;
}

// Fetch submissions mapping
$stmt = $pdo->prepare("SELECT * FROM student_submissions WHERE registration_id = ? AND submission_type = 'assignment'");
$stmt->execute([(int)$reg['id']]);
$subs = $stmt->fetchAll();
$submissionsMap = [];
foreach ($subs as $s) {
    $submissionsMap[(int)$s['module_content_id']] = $s;
}

$page_title = 'Module: ' . $module['title'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-3">← Back to Course</a>

<div class="card-student mb-4">
    <div class="card-head">
        <h5 class="mb-0 text-white"><?php echo htmlspecialchars($module['title']); ?></h5>
    </div>
    <div class="card-body">
        <?php if ($module['description']): ?>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($module['description']); ?></p>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($contents)): ?>
    <div class="alert alert-info">No content has been added to this module yet.</div>
<?php else: ?>
    <?php foreach ($contents as $idx => $c): 
        $sub = $submissionsMap[$c['id']] ?? null;
    ?>
    <div class="card-student mb-4 shadow-sm">
        <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <?php if ($c['type'] === 'assignment') echo '<i class="fas fa-tasks text-warning me-2"></i>'; ?>
                <?php if ($c['type'] === 'question') echo '<i class="fas fa-question-circle text-info me-2"></i>'; ?>
                <?php if ($c['type'] === 'material') echo '<i class="fas fa-file-alt text-primary me-2"></i>'; ?>
                <?php echo htmlspecialchars($c['title']); ?>
            </h6>
            <?php if (in_array($c['type'], ['assignment', 'question'])): ?>
                <div>
                    <?php if ($c['max_score']): ?><span class="badge bg-secondary me-2">Score: <?php echo $c['max_score']; ?></span><?php endif; ?>
                    <?php if ($sub): ?>
                        <span class="badge bg-success">Submitted</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Pending</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
            <div style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($c['content']); ?></div>
            
            <?php if (in_array($c['type'], ['assignment', 'question'])): ?>
                <hr class="my-4">
                <?php if ($sub): ?>
                    <div class="bg-light p-3 rounded border">
                        <h6 class="fw-bold mb-2 text-success"><i class="fas fa-check-circle me-1"></i> Your Submission</h6>
                        <div class="text-muted small mb-2">Submitted on <?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></div>
                        <div style="white-space: pre-wrap;" class="fst-italic"><?php echo htmlspecialchars($sub['file_path']); ?></div>
                        
                        <?php if ($sub['status'] === 'graded'): ?>
                            <div class="mt-3 pt-3 border-top border-success">
                                <h6 class="fw-bold text-success mb-1">Tutor Feedback & Grade</h6>
                                <div><strong>Grade:</strong> <?php echo htmlspecialchars($sub['marks'] ?? 'N/A'); ?> / <?php echo $c['max_score'] ?? '-'; ?></div>
                                <?php if ($sub['feedback']): ?>
                                    <div class="mt-2 text-muted" style="white-space: pre-wrap;"><?php echo htmlspecialchars($sub['feedback']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="submit_assignment" value="1">
                        <input type="hidden" name="content_id" value="<?php echo $c['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your Answer</label>
                            <textarea name="submission_text" class="form-control" rows="4" placeholder="Type your answer here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-brand w-100">Submit Answer</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php'; ?>
