<?php
// Streams an assignment attachment (t=att) or a student's submitted image/file (t=sub) from private storage,
// only to people allowed to see it: admins, the owning tutor, the student who sent it, or (attachments) a student
// who was given the assignment and has it unlocked.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/assignment_files.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/models/AssignmentModel.php';
require_once __DIR__ . '/../../app/v1/helpers/open_submissions.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$am = new AssignmentModel($db);
$uid = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = in_array(SessionModel::get('role'), ['admin', 'superadmin'], true);
$tutor = $am->tutorByUser($uid);
$id = (int) ($_GET['id'] ?? 0);

function asg_deny(): void
{
    http_response_code(404);
    exit('File not found.');
}

if (($_GET['t'] ?? '') === 'att') {
    $f = $am->attachment($id);
    if (!$f || !$f['file_path']) asg_deny();
    $a = $am->get((int) $f['assignment_id']);
    $ok = $isAdmin || ($tutor && (int) $a['tutor_id'] === (int) $tutor['id']);
    if (!$ok) {
        $item = $am->itemForStudent($uid, (int) $a['id']);
        $ok = $item && $item['state'] !== 'locked';
    }
    $name = $f['original_name'] ?: $f['label'];
    $mime = $f['kind'] === 'pdf' ? 'application/pdf' : (str_ends_with($f['file_path'], '.png') ? 'image/png' : 'image/jpeg');
} elseif (($_GET['t'] ?? '') === 'aud') {
    // A recorded answer (id = submission id): the student who sent it, or their tutor. Never admin.
    $sub = $am->submissionById($id);
    if (!$sub || empty($sub['audio_path'])) asg_deny();
    $a = $am->get((int) $sub['assignment_id']);
    $ok = (int) $sub['student_user_id'] === $uid || in_array($uid, $am->groupMembers((int) $a['id'], (int) $sub['student_user_id']), true)
        || ($tutor && (int) $a['tutor_id'] === (int) $tutor['id'] && $am->isTutorStudent((int) $tutor['id'], (int) $sub['student_user_id']));
    $f = ['file_path' => $sub['audio_path']];
    $ext = pathinfo((string) $sub['audio_path'], PATHINFO_EXTENSION);
    $name = 'answer.' . $ext;
    $mime = ['webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg'][$ext] ?? 'application/octet-stream';
} elseif (($_GET['t'] ?? '') === 'open') {
    // Open submission document: the student who sent it, the tutor it was sent to (still teaching them), or an admin.
    $f = os_get($db, $id);
    if (!$f || !$f['file_path']) asg_deny();
    $ok = $isAdmin || (int) $f['student_user_id'] === $uid
        || ($tutor && (int) $f['tutor_id'] === (int) $tutor['id'] && $am->isTutorStudent((int) $tutor['id'], (int) $f['student_user_id']));
    $name = $f['original_name'];
    $mime = $f['mime'];
} else {
    $f = $am->file($id);
    if (!$f) asg_deny();
    $a = $am->get((int) $f['assignment_id']);
    $ok = $isAdmin || (int) $f['student_user_id'] === $uid
        || in_array($uid, $am->groupMembers((int) $a['id'], (int) $f['student_user_id']), true)   // group work: members share it
        || ($tutor && (int) $a['tutor_id'] === (int) $tutor['id'] && $am->isTutorStudent((int) $tutor['id'], (int) $f['student_user_id']));
    $name = $f['original_name'];
    $mime = $f['mime'];
}
if (!$ok) asg_deny();
$path = asg_file_abs((string) $f['file_path']);
if (!$path) asg_deny();

$inline = !isset($_GET['download']) && in_array($mime, ['image/png', 'image/jpeg', 'application/pdf', 'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg'], true);
$safe = preg_replace('/[^A-Za-z0-9 _.()-]/', '_', (string) $name) ?: 'file';
header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safe . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
