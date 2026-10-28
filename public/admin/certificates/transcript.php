<?php
// Admin: transcript status. Words only (No transcript, In progress, Waiting for fees, Waiting for the official stamp, Released ...),
// the transcript number and the release date. Never a mark or a grade.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TranscriptService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_GET['student'] ?? 0);
$courseId = (int) ($_GET['course'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = tr_status($db, $uid, $courseId);
$rel = $status['released'];
$page_title = 'Transcript status';
ob_start();
?>
<div class="container-fluid py-4" style="max-width:640px">
    <h3 class="mb-3">Transcript status</h3>
    <div class="card p-3">
        <div class="small text-muted text-uppercase">Status</div><div class="fw-semibold mb-2"><?php echo $e($status['word']); ?></div>
        <?php if ($rel): ?>
            <div class="small text-muted text-uppercase">Transcript number</div><div class="fw-semibold mb-2"><?php echo $e($rel['transcript_no']); ?></div>
            <div class="small text-muted text-uppercase">Released on</div><div class="fw-semibold mb-2"><?php echo $e(date('j F Y', strtotime($rel['released_at']))); ?></div>
        <?php endif; ?>
        <div class="small text-muted">Marks are shown to the student and their tutor only.</div>
    </div>
    <a href="<?php echo url('admin/certificates/index.php'); ?>" class="btn btn-outline-secondary mt-3">← Certificates</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
