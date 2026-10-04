<?php
// Full notification list for a portal. Expects $notifBase ('tutor' or 'students'). ?open=ID marks it read and follows its link.
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_SESSION['user_id'] ?? 0);

if (isset($_GET['open'])) {
    $n = PortalNotifications::markRead($db, $uid, (int) $_GET['open']);
    // Only links stored by the system itself are followed (always app-relative paths).
    $to = $n && $n['url'] && !preg_match('#^[a-z]+:|^//#i', $n['url']) ? url(ltrim($n['url'], '/')) : url($notifBase . '/notifications/index.php');
    header('Location: ' . $to);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    PortalNotifications::markAllRead($db, $uid);
    header('Location: ' . url($notifBase . '/notifications/index.php'));
    exit;
}
$page = max(1, (int) ($_GET['p'] ?? 1));
$items = PortalNotifications::latest($db, $uid, 30, ($page - 1) * 30);
$unread = PortalNotifications::unreadCount($db, $uid);
$page_title = 'Notifications';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="fas fa-bell text-success me-2"></i>Notifications <?php if ($unread): ?><span class="badge bg-danger"><?php echo $unread; ?> unread</span><?php endif; ?></h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-success d-none" data-pwa-enable-push><i class="fas fa-bell me-1"></i>Turn on phone alerts</button>
        <?php if ($unread): ?><form method="POST" class="m-0"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-secondary">Mark all as read</button></form><?php endif; ?>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <?php if (!$items): ?>
        <p class="text-muted p-4 mb-0 text-center">No notifications yet.</p>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($items as $n): ?>
        <a href="?open=<?php echo (int) $n['id']; ?>" class="list-group-item list-group-item-action d-flex gap-3 align-items-start <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
            <?php if ($n['icon']): ?><img src="<?php echo htmlspecialchars(settings_asset_url($n['icon'])); ?>" alt="" class="rounded-circle flex-shrink-0" style="width:40px;height:40px;object-fit:cover">
            <?php else: ?><span class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px"><i class="fas fa-bell"></i></span><?php endif; ?>
            <span class="flex-grow-1">
                <span class="d-flex justify-content-between gap-2"><strong class="<?php echo $n['is_read'] ? 'fw-normal' : ''; ?>"><?php echo htmlspecialchars($n['title']); ?></strong>
                    <small class="text-muted text-nowrap"><?php echo PortalNotifications::ago($n['created_at']); ?></small></span>
                <?php if ($n['body']): ?><span class="d-block small text-muted"><?php echo htmlspecialchars($n['body']); ?></span><?php endif; ?>
            </span>
            <?php if (!$n['is_read']): ?><span class="badge bg-danger rounded-pill align-self-center">new</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php if (count($items) === 30): ?><a class="btn btn-link mt-2" href="?p=<?php echo $page + 1; ?>">Older →</a><?php endif; ?>
<?php
$content = ob_get_clean();
