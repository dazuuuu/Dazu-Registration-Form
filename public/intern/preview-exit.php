<?php
// public/intern/preview-exit.php — end an intern-portal preview: the temporary intern and its sample work are deleted and its session destroyed.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/PortalPreviewService.php';

if (!empty($_SESSION['preview'])) {
    PortalPreviewService::end(\Models\Connection::getInstance()->getConnection());
}
// Back to the admin portal (its session was never touched). The page below is only a fallback if the redirect is blocked.
header('Cache-Control: no-store');
header('Location: ' . url('admin/dashboard.php'));
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Preview ended</title>
<body style="font:16px system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6f8;color:#0f172a">
<div style="text-align:center;padding:24px"><h2 style="margin:0 0 8px">Preview ended</h2>
<p style="color:#64748b;margin:0 0 20px">The temporary intern account has been deleted. Taking you back to the admin portal…</p>
<a href="<?php echo htmlspecialchars(url('admin/dashboard.php')); ?>" style="color:#15a24a;font-weight:600">Back to the admin portal</a></div></body></html>
