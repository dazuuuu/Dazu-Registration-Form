<?php
// Apply for a certificate: the four conditions, course time, apply, the tutor's lines, return, approve and send, new version, audit log.
//   DAZU_DB_NAME=<scratch db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=] php -d extension=gd app/v1/tests/certificate_application_test.php
// Optional: run app/v1/tests/smtp_sink.php and set MAIL_HOST=127.0.0.1 MAIL_PORT=2525 MAIL_ENCRYPTION= MAIL_USERNAME=u MAIL_PASSWORD=p SINK_DIR=<dir> to read the real email.
// Uses a scratch database with all migrations applied. Writes the logins it made to <temp>/certapp_users.json for certificate_application_http.sh.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/CertificateApplication.php';
require_once __DIR__ . '/../services/SubjectService.php';
require_once __DIR__ . '/../helpers/certificate_pdf.php';
// The local vendor folder has no packages, so load the bundled PHPMailer first (the app's Mailer then finds it).
foreach (['Exception', 'PHPMailer', 'SMTP'] as $f) if (is_file($p = __DIR__ . "/../lib/PHPMailer/src/$f.php")) require_once $p;

$db = \Models\Connection::getInstance()->getConnection();
echo 'Database driver: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ' ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
$fails = 0; $n = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails, $n;
    $n++;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
function section(string $s): void { echo "\n== $s\n"; }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$logins = [];
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run, &$logins): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["a_$key$run", "$key$run@a.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    $logins[$key] = ['id' => $id, 'email' => "$key$run@a.test"];
    return $id;
};
$mktutor = function (string $key) use ($db, $mkuser, &$logins): array {
    $uid = $mkuser($key, 'tutor', 'Tutor', ucfirst($key));
    $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$uid]);
    $tid = (int) $db->lastInsertId();
    $logins[$key]['tutor_id'] = $tid;
    return [$uid, $tid];
};
[$tUid, $tId] = $mktutor('tutor');
[$t2Uid, $t2Id] = $mktutor('tutor2');
$adminUid = $mkuser('admin', 'admin', 'Ad', 'Min');
$mkcourse = function (string $title, string $abbr, int $months, int $fee) use ($db): int {
    $db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, ?, ?, 1, 'diploma')")->execute([$title, $abbr, $months, $fee]);
    return (int) $db->lastInsertId();
};
$capCourse = $mkcourse('Diploma in Data Science ' . $run, 'DS' . strtoupper(substr($run, 0, 4)), 12, 100000);
$plainCourse = $mkcourse('Certificate in Plain Skills ' . $run, 'PS' . strtoupper(substr($run, 0, 4)), 6, 50000);
foreach ([$capCourse, $plainCourse] as $c) $db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$c, $tId]);

$reg = []; $stu = [];
$mkStudent = function (string $key, int $courseId, int $monthsAgo, int $fee, int $tutorId = 0) use ($db, $mkuser, $run, $tId, &$reg, &$stu): int {
    $uid = $mkuser($key, 'student', 'Stu', ucfirst($key));
    $start = date('Y-m-d', strtotime("-$monthsAgo months -3 days"));
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, created_at)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, ?, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T', ?)")->execute(['A' . $key . $run, 'Stu ' . ucfirst($key), "$key$run@a.test", $courseId, $fee, $uid, $start . ' 09:00:00']);
    $reg[$uid] = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tutorId ?: $tId, $uid, $start]);
    $stu[$key] = $uid;
    return $uid;
};
foreach (['ready', 'notime', 'fees99', 'nocap', 'avg', 'ret'] as $k) $mkStudent($k, $capCourse, $k === 'notime' ? 2 : 13, 100000);
foreach (['pready', 'pnew', 'pext', 'phand'] as $k) $mkStudent($k, $plainCourse, in_array($k, ['pready', 'pext', 'phand'], true) ? 7 : 2, 50000);
$mkStudent('cat', $capCourse, 13, 100000, $t2Id);   // a student of the other tutor

