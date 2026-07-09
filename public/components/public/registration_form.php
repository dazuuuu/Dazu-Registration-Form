<?php
/**
 * Reusable course registration form.
 *
 * Expected variables:
 * - $displayCourses (array)
 * - $reg_form_id (string) e.g. dhApplyForm
 * - $reg_form_action (string) form action URL
 * - $reg_error (?string)
 * - $reg_success (bool)
 * - $reg_success_message (?string)
 * - $reg_show_head (bool) optional header inside card
 */
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$reg_form_id = $reg_form_id ?? 'dhApplyForm';
$reg_form_action = $reg_form_action ?? url('apply/index.php');
$reg_success = $reg_success ?? false;
$reg_error = $reg_error ?? null;
$reg_success_message = $reg_success_message ?? 'Application received. Our team will verify your payment and contact you within 24 hours.';
$reg_show_head = $reg_show_head ?? true;
?>

<?php if ($reg_success): ?>
<div class="hf-alert hf-alert-success dh-apply-success" role="status">
    <i class="fas fa-check-circle"></i>
    <div><strong>Thank you!</strong> <?php echo htmlspecialchars($reg_success_message); ?></div>
</div>
<?php else: ?>

<?php if ($reg_error): ?>
<div class="hf-alert hf-alert-error" role="alert">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php echo htmlspecialchars($reg_error); ?></div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="<?php echo htmlspecialchars($reg_form_id); ?>" class="dh-apply-form" action="<?php echo htmlspecialchars($reg_form_action); ?>" data-dh-apply-form>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="dh_register" value="1">

    <div class="hf-row">
        <div class="hf-field">
            <label for="<?php echo $reg_form_id; ?>_name">Full Name *</label>
            <input type="text" id="<?php echo $reg_form_id; ?>_name" name="full_name" required placeholder="Jane Wanjiku"
                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>
        <div class="hf-field">
            <label for="<?php echo $reg_form_id; ?>_phone">Phone *</label>
            <input type="tel" id="<?php echo $reg_form_id; ?>_phone" name="phone" required placeholder="07XX XXX XXX"
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>
    </div>

    <div class="hf-field">
        <label for="<?php echo $reg_form_id; ?>_email">Email *</label>
        <input type="email" id="<?php echo $reg_form_id; ?>_email" name="email" required placeholder="jane@email.com"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
    </div>

    <div class="hf-field">
        <label for="<?php echo $reg_form_id; ?>_course">Course *</label>
        <select id="<?php echo $reg_form_id; ?>_course" name="course_id" required data-dh-course-select>
            <option value="">— Select a course —</option>
            <?php foreach ($displayCourses as $c): ?>
            <option value="<?php echo (int)($c['id'] ?? 0); ?>"
                    data-fee="<?php echo (float)($c['course_fee'] ?? 0); ?>"
                    data-months="<?php echo (int)($c['duration_months'] ?? 1); ?>"
                    <?php echo (isset($_POST['course_id']) && (int)$_POST['course_id'] === (int)($c['id'] ?? 0)) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['title']); ?> — KSh <?php echo number_format((float)($c['course_fee'] ?? 0)); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="hf-field">
        <label>Study Mode *</label>
        <div class="hf-mode">
            <div class="hf-mode-opt">
                <input type="radio" id="<?php echo $reg_form_id; ?>_online" name="study_mode" value="online" required
                       <?php echo (!isset($_POST['study_mode']) || $_POST['study_mode'] === 'online') ? 'checked' : ''; ?>>
                <label for="<?php echo $reg_form_id; ?>_online">💻 <strong>Online</strong></label>
            </div>
            <div class="hf-mode-opt">
                <input type="radio" id="<?php echo $reg_form_id; ?>_physical" name="study_mode" value="physical"
                       <?php echo (isset($_POST['study_mode']) && $_POST['study_mode'] === 'physical') ? 'checked' : ''; ?>>
                <label for="<?php echo $reg_form_id; ?>_physical">🏫 <strong>Physical</strong></label>
            </div>
        </div>
    </div>

    <div class="hf-field">
        <label>Payment Plan *</label>
        <div class="hf-plan">
            <div class="hf-plan-opt">
                <input type="radio" id="<?php echo $reg_form_id; ?>_mo" name="payment_plan" value="monthly" checked data-dh-plan>
                <label for="<?php echo $reg_form_id; ?>_mo"><strong>Monthly</strong><span data-dh-hint-mo>—</span></label>
            </div>
            <div class="hf-plan-opt">
                <input type="radio" id="<?php echo $reg_form_id; ?>_3mo" name="payment_plan" value="3months" data-dh-plan>
                <label for="<?php echo $reg_form_id; ?>_3mo"><strong>3-Month</strong><span data-dh-hint-3mo>—</span></label>
            </div>
            <div class="hf-plan-opt">
                <input type="radio" id="<?php echo $reg_form_id; ?>_full" name="payment_plan" value="full" data-dh-plan>
                <label for="<?php echo $reg_form_id; ?>_full"><strong>Full</strong><span data-dh-hint-full>—</span></label>
            </div>
        </div>
    </div>

    <div class="hf-fee" data-dh-fee-box>
        <div class="hf-fee-row"><span>Reg. fee</span><strong data-dh-rfee>KSh 1,000</strong></div>
        <div class="hf-fee-row"><span data-dh-ilabel>Select a course</span><strong data-dh-iamt>—</strong></div>
        <hr class="hf-fee-hr">
        <div class="hf-fee-total">
            <span>Pay now</span>
            <span class="amt" data-dh-total>KSh 1,000</span>
        </div>
    </div>

    <div class="hf-field">
        <label class="hf-field-label-block">Pay via M-Pesa (NCBA)</label>
        <?php include __DIR__ . '/payment_credentials_box.php'; ?>
    </div>

    <div class="hf-field">
        <label for="<?php echo $reg_form_id; ?>_mpesa">M-Pesa Name / Code used *</label>
        <input type="text" id="<?php echo $reg_form_id; ?>_mpesa" name="payment_reference" required
               placeholder="Your name as on M-Pesa or confirmation code"
               value="<?php echo htmlspecialchars($_POST['payment_reference'] ?? ''); ?>">
        <small class="hf-help">Enter the name or M-Pesa code shown on your payment SMS so we can verify it.</small>
    </div>

    <div class="hf-field">
        <label>ID / Result Slip (PDF) *</label>
        <label class="hf-upload" for="<?php echo $reg_form_id; ?>_id_doc">
            <i class="fas fa-file-pdf"></i>
            <span data-dh-upload-text>Upload PDF</span>
        </label>
        <input type="file" id="<?php echo $reg_form_id; ?>_id_doc" name="id_document" accept="application/pdf" required data-dh-id-upload>
    </div>

    <button type="submit" class="hf-submit" data-dh-submit-btn>
        <i class="fas fa-paper-plane"></i> Submit Application
    </button>
</form>
<?php endif; ?>
