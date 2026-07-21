<?php
// public/admin/courses/categories.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CourseCategoryModel.php';

use Models\Connection;
use Models\CourseCategoryModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$categoryModel = new CourseCategoryModel($pdo);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed, please try again.';
    } elseif (isset($_POST['delete_id'])) {
        $categoryModel->delete((int) $_POST['delete_id']);
        SessionModel::setFlash('success', 'Category deleted.');
        header('Location: ' . url('admin/courses/categories.php'));
        exit();
    } else {
        $iconPath = null;
        if (!empty($_FILES['icon']['name'])) {
            $upload = upload_image(
                $_FILES['icon'],
                __DIR__ . '/../../uploads/courses/category-icons',
                url('uploads/courses/category-icons')
            );
            if (!$upload['ok']) {
                $error = $upload['error'];
            } else {
                $iconPath = $upload['path'];
            }
        }

        if (!$error) {
            $payload = [
                'name' => $_POST['name'] ?? '',
                'slug' => $_POST['slug'] ?? '',
                'sort_order' => $_POST['sort_order'] ?? 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($iconPath) {
                $payload['icon_path'] = $iconPath;
            }

            $editId = (int) ($_POST['edit_id'] ?? 0);
            if ($editId > 0) {
                $result = $categoryModel->update($editId, $payload);
            } else {
                $result = $categoryModel->create($payload);
            }

            if (!empty($result['ok'])) {
                SessionModel::setFlash('success', $editId > 0 ? 'Category updated.' : 'Category created.');
                header('Location: ' . url('admin/courses/categories.php'));
                exit();
            }
            $error = $result['error'] ?? 'Could not save category.';
        }
    }
}

try {
    $categories = $categoryModel->getAll(false);
} catch (Throwable $e) {
    $categories = [];
    $error = $error ?: 'Run migration databases/migrations/025_course_categories.sql first.';
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $editCategory = $categoryModel->getById((int) $_GET['edit']);
}

$page_title = 'Course Categories';
$breadcrumbs = [
    ['label' => 'Courses', 'url' => url('admin/courses/index.php')],
    ['label' => 'Categories', 'active' => true],
];

ob_start();
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><?php echo $editCategory ? 'Edit Category' : 'Add Category'; ?></h5></div>
      <div class="card-body">
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <?php if ($editCategory): ?>
            <input type="hidden" name="edit_id" value="<?php echo (int) $editCategory['id']; ?>">
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Category Name *</label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>"
                   placeholder="e.g. Web Development">
          </div>
          <div class="mb-3">
            <label class="form-label">Slug (optional)</label>
            <input type="text" name="slug" class="form-control"
                   value="<?php echo htmlspecialchars($editCategory['slug'] ?? ''); ?>"
                   placeholder="web-development">
          </div>
          <div class="mb-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                   value="<?php echo (int) ($editCategory['sort_order'] ?? 0); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Icon (PNG/SVG)</label>
            <input type="file" name="icon" class="form-control" accept="image/png,image/svg+xml,image/webp,image/jpeg">
            <small class="text-muted">Displayed in green on the homepage automatically.</small>
            <?php if (!empty($editCategory['icon_path'])): ?>
              <div class="mt-2">
                <img src="<?php echo htmlspecialchars(settings_asset_url($editCategory['icon_path'])); ?>" alt="" style="width:48px;height:48px;object-fit:contain;">
              </div>
            <?php endif; ?>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
              <?php echo (!$editCategory || !empty($editCategory['is_active'])) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active">Active on homepage</label>
          </div>
          <button type="submit" class="btn btn-primary"><?php echo $editCategory ? 'Update' : 'Create'; ?> Category</button>
          <?php if ($editCategory): ?>
            <a href="<?php echo url('admin/courses/categories.php'); ?>" class="btn btn-link">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Categories</h5>
        <a href="<?php echo url('admin/courses/create.php'); ?>" class="btn btn-sm btn-outline-primary">Add Course</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Icon</th>
                <th>Name</th>
                <th>Courses</th>
                <th>Order</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No categories yet.</td></tr>
            <?php else: foreach ($categories as $cat): ?>
              <tr>
                <td>
                  <?php if (!empty($cat['icon_path'])): ?>
                    <img src="<?php echo htmlspecialchars(settings_asset_url($cat['icon_path'])); ?>" alt="" style="width:36px;height:36px;object-fit:contain;">
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                <td><?php echo (int) ($cat['course_count'] ?? 0); ?></td>
                <td><?php echo (int) $cat['sort_order']; ?></td>
                <td><?php echo !empty($cat['is_active']) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>'; ?></td>
                <td class="text-end text-nowrap">
                  <a href="?edit=<?php echo (int) $cat['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category? Courses will be unassigned.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="delete_id" value="<?php echo (int) $cat['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
