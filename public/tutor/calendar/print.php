<?php
// Printable one-month timetable (PDF) for one of the tutor's students, or a course class (group).
// One student: their own times and details (link only if that student is online/hybrid). A group: no links at all.
require __DIR__ . '/_boot.php';

$studentUid = (int) ($_GET['student'] ?? 0);
$course = (int) ($_GET['course'] ?? 0);
$mine = cal_tutor_students($db, $me);
if ($studentUid && isset($mine[$studentUid])) {
    $sTz = cal_user_tz($db, $studentUid);
    [$ym, $from, $to] = cal_month_bounds((string) ($_GET['m'] ?? ''), $sTz);
    $rows = array_filter(cal_student_classes($db, $studentUid, $from, $to), fn($v) => true);
    $mineOnly = array_values(array_filter($rows, fn($v) => cal_tutor_class($db, $me, (int) $v['id']) !== null));
    $pdf = cal_timetable_pdf($mine[$studentUid]['full_name'], date('F Y', strtotime($ym . '-01')), $mineOnly, $sTz, true);
    $file = 'Timetable_' . preg_replace('/\W+/', '_', $mine[$studentUid]['full_name']) . '_' . $ym . '.pdf';
} elseif ($course) {
    [$ym, $from, $to] = cal_month_bounds((string) ($_GET['m'] ?? ''), $tz);
    $rows = array_map(fn($c) => cal_student_view($c, 'physical') + ['mode' => $c['mode'], 'campus' => $c['campus'], 'room' => $c['room']],
                      cal_tutor_classes($db, $me, $from, $to, ['course' => $course]));
    $name = '';
    foreach ($mine as $s) if ((int) $s['course_id'] === $course) $name = $s['course'];
    $pdf = cal_timetable_pdf(($name ?: 'Course') . ' — class timetable', date('F Y', strtotime($ym . '-01')), $rows, $tz, false);
    $file = 'Timetable_' . preg_replace('/\W+/', '_', $name ?: 'course') . '_' . $ym . '.pdf';
} else {
    SessionModel::setFlash('error', 'Choose one of your students or a course class to print.');
    header('Location: index.php');
    exit;
}
cal_audit($db, null, null, 'timetable_printed', $file);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
echo $pdf;
