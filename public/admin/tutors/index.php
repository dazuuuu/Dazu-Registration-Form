<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutors = $tutorModel->listAll();

$page_title = 'Tutors';
$breadcrumbs = [['label' => 'Tutors', 'active' => true]];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Tutors</h5>
    <a href="create.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Tutor</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($tutors)): ?>
            <p class="text-muted p-4 mb-0">No tutors yet. <a href="create.php">Create the first tutor</a>.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Courses</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tutors as $t): ?>
                    <tr>
                        <td>
                            <?php if ($t['photo_path']): ?>
                                <img src="<?php echo htmlspecialchars($t['photo_path']); ?>" width="36" height="36" class="rounded-circle me-2" style="object-fit:cover;">
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($tutorModel->fullName($t)); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($t['email']); ?></td>
                        <td><?php echo count($t['courses']); ?> course(s)</td>
                        <td><span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td><a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
