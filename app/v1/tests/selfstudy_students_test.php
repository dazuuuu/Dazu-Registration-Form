<?php
// Self-study (step 7): the Students page — who sees whom, the system comment (live), the four numbers, reset requests, reset limits and records.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_students_test.php     (a scratch database with every migration applied, 110 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyProgress.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
function refused(callable $f): ?int { try { $f(); return null; } catch (SscException $e) { return $e->getCode(); } }
$cols = $db->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'self_study_resets' AND column_name = 'note'")->fetchColumn();
if (!$cols) { fwrite(STDERR, "Apply migration 110 first.\n"); exit(2); }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sss_{$k}_$run", "sss_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$mkTutor = function (string $k) use ($db, $mk): array {
    $u = $mk($k, 'tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$u]);
    return ['uid' => $u, 'role' => 'tutor', 'tutor_id' => (int) $db->lastInsertId(), 'name' => ucfirst($k) . ' Tester'];
};
$O = $mkTutor('owner'); $E = $mkTutor('editor'); $V = $mkTutor('viewer'); $S = $mkTutor('stranger');
$adminUid = $mk('admin', 'admin'); $A = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Admin Tester'];
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 12, 100000, 1, 'diploma')")->execute(["Reg A $run", 'RA' . strtoupper(substr($run, 0, 4))]); $RC = (int) $db->lastInsertId();
$stu = function (string $k, ?array $tutor) use ($db, $mk, $run, $RC): int {
    $uid = $mk($k, 'student');
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T', ?)")->execute(['SSS-' . $k . $run, ucfirst($k) . ' Student', "$k$run@s.test", $RC, $uid, gmdate('Y-m-d H:i:s', time() - 100 * 86400)]);
    if ($tutor) $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tutor['tutor_id'], $uid, gmdate('Y-m-d', time() - 100 * 86400)]);
    return $uid;
};
$w1 = $stu('w1', $O); $sl = $stu('sl', $O); $nw = $stu('nw', $O); $fin = $stu('fin', $O); $nst = $stu('nst', $O); $e1 = $stu('e1', $E); $v1 = $stu('v1', $V); $loose = $stu('loose', null);
$all = [$w1, $sl, $nw, $fin, $nst, $e1, $v1, $loose];
$now = strtotime('2026-11-13 10:00:00 +03:00'); $day = 86400;
$r = sst_save_details($db, $O, null, ['title' => 'Students test ' . $run, 'short_description' => 'd', 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60]);
$cid = (int) $r['id'];
$db->prepare("UPDATE self_study_courses SET status = 'published', published_at = ? WHERE id = ?")->execute([ss2_ts($now), $cid]);
foreach ([[$E, 'edit'], [$V, 'view']] as [$t, $a]) $db->prepare('INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, ?, ?, ?)')->execute([$cid, $t['tutor_id'], $a, $O['uid'], ss2_ts($now)]);
$addedBy = [$w1 => $O, $sl => $O, $nw => $O, $fin => $O, $nst => $O, $e1 => $E, $v1 => $V, $loose => null];
foreach ($addedBy as $sid => $t) $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by, added_by_tutor_id, added_at) VALUES (?, ?, ?, ?, ?)')->execute([$cid, $sid, $t ? $t['uid'] : $adminUid, $t ? $t['tutor_id'] : null, ss2_ts($now - 30 * $day)]);
$db->prepare('UPDATE self_study_access SET added_at = ? WHERE course_id = ? AND student_id = ?')->execute([ss2_ts($now - 3 * $day), $cid, $nst]);       // added 3 days ago, never opened
foreach ($all as $u) $db->prepare("INSERT INTO notification_preferences (user_id, category, enabled) VALUES (?, 'other', 1)")->execute([$u]);

