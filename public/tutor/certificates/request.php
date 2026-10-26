<?php
// Tutor: one certificate request. See the certificate with a PREVIEW mark, write ONLY the lines under the student's name (up to 300 letters),
// then Return to student (with a note), Approve only, or Approve and send. After sending: Send again, or issue a new version to fix a mistake.
// Only the tutor of that student can open it (and only that student's request). Marks are never shown.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$a = certapp_get_for_tutor($pdo, $id, $tutorId);
if (!$a) asg_back('index.php', null, 'Request not found.');
$self = 'request.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    switch ($_POST['action'] ?? '') {
        case 'save':
            $r = certapp_save_lines($pdo, $id, $tutorId, $me, (string) ($_POST['lines'] ?? ''));
            asg_back($self, $r['ok'] ? 'Your lines were saved.' : null, $r['ok'] ? null : $r['error']);
        case 'redraft':
            $t = cert_assigned_tutor($pdo, (int) $a['student_user_id']);
            $draft = certapp_draft_lines($pdo, (int) $a['course_id'], $me, certificateReadiness($pdo, (int) $a['student_user_id'], (int) $a['course_id'])['has_capstone']);
            $r = certapp_save_lines($pdo, $id, $tutorId, $me, $draft);
            asg_back($self, $r['ok'] ? 'The draft was written again.' : null, $r['ok'] ? null : $r['error']);
        case 'return':
            $r = certapp_return($pdo, $id, $tutorId, $me, (string) ($_POST['note'] ?? ''));
            CertificateNotifier::flush();
            $r['ok'] ? asg_back('index.php?tab=returned', 'Returned to the student. They were told.') : asg_back($self, null, $r['error']);
        case 'approve':
        case 'approve_send':
            // The lines in the box are saved first, so what the tutor sees is what is printed.
            if (isset($_POST['lines'])) {
                $r = certapp_save_lines($pdo, $id, $tutorId, $me, (string) $_POST['lines']);
                if (!$r['ok']) asg_back($self, null, $r['error']);
            }
            $r = certapp_approve($pdo, $id, $tutorId, $me, $_POST['action'] === 'approve_send');
            CertificateNotifier::flush();
            if (!$r['ok']) asg_back($self, null, $r['error']);
            asg_back($self, $_POST['action'] === 'approve_send'
                ? 'Approved and sent. It is on the student\'s Certificates page, and ' . ($r['email_ok'] ?? false ? 'the PDF was emailed.' : 'the email was not sent: ' . ($r['email'] ?? '')) . ' Their transcript and letter are released with it.'
                : 'Approved. It has not been sent yet.');
        case 'send':
            $r = certapp_send($pdo, $id, $tutorId, $me);
            CertificateNotifier::flush();
            $r['ok'] ? asg_back($self, 'Sent. It is on the student\'s Certificates page.' . (($r['email_ok'] ?? false) ? ' The PDF was emailed.' : ' The email was not sent: ' . ($r['email'] ?? ''))) : asg_back($self, null, $r['error']);
        case 'resend':
            $r = certapp_resend($pdo, $id, $tutorId, $me);
            $r['ok'] ? asg_back($self, 'Sent again by email.') : asg_back($self, null, (string) $r['error']);
        case 'new_version':
            $r = certapp_new_version($pdo, $id, $tutorId, $me, (string) ($_POST['lines'] ?? ''), (string) ($_POST['print_name'] ?? ''));
            $r['ok'] ? asg_back($self, 'Version ' . $r['version'] . ' was issued and sent. The old one is marked Replaced.') : asg_back($self, null, $r['error']);
    }
    asg_back($self);
}

