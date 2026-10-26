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

        $featuredImagePath = null;
        if (!$error && !empty($_FILES['featured_image']['name'])) {
            $uploadImg = upload_image(
                $_FILES['featured_image'],
                __DIR__ . '/../../uploads/courses/category-featured',
                url('uploads/courses/category-featured')
            );
            if (!$uploadImg['ok']) {
                $error = $uploadImg['error'];
            } else {
                $featuredImagePath = $uploadImg['path'];
            }
        }

        if (!$error) {
            $payload = [
                'name'        => $_POST['name'] ?? '',
                'slug'        => $_POST['slug'] ?? '',
                'description' => trim($_POST['description'] ?? ''),
                'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
                'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($iconPath) {
                $payload['icon_path'] = $iconPath;
            }
            if ($featuredImagePath) {
                $payload['featured_image'] = $featuredImagePath;
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
    $error = $error ?: 'Apply the pending system update first (System Updates).';
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
  <!-- LEFT: Add / Edit form -->
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
            <label class="form-label">Slug <span class="text-muted">(optional — auto-generated)</span></label>
            <input type="text" name="slug" class="form-control"
                   value="<?php echo htmlspecialchars($editCategory['slug'] ?? ''); ?>"
                   placeholder="web-development">
          </div>

          <div class="mb-3">
            <label class="form-label">Icon (PNG/SVG)</label>
            <input type="file" name="icon" class="form-control" accept="image/png,image/svg+xml,image/webp,image/jpeg">
            <small class="text-muted">Displayed on the homepage category cards.</small>
            <?php if (!empty($editCategory['icon_path'])): ?>
              <div class="mt-2">
                <img src="<?php echo htmlspecialchars(settings_asset_url($editCategory['icon_path'])); ?>" alt="" style="width:48px;height:48px;object-fit:contain;">
              </div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="form-label">Description / Summary <span class="text-muted">(for SEO &amp; Sharing)</span></label>
            <textarea name="description" class="form-control" rows="3" placeholder="Brief summary for social preview cards..."><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
            <small class="text-muted">Shown in WhatsApp and social media link previews when shared.</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Featured Image <span class="text-muted">(category hero &amp; share preview)</span></label>
            <input type="file" name="featured_image" class="form-control" accept="image/png,image/webp,image/jpeg">
            <small class="text-muted">Used as the big background image on the category page.</small>
            <?php if (!empty($editCategory['featured_image'])): ?>
              <div class="mt-2">
                <img src="<?php echo htmlspecialchars(settings_asset_url($editCategory['featured_image'])); ?>" alt="Featured Preview" style="max-width:200px;max-height:110px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
              </div>
            <?php endif; ?>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
              <?php echo (!$editCategory || !empty($editCategory['is_active'])) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active">Active (visible on site)</label>
          </div>

          <button type="submit" class="btn btn-primary"><?php echo $editCategory ? 'Update' : 'Create'; ?> Category</button>
          <?php if ($editCategory): ?>
            <a href="<?php echo url('admin/courses/categories.php'); ?>" class="btn btn-link">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <!-- RIGHT: Drag-and-drop ordering -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Menu Order</h5>
          <small class="text-muted">Drag categories up or down to rearrange them in the navigation menu.</small>
        </div>
        <button id="saveSortBtn" class="btn btn-success btn-sm d-none" onclick="saveOrder()">
          <i class="fas fa-save me-1"></i> Save Order
        </button>
      </div>
      <div class="card-body p-0">
        <?php if (empty($categories)): ?>
          <div class="text-center text-muted py-5">No categories yet. Add one using the form.</div>
        <?php else: ?>
        <ul id="catSortList" class="list-unstyled mb-0">
          <?php foreach ($categories as $i => $cat): ?>
            <?php
            $ordinalLabel = ($i + 1) . match($i) { 0 => 'st', 1 => 'nd', 2 => 'rd', default => 'th' };
            ?>
            <li class="cat-sort-item d-flex align-items-center gap-3 px-3 py-3 border-bottom"
                data-id="<?php echo (int) $cat['id']; ?>"
                draggable="true">

              <!-- Drag handle -->
              <span class="drag-handle text-muted" title="Drag to reorder">
                <i class="fas fa-grip-vertical"></i>
              </span>

              <!-- Position badge -->
              <span class="position-badge badge fw-bold rounded-pill"
                    style="min-width:48px;text-align:center;font-size:11px;background:#e8f5e9;color:#2e7d32;">
                <?php echo htmlspecialchars($ordinalLabel); ?>
              </span>

              <!-- Icon -->
              <?php if (!empty($cat['icon_path'])): ?>
                <img src="<?php echo htmlspecialchars(settings_asset_url($cat['icon_path'])); ?>"
                     alt="" style="width:32px;height:32px;object-fit:contain;flex-shrink:0;">
              <?php else: ?>
                <span class="text-muted" style="width:32px;height:32px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#f1f5f9;border-radius:8px;font-size:14px;">
                  <i class="fas fa-layer-group"></i>
                </span>
              <?php endif; ?>

              <!-- Name & course count -->
              <div class="flex-grow-1" style="min-width:0;">
                <div class="fw-bold text-truncate" style="font-size:13px;"><?php echo htmlspecialchars($cat['name']); ?></div>
                <div class="text-muted" style="font-size:11px;">
                  <?php echo (int)($cat['course_count'] ?? 0); ?> course<?php echo ($cat['course_count'] ?? 0) != 1 ? 's' : ''; ?>
                </div>
              </div>

              <!-- Status -->
              <?php echo !empty($cat['is_active'])
                ? '<span class="badge bg-success" style="font-size:10px;flex-shrink:0;">Active</span>'
                : '<span class="badge bg-secondary" style="font-size:10px;flex-shrink:0;">Hidden</span>'; ?>

              <!-- Actions -->
              <div class="d-flex gap-1 flex-shrink-0">
                <a href="<?php echo url('courses/category.php?slug=' . rawurlencode($cat['slug'])); ?>"
                   target="_blank" class="btn btn-sm btn-outline-success p-1" title="View page"
                   style="width:28px;height:28px;line-height:20px;text-align:center;">
                  <i class="fas fa-external-link-alt" style="font-size:11px;"></i>
                </a>
                <a href="?edit=<?php echo (int) $cat['id']; ?>"
                   class="btn btn-sm btn-outline-secondary p-1" title="Edit"
                   style="width:28px;height:28px;line-height:20px;text-align:center;">
                  <i class="fas fa-edit" style="font-size:11px;"></i>
                </a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this category? Courses under it will be unassigned.');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="delete_id" value="<?php echo (int) $cat['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete"
                          style="width:28px;height:28px;line-height:20px;text-align:center;">
                    <i class="fas fa-trash" style="font-size:11px;"></i>
                  </button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.cat-sort-item { transition: background 0.15s ease; user-select: none; }
.cat-sort-item:hover { background: #f8fafc; }
.drag-handle { opacity: 0.35; font-size: 16px; cursor: grab; transition: opacity 0.15s; }
.cat-sort-item:hover .drag-handle { opacity: 0.85; }
.cat-sort-item.dragging { opacity: 0.45; background: #f0f9ff !important; }
.cat-sort-item.drag-over { background: #f0fdf4 !important; border-top: 2px solid #86efac !important; }
@keyframes pulse-green { 0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,.45); } 50% { box-shadow: 0 0 0 7px rgba(34,197,94,0); } }
.pulse-btn { animation: pulse-green 1.3s ease infinite; }
</style>

<script>
(function () {
  const list = document.getElementById('catSortList');
  const saveBtn = document.getElementById('saveSortBtn');
  if (!list || !saveBtn) return;

  const csrfToken = '<?php echo csrf_token(); ?>';
  const sortUrl   = '<?php echo url('admin/courses/categories_sort.php'); ?>';

  function ordinal(n) {
    const v = n % 100;
    const suffix = ['th','st','nd','rd'];
    return n + (suffix[(v - 20) % 10] || suffix[v] || suffix[0]);
  }

  function refreshBadges() {
    list.querySelectorAll('.cat-sort-item').forEach((item, i) => {
      const badge = item.querySelector('.position-badge');
      if (badge) badge.textContent = ordinal(i + 1);
    });
  }

  let dragEl = null;

  list.querySelectorAll('.cat-sort-item').forEach(item => {
    item.addEventListener('dragstart', e => {
      dragEl = item;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => item.classList.add('dragging'), 0);
    });

    item.addEventListener('dragend', () => {
      item.classList.remove('dragging');
      list.querySelectorAll('.cat-sort-item').forEach(i => i.classList.remove('drag-over'));
      dragEl = null;
      refreshBadges();
      saveBtn.classList.remove('d-none');
      saveBtn.classList.add('pulse-btn');
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Order';
    });

    item.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      if (!dragEl || item === dragEl) return;
      list.querySelectorAll('.cat-sort-item').forEach(i => i.classList.remove('drag-over'));
      item.classList.add('drag-over');
      const rect = item.getBoundingClientRect();
      if (e.clientY < rect.top + rect.height / 2) {
        list.insertBefore(dragEl, item);
      } else {
        list.insertBefore(dragEl, item.nextSibling);
      }
    });

    item.addEventListener('dragleave', () => item.classList.remove('drag-over'));
    item.addEventListener('drop', e => { e.preventDefault(); item.classList.remove('drag-over'); });
  });

  window.saveOrder = function () {
    const ids = Array.from(list.querySelectorAll('.cat-sort-item')).map(el => el.dataset.id);
    saveBtn.disabled = true;
    saveBtn.classList.remove('pulse-btn');
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

    fetch(sortUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&ids=' + ids.join(',')
    })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        saveBtn.innerHTML = '<i class="fas fa-check me-1"></i> Saved!';
        saveBtn.classList.remove('btn-success');
        saveBtn.classList.add('btn-outline-success');
        setTimeout(() => {
          saveBtn.classList.add('d-none');
          saveBtn.classList.remove('btn-outline-success');
          saveBtn.classList.add('btn-success');
          saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Order';
          saveBtn.disabled = false;
        }, 2200);
      } else {
        saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Error — try again';
        saveBtn.disabled = false;
      }
    })
    .catch(() => {
      saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i> Error — try again';
      saveBtn.disabled = false;
    });
  };
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
