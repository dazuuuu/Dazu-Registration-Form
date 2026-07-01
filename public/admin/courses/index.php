<?php
// public/admin/courses/index.php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed, please try again.');
    } else {
        $id = (int) $_POST['toggle_id'];
        $active = $_POST['set_active'] === '1';
        $courseModel->setActive($id, $active);
        SessionModel::setFlash('success', $active ? 'Course activated.' : 'Course deactivated.');
    }
    header('Location: /Registration-System/public/admin/courses/index.php');
    exit();
}

$courses = $courseModel->getAll(false);

$page_title = 'Courses';
$breadcrumbs = [['label' => 'Courses', 'active' => true]];

ob_start();
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Courses</h5>
        <a href="/Registration-System/public/admin/courses/create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Course
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Abbrev.</th>
                        <th>Duration</th>
                        <th>Fee</th>
                        <th>Registrations so far</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No courses yet. Add your first one.</td></tr>
                <?php else: foreach ($courses as $c): ?>
                    <tr>
                        <td>
                            <?php if (!empty($c['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars(settings_asset_url($c['cover_image'])); ?>"
                                     alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                            <?php else: ?>
                                <div style="width:48px;height:48px;border-radius:6px;background:#eee;display:flex;align-items:center;justify-content:center;color:#aaa;">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['title']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($c['abbreviation']); ?></span></td>
                        <td><?php echo (int) $c['duration_months']; ?> month<?php echo $c['duration_months'] == 1 ? '' : 's'; ?></td>
                        <td>KSh <?php echo number_format($c['course_fee'], 0); ?></td>
                        <td><?php echo (int) $c['last_sequence']; ?></td>
                        <td>
                            <?php if ($c['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/Registration-System/public/admin/courses/edit.php?id=<?php echo (int) $c['id']; ?>"
                               class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo $c['is_active'] ? 'Deactivate' : 'Activate'; ?> this course?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="toggle_id" value="<?php echo (int) $c['id']; ?>">
                                <input type="hidden" name="set_active" value="<?php echo $c['is_active'] ? '0' : '1'; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?php echo $c['is_active'] ? 'danger' : 'success'; ?>">
                                    <i class="fas fa-<?php echo $c['is_active'] ? 'ban' : 'check'; ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';