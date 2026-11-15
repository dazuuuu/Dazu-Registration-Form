<?php
// Self-study (step 2): the study screen rules — cleaning, YouTube, strict order, quizzes read from saved attempts, the video tracker, topic questions, Knowledge Check, files.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_study_test.php     (a scratch database with every migration applied, 105 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyStudy.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
if (!ss2_installed($db)) { fwrite(STDERR, "Apply migration 105 first.\n"); exit(2); }

echo "== Cleaning tutor text\n";
$dirty = '<h1>Big</h1><p onclick="x()" style="color:red">Hi <b>there</b> <i>you</i><script>alert(1)</script></p><a href="javascript:alert(1)">bad</a><a href=" jav&#x09;ascript:alert(1)">bad2</a>'
       . '<a href="https://example.com/a">ok</a><img src="x" onerror="alert(1)"><img src="https://example.com/p.png" alt="pic"><img src="data:image/png;base64,AAAA"><iframe src="https://evil"></iframe>'
       . '<table><tr><td colspan="2">c</td></tr></table><pre><code>df = 1</code></pre><ul><li>one</li></ul><form action="/x"><input name="a"></form><svg onload="x()"></svg><!-- hide --><style>p{}</style><blockquote>q</blockquote>';
$c = ss_clean_html($dirty);
check('script, iframe, form, svg, style and comments are gone', !preg_match('/<script|<iframe|<form|<input|<svg|<style|<!--|alert\(1\)<\/|evil/i', $c), $c);
check('event handlers and style attributes are removed', !preg_match('/onclick|onerror|onload|style=/i', $c), $c);
check('javascript: links lose their href (even with hidden characters)', !preg_match('/href="\s*jav|href="javascript/i', $c), $c);
check('an https link is kept and opens safely', str_contains($c, 'href="https://example.com/a"') && str_contains($c, 'noopener'));
check('an image needs an https address: data: and a bare "x" are dropped', str_contains($c, 'src="https://example.com/p.png"') && !str_contains($c, 'data:image') && substr_count($c, '<img') === 1, $c);
check('headings, bold, italic, lists, tables, code and quotes survive', str_contains($c, '<h2>Big</h2>') && str_contains($c, '<b>there</b>') && str_contains($c, '<i>you</i>') && str_contains($c, '<li>one</li>') && str_contains($c, '<td colspan="2">') && str_contains($c, '<pre><code>df = 1</code></pre>') && str_contains($c, '<blockquote>q</blockquote>'));
check('an empty or null text gives an empty string', ss_clean_html(null) === '' && ss_clean_html('  ') === '');
check('a plain own upload path is allowed for images', str_contains(ss_clean_html('<img src="uploads/selfstudy/1/a.png">'), 'uploads/selfstudy/1/a.png') && !str_contains(ss_clean_html('<img src="uploads/../../etc/passwd">'), 'passwd'));

echo "\n== Only YouTube\n";
$id = 'dQw4w9WgXcQ';
check('watch, youtu.be, embed, shorts and a bare id are accepted', ss_youtube_id("https://www.youtube.com/watch?v=$id&t=5") === $id && ss_youtube_id("https://youtu.be/$id") === $id && ss_youtube_id("https://www.youtube-nocookie.com/embed/$id") === $id && ss_youtube_id("https://m.youtube.com/shorts/$id") === $id && ss_youtube_id($id) === $id);
check('other sites, other schemes and odd ids are refused', ss_youtube_id("https://vimeo.com/123456789") === null && ss_youtube_id("https://evil.com/watch?v=$id") === null && ss_youtube_id("javascript:alert(1)") === null && ss_youtube_id("https://youtube.com.evil.com/watch?v=$id") === null && ss_youtube_id('short') === null && ss_youtube_id("https://www.youtube.com/watch?v=$id\"onload=") === null);
check('the embed uses the privacy-friendly address', str_starts_with(ss_youtube_embed_url($id), 'https://www.youtube-nocookie.com/embed/' . $id));

echo "\n== Set up a course\n";
$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sss_{$k}_$run", "sss_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$tutorUser = $mk('tutor', 'tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutorUser]);
$tutorId = (int) $db->lastInsertId();
$me = $mk('me', 'student'); $other = $mk('other', 'student'); $outsider = $mk('out', 'student');
$db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count) VALUES (?, ?, 'd', 'beginner', 'published', 2)")->execute([$tutorId, "Study $run"]);
$cid = (int) $db->lastInsertId();
$db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status) VALUES (?, ?, 'd', 'beginner', 'draft')")->execute([$tutorId, "Draft $run"]);
$draft = (int) $db->lastInsertId();
foreach ([$me, $other] as $s) $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by) VALUES (?, ?, ?)')->execute([$cid, $s, $tutorUser]);
$db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by) VALUES (?, ?, ?)')->execute([$draft, $me, $tutorUser]);

