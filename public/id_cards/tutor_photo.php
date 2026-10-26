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
$abs = ($who === $me || tid_is_admin()) && staff_tutor($db, $who) ? idc_student_photo_file($db, $who) : null;
if (!$abs) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . (str_ends_with(strtolower($abs), '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($abs);
