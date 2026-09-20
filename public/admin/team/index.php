<?php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/bootstrap.php';

use Models\TeamMemberModel;

$team = new TeamMemberModel($pdo);
$page_title = 'Team Management';
$breadcrumbs = [['label' => 'Team', 'active' => true]];
$editing = isset($_GET['edit']) ? $team->find((int) $_GET['edit']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Your form session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $member = $id ? $team->find($id) : null;
        if (!$member) {
            $_SESSION['flash']['error'] = 'That team member could not be found.';
        } else {
            try {
                $deleted = $team->delete($id);
            } catch (Throwable $e) {
                $deleted = false;
            }
            if ($deleted) {
                if (!empty($member['photo_path'])) {
                    $photo = public_path($member['photo_path']);
                    if (is_file($photo)) {
                        @unlink($photo);
                    }
                }
                $_SESSION['flash']['success'] = 'Team member removed.';
            } else {
                $_SESSION['flash']['error'] = 'Unable to remove this team member. Please try again.';
            }
        }
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        if ($name === '' || $role === '') {
            $_SESSION['flash']['error'] = 'Name and role are required.';
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $existing = $id ? $team->find($id) : null;
            if ($id && !$existing) {
                $_SESSION['flash']['error'] = 'That team member could not be found.';
                header('Location: index.php');
                exit;
            }
            $photoPath = $existing['photo_path'] ?? null;
            $previousPhotoPath = $photoPath;
            if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = upload_image($_FILES['photo'], public_path('uploads/team'), 'uploads/team');
                if (!$upload['ok']) {
                    $_SESSION['flash']['error'] = $upload['error'] ?? 'Unable to upload the photo.';
                    header('Location: index.php' . ($id ? '?edit=' . $id : ''));
                    exit;
                }
                $photoPath = $upload['path'];
            }
            try {
                $team->save([
                    'name' => $name,
                    'role' => $role,
                    'bio' => $_POST['bio'] ?? '',
                    'photo_path' => $photoPath,
                    'sort_order' => $_POST['sort_order'] ?? 0,
                ], $id ?: null);
                if ($photoPath !== $previousPhotoPath && !empty($previousPhotoPath)) {
                    $oldPhoto = public_path($previousPhotoPath);
                    if (is_file($oldPhoto)) {
                        @unlink($oldPhoto);
                    }
                }
                $_SESSION['flash']['success'] = $id ? 'Team member updated.' : 'Team member added.';
            } catch (Throwable $e) {
                if ($photoPath !== $previousPhotoPath && !empty($photoPath)) {
                    $newPhoto = public_path($photoPath);
                    if (is_file($newPhoto)) {
                        @unlink($newPhoto);
                    }
                }
                $_SESSION['flash']['error'] = 'Unable to save this team member. Please try again.';
            }
        }
    }
    header('Location: index.php');
    exit;
}

$members = $team->all();
$form = $editing ?: ['id' => '', 'name' => '', 'role' => '', 'bio' => '', 'photo_path' => '', 'sort_order' => count($members) + 1];

ob_start();
?>
<style>
.team-photo { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; background: #e9f8ed; }
.team-placeholder { display:inline-flex; align-items:center; justify-content:center; color:#198754; font-weight:800; }
</style>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><?php echo $editing ? 'Edit team member' : 'Add team member'; ?></h5></div>
      <div class="card-body">
        <form method="post" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
          <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?php echo htmlspecialchars($form['name']); ?>"></div>
          <div class="mb-3"><label class="form-label">Role</label><input class="form-control" name="role" required placeholder="Founder, Senior Developer, Tutor" value="<?php echo htmlspecialchars($form['role']); ?>"></div>
          <div class="mb-3"><label class="form-label">Bio</label><textarea class="form-control" rows="4" name="bio"><?php echo htmlspecialchars($form['bio'] ?? ''); ?></textarea></div>
          <div class="mb-3"><label class="form-label">Photo <small class="text-muted">(optional)</small></label><input class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"></div>
          <div class="mb-3"><label class="form-label">Display order</label><input class="form-control" type="number" min="0" name="sort_order" value="<?php echo (int) $form['sort_order']; ?>"></div>
          <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> <?php echo $editing ? 'Save changes' : 'Add member'; ?></button>
          <?php if ($editing): ?><a href="index.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header"><h5 class="mb-0">Team members</h5></div><div class="card-body p-0">
      <?php if (!$members): ?><p class="p-4 mb-0 text-muted">No team members yet.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Member</th><th>Role</th><th>Order</th><th></th></tr></thead><tbody>
      <?php foreach ($members as $member): ?><tr>
        <td><div class="d-flex align-items-center gap-3"><?php if ($member['photo_path']): ?><img class="team-photo" src="<?php echo htmlspecialchars(url($member['photo_path']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?>"><?php else: ?><span class="team-photo team-placeholder"><?php echo htmlspecialchars(strtoupper(substr($member['name'], 0, 1))); ?></span><?php endif; ?><strong><?php echo htmlspecialchars($member['name']); ?></strong></div></td>
        <td><?php echo htmlspecialchars($member['role']); ?></td><td><?php echo (int) $member['sort_order']; ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int) $member['id']; ?>"><i class="fas fa-edit"></i></a><form method="post" class="d-inline" onsubmit="return confirm('Remove this team member?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td>
      </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
