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
<style>
    .tutor-cards { display: none; }
    .tutor-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border-bottom: 1px solid #e0e0e0;
    }
    .tutor-card:last-child { border-bottom: none; }
    .tutor-card-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .tutor-card-avatar-placeholder {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #f0f0f0;
        color: #999;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .tutor-card-body { min-width: 0; flex: 1; }
    .tutor-card-name { font-weight: 600; }
    .tutor-card-email {
        font-size: 0.85rem;
        color: #666;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .tutor-card-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
        flex-wrap: wrap;
    }
    @media (max-width: 767.98px) {
        .tutor-table-wrap { display: none; }
        .tutor-cards { display: block; }
    }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Tutors</h5>
    <a href="create.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Tutor</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($tutors)): ?>
            <p class="text-muted p-4 mb-0">No tutors yet. <a href="create.php">Create the first tutor</a>.</p>
        <?php else: ?>
        <div class="table-responsive tutor-table-wrap">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Students</th><th>Courses</th><th>Status</th><th></th></tr></thead>
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
                        <td><?php echo count($t['students']); ?> student(s)</td>
                        <td><?php echo count($t['courses']); ?> course(s)</td>
                        <td><span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td><a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="tutor-cards">
            <?php foreach ($tutors as $t): ?>
                <div class="tutor-card">
                    <?php if ($t['photo_path']): ?>
                        <img src="<?php echo htmlspecialchars($t['photo_path']); ?>" class="tutor-card-avatar" alt="">
                    <?php else: ?>
                        <div class="tutor-card-avatar-placeholder"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div class="tutor-card-body">
                        <div class="tutor-card-name"><?php echo htmlspecialchars($tutorModel->fullName($t)); ?></div>
                        <div class="tutor-card-email"><?php echo htmlspecialchars($t['email']); ?></div>
                        <div class="tutor-card-meta">
                            <span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'secondary'; ?>"><?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?></span>
                            <span class="text-muted small"><?php echo count($t['students']); ?> student(s), <?php echo count($t['courses']); ?> course(s)</span>
                            <a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary ms-auto">Manage</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
