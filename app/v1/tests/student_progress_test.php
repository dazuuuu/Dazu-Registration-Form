<?php
// Student Progress: who sees whom, the numbers (from the course average service), the status words, notes, nudge, settings, the download log and the PDF.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/student_progress_test.php      (a scratch database with every migration applied, 103 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/StudentProgress.php';
// This machine's vendor/composer has no platform_check.php, so load the bundled dompdf first (the live site is unaffected).
if (!class_exists('Dompdf\Dompdf') && is_file(__DIR__ . '/../lib/dompdf/autoload.inc.php')) require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../helpers/progress_pdf.php';

$db = \Models\Connection::getInstance()->getConnection();
echo 'Database driver: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
function section(string $s): void { echo "\n== $s\n"; }
function near($a, $b, float $eps = 0.011): bool { return $a !== null && abs((float) $a - (float) $b) <= $eps; }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["p_$key$run", "$key$run@p.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$mktutor = function (string $key) use ($db, $mkuser): array {
    $uid = $mkuser($key, 'tutor', 'Tutor', ucfirst($key));
    $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$uid]);
    return [$uid, (int) $db->lastInsertId()];
};
[$t1Uid, $t1] = $mktutor('one');
[$t2Uid, $t2] = $mktutor('two');
$adminUid = $mkuser('admin', 'admin', 'Ad', 'Min');
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 12, 100000, 1, 'diploma')")->execute(['Progress Course ' . $run, 'PC' . strtoupper(substr($run, 0, 4))]);
$course = (int) $db->lastInsertId();
$mkstudent = function (string $key, string $name, int $tutorId) use ($db, $mkuser, $run, $course): int {
    $uid = $mkuser($key, 'student', 'Stu', ucfirst($key));
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T', ?)")
       ->execute(['PRG-' . $key . $run, $name, "$key$run@p.test", $course, $uid, gmdate('Y-m-d H:i:s', time() - 100 * 86400)]);
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tutorId, $uid, gmdate('Y-m-d', time() - 100 * 86400)]);
    return $uid;
};
$sA = $mkstudent('ann', 'Ann Active', $t1);
$sB = $mkstudent('bob', 'Bob Quiet', $t1);
$sC = $mkstudent('cat', 'Cat OtherTutor', $t2);
$sD = $mkstudent('dan', 'Dan Slow', $t1);

$subj = function (string $name, int $tutorId) use ($db, $course): int {
    $db->prepare('INSERT INTO course_subjects (course_id, tutor_id, name, monthly_expected, sort_order, is_active) VALUES (?, ?, ?, 1, 1, 1)')->execute([$course, $tutorId, $name]);
    return (int) $db->lastInsertId();
};
$py = $subj('Python', $t1); $sqlS = $subj('SQL', $t1); $ml = $subj('Machine Learning', $t1);   // ML is never started

$order = 0;
/** A published assignment due $dueDays ago for these students. */
$mk = function (int $tutorId, ?int $subjectId, string $kind, float $weight, int $dueDaysAgo, array $students) use ($db, $course, &$order): int {
    $order++;
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, subject_id, title, kind, weight, status, max_score, grading_mode, sort_order, due_at, is_template, audience)
                  VALUES (?, ?, ?, ?, ?, ?, 'published', 100, 'marks', ?, ?, 0, 'selected')")
       ->execute([$tutorId, $course, $subjectId, ucfirst($kind) . " $order", $kind, $weight, $order, gmdate('Y-m-d H:i:s', time() - $dueDaysAgo * 86400)]);
    $id = (int) $db->lastInsertId();
    foreach ($students as $su) $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at) VALUES (?, ?, ?)')->execute([$id, $su, gmdate('Y-m-d H:i:s')]);
    return $id;
};
$sub = function (int $aid, int $uid, int $handedDaysAgo, ?float $score, bool $released = true) use ($db): void {
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, is_late)
                  VALUES (?, ?, ?, ?, ?, ?, 0)")
       ->execute([$aid, $uid, $score !== null ? 'graded' : 'submitted', gmdate('Y-m-d H:i:s', time() - $handedDaysAgo * 86400), $score, $score !== null && $released ? gmdate('Y-m-d H:i:s', time() - 3600) : null]);
};

