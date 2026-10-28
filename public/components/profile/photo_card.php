<?php
// "Profile photo" card: change or delete your own photo. Expects $db, $uid; the page calls pp_handle() first.
require_once __DIR__ . '/../../../app/v1/helpers/profile_photo.php';
$ppUrl = user_photo_url($db, (int) $uid);
$ppFlash = $_SESSION['pp_flash'] ?? null;
unset($_SESSION['pp_flash']);
?>
<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="fw-semibold mb-3"><i class="fas fa-camera me-2 text-success"></i>Profile photo</h6>
    <?php if ($ppFlash): ?><div class="alert alert-<?php echo $ppFlash[0] === 'success' ? 'success' : 'danger'; ?> py-2 small"><?php echo htmlspecialchars($ppFlash[1]); ?></div><?php endif; ?>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <?php if ($ppUrl): ?>
            <img src="<?php echo htmlspecialchars($ppUrl); ?>" alt="Your photo" style="width:96px;height:96px;border-radius:50%;object-fit:cover">
        <?php else: ?>
            <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:96px;height:96px;border-radius:50%"><i class="fas fa-user fa-2x"></i></span>
        <?php endif; ?>
        <div style="min-width:0;flex:1">
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                <?php echo csrf_field(); ?><input type="hidden" name="pp_action" value="upload">
                <input type="file" name="pp_photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" style="max-width:260px" required>
                <button class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i><?php echo $ppUrl ? 'Change photo' : 'Upload photo'; ?></button>
            </form>
            <?php if ($ppUrl): ?>
            <form method="post" class="mt-2" onsubmit="return confirm('Delete your photo? It is removed from your profile and your ID card.');">
                <?php echo csrf_field(); ?><input type="hidden" name="pp_action" value="delete">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete photo</button>
            </form>
            <?php endif; ?>
            <div class="form-text">JPG, PNG or WebP, up to 5 MB.</div>
        </div>
    </div>
</div></div>
