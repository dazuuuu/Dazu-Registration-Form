<?php // Notification bell with the red unread count. Expects $pwaPortal. Filled and kept live by assets/js/portal-pwa.js.
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
$bellUnread = PortalNotifications::unreadCount(\Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0));
?>
<div class="dropdown">
    <button type="button" class="notif-bell" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            aria-label="Notifications" data-notif-toggle="#notifMenu">
        <i class="fas fa-bell"></i>
        <span class="notif-badge <?php echo $bellUnread ? '' : 'd-none'; ?>" data-notif-count><?php echo $bellUnread > 99 ? '99+' : $bellUnread; ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow notif-menu" id="notifMenu" data-notif-menu></div>
</div>
