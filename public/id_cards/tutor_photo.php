<?php
// A tutor's private ID photo (app/v1/storage/tutor_photos), for their own page and for admins only.
// ?u=<tutor's users.id>. Never a public link; the files themselves cannot be opened from the web.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/TutorIdService.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$who = (int) ($_GET['u'] ?? $me);
// Admins by role name or role number (an admin coming from their tutor portal may still carry the tutor role name).
$isAdmin = tid_is_admin() || (int) ($_SESSION['role_id'] ?? 99) <= 2 || !empty($_SESSION['admin_tutor']);
$refuse = function (string $why) use ($me, $who) {
    error_log('[tutor photo] 404 ' . $why . ' (viewer ' . $me . ', tutor user ' . $who . ', role ' . ($_SESSION['role'] ?? 'none') . ')');
    http_response_code(404);
    exit;
};
if ($who !== $me && !$isAdmin) $refuse('not allowed');
if (!staff_tutor($db, $who)) $refuse('no tutor record');
$abs = idc_student_photo_file($db, $who);
if (!$abs) $refuse('no photo file on the server');
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($abs);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) $refuse('not an image (' . $mime . ')');
while (ob_get_level()) ob_end_clean();   // nothing but the image bytes
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($abs);
