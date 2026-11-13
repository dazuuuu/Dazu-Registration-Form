<?php
// public/components/selfstudy/api.php — the small JSON / upload calls of the Content and Questions tabs (POST only).
// Every call checks, on the server: a signed-in tutor or admin, the CSRF token, and the permission on the course (owner / "Can edit" to change, "Can view" and admin to read).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyContent.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$reply = function (int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; };

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $reply(405, ['ok' => false, 'error' => 'POST only.']);
SessionModel::start();
if ((int) SessionModel::get('user_id') <= 0) $reply(401, ['ok' => false, 'error' => 'Please sign in again.']);
$isMultipart = stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') !== false;
$in = $isMultipart ? $_POST : json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) $reply(400, ['ok' => false, 'error' => 'Bad request.']);
if (!csrf_verify($in['t'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) $reply(403, ['ok' => false, 'error' => 'Your session expired. Please refresh the page.']);

$db = \Models\Connection::getInstance()->getConnection();
$viewer = sst_viewer($db, ($sst_portal ?? '') === 'admin' ? 'admin' : 'tutor');
if (!$viewer) $reply(403, ['ok' => false, 'error' => 'Only tutors and admin can use this.']);
if (!sst_installed($db) || !ssc_installed($db)) $reply(503, ['ok' => false, 'error' => 'Self-study needs migration 108 first.']);

$cid = (int) ($in['course'] ?? 0);
$id = (int) ($in['id'] ?? 0);
try {
    switch ((string) ($in['action'] ?? '')) {
        case 'outline':
            ssc_guard($db, $viewer, $cid, false);
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'add_module':
            $newId = ssc_add_module($db, $viewer, $cid, (string) ($in['title'] ?? ''));
            $reply(200, ['ok' => true, 'id' => $newId, 'outline' => ssc_outline($db, $cid)]);
        case 'add_topic':
            $mod = isset($in['module']) && $in['module'] !== '' && $in['module'] !== null ? (int) $in['module'] : null;
            $newId = ssc_add_topic($db, $viewer, $cid, $mod, (string) ($in['title'] ?? ''));
            $reply(200, ['ok' => true, 'id' => $newId, 'outline' => ssc_outline($db, $cid)]);
        case 'rename':
            ssc_rename($db, $viewer, $cid, (string) ($in['kind'] ?? ''), $id, (string) ($in['title'] ?? ''));
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'reorder':
            ssc_reorder($db, $viewer, $cid, (string) ($in['kind'] ?? ''), (array) ($in['ids'] ?? []), isset($in['module']) ? (int) $in['module'] : null);
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'archive':
            ssc_archive($db, $viewer, $cid, (string) ($in['kind'] ?? ''), $id);
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'restore':
            ssc_restore($db, $viewer, $cid, (string) ($in['kind'] ?? ''), $id);
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'delete':
            if (empty($in['confirm'])) $reply(422, ['ok' => false, 'error' => 'Please confirm the deletion.']);
            ssc_delete($db, $viewer, $cid, (string) ($in['kind'] ?? ''), $id);
            $reply(200, ['ok' => true, 'outline' => ssc_outline($db, $cid)]);
        case 'load_topic':
            $reply(200, ['ok' => true, 'topic' => ssc_load_topic($db, $viewer, $cid, $id)]);
        case 'save_topic':
            $r = ssc_save_topic($db, $viewer, $cid, $id, $in);
            $reply($r['ok'] ? 200 : 409, $r);
        case 'upload_image':
            $r = ssc_upload_image($db, $viewer, $cid, $_FILES['file'] ?? null);
            $reply($r['ok'] ? 200 : 422, $r);
        case 'attach_file':
            $r = ssc_attach_file($db, $viewer, $cid, $id, $_FILES['file'] ?? null);
            $reply($r['ok'] ? 200 : 422, $r);
        case 'remove_file':
            ssc_remove_file($db, $viewer, $cid, $id);
            $reply(200, ['ok' => true]);
        case 'questions':
            $reply(200, ['ok' => true] + ssc_questions($db, $viewer, $cid, (string) ($in['bank'] ?? '')));
        case 'save_question':
            $qid = ssc_save_question($db, $viewer, $cid, (string) ($in['bank'] ?? ''), (array) ($in['question'] ?? []));
            $reply(200, ['ok' => true, 'id' => $qid] + ssc_questions($db, $viewer, $cid, (string) $in['bank']) + ['outline' => ssc_outline($db, $cid)]);
        case 'delete_question':
            $archived = ssc_delete_question($db, $viewer, $cid, $id);
            $reply(200, ['ok' => true, 'archived' => $archived] + ssc_questions($db, $viewer, $cid, (string) ($in['bank'] ?? '')) + ['outline' => ssc_outline($db, $cid)]);
        case 'reorder_questions':
            ssc_reorder_questions($db, $viewer, $cid, (string) ($in['bank'] ?? ''), (array) ($in['ids'] ?? []));
            $reply(200, ['ok' => true] + ssc_questions($db, $viewer, $cid, (string) $in['bank']));
        case 'answers_to_mark':
            $reply(200, ['ok' => true, 'answers' => ssc_answers_to_mark($db, $viewer, $cid)]);
        case 'mark_answer':
            ssc_mark_answer($db, $viewer, $cid, $id, (string) ($in['comment'] ?? ''));
            $reply(200, ['ok' => true, 'answers' => ssc_answers_to_mark($db, $viewer, $cid)]);
        default:
            $reply(400, ['ok' => false, 'error' => 'Unknown action.']);
    }
} catch (SscException $e) {
    $reply($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('selfstudy api: ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
