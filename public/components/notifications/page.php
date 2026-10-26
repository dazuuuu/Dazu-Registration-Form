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
    $act = $_POST['action'] ?? 'read_all';
    if ($act === 'choices') {
        try {
            PortalNotifications::saveChoices($db, $uid, array_map('strval', (array) ($_POST['on'] ?? [])));
            $_SESSION['flash']['success'] = 'Saved. You will only be told about what you switched on.';
        } catch (Throwable $e) {
            $_SESSION['flash']['error'] = 'Could not save. Ask the office to run System Updates (migration 087).';
        }
    } elseif ($act === 'delete_all') {
        PortalNotifications::deleteAll($db, $uid);
    } else {
        PortalNotifications::markAllRead($db, $uid);
    }
    header('Location: ' . url($notifBase . '/notifications/index.php'));
    exit;
}
$choices = PortalNotifications::choices($db, $uid);
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
        <?php if ($items): ?><form method="POST" class="m-0" onsubmit="return confirm('Delete all your notifications?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_all"><button class="btn btn-sm btn-outline-danger">Delete all</button></form><?php endif; ?>
    </div>
</div>
<?php if ($choices !== null): ?>
<details class="card border-0 shadow-sm mb-3" <?php echo $choices ? '' : 'open'; ?>>
    <summary class="card-body py-3 fw-semibold" style="cursor:pointer"><i class="fas fa-sliders me-2 text-success"></i>Choose what you are told about
        <span class="small text-muted fw-normal ms-1"><?php echo $choices ? count($choices) . ' switched on' : 'everything is off'; ?></span></summary>
    <form method="POST" class="card-body pt-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="choices">
        <p class="small text-muted">You only get notifications (here and on your phone) for what you switch on. Everything else is not sent at all.</p>
        <div class="row g-2">
        <?php foreach (PortalNotifications::CATEGORIES as $key => [$label]): ?>
            <div class="col-md-6"><div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="on[]" value="<?php echo htmlspecialchars($key); ?>" id="nc_<?php echo htmlspecialchars($key); ?>" <?php echo !empty($choices[$key]) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="nc_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label></div></div>
        <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-success mt-3">Save my choices</button>
    </form>
</details>
<?php endif; ?>
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