// The capstone (open, 5% weight), its subjects, and helpers to mark and pay.
$utc = fn(int $days, int $hour = 15): string => gmdate('Y-m-d', time() + $days * 86400) . sprintf(' %02d:00:00', $hour);
$students = array_map(fn($u) => ['uid' => $u, 'team_no' => null, 'own_due_at' => null], array_values(array_filter($stu, fn($u) => in_array($u, [$stu['ready'], $stu['notime'], $stu['fees99'], $stu['nocap'], $stu['avg'], $stu['ret']], true))));
$r = cap_save($db, $tId, null, [
    'course_id' => $capCourse, 'intake_id' => null, 'title' => 'Build a booking app', 'brief' => 'b', 'instructions' => 'i', 'requirements' => 'r', 'example_link' => null, 'mode' => 'individual',
    'team_size' => 3, 'visibility' => 'now', 'opens_at' => null, 'due_at' => $utc(14), 'w_project' => 50, 'w_docs' => 20, 'w_present' => 20, 'w_time' => 10, 'weight' => 5,
    'deliverables' => [['kind' => 'project_link', 'label' => CAP_DELIVERABLES['project_link'][0], 'item_type' => 'link', 'is_required' => 1]],
    'milestones' => [['title' => 'Final', 'kind' => 'final', 'due_at' => $utc(14), 'marks' => 10]], 'students' => $students,
], $tUid);
check('the capstone is set up', $r['ok'], json_encode($r));
$capId = (int) $r['id'];
$cap = cap_get($db, $capId);
$dels = array_column(cap_deliverables($db, $capId), null, 'kind');
foreach (['Python Programming', 'Databases and SQL', 'Machine Learning'] as $i => $sn) subj_create($db, $tId, $tUid, $capCourse, ['name' => $sn, 'monthly_expected' => 1]);

