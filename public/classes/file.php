<?php
// Serves a class material from private storage. Allowed: the tutor who owns the class, or a student of that class
// (materials and notes show to the class's students as soon as they are added, before or after the class).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/ClassCalendarService.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) $_SESSION['user_id'];
$st = $db->prepare('SELECT m.*, c.tutor_id, c.end_utc FROM class_materials m JOIN classes c ON c.id = m.class_id WHERE m.id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$m = $st->fetch(PDO::FETCH_ASSOC);
$ok = false;
if ($m) {
    if ((int) $m['tutor_id'] === $uid) {
        $ok = true;
    } elseif (($_SESSION['role'] ?? '') === 'student') {
        $ok = (bool) cal_student_class($db, $uid, (int) $m['class_id']);
    }
}
$abs = $ok ? cal_material_abs($m['file_path']) : null;
if (!$abs) {
    http_response_code(404);
    exit('File not found.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($abs) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^\w .()-]+/u', '_', $m['title']) . '.' . pathinfo($abs, PATHINFO_EXTENSION) . '"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
