<?php
// Tutor: My ID & signature. The passport photo goes on their staff ID card, which is issued and emailed automatically
// once the photo is in. The signature is printed on the certificates of the students they trained (and on their card).
// The page shows checks and watermarked previews of the real card and a sample certificate (components/tutor_id/panel.php).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/StaffIdService.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) $_SESSION['user_id'];
$self = url('tutor/profile/index.php');
$t = staff_tutor($db, $uid);
if (!$t) {
    http_response_code(403);
    exit('Your tutor account is not active.');
}

require_once __DIR__ . '/../../../app/v1/helpers/profile_photo.php';
pp_handle($db, $uid, $self);   // profile photo: change or delete

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['tid_flash'] = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'github') {
        // Optional: shown to students in "add your tutor as a collaborator" when their GitHub link is private.
        $gh = ltrim(trim((string) ($_POST['github_username'] ?? '')), '@');
        if ($gh !== '' && !preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/', $gh)) {
            $_SESSION['tid_flash'] = ['error', 'That is not a valid GitHub name.'];
        } else {
            try {
                $db->prepare("UPDATE tutors SET github_username = ? WHERE user_id = ?")->execute([$gh !== '' ? $gh : null, $uid]);
                $_SESSION['tid_flash'] = ['success', $gh !== '' ? 'GitHub name saved.' : 'GitHub name removed.'];
            } catch (Throwable $e) {
                $_SESSION['tid_flash'] = ['error', 'Ask the office to run System Updates first.'];
            }
        }
    } elseif (($_POST['action'] ?? '') === 'specialties') {
        // Shown on the public QR verification page of their ID card.
        $sp = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['specialties'] ?? ''))));
        try {
            $db->prepare('UPDATE tutors SET specialties = ? WHERE user_id = ?')->execute([$sp !== '' ? mb_substr($sp, 0, 255) : null, $uid]);
            $_SESSION['tid_flash'] = ['success', $sp !== '' ? 'Specialties saved.' : 'Specialties removed.'];
        } catch (Throwable $e) {
            $_SESSION['tid_flash'] = ['error', 'Ask the office to run System Updates first.'];
        }
    } elseif (($_POST['action'] ?? '') === 'email_id') {
        if (!empty($t['staff_no'])) {
            $card = staff_card($db, $t);
            $r = idc_send_pdf_email($t['email'], $t['full_name'], 'Your Dazu staff ID card', 'Your staff ID card is attached, as you asked.', idc_pdf_bytes($card), idc_pdf_filename($card));
            $sent = $r['ok'] && empty($r['skipped']);
            $_SESSION['tid_flash'] = [$sent ? 'success' : 'error', $sent ? 'Sent to ' . $t['email'] . '.' : 'The email could not be sent. Please try again later.'];
        }
    } elseif (tid_installed($db)) {
        $_SESSION['tid_flash'] = tid_handle_post($db, $t, false);   // photo, signature, edit info
    }
    header('Location: ' . $self . (($_POST['tid_action'] ?? '') === 'display' ? '#tid-card' : ''));
    exit;
}

// A tutor who already had a photo before this page existed gets their card on first visit.
if (empty($t['staff_no']) && idc_student_photo_file($db, $uid)) {
    staff_issue($db, $uid);
    $t = staff_tutor($db, $uid);
}
$page_title = 'My profile & ID';
ob_start();
require __DIR__ . '/../../components/profile/photo_card.php';
$spVal = (string) ($t['specialties'] ?? '');
?>
<form method="POST" class="card border-0 shadow-sm mb-3"><div class="card-body"><?php echo csrf_field(); ?><input type="hidden" name="action" value="specialties">
    <label class="form-label fw-semibold mb-1" for="tSpec"><i class="fas fa-star me-2 text-success"></i>My specialties</label>
    <div class="small text-muted mb-2">For example: Web development, Cybersecurity. Shown on the page people see when they scan the QR on your ID card.</div>
    <div class="input-group input-group-sm"><input id="tSpec" name="specialties" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($spVal, ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-success">Save</button></div>
</div></form>
<?php
if (!tid_installed($db)) {
    echo '<div class="alert alert-warning">This page is being updated. Ask the office to run System Updates (migration 080).</div>';
} else {
    $tidAdmin = false;
    $tidSelf = $self;
    require __DIR__ . '/../../components/tutor_id/panel.php';
    if (!empty($t['staff_no'])) {
        echo '<form method="POST" class="mt-3 text-end">' . csrf_field() . '<input type="hidden" name="action" value="email_id"><button class="btn btn-sm btn-outline-secondary"><i class="fas fa-envelope me-1"></i>Email my staff ID card to me</button></form>';
    }
}
$ghSt = $db->prepare("SELECT * FROM tutors WHERE user_id = ?");
$ghSt->execute([$uid]);
$gh = (string) (($ghSt->fetch(PDO::FETCH_ASSOC) ?: [])['github_username'] ?? '');
?>
<form method="POST" class="card-tutor p-3 mt-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="github">
    <label class="form-label fw-semibold mb-1" for="ghName">GitHub name <span class="text-muted fw-normal">(optional)</span></label>
    <div class="small text-muted mb-2">When a student's GitHub project is private, we tell them to add you as a collaborator by this name.</div>
    <div class="input-group input-group-sm" style="max-width:360px"><span class="input-group-text">@</span>
        <input id="ghName" name="github_username" class="form-control" maxlength="39" value="<?php echo htmlspecialchars($gh, ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-brand">Save</button></div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
