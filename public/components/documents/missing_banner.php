<?php
// Reminder on the dashboards until passport photo, T-shirt size and academic documents are provided.
// Expects: $docsLink (URL of the person's My Documents page).
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
try {
    $docsMissingNow = docs_missing(\Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0));
} catch (Throwable $e) {
    $docsMissingNow = [];
}
if ($docsMissingNow): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-exclamation-triangle me-1"></i><strong>Action required:</strong> submit your <?php echo htmlspecialchars(implode(', ', $docsMissingNow)); ?>.</span>
    <a href="<?php echo htmlspecialchars($docsLink); ?>" class="btn btn-sm btn-warning">Go to My Documents</a>
</div>
<?php endif;
