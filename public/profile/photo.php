<?php
// Any user's photo for avatars (?u=users.id). Signed-in users only, and only people the viewer may see
// (see user_photo_allowed). Files are read from private or public storage; the real path is never shown.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/helpers/user_photo.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$who = (int) ($_GET['u'] ?? $me);
$abs = user_photo_allowed($db, $me, (int) ($_SESSION['role_id'] ?? 99), $who) ? idc_student_photo_file($db, $who) : null;
if (!$abs) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($abs);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
while (ob_get_level()) ob_end_clean();   // nothing but the image bytes
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=86400');   // the URL changes (v=…) whenever the photo changes
header('X-Content-Type-Options: nosniff');
readfile($abs);
