<?php
// Admin: one enquiry, READ ONLY (details, notes with who wrote each and when, and the history). Normal admin only. No buttons that change anything.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryEnquiryService.php';

use Models\Connection;

secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$e = (new SecretaryEnquiryService($pdo))->get((int) ($_GET['id'] ?? 0));
if (!$e) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('That enquiry was not found.'); }
$statuses = SecretaryEnquiryService::STATUSES; $sources = SecretaryEnquiryService::SOURCES;
$d = static fn(?string $x): string => $x ? date('D j M Y', strtotime($x)) : '—';
$dt = static fn(?string $x): string => $x ? date('j M Y, g:i a', strtotime($x)) : '';
$lab = ['created' => 'Added', 'status' => 'Status', 'follow_up' => 'Follow-up date', 'note' => 'Note', 'taken_over' => 'Taken over'];

$page_title = 'Enquiry';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Enquiries', 'url' => 'enquiries.php'], ['label' => 'Enquiry', 'active' => true]];
ob_start();
?>
<a class="btn btn-outline-success mb-3" href="enquiries.php"><i class="fas fa-arrow-left me-1"></i> All enquiries</a>
<div class="card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2"><div><h2 class="h4 mb-0"><?php echo $h($e['name']); ?></h2><div class="text-muted"><?php echo $h($e['course']); ?></div></div><span class="badge bg-success align-self-start"><?php echo $h($statuses[$e['status']] ?? $e['status']); ?></span></div>
    <hr>
    <div class="row g-3">
        <div class="col-6 col-md-4"><small class="text-muted d-block">Phone</small><?php echo $h($e['phone']); ?></div>
        <div class="col-6 col-md-4"><small class="text-muted d-block">Email</small><?php echo $e['email'] ? $h($e['email']) : '—'; ?></div>
        <div class="col-6 col-md-4"><small class="text-muted d-block">Source</small><?php echo $h($sources[$e['source']] ?? $e['source']); ?></div>
        <div class="col-6 col-md-4"><small class="text-muted d-block">Date</small><?php echo $h($d($e['enquiry_date'])); ?></div>
        <div class="col-6 col-md-4"><small class="text-muted d-block">Follow up</small><?php echo $h($d($e['follow_up_date'])); ?></div>
        <div class="col-6 col-md-4"><small class="text-muted d-block">Added by</small><?php echo $h($e['added_by_name']); ?><?php echo $e['handled_by'] ? ' · taken over by ' . $h($e['handled_by_name']) : ''; ?></div>
    </div>
</div></div>
<div class="card mb-3"><div class="card-header fw-bold">Notes</div><div class="card-body">
    <?php if (!$e['notes']): ?><span class="text-muted">No notes.</span><?php endif; ?>
    <?php foreach ($e['notes'] as $n): ?><div class="mb-3"><strong><?php echo $h($n['author_name']); ?></strong> <small class="text-muted"><?php echo $h($dt($n['created_at'])); ?></small><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?php echo $h($n['body']); ?></div></div><?php endforeach; ?>
</div></div>
<div class="card"><div class="card-header fw-bold">History</div><div class="card-body small">
    <?php foreach ($e['history'] as $x): ?><div class="py-1 border-bottom"><strong><?php echo $h($x['actor_name']); ?></strong> · <?php echo $h($lab[$x['action']] ?? $x['action']); ?><?php
        if ($x['old_value'] !== null && $x['new_value'] !== null) echo ': ' . $h($x['old_value']) . ' → ' . $h($x['new_value']); elseif ($x['new_value'] !== null) echo ': ' . $h($x['new_value']); ?> <span class="text-muted">· <?php echo $h($dt($x['created_at'])); ?></span></div><?php endforeach; ?>
</div></div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
