<?php
// One enquiry (secretary portal): the details, who added it, its notes (who wrote each and when), its history, and what a secretary may do: change the status, add a note, change the follow-up date, take it over.
// No delete. Checked on every request: an active secretary with the Enquiries switch on (On leave is fine).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryEnquiryService.php';

$sec = secretary_require('enquiries');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryEnquiryService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    if ($act === 'delete' || isset($_POST['delete'])) {   // a secretary can never delete an enquiry
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Not allowed. Enquiries are never deleted.');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'The security check failed. Please try again.');
    } else {
        switch ($act) {
            case 'status':    $r = $svc->setStatus($sec, $id, (string) ($_POST['status'] ?? '')); $ok = 'Status saved.'; break;
            case 'follow_up': $r = $svc->setFollowUp($sec, $id, (string) ($_POST['follow_up_date'] ?? '')); $ok = 'Follow-up date saved.'; break;
            case 'note':      $r = $svc->addNote($sec, $id, (string) ($_POST['note'] ?? '')); $ok = 'Note added.'; break;
            case 'take_over': $r = $svc->takeOver($sec, $id); $ok = 'You took this enquiry over.'; break;
            default:          $r = ['ok' => false, 'error' => 'Unknown action.']; $ok = '';
        }
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? $ok : $h($r['error'] ?? 'Could not save.'));
    }
    header('Location: view.php?id=' . $id);
    exit();
}

$e = $svc->get($id);
if (!$e) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('That enquiry was not found.'); }
$statuses = SecretaryEnquiryService::STATUSES;
$sources = SecretaryEnquiryService::SOURCES;
$d = static fn(?string $x): string => $x ? date('D j M Y', strtotime($x)) : '—';
$dt = static fn(?string $x): string => $x ? date('j M Y, g:i a', strtotime($x)) : '';
$mine = (int) ($e['handled_by'] ?: $e['added_by']) === (int) $sec['id'];
$fu = (string) ($e['follow_up_date'] ?? '');
$labelAct = ['created' => 'Added', 'status' => 'Status', 'follow_up' => 'Follow-up date', 'note' => 'Note', 'taken_over' => 'Taken over'];

$page_title = 'Enquiry';
$sp_active = 'enquiries';
ob_start();
require __DIR__ . '/../../components/secretary/enquiries_css.php';
?>
<a class="se-back" href="index.php"><i class="fas fa-arrow-left"></i> All enquiries</a>
<div class="se-card">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
        <div><h2 style="font-size:1.4rem;color:var(--ink);margin:0"><?php echo $h($e['name']); ?></h2><div class="text-muted"><?php echo $h($e['course']); ?></div></div>
        <span class="se-chip <?php echo $h($e['status']); ?>"><?php echo $h($statuses[$e['status']] ?? $e['status']); ?></span>
    </div>
    <div class="se-dl">
        <div><small>Phone</small><a href="tel:<?php echo $h(preg_replace('/[^0-9+]/', '', $e['phone'])); ?>"><?php echo $h($e['phone']); ?></a></div>
        <div><small>Email</small><?php echo $e['email'] ? $h($e['email']) : '—'; ?></div>
        <div><small>Source</small><?php echo $h($sources[$e['source']] ?? $e['source']); ?></div>
        <div><small>Date</small><?php echo $h($d($e['enquiry_date'])); ?></div>
        <div><small>Follow up</small><?php echo $fu === '' ? '—' : ($fu === SecretaryEnquiryService::today() ? '<strong>Today</strong>' : $h($d($fu))); ?></div>
        <div><small>Added by</small><?php echo $h($e['added_by_name']); ?></div>
        <?php if ($e['handled_by']): ?><div><small>Taken over by</small><?php echo $h($e['handled_by_name']); ?><?php if ($e['away'] !== ''): ?><span class="se-away"><?php echo $h($e['away']); ?></span><?php endif; ?></div>
        <?php elseif ($e['away'] !== ''): ?><div><small>Added by is</small><span class="se-away" style="margin:0"><?php echo $h($e['away']); ?></span></div><?php endif; ?>
    </div>
</div>

<div class="se-card">
    <h2>What you can do</h2>
    <div class="se-actions">
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>"><input type="hidden" name="action" value="status">
            <label for="st">Change the status</label>
            <select id="st" name="status"><?php foreach ($statuses as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo $e['status'] === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?></select>
            <button class="se-btn" type="submit">Save status</button>
            <div class="small text-muted mt-2">Registered is set by hand here. It is not linked to any student account.</div></form>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>"><input type="hidden" name="action" value="follow_up">
            <label for="fu">Follow-up date</label><input id="fu" type="date" name="follow_up_date" value="<?php echo $h($fu); ?>">
            <button class="se-btn" type="submit">Save date</button> <span class="small text-muted">Empty clears it.</span></form>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>"><input type="hidden" name="action" value="note">
            <label for="nt">Add a note</label><textarea id="nt" name="note" rows="3" maxlength="1000" required placeholder="What happened? What is next?"></textarea>
            <button class="se-btn" type="submit">Add note</button></form>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>"><input type="hidden" name="action" value="take_over">
            <label>Take over</label>
            <p class="small text-muted"><?php echo $mine ? 'You already have this enquiry.' : 'If the secretary who has it is away, take it over. It stays visible to everyone and the history records it.'; ?></p>
            <button class="se-btn ghost" type="submit" <?php echo $mine ? 'disabled' : ''; ?>><i class="fas fa-hand me-1"></i> Take over</button></form>
    </div>
</div>

<div class="se-card">
    <h2>Notes</h2>
    <?php if (!$e['notes']): ?><div class="text-muted">No notes yet.</div><?php endif; ?>
    <?php foreach ($e['notes'] as $n): ?><div class="se-note"><b><?php echo $h($n['author_name']); ?></b><small><?php echo $h($dt($n['created_at'])); ?></small><p><?php echo $h($n['body']); ?></p></div><?php endforeach; ?>
</div>
<div class="se-card">
    <h2>History</h2>
    <div class="se-hist">
    <?php foreach ($e['history'] as $x): ?>
        <div><b><?php echo $h($x['actor_name']); ?></b> · <?php echo $h($labelAct[$x['action']] ?? $x['action']); ?><?php
            if ($x['old_value'] !== null && $x['new_value'] !== null) echo ': ' . $h($x['old_value']) . ' → ' . $h($x['new_value']); elseif ($x['new_value'] !== null) echo ': ' . $h($x['new_value']); ?>
            <small class="text-muted"> · <?php echo $h($dt($x['created_at'])); ?></small></div>
    <?php endforeach; ?>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
