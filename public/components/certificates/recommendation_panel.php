<?php
// Letter of recommendation card on the certificate edit page (admin + superadmin). Expects $db, $cert.
// Admins and superadmins are the only ones who can edit the text; tutors can generate and view.
require_once __DIR__ . '/../../../app/v1/services/RecommendationService.php';
$letter = lor_get_by_reg($db, (int) $cert['registration_id']);
$lorUrl = $letter ? url('letters/recommendation.php?id=' . (int) $letter['id']) : '';
?>
<div class="card shadow-sm border-0 rounded-4 mb-4" id="lor">
    <div class="card-body p-4">
        <h5 class="mb-1"><i class="fas fa-file-signature text-success me-2"></i>Letter of recommendation</h5>
        <?php if ($cert['status'] === 'withdrawn'): ?>
            <p class="small text-danger mb-0">The certificate is withdrawn, so no letter of recommendation is available.</p>
        <?php elseif (!$letter): ?>
            <p class="small text-muted">Written by the system from the student's course, with the registration number and today's date.
                The student gets it by email and on their Certificates page.</p>
            <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="lor_action" value="generate">
                <button class="btn btn-success"><i class="fas fa-wand-magic-sparkles me-1"></i>Generate letter of recommendation</button>
            </form>
        <?php else: ?>
            <p class="small text-muted mb-3">Generated <?php echo date('j M Y', strtotime($letter['created_at'])); ?>
                <?php if ($letter['edited_at']): ?>· edited <?php echo date('j M Y', strtotime($letter['edited_at'])); ?><?php endif; ?>
                <?php if ($letter['emailed_at']): ?>· <i class="fas fa-envelope-circle-check text-success"></i> emailed <?php echo date('j M', strtotime($letter['emailed_at'])); ?><?php endif; ?></p>
            <form method="POST" class="mb-2"><?php echo csrf_field(); ?><input type="hidden" name="lor_action" value="save">
                <textarea name="lor_body" rows="12" class="form-control mb-2" style="font-family:Georgia,serif"><?php echo htmlspecialchars($letter['body']); ?></textarea>
                <div class="form-text mb-2">Leave a blank line between paragraphs. The logo, title, registration number, date and signatures are added automatically.</div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success"><i class="fas fa-save me-1"></i>Save letter</button>
                    <a class="btn btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars($lorUrl); ?>"><i class="fas fa-eye me-1"></i>Preview</a>
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($lorUrl . '&dl=1'); ?>"><i class="fas fa-download me-1"></i>Download</a>
                </div>
            </form>
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="lor_action" value="email">
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-paper-plane me-1"></i>Email to student</button></form>
                <form method="POST" class="m-0" onsubmit="return confirm('Replace the text with the system wording for this course? Your edits will be lost.')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="lor_action" value="reset">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-rotate-left me-1"></i>Reset to system text</button></form>
            </div>
        <?php endif; ?>
    </div>
</div>
