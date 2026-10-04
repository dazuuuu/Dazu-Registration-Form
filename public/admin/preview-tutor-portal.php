<?php
// public/admin/preview-tutor-portal.php — open the tutor portal as a temporary tutor (POST from the admin menu, in a new tab).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/PortalPreviewService.php';

Middleware::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . url('admin/dashboard.php'));
    exit();
}

$pdo = \Models\Connection::getInstance()->getConnection();
$r = PortalPreviewService::startTutor($pdo, (int) ($_SESSION['user_id'] ?? 0));
if (!$r['ok']) {
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><title>Preview</title><body style="font:16px system-ui;padding:32px"><p>' . htmlspecialchars($r['error']) . '</p><p><a href="' . htmlspecialchars(url('admin/dashboard.php')) . '">Back to admin</a></p></body>';
    exit();
}
header('Location: ' . $r['redirect']);
exit();
