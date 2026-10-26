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
if (($_SESSION['role'] ?? '') !== 'tutor') tidph_refuse(403, 'the account is not a tutor');
$me = (int) $_SESSION['user_id'];
if (isset($_GET['u']) && (int) $_GET['u'] !== $me) tidph_refuse(403, 'asked for another tutor\'s photo');
$db = \Models\Connection::getInstance()->getConnection();
$abs = staff_tutor($db, $me) ? idc_student_photo_file($db, $me) : null;
if (!$abs) tidph_refuse(404, 'no photo');
header('Content-Type: ' . (str_ends_with(strtolower($abs), '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($abs);
