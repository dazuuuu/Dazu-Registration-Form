<?php
// Certificate readiness and the release of the transcript + letter of recommendation, for courses with a capstone.
//   DAZU_DB_NAME=<scratch db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=] php app/v1/tests/certificate_readiness_test.php
// Uses a scratch database that has all migrations applied. It adds its own rows and writes the logins it made to
// <temp>/readiness_users.json so the HTTP checks (tests/certificate_readiness_http.sh) can use them.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/CapstoneService.php';
require_once __DIR__ . '/../services/CapstoneNotifier.php';
require_once __DIR__ . '/../services/AssignmentAverage.php';
require_once __DIR__ . '/../services/CertificateService.php';
require_once __DIR__ . '/../helpers/completion_letter_pdf.php';

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
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["r_$key$run", "$key$run@r.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    $logins[$key] = ['id' => $id, 'email' => "$key$run@r.test"];
    return $id;
};
$tutorUid = $mkuser('tutor', 'tutor', 'Tina', 'Tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutorUid]);
$tutorId = (int) $db->lastInsertId();
$adminUid = $mkuser('admin', 'admin', 'Ad', 'Min');
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES ('Readiness Course', ?, 12, 100000, 1, 'diploma')")->execute(['R' . strtoupper(substr($run, 0, 5))]);
$courseId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES ('Plain Course', ?, 6, 50000, 1, 'certificate')")->execute(['P' . strtoupper(substr($run, 0, 5))]);
$plainCourse = (int) $db->query('SELECT MAX(id) FROM courses')->fetchColumn();
$db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$courseId, $tutorId]);

$stu = []; $reg = [];
$mkStudent = function (string $key, int $course = 0, float $fee = 100000) use ($db, $mkuser, $courseId, $tutorId, $run, &$stu, &$reg): int {
    $course = $course ?: $courseId;
    $uid = $mkuser($key, 'student', 'Stu', ucfirst($key));
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, ?, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T')")->execute(['R' . $key . $run, 'Stu ' . ucfirst($key), "$key$run@r.test", $course, $fee, $uid]);
    $reg[$uid] = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$tutorId, $uid]);
    $stu[$key] = $uid;
    return $uid;
};
foreach (['ready', 'notfin', 'unpaid', 'failed', 'avg49', 'held', 'f99', 'retakepaid', 'undo', 'other', 'letter'] as $k) $mkStudent($k);
$plain = $mkStudent('plain', $plainCourse, 50000);

$utc = fn(int $days, int $hour = 15): string => gmdate('Y-m-d', time() + $days * 86400) . sprintf(' %02d:00:00', $hour);
$students = array_map(fn($u) => ['uid' => $u, 'team_no' => null, 'own_due_at' => null], array_values(array_diff($stu, [$stu['plain']])));
$r = cap_save($db, $tutorId, null, [
    'course_id' => $courseId, 'intake_id' => null, 'title' => 'Build a booking app', 'brief' => 'b', 'instructions' => 'i', 'requirements' => 'r', 'example_link' => null, 'mode' => 'individual',
    'team_size' => 3, 'visibility' => 'now', 'opens_at' => null, 'due_at' => $utc(14), 'w_project' => 50, 'w_docs' => 20, 'w_present' => 20, 'w_time' => 10, 'weight' => 5,
    'deliverables' => [['kind' => 'project_link', 'label' => CAP_DELIVERABLES['project_link'][0], 'item_type' => 'link', 'is_required' => 1]],
    'milestones' => [['title' => 'Final', 'kind' => 'final', 'due_at' => $utc(14), 'marks' => 10]], 'students' => $students,
], $tutorUid);
check('the capstone is set up for the test students', $r['ok'], json_encode($r));
$capId = (int) $r['id'];
$cap = cap_get($db, $capId);
$dels = array_column(cap_deliverables($db, $capId), null, 'kind');

