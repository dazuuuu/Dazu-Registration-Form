<?php
// The letter of recommendation (PDF) for a student who completed a course with a capstone.
//   student: opens their own letter ONLY after it was released (the student is Ready).
//   tutor:   previews the letter of their own student at any time (a DRAFT watermark until it is released).
//   admin and everyone else: not allowed. The letter text and the tutor's sentence are never shown to the admin.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/CapstoneService.php';
require_once __DIR__ . '/../../app/v1/helpers/completion_letter_pdf.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$courseId = (int) ($_GET['course'] ?? 0);
$draft = false;

function cap_letter_deny(): void
{
    http_response_code(403);
    exit('Not allowed.');
}

if ($role === 'student') {
    $uid = $me;
    $state = cap_release_state($db, $uid, $courseId);          // re-checks the four conditions first
    if (!$state['letter']) cap_letter_deny();
} elseif ($role === 'tutor') {
    $uid = (int) ($_GET['student'] ?? 0);
    $am = new AssignmentModel($db);
    $tutor = $am->tutorByUser($me);
    if (!$uid || !$tutor || !$am->isTutorStudent((int) $tutor['id'], $uid)) cap_letter_deny();
    $state = cap_release_state($db, $uid, $courseId);
    $draft = !$state['letter'];
} else {
    cap_letter_deny();
}
$d = cap_letter_data($db, $uid, $courseId);
if (!$d || (!$draft && !$d['released'])) cap_letter_deny();
if (!$d['result']) $d['result'] = null;   // a draft before the capstone is marked simply leaves the result out
$pdf = cap_letter_pdf($db, $d, $draft);
$name = 'Letter_of_Recommendation_' . trim(preg_replace('/[^A-Za-z0-9_\-]+/', '_', $d['registration_no']), '_') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $pdf;