// content: 2 modules, topics, a quiz bank with topic tags
$mod = function (string $t, int $p) use ($db, $cid): int { $db->prepare('INSERT INTO self_study_modules (course_id, title, position) VALUES (?, ?, ?)')->execute([$cid, $t, $p]); return (int) $db->lastInsertId(); };
$top = function (string $t, int $p, int $m) use ($db, $cid): int { $db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html) VALUES (?, ?, ?, ?, '<p>x</p>')")->execute([$cid, $m, $t, $p]); return (int) $db->lastInsertId(); };
$m1 = $mod('Start', 1); $m2 = $mod('Data', 2);
$t10 = $top('Intro', 1, $m1); $t20 = $top('Reading', 2, $m2); $t21 = $top('Cleaning', 3, $m2); $t22 = $top('Filtering', 4, $m2);
$qn = fn(string $place, ?int $m, ?int $t) => $db->prepare("INSERT INTO self_study_questions (course_id, place, module_id, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, ?, ?, ?, 'multiple_choice', 'Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $place, $m, $t]);
$qn('module_quiz', $m1, $t10); $qn('module_quiz', $m2, $t22); $qn('module_quiz', $m2, $t22); $qn('checkpoint_exam', null, $t10); $qn('final_exam', null, $t10);
$qT22 = (int) $db->query("SELECT id FROM self_study_questions WHERE course_id = $cid AND module_id = $m2 LIMIT 1")->fetchColumn();
$prog = fn(int $s, int $pct, ?int $last, ?int $done = null) => $db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at, completed_at) VALUES (?, ?, ?, ?, ?)')->execute([$cid, $s, $pct, $last ? ss2_ts($last) : null, $done ? ss2_ts($done) : null]);
$topicDone = fn(int $s, int $t) => $db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) VALUES (?, ?, ?, ?)')->execute([$s, $t, $cid, ss2_ts($now - 5 * $day)]);
$att = function (int $s, string $kind, ?int $m, int $at, bool $pass, int $score = 30, ?int $cleared = null) use ($db, $cid) {
    $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids, tries_cleared_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', '[]', ?)")
       ->execute([$s, $cid, $kind, $m, ss2_ts($at), ss2_ts($at + 300), $score, $pass ? 1 : 0, $cleared ? ss2_ts($cleared) : null]);
    return (int) $db->lastInsertId();
};
// w1: working well, in module 2, passed module 1 quiz
$prog($w1, 40, $now - 1 * $day); $topicDone($w1, $t10); $att($w1, 'module_quiz', $m1, $now - 4 * $day, true, 83);
// sl: studied 9 days ago
$prog($sl, 20, $now - 9 * $day); $topicDone($sl, $t10);
// nw: studied 19 days ago
$prog($nw, 12, $now - 19 * $day);
// fin: finished
$prog($fin, 100, $now - 2 * $day, $now - 2 * $day);
// nst: never opened (added 3 days ago): no progress row
// e1: studied yesterday but failed the Module 2 quiz twice in a row; v1: used all 3 tries this week and failed
$prog($e1, 50, $now - 1 * $day); $topicDone($e1, $t10); $att($e1, 'module_quiz', $m1, $now - 6 * $day, true, 100); foreach ([$t20, $t21, $t22] as $t) $topicDone($e1, $t);
$a1 = $att($e1, 'module_quiz', $m2, $now - 2 * $day, false, 20); $a2 = $att($e1, 'module_quiz', $m2, $now - 1 * $day, false, 30);
foreach ([$a1, $a2] as $a) $db->prepare('INSERT INTO self_study_attempt_answers (attempt_id, question_id, answer, is_correct) VALUES (?, ?, ?, 0)')->execute([$a, $qT22, '1']);
$prog($v1, 30, $now - 1 * $day); $topicDone($v1, $t10); $att($v1, 'module_quiz', $m1, $now - 5 * $day, true, 100); foreach ([$t20, $t21, $t22] as $t) $topicDone($v1, $t);
foreach ([3, 2, 1] as $d) $att($v1, 'module_quiz', $m2, $now - $d * 3600 * 3, false, 20);
$prog($loose, 5, $now - 3 * $day);
$rows = fn(array $ov) => array_column($ov['rows'], null, 'id');