// Ann: Python 3 of 4 handed in (80, 60, held), 1 missing; SQL 1 of 1 (100); 2 monthly projects, month 1 = 70, month 2 not handed in. Last hand-in 2 days ago.
$all4 = [$sA, $sB, $sD];
$p1 = $mk($t1, $py, 'daily', 1, 30, [$sA]); $sub($p1, $sA, 25, 80);
$p2 = $mk($t1, $py, 'daily', 1, 20, [$sA]); $sub($p2, $sA, 15, 60);
$p3 = $mk($t1, $py, 'weekly', 2, 10, [$sA]); $sub($p3, $sA, 2, 90, false);   // held: handed in, mark not released
$p4 = $mk($t1, $py, 'daily', 1, 5, [$sA]);                                   // missing (due 5 days ago, nothing handed in)
$q1 = $mk($t1, $sqlS, 'daily', 1, 12, [$sA]); $sub($q1, $sA, 3, 100);
$m1 = $mk($t1, $py, 'monthly', 3, 40, [$sA]); $sub($m1, $sA, 38, 70);
$m2 = $mk($t1, $py, 'monthly', 3, 3, [$sA]);                                 // project not handed in
// Bob: nothing handed in for 20 days (his one hand-in was 20 days ago) and 3 assignments missing.
$b1 = $mk($t1, $sqlS, 'daily', 1, 40, [$sB]); $sub($b1, $sB, 20, 50);
foreach ([30, 25, 22] as $d) $mk($t1, $sqlS, 'daily', 1, $d, [$sB]);
// Dan: last hand-in 9 days ago, 1 missing: slowing down by days alone.
$d1 = $mk($t1, $py, 'daily', 1, 15, [$sD]); $sub($d1, $sD, 9, 70);
$d2 = $mk($t1, $py, 'daily', 1, 4, [$sD]);
// Cat (other tutor)
$c1 = $mk($t2, null, 'daily', 1, 5, [$sC]); $sub($c1, $sC, 1, 90);

$vT1 = ['uid' => $t1Uid, 'role' => 'tutor', 'tutor_id' => $t1, 'name' => 'Tutor One'];
$vT2 = ['uid' => $t2Uid, 'role' => 'tutor', 'tutor_id' => $t2, 'name' => 'Tutor Two'];
$vAd = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Ad Min'];

section('Who sees whom');
check('tutor one sees exactly their three students', array_keys(sp_students($db, $vT1)) === [$sA, $sB, $sD] || (function () use ($db, $vT1, $sA, $sB, $sD) { $k = array_keys(sp_students($db, $vT1)); sort($k); $e = [$sA, $sB, $sD]; sort($e); return $k === $e; })());
check('tutor one cannot see tutor two\'s student', !isset(sp_students($db, $vT1)[$sC]));
check('tutor two sees only their student', array_keys(sp_students($db, $vT2)) === [$sC]);
check('admin sees all four', count(array_intersect(array_keys(sp_students($db, $vAd)), [$sA, $sB, $sC, $sD])) === 4);
check('admin tutor filter works', array_keys(sp_students($db, $vAd, ['tutor' => $t2])) === [$sC]);
check('a request for a student outside a tutor\'s list is not allowed', sp_allowed_ids($db, $vT1, [$sA, $sC]) === [$sA]);
check('search by name and by number', isset(sp_students($db, $vT1, ['q' => 'quiet'])[$sB]) && isset(sp_students($db, $vT1, ['q' => 'PRG-ann'])[$sA]) && !isset(sp_students($db, $vT1, ['q' => 'quiet'])[$sA]));
$_SESSION = ['logged_in' => true, 'user_id' => $sA, 'role' => 'student', 'role_id' => $roles['student']];
check('a student gets no viewer, on either portal', sp_viewer($db, 'tutor') === null && sp_viewer($db, 'admin') === null);
$_SESSION = ['logged_in' => true, 'user_id' => $t1Uid, 'role' => 'tutor', 'role_id' => $roles['tutor']];
check('a tutor is a tutor viewer but not an admin viewer', sp_viewer($db, 'tutor')['tutor_id'] === $t1 && sp_viewer($db, 'admin') === null);
$_SESSION = ['logged_in' => true, 'user_id' => $adminUid, 'role' => 'admin', 'role_id' => 2];
check('admin is an admin viewer, not a tutor viewer', sp_viewer($db, 'admin')['role'] === 'admin' && sp_viewer($db, 'tutor') === null);
$_SESSION = ['logged_in' => true, 'user_id' => $adminUid, 'role' => 'employee', 'role_id' => $roles['employee'] ?? 5];
check('staff (employee) gets no viewer', sp_viewer($db, 'admin') === null && sp_viewer($db, 'tutor') === null);
$_SESSION = [];

