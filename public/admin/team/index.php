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
    if (($_POST['action'] ?? '') === 'reorder') {
        // Drag-and-drop order from the members table (AJAX)
        header('Content-Type: application/json');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']);
            exit;
        }
        $team->reorder(array_map('intval', (array) ($_POST['ids'] ?? [])));
        echo json_encode(['ok' => true]);
        exit;
    }
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
                    'sort_order' => $existing['sort_order'] ?? (count($team->all()) + 1),
                ] + array_intersect_key($_POST, array_flip(TeamMemberModel::SOCIALS)), $id ?: null);
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
$form = $editing ?: ['id' => '', 'name' => '', 'role' => '', 'bio' => '', 'photo_path' => ''];
$socialFields = [
    'phone'     => ['Phone', 'fas fa-phone', 'tel', '+254 7XX XXX XXX'],
    'email'     => ['Email', 'fas fa-envelope', 'email', 'name@dazu.co.ke'],
    'whatsapp'  => ['WhatsApp', 'fab fa-whatsapp', 'text', '+254 7XX XXX XXX'],
    'tiktok'    => ['TikTok', 'fab fa-tiktok', 'url', 'https://tiktok.com/@…'],
    'linkedin'  => ['LinkedIn', 'fab fa-linkedin-in', 'url', 'https://linkedin.com/in/…'],
    'facebook'  => ['Facebook', 'fab fa-facebook-f', 'url', 'https://facebook.com/…'],
    'instagram' => ['Instagram', 'fab fa-instagram', 'url', 'https://instagram.com/…'],
];

ob_start();
?>
<style>
.team-photo { width: 54px; height: 54px; border-radius: 50%; object-fit: cover; background: #e9f8ed; }
.drag-handle { cursor: grab; } .sortable-ghost { opacity: .4; background: #e9f8ed; }
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
          <label class="form-label mb-1">Contacts &amp; socials <small class="text-muted">(only filled ones show on the website)</small></label>
          <?php foreach ($socialFields as $key => [$label, $icon, $type, $ph]): ?>
            <div class="input-group input-group-sm mb-2">
              <span class="input-group-text" style="width:38px;justify-content:center" title="<?php echo $label; ?>"><i class="<?php echo $icon; ?>"></i></span>
              <input class="form-control" type="<?php echo $type; ?>" name="<?php echo $key; ?>" placeholder="<?php echo htmlspecialchars($label . ' — ' . $ph); ?>" value="<?php echo htmlspecialchars((string) ($form[$key] ?? '')); ?>">
            </div>
          <?php endforeach; ?>
          <div class="mb-3"></div>
          <button class="btn btn-success" type="submit"><i class="fas fa-save"></i> <?php echo $editing ? 'Save changes' : 'Add member'; ?></button>
          <?php if ($editing): ?><a href="index.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Team members</h5><small class="text-muted" id="orderMsg"><i class="fas fa-grip-vertical me-1"></i>Drag rows to set the order on the website</small></div><div class="card-body p-0">
      <?php if (!$members): ?><p class="p-4 mb-0 text-muted">No team members yet.</p><?php else: ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th style="width:36px"></th><th>Member</th><th>Role</th><th>Socials</th><th></th></tr></thead><tbody id="teamRows">
      <?php foreach ($members as $member): ?><tr data-id="<?php echo (int) $member['id']; ?>">
        <td class="drag-handle text-muted" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></td>
        <td><div class="d-flex align-items-center gap-3"><?php if ($member['photo_path']): ?><img class="team-photo" src="<?php echo htmlspecialchars(url($member['photo_path']), ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?>"><?php else: ?><span class="team-photo team-placeholder"><?php echo htmlspecialchars(strtoupper(substr($member['name'], 0, 1))); ?></span><?php endif; ?><strong><?php echo htmlspecialchars($member['name']); ?></strong></div></td>
        <td><?php echo htmlspecialchars($member['role']); ?></td><td class="text-muted small"><?php foreach ($socialFields as $key => [$label, $icon]): if (trim((string) ($member[$key] ?? '')) !== ''): ?><i class="<?php echo $icon; ?> me-2" title="<?php echo $label; ?>"></i><?php endif; endforeach; ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int) $member['id']; ?>"><i class="fas fa-edit"></i></a><form method="post" class="d-inline" onsubmit="return confirm('Remove this team member?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td>
      </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
  const rows = document.getElementById('teamRows');
  if (!rows || !window.Sortable) return;
  const msg = document.getElementById('orderMsg');
  const token = <?php echo json_encode(csrf_token()); ?>;
  Sortable.create(rows, {
    handle: '.drag-handle', animation: 150,
    onEnd: async () => {
      const body = new URLSearchParams({action: 'reorder', csrf_token: token});
      rows.querySelectorAll('tr[data-id]').forEach(tr => body.append('ids[]', tr.dataset.id));
      msg.textContent = 'Saving order…';
      try {
        const r = await (await fetch('index.php', {method: 'POST', body})).json();
        msg.innerHTML = r.ok ? '<span class="text-success"><i class="fas fa-check me-1"></i>Order saved</span>' : '<span class="text-danger">' + r.error + '</span>';
      } catch (e) { msg.innerHTML = '<span class="text-danger">Could not save the order. Refresh and try again.</span>'; }
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
