<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../../app/v1/helpers/document_letterhead.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

$docService = new DocumentComposeService($pdo);

$template = $_GET['template'] ?? 'admission_letter';
if (!$docService->templateMeta($template)) {
    $template = 'admission_letter';
}

$courseId = (int) ($_GET['course_id'] ?? 0);
$smsStudentId = (int) ($_GET['sms_student_id'] ?? 0);
$prospectId = (int) ($_GET['prospect_id'] ?? 0);

$built = $docService->buildComposeContext(
    $courseId ?: null,
    $smsStudentId ?: null,
    $prospectId ?: null,
    null
);
$ctx = $built['ctx'];
$regMeta = $built['reg'];

$bodyTemplate = $docService->bodyTemplate($template);
$meta = $docService->templateMeta($template);
$fieldUi = $docService->templateFieldUi($template);
$bodyDefault = $docService->defaultBody($template, $ctx);
$subjectDefault = document_merge_placeholders($meta['subject'], $ctx);

$students = $sms->listStudents();
$prospects = $sms->listProspects(null, 'open');
$courses = $sms->courses();

$studyModeRaw = '';
if (($ctx['study_mode'] ?? 'N/A') !== 'N/A') {
    $studyModeRaw = $ctx['study_mode'];
}

$page_title = 'Compose — ' . $meta['label'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Documents', 'url' => url('admin/sms/documents/index.php')],
    ['label' => 'Compose', 'active' => true],
];
$documents_nav_active = 'compose';