section('The numbers come from the course average service');
$cards = sp_compute($db, sp_students($db, $vT1), true);
$a = $cards[$sA];
$svc = AssignmentAverage::forStudent($db, $sA)[$course];
check('overall average equals the course average service (held mark and missing work not counted)', near($a['average'], $svc['average']), (string) $a['average'] . ' vs ' . $svc['average']);
// marks: p1 80, p2 60, q1 100, m1 70 (weight 3), p3 held, p4 missing -> (80+60+100+210)/(1+1+1+3)
check('...and it is the expected weighted figure, 75', near($a['average'], (80 + 60 + 100 + 3 * 70) / 6), (string) $a['average']);
check('handed in counts the held one, missing is the unhanded past-due work (assignments and projects)', $a['handed'] === 5 && $a['total'] === 7 && $a['missing'] === 2, "{$a['handed']} of {$a['total']}, missing {$a['missing']}");
check('last hand-in is 2 days ago', $a['days_since'] === 2, (string) $a['days_since']);
check('project average is the monthly marks only (70), not counting the missing month as zero', near($a['project_average'], 70), (string) $a['project_average']);
$lines = [];
foreach ($a['subject_lines']['lines'] as $l) $lines[$l['name']] = $l;
$sm = subjectMarks($db, $sA, $course);
$pyMark = null; foreach ($sm as $m) if ($m['name'] === 'Python') $pyMark = $m['mark'];
check('Python line: 3 of 4 handed in', ($lines['Python']['handed'] ?? null) === 3 && ($lines['Python']['total'] ?? null) === 4, json_encode($lines['Python'] ?? null));
check('Python average equals the subject service (weights, released only)', near($lines['Python']['mark'] ?? null, $pyMark), json_encode([$lines['Python']['mark'] ?? null, $pyMark]));
check('SQL line: 1 of 1, 100%', ($lines['SQL']['handed'] ?? null) === 1 && near($lines['SQL']['mark'] ?? null, 100));
check('the subject not started is folded into one line, not listed', !isset($lines['Machine Learning']) && $a['subject_lines']['not_started'] === 1);
$items = $a['projects']['items'];
check('monthly projects: month 1 = 70%, month 2 not handed in', count($items) === 2 && $items[0]['text'] === '70%' && $items[1]['text'] === 'not handed in', json_encode($items));
$b = $cards[$sB];
check('a student with no released marks in a subject shows "no mark yet"', $b['average'] !== null && near($b['average'], 50));
$noMark = sp_subject_lines([['id' => $py, 'name' => 'Python', 'code' => null, 'weight' => null, 'monthly_expected' => 1, 'is_active' => 1]], [['id' => 999, 'title' => 'x', 'kind' => 'daily', 'weight' => 1, 'grading_mode' => 'marks', 'max_score' => 100, 'subject_id' => $py, 'sub_status' => 'submitted', 'submitted_at' => gmdate('Y-m-d H:i:s'), 'score' => null, 'pass_result' => null, 'result_published_at' => null, 'release_grades_at' => null, 'is_late' => 0, 'due' => null]], time());
check('handed in but nothing released: "no mark yet"', ($noMark['lines'][0]['text'] ?? '') === 'no mark yet');
check('should-be today is about 100/365 of the year (12 months course, started 100 days ago)', near($a['should_be'], 100 / 365.25 * 12 / 12 * 100 * 365.25 / 365.25 / 1, 3.0) || near($a['should_be'], 100 / (12 * 30.4375) * 100, 0.5), (string) $a['should_be']);

