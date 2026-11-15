<?php
// Self-study (step 3): module quizzes, Checkpoint and Final Exam, tries (Nairobi day/week/month), the server clock, resets, the result, completion.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_quiz_test.php     (a scratch database with every migration applied, 106 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyQuiz.php';
require_once __DIR__ . '/../services/SelfStudyContent.php';
require_once __DIR__ . '/../../../public/components/selfstudy/ui.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
if (!ss3_installed($db)) { fwrite(STDERR, "Apply migration 106 first.\n"); exit(2); }

echo "== Nairobi days, weeks and months\n";
$wed = strtotime('2026-11-11 10:00:00 +03:00');
$w = ss3_windows($wed);
check('the day starts at Nairobi midnight', $w['day'][0] === strtotime('2026-11-11 00:00:00 +03:00') && $w['day'][1] === strtotime('2026-11-12 00:00:00 +03:00'));
check('a week starts on Monday', $w['week'][0] === strtotime('2026-11-09 00:00:00 +03:00') && $w['week'][1] === strtotime('2026-11-16 00:00:00 +03:00'));
check('a month is the calendar month', $w['month'][0] === strtotime('2026-11-01 00:00:00 +03:00') && $w['month'][1] === strtotime('2026-12-01 00:00:00 +03:00'));
$lateUtc = strtotime('2026-11-11 21:30:00 UTC');      // 00:30 on the 12th in Nairobi
check('21:30 UTC is already tomorrow in Nairobi', ss3_windows($lateUtc)['day'][0] === strtotime('2026-11-12 00:00:00 +03:00'));
$sun = strtotime('2026-11-15 23:30:00 +03:00');
check('Sunday night still belongs to the old week', ss3_windows($sun)['week'][0] === strtotime('2026-11-09 00:00:00 +03:00'));

echo "\n== Set up\n";
$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["ssq_{$k}_$run", "ssq_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$tutorUser = $mk('tutor', 'tutor'); $ownTutorUser = $mk('owntutor', 'tutor'); $admin = $mk('admin', 'admin'); $me = $mk('me', 'student'); $other = $mk('other', 'student');
foreach ([$tutorUser, $ownTutorUser] as $tu) $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tu]);
$tutorId = (int) $db->query("SELECT id FROM tutors WHERE user_id = $tutorUser")->fetchColumn();
$ownTutorId = (int) $db->query("SELECT id FROM tutors WHERE user_id = $ownTutorUser")->fetchColumn();
$db->prepare('INSERT INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$ownTutorId, $me]);
foreach ([$tutorUser, $ownTutorUser, $admin] as $u) $db->prepare("INSERT INTO notification_preferences (user_id, category, enabled) VALUES (?, 'assignments', 1)")->execute([$u]);   // notifications start switched off
$db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count) VALUES (?, ?, 'd', 'beginner', 'published', 2)")->execute([$tutorId, "Quiz $run"]);
$cid = (int) $db->lastInsertId();
foreach ([$me, $other] as $s) $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by) VALUES (?, ?, ?)')->execute([$cid, $s, $tutorUser]);
$mod = function (string $t, int $pos) use ($db, $cid): int { $db->prepare('INSERT INTO self_study_modules (course_id, title, position) VALUES (?, ?, ?)')->execute([$cid, $t, $pos]); return (int) $db->lastInsertId(); };
$top = function (string $t, int $pos, int $m) use ($db, $cid): int { $db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, is_intro) VALUES (?, ?, ?, ?, '<p>x</p>', 0)")->execute([$cid, $m, $t, $pos]); return (int) $db->lastInsertId(); };
$m1 = $mod('Module 1: A', 1); $m2 = $mod('Module 2: B', 2);
$t1 = $top('Reading CSV files', 1, $m1); $t2 = $top('Cleaning', 2, $m2);
$qn = function (string $place, ?int $module, ?int $topic, string $type, string $text, ?array $opts, ?int $ok, ?array $acc = null) use ($db, $cid): int {
    $db->prepare('INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index, accepted_answers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute([$cid, $place, $topic, $module, $type, $text, $opts ? json_encode($opts) : null, $ok, $acc ? json_encode($acc) : null]);
    return (int) $db->lastInsertId();
};
for ($i = 1; $i <= 10; $i++) $qn('module_quiz', $m1, $t1, 'multiple_choice', "M1 Q$i", ["right$i", "wrong$i", "bad$i", "no$i"], 0);
$qShort = $qn('module_quiz', $m1, $t1, 'short_answer', 'M1 short', null, null, ['dropna']);
$qLong = $qn('module_quiz', $m1, null, 'long_answer', 'M1 long', null, null);          // must never be drawn
$qNoTag = $qn('module_quiz', $m1, null, 'short_answer', 'M1 untagged', null, null, ['yes']);   // module tag only
$m2q = []; for ($i = 1; $i <= 12; $i++) $m2q[] = $qn('module_quiz', $m2, $t2, 'multiple_choice', "M2 Q$i", ["r$i", "w$i"], 0);
$cpq = []; for ($i = 1; $i <= 5; $i++) $cpq[] = $qn('checkpoint_exam', null, null, 'multiple_choice', "CP Q$i", ["r$i", "w$i"], 0);
$fnq = []; for ($i = 1; $i <= 3; $i++) $fnq[] = $qn('final_exam', null, null, 'multiple_choice', "FN Q$i", ["r$i", "w$i"], 0);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 5, final_questions = 3 WHERE id = ?')->execute([$cid]);        // quiz stays at 12
$done = $db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, started_at, last_seen_at, done_at) VALUES (?, ?, ?, ?, ?, ?)');
$ts = ss2_ts($wed - 86400 * 3);
foreach ([$t1, $t2] as $t) $done->execute([$me, $t, $cid, $ts, $ts, $ts]);
$done->execute([$other, $t1, $cid, $ts, $ts, $ts]);
$M = fn(?int $u = null) => ss2_model($db, $cid, $u ?? $me);