echo "== Who sees whom\n";
$ovO = ssd_overview($db, $O, $cid, $now);
check('the owner sees every student on the course (8) with who added them', count($ovO['rows']) === 8 && $rows($ovO)[$e1]['added_by'] === 'Editor Tester' && $rows($ovO)[$loose]['added_by'] === 'Admin' && $rows($ovO)[$w1]['added_by'] === 'Owner Tester');
$ovE = ssd_overview($db, $E, $cid, $now);
check('a tutor with Can edit sees only their own student (e1) and no "added by" note', array_keys($rows($ovE)) === [$e1] && $rows($ovE)[$e1]['added_by'] === '');
check('a "Can view" tutor sees only their own student too', array_keys($rows(ssd_overview($db, $V, $cid, $now))) === [$v1]);
check('admin sees everyone', count(ssd_overview($db, $A, $cid, $now)['rows']) === 8);
check('a tutor with no access gets 404', refused(fn() => ssd_overview($db, $S, $cid, $now)) === 404);
$_SESSION = ['user_id' => $w1, 'role' => 'student']; $stf = $mk('staff', 'employee');
check('a student cannot open the page (no viewer)', sst_viewer($db, 'tutor') === null && sst_viewer($db, 'admin') === null);
$_SESSION = ['user_id' => $stf, 'role' => 'employee'];
check('staff cannot open the page (no viewer)', sst_viewer($db, 'tutor') === null && sst_viewer($db, 'admin') === null);

echo "\n== The four numbers\n";
$S0 = $ovO['summary'];
$pcts = array_column($ovO['rows'], 'percent');
check('students and courses', $S0['students'] === 8 && $S0['courses'] === 1);
check('started = studied at least once (7 of 8; one never opened it)', $S0['started'] === 7 && $S0['not_opened'] === 1);
check('average progress over all students', $S0['average'] === (int) round(array_sum($pcts) / 8), "{$S0['average']}");
check('finished = passed the Final Exam / got the badge', $S0['finished'] === 1);
check('for a tutor the numbers cover only their own students', ssd_overview($db, $E, $cid, $now)['summary']['students'] === 1);

