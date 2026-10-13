<?php
// Common start for tutor Assignments / Submissions pages: login and role are checked before any input is read.
// Gives $pdo, $am (AssignmentModel), $tutor, $tutorId (tutors.id), $me (users.id).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/assignment_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/AssignmentModel.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentNotifier.php';

use Models\Connection;
use Models\AssignmentModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$am = new AssignmentModel($pdo);
$me = (int) $_SESSION['user_id'];
$tutor = $am->tutorByUser($me);
if (!$tutor) {
    http_response_code(403);
    exit('Your tutor account is not active.');
}
$tutorId = (int) $tutor['id'];

/** Flash + redirect helper for POST handlers. */
function asg_back(string $to, ?string $ok = null, ?string $err = null): void
{
    if ($ok) SessionModel::setFlash('success', htmlspecialchars($ok));
    if ($err) SessionModel::setFlash('error', htmlspecialchars($err));
    header('Location: ' . $to);
    exit();
}

function asg_csrf_or_back(string $to): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) asg_back($to, null, 'Security check failed. Please refresh and try again.');
}

require_once __DIR__ . '/ui.php';
