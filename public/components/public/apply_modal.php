<?php
// Modal shell — included from public layout; form loaded once for all Apply buttons.
if (empty($dh_apply_modal_courses)) {
    return;
}
?>
<div class="dh-apply-modal" id="dhApplyModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="dhApplyModalTitle">
    <div class="dh-apply-modal-backdrop" data-dh-apply-close></div>
    <div class="dh-apply-modal-panel" role="document">
        <header class="dh-apply-modal-header">
            <div>
                <h2 id="dhApplyModalTitle"><i class="fas fa-graduation-cap"></i> Apply Now</h2>
                <p>Pay via M-Pesa, then submit your details below.</p>
            </div>
            <button type="button" class="dh-apply-modal-close" data-dh-apply-close aria-label="Close application form">
                <i class="fas fa-times"></i>
            </button>
        </header>
        <div class="dh-apply-modal-body" id="dhApplyModalBody">
            <?php
            $displayCourses = $dh_apply_modal_courses ?? ($displayCourses ?? []);
            $reg_form_id = 'dhApplyModalForm';
            $reg_form_action = url('apply/index.php');
            $reg_success = false;
            $reg_error = null;
            include __DIR__ . '/registration_form.php';
            ?>
        </div>
    </div>
</div>