$addWork = function (int $uid) use ($db, $capId, $cap, $dels): void {
    $att = cap_latest_attempt($db, $capId, $uid);
    $owner = cap_owner($db, $att);
    $w = cap_work($db, $owner, true);
    cap_put_item($db, (int) $w['id'], (int) $dels['project_link']['id'], $dels['project_link'], ['url' => 'https://github.com/x/y' . $uid, 'file_path' => null, 'file_name' => null, 'link_status' => 'ok', 'link_message' => 'ok', 'same' => 0]);
    $db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at) VALUES (?, ?, ?, ?)')->execute([(int) $owner['id'], $uid, (int) $w['version'], cap_now()]);
};
/** Submit and mark so the total is $total (parts spread), optionally held. */
$markTo = function (int $uid, float $total, ?string $held = null) use ($db, $capId, $cap, $addWork, $tutorId, $tutorUid): array {
    $addWork($uid);
    $s = cap_submit($db, cap_for_student($db, $capId, $uid), cap_latest_attempt($db, $capId, $uid), $uid, true);
    $a = cap_latest_attempt($db, $capId, $uid);
    $left = $total;
    $in = [];
    foreach (['project' => 50, 'docs' => 20, 'present' => 20, 'time' => 10] as $k => $max) { $v = min($max, $left); $in['p_' . $k] = $v; $left -= $v; }
    $in['feedback'] = 'SECRET-COMMENT-TEXT';
    $in['c_project'] = 'SECRET-PROJECT-COMMENT';
    if ($held) $in['held_until'] = $held;
    return cap_save_mark($db, (int) $a['id'], $tutorId, $in, $tutorUid);
};
$pay = function (int $uid, float $amount) use ($db, $reg, $tutorUid): void {
    $db->prepare("INSERT INTO payments (registration_id, amount, payer_reference, recorded_by, status, paid_at) VALUES (?, ?, 'TEST', ?, 'approved', ?)")->execute([$reg[$uid], $amount, $tutorUid, gmdate('Y-m-d H:i:s')]);
};
$assignment = function (int $uid, float $score, string $title) use ($db, $tutorId, $courseId): void {
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, title, status, grading_mode, max_score, weight, kind, due_at) VALUES (?, ?, ?, 'published', 'marks', 100, 1, 'daily', ?)")->execute([$tutorId, $courseId, $title, gmdate('Y-m-d H:i:s', time() - 86400)]);
    $aid = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id) VALUES (?, ?)')->execute([$aid, $uid]);
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, graded_at, is_late) VALUES (?, ?, 'graded', ?, ?, ?, ?, 0)")
       ->execute([$aid, $uid, gmdate('Y-m-d H:i:s', time() - 3600), $score, gmdate('Y-m-d H:i:s', time() - 1800), gmdate('Y-m-d H:i:s', time() - 1800)]);
};
$finish = fn(int $uid) => cap_finish_course($db, $tutorId, $uid, $courseId, date('Y-m-d'), '', $tutorUid);
$rel = fn(int $uid) => cap_release_row($db, $uid, $courseId);
$released = fn(int $uid) => ($row = cap_release_row($db, $uid, $courseId)) && $row['transcript_released_at'] && $row['letter_released_at'];
$ready = fn(int $uid) => cap_certificate_readiness($db, $uid, $courseId, $reg[$uid]);
$queue = (new ReflectionClass('AssignmentNotifier'))->getProperty('queue');
$queue->setAccessible(true);
$mailsFor = function (string $email) use ($queue): array { return array_values(array_filter($queue->getValue(), fn($m) => $m['to_email'] === $email)); };

