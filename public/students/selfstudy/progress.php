<?php
// public/students/selfstudy/progress.php — the small, quick JSON calls of the study screen (POST only).
//   video  {course, topic, pos, dur}        a progress report from the player (about every 5 seconds)
//   submit {course, topic, answers}         the six topic questions
//   done   {course, topic}                  finish a topic that has no questions
//   kb     {course, answers}                the Knowledge Check
// Every call checks, on the server: a signed-in student, the CSRF token, the course is published and shared with them, and the topic is open.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyStudy.php';

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
if (ss2_portal_locked($pdo, $uid)) $reply(403, ['ok' => false, 'error' => 'Your portal is locked.']);

$courseId = (int) ($in['course'] ?? 0);
$topicId = (int) ($in['topic'] ?? 0);
if (!ss2_installed($pdo) || !ss_course_for_student($pdo, $courseId, $uid)) $reply(404, ['ok' => false, 'error' => 'That course is not available to you.']);
$action = (string) ($in['action'] ?? '');
$answers = is_array($in['answers'] ?? null) ? $in['answers'] : [];

try {
    if ($action === 'kb') {
        $r = ss2_submit_kb($pdo, $uid, $courseId, $answers);
        $reply($r['ok'] ? 200 : 422, $r);
    }
    $model = ss2_model($pdo, $courseId, $uid);
    $topic = $model['topics'][$topicId] ?? null;
    if (!$topic) $reply(404, ['ok' => false, 'error' => 'That topic was not found.']);
    if (!$topic['open']) $reply(403, ['ok' => false, 'locked' => true, 'error' => $topic['reason']]);
    if ($action === 'video') {
        if (!$topic['video_id'] || !ss_youtube_id($topic['video_id'])) $reply(422, ['ok' => false, 'error' => 'This topic has no video.']);
        if ($topic['done']) $reply(200, ['ok' => true] + ss2_video_state($pdo, $uid, $topicId, $topic['video_duration']));
        $r = ss2_video_tick($pdo, $uid, $courseId, $topicId, $topic['video_duration'], (int) floor((float) ($in['pos'] ?? 0)), (int) floor((float) ($in['dur'] ?? 0)));
        $reply(200, ['ok' => true] + $r);
    }
    if ($action === 'submit') { $r = ss2_submit_topic($pdo, $uid, $courseId, $topicId, $answers); $reply($r['ok'] ? 200 : 422, $r); }
    if ($action === 'done') { $r = ss2_mark_done($pdo, $uid, $courseId, $topicId); $reply($r['ok'] ? 200 : 422, $r); }
    $reply(400, ['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('selfstudy progress: ' . $e->getMessage());
    $reply(500, ['ok' => false, 'error' => 'Something went wrong. Please try again.']);
}
