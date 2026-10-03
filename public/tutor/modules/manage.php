<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

use Models\Connection;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutor_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM tutors WHERE user_id = ?");
$stmt->execute([$tutor_id]);
$tutor = $stmt->fetch();

$module_id = (int)($_GET['id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0);

// Validate module ownership
$stmt = $pdo->prepare("SELECT * FROM course_modules WHERE id = ? AND tutor_id = ?");
$stmt->execute([$module_id, $tutor['id']]);
$module = $stmt->fetch();
if (!$module) die("Module not found or unauthorized.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type']; // 'material', 'assignment', 'question'
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $max_score = !empty($_POST['max_score']) ? (int)$_POST['max_score'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    if ($title && in_array($type, ['material', 'assignment', 'question'])) {
        $stmt = $pdo->prepare("INSERT INTO module_contents (module_id, type, title, content, max_score, due_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$module_id, $type, $title, $content, $max_score, $due_date]);
        \Models\SessionModel::setFlash('success', 'Content added to module.');
    }
    header("Location: manage.php?id=$module_id&student_id=$student_id");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM module_contents WHERE module_id = ? ORDER BY created_at ASC");
$stmt->execute([$module_id]);
$contents = $stmt->fetchAll();

$page_title = 'Manage Module: ' . $module['title'];
ob_start();
?>
<a href="index.php?course_id=<?php echo $module['course_id']; ?>&student_id=<?php echo $student_id; ?>" class="btn btn-link btn-sm ps-0 mb-3">← Back to Modules</a>

<div class="row">
    <div class="col-md-5">
        <div class="card-tutor mb-4">
            <div class="head">Add Content / Assignment</div>
            <div class="p-3">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm" required onchange="toggleFields(this.value)">
                            <option value="material">Study Material</option>
                            <option value="assignment">Assignment</option>
                            <option value="question">Question</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Title</label>
                        <input type="text" name="title" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Content / Instructions</label>
                        <textarea name="content" class="form-control form-control-sm" rows="5"></textarea>
                    </div>
                    <div id="gradingFields" style="display: none;">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Max Score (Optional)</label>
                                <input type="number" name="max_score" class="form-control form-control-sm" placeholder="e.g. 100">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Due Date (Optional)</label>
                                <input type="date" name="due_date" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand w-100">Add to Module</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card-tutor">
            <div class="head">Module Contents</div>
            <?php if (empty($contents)): ?>
                <div class="p-4 text-center text-muted">No content added yet.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($contents as $c): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-bold">
                                    <?php if ($c['type'] === 'assignment') echo '<i class="fas fa-tasks text-warning me-2"></i>'; ?>
                                    <?php if ($c['type'] === 'question') echo '<i class="fas fa-question-circle text-info me-2"></i>'; ?>
                                    <?php if ($c['type'] === 'material') echo '<i class="fas fa-file-alt text-primary me-2"></i>'; ?>
                                    <?php echo htmlspecialchars($c['title']); ?>
                                </h6>
                                <span class="badge bg-light text-dark border"><?php echo ucfirst($c['type']); ?></span>
                            </div>
                            <div class="text-muted small mb-2" style="white-space: pre-wrap;"><?php echo htmlspecialchars($c['content']); ?></div>
                            
                            <?php if (in_array($c['type'], ['assignment', 'question'])): ?>
                                <div class="bg-light p-2 rounded small d-flex gap-3 mt-2">
                                    <?php if ($c['max_score']): ?>
                                        <span class="text-muted"><i class="fas fa-star me-1"></i> Max Score: <?php echo $c['max_score']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($c['due_date']): ?>
                                        <span class="text-danger"><i class="fas fa-clock me-1"></i> Due: <?php echo date('M d, Y', strtotime($c['due_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleFields(type) {
    document.getElementById('gradingFields').style.display = (type === 'assignment' || type === 'question') ? 'block' : 'none';
}
</script>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../templates/tutor/layout.php';
