<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';

use Models\Connection;
use Models\TutorModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutor = (new TutorModel($pdo))->getByUserId((int)$_SESSION['user_id']);
$page_title = 'My Courses';
ob_start();
?>
<div class="row g-3">
<?php foreach ($tutor['courses'] as $c): ?>
<div class="col-md-6"><div class="card-tutor p-4">
    <h5><?php echo htmlspecialchars($c['title']); ?></h5>
    <p class="text-muted small mb-3">Abbreviation: <?php echo htmlspecialchars($c['abbreviation']); ?></p>
    <a href="curriculum.php?course_id=<?php echo (int)$c['id']; ?>" class="btn btn-brand btn-sm"><i class="fas fa-sitemap me-1"></i> Manage Units & Content</a>
</div></div>
<?php endforeach; ?>
<?php if (empty($tutor['courses'])): ?><p class="text-muted">No courses assigned yet. Contact admin.</p><?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
