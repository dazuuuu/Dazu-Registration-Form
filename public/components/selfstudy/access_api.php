<?php
// public/components/selfstudy/access_api.php — saves the students and the tutor sharing of a course (POST, JSON, CSRF checked).
//   save_students {course, ids[], publish}   the students ticked by this tutor/admin (+ publish a draft)
//   set_editor    {course, tutor, access}    none | view | edit (owner and admin only)
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyAccess.php';

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
$viewer = sst_viewer($db, ($sst_portal ?? '') === 'admin' ? 'admin' : 'tutor');      // students, staff, interns and everyone else: refused
if (!$viewer) $reply(403, ['ok' => false, 'error' => 'Only tutors and admin can use this.']);
if (!sst_installed($db) || !ssc_installed($db) || !ssa_installed($db)) $reply(503, ['ok' => false, 'error' => 'Self-study needs migration 109 first.']);

$cid = (int) ($in['course'] ?? 0);
try {
    switch ((string) ($in['action'] ?? '')) {
        case 'save_students':
            $ids = is_array($in['ids'] ?? null) ? $in['ids'] : [];
            $r = !empty($in['publish']) ? ssa_save_and_publish($db, $viewer, $cid, $ids) : ssa_save_students($db, $viewer, $cid, $ids);
            $reply(200, $r);
        case 'set_editor':
            $reply(200, ssa_set_editor($db, $viewer, $cid, (int) ($in['tutor'] ?? 0), (string) ($in['access'] ?? '')));
        default:
            $reply(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (SscException $e) {
    $reply($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('selfstudy access api: ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
