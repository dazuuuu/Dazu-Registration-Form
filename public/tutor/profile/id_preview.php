<?php
// The tutor's OWN staff ID card (?doc=card) or sample certificate (?doc=cert) as a watermarked screen preview (a PDF that the page draws on a canvas).
// It lives under /tutor/ on purpose: the "Tutor portal preview" (admin menu) keeps its session cookie only for /tutor/, so a link outside this folder
// (the old id_cards/tutor_preview.php) was opened with the ADMIN's session, which is not a tutor, and answered 404.
// Each tutor (real or the temporary preview tutor) gets only their own. This is never the print file (bleed + crop marks).
// Answers: 401 not logged in, 403 not a tutor / not your preview, 404 no tutor profile. The reason is written to the error log.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

function tidp_refuse(int $status, string $reason, string $message): void
{
    error_log('[tutor id preview] ' . $status . ' ' . $reason . ' (user ' . (int) ($_SESSION['user_id'] ?? 0) . ', role ' . ($_SESSION['role'] ?? 'none')
        . ', session ' . session_name() . ', asked for tutor ' . (int) ($_GET['tutor'] ?? 0) . ')');
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    exit($message);
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    tidp_refuse(401, 'not logged in', 'Your session has ended. Log in again.');
}
if (($_SESSION['role'] ?? '') !== 'tutor') {
    tidp_refuse(403, 'the account is not a tutor', 'This preview is only for tutors.');
}
$me = (int) $_SESSION['user_id'];
if (isset($_GET['tutor']) && (int) $_GET['tutor'] !== $me) {
    tidp_refuse(403, 'asked for another tutor\'s preview', 'This preview is not yours.');
}
$db = \Models\Connection::getInstance()->getConnection();
$t = staff_tutor($db, $me);
if (!$t) {
    tidp_refuse(404, 'no tutors row for this user', 'Your tutor profile was not found. Ask the office.');
}
$pdf = (($_GET['doc'] ?? 'card') === 'cert') ? tid_sample_certificate_pdf($db, $t) : tid_card_preview_pdf($db, $t);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $pdf;