// ============================================================ all four true -> Ready, release, certificate
section('1. All four conditions true');
$u = $stu['ready'];
PortalNotifications::saveChoices($db, $u, ['assignments']);   // the student switched this kind of notice on, so the notice is stored
$markTo($u, 64);
check('capstone released and passed, but the course is not finished yet: NOT ready', !$ready($u)['ready'] && $ready($u)['word'] === 'Not ready');
$finish($u);
check('finished + capstone 64 but fees not paid: Waiting for fees', $ready($u)['word'] === 'Waiting for fees', $ready($u)['word']);
check('...and nothing is released while fees are unpaid', !$released($u));
$pay($u, 101000);
$x = cap_release_sync($db, $u, $courseId);
$r1 = $ready($u);
check('finished + fees 100% + capstone 64 (released) + average 64: READY', $r1['ready'] && $r1['word'] === 'Ready', json_encode($r1['words']));
check('...the transcript and the letter are released together at once', $x === 'released' && $released($u), (string) $x);
$row = $rel($u);
check('transcript_released_at and letter_released_at are stored, with a letter code', !empty($row['transcript_released_at']) && !empty($row['letter_released_at']) && strlen((string) $row['letter_hash']) === 32);
$nt = $db->prepare("SELECT title, body FROM portal_notifications WHERE user_id = ? AND title LIKE 'Your course is complete%'");
$nt->execute([$u]);
$notices = $nt->fetchAll(PDO::FETCH_ASSOC);
$prefs = PortalNotifications::choices($db, $u);
check('the student got exactly one notification: "Your course is complete. Your transcript and letter of recommendation are ready."',
    count($notices) === 1 && $notices[0]['title'] === 'Your course is complete. Your transcript and letter of recommendation are ready.' && !preg_match('/\d/', $notices[0]['body'] . ''), json_encode($notices));
$mails = $mailsFor("ready$run@r.test");
$completion = array_values(array_filter($mails, fn($m) => strpos($m['subject'], 'Your course is complete') === 0));
check('one email was queued for it', count($completion) === 1, (string) count($mails));
check('the email has no mark, no number from the mark, no comment', $completion && !preg_match('/\b64\b|SECRET|out of 100|mark/i', $completion[0]['html']));
check('a second check does not release or notify again', cap_release_sync($db, $u, $courseId) === 'unchanged' && count($mailsFor("ready$run@r.test")) === count($mails));
$c = cert_create_for_registration($db, $reg[$u]);
check('the certificate can be created by the office flow', $c['ok'], json_encode($c));
check('...and issued', $c['ok'] && cert_issue($db, (int) $c['id']) === true);
check('after the certificate is issued, the release no longer changes (undo is blocked)', !cap_unfinish_course($db, $tutorId, $u, $courseId, $tutorUid)['ok'] && $released($u));

