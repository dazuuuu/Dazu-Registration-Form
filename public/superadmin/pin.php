<?php
// public/superadmin/pin.php — JSON endpoint: verify the PIN (unlock the dashboard) or lock it again.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/superadmin_pin.php';

Middleware::requireSuperPortal();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pin_reply(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pin_reply(['ok' => false, 'error' => 'POST only.'], 405);
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    pin_reply(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.'], 403);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'lock') {
    sa_pin_lock();
    pin_reply(['ok' => true]);
}

if ($action === 'verify') {
    if ($wait = sa_pin_blocked_for()) {
        pin_reply(['ok' => false, 'error' => 'Too many wrong PINs. Try again in ' . ceil($wait / 60) . ' minute(s).'], 429);
    }
    $pin = (string) ($_POST['pin'] ?? '');
    if (sa_pin_valid_format($pin) && sa_pin_verify($pdo, $userId, $pin)) {
        sa_pin_unlock();
        pin_reply(['ok' => true]);
    }
    sa_pin_register_miss();
    pin_reply(['ok' => false, 'error' => 'Wrong PIN.'], 401);
}

pin_reply(['ok' => false, 'error' => 'Unknown action.'], 400);