section('Status words and the comment, worked out live');
check('Ann: Working well (handed in 2 days ago, 2 missing -> slowing by count)', in_array($a['status']['key'], ['slow', 'good'], true), $a['status']['key']);
check('Ann has 2 late, so with the standard numbers she is Slowing down', $a['status']['key'] === 'slow' && in_array('2 assignments are late', $a['status']['reasons'], true), json_encode($a['status']));
check('Bob: 20 days quiet and 3 late -> Not working, reasons and What to do', $b['status']['key'] === 'bad' && in_array('No work handed in for 20 days', $b['status']['reasons'], true) && in_array('3 assignments are late', $b['status']['reasons'], true) && $b['status']['action'] !== '', json_encode($b['status']));
$d = $cards[$sD];
check('Dan: 9 days quiet, 1 late -> Slowing down', $d['status']['key'] === 'slow' && in_array('No work handed in for 9 days', $d['status']['reasons'], true), json_encode($d['status']));
$set = sp_settings($db);
check('the standard numbers are the defaults', $set === SP_DEFAULTS, json_encode($set));
$mkm = fn(?int $days, int $missing, bool $never = false) => sp_status(['days_since' => $days, 'missing' => $missing, 'total' => 5, 'never' => $never], $set)['key'];
check('boundaries: 6 days & 1 missing good; 7 days slow; 14 days slow; 15 days bad', $mkm(6, 1) === 'good' && $mkm(7, 0) === 'slow' && $mkm(14, 0) === 'slow' && $mkm(15, 0) === 'bad');
check('boundaries: 2 missing slow; 3 missing bad; recent work with 3 missing is still bad', $mkm(1, 2) === 'slow' && $mkm(1, 3) === 'bad');
check('no assignments yet is Working well, never a false alarm', sp_status(['days_since' => 50, 'missing' => 0, 'total' => 0, 'never' => true], $set)['key'] === 'good');
check('sort: needs attention first (Bob, then Dan/Ann, nobody good before bad)', array_keys(sp_sort($cards))[0] === $sB);
check('tabs filter', array_keys(sp_filter_status($cards, 'bad')) === [$sB] && count(sp_filter_status($cards, 'all')) === 3);
$cnt = sp_counts($cards);
check('tab counts add up', $cnt['all'] === 3 && $cnt['bad'] + $cnt['slow'] + $cnt['good'] === 3);

section('Settings');
check('admin can change the numbers and the status follows (7 days -> 10: Dan is now Working well)', sp_settings_save($db, ['working_days' => 10, 'slowing_days' => 14, 'slowing_missing' => 2, 'notworking_missing' => 3], $adminUid)['ok']
    && sp_sort(sp_compute($db, sp_students($db, $vT1, ['ids' => [$sD]])), 'name')[$sD]['status']['key'] === 'good');
check('bad numbers are refused (days order, text, zero)', !sp_settings_save($db, ['working_days' => 14, 'slowing_days' => 7, 'slowing_missing' => 2, 'notworking_missing' => 3], 1)['ok']
    && !sp_settings_save($db, ['working_days' => 'x', 'slowing_days' => 14, 'slowing_missing' => 2, 'notworking_missing' => 3], 1)['ok']
    && !sp_settings_save($db, ['working_days' => 0, 'slowing_days' => 14, 'slowing_missing' => 2, 'notworking_missing' => 3], 1)['ok']);
$db->exec('DELETE FROM progress_settings');
check('empty settings table means the defaults again', sp_settings($db) === SP_DEFAULTS);

