<?php
// My Profile: the intern may change their photo, phone and emergency contact. Fields HR manages are read-only.
require __DIR__ . '/../_boot.php';

$self = url('intern/profile/index.php');
if (($_POST['action'] ?? '') === 'delete_photo' && intern_post_ok()) {
    in_file_delete($intern['photo_path']);
    $db->prepare('UPDATE interns SET photo_path = NULL WHERE id = ?')->execute([(int) $intern['id']]);
    require_once __DIR__ . '/../../../app/v1/helpers/profile_photo.php';
    pp_delete($db, (int) $intern['user_id']);
    SessionModel::setFlash('success', 'Your photo was deleted.');
    header('Location: ' . $self);
    exit;
} elseif (intern_post_ok()) {
    $phone = mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 30) ?: null;
    $db->prepare('UPDATE interns SET phone = ?, emergency_name = ?, emergency_phone = ? WHERE id = ?')
       ->execute([$phone, mb_substr(trim((string) ($_POST['emergency_name'] ?? '')), 0, 120) ?: null, mb_substr(trim((string) ($_POST['emergency_phone'] ?? '')), 0, 30) ?: null, (int) $intern['id']]);
    $db->prepare('UPDATE user_profiles SET phone = ? WHERE user_id = ?')->execute([$phone, (int) $intern['user_id']]);
    $msg = 'Profile saved.';
    if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $r = in_store_file($_FILES['photo'], 'photos', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
        if ($r['ok']) {
            in_file_delete($intern['photo_path']);
            $db->prepare('UPDATE interns SET photo_path = ? WHERE id = ?')->execute([$r['path'], (int) $intern['id']]);
        } else {
            $msg = 'Profile saved, but the photo was not: ' . $r['error'];
        }
    }
    in_audit($db, (int) $intern['id'], 'profile_updated');
    SessionModel::setFlash('success', in_e($msg));
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$ro = fn($label, $value) => '<div class="col-md-6"><label class="form-label small text-muted mb-1">' . $label . '</label><div class="form-control bg-light">' . (in_e($value) ?: '—') . '</div></div>';
$period = in_period($intern);
$page_title = 'My Profile';
ob_start();
?>
<form method="POST" id="ipDelPhoto" onsubmit="return confirm('Delete your photo?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_photo"></form>
<form method="POST" enctype="multipart/form-data" class="ip-card" style="max-width:900px"><?php echo csrf_field(); ?>
    <div class="d-flex align-items-center gap-3 mb-4">
        <?php if ($intern['photo_path']): ?><img src="<?php echo in_e(in_file_url('photo', (int) $intern['id'])); ?>" alt="" style="width:84px;height:84px;border-radius:50%;object-fit:cover">
        <?php else: ?><span class="as-ava" style="width:84px;height:84px;font-size:1.6rem"><?php echo in_e(strtoupper(mb_substr($intern['full_name'], 0, 1))); ?></span><?php endif; ?>
        <div><h3><?php echo in_e($intern['full_name']); ?></h3><div class="ip-muted">Intern<?php echo $intern['department'] ? ' · ' . in_e($intern['department']) : ''; ?></div>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mt-2" style="max-width:280px">
            <?php if ($intern['photo_path']): ?><button type="submit" form="ipDelPhoto" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-trash me-1"></i>Delete photo</button><?php endif; ?></div>
    </div>
    <div class="row g-3">
        <?php echo $ro('Full name', $intern['full_name']), $ro('Email', $intern['email']); ?>
        <div class="col-md-6"><label class="form-label small text-muted mb-1">Phone</label><input name="phone" value="<?php echo in_e($intern['phone']); ?>" class="form-control" maxlength="30"></div>
        <?php echo $ro('University / college', $intern['institution']), $ro('Course', $intern['course']), $ro('Year', $intern['year_of_study']), $ro('Student ID', $intern['student_id_no']), $ro('Department', $intern['department']),
                   $ro('Internship period', $period['start'] ? date('j M Y', $period['start']) . ' → ' . ($period['end'] ? date('j M Y', $period['end']) : '…') : ''), $ro('Supervisor', $intern['supervisor_name']); ?>
        <div class="col-md-6"><label class="form-label small text-muted mb-1">Emergency contact name</label><input name="emergency_name" value="<?php echo in_e($intern['emergency_name']); ?>" class="form-control" maxlength="120"></div>
        <div class="col-md-6"><label class="form-label small text-muted mb-1">Emergency contact phone</label><input name="emergency_phone" value="<?php echo in_e($intern['emergency_phone']); ?>" class="form-control" maxlength="30"></div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-4"><span class="ip-muted">Grey fields are managed by HR. Ask the office to change them.</span><button class="btn btn-success">Save profile</button></div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