// ============================================================ remove one condition at a time
section('2. Remove one condition in turn');
$fakeCert = function (int $uid) use ($db, $reg, $courseId): int {
    $db->prepare("INSERT INTO certificates (registration_id, course_id, certificate_no, qr_code_hash, status, snapshot_name, snapshot_course, snapshot_duration_months, snapshot_dates) VALUES (?, ?, ?, ?, 'being_prepared', 'x', 'x', 12, 'x')")
       ->execute([$reg[$uid], $courseId, 'TMP-' . bin2hex(random_bytes(4)), bin2hex(random_bytes(8))]);
    return (int) $db->lastInsertId();
};
// (a) not finished
$u = $stu['notfin']; $markTo($u, 70); $pay($u, 101000); cap_release_sync($db, $u, $courseId);
$g = cert_create_for_registration($db, $reg[$u]);
check('(a) not finished: not ready, nothing released', !$ready($u)['ready'] && !$released($u));
check('(a) the certificate button path refuses with a clear message', !$g['ok'] && stripos($g['error'], 'finished') !== false, $g['error'] ?? '');
$cid = $fakeCert($u);
check('(a) a direct issue (cert_issue / cert_can_issue) is refused', cert_issue($db, $cid) === false && !cert_can_issue($db, $cid)['ok']);
$st = $db->prepare("SELECT status FROM certificates WHERE id = ?"); $st->execute([$cid]);
check('(a) the certificate row stays not issued', $st->fetchColumn() === 'being_prepared');
// (b) fees
$u = $stu['unpaid']; $markTo($u, 70); $finish($u); cap_release_sync($db, $u, $courseId);
$g = cert_create_for_registration($db, $reg[$u]); $cid = $fakeCert($u);
check('(b) fees unpaid: Waiting for fees, nothing released, button refused, direct issue refused', $ready($u)['word'] === 'Waiting for fees' && !$released($u) && !$g['ok'] && stripos($g['error'], 'balance') !== false && cert_issue($db, $cid) === false);
// (c) capstone failed
$u = $stu['failed']; $markTo($u, 40); $finish($u); $pay($u, 101000); cap_release_sync($db, $u, $courseId);
$g = cert_create_for_registration($db, $reg[$u]); $cid = $fakeCert($u);
check('(c) capstone failed (40): Retake needed, nothing released, refused', $ready($u)['word'] === 'Retake needed' && !$released($u) && !$g['ok'] && stripos($g['error'], 'retake') !== false && cert_issue($db, $cid) === false, $ready($u)['word']);
check('(c) admin word for the capstone is "Not passed" (no number)', $ready($u)['words']['capstone'] === 'Not passed');
// (d) average exactly 49 with a passed capstone (59, weight 5) and five assignments at 39%
$u = $stu['avg49']; $markTo($u, 59); foreach ([39, 39, 39, 39, 39] as $i => $sc) $assignment($u, $sc, 'weak' . $i);
$finish($u); $pay($u, 101000); cap_release_sync($db, $u, $courseId);
$a49 = cap_course_average($db, $u, $courseId);
$g = cert_create_for_registration($db, $reg[$u]);
check('(d) capstone passed (59) and average exactly 49: Retake needed, nothing released', abs($a49 - 49) < 0.001 && $ready($u)['word'] === 'Retake needed' && !$released($u), (string) $a49);
check('(d) refused with the average named, and the tutor sees the failed part', !$g['ok'] && stripos($g['error'], 'average') !== false && stripos((string) $ready($u)['detail']['failed_part'], 'daily') !== false, (string) $ready($u)['detail']['failed_part']);
check('(d) the admin words for it are plain: Average Fail, Certificate Retake needed', $ready($u)['words']['average'] === 'Fail' && $ready($u)['words']['certificate'] === 'Retake needed');

// ============================================================ held mark, fees 99%, retake payment
section('3. Held mark, 99% fees, retake payment');
$u = $stu['held']; $markTo($u, 50, gmdate('Y-m-d H:i:s', time() + 86400)); $finish($u); $pay($u, 101000); cap_release_sync($db, $u, $courseId);
$rh = $ready($u);
check('capstone mark 50 but HELD: the capstone does not count yet, word Not ready, nothing released', !$rh['ready'] && $rh['words']['capstone'] === 'Not marked yet' && $rh['word'] === 'Not ready' && !$released($u), json_encode($rh['words']));
check('...and the average does not count the held mark either', $rh['detail']['average'] === null);
$db->prepare('UPDATE capstone_marks SET held_until = ? WHERE attempt_id = (SELECT id FROM capstone_attempts WHERE capstone_id = ? AND student_user_id = ?)')->execute([gmdate('Y-m-d H:i:s', time() - 5), $capId, $u]);
$sw = cap_release_sweep($db);
check('after the hold ends the scheduled job releases it (mark 50 passes)', $ready($u)['ready'] && $released($u) && $sw['released'] >= 1);
$u = $stu['f99']; $markTo($u, 65); $finish($u); $pay($u, 99990); cap_release_sync($db, $u, $courseId);
check('fees 99%: Waiting for fees, nothing released', $ready($u)['word'] === 'Waiting for fees' && !$released($u) && cert_student_balance($db, $reg[$u]) > 0, (string) cert_student_balance($db, $reg[$u]));
$pay($u, 1010); cap_release_sync($db, $u, $courseId);
check('the last 1% paid: Ready and released', $ready($u)['ready'] && $released($u));

