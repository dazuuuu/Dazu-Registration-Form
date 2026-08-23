<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';

use Models\PartnerModel;

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$partners = new PartnerModel($pdo);
$page_title = 'Partners';
$breadcrumbs = [['label' => 'Partners', 'active' => true]];
$editing = isset($_GET['edit']) ? $partners->find((int) $_GET['edit']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Your form session expired. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id ? $partners->find($id) : null;
        if ($row) {
            $partners->delete($id);
            if (!empty($row['image_path'])) {
                $img = public_path($row['image_path']);
                if (is_file($img)) {
                    @unlink($img);
                }
            }
            SessionModel::setFlash('success', 'Partner removed.');
        }
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id ? $partners->find($id) : null;
        $imagePath = $existing['image_path'] ?? null;
        $previousPath = $imagePath;

        if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = upload_image($_FILES['image'], public_path('uploads/partners'), 'uploads/partners');
            if (!$upload['ok']) {
                SessionModel::setFlash('error', $upload['error'] ?? 'Could not upload image.');
                header('Location: index.php' . ($id ? '?edit=' . $id : ''));
                exit;
            }
            $imagePath = $upload['path'];
        }

        if (!$imagePath) {
            SessionModel::setFlash('error', 'Partner logo/image is required.');
        } else {
            try {
                $partners->save([
                    'title' => $_POST['title'] ?? '',
                    'image_path' => $imagePath,
                    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                    'is_active' => !empty($_POST['is_active']),
                ], $id ?: null);
                if ($imagePath !== $previousPath && !empty($previousPath)) {
                    $old = public_path($previousPath);
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                SessionModel::setFlash('success', $id ? 'Partner updated.' : 'Partner added.');
            } catch (Throwable $e) {
                SessionModel::setFlash('error', 'Could not save partner. Run System Update 039 if the table is missing.');
            }
        }
    }
    header('Location: index.php');
    exit;
}

try {
    $rows = $partners->all();
} catch (Throwable $e) {
    $rows = [];
}
$form = $editing ?: ['id' => '', 'title' => '', 'image_path' => '', 'sort_order' => count($rows) + 1, 'is_active' => 1];

ob_start();
?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><?php echo $editing ? 'Edit partner' : 'Add partner'; ?></h5></div>
      <div class="card-body">
        <p class="text-muted small">Upload partner logos for the homepage partners section. Title is optional.</p>
        <form method="post" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
          <div class="mb-3">
            <label class="form-label">Title <span class="text-muted">(optional)</span></label>
            <input class="form-control" name="title" placeholder="Partner name" value="<?php echo htmlspecialchars($form['title'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Logo / image <?php echo $editing ? '' : '*'; ?></label>
            <?php if (!empty($form['image_path'])): ?>
              <div class="mb-2 p-2 border rounded bg-light text-center">
                <img src="<?php echo htmlspecialchars(settings_asset_url($form['image_path'])); ?>" alt="" style="max-height:72px;max-width:100%;object-fit:contain;">
              </div>
            <?php endif; ?>
            <input class="form-control" type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" <?php echo $editing ? '' : 'required'; ?>>
            <?php if ($editing): ?><small class="text-muted">Upload a new image to replace the current one.</small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Display order</label>
            <input class="form-control" type="number" min="0" name="sort_order" value="<?php echo (int) ($form['sort_order'] ?? 0); ?>">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="partner_active" value="1" <?php echo !empty($form['is_active']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="partner_active">Show on website</label>
          </div>
          <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> <?php echo $editing ? 'Save changes' : 'Add partner'; ?></button>
          <?php if ($editing): ?><a href="index.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Partners on homepage</h5>
        <span class="badge bg-secondary"><?php echo count($rows); ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Logo</th><th>Title</th><th>Order</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="5" class="text-muted p-4">No partners yet. Add logos to show them on the homepage.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td><img src="<?php echo htmlspecialchars(settings_asset_url($row['image_path'])); ?>" alt="" style="height:40px;max-width:100px;object-fit:contain;"></td>
                <td><?php echo htmlspecialchars($row['title'] ?? '—'); ?></td>
                <td><?php echo (int) $row['sort_order']; ?></td>
                <td><span class="badge bg-<?php echo !empty($row['is_active']) ? 'success' : 'secondary'; ?>"><?php echo !empty($row['is_active']) ? 'Active' : 'Hidden'; ?></span></td>
                <td class="text-end">
                  <a href="?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Remove this partner?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
