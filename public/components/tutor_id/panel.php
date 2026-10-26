<?php
/**
 * Tutor ID & certificate preview panel, shared by tutor/profile (the tutor) and admin/tutors/id.php (the office).
 * Expects: $db, $t (staff_tutor() row), $tidAdmin (bool), $tidSelf (URL the forms post to).
 * POSTs (tid_action=…) are handled by tid_handle_post() in the page before output.
 * The ID and certificate previews are drawn in the browser from the same PDFs the system prints (pdf.js), watermarked.
 */
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uidT = (int) $t['user_id'];
$photoAbs = idc_student_photo_file($db, $uidT);
$st = $db->prepare('SELECT profile_photo_path FROM user_profiles WHERE user_id = ?');
$st->execute([$uidT]);
$photoRel = (string) $st->fetchColumn() . ($t['id_photo_path'] ?? '');
// A tutor (also in the Tutor portal preview, whose session cookie only travels to /tutor/) uses the routes under tutor/profile/; the office uses id_cards/.
$photoSrc = $tidAdmin ? url('id_cards/tutor_photo.php?u=' . $uidT) . '&v=' . md5($photoRel) : url('tutor/profile/id_photo.php') . '?v=' . md5($photoRel);   // private photo, permission-checked
$sigUri = sig_data_uri($t['signature_path']);
$photoStatus = $photoAbs ? ($t['photo_status'] === 'none' ? 'ok' : $t['photo_status']) : ($t['photo_status'] === 'requested' ? 'requested' : 'none');
$sigStatus = $sigUri ? ($t['signature_status'] === 'none' ? 'ok' : $t['signature_status']) : ($t['signature_status'] === 'requested' ? 'requested' : 'none');
$idStatus = tid_id_status($db, $t);
$pq = $tidAdmin ? '&tutor=' . $uidT : '';
$cardPdf = ($tidAdmin ? url('id_cards/tutor_preview.php?doc=card' . $pq) : url('tutor/profile/id_preview.php?doc=card')) . '&v=' . md5(($t['updated_at'] ?? '') . $photoRel . $t['signature_path']);
$certPdf = ($tidAdmin ? url('id_cards/tutor_preview.php?doc=cert' . $pq) : url('tutor/profile/id_preview.php?doc=cert')) . '&v=' . md5(($t['updated_at'] ?? '') . $t['signature_path']);
$tone = ['ok' => 'success', 'approved' => 'success', 'needs_update' => 'warning', 'requested' => 'danger', 'none' => 'secondary',
         'preview_ready' => 'success', 'sent_to_print' => 'primary', 'printed' => 'dark', 'not_created' => 'secondary'];
