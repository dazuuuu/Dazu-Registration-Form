<?php
// Streams a capstone file from private storage, only to people allowed to see it:
//   t=item       a file a student sent (id = capstone_submission_items.id): the student (or a team member) and the tutor of the capstone;
//   t=milestone  a file sent for a milestone (id = capstone_milestone_submissions.id): same people;
//   t=example    the tutor's example file (id = capstones.id): the tutor, and a student only when the capstone is open for them
//                and they ticked "I understand this is only an example".
// The admin can never open a capstone file. Anything else is "not found", so nobody can probe ids.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/CapstoneService.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$am = new AssignmentModel($db);
$uid = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$tutor = $role === 'tutor' ? $am->tutorByUser($uid) : null;
$id = (int) ($_GET['id'] ?? 0);

function cap_deny(): void
{
    http_response_code(404);
    exit('File not found.');
}

/** May this user see work of the given owner attempt? Student/team member, or the capstone's own tutor. Never an admin. */
$mayView = function (array $capRow, array $ownerAttempt) use ($db, $uid, $tutor): bool {
    if ($tutor && (int) $capRow['tutor_id'] === (int) $tutor['id']) return true;
    foreach (cap_group_attempts($db, $ownerAttempt) as $g) if ((int) $g['student_user_id'] === $uid) return true;
    return false;
};

$rel = null;
$name = 'file';
$t = (string) ($_GET['t'] ?? '');
if ($t === 'item' || $t === 'milestone') {
    if ($t === 'item') {
        $f = cap_item($db, $id);
        $sub = $f ? $db->prepare('SELECT attempt_id FROM capstone_submissions WHERE id = ?') : null;
        if (!$f || !$f['file_path'] || !$sub) cap_deny();
        $sub->execute([(int) $f['submission_id']]);
        $attemptId = (int) $sub->fetchColumn();
        $rel = $f['file_path'];
        $name = $f['file_name'] ?: 'file.pdf';
    } else {
        $st = $db->prepare('SELECT * FROM capstone_milestone_submissions WHERE id = ?');
        $st->execute([$id]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        if (!$f || !$f['file_path']) cap_deny();
        $attemptId = (int) $f['attempt_id'];
        $rel = $f['file_path'];
        $name = $f['file_name'] ?: 'file.pdf';
    }
    $owner = cap_attempt($db, $attemptId);
    $capRow = $owner ? cap_get($db, (int) $owner['capstone_id']) : null;
    if (!$owner || !$capRow || !$mayView($capRow, $owner)) cap_deny();
} elseif ($t === 'example') {
    $capRow = cap_get($db, $id);
    if (!$capRow || !$capRow['example_file']) cap_deny();
    $ok = $tutor && (int) $capRow['tutor_id'] === (int) $tutor['id'];
    if (!$ok && $role === 'student') {
        $row = cap_for_student($db, $id, $uid);
        $ok = $row && cap_is_open_for($row) && cap_example_acked($db, $id, $uid);
    }
    if (!$ok) cap_deny();
    $rel = $capRow['example_file'];
    $name = $capRow['example_file_name'] ?: 'example.pdf';
} else {
    cap_deny();
}

$abs = asg_file_abs((string) $rel);
if (!$abs) cap_deny();
$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mimes = ['pdf' => 'application/pdf', 'zip' => 'application/zip',
          'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: ' . ($ext === 'pdf' && empty($_GET['download']) ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $name) . '"');
header('Content-Length: ' . filesize($abs));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($abs);