echo "\n== Opening rules\n";
check('Module 1 quiz is open (its topics are done)', $M()['modules'][0]['quiz']['open']);
$r = ss3_start($db, $me, $cid, 'module_quiz', $m2, $wed);
check('Module 2 quiz is refused while Module 1 is not passed', !$r['ok'] && $r['code'] === 'locked');
$r = ss3_start($db, $me, $cid, 'checkpoint_exam', null, $wed);
check('the Checkpoint Exam is refused while a module quiz is not passed', !$r['ok'] && $r['code'] === 'locked');
check('the Final Exam is refused too', !ss3_start($db, $me, $cid, 'final_exam', null, $wed)['ok']);

echo "\n== Starting: one attempt, one try\n";
$r = ss3_start($db, $me, $cid, 'module_quiz', $m1, $wed);
check('the quiz starts', $r['ok'] && !$r['resumed'] && $r['attempt']['status'] === 'in_progress' && $r['attempt']['ends_at'] === null);
$a1 = (int) $r['attempt']['id'];
$set = json_decode($r['attempt']['question_ids'], true);
check('12 questions are drawn from the 13 usable ones (the long answer is never drawn)', count($set) === 12 && !in_array($qLong, array_column($set, 'q'), true));
$r2 = ss3_start($db, $me, $cid, 'module_quiz', $m1, $wed + 1);
check('a second click continues the same attempt and adds no try', $r2['ok'] && $r2['resumed'] && (int) $r2['attempt']['id'] === $a1 && (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me")->fetchColumn() === 1);
check('another quiz cannot be started while one is open', ($x = ss3_start($db, $me, $cid, 'checkpoint_exam', null, $wed + 2)) && !$x['ok']);
$view = ss3_attempt_view($db, $me, $a1, $wed + 3);
$json = json_encode($view);
check('the screen gets no correct answers', !str_contains($json, 'correct_index') && !str_contains($json, 'accepted'));
check("another student cannot open this attempt", ss3_attempt_view($db, $other, $a1, $wed) === null);
check('options are all there for a multiple choice question', count(array_filter($view['questions'], fn($q) => $q['type'] === 'multiple_choice' && count($q['options']) === 4)) >= 10);
$orders = array_map(fn($x) => $x['o'], array_filter($set, fn($x) => $x['o']));
check('the answer options are shuffled (not all in the saved order)', count(array_filter($orders, fn($o) => $o !== [0, 1, 2, 3])) > 0);
$tries = ss3_tries($db, $me, $cid, 'module_quiz', $m1, $wed);
check('the try counted the moment it started', $tries['windows']['week']['used'] === 1 && $tries['windows']['week']['left'] === 2);

echo "\n== Answering and submitting\n";
$byId = []; foreach ($view['questions'] as $q) $byId[$q['id']] = $q;
$opt = function (array $q, string $label) { foreach ($q['options'] as [$i, $l]) if ($l === $label) return $i; return -1; };
// answer 5 of the 12 right, leave the rest empty
$right = 0;
foreach ($view['questions'] as $q) {
    if ($right >= 5) break;
    if ($q['type'] === 'multiple_choice') { $n = substr($q['text'], 4); $s = ss3_save_answer($db, $me, $a1, $q['id'], $opt($q, "right$n"), $wed + 10); if ($s['ok']) $right++; }
}
check('answers are saved as the student goes', (int) $db->query("SELECT COUNT(*) FROM self_study_attempt_answers WHERE attempt_id = $a1")->fetchColumn() === 5);
check('a made-up option, or a question that is not in the attempt, is refused', !ss3_save_answer($db, $me, $a1, array_values(array_filter($view['questions'], fn($q) => $q['type'] === 'multiple_choice'))[0]['id'], 99, $wed + 11)['ok'] && !ss3_save_answer($db, $me, $a1, $m2q[0], 0, $wed + 11)['ok']);
check("another student cannot save into my attempt", !ss3_save_answer($db, $other, $a1, $view['questions'][0]['id'], 0, $wed + 11)['ok']);
$sub = ss3_submit($db, $me, $a1, $wed + 60);
$at = $sub['attempt'];
$expectPct = (int) floor($right * 100 / 12);
check('submitting marks it at once; unanswered questions count as wrong', $sub['ok'] && $at['status'] === 'submitted' && (int) $at['correct_count'] === $right && (int) $at['total_count'] === 12 && (int) $at['score_percent'] === $expectPct, json_encode($at));
check('under 50% is not passed', (int) $at['passed'] === 0 && !$M()['modules'][1]['open']);
$again = ss3_submit($db, $me, $a1, $wed + 70);
check('pressing Submit twice changes nothing', $again['ok'] && (int) $again['attempt']['correct_count'] === $right && (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me")->fetchColumn() === 1);
check('a saved answer cannot be changed after submitting', !ss3_save_answer($db, $me, $a1, $view['questions'][0]['id'], 0, $wed + 80)['ok']);

echo "\n== The result screen data\n";
$res = ss3_result($db, $me, $at, $M(), $wed + 90);
check('only the questions that were got wrong are listed', count($res['look']) === 12 - $right && $res['correct'] === $right);
$js = json_encode($res);
check('no correct answer and no given answer is in the result', !str_contains($js, 'correct_index') && !str_contains($js, 'right1') && !str_contains($js, 'accepted'));
$tagged = array_values(array_filter($res['look'], fn($l) => $l['link'] && str_contains($l['link']['label'], 'Reading CSV')));
check('a question tagged with a topic links to that topic', count($tagged) > 0 && $tagged[0]['link']['topic'] === $t1);
$untagged = array_values(array_filter($res['look'], fn($l) => $l['text'] === 'M1 untagged'));
check('a question with no topic tag links to the module (its first topic)', !$untagged || $untagged[0]['link']['topic'] === $t1);
check('the tries line says used, left and when they come back', str_contains($res['tries_text'], 'Try 1 of 3 used this week') && str_contains($res['tries_text'], '2 tries left') && str_contains($res['tries_text'], 'resets on Monday'), $res['tries_text']);
$steps = $res['next'];
check('what happens next: current quiz, locked modules grouped, then the exams locked', $steps[0]['state'] === 'current' && $steps[0]['sub'] === '50% needed' && end($steps)['state'] === 'locked');
check('the rules box: 50%, no timer, 3 a week', $res['rules']['pass'] === 50 && !$res['rules']['timed'] && $res['rules']['limits'] === ['week' => 3]);

echo "\n== 3 tries a week\n";
$tryAll = function (int $time, bool $pass) use ($db, $me, $cid, $m1, $opt) {
    $r = ss3_start($db, $me, $cid, 'module_quiz', $m1, $time); if (!$r['ok']) return $r;
    $v = ss3_attempt_view($db, $me, (int) $r['attempt']['id'], $time);
    foreach ($v['questions'] as $q) { if (!$pass) continue; if ($q['type'] === 'multiple_choice') ss3_save_answer($db, $me, (int) $r['attempt']['id'], $q['id'], $opt($q, 'right' . substr($q['text'], 4)), $time + 5); else ss3_save_answer($db, $me, (int) $r['attempt']['id'], $q['id'], $q['text'] === 'M1 short' ? 'DROPNA.' : 'yes', $time + 5); }
    return ss3_submit($db, $me, (int) $r['attempt']['id'], $time + 30) + ['started' => $r];
};
$f2 = $tryAll($wed + 3600, false);
check('second try used', $f2['ok'] && (int) $f2['attempt']['score_percent'] === 0);
$f3 = $tryAll($wed + 7200, false);
$blocked = ss3_start($db, $me, $cid, 'module_quiz', $m1, $wed + 9000);
check('the fourth try in the same week is refused with the time it comes back (Monday)', !$blocked['ok'] && $blocked['code'] === 'tries' && $blocked['next_at'] === strtotime('2026-11-16 00:00:00 +03:00'));
check('the count survives: 3 attempts, no extra try', (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me AND kind = 'module_quiz'")->fetchColumn() === 3);
$nextWeek = ss3_start($db, $me, $cid, 'module_quiz', $m1, strtotime('2026-11-16 00:05:00 +03:00'));
check('on Monday the tries are back', $nextWeek['ok']);
ss3_submit($db, $me, (int) $nextWeek['attempt']['id'], strtotime('2026-11-16 00:06:00 +03:00'));      // a blank attempt (a failed try)

echo "\n== Asking for a reset, and granting it\n";
$req = ss3_request_reset($db, $me, $cid, 'module_quiz', $m1, '  I was on a bad connection   and it dropped  ', $wed + 9600);
check('a waiting request is saved with the reason', $req['ok'] && $db->query("SELECT status FROM self_study_reset_requests WHERE id = {$req['id']}")->fetchColumn() === 'waiting');
check('an empty reason is refused', !ss3_request_reset($db, $me, $cid, 'module_quiz', $m1, '   ', $wed + 9600)['ok']);
check('a second request while one waits is refused', !ss3_request_reset($db, $me, $cid, 'module_quiz', $m1, 'again', $wed + 9601)['ok']);
$long = ss3_request_reset($db, $other, $cid, 'module_quiz', $m1, str_repeat('x', 300), $wed);
check('a very long reason is cut to 200 letters', !$long['ok'] || mb_strlen((string) $db->query("SELECT reason FROM self_study_reset_requests WHERE id = {$long['id']}")->fetchColumn()) <= 200);
$note = fn(int $u) => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $u AND title LIKE '%asked for a reset%'")->fetchColumn();
check('the course tutor, the student\'s own tutor and the admin were notified', $note($tutorUser) >= 1 && $note($ownTutorUser) >= 1 && $note($admin) >= 1, "t={$note($tutorUser)} own={$note($ownTutorUser)} admin={$note($admin)}");
check('the student is still blocked until the tutor acts', ss3_tries($db, $me, $cid, 'module_quiz', $m1, $wed + 9700)['blocked']);
$before = (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me AND tries_cleared_at IS NULL")->fetchColumn();
$g = ss3_grant_reset($db, $me, $cid, 'module_quiz', $m1, $tutorUser, 'Connection dropped', $req['id'], $wed + 9800);
check('a granted reset clears this week\'s tries only', $g['ok'] && $g['window'] === 'week' && $g['cleared'] === 3, json_encode($g));
check('the attempts themselves are kept', (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me")->fetchColumn() === 4);
check('last week\'s try was not touched', (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $me AND tries_cleared_at IS NULL")->fetchColumn() === 1);
check('the reset is recorded with who and why', (string) $db->query("SELECT reason FROM self_study_resets WHERE student_id = $me")->fetchColumn() === 'Connection dropped' && (int) $db->query("SELECT reset_by FROM self_study_resets WHERE student_id = $me")->fetchColumn() === $tutorUser);
check('the request is marked granted', $db->query("SELECT status FROM self_study_reset_requests WHERE id = {$req['id']}")->fetchColumn() === 'granted');
check('the student has 3 tries again', ss3_tries($db, $me, $cid, 'module_quiz', $m1, $wed + 9900)['windows']['week']['left'] === 3);
$dec = ss3_request_reset($db, $me, $cid, 'module_quiz', $m1, 'one more', $wed + 9950);
check('a declined request can be recorded', $dec['ok'] && ss3_decline_request($db, $dec['id'], $tutorUser, $wed + 9960) && $db->query("SELECT status FROM self_study_reset_requests WHERE id = {$dec['id']}")->fetchColumn() === 'declined');

echo "\n== Passing a module quiz\n";
$p = $tryAll($wed + 10000, true);
check('all answers right passes (the short answer ignores capitals and full stops)', $p['ok'] && (int) $p['attempt']['passed'] === 1 && (int) $p['attempt']['score_percent'] === 100, json_encode($p['attempt'] ?? $p));
$m = $M();
check('passing opens the next module and moves the bars', $m['modules'][0]['quiz']['passed'] && $m['modules'][1]['open']);
$pr = $db->query("SELECT percent_complete, last_studied_at FROM self_study_progress WHERE course_id = $cid AND student_id = $me")->fetch(PDO::FETCH_ASSOC);
check('the saved progress was updated', $pr && (int) $pr['percent_complete'] === $m['percent'] && $m['percent'] > 0);
check('once passed, a new try cannot be started (and it stays passed)', ($x = ss3_start($db, $me, $cid, 'module_quiz', $m1, $wed + 10100)) && !$x['ok'] && $x['code'] === 'passed');
$db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, is_intro) VALUES (?, ?, 'Late topic', 9, '<p>x</p>', 0)")->execute([$cid, $m1]);
check('a topic the tutor adds later does not un-pass the quiz', $M()['modules'][0]['quiz']['passed'] && $M()['modules'][1]['open']);

echo "\n== Module 2 then the Checkpoint\n";
$s2 = ss3_start($db, $me, $cid, 'module_quiz', $m2, $wed + 11000);
check('Module 2 quiz is open now', $s2['ok']);
$v2 = ss3_attempt_view($db, $me, (int) $s2['attempt']['id'], $wed + 11000);
foreach ($v2['questions'] as $q) ss3_save_answer($db, $me, (int) $s2['attempt']['id'], $q['id'], $opt($q, 'r' . substr($q['text'], 4)), $wed + 11010);
ss3_submit($db, $me, (int) $s2['attempt']['id'], $wed + 11020);
check('with every module quiz passed the Checkpoint opens, the Final does not', $M()['checkpoint']['open'] && !$M()['final']['open']);

echo "\n== The Checkpoint Exam: timer kept by the server\n";
$c = ss3_start($db, $me, $cid, 'checkpoint_exam', null, $wed + 12000);
$cid1 = (int) $c['attempt']['id'];
check('it starts with an end time 45 minutes later and all 5 questions', $c['ok'] && ss2_parse($c['attempt']['ends_at']) === $wed + 12000 + 45 * 60 && count(json_decode($c['attempt']['question_ids'], true)) === 5);
$vc = ss3_attempt_view($db, $me, $cid1, $wed + 12600);
check('the seconds left come from the server clock', $vc['seconds_left'] === 45 * 60 - 600);
foreach (array_slice($vc['questions'], 0, 4) as $q) ss3_save_answer($db, $me, $cid1, $q['id'], $opt($q, 'r' . substr($q['text'], 4)), $wed + 12700);      // 4 of 5 answered, all right
$reopen = ss3_start($db, $me, $cid, 'checkpoint_exam', null, $wed + 13000);
check('reopening continues the same attempt and the same questions, no new try', $reopen['ok'] && $reopen['resumed'] && (int) $reopen['attempt']['id'] === $cid1);
check('an answer after the time is up is refused and the attempt is submitted for the student', ($late = ss3_save_answer($db, $me, $cid1, $vc['questions'][4]['id'], 0, $wed + 12000 + 46 * 60)) && !$late['ok'] && !empty($late['done']));
$ca = $db->query("SELECT * FROM self_study_attempts WHERE id = $cid1")->fetch(PDO::FETCH_ASSOC);
check('it was submitted as timed out with the answers saved so far (4 of 5 = 80%) and passed', $ca['status'] === 'timed_out' && (int) $ca['correct_count'] === 4 && (int) $ca['score_percent'] === 80 && (int) $ca['passed'] === 1, json_encode($ca));
check('the Final Exam is open after the Checkpoint is passed', $M()['final']['open'] && $M()['checkpoint']['passed']);

echo "\n== An attempt left open is closed by the clock, even if the page is closed\n";
$c2 = ss3_start($db, $me, $cid, 'final_exam', null, $wed + 20000);
check('the Final Exam starts with a 60 minute limit', $c2['ok'] && ss2_parse($c2['attempt']['ends_at']) === $wed + 20000 + 3600);
$fid = (int) $c2['attempt']['id'];
$vf = ss3_attempt_view($db, $me, $fid, $wed + 20000);
foreach ($vf['questions'] as $i => $q) if ($i < 2) ss3_save_answer($db, $me, $fid, $q['id'], $opt($q, 'r' . substr($q['text'], 4)), $wed + 20100);   // 2 of 3 right, then the page is closed
check('the cron tool path submits it later', ss3_expire_due($db, $wed + 20000 + 3600 + 5) === 1);
$fa = $db->query("SELECT * FROM self_study_attempts WHERE id = $fid")->fetch(PDO::FETCH_ASSOC);
check('2 of 3 = 66% is under 70%: timed out and not passed', $fa['status'] === 'timed_out' && (int) $fa['score_percent'] === 66 && (int) $fa['passed'] === 0, json_encode($fa));
check('the course is not completed yet', !$db->query("SELECT completed_at FROM self_study_progress WHERE course_id = $cid AND student_id = $me")->fetchColumn());

echo "\n== 2 a day and 10 a month for an exam\n";
$f2 = ss3_start($db, $me, $cid, 'final_exam', null, $wed + 30000);
check('a second Final Exam try today is allowed', $f2['ok']);
ss3_submit($db, $me, (int) $f2['attempt']['id'], $wed + 30010);
$b = ss3_start($db, $me, $cid, 'final_exam', null, $wed + 31000);
check('the third today is refused until Nairobi midnight', !$b['ok'] && $b['code'] === 'tries' && $b['next_at'] === strtotime('2026-11-12 00:00:00 +03:00'));
check('the Checkpoint is counted separately (it is already passed)', ss3_tries($db, $me, $cid, 'checkpoint_exam', null, $wed + 31000)['windows']['day']['used'] === 1);
$ins = $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, started_at, submitted_at, status, score_percent, passed, question_ids) VALUES (?, ?, 'final_exam', ?, ?, 'submitted', 0, 0, '[]')");
foreach ([2, 3, 4, 5, 6, 7, 8, 9] as $d) { $t = strtotime("2026-11-0$d 12:00:00 +03:00"); $ins->execute([$me, $cid, ss2_ts($t), ss2_ts($t + 60)]); }
$next = ss3_start($db, $me, $cid, 'final_exam', null, strtotime('2026-11-12 09:00:00 +03:00'));
check('the 10 tries of the month (2 on the 11th + 8 earlier) block even on a new day', !$next['ok'] && $next['code'] === 'tries' && $next['next_at'] === strtotime('2026-12-01 00:00:00 +03:00'), json_encode($next));
$g2 = ss3_grant_reset($db, $me, $cid, 'final_exam', null, $admin, 'Power cut', null, strtotime('2026-11-12 09:30:00 +03:00'));
check('a reset when the month is used up clears the month\'s tries', $g2['ok'] && $g2['window'] === 'month' && $g2['cleared'] >= 9, json_encode($g2));
$next = ss3_start($db, $me, $cid, 'final_exam', null, strtotime('2026-11-12 09:40:00 +03:00'));
check('then the exam can be taken again', $next['ok']);

echo "\n== Passing the Final Exam completes the course\n";
$vn = ss3_attempt_view($db, $me, (int) $next['attempt']['id'], strtotime('2026-11-12 09:41:00 +03:00'));
foreach ($vn['questions'] as $q) ss3_save_answer($db, $me, (int) $next['attempt']['id'], $q['id'], $opt($q, 'r' . substr($q['text'], 4)), strtotime('2026-11-12 09:42:00 +03:00'));
$fin = ss3_submit($db, $me, (int) $next['attempt']['id'], strtotime('2026-11-12 09:50:00 +03:00'));
check('100% passes the Final Exam', $fin['ok'] && (int) $fin['attempt']['passed'] === 1);
$pg = $db->query("SELECT percent_complete, completed_at FROM self_study_progress WHERE course_id = $cid AND student_id = $me")->fetch(PDO::FETCH_ASSOC);
check('the completed date is set in the progress table, so the card shows Completed', (int) $pg['percent_complete'] === 100 && $pg['completed_at']);
$card = ss_courses_for_student($db, $me)[0];
check('the Self-study card says completed', $card['state'] === 'completed');
check('the tutor was notified', (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $tutorUser AND title LIKE '%passed the Final Exam%'")->fetchColumn() === 1);
check('the other student is unaffected', !$db->query("SELECT 1 FROM self_study_progress WHERE course_id = $cid AND student_id = $other AND completed_at IS NOT NULL")->fetchColumn());

echo "\n== A quiz with no questions at all never counts as passed\n";
$db->prepare("INSERT INTO self_study_modules (course_id, title, position) VALUES (?, 'Empty', 3)")->execute([$cid]); $emptyId = (int) $db->lastInsertId();
$ro = ss2_model($db, $cid, $me)['modules'][2];
check('it is open to look at but "being updated", not passed', $ro['quiz']['open'] && $ro['quiz']['updating'] && !$ro['quiz']['passed'] && $ro['quiz']['questions'] === 0);
$rz = ss3_start($db, $me, $cid, 'module_quiz', $emptyId, $wed + 700000);
check('it cannot be started', !$rz['ok'] && $rz['code'] === 'updating' && $rz['error'] === 'This quiz is being updated. Please check again soon.');
$db->exec("DELETE FROM self_study_modules WHERE id = $emptyId");

echo "\n== A quiz or exam that is short of questions (the tutor deleted some after publishing)\n";
$third = $mk('third', 'student');
$m1ids = array_map('intval', $db->query("SELECT id FROM self_study_questions WHERE course_id = $cid AND place = 'module_quiz' AND module_id = $m1 AND qtype IN ('multiple_choice', 'short_answer') ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN));
$db->exec("UPDATE self_study_questions SET archived_at = '" . ss2_ts($wed) . "' WHERE id IN (" . implode(',', $m1ids) . ')');      // 12 -> 7 usable, 12 needed
foreach (ss2_model($db, $cid, $other)['modules'][0]['topics'] as $tt) if (!$tt['done']) $db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) VALUES (?, ?, ?, ?)')->execute([$other, $tt['id'], $cid, ss2_ts($wed)]);
$mo = ss2_model($db, $cid, $other)['modules'];
check('the quiz is "being updated": open to look at, but not passed, and the next module stays locked', $mo[0]['quiz']['updating'] === true && !$mo[0]['quiz']['passed'] && !$mo[1]['open'] && $mo[0]['quiz']['need'] === 12 && $mo[0]['quiz']['questions'] === 7, json_encode($mo[0]['quiz']) . ' topics ' . json_encode(''));
$rs = ss3_start($db, $other, $cid, 'module_quiz', $m1, $wed + 900000);
check('the student cannot start it and is told so in plain words', !$rs['ok'] && $rs['code'] === 'updating' && $rs['error'] === 'This quiz is being updated. Please check again soon.', json_encode($rs));
check('no attempt and no try was created by the refusal', (int) $db->query("SELECT COUNT(*) FROM self_study_attempts WHERE student_id = $other AND kind = 'module_quiz' AND module_id = $m1 AND started_at >= '" . ss2_ts($wed + 800000) . "'")->fetchColumn() === 0);
$mm = ss2_model($db, $cid, $me)['modules'];
check('a student who already passed it keeps the pass, and the next module stays open for them', $mm[0]['quiz']['passed'] && !$mm[0]['quiz']['updating'] && $mm[1]['open']);
$warn = sst_bank_warnings($db, $cid, '/tutor/selfstudy/');
check('the tutor sees: "this quiz needs 12 questions and has 8"', str_contains($warn, 'needs 12 questions and has 7') && str_contains($warn, 'bank=module:' . $m1), $warn);
$db->prepare("UPDATE self_study_courses SET status = 'draft' WHERE id = ?")->execute([$cid]);
check('the warning is only for a published course (a draft is covered by the Publish checklist)', sst_bank_warnings($db, $cid, '/x/') === '');
$db->prepare("UPDATE self_study_courses SET status = 'published' WHERE id = ?")->execute([$cid]);
$db->exec("UPDATE self_study_questions SET archived_at = NULL WHERE id IN (" . implode(',', $m1ids) . ')');
$mo = ss2_model($db, $cid, $other)['modules'];
check('once the bank is full again the quiz can be taken', !$mo[0]['quiz']['updating'] && ss3_start($db, $other, $cid, 'module_quiz', $m1, $wed + 950000)['ok']);
check('and the warning is gone', sst_bank_warnings($db, $cid, '/x/') === '');
// the same for an exam: a student with every module passed
$db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) VALUES (?, ?, ?, ?)')->execute([$third, $t1, $cid, ss2_ts($wed)]);
$db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) VALUES (?, ?, ?, ?)')->execute([$third, $t2, $cid, ss2_ts($wed)]);
foreach ([$m1, $m2] as $mm1) $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'module_quiz', ?, ?, ?, 100, 1, 'submitted', '[]')")->execute([$third, $cid, $mm1, ss2_ts($wed), ss2_ts($wed + 300)]);
check('with every module passed the Checkpoint is open', ss2_model($db, $cid, $third)['checkpoint']['open'] && !ss2_model($db, $cid, $third)['checkpoint']['updating']);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 9 WHERE id = ?')->execute([$cid]);          // the bank has 5, the exam now needs 9
$mx = ss2_model($db, $cid, $third);
check('the Checkpoint Exam is "being updated": it cannot be started and the Final Exam stays locked', $mx['checkpoint']['updating'] === true && !$mx['checkpoint']['passed'] && !$mx['final']['open']);
$re = ss3_start($db, $third, $cid, 'checkpoint_exam', null, $wed + 960000);
check('the student is told "This exam is being updated. Please check again soon."', !$re['ok'] && $re['code'] === 'updating' && $re['error'] === 'This exam is being updated. Please check again soon.');
check('the tutor sees the exam warning too', str_contains(sst_bank_warnings($db, $cid, '/x/'), 'Checkpoint Exam') && str_contains(sst_bank_warnings($db, $cid, '/x/'), 'needs 9 questions and has 5'));
$db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'checkpoint_exam', NULL, ?, ?, 90, 1, 'submitted', '[]')")->execute([$third, $cid, ss2_ts($wed), ss2_ts($wed + 300)]);
$mx = ss2_model($db, $cid, $third);
check('an exam already passed stays passed (and the Final Exam stays open) even though the bank is short', $mx['checkpoint']['passed'] && !$mx['checkpoint']['updating'] && $mx['final']['open']);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 5 WHERE id = ?')->execute([$cid]);

echo "\n== Clean up\n";
$ids = "$cid";
foreach (['self_study_attempt_answers' => 'attempt_id IN (SELECT id FROM self_study_attempts WHERE course_id = ' . $cid . ')', 'self_study_attempts' => "course_id = $cid", 'self_study_reset_requests' => "course_id = $cid", 'self_study_resets' => "course_id = $cid",
         'self_study_answers' => "course_id = $cid", 'self_study_topic_progress' => "course_id = $cid", 'self_study_progress' => "course_id = $cid", 'self_study_access' => "course_id = $cid", 'self_study_questions' => "course_id = $cid",
         'self_study_topics' => "course_id = $cid", 'self_study_modules' => "course_id = $cid", 'self_study_courses' => "id = $cid"] as $t => $where) $db->exec("DELETE FROM $t WHERE $where");
$all = implode(',', [$tutorUser, $ownTutorUser, $admin, $me, $other, $third]);
$db->exec("DELETE FROM portal_notifications WHERE user_id IN ($all)");
$db->exec("DELETE FROM notification_preferences WHERE user_id IN ($all)");
$db->exec("DELETE FROM tutor_students WHERE user_id = $me");
$db->exec("DELETE FROM tutors WHERE user_id IN ($tutorUser, $ownTutorUser)");
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($all)");
$db->exec("DELETE FROM users WHERE id IN ($all)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
