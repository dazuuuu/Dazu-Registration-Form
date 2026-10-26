<?php
// Announcements the signed-in person received. Expects $db. ?a=ID (the notification link) highlights that one.
require_once __DIR__ . '/../../../app/v1/services/AnnouncementService.php';
require __DIR__ . '/styles.php';
$annUid = (int) ($_SESSION['user_id'] ?? 0);
$annOpen = (int) ($_GET['a'] ?? 0);
if ($annOpen) ann_dismiss($db, $annUid, $annOpen);   // opened from the bell or a push: it no longer pops up
$annList = ann_received($db, $annUid);
ann_mark_all_read($db, $annUid);   // read once they are on screen; "new" below is from before this visit
$annCsrf = csrf_token();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$annList): ?>
    <div class="ann-card text-center text-muted py-5"><i class="fas fa-bullhorn fa-2x mb-2 opacity-25"></i><p class="mb-0">No announcements right now.</p></div>
<?php else: foreach ($annList as $a): ?>
    <article class="ann-card <?php echo ($annOpen === (int) $a['id'] || (array_key_exists('read_at', $a) ? !$a['read_at'] : !$a['dismissed_at'])) ? 'new' : ''; ?>" id="ann-<?php echo (int) $a['id']; ?>">
        <div class="d-flex flex-wrap gap-1 mb-1">
            <?php if (($a['priority'] ?? 'normal') !== 'normal'): ?><span class="badge <?php echo $a['priority'] === 'urgent' ? 'bg-danger' : 'bg-warning text-dark'; ?>"><?php echo ucfirst($a['priority']); ?></span><?php endif; ?>
            <?php if (array_key_exists('read_at', $a) && !$a['read_at']): ?><span class="badge bg-success">New</span><?php endif; ?>
            <?php if (!empty($a['deadline'])): ?><span class="badge bg-light text-dark border"><i class="far fa-calendar me-1"></i>By <?php echo $e(date('j M Y', strtotime($a['deadline']))); ?></span><?php endif; ?>
        </div>
        <h3><?php echo $e($a['title']); ?></h3>
        <p class="ann-summary"><?php echo $e($a['summary']); ?></p>
        <?php if ($media = ann_media_html($a)): ?><div class="ann-media"><?php echo $media; ?></div><?php endif; ?>
        <?php if ($a['message']): ?><div class="ann-msg mb-2"><?php echo $e($a['message']); ?></div><?php endif; ?>
        <div class="ann-meta"><i class="fas fa-user me-1"></i><?php echo $e($a['author_name']); ?> · <?php echo $e(date('j M Y, g:i a', strtotime($a['created_at']))); ?>
            <?php if ($a['expires_at']): ?> · disappears <?php echo $e(date('j M Y', strtotime($a['expires_at']))); ?><?php endif; ?></div>
        <?php if (!empty($a['needs_confirm'])): ?>
            <div class="mt-2">
            <?php if (!empty($a['confirmed_at'])): ?><span class="badge bg-success"><i class="fas fa-check me-1"></i>You confirmed attendance</span>
            <?php else: ?><form method="POST" action="<?php echo url('announcements/dismiss.php'); ?>" class="m-0"><input type="hidden" name="csrf_token" value="<?php echo $e($annCsrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>"><input type="hidden" name="action" value="confirm">
                <input type="hidden" name="back" value="<?php echo $e($_SERVER['REQUEST_URI'] ?? ''); ?>">
                <button class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Confirm attendance</button></form><?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
<?php endforeach; endif; ?>
<?php if ($annOpen): ?><script>document.getElementById('ann-<?php echo $annOpen; ?>')?.scrollIntoView({block: 'start'});</script><?php endif; ?>
