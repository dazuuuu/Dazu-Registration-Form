<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

$docService = new DocumentComposeService($pdo);

$search = trim($_GET['q'] ?? '');
$template = $_GET['template'] ?? 'all';
$delivery = $_GET['delivery'] ?? 'all';
$source = $_GET['source'] ?? 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$filters = [
    'q'        => $search,
    'template' => $template,
    'delivery' => $delivery,
    'source'   => $source,
];

$result = $docService->listDocumentHistory($filters, $perPage, $offset);
$items = $result['items'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

$tableReady = $docService->composedDocumentsTableExists();
$admissionReady = $docService->admissionDocumentsTableExists();

$page_title = 'Document history';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Documents', 'url' => url('admin/sms/documents/index.php')],
    ['label' => 'Generated & sent', 'active' => true],
];
$documents_nav_active = 'history';

ob_start();
require __DIR__ . '/_nav.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Generated &amp; sent documents</h4>
        <p class="text-muted small mb-0">PDFs downloaded or emailed from Documents, plus admission letters issued on enrollment.</p>
    </div>
    <a href="<?= url('admin/sms/documents/compose.php') ?>" class="btn btn-dark btn-sm">
        <i class="fas fa-pen-fancy me-1"></i> Compose new
    </a>
</div>

<?php if (!$tableReady && !$admissionReady): ?>
<div class="alert alert-warning">
    Document history is not set up yet. Run <strong>Admin → System Updates</strong> and apply update
    <code>040_composed_documents_log.sql</code> to enable logging for composed documents.
</div>
<?php elseif (!$tableReady): ?>
<div class="alert alert-info small">
    Composed document logging will begin after you apply update <code>040_composed_documents_log.sql</code> under System Updates.
    Enrollment admission letters are shown below.
</div>
<?php endif; ?>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-3">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control form-control-sm" placeholder="Search name, email, reg no…">
    </div>
    <div class="col-md-2">
        <select name="source" class="form-select form-select-sm">
            <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>All sources</option>
            <option value="composed" <?= $source === 'composed' ? 'selected' : '' ?>>Composed (SmS)</option>
            <option value="enrollment" <?= $source === 'enrollment' ? 'selected' : '' ?>>Enrollment</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="template" class="form-select form-select-sm">
            <option value="all" <?= $template === 'all' ? 'selected' : '' ?>>All templates</option>
            <?php foreach (DocumentComposeService::TEMPLATES as $slug => $meta): ?>
            <option value="<?= htmlspecialchars($slug) ?>" <?= $template === $slug ? 'selected' : '' ?>>
                <?= htmlspecialchars($meta['label']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="delivery" class="form-select form-select-sm">
            <option value="all" <?= $delivery === 'all' ? 'selected' : '' ?>>All delivery</option>
            <option value="download" <?= $delivery === 'download' ? 'selected' : '' ?>>Downloaded</option>
            <option value="email" <?= $delivery === 'email' ? 'selected' : '' ?>>Emailed</option>
            <option value="enrollment" <?= $delivery === 'enrollment' ? 'selected' : '' ?>>Enrollment</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Document</th>
                    <th>Recipient</th>
                    <th>Reg. no.</th>
                    <th>Course</th>
                    <th>Delivery</th>
                    <th>By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$items): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No documents found. Compose and download or email a document to see it here.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td class="text-nowrap small">
                        <?= htmlspecialchars(date('d M Y, H:i', strtotime((string) ($row['created_at'] ?? 'now')))) ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($row['document_label'] ?? 'Document') ?></strong>
                        <?php if (($row['history_source'] ?? '') === 'enrollment'): ?>
                        <br><span class="badge bg-secondary-subtle text-secondary border">Enrollment</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($row['recipient_name'] ?? '—') ?>
                        <?php if (!empty($row['recipient_email'])): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($row['recipient_email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($row['registration_no'] ?? '—') ?></small></td>
                    <td><small><?= htmlspecialchars($row['course_title'] ?? '—') ?></small></td>
                    <td>
                        <?php
                        $deliveryLabel = $row['delivery_label'] ?? ucfirst((string) ($row['delivery'] ?? ''));
                        $badgeClass = ($row['delivery'] ?? '') === 'email' ? 'bg-primary' : 'bg-dark';
                        if (($row['delivery'] ?? '') === 'enrollment') {
                            $badgeClass = 'bg-secondary';
                        }
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($deliveryLabel) ?></span>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($row['actor_name'] ?? '—') ?></small></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('admin/sms/documents/file.php?source=' . urlencode((string) ($row['history_source'] ?? 'composed')) . '&id=' . (int) ($row['history_id'] ?? 0)) ?>"
                           class="btn btn-sm btn-outline-dark" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i> View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item<?= $p === $page ? ' active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<p class="text-muted small mt-3 mb-0">
    Showing <?= count($items) ?> of <?= (int) $total ?> document<?= $total === 1 ? '' : 's' ?>.
</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