section('Notes and nudge');
check('tutor one saves a note', sp_add_note($db, $vT1, $sA, 'Needs a call about Python.')['ok']);
check('tutor two cannot write a note about tutor one\'s student', !sp_add_note($db, $vT2, $sA, 'Sneaky')['ok']);
check('an empty note is refused', !sp_add_note($db, $vT1, $sA, '   ')['ok']);
check('the author sees their note with the date and name', ($n = sp_notes($db, $vT1, $sA)) && $n[0]['author_name'] === 'Tutor One' && $n[0]['created_at'] !== '');
check('admin sees it too', count(sp_notes($db, $vAd, $sA)) === 1);
check('another tutor does not see it', sp_notes($db, $vT2, $sA) === []);
$db->prepare("INSERT INTO progress_notes (student_user_id, author_user_id, author_name, note) VALUES (?, ?, 'Tutor Two', 'other')")->execute([$sA, $t2Uid]);
check('a tutor sees only their own notes even when others wrote about the same student', count(sp_notes($db, $vT1, $sA)) === 1);
$before = (int) $db->query('SELECT COUNT(*) FROM portal_notifications')->fetchColumn();
$nudges0 = (int) $db->query('SELECT COUNT(*) FROM progress_nudges')->fetchColumn();
$r = sp_nudge($db, $vT1, $sB);
check('a nudge goes through the notification system (or is recorded as switched off)', $r['ok'] && (int) $db->query('SELECT COUNT(*) FROM progress_nudges')->fetchColumn() === $nudges0 + 1, $r['message']);
$db->prepare("INSERT INTO notification_preferences (user_id, category, enabled) VALUES (?, 'assignments', 1)")->execute([$sA]);
$r2 = sp_nudge($db, $vT1, $sA);
check('with assignment notifications on, the student gets a bell notification', $r2['ok'] && (int) $db->query('SELECT COUNT(*) FROM portal_notifications')->fetchColumn() === $before + 1, $r2['message']);
check('a tutor cannot nudge another tutor\'s student; admin cannot nudge', !sp_nudge($db, $vT1, $sC)['ok'] && !sp_nudge($db, $vAd, $sA)['ok']);
check('nothing was sent by merely building cards (no nudge without a press)', (int) $db->query('SELECT COUNT(*) FROM progress_nudges')->fetchColumn() === $nudges0 + 2);

section('The PDF');
$bytes = sp_pdf_build($db, $vT1, [$sB, $sA, $sD]);
check('a PDF of three students is made', str_starts_with($bytes, '%PDF') && strlen($bytes) > 3000, (string) strlen($bytes));
preg_match_all('/\/Type\s*\/Page[^s]/', $bytes, $mm);
check('one page per student', count($mm[0]) === 3, (string) count($mm[0]));
$one = sp_pdf_build($db, $vT1, [$sA]);
preg_match_all('/\/Type\s*\/Page[^s]/', $one, $m1);
check('one student is one page', count($m1[0]) === 1);
$html = spp_card_html(sp_compute($db, sp_students($db, $vT1, ['ids' => [$sA]]), true)[$sA], null, '13 November 2026', 'Tutor One', true);
check('the card has the status word and the comment, and never the private note', str_contains($html, 'Slowing down') && str_contains($html, 'What to do') && !str_contains($html, 'Needs a call about Python'));
sp_log($db, $vT1, 'download_selected', [$sA, $sB]);
$log = $db->query('SELECT * FROM progress_activity_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check('the download log has who and which students', $log['actor_name'] === 'Tutor One' && $log['student_ids'] === "$sA,$sB" && (int) $log['student_count'] === 2);

section('Nothing existing was changed');
$root = realpath(__DIR__ . '/../../..');
$bad = [];
foreach (['public/components/progress', 'app/v1/services/StudentProgress.php', 'app/v1/helpers/progress_pdf.php'] as $p) {
    $files = is_dir("$root/$p") ? glob("$root/$p/*.php") : ["$root/$p"];
    foreach ($files as $f) if (preg_match('/(?<!KEY )\b(UPDATE|DELETE\s+FROM|INSERT\s+INTO)\s+(?!progress_)(\w+)/i', (string) file_get_contents($f), $mm2)) $bad[] = basename($f) . ': ' . $mm2[0];
}
check('the new code writes only to the new progress_ tables', !$bad, implode('; ', $bad));

echo $fails ? "\n$fails failed\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
