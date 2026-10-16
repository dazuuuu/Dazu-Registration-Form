<?php
// The student's own timetable for one month (PDF), in their time zone. Links only for their online/hybrid classes.
require __DIR__ . '/_boot.php';
[$ym, $from, $to] = cal_month_bounds((string) ($_GET['m'] ?? ''), $tz);
$st = $db->prepare("SELECT full_name FROM registrations WHERE user_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1");
$st->execute([$me]);
$name = (string) ($st->fetchColumn() ?: 'Student');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="My_timetable_' . $ym . '.pdf"');
echo cal_timetable_pdf($name, date('F Y', strtotime($ym . '-01')), cal_student_classes($db, $me, $from, $to), $tz, true);
