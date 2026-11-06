<?php
// The text under the name on one certificate: (1) the default wording (≤ 200, fields {course} {duration} {student_name})
// and (2) the tutor's extra lines (≤ 160). Admin and the student's own tutor may edit both; the name, registration number
// and certificate number are locked. Live preview; nothing is kept until Save. An issued certificate stays exactly as issued:
// saved text shows on it only after the admin presses Reissue. ?id=<certificate id>
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../app/v1/models/AssignmentModel.php';
require_once __DIR__ . '/../../app/v1/helpers/certificate_pdf.php';
require_once __DIR__ . '/../../app/v1/helpers/certificate_v2.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cert = $id ? (new \Models\CertificateModel($db))->getCertificateById($id) : null;
$deny = function (string $why = 'Not allowed.') { http_response_code(403); exit($why); };
if (!$cert) { http_response_code(404); exit('Certificate not found.'); }

// Role check first: the office, or the tutor of this student.
$isAdmin = in_array($role, ['admin', 'superadmin'], true);
if (!$isAdmin) {
    if ($role !== 'tutor') $deny();
    $am = new \Models\AssignmentModel($db);
    $t = $am->tutorByUser($me);
    if (!$t || empty($cert['user_id']) || !$am->isTutorStudent((int) $t['id'], (int) $cert['user_id'])) $deny();
}
$hasCols = true;
try { $db->query('SELECT wording_text, issued_snapshot FROM certificates LIMIT 1'); } catch (Throwable $e) { $hasCols = false; }
if (!$hasCols) exit('An administrator must run System Updates (migration 100) first.');
$issued = in_array($cert['status'], ['issued', 'collected'], true);
$rn = $db->prepare('SELECT registration_no FROM registrations WHERE id = ?');
$rn->execute([(int) $cert['registration_id']]);
$cert['registration_no'] = (string) $rn->fetchColumn();
$self = url('certificates/text.php?id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if (($_POST['action'] ?? '') === 'preview') { http_response_code(403); exit('Refresh the page.'); }
        $_SESSION['ctext_flash'] = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'preview') {
        // The real certificate with the unsaved boxes (never saved). Issued or not, it shows how it will look.
        header('Content-Type: text/html; charset=UTF-8');
        $c = $cert; unset($c['issued_snapshot']); $c['status'] = 'being_prepared';
        echo cv2_html(cv2_live($db, $c, ['wording' => (string) ($_POST['wording'] ?? ''), 'extra' => (string) ($_POST['extra'] ?? '')]));
        exit;
    } elseif (($_POST['action'] ?? '') === 'reissue' && $isAdmin) {
        $r = cert_reissue($db, $id, $me);
        $_SESSION['ctext_flash'] = $r['ok'] ? ['success', 'Reissued with the saved text and the current settings. Same certificate number and QR code.'] : ['danger', $r['error']];
    } else {
        $r = cv2_save_text($db, $id, (string) ($_POST['wording'] ?? ''), (string) ($_POST['extra'] ?? ''), $me);
        $_SESSION['ctext_flash'] = $r['ok'] ? ['success', $issued ? 'Saved. The issued certificate does not change until the office presses Reissue.' : 'Saved.'] : ['danger', $r['error']];
    }
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['ctext_flash'] ?? null;
unset($_SESSION['ctext_flash']);
$set = cv2_settings($db);
$wording = trim((string) ($cert['wording_text'] ?? '')) ?: $set['wording'];
$extra = cv2_clean_extra((string) ($cert['taught_skills'] ?? ''));
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$back = $isAdmin ? url(($role === 'superadmin' ? 'superadmin' : 'admin') . '/certificates/index.php') : url('tutor/students/view.php?id=' . (int) ($cert['user_id'] ?? 0));
header('X-Robots-Tag: noindex');
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Certificate text · <?php echo $e($cert['certificate_no']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<?php require __DIR__ . '/../components/certificates/text_editor_css.php'; ?>
</head><body>
<div class="wrap">
  <a class="back" href="<?php echo $e($back); ?>">‹ Back</a>
  <h1>Certificate text · <?php echo $e($cert['certificate_no']); ?></h1>
  <?php if ($flash): ?><div class="alert <?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
  <?php if ($issued): ?><div class="alert info">This certificate is issued and stays exactly as it was issued. Text you save here shows on it only after the office presses <b>Reissue</b><?php echo $cert['issued_snapshot'] ? '' : ' (it was issued with the earlier layout)'; ?>.</div><?php endif; ?>
  <div class="grid">
    <form method="POST" id="tf" class="panel"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>">
      <div class="locked"><div><span>Student</span><b><?php echo $e($cert['snapshot_name']); ?></b></div><div><span>Registration No.</span><b><?php echo $e($cert['registration_no'] ?? '—'); ?></b></div><div><span>Certificate No.</span><b><?php echo $e($cert['certificate_no']); ?></b></div>
        <small>Locked: nobody can change these.</small></div>
      <?php $box = function (string $name, string $label, string $hint, string $val, int $max, int $rows) use ($e) { ?>
        <label class="lbl" for="<?php echo $name; ?>"><?php echo $label; ?></label>
        <textarea id="<?php echo $name; ?>" name="<?php echo $name; ?>" rows="<?php echo $rows; ?>" maxlength="<?php echo $max; ?>" data-max="<?php echo $max; ?>"><?php echo $e($val); ?></textarea>
        <div class="meta"><span class="hint"><?php echo $hint; ?></span><span class="count" data-for="<?php echo $name; ?>"></span></div>
      <?php }; ?>
      <?php $box('wording', '1 · Default wording', 'Fields: <code>{course}</code> <code>{a_duration}</code> <code>{duration}</code> <code>{student_name}</code>. {a_duration} writes "an 18-month" or "a 1-month" for you.', $wording, CV2_WORDING_MAX, 3); ?>
      <?php $box('extra', '2 · Tutor\'s extra lines', 'Optional, about two lines.', $extra, CV2_EXTRA_MAX, 2); ?>
      <div class="bar"><span id="dirty" class="dirty" hidden>Unsaved changes</span><button class="btn" name="action" value="save">Save</button>
        <?php if ($isAdmin && $issued): ?><button class="btn o" name="action" value="reissue" formnovalidate onclick="return confirm('Reissue this certificate with the saved text and the current settings? The old version is kept in the history.')">Reissue</button><?php endif; ?></div>
    </form>
    <div class="panel"><div class="small">Live preview</div><div class="pv" id="wrap"><iframe id="pv" title="Certificate preview"></iframe></div></div>
  </div>
</div>
<?php require __DIR__ . '/../components/certificates/text_editor_js.php'; ?>
</body></html>
