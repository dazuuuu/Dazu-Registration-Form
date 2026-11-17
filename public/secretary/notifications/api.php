<?php
// The small JSON service behind the bell, the unread count and the phone alerts (turn on, turn off). The existing notification code is only called.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
// A plain JSON "not allowed" for anyone who is not a secretary who may sign in (no redirect for a script request). Left and suspended are refused on every call.
$sec = null;
try { $sec = secretary_by_user(\Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0)); } catch (Throwable $e) { $sec = null; }
if (!$sec || $sec['role_name'] !== 'secretary' || empty($sec['user_active']) || $sec['status'] === 'left') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo '{"ok":false,"error":"not allowed"}';
    exit;
}
require __DIR__ . '/../../components/notifications/api.php';
