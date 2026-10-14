<?php
// Tutor: My ID & signature. The passport photo goes on their staff ID card, which is issued and emailed automatically
// once the photo is in. The signature is printed on the certificates of the students they trained (and on their card).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/StaffIdService.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) $_SESSION['user_id'];
$self = url('tutor/profile/index.php');
$t = staff_tutor($db, $uid);
if (!$t) {
    http_response_code(403);
    exit('Your tutor account is not active.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please refresh and try again.');
    } else {
        switch ($_POST['action'] ?? '') {
            case 'photo':
                $r = docs_save_photo($db, $uid, $_FILES['photo'] ?? []);
                if (!$r['ok']) { SessionModel::setFlash('error', htmlspecialchars($r['error'])); break; }
                $i = staff_issue($db, $uid);
                SessionModel::setFlash('success', 'Passport photo saved.' . (!empty($i['issued']) ? ' Your staff ID card is ready' . (!empty($i['emailed']) ? ' and was emailed to you.' : '.') : ''));
                break;
            case 'signature':
                $r = sig_store($_FILES['signature'] ?? []);
                if (!$r['ok']) { SessionModel::setFlash('error', htmlspecialchars($r['error'])); break; }
                $db->prepare('UPDATE tutors SET signature_path = ? WHERE id = ?')->execute([$r['path'], (int) $t['id']]);
                sig_delete($t['signature_path']);
                if (!empty($t['staff_no'])) staff_issue($db, $uid);
                SessionModel::setFlash('success', 'Signature saved. It will appear on the certificates of students you trained.');
                break;
            case 'email_id':
                if (empty($t['staff_no'])) break;
                $card = staff_card($db, $t);
                $r = idc_send_pdf_email($t['email'], $t['full_name'], 'Your Dazu staff ID card', 'Your staff ID card is attached, as you asked.', idc_pdf_bytes($card), idc_pdf_filename($card));
                $sent = $r['ok'] && empty($r['skipped']);
                SessionModel::setFlash($sent ? 'success' : 'error', $sent ? 'Sent to ' . htmlspecialchars($t['email']) . '.' : 'The email could not be sent. Download the card instead.');
                break;
        }
    }
    header('Location: ' . $self);
    exit;
}

// A tutor who already had a photo before this page existed gets their card on first visit.
if (empty($t['staff_no']) && idc_student_photo_file($db, $uid)) {
    staff_issue($db, $uid);
    $t = staff_tutor($db, $uid);
}
$st = $db->prepare('SELECT profile_photo_path FROM user_profiles WHERE user_id = ?');
$st->execute([$uid]);
$photo = (string) $st->fetchColumn();
$sig = sig_data_uri($t['signature_path']);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$page_title = 'My ID & signature';
ob_start();
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-tutor p-3 p-md-4 h-100">
            <h6 class="fw-bold mb-1">Passport photo</h6>
            <p class="small text-muted">A clear, front-facing photo on a plain background. It is printed on your staff ID card.</p>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <?php if ($photo): ?><img src="<?php echo $e(settings_asset_url($photo)); ?>" alt="Your passport photo" class="rounded border" style="width:84px;height:100px;object-fit:cover">
                <?php else: ?><div class="rounded border d-flex align-items-center justify-content-center text-muted" style="width:84px;height:100px"><i class="fas fa-user fa-2x"></i></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="flex-grow-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="photo">
                    <input type="file" name="photo" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp" required>
                    <button class="btn btn-sm btn-brand"><i class="fas fa-upload me-1"></i><?php echo $photo ? 'Change photo' : 'Upload photo'; ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-tutor p-3 p-md-4 h-100">
            <h6 class="fw-bold mb-1">Signature</h6>
            <p class="small text-muted">Sign in dark ink on plain white paper and upload a close, clear photo or scan. The background is removed automatically. It is printed on the certificates of the students you trained.</p>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <div class="rounded border d-flex align-items-center justify-content-center bg-white" style="width:180px;height:80px">
                    <?php if ($sig): ?><img src="<?php echo $sig; ?>" alt="Your signature" style="max-width:170px;max-height:70px">
                    <?php else: ?><span class="small text-muted">No signature yet</span><?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" class="flex-grow-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="signature">
                    <input type="file" name="signature" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png" required>
                    <button class="btn btn-sm btn-brand"><i class="fas fa-signature me-1"></i><?php echo $sig ? 'Replace signature' : 'Upload signature'; ?></button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card-tutor p-3 p-md-4">
            <h6 class="fw-bold mb-1">Staff ID card</h6>
            <?php if (empty($t['staff_no'])): ?>
                <p class="small text-muted mb-0">Upload your passport photo and your staff ID card is created and emailed to you straight away.</p>
            <?php else: ?>
                <p class="small text-muted">Staff no. <strong><?php echo $e($t['staff_no']); ?></strong> · issued <?php echo $e(date('j M Y', strtotime($t['id_issued_at']))); ?>
                    <?php echo $t['id_emailed_at'] ? ' · emailed to ' . $e($t['email']) : ''; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo $e(url('id_cards/pdf.php?staff=' . $uid)); ?>"><i class="fas fa-eye me-1"></i>View</a>
                    <a class="btn btn-sm btn-brand" href="<?php echo $e(url('id_cards/pdf.php?staff=' . $uid . '&dl=1')); ?>"><i class="fas fa-download me-1"></i>Download</a>
                    <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="email_id">
                        <button class="btn btn-sm btn-outline-dark"><i class="fas fa-envelope me-1"></i>Email it to me</button></form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
