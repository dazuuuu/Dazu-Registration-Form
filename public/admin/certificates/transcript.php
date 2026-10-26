<?php
// Admin: transcript preview. The full transcript page is built separately later; this page only enforces the rule it will use:
// it opens only when the student's tutor allowed the admin to preview the transcript (default off).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CapstoneStatus.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_GET['student'] ?? 0);
$courseId = (int) ($_GET['course'] ?? 0);
if (!cap_admin_may_preview_transcript($db, $uid, $courseId)) {
    http_response_code(403);
    exit('Not allowed. The student\'s tutor has not allowed a transcript preview.');
}
$page_title = 'Transcript preview';
ob_start();
?>
<div class="container-fluid py-4">
    <h3 class="mb-2">Transcript preview</h3>
    <div class="alert alert-info">The transcript page is built separately and will appear here. The tutor allowed this preview.</div>
    <a href="<?php echo url('admin/certificates/index.php'); ?>" class="btn btn-outline-secondary">← Certificates</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
