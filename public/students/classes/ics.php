<?php
// One class as a calendar file (.ics), in UTC. The link is only inside for an online/hybrid student.
require __DIR__ . '/_boot.php';
$v = cal_student_class($db, $me, (int) ($_GET['id'] ?? 0));
if (!$v) {
    http_response_code(404);
    exit('Class not found.');
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="class-' . (int) $v['id'] . '.ics"');
echo cal_ics([$v], $v['title']);
