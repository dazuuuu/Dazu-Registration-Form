<?php
// public/components/staffcalendar/action.php — saves or deletes an event (POST only). Checked here: portal and real role (boot.php), the security token,
// and, for a change or delete, that the person is the owner (or the super admin) and that a private event is theirs (scal_save / scal_delete).
// The form sends it with fetch and Accept: application/json and gets {ok, error, go}; a plain form post still works and goes back to the calendar.
require __DIR__ . '/boot.php';

$wantsJson = stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$view = (string) ($_POST['return_view'] ?? '');
$day = (string) ($_POST['return_d'] ?? '');
$goUrl = function (string $d, string $v) use ($sc_base, $sc_portal): string {
    if ((int) ($_POST['return_project'] ?? 0) > 0) return url($sc_portal . '/staffcalendar/project.php') . '?id=' . (int) $_POST['return_project'];   // a change made on the project page goes back to it
    $q = [];
    if (in_array($v, ['month', 'week', 'day', 'agenda'], true)) $q['view'] = $v;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $q['d'] = $d;
    return $sc_base . ($q ? '?' . http_build_query($q) : '');
};
$finish = function (bool $ok, string $msg, int $status, string $d = '') use ($wantsJson, $goUrl, $view, $day): void {
    SessionModel::setFlash($ok ? 'success' : 'error', htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'));
    if ($wantsJson) {
        if ($ok === false) SessionModel::getFlash('error');   // the window shows the message itself; do not repeat it on the next page
        http_response_code($ok ? 200 : $status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'error' => $ok ? '' : $msg, 'go' => $goUrl($d !== '' ? $d : $day, $view)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . $goUrl($d !== '' ? $d : $day, $view));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Use the form on the calendar page.'); }
// a picture bigger than the server accepts makes PHP drop the whole form, token included
$max = (int) ini_get('post_max_size'); $unit = strtoupper(substr(trim((string) ini_get('post_max_size')), -1));
$max = $unit === 'G' ? $max * 1073741824 : ($unit === 'M' ? $max * 1048576 : ($unit === 'K' ? $max * 1024 : $max));
if (!$_POST && $max > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) $finish(false, 'That picture is too big. Use one of 5 MB or less.', 413);
if (!csrf_verify($_POST['csrf_token'] ?? null)) $finish(false, 'Security check failed. Please reload the page and try again.', 403);
if (!$sc_ready) $finish(false, 'The Staff Calendar is not installed yet.', 503);

$id = (int) ($_POST['id'] ?? 0);
if (($_POST['op'] ?? '') === 'prefs_save') {   // "My settings": the logged-in person's own switches. Always their own: the person is never taken from the request.
    require_once __DIR__ . '/../../../app/v1/services/StaffSettings.php';
    header('Content-Type: application/json; charset=utf-8');
    if (!sst_installed($db)) { http_response_code(503); echo json_encode(['ok' => false, 'error' => 'Settings are not installed yet.']); exit; }
    sst_user_save($db, $actor, $_POST);
    echo json_encode(['ok' => true]);
    exit;
}
if (in_array($_POST['op'] ?? '', ['task_save', 'task_delete', 'task_status'], true)) {   // a project's tasks: who may do what is checked again in StaffProjects on every call
    require_once __DIR__ . '/../../../app/v1/services/StaffProjects.php';
    if (!spj_installed($db)) $finish(false, 'Projects are not installed yet.', 503);
    $eid = (int) ($_POST['event'] ?? 0);
    switch ($_POST['op']) {
        case 'task_save':   $r = spj_task_save($db, $actor, $eid, $_POST); $okMsg = (int) ($_POST['task'] ?? 0) > 0 ? 'Task saved.' : 'Task added.'; break;
        case 'task_delete': $r = spj_task_delete($db, $actor, $eid, (int) ($_POST['task'] ?? 0)); $okMsg = 'Task deleted.'; break;
        default:            $r = spj_task_status($db, $actor, $eid, (int) ($_POST['task'] ?? 0), (string) ($_POST['status'] ?? '')); $okMsg = 'Saved.';
    }
    $err = (string) ($r['error'] ?? '');
    $finish(!empty($r['ok']), !empty($r['ok']) ? $okMsg : $err, strpos($err, 'Only') === 0 ? 403 : (strpos($err, 'not found') !== false ? 404 : 422));
}
if (in_array($_POST['op'] ?? '', ['gift_send', 'gift_undo', 'gift_confirm', 'gift_remind', 'gift_note_dismiss'], true)) {   // the gift tracker: every tap is checked again in StaffGifts (who is asking, which row)
    require_once __DIR__ . '/../../../app/v1/services/StaffGifts.php';
    if (!sg_installed($db)) $finish(false, 'The gift tracker is not installed yet.', 503);
    $lid = (int) ($_POST['list'] ?? 0);
    switch ($_POST['op']) {
        case 'gift_send':    $r = sg_send($db, $actor, $lid); $okMsg = 'Marked as sent. Thank you!'; break;
        case 'gift_undo':    $r = sg_undo($db, $actor, $lid); $okMsg = 'Undone.'; break;
        case 'gift_confirm': $r = sg_confirm($db, $actor, $lid, (int) ($_POST['giver'] ?? 0)); $okMsg = 'Marked as received.'; break;
        case 'gift_remind':  $r = sg_remind($db, $actor, $lid); $okMsg = 'A gentle reminder was sent' . (isset($r['sent']) ? ' to ' . (int) $r['sent'] . ' ' . ((int) $r['sent'] === 1 ? 'person' : 'people') : '') . '.'; break;
        default:             $r = sg_note_dismiss($db, $actor, (int) ($_POST['note'] ?? 0)); $okMsg = 'Done.';
    }
    $err = (string) ($r['error'] ?? '');
    $finish(!empty($r['ok']), !empty($r['ok']) ? $okMsg : $err, !empty($r['already']) ? 409 : (strpos($err, 'Only') === 0 ? 403 : 422));
}
if (in_array($_POST['op'] ?? '', ['bday_save', 'bday_remove', 'suggest'], true)) {   // birthdays and message suggestions: tutors and the super admin (the person is checked again in the service)
    require_once __DIR__ . '/../../../app/v1/services/StaffBirthdays.php';
    if (!sbd_installed($db)) $finish(false, 'Birthdays are not installed yet.', 503);
    if ($_POST['op'] === 'bday_save') { $r = sbd_save($db, $actor, $_POST); $finish($r['ok'], $r['ok'] ? 'Birthday saved.' : (string) ($r['error'] ?? ''), 422); }
    if ($_POST['op'] === 'bday_remove') { $r = sbd_remove($db, $actor, $_POST); $finish(true, 'Birthday removed.', 200); }
    $r = sbd_suggest($db, $actor, $_POST);
    $finish($r['ok'], $r['ok'] ? 'Thank you. Your suggestion is waiting for the super admin.' : (string) ($r['error'] ?? ''), 422);
}
if (($_POST['op'] ?? '') === 'tick') {   // a checklist tick in the details panel: any tutor or the super admin who can see the event; saved at once
    $r = scal_tick($db, $actor, $id, (int) ($_POST['item'] ?? 0), !empty($_POST['done']));
    if ($wantsJson) {
        http_response_code($r['ok'] ? 200 : 404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($r['ok'] ? ['ok' => true, 'done' => $r['done']] : ['ok' => false, 'error' => $r['error']]);
        exit;
    }
    $finish($r['ok'], $r['ok'] ? 'Saved.' : $r['error'], 404);
}
if (($_POST['op'] ?? '') === 'delete') {
    $r = scal_delete($db, $actor, $id);
    $finish($r['ok'], $r['ok'] ? 'Deleted “' . $r['title'] . '”.' : (string) $r['error'], ($r['error'] ?? '') === 'That event was not found.' ? 404 : 403);
}
$r = scal_save($db, $actor, $_POST, $_FILES['cover'] ?? null, $id);
if (!$r['ok']) $finish(false, $r['error'], in_array($r['error'], ['That event was not found.'], true) ? 404 : (strpos($r['error'], 'Only the person') === 0 ? 403 : 422));
// a new event: show the day it is on; a change: stay where the person is
$finish(true, $id > 0 ? 'Event saved.' : 'Event added.', 200, $id > 0 ? $day : (string) ($_POST['start_date'] ?? $day));