$u = $stu['retakepaid']; $markTo($u, 65); $finish($u);
$db->prepare('INSERT INTO capstone_retake_fees (course_id, amount) VALUES (?, 5000) ON DUPLICATE KEY UPDATE amount = VALUES(amount)')->execute([$courseId]);
$balBefore = cert_student_balance($db, $reg[$u]);
$att = cap_latest_attempt($db, $capId, $u);
$db->prepare("INSERT INTO capstone_attempts (capstone_id, student_user_id, attempt_no, status, retake_fee) VALUES (?, ?, 2, 'awaiting_payment', 5000)")->execute([$capId, $u]);
$newAtt = (int) $db->lastInsertId();
cap_retake_paid($db, $newAtt, 5000, 'RT1', $adminUid);
check('a paid retake does not change the course fee balance', cert_student_balance($db, $reg[$u]) === $balBefore && $balBefore > 0);
check('...and does not satisfy the fees condition', !$ready($u)['conditions']['fees']['ok'] && $ready($u)['words']['fees'] === 'Balance due');
check('...and the retake is not a condition: no transcript or letter', !$released($u));

// ============================================================ undo before issue hides again
section('4. Undo "course finished"');
$u = $stu['undo']; $markTo($u, 66); $finish($u); $pay($u, 101000); cap_release_sync($db, $u, $courseId);
check('ready and released', $released($u));
$un = cap_unfinish_course($db, $tutorId, $u, $courseId, $tutorUid);
check('undo before the certificate is issued works', $un['ok']);
$row = $rel($u);
check('the transcript and the letter are hidden again (hidden_at set, release times cleared)', !$released($u) && empty($row['transcript_released_at']) && empty($row['letter_released_at']) && !empty($row['hidden_at']));
$finish($u);
check('finishing again releases them again', $released($u));

// ============================================================ letter
section('5. The letter');
$u = $stu['letter'];
$markTo($u, 74);
$d = cap_letter_data($db, $u, $courseId);
check('a draft letter before finishing has no result problem', $d !== null);
// finished early: planned 12 months, finish after 5.5 months
$db->prepare('INSERT INTO course_finish (student_user_id, course_id, registration_id, tutor_id, planned_months, finished_after_months, finished_on, note) VALUES (?, ?, ?, ?, 12, 5.5, ?, NULL)')
   ->execute([$u, $courseId, $reg[$u], $tutorId, date('Y-m-d')]);
$pay($u, 101000);
cap_letter_note_save($db, $tutorId, $u, $courseId, 'Stu Letter led her team calmly and helped others fix their code.', $tutorUid);
cap_release_sync($db, $u, $courseId);
$d = cap_letter_data($db, $u, $courseId);
$paras = implode("\n", cap_letter_paragraphs($d));
check('the letter has the right name, course and registration number', strpos($paras, 'Stu Letter') !== false && strpos($paras, 'Readiness Course') !== false && strpos($paras, 'Rletter') !== false);
check('it has the real start and finish dates', strpos($paras, date('F Y')) !== false);
check('a shortened course says so (5.5 months instead of 12)', $d['shortened'] && strpos($paras, '5.5 months') !== false && strpos($paras, 'planned 12 months') !== false, $paras);
check('it has the result word (Distinction for 74) and the capstone title', strpos($paras, 'Distinction') !== false && strpos($paras, 'Build a booking app') !== false);
check('it shows NO mark', !preg_match('/\b74\b|out of 100|marks?\b|\bscore/i', $paras) && !preg_match('/\b74\b/', cap_letter_html($db, $d, false)));
check('it carries the tutor\'s sentence', strpos($paras, 'led her team calmly') !== false);
$pdf = cap_letter_pdf($db, $d, false);
check('the PDF is made (director signature picked up automatically)', strncmp($pdf, '%PDF', 4) === 0 && strlen($pdf) > 2000, (string) strlen($pdf));
$html = cap_letter_html($db, $d, false);
check('the letter carries a verification QR to the public check page', strpos($html, 'data:image/png;base64') !== false && !empty($d['hash']));
check('the tutor can still change the sentence only before release', !cap_letter_note_save($db, $tutorId, $u, $courseId, 'late edit', $tutorUid)['ok']);
$g = cap_certificate_gate($db, $u, $courseId, $reg[$u]);
$words = cap_admin_cert_words($db, ['id' => $reg[$u], 'user_id' => $u, 'course_id' => $courseId]);
check('the tutor\'s sentence never reaches the admin words or gate', strpos(json_encode([$g, $words]), 'calmly') === false);
$adminSrc = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../../public/admin')) as $f) if ($f->isFile() && substr($f->getFilename(), -4) === '.php') $adminSrc .= file_get_contents($f->getPathname());
check('no admin page reads the letter text, a mark, a comment or a capstone file', !preg_match('/letter_note|cap_letter|capstone_marks|capstone_submission|capstone_returns|c_project|completion_letter/', $adminSrc));
check('the admin words contain no digits', !preg_match('/\d/', implode(' ', $words)), json_encode($words));

