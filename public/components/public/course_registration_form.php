<?php
// public/components/public/course_registration_form.php
// Reusable registration form — include after setting $course, $reg_success, $reg_error, $pfx (optional).

if (!isset($course) || !is_array($course)) {
    throw new RuntimeException('course_registration_form requires $course');
}

$pfx = $pfx ?? 'crf';
$fee = (float) $course['course_fee'];
$mo  = max(1, (int) $course['duration_months']);
$reg_success = $reg_success ?? false;
$reg_error   = $reg_error ?? null;
$referrer    = $referrer ?? null;
?>

<?php if ($reg_success): ?>
<div class="crf-alert crf-alert--ok">
    <i class="fas fa-check-circle"></i>
    <div>
        <strong>Application received!</strong>
        Our team will verify your payment and contact you within 24 hours. You can also reach us on WhatsApp.
    </div>
</div>
<?php else: ?>

<?php if ($reg_error): ?>
<div class="crf-alert crf-alert--err">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php echo htmlspecialchars($reg_error); ?></div>
</div>
<?php endif; ?>

<?php if ($referrer): ?>
<div class="crf-referral">
    <i class="fas fa-user-friends"></i>
    Recommended by <strong><?php echo htmlspecialchars($referrer['full_name']); ?></strong>
    (<?php echo htmlspecialchars($referrer['registration_no']); ?>)
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="<?php echo $pfx; ?>Form" class="crf-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="dh_register" value="1">
    <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">

    <div class="crf-selected">
        <label>Course</label>
        <div class="crf-selected-val"><?php echo htmlspecialchars($course['title']); ?></div>
    </div>

    <div class="crf-row">
        <div class="crf-field">
            <label for="<?php echo $pfx; ?>_name">Full Name *</label>
            <input type="text" id="<?php echo $pfx; ?>_name" name="full_name" required
                   placeholder="Jane Wanjiku" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>
        <div class="crf-field">
            <label for="<?php echo $pfx; ?>_email">Email *</label>
            <input type="email" id="<?php echo $pfx; ?>_email" name="email" required
                   placeholder="jane@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
    </div>

    <div class="crf-field">
        <label for="<?php echo $pfx; ?>_phone">Phone *</label>
        <input type="tel" id="<?php echo $pfx; ?>_phone" name="phone" required
               placeholder="07XX XXX XXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
    </div>

    <div class="crf-field">
        <label>Study Mode *</label>
        <div class="crf-mode">
            <div class="crf-mode-opt">
                <input type="radio" id="<?php echo $pfx; ?>_online" name="study_mode" value="online" required
                       <?php echo (!isset($_POST['study_mode']) || $_POST['study_mode'] === 'online') ? 'checked' : ''; ?>>
                <label for="<?php echo $pfx; ?>_online"><span class="em">💻</span><strong>Online</strong><span>KSh 1,000 fee</span></label>
            </div>
            <div class="crf-mode-opt">
                <input type="radio" id="<?php echo $pfx; ?>_physical" name="study_mode" value="physical"
                       <?php echo (isset($_POST['study_mode']) && $_POST['study_mode'] === 'physical') ? 'checked' : ''; ?>>
                <label for="<?php echo $pfx; ?>_physical"><span class="em">🏫</span><strong>Physical</strong><span>KSh 1,500 + ID</span></label>
            </div>
        </div>
    </div>

    <div class="crf-field">
        <label>Payment Plan *</label>
        <div class="crf-plan">
            <div class="crf-plan-opt">
                <input type="radio" id="<?php echo $pfx; ?>_monthly" name="payment_plan" value="monthly" checked>
                <label for="<?php echo $pfx; ?>_monthly"><strong>Monthly</strong><span>KSh <?php echo number_format($fee / $mo); ?>/mo</span></label>
            </div>
            <div class="crf-plan-opt">
                <input type="radio" id="<?php echo $pfx; ?>_3mo" name="payment_plan" value="3months">
                <label for="<?php echo $pfx; ?>_3mo"><strong>3-Month</strong><span>KSh <?php echo number_format($fee / 3); ?></span></label>
            </div>
            <div class="crf-plan-opt">
                <input type="radio" id="<?php echo $pfx; ?>_full" name="payment_plan" value="full">
                <label for="<?php echo $pfx; ?>_full"><strong>Full</strong><span>KSh <?php echo number_format($fee); ?></span></label>
            </div>
        </div>
    </div>

    <div class="crf-fee" data-fee="<?php echo $fee; ?>" data-months="<?php echo $mo; ?>">
        <div class="crf-fee-row"><span>Registration fee</span><strong id="<?php echo $pfx; ?>_rfee">KSh 1,000</strong></div>
        <div class="crf-fee-row"><span id="<?php echo $pfx; ?>_ilabel">First installment</span><strong id="<?php echo $pfx; ?>_iamt">KSh <?php echo number_format($fee / $mo); ?></strong></div>
        <hr>
        <div class="crf-fee-total"><span>Pay now</span><span class="amt" id="<?php echo $pfx; ?>_total">KSh <?php echo number_format(1000 + $fee / $mo); ?></span></div>
    </div>

    <div class="crf-pay-note">
        <div class="crf-pay-label"><i class="fas fa-mobile-screen-button"></i> NCBA Paybill</div>
        Pay via M-Pesa using our NCBA Paybill — enter your name or M-Pesa code below.
    </div>

    <div class="crf-field">
        <label for="<?php echo $pfx; ?>_mpesa">M-Pesa Name / Code *</label>
        <input type="text" id="<?php echo $pfx; ?>_mpesa" name="payment_reference" required
               placeholder="Jane Wanjiku or QGH7XXXX"
               value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
    </div>

    <div class="crf-field">
        <label>ID / Result Slip (PDF, max 5 MB) *</label>
        <label class="crf-upload" for="<?php echo $pfx; ?>_id_doc">
            <i class="fas fa-file-pdf"></i>
            <span id="<?php echo $pfx; ?>_uploadText">Upload PDF</span>
        </label>
        <input type="file" id="<?php echo $pfx; ?>_id_doc" name="id_document" accept="application/pdf" required>
    </div>

    <button type="submit" class="crf-submit" id="<?php echo $pfx; ?>SubmitBtn">
        <i class="fas fa-paper-plane"></i> Submit Application
    </button>
