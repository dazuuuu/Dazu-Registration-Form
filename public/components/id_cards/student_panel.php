<?php
// "Student ID card" panel at the top of the student's My Documents page.
// Expects: $db, $userId, $docsSelf. Requests are POSTed back with idc_action=request (see students/documents/index.php).
require_once __DIR__ . '/../../../app/v1/services/IdCardService.php';

try {
    $idcEnrolments = idc_student_enrolments($db, $userId);
} catch (Throwable $ex) {
    $idcEnrolments = [];
}
if (!$idcEnrolments) {
    return;
}
$idcE = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$idcHasPhoto = (bool) idc_student_photo_file($db, $userId);
?>
<style>
.idp{border-radius:20px;overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.05),0 8px 24px rgba(15,23,42,.06);margin-bottom:1rem}
.idp-head{display:flex;align-items:center;gap:.75rem;padding:1rem 1.1rem;background:linear-gradient(120deg,#373435 0%,#373435 55%,#44A024 55.2%,#44A024 62%,#373435 62.2%);color:#fff}
.idp-head i{font-size:1.25rem;color:#8fd16d}
.idp-head h6{margin:0;font-weight:700;letter-spacing:.01em;color:#fff}
.idp-head small{opacity:.75}
.idp-item{display:flex;gap:1rem;align-items:center;padding:1rem 1.1rem;border-top:1px solid #f1f5f9;flex-wrap:wrap}
.idp-mini{width:86px;aspect-ratio:85.6/54;border-radius:8px;flex:none;position:relative;overflow:hidden;background:#fff;border:1px solid #e5e7eb;box-shadow:0 2px 6px rgba(0,0,0,.08)}
.idp-mini:before{content:"";position:absolute;right:-12px;top:-14px;width:58px;height:34px;background:#373435;transform:rotate(28deg);box-shadow:-4px 4px 0 #44A024}
.idp-mini:after{content:"";position:absolute;left:0;right:0;bottom:0;height:9px;background:#44A024}
.idp-mini span{position:absolute;left:7px;top:17px;width:15px;height:19px;border:2px solid #44A024;border-radius:3px;background:#e6e8e3}
.idp-mini b{position:absolute;left:27px;top:20px;width:34px;height:4px;border-radius:2px;background:#373435}
.idp-mini em{position:absolute;left:27px;top:28px;width:22px;height:3px;border-radius:2px;background:#c5cac0}
.idp-body{flex:1;min-width:200px}
.idp-course{font-weight:600;color:#0f172a}
.idp-sub{font-size:.82rem;color:#64748b}
.idp-steps{display:flex;gap:.3rem;margin-top:.55rem}
.idp-steps i{height:5px;flex:1;border-radius:99px;background:#e5e7eb;max-width:46px}
.idp-steps i.on{background:#44A024}
.idp-pick{display:flex;gap:.6rem;align-items:center;background:#ecf7e6;border:1px solid #cfe9c1;color:#24610f;border-radius:14px;padding:.6rem .8rem;margin-top:.6rem;font-size:.88rem}
.idp-pick i{font-size:1.1rem}
.idp-actions{display:flex;gap:.4rem;flex-wrap:wrap}
</style>
<div class="idp">
    <div class="idp-head"><i class="fas fa-id-card"></i><div><h6>Student ID card</h6><small>Download your card, track printing and pickup</small></div></div>
    <?php foreach ($idcEnrolments as $en):
        $card = $en['card'];
        $st = $card['status'] ?? null;
        $step = ['requested' => 1, 'generated' => 2, 'ready' => 3, 'collected' => 4][$st ?? ''] ?? 0;
    ?>
        <div class="idp-item">
            <div class="idp-mini" aria-hidden="true"><span></span><b></b><em></em></div>
            <div class="idp-body">
                <div class="idp-course"><?php echo $idcE($en['course_name']); ?></div>
                <div class="idp-sub">
                    <?php echo $idcE($en['registration_no'] ?: 'Registration number pending'); ?> ·
                    <?php if ($st && $st !== 'declined'): ?><?php echo idc_status_badge($st); ?>
                    <?php elseif ($st === 'declined'): ?><span class="badge bg-secondary">Request declined</span>
                    <?php else: ?>No ID card yet<?php endif; ?>
                </div>
                <?php if ($step): ?>
                    <div class="idp-steps" title="Requested → Generated → Ready → Collected"><?php for ($i = 1; $i <= 4; $i++): ?><i class="<?php echo $i <= $step ? 'on' : ''; ?>"></i><?php endfor; ?></div>
                <?php endif; ?>
                <?php if ($st === 'ready' && $card['pickup_location']): ?>
                    <div class="idp-pick"><i class="fas fa-location-dot"></i><div><strong>Ready — go pick it up</strong> at our <?php echo $idcE(IDC_LOCATIONS[$card['pickup_location']] ?? ''); ?> branch. Sign the back when you collect it.</div></div>
                <?php elseif ($st === 'generated'): ?>
                    <div class="idp-sub mt-2">Your printed card is being prepared. We'll notify you when it's ready for pickup.</div>
                <?php elseif ($st === 'requested'): ?>
                    <div class="idp-sub mt-2">Request sent <?php echo $idcE(date('j M Y', strtotime($card['requested_at'] ?: $card['created_at']))); ?>. The office will approve it and email you your card.</div>
                <?php elseif ($st === 'declined' && $card['admin_note']): ?>
                    <div class="idp-sub mt-2">“<?php echo $idcE($card['admin_note']); ?>”</div>
                <?php endif; ?>
            </div>
            <div class="idp-actions">
                <?php $idcDue = idc_reg_fee_due($db, (int) $en['id']); if ($idcDue > 0): ?>
                    <span class="small text-warning fw-semibold"><i class="fas fa-lock me-1"></i>Pay the registration fee (KSh <?php echo number_format($idcDue); ?> left) to get your ID card</span>
                <?php elseif (in_array($st, ['generated', 'ready', 'collected'], true)): ?>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill" target="_blank" href="<?php echo $idcE(url('id_cards/pdf.php?id=' . (int) $card['id'])); ?>"><i class="fas fa-eye me-1"></i>View</a>
                    <a class="btn btn-sm btn-success rounded-pill" href="<?php echo $idcE(url('id_cards/pdf.php?id=' . (int) $card['id'] . '&dl=1')); ?>"><i class="fas fa-download me-1"></i>Download</a>
                <?php elseif ((!$st || $st === 'declined') && !$idcHasPhoto): ?>
                    <a class="btn btn-sm btn-outline-success rounded-pill" href="#passport-photo"><i class="fas fa-camera me-1"></i>Add passport photo to request</a>
                <?php elseif (!$st || $st === 'declined'): ?>
                    <button class="btn btn-sm btn-success rounded-pill" data-bs-toggle="collapse" data-bs-target="#idpReq<?php echo (int) $en['id']; ?>"><i class="fas fa-paper-plane me-1"></i>Request ID card</button>
                <?php endif; ?>
            </div>
            <?php if ((!$st || $st === 'declined') && $idcHasPhoto && $idcDue <= 0): ?>
                <form method="POST" action="<?php echo $idcE($docsSelf); ?>" class="collapse w-100" id="idpReq<?php echo (int) $en['id']; ?>">
                    <?php echo csrf_field(); ?><input type="hidden" name="idc_action" value="request"><input type="hidden" name="registration_id" value="<?php echo (int) $en['id']; ?>">
                    <?php if ($en['study_mode'] !== 'physical'): ?>
                        <div class="small text-muted mb-2"><i class="fas fa-info-circle me-1"></i>ID cards are mainly for physical students. As an online student, the office decides whether to issue one.</div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" name="note" maxlength="255" class="form-control form-control-sm flex-grow-1" style="min-width:200px" placeholder="Note for the office (optional)">
                        <button class="btn btn-sm btn-success rounded-pill px-3">Send request</button>
                    </div>
                    <div class="small text-muted mt-2">Make sure your passport photo below is clear — it's printed on the card.</div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