// ============================================================ transcript access (function level)
section('6. Transcript access rule');
$a = $stu['ready']; $b = $stu['other'];
check('a student who is Ready: transcript released', cap_release_state($db, $a, $courseId)['transcript']);
check('another student (not Ready) cannot get a release by changing the course/user', !cap_release_state($db, $b, $courseId)['transcript']);
check('before release the student state is "not released"', !cap_release_state($db, $stu['notfin'], $courseId)['transcript']);

// ============================================================ course without a capstone
section('7. A course without a capstone');
$pr = cap_certificate_readiness($db, $plain, $plainCourse, $reg[$plain]);
check('readiness does not apply and nothing is released', !$pr['applies'] && cap_release_sync($db, $plain, $plainCourse) === 'unchanged' && cap_release_row($db, $plain, $plainCourse) === null);
$e = cert_eligibility($db, $reg[$plain]);
check('the old rule is unchanged: fees unpaid blocks', !$e['ok'] && stripos($e['reason'], 'Fee balance') !== false);
$pay($plain, 51000);
check('the old rule is unchanged: fees paid allows the certificate', cert_eligibility($db, $reg[$plain])['ok'] === true);
$cc = cert_create_for_registration($db, $reg[$plain]);
check('...and the certificate is created and issued as before', $cc['ok'] && cert_issue($db, (int) $cc['id']) === true);

// ============================================================ audit, static
section('8. Audit log');
$evs = $db->query("SELECT event, COUNT(*) c FROM capstone_events WHERE event IN ('course_finished','course_unfinished','release_released','release_hidden','certificate_issued','certificate_refused') GROUP BY event")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['course_finished', 'course_unfinished', 'release_released', 'release_hidden', 'certificate_issued', 'certificate_refused'] as $ev) check("audit log has: $ev", !empty($evs[$ev]));
$ref = $db->query("SELECT details FROM capstone_events WHERE event = 'certificate_refused' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
$conds = []; foreach ($ref as $d0) { $j = json_decode($d0, true); if (!empty($j['condition'])) $conds[$j['condition']] = true; }
check('refusals record WHICH condition failed (finished, fees, capstone, average)', isset($conds['finished'], $conds['fees'], $conds['capstone'], $conds['average']), implode(',', array_keys($conds)));
$edit = file_get_contents(__DIR__ . '/../../../public/admin/certificates/edit.php');
check('the admin certificate edit page (direct URL) also checks cert_can_issue on the server', strpos($edit, 'cert_can_issue') !== false);

file_put_contents(sys_get_temp_dir() . '/readiness_users.json', json_encode($logins + ['_run' => $run, '_course' => $courseId, '_cap' => $capId]));
AssignmentNotifier::flush();
echo "\n" . ($fails ? "$fails of $n FAILED\n" : "All $n passed\n");
exit($fails ? 1 : 0);