$markTo = function (int $uid, float $total) use ($db, $capId, $dels, $tId, $tUid): void {
    $att = cap_latest_attempt($db, $capId, $uid);
    $owner = cap_owner($db, $att);
    $w = cap_work($db, $owner, true);
    cap_put_item($db, (int) $w['id'], (int) $dels['project_link']['id'], $dels['project_link'], ['url' => 'https://github.com/x/y' . $uid, 'file_path' => null, 'file_name' => null, 'link_status' => 'ok', 'link_message' => 'ok', 'same' => 0]);
    $db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at) VALUES (?, ?, ?, ?)')->execute([(int) $owner['id'], $uid, (int) $w['version'], cap_now()]);
    cap_submit($db, cap_for_student($db, $capId, $uid), cap_latest_attempt($db, $capId, $uid), $uid, true);
    $a = cap_latest_attempt($db, $capId, $uid);
    $left = $total; $in = [];
    foreach (['project' => 50, 'docs' => 20, 'present' => 20, 'time' => 10] as $k => $max) { $v = min($max, $left); $in['p_' . $k] = $v; $left -= $v; }
    $in['feedback'] = 'SECRET-COMMENT';
    cap_save_mark($db, (int) $a['id'], $tId, $in, $tUid);
};
$pay = function (int $uid, float $amount) use ($db, $reg, $tUid): void {
    $db->prepare("INSERT INTO payments (registration_id, amount, payer_reference, recorded_by, status, paid_at) VALUES (?, ?, 'TEST', ?, 'approved', ?)")->execute([$reg[$uid], $amount, $tUid, gmdate('Y-m-d H:i:s')]);
};
$assignment = function (int $uid, int $courseId, float $score, string $title) use ($db, $tId): void {
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, title, status, grading_mode, max_score, weight, kind, due_at) VALUES (?, ?, ?, 'published', 'marks', 100, 1, 'daily', ?)")->execute([$tId, $courseId, $title, gmdate('Y-m-d H:i:s', time() - 86400)]);
    $aid = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id) VALUES (?, ?)')->execute([$aid, $uid]);
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, graded_at, is_late) VALUES (?, ?, 'graded', ?, ?, ?, ?, 0)")
       ->execute([$aid, $uid, gmdate('Y-m-d H:i:s', time() - 3600), $score, gmdate('Y-m-d H:i:s', time() - 1800), gmdate('Y-m-d H:i:s', time() - 1800)]);
};
$rd = fn(int $uid, int $courseId = 0) => certificateReadiness($db, $uid, $courseId ?: $capCourse);
$queue = (new ReflectionClass('AssignmentNotifier'))->getProperty('queue');
$queue->setAccessible(true);
$mailsFor = fn(string $email) => array_values(array_filter($queue->getValue(), fn($m) => $m['to_email'] === $email));
$notices = function (int $uid, string $like) use ($db): array {
    $st = $db->prepare('SELECT title, body FROM portal_notifications WHERE user_id = ? AND title LIKE ?');
    $st->execute([$uid, $like]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
};
$events = function (string $event, ?int $uid = null) use ($db): int {
    $st = $db->prepare('SELECT COUNT(*) FROM certificate_events WHERE event = ?' . ($uid ? ' AND student_user_id = ?' : ''));
    $st->execute($uid ? [$event, $uid] : [$event]);
    return (int) $st->fetchColumn();
};
foreach ($stu as $k => $uid) PortalNotifications::saveChoices($db, $uid, ['certificates', 'assignments']);
PortalNotifications::saveChoices($db, $tUid, ['certificates', 'assignments']);

// Data: ready = all four; one condition missing for each of the others.
$markTo($stu['ready'], 64); $assignment($stu['ready'], $capCourse, 64, 'a1'); $pay($stu['ready'], 101000);
$markTo($stu['notime'], 64); $assignment($stu['notime'], $capCourse, 64, 'a1'); $pay($stu['notime'], 101000);
$markTo($stu['fees99'], 64); $assignment($stu['fees99'], $capCourse, 64, 'a1'); $pay($stu['fees99'], round(101000 * 0.99));
$assignment($stu['nocap'], $capCourse, 64, 'a1'); $pay($stu['nocap'], 101000);
$markTo($stu['avg'], 59); foreach (range(1, 5) as $i) $assignment($stu['avg'], $capCourse, 39, 'w' . $i); $pay($stu['avg'], 101000);
$pay($stu['pready'], 51000); $assignment($stu['pready'], $plainCourse, 70, 'p1');
$markTo($stu['ret'], 64); $assignment($stu['ret'], $capCourse, 64, 'a1'); $pay($stu['ret'], 101000);
$assignment($stu['cat'], $capCourse, 64, 'a1'); $pay($stu['cat'], 101000);

/* ================================================================================== 1. the four conditions */
section('1. The Apply button: grey until all four are true');
$x = $rd($stu['ready']);
check('all four true: ready (button opens)', $x['ready'] && $x['first_failed'] === null, json_encode($x['conditions']));
$x = $rd($stu['fees99']);
check('fees at 99%: not ready, the missing one is fees, with the balance in the reason', !$x['ready'] && $x['first_failed'] === 'fees' && strpos($x['reason'], 'KES') !== false, $x['reason']);
check('...the other three are done', $x['conditions']['time']['ok'] && $x['conditions']['capstone']['ok'] && $x['conditions']['average']['ok']);
$x = $rd($stu['notime']);
check('only course time missing: not ready, first_failed = time', !$x['ready'] && $x['first_failed'] === 'time' && $x['conditions']['fees']['ok'] && $x['conditions']['capstone']['ok'] && $x['conditions']['average']['ok']);
$x = $rd($stu['nocap']);
check('only the capstone missing (not marked): not ready, first_failed = capstone', !$x['ready'] && $x['first_failed'] === 'capstone' && $x['conditions']['time']['ok'] && $x['conditions']['fees']['ok']);
$x = $rd($stu['avg']);
check('capstone passed but the average is below 50: not ready, first_failed = average', !$x['ready'] && $x['first_failed'] === 'average' && $x['conditions']['capstone']['ok'], json_encode($x['conditions']['average']));
check('a student not in the course is not ready', !certificateReadiness($db, $stu['ready'], $plainCourse)['applies']);

section('2. Course time: counted from the day the student was added');
$p = $rd($stu['pready'], $plainCourse);
check('a 6-month course, student added 7 months ago: time finished', $p['conditions']['time']['ok'] && $p['time']['passed'] && !$p['time']['by_hand']);
$pn = $rd($stu['pnew'], $plainCourse);
check('...added 2 months ago: time NOT finished (end date = start + 6 months)', !$pn['conditions']['time']['ok'] && $pn['time']['end'] === date('Y-m-d', strtotime($pn['time']['start'] . ' +6 months')), json_encode($pn['time']));
$edge = $db->prepare('UPDATE tutor_students SET start_date = ? WHERE user_id = ?');
$edge->execute([date('Y-m-d', strtotime('-6 months')), $stu['pnew']]);
check('...the course finishes ON the end day (started exactly 6 months ago)', $rd($stu['pnew'], $plainCourse)['time']['passed']);
$edge->execute([date('Y-m-d', strtotime('-6 months +1 day')), $stu['pnew']]);
check('...and not the day before', !$rd($stu['pnew'], $plainCourse)['time']['passed']);
$edge->execute([date('Y-m-d', strtotime('-2 months')), $stu['pnew']]);
// hand-marking
$pf = $stu['phand'];
$db->prepare('UPDATE tutor_students SET start_date = ? WHERE user_id = ?')->execute([date('Y-m-d', strtotime('-1 month')), $pf]);
check('a student 1 month into a 6-month course: not finished', !$rd($pf, $plainCourse)['conditions']['time']['ok']);
$fin = cap_finish_course($db, $tId, $pf, $plainCourse, date('Y-m-d'), 'fast learner', $tUid);
check('the tutor marks the course finished by hand: time opens early', $fin['ok'] && $rd($pf, $plainCourse)['conditions']['time']['ok'] && $rd($pf, $plainCourse)['time']['by_hand'], json_encode($fin));
check('...and the existing "Studies completed" is ticked too', (bool) $db->query('SELECT studies_completed_at FROM registrations WHERE id = ' . $reg[$pf])->fetchColumn());
// extension
$pe = $stu['pext'];
check('before the extension: time is finished (7 months ago + 6 months)', $rd($pe, $plainCourse)['conditions']['time']['ok']);
$bad = [certapp_extend_end($db, $tId, $tUid, $pe, $plainCourse, date('Y-m-d', strtotime('+20 days')), ''), certapp_extend_end($db, $tId, $tUid, $pe, $plainCourse, date('Y-m-d', strtotime('-2 months')), 'earlier date'),
        certapp_extend_end($db, $t2Id, $t2Uid, $pe, $plainCourse, date('Y-m-d', strtotime('+20 days')), 'not my student')];
check('an extension needs a reason, a later date, and the student\'s own tutor', !$bad[0]['ok'] && !$bad[1]['ok'] && !$bad[2]['ok'], json_encode($bad));
$ex = certapp_extend_end($db, $tId, $tUid, $pe, $plainCourse, date('Y-m-d', strtotime('+20 days')), 'A two-month break for illness');
$after = $rd($pe, $plainCourse);
check('the tutor extends the end date (with a reason): the course time is NOT finished any more', $ex['ok'] && !$after['conditions']['time']['ok'] && $after['time']['extended'] && $after['time']['end'] === date('Y-m-d', strtotime('+20 days')), json_encode($after['time']));
check('...the student is told the new end date', strpos($after['conditions']['time']['reason'], date('j M Y', strtotime('+20 days'))) !== false);
check('...and the extension is in the audit log', $events('end_extended', $pe) === 1);
$db->prepare('UPDATE course_end_dates SET end_date = ? WHERE student_user_id = ?')->execute([date('Y-m-d', strtotime('-2 months')), $pe]);
check('an end date earlier than the natural end changes nothing (the later of the two counts)', $rd($pe, $plainCourse)['conditions']['time']['ok'] && !$rd($pe, $plainCourse)['time']['extended']);
// a course with no capstone: capstone not needed
check('a course with no capstone: the capstone condition is not needed, the other three are checked', $rd($stu['pready'], $plainCourse)['conditions']['capstone']['reason'] === 'This course has no capstone project.');

/* ================================================================================== 3. apply */
section('3. Apply: the server decides');
foreach (['notime' => 'course time', 'fees99' => 'fees', 'nocap' => 'capstone', 'avg' => 'average'] as $k => $why) {
    $r = certapp_apply($db, $stu[$k], $capCourse, 'Stu ' . ucfirst($k), true);
    check("apply by a direct call while $why is missing is refused", !$r['ok'] && strpos($r['error'], 'cannot apply yet') !== false, json_encode($r));
    check("...no application row was made ($k)", certapp_get($db, $stu[$k], $capCourse) === null);
}
check('the refusals are in the audit log (with the condition)', array_sum(array_map(fn($k) => $events('apply_refused', $stu[$k]), ['notime', 'fees99', 'nocap', 'avg'])) === 4);
$u = $stu['ready'];
check('the name must be confirmed', !certapp_apply($db, $u, $capCourse, 'Stu Ready', false)['ok']);
check('the name must be real (2 letters is too short)', !certapp_apply($db, $u, $capCourse, 'S', true)['ok']);
$ap = certapp_apply($db, $u, $capCourse, 'Stuart Ready', true);
check('all four true: the student applies (after checking the name)', $ap['ok'], json_encode($ap));
$app = certapp_get($db, $u, $capCourse);
check('the request is Pending with the name the student checked', $app['status'] === 'pending' && $app['print_name'] === 'Stuart Ready' && $app['tutor_user_id'] == $tUid);
check('...a first draft of the lines was written from the course subjects (300 letters at most)', strpos($app['lines_text'], 'covering Python Programming, Databases and SQL and Machine Learning') === 0 && mb_strlen($app['lines_text']) <= 300 && strpos($app['lines_text'], 'capstone') !== false, $app['lines_text']);
$again = certapp_apply($db, $u, $capCourse, 'Stuart Ready', true);
check('a second application for the same course is refused', !$again['ok'] && strpos($again['error'], 'already applied') !== false);
check('...only one row exists', (int) $db->query("SELECT COUNT(*) FROM certificate_applications WHERE student_user_id = $u AND course_id = $capCourse")->fetchColumn() === 1);
check('the tutor got a notice and an email (a request arrived)', count($notices($tUid, '%applied for a certificate%')) === 1 && count($mailsFor("tutor$run@a.test")) >= 1);
check('applied is in the audit log', $events('applied', $u) === 1);
check('the tutor sees the request in their list, the other tutor does not', count(array_filter(certapp_list_for_tutor($db, $tId), fn($a) => (int) $a['id'] === (int) $app['id'])) === 1 && certapp_list_for_tutor($db, $t2Id) === []);
check('the pending count for the tutor badge is 1', certapp_pending_count($db, $tId) === 1);
check('the admin word is "Applied" (words only)', certapp_admin_word($db, $u, $capCourse) === 'Applied');

/* ================================================================================== 4. the tutor writes the lines */
section('4. The tutor edits only the lines under the name');
check('another tutor cannot open the request', certapp_get_for_tutor($db, (int) $app['id'], $t2Id) === null);
check('...nor change the lines', !certapp_save_lines($db, (int) $app['id'], $t2Id, $t2Uid, 'HACKED')['ok'] && certapp_get($db, $u, $capCourse)['lines_text'] === $app['lines_text']);
check('...nor return it, approve it or send it', !certapp_return($db, (int) $app['id'], $t2Id, $t2Uid, 'no')['ok'] && !certapp_approve($db, (int) $app['id'], $t2Id, $t2Uid, true)['ok'] && certapp_get($db, $u, $capCourse)['status'] === 'pending');
$long = str_repeat('a', 301);
check('lines longer than 300 letters are refused', !certapp_save_lines($db, (int) $app['id'], $tId, $tUid, $long)['ok']);
check('exactly 300 letters is fine', certapp_save_lines($db, (int) $app['id'], $tId, $tUid, str_repeat('b', 300))['ok']);
$mine = 'covering Python, SQL and Machine Learning, with a final industry capstone project. <b>bold</b>';
$sv = certapp_save_lines($db, (int) $app['id'], $tId, $tUid, $mine);
check('the tutor\'s lines are saved (tags removed)', $sv['ok'] && certapp_get($db, $u, $capCourse)['lines_text'] === 'covering Python, SQL and Machine Learning, with a final industry capstone project. bold');
certapp_save_lines($db, (int) $app['id'], $tId, $tUid, 'covering Python, SQL and Machine Learning, with a final industry capstone project.');
$pv = certapp_preview_cert($db, certapp_get($db, $u, $capCourse));
$html = cert_render_html($pv);
check('the preview is the existing certificate with a PREVIEW mark, the student\'s name and the tutor\'s lines', strpos($html, 'PREVIEW') !== false && strpos($html, 'Stuart Ready') !== false && strpos($html, 'covering Python, SQL and Machine Learning') !== false);
check('...it shows no mark and no comment', !preg_match('/SECRET|\b64\b|mark/i', strip_tags($html)));
check('the preview PDF is made', substr(cert_pdf_bytes($pv), 0, 4) === '%PDF');

/* ================================================================================== 5. return and apply again */
section('5. Return to student');
$rt = $stu['ret'];
certapp_apply($db, $rt, $capCourse, 'Stu Ret', true);
$rapp = certapp_get($db, $rt, $capCourse);
check('a note is needed to return a request', !certapp_return($db, (int) $rapp['id'], $tId, $tUid, '')['ok']);
$rr = certapp_return($db, (int) $rapp['id'], $tId, $tUid, 'Please write your full second name.');
check('Return to student works (status Returned, with the note)', $rr['ok'] && certapp_get($db, $rt, $capCourse)['status'] === 'returned' && certapp_get($db, $rt, $capCourse)['return_note'] === 'Please write your full second name.');
check('...the student got a notice and an email', count($notices($rt, '%returned%')) === 1 && count($mailsFor("ret$run@a.test")) >= 1);
check('...the lines cannot be edited on a returned request', !certapp_save_lines($db, (int) $rapp['id'], $tId, $tUid, 'x')['ok']);
check('returned is in the audit log', $events('returned', $rt) === 1);
$re = certapp_apply($db, $rt, $capCourse, 'Stuart Ret Mwangi', true);
$rapp2 = certapp_get($db, $rt, $capCourse);
check('the student can apply again after a return (same application, Pending, new name)', $re['ok'] && $rapp2['status'] === 'pending' && (int) $rapp2['id'] === (int) $rapp['id'] && $rapp2['print_name'] === 'Stuart Ret Mwangi');
check('...still one row for the course', (int) $db->query("SELECT COUNT(*) FROM certificate_applications WHERE student_user_id = $rt")->fetchColumn() === 1);
check('...and a second application is refused again', !certapp_apply($db, $rt, $capCourse, 'x y z', true)['ok']);

/* ================================================================================== 6. approve and send */
section('6. Approve only, then send; Approve and send');
$before = (int) $db->query("SELECT COUNT(*) FROM certificates WHERE registration_id = {$reg[$u]}")->fetchColumn();
$ao = certapp_approve($db, (int) $app['id'], $tId, $tUid, false);
$cid = (int) ($ao['certificate_id'] ?? 0);
$crow = $db->query("SELECT * FROM certificates WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
check('Approve only: a certificate is made (not issued) with the printed name and the tutor\'s lines', $ao['ok'] && $crow['status'] === 'being_prepared' && $crow['snapshot_name'] === 'Stuart Ready' && strpos($crow['taught_skills'], 'covering Python, SQL') === 0 && (int) $crow['application_id'] === (int) $app['id'], json_encode($ao));
check('...the certificate number is the existing kind, the QR code hash is made', preg_match('/^DDS-\d{4}-\d{4}$/', $crow['certificate_no']) && strlen($crow['qr_code_hash']) >= 16);
check('...the application is Approved and the admin word is "Approved"', certapp_get($db, $u, $capCourse)['status'] === 'approved' && certapp_admin_word($db, $u, $capCourse) === 'Approved');
$st = $db->query("SELECT status FROM certificates WHERE id = $cid")->fetchColumn();
check('...the student cannot see it yet (not issued)', $st === 'being_prepared' && !cap_certificate_issued($db, $u, $capCourse));
$sinkDir = getenv('SINK_DIR');
$sinkBefore = $sinkDir ? count(glob("$sinkDir/*.eml")) : 0;   // (messages already in the sink before this step)
$sd = certapp_approve($db, (int) $app['id'], $tId, $tUid, true);   // an approved request: "Approve and send" sends it
check('Send: the certificate is issued and the application is Sent', $sd['ok'] && certapp_get($db, $u, $capCourse)['status'] === 'sent' && cap_certificate_issued($db, $u, $capCourse), json_encode($sd));
check('...it appears on the student\'s Certificates page (not withdrawn, issued)', (bool) $db->query("SELECT 1 FROM certificates c JOIN registrations r ON r.id = c.registration_id WHERE r.user_id = $u AND c.status = 'issued' AND c.id = $cid")->fetchColumn());
check('...the admin word is "Sent"', certapp_admin_word($db, $u, $capCourse) === 'Sent');
$rel = cap_release_row($db, $u, $capCourse);
check('...the transcript and the letter of recommendation are released with it', $rel && $rel['transcript_released_at'] && $rel['letter_released_at'] && empty($rel['hidden_at']));
check('...the student got "Your certificate is ready" (existing certificate notice)', count($notices($u, 'Your certificate is ready%')) === 1);
check('sent and approved are in the audit log', $events('approved', $u) === 1 && $events('sent', $u) === 1);
check('a sent certificate cannot be approved or sent twice', !certapp_send($db, (int) $app['id'], $tId, $tUid)['ok'] && (int) $db->query("SELECT COUNT(*) FROM certificates WHERE registration_id = {$reg[$u]}")->fetchColumn() === $before + 1);
if ($sinkDir) {
    $all = glob("$sinkDir/*.eml");
    natsort($all);
    $files = array_slice(array_values($all), $sinkBefore);   // only the messages this run caused, oldest first
    $msg = '';
    foreach ($files as $f) { $m = file_get_contents($f); if (stripos($m, "ready$run@a.test") !== false && stripos($m, 'filename=Certificate_DDS') !== false) $msg = $m; }
    check('the email arrived (real SMTP, local sink) at the student\'s address', $msg !== '' && stripos($msg, "To: ") !== false && stripos($msg, "ready$run@a.test") !== false);
    check('...with the certificate PDF attached', (bool) preg_match('/filename="?Certificate_DDS-\d{4}-\d{4}\.pdf/i', $msg));
    $msg = str_replace("\r\n", "\n", $msg);
    $pdfPart = $htmlPart = '';
    foreach (preg_split('/^--b\d=_[A-Za-z0-9_-]+(?:--)?$/m', $msg) as $part) {
        if (preg_match('/filename="?Certificate_DDS/i', $part)) $pdfPart = $part;
        if (stripos($part, 'Content-Type: text/html') !== false) $htmlPart = $part;
    }
    $b64 = $pdfPart !== '' ? preg_replace('/^.*?\n\n/s', '', ltrim($pdfPart, "\n"), 1) : '';
    check('...the attachment is a real PDF', substr(base64_decode(preg_replace('/\s+/', '', $b64)), 0, 4) === '%PDF');
    $body = quoted_printable_decode(preg_replace('/^.*?\n\n/s', '', ltrim($htmlPart, "\n"), 1));
    check('...and the email shows no mark and no comment', $body !== '' && !preg_match('/SECRET|\b64\b|out of 100|average/i', strip_tags($body)), substr(trim(strip_tags($body)), 0, 150));
} else {
    echo "skip email checks (no SINK_DIR)\n";
}
$rs = certapp_resend($db, (int) $app['id'], $tId, $tUid);
check('the tutor can resend it later (counted, and in the audit log)', ($sinkDir ? $rs['ok'] : true) && $events('resent', $u) === 1 && (int) certapp_get($db, $u, $capCourse)['send_count'] === 2, json_encode($rs));

section('7. A mistake: a new version');
$nv = certapp_new_version($db, (int) $app['id'], $tId, $tUid, 'covering Python and SQL, with a final capstone project.', 'Stuart Ready Otieno');
$old = $db->query("SELECT * FROM certificates WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
$new = $db->query('SELECT * FROM certificates WHERE id = ' . (int) ($nv['certificate_id'] ?? 0))->fetch(PDO::FETCH_ASSOC);
check('a new version is issued: the old one is marked Replaced (withdrawn) and points to the new', $nv['ok'] && $old['status'] === 'withdrawn' && strpos($old['withdrawn_reason'], 'Replaced by version 2') === 0 && (int) $old['replaced_by_id'] === (int) $new['id'], json_encode($nv));
check('...the new one has a new number (never reused), version 2, the corrected lines and name', $new && $new['certificate_no'] !== $old['certificate_no'] && (int) $new['version'] === 2 && $new['snapshot_name'] === 'Stuart Ready Otieno' && strpos($new['taught_skills'], 'covering Python and SQL') === 0 && $new['status'] === 'issued');
check('...the student sees only the new one', (int) $db->query("SELECT COUNT(*) FROM certificates c JOIN registrations r ON r.id = c.registration_id WHERE r.user_id = $u AND c.status <> 'withdrawn'")->fetchColumn() === 1);
check('...the audit log has new_version', $events('new_version', $u) === 1);

/* ================================================================================== 8. scheduled job */
section('8. The scheduled job and the 3-day reminder');
$t = certapp_tick($db);
$opened = $notices($stu['pready'], 'You can now apply%');
check('the job opens the button for a student whose four conditions are true and tells them once (notice + email)', $t['opened'] >= 1 && count($opened) === 1 && count($mailsFor("pready$run@a.test")) >= 1 && $events('button_opened', $stu['pready']) === 1, json_encode($t));
$t2 = certapp_tick($db);
check('...running it again does not tell them twice', count($notices($stu['pready'], 'You can now apply%')) === 1 && $t2['opened'] === 0);
check('...nobody is told whose conditions are not all true', !$notices($stu['notime'], 'You can now apply%') && !$notices($stu['fees99'], 'You can now apply%'));
// when the clock passes (course time), the button opens: move the student's start back
$db->prepare('UPDATE tutor_students SET start_date = ? WHERE user_id = ?')->execute([date('Y-m-d', strtotime('-13 months')), $stu['notime']]);
$db->prepare('UPDATE registrations SET created_at = ? WHERE id = ?')->execute([date('Y-m-d', strtotime('-13 months')) . ' 09:00:00', $reg[$stu['notime']]]);
$t3 = certapp_tick($db);
check('when the course time passes, the next run opens the button and tells the student', count($notices($stu['notime'], 'You can now apply%')) === 1 && $t3['opened'] >= 1);
$db->prepare('UPDATE certificate_applications SET applied_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 4 * 86400), (int) $rapp['id']]);
$t4 = certapp_tick($db);
check('a request that waited more than 3 days: the tutor gets a reminder', $t4['reminded'] === 1 && count($notices($tUid, '%has waited 3 days%')) === 1);
check('...and only one (not again at once)', certapp_tick($db)['reminded'] === 0 && count($notices($tUid, '%has waited 3 days%')) === 1);

/* ================================================================================== 9. words for the admin */
section('9. Admin sees words only');
$adminSrc = file_get_contents(__DIR__ . '/../../../public/admin/certificates/index.php');
check('the admin Certificates page reads only the status of an application (no lines, no note, no name checks)', strpos($adminSrc, 'lines_text') === false && strpos($adminSrc, 'return_note') === false && strpos($adminSrc, 'print_name') === false);
check('the admin has no page that opens the tutor\'s request', !glob(__DIR__ . '/../../../public/admin/certificates/request*'));

file_put_contents((getenv('TEMP') ?: sys_get_temp_dir()) . '/certapp_users.json', json_encode($logins + [
    '_run' => $run, '_capCourse' => $capCourse, '_plainCourse' => $plainCourse, '_ready_app' => (int) $app['id'], '_ret_app' => (int) $rapp['id'], '_ready' => $stu['ready'], '_ret' => $stu['ret'],
    '_notime' => $stu['notime'], '_fees99' => $stu['fees99'], '_nocap' => $stu['nocap'], '_avg' => $stu['avg'], '_cat' => $stu['cat'], '_pready' => $stu['pready'], '_cert' => (int) $new['id'],
]));
echo "\n" . ($fails ? "$fails of $n checks FAILED\n" : "All $n passed\n");
exit($fails ? 1 : 0);
