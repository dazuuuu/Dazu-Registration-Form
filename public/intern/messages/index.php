<?php
// Simple messages between the intern and their supervisor.
require __DIR__ . '/../_boot.php';

$self = url('intern/messages/index.php');
if (intern_post_ok()) {
    $r = $intern['supervisor_id'] ? in_message_send($db, $intern, (string) ($_POST['body'] ?? '')) : ['ok' => false, 'error' => 'You have no supervisor yet.'];
    if (!$r['ok']) SessionModel::setFlash('error', in_e($r['error']));
    header('Location: ' . $self . '#end');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$messages = in_messages($db, (int) $intern['id']);
in_messages_mark_read($db, $intern, true);
$me = (int) $intern['user_id'];
$page_title = 'Messages';
$page_subtitle = $intern['supervisor_name'] ? 'With ' . $intern['supervisor_name'] : 'No supervisor yet';
ob_start();
?>
<div class="ip-card" style="max-width:820px">
    <div class="ip-head"><h3><i class="fas fa-message text-success me-2"></i><?php echo in_e($intern['supervisor_name'] ?: 'Supervisor'); ?></h3></div>
    <div id="msgBox" style="max-height:60vh;overflow:auto;padding:4px">
        <?php if (!$messages): ?><div class="ip-muted text-center py-4">No messages yet. Say hello to your supervisor.</div><?php endif; ?>
        <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === $me; ?>
            <div class="ip-msg <?php echo $mine ? 'me' : 'them'; ?>"><?php echo in_e($m['body']); ?><small><?php echo $mine ? 'You' : in_e($m['sender_name']); ?> · <?php echo date('j M, g:i A', strtotime($m['created_at'])); ?></small></div>
        <?php endforeach; ?>
        <div id="end"></div>
    </div>
    <?php if ($intern['supervisor_id']): ?>
    <form method="POST" class="d-flex gap-2 mt-3"><?php echo csrf_field(); ?>
        <textarea name="body" class="form-control" rows="2" placeholder="Write a message…" required maxlength="4000"></textarea>
        <button class="btn btn-success px-4"><i class="fas fa-paper-plane"></i></button></form>
    <?php endif; ?>
</div>
<script>var b = document.getElementById('msgBox'); if (b) b.scrollTop = b.scrollHeight;</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