echo "\n== The system comment (worked out live)\n";
$R = $rows($ovO);
check('studied yesterday, nothing failed = Working well', $R[$w1]['status']['key'] === 'working' && $R[$w1]['status']['word'] === 'Working well' && str_contains($R[$w1]['status']['reason'], 'Moving forward'), json_encode($R[$w1]['status']));
check('nothing studied for 9 days = Slowing down', $R[$sl]['status']['key'] === 'slowing' && str_contains($R[$sl]['status']['reason'], '9 days'));
check('nothing studied for 19 days = Not working', $R[$nw]['status']['key'] === 'notworking' && str_contains($R[$nw]['status']['reason'], '19 days'));
check('added 3 days ago and not opened = "Not started yet", no warning', $R[$nst]['status']['key'] === 'notstarted' && $R[$nst]['status']['word'] === 'Not started yet');
check('a student who passed the Final Exam shows Finished', $R[$fin]['status']['key'] === 'finished' && $R[$fin]['percent'] === 100);
check('two failed tries in a row on the same quiz = Slowing down, even though they studied yesterday', $R[$e1]['status']['key'] === 'slowing' && str_contains($R[$e1]['status']['reason'], 'Failed the Module 2 quiz 2 times'), $R[$e1]['status']['reason']);
check('the reason names the topic to read again (from the wrong answers: topic 2.2)', str_contains($R[$e1]['status']['reason'], 'May need help with topic 2.2'), $R[$e1]['status']['reason']);
check('every try of the week used up and failed = Not working', $R[$v1]['status']['key'] === 'notworking' && str_contains($R[$v1]['status']['reason'], 'Used every try on the Module 2 quiz') , $R[$v1]['status']['reason']);
$set = ssd_settings($db);
$st = fn(array $s, array $items = []) => ssd_status($s + ['completed' => false, 'added_ts' => $now - 30 * $day], $set, $items, $now)['key'];
check('the day edges: 6 days working, 7 slowing, 14 slowing, 15 not working', $st(['last_ts' => $now - 6 * $day]) === 'working' && $st(['last_ts' => $now - 7 * $day]) === 'slowing' && $st(['last_ts' => $now - 14 * $day]) === 'slowing' && $st(['last_ts' => $now - 15 * $day]) === 'notworking');
check('a never-opened student: under 7 days no warning, 8 days Slowing, 20 days Not working', $st(['last_ts' => null, 'added_ts' => $now - 6 * $day]) === 'notstarted' && $st(['last_ts' => null, 'added_ts' => $now - 8 * $day]) === 'slowing' && $st(['last_ts' => null, 'added_ts' => $now - 20 * $day]) === 'notworking');
$it = fn(int $run, bool $passed, bool $blocked = false) => ['label' => 'Module 2 quiz', 'passed' => $passed, 'failed' => $run, 'run' => $run, 'blocked' => $blocked, 'topic' => ''];
check('one failed try is not enough; a quiz that was passed after failing does not count', $st(['last_ts' => $now - $day], ['a' => $it(1, false)]) === 'working' && $st(['last_ts' => $now - $day], ['a' => $it(0, true)]) === 'working');
$rowsE = $rows(ssd_overview($db, $O, $cid, $now));
check('the status is live: a new day moves the student on (the same data, a week later)', ssd_overview($db, $O, $cid, $now + 8 * $day)['rows'][array_search($w1, array_column(ssd_overview($db, $O, $cid, $now + 8 * $day)['rows'], 'id'))]['status']['key'] === 'slowing');
check('the "Module k of N" label and last studied text', $R[$e1]['module'] === 'Module 2 of 2' && $R[$w1]['module'] === 'Module 2 of 2' && $R[$sl]['module'] === 'Module 1 of 2' && $R[$w1]['last'] === 'Yesterday' && $R[$sl]['last'] === '9 days ago' && $R[$nst]['last'] === 'Not yet', json_encode([$R[$e1]['module'], $R[$w1]['module'], $R[$sl]['module']]));
check('the chip counts match', $ovO['counts']['slowing'] === 2 && $ovO['counts']['notworking'] === 2 && $ovO['counts']['all'] === 8, json_encode($ovO['counts']));


echo "\n== The student panel\n";
$d = ssd_student_detail($db, $O, $cid, $e1, $now);
check('the panel shows each module and its quiz score, tries, exams', count($d['modules']) === 2 && $d['modules'][0]['quiz']['passed'] && $d['modules'][1]['quiz']['best'] === 30 && $d['modules'][1]['quiz']['week_used'] === 2 && count($d['exams']) === 2 && $d['exams'][0]['label'] === 'Checkpoint Exam');
check('a tutor can open their own student but not another tutor\'s', refused(fn() => ssd_student_detail($db, $E, $cid, $w1, $now)) === 404 && ssd_student_detail($db, $E, $cid, $e1, $now)['student']['id'] === $e1);

