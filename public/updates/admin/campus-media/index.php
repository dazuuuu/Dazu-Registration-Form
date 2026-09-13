<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/campus_media.php';
require_once __DIR__ . '/../../../app/v1/models/CampusMediaModel.php';

Middleware::requireAdmin();

$page_title = 'Campus Media';
$breadcrumbs = [['label' => 'Campus Media', 'active' => true]];
$model = new CampusMediaModel($pdo);
$PUBLIC = defined('PUBLIC_ROOT') ? rtrim(PUBLIC_ROOT, '/\\') : realpath(__DIR__ . '/../..');
$uploadDir = ['abs' => $PUBLIC . '/uploads/campus', 'web' => 'uploads/campus'];

function campus_media_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

function campus_media_redirect(): void
{
    header('Location: index.php');
    exit;
}

function campus_media_safe_unlink(?string $relPath, string $publicRoot): void
{
    if (!$relPath || preg_match('#^(https?:)?//#', $relPath)) {
        return;
    }
    $abs = realpath($publicRoot . '/' . ltrim($relPath, '/'));
    if ($abs && str_starts_with($abs, $publicRoot) && is_file($abs)) {
        @unlink($abs);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        campus_media_flash('error', 'Your session expired. Please try again.');
        campus_media_redirect();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        if (empty($_FILES['media']['name'])) {
            campus_media_flash('error', 'Please choose a campus photo or video.');
            campus_media_redirect();
        }

        $mediaType = str_starts_with((string) ($_FILES['media']['type'] ?? ''), 'video/') ? 'video' : 'image';
        $upload = $mediaType === 'video'
            ? upload_video($_FILES['media'], $uploadDir['abs'], $uploadDir['web'])
            : upload_image($_FILES['media'], $uploadDir['abs'], $uploadDir['web']);

        if (!$upload['ok']) {
            campus_media_flash('error', $upload['error']);
            campus_media_redirect();
        }

        $model->create([
            'media_type' => $mediaType,
            'file_path' => $upload['path'],
            'title' => trim($_POST['title'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'status' => isset($_POST['is_active']) ? 'active' : 'inactive',
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);
        campus_media_flash('success', 'Campus media uploaded.');
        campus_media_redirect();
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $model->update($id, [
                'title' => trim($_POST['title'] ?? '') ?: null,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'status' => isset($_POST['is_active']) ? 'active' : 'inactive',
            ]);
        }
        campus_media_flash('success', 'Campus media updated.');
        campus_media_redirect();
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $deleted = $model->delete($id);
            campus_media_safe_unlink($deleted['file_path'] ?? null, $PUBLIC);
        }
        campus_media_flash('success', 'Campus media removed.');
        campus_media_redirect();
    }
}

$items = $model->getAll(null, 100, 0);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<?php if ($flash): ?>
  <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?>">
    <?php echo htmlspecialchars($flash['msg'] ?? ''); ?>
  </div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Upload Campus Media</h5>
      </div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="vstack gap-3">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add">
          <div>
            <label class="form-label">Photo or video *</label>
            <input type="file" name="media" class="form-control" accept="image/*,video/mp4,video/webm,video/quicktime" required>
            <div class="form-text">Images up to 5 MB. Videos up to 50 MB.</div>
          </div>
          <div>
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" maxlength="160" placeholder="Lab session, project demo...">
          </div>
          <div>
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Optional short description"></textarea>
          </div>
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label">Sort order</label>
              <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check form-switch pb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                <label class="form-check-label" for="is_active">Active</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-upload me-1"></i> Upload Media
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Campus Photos &amp; Videos</h5>
        <span class="badge bg-secondary"><?php echo count($items); ?> items</span>
      </div>
      <div class="card-body">
        <?php if (!$items): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-camera-retro fa-3x mb-3"></i>
            <p class="mb-0">No campus media uploaded yet.</p>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($items as $item): ?>
              <?php $src = campus_media_url($item['file_path'] ?? ''); ?>
              <div class="col-md-6">
                <div class="border rounded-3 overflow-hidden h-100 bg-white">
                  <div class="bg-light" style="height: 220px; overflow: hidden;">
                    <?php if (($item['media_type'] ?? 'image') === 'video'): ?>
                      <video src="<?php echo htmlspecialchars($src); ?>" style="width: 100%; height: 100%; object-fit: cover;" controls muted playsinline preload="metadata"></video>
                    <?php else: ?>
                      <img src="<?php echo htmlspecialchars($src); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($item['title'] ?: 'Campus media'); ?>">
                    <?php endif; ?>
                  </div>
                  <div class="p-3">
                    <form method="POST" class="vstack gap-2">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                      <div class="d-flex justify-content-between align-items-center gap-2">
                        <span class="badge bg-<?php echo $item['media_type'] === 'video' ? 'dark' : 'info'; ?>"><?php echo ucfirst($item['media_type']); ?></span>
                        <div class="form-check form-switch m-0">
                          <input class="form-check-input" type="checkbox" name="is_active" id="active_<?php echo (int) $item['id']; ?>" <?php echo $item['status'] === 'active' ? 'checked' : ''; ?>>
                          <label class="form-check-label small" for="active_<?php echo (int) $item['id']; ?>">Active</label>
                        </div>
                      </div>
                      <input type="text" name="title" class="form-control form-control-sm" maxlength="160" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" placeholder="Title">
                      <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Optional description"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                      <div class="d-flex gap-2">
                        <input type="number" name="sort_order" class="form-control form-control-sm" value="<?php echo (int) $item['sort_order']; ?>" aria-label="Sort order">
                        <button type="submit" name="action" value="update" class="btn btn-sm btn-outline-primary">Save</button>
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this campus media item?')">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