$pill = fn(string $k, string $label) => '<span class="badge rounded-pill bg-' . $tone[$k] . ($tone[$k] === 'warning' ? ' text-dark' : '') . '">' . $e($label) . '</span>';
$notes = fn(?string $n) => $n ? '<ul class="small mb-0 mt-1 ps-3">' . implode('', array_map(fn($l) => '<li>' . $e($l) . '</li>', array_filter(explode("\n", $n)))) . '</ul>' : '';
$nameWarn = tid_name_warning(tid_name($t));
$tidCard = $tidAdmin ? 'card' : 'card-tutor';
$flash = $_SESSION['tid_flash'] ?? null;
unset($_SESSION['tid_flash']);
?>
<style>
.tid-frame{width:120px;aspect-ratio:4/5;border:3px solid #44A024;border-radius:10px;overflow:hidden;background:#f3f5f1;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tid-frame img{width:100%;height:100%;object-fit:cover}
.tid-sig{width:200px;height:84px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid #e3e6e0}
.tid-sig img{max-width:186px;max-height:72px}
.tid-cardwrap{position:relative}
.tid-cardwrap canvas{width:100%;height:auto;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.12);background:#fff;display:block}
.tid-side{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#7b8078;font-weight:700;margin-bottom:6px}
.tid-certline{position:relative;background:#fbfbf8;border:1px dashed #d8dbd4;border-radius:10px;padding:14px 10px 8px;text-align:center;width:220px}
.tid-certline img{max-height:34px;max-width:160px;display:block;margin:0 auto -2px}
.tid-certline .ln{border-top:1.5px solid #373435;margin:2px 18px 4px}
.tid-crop{position:relative;width:100%;max-width:320px;aspect-ratio:4/5;margin:0 auto;overflow:hidden;border-radius:12px;background:#222;touch-action:none;cursor:grab}
.tid-crop img{position:absolute;left:0;top:0;transform-origin:0 0;user-select:none;-webkit-user-drag:none;max-width:none}
.tid-crop:after{content:"";position:absolute;inset:0;border:3px solid #44A024;border-radius:12px;pointer-events:none}
@media (max-width:767.98px){.tid-flip .tid-back{display:none}.tid-flip.flipped .tid-front{display:none}.tid-flip.flipped .tid-back{display:block}}
@media (min-width:768px){.tid-flipbtn{display:none}}
/* Phone: keep Save and update preview in reach, just above the bottom tab bar. */
@media (max-width:991.98px){.tid-savebar{position:sticky;bottom:calc(88px + env(safe-area-inset-bottom,0px));z-index:5;padding:8px 0 0;background:linear-gradient(to top,#fff 70%,rgba(255,255,255,0))}.tid-savebar .btn{padding:.7rem 1rem;font-size:1rem;box-shadow:0 6px 18px rgba(68,160,36,.35)}}
</style>

<?php if ($flash): ?><div class="alert alert-<?php echo ['success' => 'success', 'warning' => 'warning', 'error' => 'danger'][$flash[0]] ?? 'info'; ?> py-2"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (in_array('requested', [$photoStatus, $sigStatus], true) && $t['request_note']): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start"><i class="fas fa-bell mt-1"></i><div><strong>The office asked for a new <?php echo $photoStatus === 'requested' ? 'photo' : 'signature'; ?>.</strong> <?php echo $e($t['request_note']); ?></div></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Passport photo -->
    <div class="col-lg-6" id="tid-photo">
        <div class="<?php echo $tidCard; ?> p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1"><h6 class="fw-bold mb-0">Passport photo</h6><?php echo $pill($photoStatus, TID_PHOTO_LABELS[$photoStatus]); ?></div>
            <p class="small text-muted">A clear, front-facing photo on a plain background. It is printed on your staff ID card in this 4:5 frame.</p>
            <div class="d-flex gap-3 align-items-start flex-wrap">
                <div class="tid-frame"><?php if ($photoRel && $photoAbs): ?><img src="<?php echo $e($photoSrc); ?>" alt="Passport photo"><?php else: ?><i class="fas fa-user fa-3x text-muted"></i><?php endif; ?></div>
                <div class="flex-grow-1" style="min-width:200px">
                    <?php if ($photoStatus === 'needs_update'): ?><div class="small text-warning-emphasis bg-warning-subtle rounded p-2 mb-2" style="background:#fff7e0"><strong><i class="fas fa-triangle-exclamation me-1"></i>Needs a better photo</strong><?php echo $notes($t['photo_check_notes']); ?><div class="mt-1 text-muted">You can keep this photo, but a better one prints more clearly.</div></div>
                    <?php elseif (in_array($photoStatus, ['ok', 'approved'], true)): ?><div class="small text-success mb-2"><i class="fas fa-circle-check me-1"></i>Looks good<?php echo $photoStatus === 'approved' ? ' and approved by the office' : ''; ?>.</div><?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" action="<?php echo $e($tidSelf); ?>" class="tid-photo-form"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="photo">
                        <input type="file" name="photo" class="form-control form-control-sm mb-2 tid-photo-input" accept="image/jpeg,image/png" required>
                        <button class="btn btn-sm btn-brand btn-success"><i class="fas fa-upload me-1"></i><?php echo $photoAbs ? 'Change photo' : 'Upload photo'; ?></button>
                        <div class="form-text">JPG or PNG, at least 600 × 750 pixels, max 5 MB. After choosing, drag and zoom to place your face in the frame. It is resized to the standard ID size (900 × 1125) and kept private.</div>
                    </form>
                    <?php if ($tidAdmin && $photoAbs): ?>
                        <div class="d-flex gap-2 flex-wrap mt-2">
                            <?php if ($photoStatus !== 'approved'): ?><form method="POST" action="<?php echo $e($tidSelf); ?>"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="approve_photo"><button class="btn btn-sm btn-outline-success"><i class="fas fa-check me-1"></i>Approve photo</button></form><?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#tidReqPhoto">Request a new photo</button>
                        </div>
                        <form method="POST" action="<?php echo $e($tidSelf); ?>" class="collapse mt-2" id="tidReqPhoto"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="request"><input type="hidden" name="what" value="photo">
                            <div class="input-group input-group-sm"><input name="message" class="form-control" maxlength="255" required value="Please upload a clearer photo on a plain background."><button class="btn btn-danger">Send</button></div></form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Signature -->
    <div class="col-lg-6" id="tid-signature">
        <div class="<?php echo $tidCard; ?> p-3 p-md-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1"><h6 class="fw-bold mb-0">Signature</h6><?php echo $pill($sigStatus, TID_SIG_LABELS[$sigStatus]); ?></div>
            <p class="small text-muted">Sign in dark ink on plain white paper and upload a close, clear photo or scan. The background is removed automatically. It is printed on the certificates of the students you trained.</p>
            <div class="d-flex gap-2 flex-wrap mb-2">
                <div><div class="tid-side">On white</div><div class="tid-sig bg-white"><?php echo $sigUri ? '<img src="' . $sigUri . '" alt="Signature">' : '<span class="small text-muted">No signature yet</span>'; ?></div></div>
                <div><div class="tid-side">On dark (background removed)</div><div class="tid-sig" style="background:#373435"><?php echo $sigUri ? '<img src="' . $sigUri . '" alt="" style="filter:invert(1)">' : '<span class="small" style="color:#bbb">No signature yet</span>'; ?></div></div>
                <div><div class="tid-side">On a certificate</div><div class="tid-certline"><?php echo $sigUri ? '<img src="' . $sigUri . '" alt="">' : '<div style="height:34px"></div>'; ?><div class="ln"></div>
                    <div class="fw-bold" style="font-size:.78rem;font-family:serif"><?php echo $e(tid_name($t)); ?></div><div style="font-size:.58rem;letter-spacing:.12em;font-family:serif"><?php echo $e(mb_strtoupper(tid_role($t))); ?></div></div></div>
            </div>
            <?php if ($sigStatus === 'needs_update'): ?><div class="small rounded p-2 mb-2" style="background:#fff7e0"><strong><i class="fas fa-triangle-exclamation me-1"></i>Needs a better signature</strong><?php echo $notes($t['signature_check_notes']); ?></div>
            <?php elseif ($sigStatus === 'ok'): ?><div class="small text-success mb-2"><i class="fas fa-circle-check me-1"></i>Looks good.</div><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" action="<?php echo $e($tidSelf); ?>"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="signature">
                <div class="d-flex gap-2 flex-wrap"><input type="file" name="signature" class="form-control form-control-sm" style="max-width:260px" accept="image/jpeg,image/png" required>
                    <button class="btn btn-sm btn-brand btn-success"><i class="fas fa-signature me-1"></i><?php echo $sigUri ? 'Replace signature' : 'Upload signature'; ?></button></div>
                <?php if ($sigUri): ?><div class="form-text">Your current signature stays until the new one is saved.</div><?php endif; ?>
            </form>
            <?php if ($tidAdmin): ?>
                <button type="button" class="btn btn-sm btn-outline-danger mt-2 align-self-start" data-bs-toggle="collapse" data-bs-target="#tidReqSig">Request a new signature</button>
                <form method="POST" action="<?php echo $e($tidSelf); ?>" class="collapse mt-2" id="tidReqSig"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="request"><input type="hidden" name="what" value="signature">
                    <div class="input-group input-group-sm"><input name="message" class="form-control" maxlength="255" required value="Please sign again in dark ink on plain white paper."><button class="btn btn-danger">Send</button></div></form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Staff ID card -->
    <div class="col-12" id="tid-card">
        <div class="<?php echo $tidCard; ?> p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                <div><h6 class="fw-bold mb-0">Staff ID card</h6><div class="small text-muted">Preview only. Your ID is printed by the office.</div></div>
                <div class="d-flex gap-2 align-items-center"><?php echo $pill($idStatus, TID_ID_LABELS[$idStatus]); ?>
                    <?php if ($tidAdmin && !empty($t['edited_by_tutor'])): ?><span class="badge rounded-pill bg-info text-dark">Edited by tutor</span><?php endif; ?></div>
            </div>
            <?php if (!$photoAbs): ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><span><i class="fas fa-camera me-1"></i>This is how your ID will look. Upload your passport photo and your staff ID card is created and emailed to you straight away.</span><a href="#tid-photo" class="btn btn-sm btn-warning">Upload photo</a></div>
            <?php endif; ?>
            <?php if (true): // the preview always shows; without a photo the frame shows a placeholder ?>
                <?php if ($photoStatus === 'needs_update' || $photoStatus === 'requested'): ?><div class="alert alert-warning py-2 d-flex justify-content-between align-items-center flex-wrap gap-2"><span><i class="fas fa-triangle-exclamation me-1"></i>A better photo will make your card print more clearly.</span><a href="#tid-photo" class="btn btn-sm btn-warning">Update photo</a></div><?php endif; ?>
                <?php if ($nameWarn): ?><div class="alert alert-warning py-2 small"><i class="fas fa-text-width me-1"></i><?php echo $e($nameWarn); ?></div><?php endif; ?>
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="tid-flip" id="tidFlip">
                            <div class="row g-3">
                                <div class="col-md-6 tid-front"><div class="tid-side">Front</div><div class="tid-cardwrap"><canvas data-pdf="<?php echo $e($cardPdf); ?>" data-page="1"></canvas></div></div>
                                <div class="col-md-6 tid-back"><div class="tid-side">Back</div><div class="tid-cardwrap"><canvas data-pdf="<?php echo $e($cardPdf); ?>" data-page="2"></canvas></div></div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2 tid-flipbtn" onclick="document.getElementById('tidFlip').classList.toggle('flipped')"><i class="fas fa-rotate me-1"></i>Flip</button>
                        </div>
                        <?php if ($tidAdmin): ?>
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <?php if (!empty($t['staff_no'])): ?><a class="btn btn-sm btn-outline-dark" href="<?php echo $e(url('id_cards/pdf.php?staff=' . $uidT . '&print=1&dl=1')); ?>"><i class="fas fa-print me-1"></i>Print-ready PDF</a><?php endif; ?>
                                <form method="POST" action="<?php echo $e($tidSelf); ?>" class="d-flex gap-1"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="id_status">
                                    <select name="id_status" class="form-select form-select-sm" style="width:auto"><?php foreach (['preview_ready', 'sent_to_print', 'printed'] as $s): ?><option value="<?php echo $s; ?>" <?php echo $idStatus === $s ? 'selected' : ''; ?>><?php echo TID_ID_LABELS[$s]; ?></option><?php endforeach; ?></select>
                                    <button class="btn btn-sm btn-outline-success">Save status</button></form>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tidAdminCrop" data-src="<?php echo $e($photoAbs ? $photoSrc : ''); ?>"><i class="fas fa-crop-simple me-1"></i>Crop photo</button>
                                <form method="POST" action="<?php echo $e($tidSelf); ?>" onsubmit="return confirm('Make a new QR code? The QR on the printed card stops working. Use this only for a lost card.');"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="regen_token"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-qrcode me-1"></i>New QR (lost card)</button></form>
                            </div>
                            <form method="POST" action="<?php echo $e($tidSelf); ?>" id="tidCropForm" class="d-none"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="crop"><input type="hidden" name="crop" id="tidCropVal"><input type="hidden" name="reset" id="tidCropReset"></form>
                            <div class="small text-muted mt-2">QR scans: <?php echo (int) ($t['verify_scans'] ?? 0); ?><?php echo !empty($t['last_scanned_at']) ? ' · last ' . $e(date('j M Y, H:i', strtotime($t['last_scanned_at']))) : ''; ?><?php echo !empty($t['photo_crop']) ? ' · photo crop set by the office' : ''; ?></div>
                            <?php [$rdy, $why] = tid_ready($db, $t); ?><div class="small mt-1 <?php echo $rdy ? 'text-success' : 'text-warning-emphasis'; ?>"><?php echo $rdy ? 'Ready to print.' : 'Not ready to print: ' . $e($why) . '.'; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-5">
                        <form method="POST" action="<?php echo $e($tidSelf); ?>" class="border rounded-3 p-3"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="display">
                            <div class="fw-semibold mb-2"><i class="fas fa-pen me-1 text-success"></i>Edit info</div>
                            <label class="form-label small fw-semibold mb-1">Full name as printed</label>
                            <input name="display_name" class="form-control form-control-sm mb-1" maxlength="150" required value="<?php echo $e(tid_name($t)); ?>">
                            <div class="form-text mt-0 mb-2">First, middle and last name. A long name moves to two lines on the card.</div>
                            <label class="form-label small fw-semibold mb-1">Role / job title</label>
                            <input name="display_role" class="form-control form-control-sm mb-2" maxlength="120" required value="<?php echo $e(tid_role($t)); ?>" placeholder="Tutor, Cyber Security">
                            <label class="form-label small fw-semibold mb-1">Email on the card</label>
                            <input type="email" name="card_email" class="form-control form-control-sm mb-1" maxlength="190" value="<?php echo $e($t['card_email'] ?? ''); ?>" placeholder="<?php echo $e($t['email']); ?>">
                            <div class="form-text mt-0 mb-2">The work email printed on your ID. Leave empty to use your account email (<?php echo $e($t['email']); ?>). Your login email does not change.</div>
                            <label class="form-label small fw-semibold mb-1">ID number</label>
                            <?php if ($tidAdmin): ?><input name="staff_no" class="form-control form-control-sm mb-2 text-uppercase" maxlength="40" value="<?php echo $e($t['staff_no']); ?>">
                            <?php else: ?><div class="input-group input-group-sm mb-1"><span class="input-group-text"><i class="fas fa-lock"></i></span><input class="form-control" value="<?php echo $e($t['staff_no'] ?: 'Not created yet'); ?>" readonly disabled></div><div class="form-text mt-0 mb-2">Assigned by the office.</div><?php endif; ?>
                            <label class="form-label small fw-semibold mb-1">QR code</label>
                            <div class="input-group input-group-sm mb-3"><span class="input-group-text"><i class="fas fa-qrcode"></i></span><input class="form-control" value="System generated" readonly disabled></div>
                            <div class="tid-savebar"><button class="btn btn-sm btn-brand btn-success w-100">Save and update preview</button></div>
                            <?php if ($tidAdmin && !empty($t['edited_by_tutor'])): ?></form><form method="POST" action="<?php echo $e($tidSelf); ?>" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="tid_action" value="clear_edited"><button class="btn btn-sm btn-link p-0">Mark the tutor's edit as checked</button><?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sample certificate -->
    <div class="col-12" id="tid-certificate">
        <div class="<?php echo $tidCard; ?> p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                <div><h6 class="fw-bold mb-0">Certificate preview</h6><div class="small text-muted">A sample in the Dazu certificate design with your printed name, role and signature. Check that they look right.</div></div>
                <?php echo $pill($sigUri ? 'ok' : 'none', $sigUri ? 'Sample ready' : 'Needs signature'); ?>
            </div>
            <div class="row g-3 align-items-center">
                <div class="col-lg-8"><div class="tid-cardwrap"><canvas data-pdf="<?php echo $e($certPdf); ?>" data-page="1"></canvas></div></div>
                <div class="col-lg-4 small text-muted">
                    <p class="mb-2">The student, course and dates are placeholders. Your name and role come from <strong>Edit info</strong>, so changing them updates both previews.</p>
                    <?php if (!$sigUri): ?><p class="text-warning-emphasis mb-2"><i class="fas fa-signature me-1"></i>Upload your signature to see it on the certificate.</p><?php endif; ?>
                    <a class="btn btn-sm btn-outline-success" href="<?php echo $e(url($tidAdmin ? 'admin/certificates/index.php' : 'tutor/students/index.php')); ?>"><i class="fas fa-award me-1"></i><?php echo $tidAdmin ? 'Certificates' : "My students' certificates"; ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tidAdmin):
        $h = $db->prepare('SELECT h.*, u.username FROM tutor_id_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.tutor_id = ? ORDER BY h.id DESC LIMIT 30');
        $h->execute([(int) $t['id']]); $h = $h->fetchAll(PDO::FETCH_ASSOC); ?>
    <div class="col-12"><div class="card p-3"><h6 class="fw-bold">History</h6>
        <?php if (!$h): ?><div class="small text-muted">No changes yet.</div><?php else: ?>
        <div class="table-responsive"><table class="table table-sm small mb-0"><thead><tr><th>When</th><th>Who</th><th>What</th><th>Was</th><th>Now</th></tr></thead><tbody>
        <?php foreach ($h as $r): ?><tr><td class="text-nowrap"><?php echo $e(date('j M Y, H:i', strtotime($r['changed_at']))); ?></td><td><?php echo $e($r['username'] ?? '—'); ?></td><td><?php echo $e(str_replace('_', ' ', $r['field'])); ?></td><td><?php echo $e($r['old_value']); ?></td><td><?php echo $e($r['new_value']); ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></div></div>
    <?php endif; ?>
</div>

<!-- Crop tool (4:5): drag and zoom, then the cropped photo is uploaded. -->
<div class="modal fade" id="tidCropModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Place your face in the frame</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="tid-crop" id="tidCrop"><img id="tidCropImg" alt=""></div>
        <input type="range" class="form-range mt-3" id="tidZoom" min="1" max="4" step="0.01" value="1">
        <div class="small text-muted text-center">Drag to move · slide to zoom. Face and shoulders should fill the frame.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="tidCropOk">Use this photo</button></div>
</div></div></div>

<script src="<?php echo $e(url('assets/vendor/pdfjs/pdf.min.js')); ?>"></script>
<script>
(function () {
    // Previews: draw the real (watermarked) PDFs onto canvases. No download link is shown.
    if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = <?php echo json_encode(url('assets/vendor/pdfjs/pdf.worker.min.js')); ?>;
        var docs = {};
        document.querySelectorAll('canvas[data-pdf]').forEach(function (cv) {
            var src = cv.dataset.pdf;
            docs[src] = docs[src] || pdfjsLib.getDocument({ url: src, withCredentials: true }).promise;
            docs[src].then(function (pdf) { return pdf.getPage(+cv.dataset.page); }).then(function (page) {
                var vp = page.getViewport({ scale: 1 }), scale = Math.min(4, 1400 / vp.width), v = page.getViewport({ scale: scale });
                cv.width = v.width; cv.height = v.height;
                return page.render({ canvasContext: cv.getContext('2d'), viewport: v }).promise;
            }).catch(function (err) {
                // Say what went wrong, not only "refresh": the server answers 401 / 403 / 404 with a reason.
                var s = err && err.status, msg = s === 401 ? 'Your session has ended. Log in again to see your preview.'
                    : s === 403 ? 'This preview is not available for this account.'
                    : s === 404 ? 'Your tutor profile was not found. Ask the office to check your account.'
                    : 'The preview could not be drawn. Refresh the page. If it still fails, tell the office.';
                cv.replaceWith(Object.assign(document.createElement('div'), { className: 'small text-muted p-3 border rounded', textContent: msg }));
            });
            cv.addEventListener('contextmenu', function (ev) { ev.preventDefault(); });
        });
    }

    // Crop tool for the passport photo (Bootstrap's script loads at the end of the page, so start on load).
    window.addEventListener('load', function () {
    var input = document.querySelector('.tid-photo-input') || document.createElement('input'), modalEl = document.getElementById('tidCropModal');
    if (!modalEl || !window.bootstrap) return;
    var modal = new bootstrap.Modal(modalEl), box = document.getElementById('tidCrop'), img = document.getElementById('tidCropImg'),
        zoom = document.getElementById('tidZoom'), st = { x: 0, y: 0, base: 1, z: 1 }, drag = null, picked = null, confirmed = false;
    function fit() {
        var W = box.clientWidth, H = box.clientHeight;
        st.base = Math.max(W / img.naturalWidth, H / img.naturalHeight); st.z = 1; zoom.value = 1;
        st.x = (W - img.naturalWidth * st.base) / 2; st.y = (H - img.naturalHeight * st.base) / 2; draw();
    }
    function clamp() {
        var W = box.clientWidth, H = box.clientHeight, s = st.base * st.z;
        st.x = Math.min(0, Math.max(W - img.naturalWidth * s, st.x)); st.y = Math.min(0, Math.max(H - img.naturalHeight * s, st.y));
    }
    function draw() { clamp(); img.style.transform = 'translate(' + st.x + 'px,' + st.y + 'px) scale(' + (st.base * st.z) + ')'; }
    input.addEventListener('change', function () {
        var f = input.files[0]; if (!f || confirmed) { confirmed = false; return; }
        picked = f; img.onload = function () { modal.show(); setTimeout(fit, 200); }; img.src = URL.createObjectURL(f);
    });
    zoom.addEventListener('input', function () {
        var W = box.clientWidth / 2, H = box.clientHeight / 2, old = st.base * st.z;
        st.z = +zoom.value; var s = st.base * st.z;
        st.x = W - (W - st.x) * s / old; st.y = H - (H - st.y) * s / old; draw();
    });
    box.addEventListener('pointerdown', function (ev) { drag = { x: ev.clientX - st.x, y: ev.clientY - st.y }; box.setPointerCapture(ev.pointerId); });
    box.addEventListener('pointermove', function (ev) { if (!drag) return; st.x = ev.clientX - drag.x; st.y = ev.clientY - drag.y; draw(); });
    box.addEventListener('pointerup', function () { drag = null; });
    var adminBtn = document.getElementById('tidAdminCrop'), adminMode = false;
    if (adminBtn && adminBtn.dataset.src) adminBtn.addEventListener('click', function () {
        adminMode = true; img.onload = function () { modal.show(); setTimeout(fit, 200); }; img.src = adminBtn.dataset.src;
    });
    document.getElementById('tidCropOk').addEventListener('click', function () {
        if (adminMode) {
            var k = st.base * st.z;
            document.getElementById('tidCropVal').value = JSON.stringify({ x: -st.x / k / img.naturalWidth, y: -st.y / k / img.naturalHeight, w: box.clientWidth / k / img.naturalWidth });
            document.getElementById('tidCropForm').submit(); return;
        }
        var s = st.base * st.z, sx = -st.x / s, sy = -st.y / s, sw = box.clientWidth / s, sh = box.clientHeight / s;
        var outW = Math.min(1200, Math.round(sw)),   // never enlarged, so the size check stays honest
            outH = Math.round(outW * 5 / 4), c = document.createElement('canvas');
        c.width = outW; c.height = outH;
        var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, outW, outH); g.drawImage(img, sx, sy, sw, sh, 0, 0, outW, outH);
        c.toBlob(function (b) {
            var dt = new DataTransfer(); dt.items.add(new File([b], (picked.name || 'photo').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
            confirmed = true; input.files = dt.files; modal.hide();
            var f = document.querySelector('.tid-photo-form'), prev = f.querySelector('.tid-crop-prev') || f.insertBefore(Object.assign(document.createElement('img'), { className: 'tid-crop-prev rounded border mb-2 d-block', style: 'width:96px;aspect-ratio:4/5;object-fit:cover' }), f.firstChild.nextSibling);
            prev.src = c.toDataURL('image/jpeg', .8);
        }, 'image/jpeg', 0.92);
    });
    });
})();
</script>
