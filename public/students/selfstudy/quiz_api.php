<?php
// public/students/selfstudy/quiz_api.php — small JSON calls while an attempt is open (POST only): answer, sync, submit.
// The server keeps the clock: a call on an attempt whose time is up submits it first.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyQuiz.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$reply = function (int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $reply(405, ['ok' => false, 'error' => 'POST only.']);
SessionModel::start();
$uid = (int) SessionModel::get('user_id');
if ($uid <= 0) $reply(401, ['ok' => false, 'error' => 'Please sign in again.']);
if (SessionModel::get('role') !== 'student') $reply(403, ['ok' => false, 'error' => 'Students only.']);
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $reply(400, ['ok' => false, 'error' => 'Bad request.']);
if (!csrf_verify($in['t'] ?? null)) $reply(403, ['ok' => false, 'error' => 'Your session expired. Please refresh the page.']);
$pdo = \Models\Connection::getInstance()->getConnection();
if (!ss3_installed($pdo) || ss2_portal_locked($pdo, $uid)) $reply(403, ['ok' => false, 'error' => 'Not available.']);

$attemptId = (int) ($in['attempt'] ?? 0);
$now = time();
try {
    switch ((string) ($in['action'] ?? '')) {
        case 'answer':
            $r = ss3_save_answer($pdo, $uid, $attemptId, (int) ($in['question'] ?? 0), $in['answer'] ?? null, $now);
            $reply($r['ok'] ? 200 : 422, $r);
        case 'submit':
            $r = ss3_submit($pdo, $uid, $attemptId, $now);
            $reply($r['ok'] ? 200 : 422, ['ok' => $r['ok'], 'error' => $r['error'] ?? null]);
        case 'sync':
            $a = ss2_rows($pdo, 'SELECT * FROM self_study_attempts WHERE id = ? AND student_id = ?', [$attemptId, $uid])[0] ?? null;
            if (!$a) $reply(404, ['ok' => false, 'error' => 'That attempt was not found.']);
            if ($a['status'] === 'in_progress' && ss3_seconds_left($a, $now) === 0) { ss3_finalize($pdo, $attemptId, 'timed_out', $now); $a['status'] = 'timed_out'; }
            $reply(200, ['ok' => true, 'done' => $a['status'] !== 'in_progress', 'seconds_left' => ss3_seconds_left($a, $now)]);
        default:
            $reply(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    error_log('selfstudy quiz_api: ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