ob_start();
require __DIR__ . '/_nav.php';
?>
<form method="post" action="<?= url('admin/sms/documents/action.php') ?>" id="composeForm" target="_blank">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="template" id="templateField" value="<?= htmlspecialchars($template) ?>">
    <input type="hidden" name="sms_student_id" id="smsStudentIdField" value="<?= $smsStudentId > 0 ? (int) $smsStudentId : '' ?>">
    <input type="hidden" name="reg_is_new" id="regIsNew" value="<?= !empty($regMeta['is_new']) ? '1' : '0' ?>">
    <input type="hidden" name="reg_is_preview" id="regIsPreview" value="<?= !empty($regMeta['is_preview']) ? '1' : '0' ?>">

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-semibold">Template &amp; recipient</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Template</label>
                        <select class="form-select" id="templateSelect">
                            <?php foreach (DocumentComposeService::TEMPLATES as $slug => $m): ?>
                            <option value="<?= htmlspecialchars($slug) ?>" <?= $slug === $template ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['label']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fill from</label>
                        <select class="form-select" id="recipientSource">
                            <option value="manual" <?= $smsStudentId <= 0 && $prospectId <= 0 ? 'selected' : '' ?>>Manual entry</option>
                            <option value="student" <?= $smsStudentId > 0 ? 'selected' : '' ?>>SmS student</option>
                            <option value="prospect" <?= $prospectId > 0 ? 'selected' : '' ?>>Prospect</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="studentPick">
                        <label class="form-label">Student</label>
                        <select class="form-select" id="smsStudentSelect">
                            <option value="">— Select —</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $smsStudentId === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($s['registration_no'] ?? '') . ' — ' . $s['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="prospectPick">
                        <label class="form-label">Prospect</label>
                        <select class="form-select" id="prospectSelect">
                            <option value="">— Select —</option>
                            <?php foreach ($prospects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $prospectId === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['name'] . ($p['email'] ? ' — ' . $p['email'] : '')) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sync-field mb-3">
                        <label class="form-label">Recipient name</label>
                        <input type="text" name="recipient_name" id="recipientName" class="form-control sync-input" value="<?= htmlspecialchars($ctx['recipient_name']) ?>">
                    </div>
                    <div class="sync-field mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="recipientEmail" class="form-control sync-input" value="<?= htmlspecialchars($ctx['email']) ?>" placeholder="For sending">
                    </div>
                    <div class="sync-field mb-3">
                        <label class="form-label">Registration no.</label>
                        <input type="text" name="registration_no" id="registrationNo" class="form-control bg-light" value="<?= htmlspecialchars($ctx['registration_no']) ?>" readonly>
                        <small class="text-muted" id="regNoHint">
                            <?php if (!empty($regMeta['is_new']) && !empty($regMeta['is_preview'])): ?>
                                Preview — new number issued when you download or email (select a course first).
                            <?php elseif (!empty($regMeta['is_new']) === false && $ctx['registration_no'] !== ''): ?>
                                Existing student — first enrollment number retained.
                            <?php else: ?>
                                Select a course to auto-generate DH/ABBR-0050/YY for new students.
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="sync-field mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="recipientPhone" class="form-control sync-input" value="<?= htmlspecialchars($ctx['phone']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select sync-input" id="courseSelect">
                            <option value="">— Select course —</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $courseId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sync-field mb-3">
                        <label class="form-label">Study mode</label>
                        <select name="study_mode" id="studyMode" class="form-select sync-input">
                            <option value="" <?= $studyModeRaw === '' ? 'selected' : '' ?>>N/A (leave blank)</option>
                            <option value="Online" <?= strcasecmp($studyModeRaw, 'Online') === 0 ? 'selected' : '' ?>>Online</option>
                            <option value="Physical" <?= strcasecmp($studyModeRaw, 'Physical') === 0 ? 'selected' : '' ?>>Physical</option>
                        </select>
                    </div>

                    <div id="schoolFeesWrap" class="sync-field mb-3<?= empty($fieldUi['show_school_fees']) ? ' d-none' : '' ?>">
                        <label class="form-label">School fees</label>
                        <input type="text" id="schoolFeesDisplay" class="form-control bg-light" value="<?= htmlspecialchars($ctx['school_fees']) ?>" readonly>
                        <small class="text-muted">Auto-filled from course fees (online/physical by study mode).</small>
                    </div>

                    <div id="amountPaidWrap" class="sync-field mb-3<?= empty($fieldUi['show_amount_paid']) ? ' d-none' : '' ?>">
                        <label class="form-label">Amount received</label>
                        <input type="text" name="amount_paid" id="amountPaidField" class="form-control sync-input" value="<?= htmlspecialchars($ctx['amount_paid']) ?>" placeholder="Payment amount">
                    </div>

                    <div id="admissionFeesHint" class="small text-muted mb-3<?= empty($fieldUi['admission_auto_fees']) ? ' d-none' : '' ?>">
                        School fees are included automatically in the admission letter from the selected course.
                    </div>

                    <input type="hidden" name="invoice_no" id="invoiceNo" value="<?= htmlspecialchars($ctx['invoice_no']) ?>">
                    <input type="hidden" name="receipt_no" id="receiptNo" value="<?= htmlspecialchars($ctx['receipt_no']) ?>">
                    <input type="hidden" name="payment_reference" id="paymentReference" value="<?= htmlspecialchars($ctx['payment_reference']) ?>">
                    <input type="hidden" id="schoolFees" value="<?= htmlspecialchars($ctx['school_fees']) ?>">
                    <input type="hidden" id="balanceHidden" value="<?= htmlspecialchars($ctx['balance']) ?>">
                    <input type="hidden" id="amountPaidHidden" value="<?= htmlspecialchars($ctx['amount_paid']) ?>">
                    <input type="hidden" id="instituteName" value="<?= htmlspecialchars($ctx['institute_name']) ?>">
                    <input type="hidden" id="feeOnline" value="<?= htmlspecialchars($ctx['fee_online']) ?>">
                    <input type="hidden" id="feePhysical" value="<?= htmlspecialchars($ctx['fee_physical']) ?>">
                    <input type="hidden" id="courseTitle" value="<?= htmlspecialchars($ctx['course_title']) ?>">
                    <input type="hidden" id="fieldUiJson" value="<?= htmlspecialchars(json_encode($fieldUi), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold">Actions</div>
                <div class="card-body d-grid gap-2">
                    <button type="submit" name="action" value="preview" class="btn btn-outline-dark">
                        <i class="fas fa-eye me-1"></i> Preview
                    </button>
                    <button type="submit" name="action" value="download" class="btn btn-dark">
                        <i class="fas fa-download me-1"></i> Download PDF
                    </button>
                    <button type="submit" name="action" value="email" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Email to recipient
                    </button>
                    <a href="<?= url('admin/sms/documents/index.php') ?>" class="btn btn-link btn-sm text-muted">Back to templates</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Document text</span>
                    <small class="text-muted">Updates live as you edit fields on the left</small>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Email subject</label>
                        <input type="text" name="subject" id="subjectField" class="form-control" value="<?= htmlspecialchars($subjectDefault) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Body</label>
                        <textarea name="body" class="form-control font-monospace" rows="22" id="bodyField"><?= htmlspecialchars($bodyDefault) ?></textarea>
                    </div>
                    <textarea id="bodyTemplate" class="d-none"><?= htmlspecialchars($bodyTemplate) ?></textarea>
                    <textarea id="subjectTemplate" class="d-none"><?= htmlspecialchars($meta['subject']) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var contextUrl = <?= json_encode(url('admin/sms/documents/context.php')) ?>;
    var composeUrl = <?= json_encode(url('admin/sms/documents/compose.php')) ?>;

    var templateSelect = document.getElementById('templateSelect');
    var templateField = document.getElementById('templateField');
    var recipientSource = document.getElementById('recipientSource');
    var studentPick = document.getElementById('studentPick');
    var prospectPick = document.getElementById('prospectPick');
    var smsStudentSelect = document.getElementById('smsStudentSelect');
    var prospectSelect = document.getElementById('prospectSelect');
    var courseSelect = document.getElementById('courseSelect');
    var composeForm = document.getElementById('composeForm');
    var bodyField = document.getElementById('bodyField');
    var bodyTemplateEl = document.getElementById('bodyTemplate');
    var subjectField = document.getElementById('subjectField');
    var subjectTemplateEl = document.getElementById('subjectTemplate');
    var regNoHint = document.getElementById('regNoHint');
    var regIsNew = document.getElementById('regIsNew');
    var regIsPreview = document.getElementById('regIsPreview');
    var smsStudentIdField = document.getElementById('smsStudentIdField');
    var fetchTimer = null;

    function displayVal(value, fallback) {
        var v = (value || '').trim();
        return v !== '' ? v : (fallback || '_________________________');
    }

    function studyModeVal() {
        var v = (document.getElementById('studyMode').value || '').trim();
        return v === '' ? 'N/A' : v;
    }

    function amountPaidVal() {
        var paidField = document.getElementById('amountPaidField');
        if (paidField && !paidField.closest('.d-none')) {
            return displayVal(paidField.value);
        }
        return displayVal(document.getElementById('amountPaidHidden').value);
    }

    function collectVars() {
        var schoolFees = displayVal(document.getElementById('schoolFees').value, 'As advised');
        return {
            institute_name: document.getElementById('instituteName').value || '',
            date: <?= json_encode(date('d F Y')) ?>,
            recipient_name: displayVal(document.getElementById('recipientName').value),
            registration_no: displayVal(document.getElementById('registrationNo').value),
            course_title: displayVal(document.getElementById('courseTitle').value),
            study_mode: studyModeVal(),
            email: (document.getElementById('recipientEmail').value || '').trim(),
            phone: (document.getElementById('recipientPhone').value || '').trim(),
            fee_online: displayVal(document.getElementById('feeOnline').value, 'As advised'),
            fee_physical: displayVal(document.getElementById('feePhysical').value, 'As advised'),
            school_fees: schoolFees,
            amount: schoolFees,
            amount_paid: amountPaidVal(),
            balance: displayVal(document.getElementById('balanceHidden').value),
            payment_reference: displayVal(document.getElementById('paymentReference').value),
            invoice_no: document.getElementById('invoiceNo').value || '',
            receipt_no: document.getElementById('receiptNo').value || ''
        };
    }

    function applyFieldUi(fieldUi) {
        if (!fieldUi) {
            try {
                fieldUi = JSON.parse(document.getElementById('fieldUiJson').value || '{}');
            } catch (e) {
                fieldUi = {};
            }
        } else {
            document.getElementById('fieldUiJson').value = JSON.stringify(fieldUi);
        }

        document.getElementById('schoolFeesWrap').classList.toggle('d-none', !fieldUi.show_school_fees);
        document.getElementById('amountPaidWrap').classList.toggle('d-none', !fieldUi.show_amount_paid);
        document.getElementById('admissionFeesHint').classList.toggle('d-none', !fieldUi.admission_auto_fees);
    }

    function mergeTemplate(tpl, vars) {
        var out = tpl;
        Object.keys(vars).forEach(function (key) {
            out = out.split('{' + key + '}').join(vars[key]);
        });
        return out.replace(/\{[a-z0-9_]+\}/gi, '');
    }

    function syncLetterFromFields() {
        var vars = collectVars();
        bodyField.value = mergeTemplate(bodyTemplateEl.value, vars);
        subjectField.value = mergeTemplate(subjectTemplateEl.value, vars);
    }

    function syncRecipientUi() {
        var src = recipientSource.value;
        studentPick.classList.toggle('d-none', src !== 'student');
        prospectPick.classList.toggle('d-none', src !== 'prospect');
    }

    function applyContext(data) {
        if (!data || !data.ok) return;
        var ctx = data.ctx || {};
        document.getElementById('recipientName').value = ctx.recipient_name || '';
        document.getElementById('recipientEmail').value = ctx.email || '';
        document.getElementById('recipientPhone').value = ctx.phone || '';
        document.getElementById('registrationNo').value = ctx.registration_no || '';
        document.getElementById('schoolFees').value = ctx.school_fees || '';
        document.getElementById('schoolFeesDisplay').value = ctx.school_fees || '';
        document.getElementById('balanceHidden').value = ctx.balance || '';
        document.getElementById('amountPaidHidden').value = ctx.amount_paid || '';
        var amountPaidField = document.getElementById('amountPaidField');
        if (amountPaidField) {
            amountPaidField.value = ctx.amount_paid || '';
        }
        document.getElementById('feeOnline').value = ctx.fee_online || '';
        document.getElementById('feePhysical').value = ctx.fee_physical || '';
        document.getElementById('courseTitle').value = ctx.course_title || '';
        document.getElementById('invoiceNo').value = ctx.invoice_no || '';
        document.getElementById('receiptNo').value = ctx.receipt_no || '';
        document.getElementById('paymentReference').value = ctx.payment_reference || '';

        var mode = (ctx.study_mode || 'N/A');
        document.getElementById('studyMode').value = (mode === 'N/A' || mode === '') ? '' : mode;

        if (data.body_template) bodyTemplateEl.value = data.body_template;
        if (data.subject_template) subjectTemplateEl.value = data.subject_template;
        if (data.field_ui) applyFieldUi(data.field_ui);

        if (data.reg) {
            regIsNew.value = data.reg.is_new ? '1' : '0';
            regIsPreview.value = data.reg.is_preview ? '1' : '0';
            if (data.reg.is_new && data.reg.is_preview) {
                regNoHint.textContent = 'Preview — this new number (from 0050) is issued once when you download or email.';
            } else if (!data.reg.is_new && ctx.registration_no) {
                regNoHint.textContent = 'Existing student — using their first enrollment registration number.';
            } else if (!ctx.registration_no) {
                regNoHint.textContent = 'Select a course to auto-generate DH/ABBR-0050/YY for new students.';
            } else {
                regNoHint.textContent = 'Registration number assigned.';
            }
        }

        syncLetterFromFields();
    }

    function fetchContext() {
        var params = new URLSearchParams();
        params.set('template', templateField.value);
        if (courseSelect.value) params.set('course_id', courseSelect.value);
        if (recipientSource.value === 'student' && smsStudentSelect.value) {
            params.set('sms_student_id', smsStudentSelect.value);
            smsStudentIdField.value = smsStudentSelect.value;
        } else {
            smsStudentIdField.value = '';
        }
        if (recipientSource.value === 'prospect' && prospectSelect.value) {
            params.set('prospect_id', prospectSelect.value);
        }
        var email = document.getElementById('recipientEmail').value.trim();
        if (email) params.set('email', email);

        fetch(contextUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(applyContext)
            .catch(function () { syncLetterFromFields(); });
    }

    function scheduleFetch() {
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchContext, 350);
    }

    templateSelect.addEventListener('change', function () {
        var params = new URLSearchParams(window.location.search);
        params.set('template', templateSelect.value);
        window.location = composeUrl + '?' + params.toString();
    });

    recipientSource.addEventListener('change', function () {
        syncRecipientUi();
        scheduleFetch();
    });
    smsStudentSelect.addEventListener('change', scheduleFetch);
    prospectSelect.addEventListener('change', scheduleFetch);
    courseSelect.addEventListener('change', scheduleFetch);
    document.getElementById('recipientEmail').addEventListener('change', scheduleFetch);

    document.querySelectorAll('.sync-input').forEach(function (el) {
        el.addEventListener('input', syncLetterFromFields);
        el.addEventListener('change', function () {
            if (el.id === 'courseSelect' || el.id === 'studyMode') {
                scheduleFetch();
            } else {
                syncLetterFromFields();
            }
        });
    });

    composeForm.addEventListener('submit', function (e) {
        var action = e.submitter && e.submitter.value;
        composeForm.target = (action === 'email') ? '_self' : '_blank';
    });

    syncRecipientUi();
    applyFieldUi();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
