<?php
// The tutor's OWN private ID photo for their My ID & signature page. Under /tutor/ so it also works in the Tutor portal preview
// (whose session cookie is only sent to /tutor/). Never someone else's: 403. (Admins use id_cards/tutor_photo.php.)
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

function tidph_refuse(int $status, string $reason): void
{
    error_log('[tutor id photo] ' . $status . ' ' . $reason . ' (user ' . (int) ($_SESSION['user_id'] ?? 0) . ', role ' . ($_SESSION['role'] ?? 'none') . ')');
    http_response_code($status);
    exit;
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) tidph_refuse(401, 'not logged in');
$me = (int) $_SESSION['user_id'];
if (isset($_GET['u']) && (int) $_GET['u'] !== $me) tidph_refuse(403, 'asked for another tutor\'s photo');
$db = \Models\Connection::getInstance()->getConnection();
// Any account with a tutor record (also an admin who teaches, whose session role may still be "admin").
if (!staff_tutor($db, $me)) tidph_refuse(403, 'the account is not a tutor');
$abs = idc_student_photo_file($db, $me);
if (!$abs) tidph_refuse(404, 'no photo');
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($abs);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) tidph_refuse(404, 'not an image (' . $mime . ')');
while (ob_get_level()) ob_end_clean();   // nothing but the image bytes (a stray space from an included file breaks the picture)
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($abs);
