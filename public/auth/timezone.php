<?php
// POST timezone=<IANA name> — the signed-in person sets (and confirms) their own time zone.
// Used by the first-login prompt (components/classes/tz_prompt.php) and the profile/calendar forms.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/ClassCalendarService.php';

$json = !empty($_POST['ajax']);
$ok = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['logged_in']) && csrf_verify($_POST['csrf_token'] ?? null)
    && cal_set_tz(\Models\Connection::getInstance()->getConnection(), (int) $_SESSION['user_id'], (string) ($_POST['timezone'] ?? ''));
if ($json) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok]);
    exit;
}
if (class_exists('SessionModel')) SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'Time zone saved. All class times now show in it.' : 'Choose a valid time zone.');
$back = (string) ($_POST['back'] ?? '');
header('Location: ' . (preg_match('#^/[^/]#', $back) ? $back : url('')));
