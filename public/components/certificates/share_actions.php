<?php
/**
 * Download / Email / WhatsApp buttons for one certificate (admin, superadmin and student pages).
 *
 * Expects:
 *   $cert                 certificate row (id, qr_code_hash, certificate_no, snapshot_name, snapshot_course)
 *   $certDownloadUrl      link that downloads the PDF for the current user
 *   $certEmailUrl         POST endpoint (fields: id, email, return_to)
 *   $certDefaultEmail     prefilled email (optional)
 *   $certDefaultPhone     prefilled WhatsApp number (optional)
 *   $certButtonClass      extra classes for the buttons (optional)
 *
 * WhatsApp: opens a chat with the number entered, carrying a message with a direct link to the PDF.
 * On phones that can share files, "Share PDF file" hands the PDF itself to WhatsApp (or any app).
 */
$__cid = (int) $cert['id'];
$__btn = $certButtonClass ?? 'btn-sm';
$__pdfLink = cert_public_pdf_url((string) $cert['qr_code_hash']);
$__course = (string) ($cert['snapshot_course'] ?: ($cert['course_name'] ?? ''));
$__waText = 'Certificate of ' . $cert['snapshot_name'] . ' — ' . $__course . ' (Dazu Training Institute, No. ' . $cert['certificate_no'] . ")\n"
    . 'Download the PDF: ' . $__pdfLink . "\n"
    . 'Verify: ' . cert_verify_url((string) $cert['qr_code_hash']);
$__fileName = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $cert['certificate_no']) . '.pdf';
$__h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="d-flex flex-wrap gap-2 cert-share">
    <a href="<?php echo $__h($certDownloadUrl); ?>" class="btn btn-success <?php echo $__btn; ?>"><i class="fas fa-download me-1"></i>Download PDF</a>
    <button type="button" class="btn btn-outline-primary <?php echo $__btn; ?>" data-bs-toggle="modal" data-bs-target="#certEmail<?php echo $__cid; ?>"><i class="fas fa-envelope me-1"></i>Email</button>
    <button type="button" class="btn btn-outline-success <?php echo $__btn; ?>" data-bs-toggle="modal" data-bs-target="#certWa<?php echo $__cid; ?>"><i class="fab fa-whatsapp me-1"></i>WhatsApp</button>
</div>

<div class="modal fade" id="certEmail<?php echo $__cid; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="<?php echo $__h($certEmailUrl); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $__cid; ?>">
            <input type="hidden" name="return_to" value="<?php echo $__h($_SERVER['REQUEST_URI'] ?? ''); ?>">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Email certificate PDF</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="certEmailIn<?php echo $__cid; ?>">Send to email address</label>
                <input type="email" class="form-control" id="certEmailIn<?php echo $__cid; ?>" name="email" required value="<?php echo $__h($certDefaultEmail ?? ''); ?>" placeholder="name@example.com">
                <div class="form-text">The certificate is attached as a PDF.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="certWa<?php echo $__cid; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form class="js-cert-wa" data-text="<?php echo $__h($__waText); ?>" data-pdf="<?php echo $__h($__pdfLink); ?>" data-file="<?php echo $__h($__fileName); ?>">
            <div class="modal-header"><h5 class="modal-title"><i class="fab fa-whatsapp me-2 text-success"></i>Send to WhatsApp</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="certWaIn<?php echo $__cid; ?>">WhatsApp number</label>
                <input type="tel" class="form-control" id="certWaIn<?php echo $__cid; ?>" name="phone" required value="<?php echo $__h($certDefaultPhone ?? ''); ?>" placeholder="e.g. 0712 345 678 or +254712345678">
                <div class="form-text">WhatsApp opens a chat with this number, with the certificate PDF link ready to send.</div>
                <div class="text-danger small mt-2 js-cert-wa-err" role="alert"></div>
                <button type="button" class="btn btn-link px-0 mt-2 js-cert-wa-file d-none"><i class="fas fa-file-pdf me-1"></i>Or share the PDF file itself (this phone)</button>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="fab fa-whatsapp me-1"></i>Send</button></div>
        </form>
    </div></div>
</div>
<?php if (!defined('CERT_SHARE_JS')): define('CERT_SHARE_JS', true); ?>
<script>
(function () {
    // Kenyan numbers: 07xx / 01xx → 2547xx / 2541xx; anything with a country code is kept.
    function waNumber(raw) {
        var d = String(raw || '').replace(/[^\d+]/g, '');
        if (d.charAt(0) === '+') d = d.slice(1);
        else if (d.indexOf('00') === 0) d = d.slice(2);
        else if (/^0[17]\d{8}$/.test(d)) d = '254' + d.slice(1);
        else if (/^[17]\d{8}$/.test(d)) d = '254' + d;
        return /^\d{10,15}$/.test(d) ? d : null;
    }
    document.querySelectorAll('form.js-cert-wa').forEach(function (form) {
        var err = form.querySelector('.js-cert-wa-err');
        var fileBtn = form.querySelector('.js-cert-wa-file');
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var n = waNumber(form.phone.value);
            if (!n) { err.textContent = 'Enter a valid phone number, e.g. 0712 345 678 or +254712345678.'; return; }
            err.textContent = '';
            window.open('https://wa.me/' + n + '?text=' + encodeURIComponent(form.dataset.text), '_blank', 'noopener');
        });
        // Phones that support sharing files can hand the actual PDF to WhatsApp.
        if (navigator.canShare && navigator.canShare({ files: [new File([''], 'x.pdf', { type: 'application/pdf' })] })) {
            fileBtn.classList.remove('d-none');
            fileBtn.addEventListener('click', function () {
                fileBtn.disabled = true;
                fetch(form.dataset.pdf).then(function (r) { if (!r.ok) throw new Error(); return r.blob(); }).then(function (b) {
                    return navigator.share({ files: [new File([b], form.dataset.file, { type: 'application/pdf' })], text: form.dataset.text });
                }).catch(function (e) {
                    if (!e || e.name !== 'AbortError') err.textContent = 'Could not share the file — use Send instead.';
                }).finally(function () { fileBtn.disabled = false; });
            });
        }
    });
})();
</script>
<?php endif; ?>