$mod = function (string $t, int $pos, int $pass = 50) use ($db, $cid): int { $db->prepare('INSERT INTO self_study_modules (course_id, title, position, pass_mark) VALUES (?, ?, ?, ?)')->execute([$cid, $t, $pos, $pass]); return (int) $db->lastInsertId(); };
$top = function (string $t, int $pos, ?int $m, bool $intro = false, ?string $vid = null, ?int $dur = null, string $html = '<p>x</p>') use ($db, $cid): int {
    $db->prepare('INSERT INTO self_study_topics (course_id, module_id, title, position, content_html, video_id, video_duration, is_intro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$cid, $m, $t, $pos, $html, $vid, $dur, $intro ? 1 : 0]);
    return (int) $db->lastInsertId();
};
$qn = function (string $place, ?int $topic, ?int $module, string $type, string $text, ?array $opts, ?int $ok, ?array $acc = null) use ($db, $cid): int {
    $db->prepare('INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index, accepted_answers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute([$cid, $place, $topic, $module, $type, $text, $opts ? json_encode($opts) : null, $ok, $acc ? json_encode($acc) : null]);
    return (int) $db->lastInsertId();
};
$m1 = $mod('Module 1: Start', 1); $m2 = $mod('Module 2: Data', 2, 50); $m3 = $mod('Module 3: Charts', 3);
$tIntro = $top('Course Introduction', 0, null, true);
$t10 = $top('Intro', 1, $m1); $t11 = $top('Reading', 2, $m1);
$t20 = $top('Intro', 3, $m2); $t21 = $top('Cleaning', 4, $m2, false, $id, 100, '<p>Hello</p><script>alert(1)</script>');
$t30 = $top('Charts', 5, $m3);
// 8 questions in the bank of $t21 (six are drawn), a quiz for module 1 (none for module 2 on purpose: lenient rule), and exams
$bank = [];
for ($i = 1; $i <= 8; $i++) $bank[] = $qn('topic', $t21, null, 'multiple_choice', "Q$i", ["a$i", "b$i", "c$i", "d$i"], 1);
$qShort = $qn('topic', $t11, null, 'short_answer', 'Name the method', null, null, ['dropna', 'df.dropna()']);
$qLong = $qn('topic', $t11, null, 'long_answer', 'Explain', null, null);
$qMc11 = $qn('topic', $t11, null, 'multiple_choice', 'Pick', ['no', 'yes'], 1);
$q1a = $qn('module_quiz', null, $m1, 'multiple_choice', 'M1 quiz 1', ['x', 'y'], 0);
$q1b = $qn('module_quiz', null, $m1, 'multiple_choice', 'M1 quiz 2', ['x', 'y'], 0);
$kb1 = $qn('knowledge_check', null, null, 'multiple_choice', 'KB1', ['p', 'q', 'r'], 2);
$kb2 = $qn('knowledge_check', null, null, 'multiple_choice', 'KB2', ['p', 'q', 'r'], 0);
$cp1 = $qn('checkpoint_exam', null, null, 'multiple_choice', 'CP', ['x', 'y'], 0);
// every quiz and exam has as many questions as one attempt needs (a short bank is "being updated" and can never be passed)
$db->prepare('UPDATE self_study_courses SET quiz_questions = 2, checkpoint_questions = 1, final_questions = 1 WHERE id = ?')->execute([$cid]);
foreach ([$m2, $m3] as $mq) for ($i = 1; $i <= 2; $i++) $qn('module_quiz', null, $mq, 'multiple_choice', "Quiz $mq-$i", ['x', 'y'], 0);
$qn('final_exam', null, null, 'multiple_choice', 'FN', ['x', 'y'], 0);
$passAtt = fn(string $kind, ?int $module) => $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, ?, ?, ?, ?, 100, 1, 'submitted', '[]')")->execute([$me, $cid, $kind, $module, gmdate('Y-m-d H:i:s', 1_700_000_000), gmdate('Y-m-d H:i:s', 1_700_000_300)]);
$db->prepare('INSERT INTO self_study_course_rules (course_id, checkpoint_pass, final_pass) VALUES (?, 70, 70)')->execute([$cid]);
$t0 = 1_700_000_000;

$M = fn() => ss2_model($db, $cid, $me);
$open = fn(array $m, int $t) => $m['topics'][$t]['open'];

echo "\n== Strict order\n";
$m = $M();
check('the Course Introduction is open, nothing after it is', $open($m, $tIntro) && !$open($m, $t10) && !$open($m, $t11) && !$open($m, $t20));
check('the locked topic says why', $m['topics'][$t10]['reason'] !== '');
check('Module 2 is locked with the quiz reason, Module 1 only needs the Introduction', !$m['modules'][1]['open'] && $m['modules'][1]['reason'] === 'Pass the Module 1 quiz (50%) to open this module.' && $m['modules'][0]['reason'] === 'Finish the Course Introduction to open this module.');
check('the quiz, Checkpoint and Final are locked', !$m['modules'][0]['quiz']['open'] && !$m['checkpoint']['open'] && !$m['final']['open']);
check('the student is sent to the Introduction (the topic they are on)', $m['resume'] === $tIntro);
$r = ss2_mark_done($db, $me, $cid, $t10, $t0);
check('finishing a locked topic is refused on the server', !$r['ok'] && !empty($r['locked']));
$r = ss2_submit_topic($db, $me, $cid, $t11, [], $t0);
check('submitting a locked topic is refused on the server', !$r['ok'] && !empty($r['locked']));
check('the video topic is locked too (progress.php refuses video reports for a locked topic)', !$M()['topics'][$t21]['open']);

echo "\n== A topic without questions\n";
$r = ss2_mark_done($db, $me, $cid, $tIntro, $t0);
check('the Introduction is finished with one button and opens Module 1', $r['ok'] && $r['next'] === $t10 && $open($M(), $t10));
$r = ss2_mark_done($db, $me, $cid, $t10, $t0 + 10);
check('Module 1 topic 1 done opens topic 2', $r['ok'] && $r['next'] === $t11);
check('a topic that has questions cannot be skipped with the one button', !ss2_mark_done($db, $me, $cid, $t11, $t0 + 11)['ok']);

echo "\n== Topic questions: six drawn, shuffled, saved, no pass mark\n";
$qs = ss2_topic_questions($db, $me, $cid, $t11, $t0);
check('a bank of 3 gives all 3', count($qs) === 3);
$json = json_encode($qs);
check('the correct answers are not in what the screen receives', !str_contains($json, 'correct') && !str_contains($json, 'accepted'));
$again = ss2_topic_questions($db, $me, $cid, $t11, $t0 + 5);
check('the same questions and the same order come back after a reload', $json === json_encode($again));
$byType = []; foreach ($qs as $q) $byType[$q['type']] = $q;
$mcq = $byType['multiple_choice'];
$opt = function (array $q, string $label) { foreach ($q['options'] as [$i, $l]) if ($l === $label) return $i; return -1; };
$ans = [(string) $byType['short_answer']['id'] => '  DF.dropna()  .', (string) $byType['long_answer']['id'] => 'Because empty cells change averages.', (string) $mcq['id'] => (string) $opt($mcq, 'yes')];
check('an unanswered question is refused', !ss2_submit_topic($db, $me, $cid, $t11, array_slice($ans, 0, 2, true), $t0 + 20)['ok']);
check('a made-up option number is refused', !ss2_submit_topic($db, $me, $cid, $t11, [(string) $mcq['id'] => '99'] + $ans, $t0 + 20)['ok']);
$r = ss2_submit_topic($db, $me, $cid, $t11, $ans, $t0 + 30);
check('submitting marks, finishes the topic and opens the next one', $r['ok'] && $r['done'] && $db->query("SELECT done_at FROM self_study_topic_progress WHERE student_id = $me AND topic_id = $t11")->fetchColumn());
$tp = $db->query("SELECT score_correct, score_total FROM self_study_topic_progress WHERE student_id = $me AND topic_id = $t11")->fetch(PDO::FETCH_ASSOC);
check('short answer ignores capitals, spaces and full stops; the long answer is left for the tutor (2 of 2 marked)', (int) $tp['score_correct'] === 2 && (int) $tp['score_total'] === 2, json_encode($tp));
check('the long answer is saved with no mark', (string) $db->query("SELECT answer FROM self_study_answers WHERE student_id = $me AND question_id = $qLong")->fetchColumn() === 'Because empty cells change averages.' && $db->query("SELECT is_correct FROM self_study_answers WHERE student_id = $me AND question_id = $qLong")->fetchColumn() === null);
$rows = (int) $db->query("SELECT COUNT(*) FROM self_study_answers WHERE student_id = $me AND course_id = $cid")->fetchColumn();
$r2 = ss2_submit_topic($db, $me, $cid, $t11, $ans, $t0 + 40);
check('submitting a finished topic again changes nothing', $r2['ok'] && (int) $db->query("SELECT COUNT(*) FROM self_study_answers WHERE student_id = $me AND course_id = $cid")->fetchColumn() === $rows);
$ro = ss2_topic_answers($db, $me, $cid, ss2_topic_questions($db, $me, $cid, $t11, $t0 + 50));
check('the finished topic shows what the student wrote (read only), not the key', in_array('yes', $ro, true) && in_array('Because empty cells change averages.', $ro, true));

echo "\n== Module quiz is read from saved attempts\n";
$m = $M();
check('after all Module 1 topics the quiz opens but Module 2 stays locked', $m['modules'][0]['quiz']['open'] && !$m['modules'][0]['quiz']['passed'] && !$m['modules'][1]['open'] && !$open($m, $t20));
$ins = $db->prepare('INSERT INTO self_study_answers (student_id, question_id, course_id, answer, is_correct, attempt) VALUES (?, ?, ?, ?, ?, ?)');
$ins->execute([$me, $q1a, $cid, '1', 0, 1]); $ins->execute([$me, $q1b, $cid, '1', 0, 1]);
check('a failed attempt (0%) keeps Module 2 locked', !$M()['modules'][1]['open'] && $M()['modules'][0]['quiz']['best'] === 0);
$ins->execute([$me, $q1a, $cid, '0', 1, 2]); $ins->execute([$me, $q1b, $cid, '1', 0, 2]);
check('50% on a retry passes (the pass mark is 50) and opens Module 2', $M()['modules'][0]['quiz']['passed'] && $M()['modules'][1]['open'] && $open($M(), $t20));
check("another student's attempts do not count for me", !ss2_model($db, $cid, $other)['modules'][0]['quiz']['passed']);

echo "\n== Video tracker\n";
ss2_mark_done($db, $me, $cid, $t20, $t0 + 100);
check('the video topic opens after the one before it', $open($M(), $t21));
$v = ss2_video_tick($db, $me, $cid, $t21, 100, 0, 100, $t0 + 200);
check('first report: nothing watched yet', $v['watched'] === 0 && !$v['completed']);
$v = ss2_video_tick($db, $me, $cid, $t21, 100, 90, 100, $t0 + 205);
check('claiming 90 seconds after 5 real seconds is cut back to what time allows', $v['furthest'] <= 8 && $v['watched'] <= 8, json_encode($v));
$v = ss2_video_tick($db, $me, $cid, $t21, 100, 99, 100, $t0 + 206);
check('a second fake report one second later gets almost nothing', $v['furthest'] <= 10, json_encode($v));
$t = $t0 + 206; $pos = $v['furthest'];
while ($pos < 60) { $t += 5; $pos += 5; $v = ss2_video_tick($db, $me, $cid, $t21, 100, $pos, 100, $t); }
check('honest playing at normal speed is credited fully', $v['furthest'] >= 55 && $v['watched'] >= 55 && !$v['completed'], json_encode($v));
$before = $v['watched'];
$v = ss2_video_tick($db, $me, $cid, $t21, 100, 20, 100, $t + 5);
check('going back and replaying counts nothing twice', $v['watched'] === $before);
check('the question submit is refused until the video is watched, with the percent', ($r = ss2_submit_topic($db, $me, $cid, $t21, [], $t + 6)) && !$r['ok'] && str_contains($r['error'], 'Watched') && str_contains($r['error'], 'Watch to the end'), json_encode($r));
$t += 60; $v = ss2_video_tick($db, $me, $cid, $t21, 100, 80, 100, $t);
check('one long silence cannot be used to jump (a gap is capped at 15 seconds of credit)', $v['furthest'] <= 80 && $v['furthest'] < 100);
while ($v['furthest'] < 95) { $t += 5; $v = ss2_video_tick($db, $me, $cid, $t21, 100, $v['furthest'] + 5, 100, $t); }
check('95% played with no forward jump counts as watched', $v['completed'] && (int) $db->query("SELECT video_watched FROM self_study_topic_progress WHERE student_id = $me AND topic_id = $t21")->fetchColumn() === 1, json_encode($v));
check('the resume point is the furthest second reached', ss2_video_state($db, $me, $t21, 100)['furthest'] === $v['furthest']);
$c = ss2_topic_content($db, $cid, $t21);
check('the topic text is cleaned and the video id is verified', !str_contains($c['html'], 'script') && str_contains($c['html'], 'Hello') && $c['video_id'] === $id);
$db->prepare('UPDATE self_study_topics SET video_id = ? WHERE id = ?')->execute(['https://evil.example/x', $t21]);
check('a non-YouTube address saved by mistake shows no video', ss2_topic_content($db, $cid, $t21)['video_id'] === null);
$db->prepare('UPDATE self_study_topics SET video_id = ? WHERE id = ?')->execute([$id, $t21]);

echo "\n== Six of eight, shuffled per student\n";
$qa = ss2_topic_questions($db, $me, $cid, $t21, $t);
check('six questions are drawn from the bank of eight', count($qa) === 6 && count(array_unique(array_column($qa, 'id'))) === 6);
$qb = ss2_topic_questions($db, $other, $cid, $t21, $t);
check('another student has their own draw/order or option order (not guaranteed different, but saved separately)', count($qb) === 6 && (int) $db->query("SELECT COUNT(*) FROM self_study_topic_progress WHERE topic_id = $t21 AND question_set IS NOT NULL")->fetchColumn() === 2);
$optSets = array_map(fn($q) => array_column($q['options'], 0), $qa);
check('every question lists all four options', count(array_filter($optSets, fn($o) => count($o) === 4 && count(array_unique($o)) === 4)) === 6);
$right = []; foreach ($qa as $i => $q) $right[(string) $q['id']] = $i < 4 ? $opt($q, 'b' . substr($q['text'], 1)) : $opt($q, 'a' . substr($q['text'], 1));
$r = ss2_submit_topic($db, $me, $cid, $t21, $right, $t + 10);
$tp = $db->query("SELECT score_correct, score_total FROM self_study_topic_progress WHERE student_id = $me AND topic_id = $t21")->fetch(PDO::FETCH_ASSOC);
check('4 right, 2 wrong = 4 of 6, and the topic is done', $r['ok'] && (int) $tp['score_correct'] === 4 && (int) $tp['score_total'] === 6, json_encode($tp));
check('Module 2 is not passed by merely finishing its topics: the quiz opens but Module 3 stays locked', $M()['modules'][1]['quiz']['open'] && !$M()['modules'][1]['quiz']['passed'] && !$open($M(), $t30));
$passAtt('module_quiz', $m2);
check('passing the Module 2 quiz opens Module 3', $M()['modules'][1]['quiz']['passed'] && $open($M(), $t30));

echo "\n== Checkpoint and Final\n";
$m = $M();
check('the Checkpoint stays locked while Module 3 is not passed', !$m['checkpoint']['open'] && !$m['final']['open']);
ss2_mark_done($db, $me, $cid, $t30, $t + 20);
$passAtt('module_quiz', $m3);
$m = $M();
check('with every module quiz passed the Checkpoint opens; it is not passed yet', $m['checkpoint']['open'] && !$m['checkpoint']['passed'] && !$m['final']['open']);
$ins->execute([$me, $cp1, $cid, '1', 0, 1]);
check('a failed Checkpoint keeps the Final locked', !$M()['final']['open']);
$ins->execute([$me, $cp1, $cid, '0', 1, 2]);
$m = $M();
check('passing the Checkpoint opens the Final, which is not passed until it is taken', $m['checkpoint']['passed'] && $m['final']['open'] && !$m['final']['passed']);
$passAtt('final_exam', null);
$m = $M();
check('passing the Final Exam completes the course', $m['checkpoint']['passed'] && $m['final']['open'] && $m['percent'] === 100, 'percent ' . $m['percent']);

echo "\n== Progress is saved for the Self-study cards\n";
ss2_save_progress($db, $me, $cid, $t + 30);
$p = $db->query("SELECT percent_complete, last_studied_at, completed_at FROM self_study_progress WHERE course_id = $cid AND student_id = $me")->fetch(PDO::FETCH_ASSOC);
check('percent and last studied date are in self_study_progress; 100% sets the completed date', (int) $p['percent_complete'] === 100 && $p['last_studied_at'] && $p['completed_at']);
$cards = ss_courses_for_student($db, $me);
check('the Self-study card reads the same numbers', $cards && $cards[0]['percent'] === 100 && $cards[0]['state'] === 'completed');
check('a student who has not begun has no progress row until they open a topic', !$db->query("SELECT 1 FROM self_study_progress WHERE course_id = $cid AND student_id = $other")->fetchColumn());
ss2_touch($db, $other, $cid, $tIntro, $t0);
check('opening the Introduction creates it at 0%', (int) $db->query("SELECT percent_complete FROM self_study_progress WHERE course_id = $cid AND student_id = $other")->fetchColumn() === 0);

echo "\n== Where the student left off\n";
$mm = ss2_model($db, $cid, $other);
check('the course reopens at the last topic seen', $mm['resume'] === $tIntro);
ss2_mark_done($db, $other, $cid, $tIntro, $t0 + 1); ss2_touch($db, $other, $cid, $t10, $t0 + 50);
check('after moving on, the last topic is the one they opened', ss2_model($db, $cid, $other)['resume'] === $t10);

echo "\n== Knowledge Check (no pass mark, retake)\n";
check('a course with a Knowledge Check lists its questions', count(ss2_kb_questions($db, $cid)) === 2);
$r = ss2_submit_kb($db, $other, $cid, [(string) $kb1 => '2', (string) $kb2 => '1'], $t0 + 60);
check('it marks and returns the score', $r['ok'] && $r['correct'] === 1 && $r['total'] === 2 && $r['attempt'] === 1);
$r = ss2_submit_kb($db, $other, $cid, [(string) $kb1 => '2', (string) $kb2 => '0'], $t0 + 70);
check('a retake is a new attempt and the latest score is shown', $r['correct'] === 2 && $r['attempt'] === 2 && ss2_model($db, $cid, $other)['kb']['last']['correct'] === 2);
check('it never changes the course bar', ss2_model($db, $cid, $other)['percent'] === ss2_model($db, $cid, $other)['percent'] && !$db->query("SELECT 1 FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id WHERE q.place = 'knowledge_check' AND a.is_correct IS NULL")->fetchColumn());

echo "\n== Files are private\n";
$dir = storage_path('selfstudy/' . $cid); @mkdir($dir, 0775, true); file_put_contents($dir . '/notes.txt', 'hello');
$db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path) VALUES (?, NULL, ?, ?)')->execute([$cid, 'notes.txt', $cid . '/notes.txt']); $f1 = (int) $db->lastInsertId();
$db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path) VALUES (?, NULL, ?, ?)')->execute([$cid, 'evil', '../../config/database.php']); $f2 = (int) $db->lastInsertId();
$db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path) VALUES (?, ?, ?, ?)')->execute([$cid, $t30, 'topic.txt', $cid . '/notes.txt']); $f3 = (int) $db->lastInsertId();
check('a student the course is shared with can open the file', ss2_file_for_student($db, $f1, $me) !== null);
check('a student it is NOT shared with cannot', ss2_file_for_student($db, $f1, $outsider) === null);
check('a path that climbs out of the folder is refused', ss2_file_for_student($db, $f2, $me) === null);
check('a file of a topic that is still locked is refused', ss2_file_for_student($db, $f3, $other) === null && ss2_file_for_student($db, $f3, $me) !== null);
$db->prepare("UPDATE self_study_courses SET status = 'archived' WHERE id = ?")->execute([$cid]);
check('an archived course gives no file and no course', ss2_file_for_student($db, $f1, $me) === null && ss_course_for_student($db, $cid, $me) === null);
$db->prepare("UPDATE self_study_courses SET status = 'published' WHERE id = ?")->execute([$cid]);

echo "\n== Clean up\n";
@unlink($dir . '/notes.txt'); @rmdir($dir);
foreach (['self_study_answers', 'self_study_topic_progress', 'self_study_video_progress', 'self_study_progress', 'self_study_access', 'self_study_files', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_rules'] as $t) {
    $col = in_array($t, ['self_study_course_rules']) ? 'course_id' : 'course_id';
    if ($t === 'self_study_video_progress') $db->exec("DELETE FROM $t WHERE topic_id IN (SELECT id FROM self_study_topics WHERE course_id IN ($cid, $draft))");
    else $db->exec("DELETE FROM $t WHERE $col IN ($cid, $draft)");
}
$db->exec("DELETE FROM self_study_courses WHERE id IN ($cid, $draft)");
$db->exec("DELETE FROM tutors WHERE id = $tutorId");
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($tutorUser, $me, $other, $outsider)");
$db->exec("DELETE FROM users WHERE id IN ($tutorUser, $me, $other, $outsider)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
