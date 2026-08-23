<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

$docService = new DocumentComposeService($pdo);

$page_title = 'Documents';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Documents', 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Official Documents</h4>
        <p class="text-muted small mb-0">Black &amp; white letterhead templates with institute logo. Compose, edit, download PDF, or email.</p>
    </div>
    <a href="<?= url('admin/sms/documents/compose.php') ?>" class="btn btn-dark">
        <i class="fas fa-pen-fancy me-1"></i> Compose
    </a>
</div>

<div class="row g-3">
    <?php foreach (DocumentComposeService::TEMPLATES as $slug => $meta): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded border d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;background:#f8f8f8;">
                        <i class="fas fa-file-alt text-dark"></i>
                    </div>
                    <div>
                        <h6 class="mb-1"><?= htmlspecialchars($meta['label']) ?></h6>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($meta['title']) ?></p>
                        <a href="<?= url('admin/sms/documents/compose.php?template=' . urlencode($slug)) ?>" class="btn btn-sm btn-outline-dark">
                            Use template
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4 border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">About these templates</div>
    <div class="card-body small text-muted">
        <ul class="mb-0">
            <li>Logo defaults to <code>assets/images/logo/logo.png</code> (upload under Settings → Branding to override).</li>
            <li>Documents use official black-and-white letterhead — no coloured branding.</li>
            <li>Use placeholders like <code>{recipient_name}</code>, <code>{registration_no}</code>, <code>{course_title}</code>, <code>{school_fees}</code>, <code>{amount_paid}</code>, <code>{balance}</code>, <code>{date}</code> in the body text.</li>
            <li>School fees are filled automatically from the course. Remembrance notes include outstanding balance from SmS student records.</li>
            <li>Email attachments are sent as PDF when Dompdf is installed.</li>
        </ul>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