</form>

<script>
(function(){
    var pfx = <?php echo json_encode($pfx); ?>;
    var fee = <?php echo json_encode($fee); ?>;
    var mo  = <?php echo json_encode($mo); ?>;
    function el(id){ return document.getElementById(pfx + id); }
    function fmt(n){ return 'KSh ' + Math.round(n).toLocaleString(); }
    function recalc(){
        var plan='monthly', mode='online';
        document.querySelectorAll('#'+pfx+'Form input[name="payment_plan"]').forEach(function(r){ if(r.checked) plan=r.value; });
        document.querySelectorAll('#'+pfx+'Form input[name="study_mode"]').forEach(function(r){ if(r.checked) mode=r.value; });
        var regFee = mode==='physical' ? 1500 : 1000;
        var count  = plan==='monthly' ? Math.max(1,mo) : (plan==='3months' ? 3 : 1);
        var inst   = fee / count;
        var label  = plan==='full' ? 'Full course fee' : 'First of '+count+' installment'+(count>1?'s':'');
        if(el('_rfee')) el('_rfee').textContent = fmt(regFee);
        if(el('_ilabel')) el('_ilabel').textContent = label;
        if(el('_iamt')) el('_iamt').textContent = fmt(inst);
        if(el('_total')) el('_total').textContent = fmt(regFee + inst);
    }
    document.querySelectorAll('#'+pfx+'Form input[name="payment_plan"], #'+pfx+'Form input[name="study_mode"]').forEach(function(r){ r.addEventListener('change', recalc); });
    recalc();
    var inp = el('_id_doc'), txt = el('_uploadText');
    if(inp && txt) inp.addEventListener('change', function(){ txt.textContent = inp.files[0] ? inp.files[0].name : 'Upload PDF'; });
    var form = document.getElementById(pfx+'Form');
    if(form) form.addEventListener('submit', function(){
        var b = el('SubmitBtn');
        if(b){ b.disabled=true; b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
    });
})();
</script>
<?php endif; ?>