$ready = certificateReadiness($pdo, (int) $a['student_user_id'], (int) $a['course_id']);
$cn = $ready['conditions'];
$cert = null;
if ($a['certificate_id']) {
    $q = $pdo->prepare('SELECT id, certificate_no, version, status FROM certificates WHERE id = ?');
    $q->execute([(int) $a['certificate_id']]);
    $cert = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
$editable = $a['status'] === 'pending';
$chip = ['pending' => ['Pending', 'st-extension'], 'returned' => ['Returned', 'st-returned'], 'approved' => ['Approved', 'st-progress'], 'sent' => ['Sent', 'st-graded']][$a['status']];
$page_title = 'Certificate request';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Certificate requests</a>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div><div class="fw-bold"><?php echo asg_e($a['student_name']); ?></div><div class="small text-muted"><?php echo asg_e($a['course_title']); ?> · Applied <?php echo asg_e(AssignmentModel::local($a['applied_at'], 'D j M')); ?></div></div>
            <div class="text-end"><span class="asg-chip <?php echo $chip[1]; ?>"><?php echo $chip[0]; ?></span></div>
        </div>
        <div class="small text-muted mb-2">Preview. The lines in the box on the right are the only part you can change.</div>
        <iframe title="Certificate preview" src="preview.php?id=<?php echo $id; ?>&amp;v=<?php echo urlencode((string) $a['updated_at']); ?>" style="width:100%;aspect-ratio:297/210;border:1px solid #ddd;border-radius:8px;background:#f6f6f6"></iframe>
        <div class="small text-muted mt-2">Printed name: <strong><?php echo asg_e($a['print_name']); ?></strong> (checked by the student).<?php echo $cert ? ' Certificate ' . asg_e($cert['certificate_no']) . ($cert['version'] > 1 ? ' (version ' . (int) $cert['version'] . ')' : '') . '.' : ' The number is given when you approve.'; ?></div>
    </div>
</div>
<div class="col-lg-5">
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold mb-1">Lines under the certificate</h6>
        <div class="small text-muted mb-2">We wrote a first draft from the course and its subjects. Change it as you like. It continues the sentence "…programme of study in the <?php echo asg_e($a['course_title']); ?> at Dazu Training Institute".</div>
        <form method="POST" id="linesForm"><?php echo csrf_field(); ?>
            <textarea name="lines" id="lines" rows="5" maxlength="300" class="form-control" <?php echo $editable ? '' : 'readonly'; ?>><?php echo asg_e((string) $a['lines_text']); ?></textarea>
            <div class="d-flex justify-content-between small text-muted mt-1"><span><span id="cnt"><?php echo mb_strlen((string) $a['lines_text']); ?></span> of 300 letters</span>
                <?php if ($editable): ?><button name="action" value="redraft" class="btn btn-link btn-sm p-0" formnovalidate>Write the draft again</button><?php endif; ?></div>
            <?php if ($editable): ?><button name="action" value="save" class="btn btn-outline-dark btn-sm mt-2">Save lines and refresh the preview</button><?php endif; ?>
            <?php if ($editable || $a['status'] === 'approved'): ?>
            <hr>
            <h6 class="fw-bold">Checks</h6>
            <?php foreach (['time' => 'Course time finished', 'fees' => 'Fees paid 100%', 'capstone' => 'Capstone passed', 'average' => 'Average 50 or more'] as $k => $lbl): if (!isset($cn[$k]) || ($k === 'capstone' && !$ready['has_capstone'])) continue; ?>
            <div class="d-flex justify-content-between border-bottom py-1 small"><span><?php echo asg_e($lbl); ?></span><span class="badge <?php echo $cn[$k]['ok'] ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'; ?>"><?php echo $cn[$k]['ok'] ? 'Done' : 'Waiting'; ?></span></div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-between border-bottom py-1 small"><span>Name checked by the student</span><span class="badge bg-success-subtle text-success">Done</span></div>
            <div class="small text-muted mt-1">Marks are not shown on the certificate.</div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($editable): ?>
    <div class="card-tutor p-3 p-md-4">
        <form method="POST" onsubmit="return confirm('Approve and send this certificate to the student now?')"><?php echo csrf_field(); ?>
            <textarea name="lines" class="d-none" id="linesCopy"><?php echo asg_e((string) $a['lines_text']); ?></textarea>
            <button name="action" value="approve_send" class="btn btn-brand w-100 mb-2" <?php echo $ready['ready'] ? '' : 'disabled'; ?>>Approve and send certificate</button>
            <button name="action" value="approve" class="btn btn-outline-dark w-100 mb-2" <?php echo $ready['ready'] ? '' : 'disabled'; ?>>Approve only</button>
            <?php if (!$ready['ready']): ?><div class="small text-danger mb-2">Not possible now: <?php echo asg_e($ready['reason']); ?></div><?php endif; ?>
        </form>
        <form method="POST"><?php echo csrf_field(); ?>
            <label class="form-label small fw-semibold mb-0">Return to student (a note is needed)</label>
            <input name="note" class="form-control form-control-sm mb-2" maxlength="500" placeholder="For example: please check the spelling of your second name" required>
            <button name="action" value="return" class="btn btn-outline-danger btn-sm w-100">Return to student</button>
        </form>
        <div class="small text-muted mt-2">Sending puts it on the student's Certificates page and emails it with the PDF. The transcript and the letter of recommendation are released with it.</div>
    </div>
    <?php elseif ($a['status'] === 'approved'): ?>
    <div class="card-tutor p-3 p-md-4"><form method="POST" onsubmit="return confirm('Send this certificate to the student now?')"><?php echo csrf_field(); ?>
        <button name="action" value="send" class="btn btn-brand w-100">Send certificate</button></form></div>
    <?php elseif ($a['status'] === 'returned'): ?>
    <div class="card-tutor p-3 p-md-4 small"><strong>Returned to the student</strong><br><?php echo nl2br(asg_e((string) $a['return_note'])); ?><br><span class="text-muted">The student can apply again. It will come back to you as Pending.</span></div>
    <?php elseif ($a['status'] === 'sent'): ?>
    <div class="card-tutor p-3 p-md-4">
        <div class="alert alert-success small">Sent <?php echo asg_e(AssignmentModel::local($a['sent_at'], 'D j M Y, H:i')); ?> EAT<?php echo (int) $a['send_count'] > 1 ? ' · ' . (int) $a['send_count'] . ' times' : ''; ?>.</div>
        <form method="POST" class="mb-3"><?php echo csrf_field(); ?><button name="action" value="resend" class="btn btn-outline-dark btn-sm">Send again by email</button></form>
        <form method="POST" onsubmit="return confirm('Issue a new version? The old certificate will be marked Replaced.')"><?php echo csrf_field(); ?>
            <h6 class="fw-bold">Fix a mistake</h6>
            <div class="small text-muted mb-2">A mistake is fixed by a new version. The old certificate is marked Replaced and its number is never used again.</div>
            <label class="form-label small mb-0">Name</label><input name="print_name" class="form-control form-control-sm mb-2" maxlength="150" value="<?php echo asg_e($a['print_name']); ?>" required>
            <label class="form-label small mb-0">Lines</label><textarea name="lines" rows="4" maxlength="300" class="form-control form-control-sm mb-2"><?php echo asg_e((string) $a['lines_text']); ?></textarea>
            <button name="action" value="new_version" class="btn btn-outline-danger btn-sm">Issue a new version and send</button>
        </form>
    </div>
    <?php endif; ?>
</div>
</div>
<script>
(function () {
    var t = document.getElementById('lines'), c = document.getElementById('cnt'), copy = document.getElementById('linesCopy');
    if (!t) return;
    function sync() { c.textContent = t.value.length; if (copy) copy.value = t.value; }
    t.addEventListener('input', sync); sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
