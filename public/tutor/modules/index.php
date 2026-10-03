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
if (!$tutor) die("Tutor profile not found.");

$course_id = (int)($_GET['course_id'] ?? 0);
$student_id = (int)($_GET['student_id'] ?? 0); // For back link

// Fetch course
$stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) die("Course not found.");

// Create module
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_module'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    if ($title) {
        $stmt = $pdo->prepare("INSERT INTO course_modules (course_id, tutor_id, title, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $tutor['id'], $title, $description]);
        \Models\SessionModel::setFlash('success', 'Module created successfully.');
        header("Location: ?course_id=$course_id&student_id=$student_id");
        exit;
    }
}

// Fetch modules
$stmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? AND tutor_id = ? ORDER BY order_index ASC, created_at ASC");
$stmt->execute([$course_id, $tutor['id']]);
$modules = $stmt->fetchAll();

$page_title = 'Modules for ' . $course['title'];
ob_start();
?>
<?php if ($student_id): ?>
    <a href="<?php echo url("tutor/students/view.php?id=$student_id"); ?>" class="btn btn-link btn-sm ps-0 mb-3">← Back to Student</a>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card-tutor mb-4">
            <div class="head">Create New Module</div>
            <div class="p-3">
                <form method="POST">
                    <input type="hidden" name="create_module" value="1">
                    <div class="mb-2">
                        <label class="form-label small">Module Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-brand w-100">Create Module</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card-tutor">
            <div class="head">Course Modules</div>
            <?php if (empty($modules)): ?>
                <div class="p-4 text-center text-muted">No modules created yet.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($modules as $m): ?>
                        <div class="list-group-item p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($m['title']); ?></h5>
                                <a href="manage.php?id=<?php echo $m['id']; ?>&student_id=<?php echo $student_id; ?>" class="btn btn-sm btn-outline-primary">Manage Content & Assignments</a>
                            </div>
                            <?php if ($m['description']): ?>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($m['description']); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            // Fetch content count
                            $stmt = $pdo->prepare("SELECT type, COUNT(*) as cnt FROM module_contents WHERE module_id = ? GROUP BY type");
                            $stmt->execute([$m['id']]);
                            $counts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
                            ?>
                            <div class="mt-2 small text-muted">
                                <span class="me-3"><i class="fas fa-file-alt me-1"></i> <?php echo $counts['material'] ?? 0; ?> Materials</span>
                                <span class="me-3"><i class="fas fa-tasks me-1"></i> <?php echo $counts['assignment'] ?? 0; ?> Assignments</span>
                                <span><i class="fas fa-question-circle me-1"></i> <?php echo $counts['question'] ?? 0; ?> Questions</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../../templates/tutor/layout.php';
