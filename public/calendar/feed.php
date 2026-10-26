<?php
// Private calendar feed (.ics) for one student: ?t=<random token from My Classes>. No sign-in, so the token is the key —
// long, random and resettable. Contains only that student's classes, times in UTC, and no link for physical classes.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/ClassCalendarService.php';

$db = \Models\Connection::getInstance()->getConnection();
$t = (string) ($_GET['t'] ?? '');
$sid = 0;
if (preg_match('/^[a-f0-9]{48}$/', $t)) {
    $st = $db->prepare('SELECT student_id FROM calendar_feed_tokens WHERE token = ?');
    $st->execute([$t]);
    $sid = (int) $st->fetchColumn();
}
if (!$sid) {
    http_response_code(404);
    exit('Calendar not found.');
}
$views = cal_student_classes($db, $sid, gmdate('Y-m-d H:i:s', strtotime('-60 days')), gmdate('Y-m-d H:i:s', strtotime('+400 days')));
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="dazu-classes.ics"');
header('Cache-Control: private, max-age=900');
echo cal_ics($views, 'Dazu classes');
