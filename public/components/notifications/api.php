<?php
// JSON API behind the bell and the installed app (tutor/notifications/api.php, students/notifications/api.php).
// GET  ?action=count | list | vapid      POST action=read&id= | read_all | subscribe | unsubscribe (CSRF required)
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$out = static function (array $data, int $code = 200) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!$input && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
    }
    if (!csrf_verify($input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) $out(['ok' => false, 'error' => 'csrf'], 403);
    switch ($input['action'] ?? '') {
        case 'read':
            PortalNotifications::markRead($db, $uid, (int) ($input['id'] ?? 0));
            break;
        case 'read_all':
            PortalNotifications::markAllRead($db, $uid);
            break;
        case 'subscribe':
            if (!PortalNotifications::saveSubscription($db, $uid, (array) ($input['subscription'] ?? []))) $out(['ok' => false, 'error' => 'bad subscription'], 422);
            break;
        case 'unsubscribe':
            PortalNotifications::deleteSubscription($db, $uid, (string) ($input['endpoint'] ?? ''));
            break;
        default:
            $out(['ok' => false, 'error' => 'unknown action'], 400);
    }
    $out(['ok' => true, 'unread' => PortalNotifications::unreadCount($db, $uid)]);
}

switch ($_GET['action'] ?? 'count') {
    case 'vapid':
        $out(['ok' => WebPush::supported(), 'key' => WebPush::supported() ? WebPush::vapidKeys($db)['public'] : null]);
    case 'list':
        $items = array_map(static fn($n) => [
            'id' => (int) $n['id'], 'title' => $n['title'], 'body' => (string) $n['body'], 'read' => (bool) $n['is_read'],
            'ago' => PortalNotifications::ago($n['created_at']),
            'icon' => $n['icon'] ? settings_asset_url($n['icon']) : null,
        ], PortalNotifications::latest($db, $uid, min(50, max(1, (int) ($_GET['limit'] ?? 10)))));
        $out(['ok' => true, 'unread' => PortalNotifications::unreadCount($db, $uid), 'items' => $items]);
    default:
        $latest = PortalNotifications::latest($db, $uid, 1);
        $out(['ok' => true, 'unread' => PortalNotifications::unreadCount($db, $uid), 'latest_id' => (int) ($latest[0]['id'] ?? 0)]);
}
