<?php
// The "Apply for a certificate" panel on the student's Certificates page: what is still missing, the Apply button (grey until all four
// are true), the name check, and where the request is. Needs $db and $userId. Shows no marks.
require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';

certapp_tick($db, $userId);   // page-load backup for the scheduled job: opens the button and tells the student
$apRegs = $db->prepare("SELECT r.course_id, r.full_name, c.title FROM registrations r JOIN courses c ON c.id = r.course_id
                        WHERE r.user_id = ? AND r.status = 'approved' ORDER BY r.id DESC");
$apRegs->execute([$userId]);
$apSeen = [];
foreach ($apRegs->fetchAll(PDO::FETCH_ASSOC) as $apReg):
    $apCid = (int) $apReg['course_id'];
    if (isset($apSeen[$apCid])) continue;
    $apSeen[$apCid] = true;
    $apApp = certapp_get($db, $userId, $apCid);
    if ($apApp && $apApp['status'] === 'sent') {
        $apRel = cap_release_row($db, $userId, $apCid);
        if ($apRel && $apRel['transcript_released_at'] && empty($apRel['hidden_at'])): ?>
<div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-2"><h5 class="mb-1"><?php echo htmlspecialchars($apReg['title']); ?> · your certificate was sent</h5><span class="badge bg-success align-self-start">Sent <?php echo htmlspecialchars(date('j M Y', strtotime($apApp['sent_at'] . ' UTC'))); ?></span></div>
    <p class="small text-muted mb-2">Also ready for you:</p>
    <a class="btn btn-sm btn-outline-secondary me-1" href="<?php echo url('capstone/transcript.php?course=' . $apCid); ?>"><i class="fas fa-file-lines me-1"></i>Transcript</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo url('capstone/letter.php?course=' . $apCid); ?>" target="_blank"><i class="fas fa-envelope-open-text me-1"></i>Letter of recommendation</a>
</div></div>
        <?php endif;
        continue;
    }
    $apR = certificateReadiness($db, $userId, $apCid);
    if (!$apR['applies']) continue;
    $apT = $apR['time'];
    $apRows = ['time' => 'Course time finished', 'fees' => 'Course fees paid in full', 'capstone' => 'Capstone project passed', 'average' => 'Course average is 50 or more'];
    if (!$apR['has_capstone']) unset($apRows['capstone']);
?>
<div class="card border-0 shadow-sm rounded-4 mb-4" id="apply-<?php echo $apCid; ?>"><div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
        <div><span class="badge bg-success-subtle text-success mb-1"><?php echo htmlspecialchars($apReg['title']); ?></span>
            <h5 class="mb-0"><?php echo $apR['ready'] ? 'You can apply for your certificate' : 'Certificate'; ?></h5></div>
        <span class="badge align-self-start <?php echo $apR['ready'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $apR['ready'] ? 'Ready' : 'Not ready yet'; ?></span>
    </div>
    <p class="small text-muted mb-2">Your course is <?php echo (int) $apT['months']; ?> months. It started on <?php echo htmlspecialchars(date('j M Y', strtotime($apT['start']))); ?> and ends on <?php echo htmlspecialchars(date('j M Y', strtotime($apT['end']))); ?>.</p>
    <div class="progress mb-3" style="height:8px" role="progressbar" aria-valuenow="<?php echo (int) $apT['percent']; ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width:<?php echo (int) ($apT['finished'] ? 100 : $apT['percent']); ?>%"></div></div>
    <p class="small fw-semibold mb-1">What you need before you can apply</p>
    <ul class="list-unstyled mb-3">
        <?php foreach ($apRows as $apK => $apLabel): $apC = $apR['conditions'][$apK]; ?>
        <li class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom">
            <span><?php echo $apC['ok'] ? '<i class="fas fa-check text-success me-2"></i>' : '<i class="fas fa-triangle-exclamation text-warning me-2"></i>'; ?><strong class="small"><?php echo htmlspecialchars($apLabel); ?></strong>
                <span class="d-block small text-muted ms-4"><?php echo htmlspecialchars($apC['reason']); ?></span></span>
            <span class="badge <?php echo $apC['ok'] ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'; ?>"><?php echo $apC['ok'] ? 'Done' : 'Waiting'; ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($apApp && in_array($apApp['status'], ['pending', 'approved'], true)): ?>
        <div class="alert alert-info small mb-0"><strong>You applied on <?php echo htmlspecialchars(date('D j M, H:i', strtotime($apApp['applied_at'] . ' UTC') + 3 * 3600)); ?>.</strong>
            <?php echo $apApp['status'] === 'pending' ? 'Your tutor is writing your certificate. You will get a notice and an email when it is sent.' : 'Your tutor approved it. It will be sent to you soon.'; ?>
            <div class="mt-1">Your transcript and letter of recommendation are released with your certificate.</div></div>
    <?php elseif ($apR['ready']): ?>
        <?php if ($apApp && $apApp['status'] === 'returned'): ?><div class="alert alert-warning small"><strong>Your tutor returned your request.</strong><br><?php echo nl2br(htmlspecialchars((string) $apApp['return_note'])); ?><br>Check your name, then apply again.</div><?php endif; ?>
        <form method="POST" action="<?php echo url('students/certificates/apply.php'); ?>"><?php echo csrf_field(); ?>
            <input type="hidden" name="course_id" value="<?php echo $apCid; ?>">
            <p class="fw-semibold mb-1">Check your name</p>
            <p class="small text-muted mb-2">This is how your name will print on the certificate. Fix it now, so it does not need to be printed again.</p>
            <label class="form-label small mb-1" for="pn<?php echo $apCid; ?>">Name on the certificate</label>
            <input id="pn<?php echo $apCid; ?>" name="print_name" class="form-control mb-2" maxlength="150" required value="<?php echo htmlspecialchars($apApp['print_name'] ?? $apReg['full_name']); ?>">
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="cf<?php echo $apCid; ?>" required><label class="form-check-label small" for="cf<?php echo $apCid; ?>">My name is spelled correctly.</label></div>
            <button class="btn btn-success w-100">Apply for certificate</button>
            <div class="small text-muted mt-2">Your transcript and letter of recommendation are released at the same time as your certificate.</div>
        </form>
    <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-secondary flex-grow-1" disabled aria-disabled="true">Apply for certificate</button>
            <?php if (!$apR['conditions']['fees']['ok']): ?><a class="btn btn-success" href="<?php echo url('students/payments/index.php'); ?>">Pay the balance</a><?php endif; ?>
        </div>
        <div class="small text-muted mt-2"><i class="fas fa-circle-info me-1"></i>Finished early? Your tutor can mark your course finished, and this page opens sooner.</div>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