echo "\n== Reset requests and who can answer them\n";
$ins = $db->prepare("INSERT INTO self_study_reset_requests (student_id, course_id, kind, module_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'waiting', ?)");
$ins->execute([$e1, $cid, 'module_quiz', $m2, 'I read topic 2.2 again and I am ready.', ss2_ts($now - 3600)]); $rq1 = (int) $db->lastInsertId();
$ins->execute([$w1, $cid, 'module_quiz', $m2, 'Please reset.', ss2_ts($now - 3000)]); $rq2 = (int) $db->lastInsertId();
$ovE = ssd_overview($db, $E, $cid, $now); $ovO = ssd_overview($db, $O, $cid, $now); $ovV = ssd_overview($db, $V, $cid, $now);
check('a tutor sees only the requests of their own students; the owner sees all', array_column($ovE['requests'], 'id') === [$rq1] && count($ovO['requests']) === 2 && $ovV['requests'] === []);
$req = $ovE['requests'][0];
check('a request shows the student, the quiz, the tries used and the reason', $req['student'] === 'E1 Student' && $req['item'] === 'Module 2 quiz' && $req['tries'] === '2 of 3 tries used this week' && str_contains($req['reason'], 'topic 2.2'), json_encode($req));
check('and how many resets the tutor has left: "0 of 2 used this month"', $req['limit_text'] === '0 of 2 used this month' && $req['can_reset'] === true);
check('a tutor cannot reset another tutor\'s student or answer their request', refused(fn() => ssd_reset($db, $E, $cid, $rq2, null, '', null, '', '', $now)) === 403 && refused(fn() => ssd_decline($db, $E, $cid, $rq2, '', $now)) === 403 && refused(fn() => ssd_reset($db, $E, $cid, null, $w1, 'module_quiz', $m2, 'why', '', $now)) === 403);
check('a "Can view" tutor cannot reset a student who is not theirs, and a stranger gets 404', refused(fn() => ssd_reset($db, $V, $cid, null, $e1, 'module_quiz', $m2, 'why', '', $now)) === 403 && refused(fn() => ssd_reset($db, $S, $cid, null, $e1, 'module_quiz', $m2, 'why', '', $now)) === 404);
$beforeAtt = (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $e1")->fetchColumn();
$r = ssd_reset($db, $E, $cid, $rq1, null, '', null, '', 'Good luck', $now);
check('"Reset quiz" clears the tries of the week for that one quiz', $r['ok'] && $r['cleared'] === 2 && $r['window'] === 'week' && ss3_tries($db, $e1, $cid, 'module_quiz', $m2, $now)['windows']['week']['used'] === 0);
check('the attempts and the earlier pass are kept (only marked as cleared)', (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $e1")->fetchColumn() === $beforeAtt && ss2_model($db, $cid, $e1)['modules'][0]['quiz']['passed'] === true && (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $e1 AND tries_cleared_at IS NOT NULL")->fetchColumn() === 2);
check('another quiz of the same student is not touched', ss3_tries($db, $v1, $cid, 'module_quiz', $m2, $now)['windows']['week']['used'] === 3);
$rec = $db->query("SELECT reset_by, reason, note, request_id, tries_cleared FROM self_study_resets WHERE student_id = $e1")->fetch(PDO::FETCH_ASSOC);
check('a record is written: who, why (the student\'s reason), the tutor\'s note, the request', (int) $rec['reset_by'] === $E['uid'] && str_contains($rec['reason'], 'topic 2.2') && $rec['note'] === 'Good luck' && (int) $rec['request_id'] === $rq1 && (int) $rec['tries_cleared'] === 2);
check('the request is marked granted and the student was told', $db->query("SELECT status FROM self_study_reset_requests WHERE id = $rq1")->fetchColumn() === 'granted' && (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $e1 AND type = 'selfstudy_reset'")->fetchColumn() === 1);
check('the activity log shows the reset with who did it', (int) $db->query("SELECT COUNT(*) FROM self_study_activity_log WHERE course_id = $cid AND action = 'reset' AND actor_user_id = {$E['uid']}")->fetchColumn() === 1);
check('a request that was already answered cannot be answered again', refused(fn() => ssd_reset($db, $E, $cid, $rq1, null, '', null, '', '', $now)) === 404);

echo "\n== The limit: twice a month per student, then only admin\n";
check('the usage shows "1 of 2 used this month"', ssd_overview($db, $E, $cid, $now + 60)['mine']['quiz'] === 1);
$att($e1, 'module_quiz', $m2, $now + 100, false, 10); $att($e1, 'module_quiz', $m2, $now + 200, false, 10);
$r2 = ssd_reset($db, $E, $cid, null, $e1, 'module_quiz', $m2, 'Asked in person after class', '', $now + 300);
check('a tutor can start a reset themselves with a reason; it counts as the second one', $r2['ok'] && (int) $db->query("SELECT COUNT(*) FROM self_study_resets WHERE student_id = $e1 AND reset_by = {$E['uid']}")->fetchColumn() === 2);
check('a tutor-started reset needs a reason', refused(fn() => ssd_reset($db, $E, $cid, null, $e1, 'module_quiz', $m2, '   ', '', $now + 400)) === 422);
$ins->execute([$e1, $cid, 'module_quiz', $m2, 'Again please', ss2_ts($now + 500)]); $rq3 = (int) $db->query("SELECT MAX(id) FROM self_study_reset_requests")->fetchColumn();
$ovE3 = ssd_overview($db, $E, $cid, $now + 600); $rq = array_values(array_filter($ovE3['requests'], fn($x) => $x['id'] === $rq3))[0];
check('the third request shows "2 of 2 used" and the button is switched off', $rq['limit_text'] === '2 of 2 used this month' && $rq['can_reset'] === false);
$code = refused(fn() => ssd_reset($db, $E, $cid, $rq3, null, '', null, '', '', $now + 700));
check('the server refuses it too: "Only admin can reset now"', $code === 403);
try { ssd_reset($db, $E, $cid, $rq3, null, '', null, '', '', $now + 700); } catch (SscException $x) { check('with that message', str_contains($x->getMessage(), 'Only admin can reset now')); }
$att($e1, 'checkpoint_exam', null, $now + 800, false, 20); $att($e1, 'checkpoint_exam', null, $now + 900, false, 20);
// the student has passed Module 1.. make the Checkpoint open for this student (all modules passed)
$db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'module_quiz', ?, ?, ?, 100, 1, 'submitted', '[]')")->execute([$e1, $cid, $m2, ss2_ts($now + 50), ss2_ts($now + 60)]);
check('(a passed quiz cannot be reset: nothing to reset)', refused(fn() => ssd_reset($db, $E, $cid, null, $e1, 'module_quiz', $m2, 'x', '', $now + 1000)) === 422);
$rx = ssd_reset($db, $E, $cid, null, $e1, 'checkpoint_exam', null, 'Power cut during the exam', '', $now + 1100);
check('an exam reset works (day window) and does not use the quiz allowance', $rx['ok'] && $rx['window'] === 'day' && ss3_tries($db, $e1, $cid, 'checkpoint_exam', null, $now + 1200)['windows']['day']['used'] === 0);
check('admin can reset beyond the limit', ($ra = ssd_reset($db, $A, $cid, null, $v1, 'module_quiz', $m2, 'Parent called the office', 'Reset by admin', $now + 1300)) && $ra['ok'] && $ra['cleared'] === 3);
foreach ([1, 2] as $_) ssd_reset($db, $A, $cid, null, $v1, 'module_quiz', $m2, 'again ' . $_, '', $now + 1400 + $_);
check('admin is never blocked by the limit (three resets of the same quiz in a month)', (int) $db->query("SELECT COUNT(*) FROM self_study_resets WHERE student_id = $v1 AND reset_by = $adminUid")->fetchColumn() >= 2);
$ovA = ssd_overview($db, $A, $cid, $now + 1500);
check('admin sees "no monthly limit" on requests and the button is on', ($rqa = array_values(array_filter($ovA['requests'], fn($x) => $x['id'] === $rq3))[0] ?? null) && $rqa['limit_text'] === 'Admin: no monthly limit' && $rqa['can_reset'] === true);
check('last month\'s resets do not count against this month', (function () use ($db, $cid, $e1, $E, $now) {
    $db->prepare('UPDATE self_study_resets SET created_at = ? WHERE student_id = ? AND reset_by = ?')->execute([ss2_ts($now - 40 * 86400), $e1, $E['uid']]);
    return (int) (ssd_usage($db, $E['uid'], $now + 1600)[$e1]['quiz'] ?? 0) === 0;
})());

echo "\n== \"Not now\"\n";
$ins->execute([$w1, $cid, 'module_quiz', $m2, 'One more try please', ss2_ts($now + 1700)]); $rq4 = (int) $db->query("SELECT MAX(id) FROM self_study_reset_requests")->fetchColumn();
$r = ssd_decline($db, $O, $cid, $rq4, 'Read topic 2.2 again first', $now + 1800);
check('"Not now" with a note: the request is declined, the note saved, nothing cleared, the student told', $r['ok'] && $db->query("SELECT status FROM self_study_reset_requests WHERE id = $rq4")->fetchColumn() === 'declined' && $db->query("SELECT handled_note FROM self_study_reset_requests WHERE id = $rq4")->fetchColumn() === 'Read topic 2.2 again first' && (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $w1 AND body LIKE '%Read topic 2.2 again first%'")->fetchColumn() === 1);
check('"Not now" does not use the monthly allowance', (int) (ssd_usage($db, $O['uid'], $now + 1900)[$w1]['quiz'] ?? 0) === 0);

echo "\n== Admin: every reset, and the settings\n";
$list = ssd_all_resets($db, $A, $cid);
check('admin sees every reset with who did it and why', count($list) >= 5 && in_array('Admin Tester', array_column($list, 'by')) && in_array('Editor Tester', array_column($list, 'by')) && $list[0]['reason'] !== '');
check('a tutor cannot see the full list', refused(fn() => ssd_all_resets($db, $O, $cid)) === 403);
check('the settings have the defaults 7 / 14 / 2 / 2 / 2', ($s0 = ssd_settings($db)) && $s0['working_days'] === 7 && $s0['slowing_days'] === 14 && $s0['failed_in_row'] === 2 && $s0['reset_quiz_month'] === 2 && $s0['reset_exam_month'] === 2);
check('admin can change them; bad numbers are refused', ssd_settings_save($db, ['failed_in_row' => 3, 'reset_quiz_month' => 4, 'reset_exam_month' => 1], $adminUid)['ok'] && ssd_settings($db)['failed_in_row'] === 3 && ssd_settings($db)['reset_quiz_month'] === 4 && !ssd_settings_save($db, ['failed_in_row' => 0, 'reset_quiz_month' => 4, 'reset_exam_month' => 1], $adminUid)['ok'] && !ssd_settings_save($db, ['failed_in_row' => 'x', 'reset_quiz_month' => 4, 'reset_exam_month' => 1], $adminUid)['ok']);
$set3 = ssd_settings($db);
check('the new numbers change the comment: 2 failed in a row is no longer "Slowing down" when the rule is 3', ssd_status(['completed' => false, 'last_ts' => $now - $day, 'added_ts' => $now - 30 * $day], $set3, ['a' => ['label' => 'Module 2 quiz', 'passed' => false, 'failed' => 2, 'run' => 2, 'blocked' => false, 'topic' => '']], $now)['key'] === 'working');
$db->exec("DELETE FROM progress_settings WHERE setting_key LIKE 'ss\\_%'");

echo "\n== Clean up\n";
foreach (['self_study_activity_log', 'self_study_resets', 'self_study_reset_requests', 'self_study_attempt_answers' => 'attempt_id IN (SELECT id FROM self_study_attempts WHERE course_id = ' . $cid . ')', 'self_study_attempts', 'self_study_topic_progress', 'self_study_progress', 'self_study_access', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_editors'] as $k => $t) {
    if (is_string($k)) $db->exec("DELETE FROM $k WHERE $t"); else $db->exec("DELETE FROM $t WHERE course_id = $cid");
}
$db->exec("DELETE FROM self_study_courses WHERE id = $cid");
$allU = implode(',', array_merge($all, [$O['uid'], $E['uid'], $V['uid'], $S['uid'], $adminUid, $stf]));
$db->exec("DELETE FROM portal_notifications WHERE user_id IN ($allU)"); $db->exec("DELETE FROM notification_preferences WHERE user_id IN ($allU)");
$db->exec("DELETE FROM tutor_students WHERE user_id IN ($allU)"); $db->exec("DELETE FROM registrations WHERE user_id IN ($allU)");
$db->exec('DELETE FROM tutors WHERE id IN (' . implode(',', [$O['tutor_id'], $E['tutor_id'], $V['tutor_id'], $S['tutor_id']]) . ')');
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($allU)"); $db->exec("DELETE FROM users WHERE id IN ($allU)");
$db->exec("DELETE FROM courses WHERE id = $RC");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
