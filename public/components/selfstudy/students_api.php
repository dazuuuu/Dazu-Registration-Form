<?php
// public/components/selfstudy/students_api.php — the actions of the Students page (POST, JSON, CSRF checked):
//   student   {course, student}                                    the small panel of one student
//   reset     {course, request}  or  {course, student, kind, module, reason, note}    reset one quiz or exam (from a request, or started by the tutor)
//   notnow    {course, request, note}                              "Not now"
// Each call checks on the server that the person may act on that student (their own, or any if they own the course / are admin).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyProgress.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$reply = function (int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $reply(405, ['ok' => false, 'error' => 'POST only.']);
SessionModel::start();
if ((int) SessionModel::get('user_id') <= 0) $reply(401, ['ok' => false, 'error' => 'Please sign in again.']);
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $reply(400, ['ok' => false, 'error' => 'Bad request.']);
if (!csrf_verify($in['t'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) $reply(403, ['ok' => false, 'error' => 'Your session expired. Please refresh the page.']);

$db = \Models\Connection::getInstance()->getConnection();
$viewer = sst_viewer($db, ($sst_portal ?? '') === 'admin' ? 'admin' : 'tutor');          // staff, students, interns and everyone else: refused
if (!$viewer) $reply(403, ['ok' => false, 'error' => 'Only tutors and admin can use this.']);
if (!sst_installed($db) || !ssc_installed($db) || !ssa_installed($db)) $reply(503, ['ok' => false, 'error' => 'Self-study needs the latest updates first.']);

$cid = (int) ($in['course'] ?? 0);
try {
    switch ((string) ($in['action'] ?? '')) {
        case 'student':
            $reply(200, ['ok' => true] + ssd_student_detail($db, $viewer, $cid, (int) ($in['student'] ?? 0)));
        case 'reset':
            $req = (int) ($in['request'] ?? 0);
            $r = ssd_reset($db, $viewer, $cid, $req ?: null, $req ? null : (int) ($in['student'] ?? 0), (string) ($in['kind'] ?? ''), isset($in['module']) && $in['module'] !== '' && $in['module'] !== null ? (int) $in['module'] : null, (string) ($in['reason'] ?? ''), (string) ($in['note'] ?? ''));
            $reply(200, $r);
        case 'notnow':
            $reply(200, ssd_decline($db, $viewer, $cid, (int) ($in['request'] ?? 0), (string) ($in['note'] ?? '')));
        default:
            $reply(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (SscException $e) {
    $reply($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('selfstudy students api: ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
