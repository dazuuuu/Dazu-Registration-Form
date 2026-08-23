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

$ctx = $docService->defaultContext($courseId ?: null);
if ($smsStudentId > 0) {
    $ctx = array_merge($ctx, $docService->contextFromSmsStudent($smsStudentId));
} elseif ($prospectId > 0) {
    $ctx = array_merge($ctx, $docService->contextFromProspect($prospectId));
}

$bodyDefault = document_merge_placeholders(
    $docService->defaultBody($template, $ctx),
    $ctx
);
$meta = $docService->templateMeta($template);
$subjectDefault = document_merge_placeholders($meta['subject'], $ctx);

$students = $sms->listStudents();
$prospects = $sms->listProspects(null, 'open');
$courses = $sms->courses();

$page_title = 'Compose — ' . $meta['label'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Documents', 'url' => url('admin/sms/documents/index.php')],
    ['label' => 'Compose', 'active' => true],
];

ob_start();
?>
<form method="post" action="<?= url('admin/sms/documents/action.php') ?>" id="composeForm" target="_blank">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="template" id="templateField" value="<?= htmlspecialchars($template) ?>">

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
                            <option value="manual">Manual entry</option>
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

                    <div id="manualFields">
                        <div class="mb-3">
                            <label class="form-label">Recipient name</label>
                            <input type="text" name="recipient_name" class="form-control" value="<?= htmlspecialchars($ctx['recipient_name']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($ctx['email']) ?>" placeholder="For sending">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Registration no.</label>
                            <input type="text" name="registration_no" class="form-control" value="<?= htmlspecialchars($ctx['registration_no']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($ctx['phone']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Course (for fee structure)</label>
                        <select name="course_id" class="form-select" id="courseSelect">
                            <option value="">— Optional —</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $courseId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Study mode</label>
                        <input type="text" name="study_mode" class="form-control" value="<?= htmlspecialchars($ctx['study_mode']) ?>" placeholder="Online / Physical">
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Amount</label>
                            <input type="text" name="amount" class="form-control" value="<?= htmlspecialchars($ctx['amount']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Balance</label>
                            <input type="text" name="balance" class="form-control" value="<?= htmlspecialchars($ctx['balance']) ?>">
                        </div>
                    </div>
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
                    <button type="submit" name="action" value="email" class="btn btn-primary" formaction="<?= url('admin/sms/documents/action.php') ?>" formtarget="_self">
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
                    <small class="text-muted">Edit before preview / download / email</small>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Email subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($subjectDefault) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Body</label>
                        <textarea name="body" class="form-control font-monospace" rows="22" id="bodyField"><?= htmlspecialchars($bodyDefault) ?></textarea>
                    </div>
                    <p class="small text-muted mb-0">
                        Placeholders: <code>{date}</code> <code>{institute_name}</code> <code>{recipient_name}</code>
                        <code>{registration_no}</code> <code>{course_title}</code> <code>{amount}</code>
                        <code>{invoice_no}</code> <code>{receipt_no}</code>
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var templateSelect = document.getElementById('templateSelect');
    var templateField = document.getElementById('templateField');
    var recipientSource = document.getElementById('recipientSource');
    var studentPick = document.getElementById('studentPick');
    var prospectPick = document.getElementById('prospectPick');
    var manualFields = document.getElementById('manualFields');
    var smsStudentSelect = document.getElementById('smsStudentSelect');
    var prospectSelect = document.getElementById('prospectSelect');
    var courseSelect = document.getElementById('courseSelect');
    var composeForm = document.getElementById('composeForm');

    function reloadWithParams() {
        var params = new URLSearchParams();
        params.set('template', templateSelect.value);
        if (recipientSource.value === 'student' && smsStudentSelect.value) {
            params.set('sms_student_id', smsStudentSelect.value);
        } else if (recipientSource.value === 'prospect' && prospectSelect.value) {
            params.set('prospect_id', prospectSelect.value);
        }
        if (courseSelect.value) {
            params.set('course_id', courseSelect.value);
        }
        window.location = '<?= url('admin/sms/documents/compose.php') ?>?' + params.toString();
    }

    function syncRecipientUi() {
        var src = recipientSource.value;
        studentPick.classList.toggle('d-none', src !== 'student');
        prospectPick.classList.toggle('d-none', src !== 'prospect');
        manualFields.classList.toggle('d-none', src !== 'manual');
    }

    templateSelect.addEventListener('change', function () {
        templateField.value = templateSelect.value;
        reloadWithParams();
    });
    recipientSource.addEventListener('change', syncRecipientUi);
    smsStudentSelect.addEventListener('change', reloadWithParams);
    prospectSelect.addEventListener('change', reloadWithParams);
    courseSelect.addEventListener('change', reloadWithParams);

    composeForm.addEventListener('submit', function (e) {
        var action = e.submitter && e.submitter.value;
        if (action === 'email') {
            composeForm.target = '_self';
        } else {
            composeForm.target = '_blank';
        }
    });

    syncRecipientUi();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
