<?php
// Self-study (step 5): the Content and Questions tabs — permissions, outline, drag-order numbering, topic text cleaning, video, pictures, files, question rules,
// archive-or-delete, optional topics for students who already passed, long-answer comments.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_content_test.php     (a scratch database with every migration applied, 108 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyContent.php';

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
if (!ssc_installed($db)) { fwrite(STDERR, "Apply migration 108 first.\n"); exit(2); }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["ssc_{$k}_$run", "ssc_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$mkTutor = function (string $k) use ($db, $mk): array {
    $u = $mk($k, 'tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$u]);
    return ['uid' => $u, 'role' => 'tutor', 'tutor_id' => (int) $db->lastInsertId(), 'name' => ucfirst($k) . ' Tester'];
};
$owner = $mkTutor('owner'); $editor = $mkTutor('editor'); $viewerT = $mkTutor('viewer'); $stranger = $mkTutor('stranger');
$adminUid = $mk('admin', 'admin'); $admin = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Admin'];
$s1 = $mk('s1', 'student'); $s2 = $mk('s2', 'student');
$r = sst_save_details($db, $owner, null, ['title' => 'Content test ' . $run, 'short_description' => 'd', 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60]);
$cid = (int) $r['id'];
foreach ([[$editor, 'edit'], [$viewerT, 'view']] as [$t, $a]) $db->prepare('INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, ?, ?, ?)')->execute([$cid, $t['tutor_id'], $a, $owner['uid'], ss2_ts(time())]);
foreach ([$s1, $s2] as $s) $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by) VALUES (?, ?, ?)')->execute([$cid, $s, $owner['uid']]);
$now = strtotime('2026-11-13 10:00:00 +03:00');

echo "== Permissions\n";
check('a "Can view" tutor cannot add a module or topic', refused(fn() => ssc_add_module($db, $viewerT, $cid, 'X')) === 403);
check('a stranger gets 404, admin cannot change', refused(fn() => ssc_add_module($db, $stranger, $cid, 'X')) === 404 && refused(fn() => ssc_add_module($db, $admin, $cid, 'X')) === 403);
check('a "Can view" tutor and admin can read the outline and a bank', refused(fn() => ssc_guard($db, $viewerT, $cid, false)) === null && refused(fn() => ssc_guard($db, $admin, $cid, false)) === null);
check('a "Can edit" tutor can add a module', ($m1 = ssc_add_module($db, $editor, $cid, '  Getting Started ', $now)) > 0);

echo "\n== The outline and its numbers\n";
$m2 = ssc_add_module($db, $owner, $cid, 'Working with Data', $now); $m3 = ssc_add_module($db, $owner, $cid, 'Charts', $now);
$ta = ssc_add_topic($db, $owner, $cid, $m1, 'Install', $now); $tb = ssc_add_topic($db, $owner, $cid, $m1, 'Variables', $now);
$tc = ssc_add_topic($db, $owner, $cid, $m2, 'Reading CSV files', $now); $td = ssc_add_topic($db, $owner, $cid, $m2, 'Cleaning', $now); $te = ssc_add_topic($db, $owner, $cid, $m2, 'Filtering', $now);
$intro = ssc_add_topic($db, $owner, $cid, null, '', $now);
check('adding the introduction twice gives the same topic', ssc_add_topic($db, $owner, $cid, null, '', $now) === $intro);
$o = ssc_outline($db, $cid);
check('modules and topics are numbered 1.0, 1.1, 2.0, 2.1, 2.2', $o['modules'][0]['topics'][0]['number'] === '1.0' && $o['modules'][0]['topics'][1]['number'] === '1.1' && $o['modules'][1]['topics'][2]['number'] === '2.2', json_encode(array_column($o['modules'][1]['topics'], 'number')));
check('the card\'s module count follows', (int) $db->query("SELECT module_count FROM self_study_courses WHERE id = $cid")->fetchColumn() === 3);
check('an empty name is refused', refused(fn() => ssc_add_topic($db, $owner, $cid, $m1, '   ')) === 422 && refused(fn() => ssc_add_module($db, $owner, $cid, '')) === 422);
ssc_rename($db, $owner, $cid, 'topic', $td, 'Cleaning missing values');
check('rename works', ssc_outline($db, $cid)['modules'][1]['topics'][1]['title'] === 'Cleaning missing values');
ssc_reorder($db, $owner, $cid, 'topic', [$te, $tc, $td], $m2, $now);
$n = array_column(ssc_outline($db, $cid)['modules'][1]['topics'], 'title', 'number');
check('reordering topics renumbers by itself (Filtering is now 2.0)', $n['2.0'] === 'Filtering' && $n['2.1'] === 'Reading CSV files' && $n['2.2'] === 'Cleaning missing values', json_encode($n));
ssc_reorder($db, $owner, $cid, 'topic', [$tb, $tc, $ta], $m1, $now);      // a topic dragged from Module 2 into Module 1
$o = ssc_outline($db, $cid);
check('a topic dragged into another module moves there and both modules renumber', count($o['modules'][0]['topics']) === 3 && count($o['modules'][1]['topics']) === 2 && $o['modules'][0]['topics'][1]['title'] === 'Reading CSV files' && $o['modules'][1]['topics'][0]['number'] === '2.0');
ssc_reorder($db, $owner, $cid, 'module', [$m2, $m1, $m3], null, $now);
check('reordering modules renumbers the topics (Module 2 is first now)', ssc_outline($db, $cid)['modules'][0]['id'] === $m2 && ssc_outline($db, $cid)['modules'][1]['topics'][0]['number'] === '2.0');
$flat = array_map('intval', array_column($db->query("SELECT id FROM self_study_topics WHERE course_id = $cid AND archived_at IS NULL ORDER BY position, id")->fetchAll(PDO::FETCH_ASSOC), 'id'));
check('the introduction stays first and positions are 1..n', $flat[0] === $intro && implode(',', array_map('intval', $db->query("SELECT position FROM self_study_topics WHERE course_id = $cid ORDER BY position")->fetchAll(PDO::FETCH_COLUMN))) === '1,2,3,4,5,6');
check('a stale or foreign list is refused', refused(fn() => ssc_reorder($db, $owner, $cid, 'module', [$m1], null)) === 409);
// back to a simple layout: m1 = [Install? ...]; keep ids
ssc_reorder($db, $owner, $cid, 'module', [$m1, $m2, $m3], null, $now);

echo "\n== Topic text: cleaned, versioned, autosaved\n";
$dirty = '<h1>Why</h1><p onclick="x()">Hi <b>there</b><script>alert(1)</script></p><img src="https://evil.example/track.png"><img src="uploads/selfstudy/' . $cid . '/topic-abc.webp"><img src="data:image/png;base64,AAAA"><iframe src="https://evil"></iframe><a href="javascript:alert(1)">x</a><pre><code>df.dropna()</code></pre><table><tr><td>1</td></tr></table>';
$r = ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'Reading CSV files', 'html' => $dirty, 'video' => '', 'version' => 1], $now);
$saved = (string) $db->query("SELECT content_html FROM self_study_topics WHERE id = $tc")->fetchColumn();
check('the text is cleaned before it is saved', $r['ok'] && !preg_match('/<script|onclick|<iframe|javascript:|evil|data:image/i', $saved) && str_contains($saved, '<h2>Why</h2>') && str_contains($saved, '<pre><code>df.dropna()</code></pre>') && str_contains($saved, '<table>'), $saved);
check('only pictures this site stored survive (the external one is removed)', substr_count($saved, '<img') === 1 && str_contains($saved, 'uploads/selfstudy/' . $cid . '/topic-abc.webp'));
$norm = ssc_clean_topic_html('<img src="https://dazu.example/uploads/selfstudy/9/topic-x.webp">');
check('a picture shown under the full site address is saved as uploads/selfstudy/...', str_contains($norm, 'src="uploads/selfstudy/9/topic-x.webp"'), $norm);
$content = ss2_topic_content($db, $cid, $tc);
check('students get the same cleaned text, with pictures as real addresses', !str_contains($content['html'], 'script') && str_contains($content['html'], 'src="' . url('uploads/selfstudy/' . $cid . '/topic-abc.webp') . '"'));
check('the version goes up on every save', (int) $db->query("SELECT edit_version FROM self_study_topics WHERE id = $tc")->fetchColumn() === 2 && $r['version'] === 2);
$r = ssc_save_topic($db, $editor, $cid, $tc, ['title' => 'Reading CSV files', 'html' => '<p>Tutor B edited</p>', 'version' => 2], $now + 60);
check('a second tutor saving from the current version works', $r['ok'] && $r['version'] === 3);
$r = ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'Reading CSV files', 'html' => '<p>Tutor A stale</p>', 'version' => 2], $now + 120);
check('a tutor who opened an older version gets a warning instead of overwriting', !$r['ok'] && !empty($r['conflict']) && $r['by'] === 'Editor Tester' && $r['version'] === 3, json_encode($r));
check('nothing of the second tutor was overwritten', str_contains((string) $db->query("SELECT content_html FROM self_study_topics WHERE id = $tc")->fetchColumn(), 'Tutor B edited'));
$r = ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'Reading CSV files', 'html' => '<p>Tutor A, on purpose</p>', 'version' => 3, 'force' => 1], $now + 180);
check('"Save mine anyway" overwrites on purpose', $r['ok'] && str_contains((string) $db->query("SELECT content_html FROM self_study_topics WHERE id = $tc")->fetchColumn(), 'on purpose'));
check('a "Can view" tutor cannot save a topic', refused(fn() => ssc_save_topic($db, $viewerT, $cid, $tc, ['title' => 'x', 'html' => '', 'version' => 4])) === 403);
check('a topic of another course cannot be saved here', refused(fn() => ssc_save_topic($db, $owner, $cid, 999999, ['title' => 'x', 'html' => '', 'version' => 1])) === 404);
check('an empty title is refused', refused(fn() => ssc_save_topic($db, $owner, $cid, $tc, ['title' => '  ', 'html' => '', 'version' => 4, 'force' => 1])) === 422);
$t = ssc_load_topic($db, $viewerT, $cid, $tc);
check('a "Can view" tutor can load it (read only) and sees the version', $t['version'] === 4 && str_contains($t['html'], 'on purpose'));

echo "\n== The YouTube video\n";
$yt = 'dQw4w9WgXcQ';
$r = ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'Reading CSV files', 'html' => '', 'video' => "https://www.youtube.com/watch?v=$yt&t=5", 'video_length' => '5:58', 'version' => 4], $now);
$row = $db->query("SELECT video_id, video_duration FROM self_study_topics WHERE id = $tc")->fetch(PDO::FETCH_ASSOC);
check('only the video id and length are saved', $r['ok'] && $row['video_id'] === $yt && (int) $row['video_duration'] === 358, json_encode($row));
check('the editor gets a thumbnail address', str_contains(ssc_load_topic($db, $owner, $cid, $tc)['thumb'], 'i.ytimg.com/vi/' . $yt) && ssc_load_topic($db, $owner, $cid, $tc)['video_length'] === '5:58');
check('another site is refused with a clear message', refused(fn() => ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'x', 'html' => '', 'video' => 'https://vimeo.com/123456789', 'version' => 6])) === 422);
check('a wrong length is refused', refused(fn() => ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'x', 'html' => '', 'video' => $yt, 'video_length' => 'abc', 'version' => 6])) === 422);
$r = ssc_save_topic($db, $owner, $cid, $tc, ['title' => 'Reading CSV files', 'html' => '', 'video' => '', 'version' => 5], $now);
check('clearing the video box removes the video', $r['ok'] && $db->query("SELECT video_id FROM self_study_topics WHERE id = $tc")->fetchColumn() === null);

echo "\n== Pictures and files\n";
$img = function (int $w, int $h, string $kind = 'jpg', bool $exif = false): string {
    $im = imagecreatetruecolor($w, $h); imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 30, 140, 70));
    $f = tempnam(sys_get_temp_dir(), 'sscp'); if ($kind === 'png') imagepng($im, $f); elseif ($kind === 'webp') imagewebp($im, $f, 90); else imagejpeg($im, $f, 90); imagedestroy($im);
    if ($exif && $kind === 'jpg') { $d = file_get_contents($f); $seg = "Exif\0\0SECRETGPS" . str_repeat('x', 20); file_put_contents($f, substr($d, 0, 2) . "\xFF\xE1" . pack('n', strlen($seg) + 2) . $seg . substr($d, 2)); }
    return $f;
};
$file = fn(string $p, string $name = 'a.jpg', ?int $size = null) => ['name' => $name, 'tmp_name' => $p, 'error' => UPLOAD_ERR_OK, 'size' => $size ?? filesize($p)];
$big = $img(2400, 1600, 'jpg', true);
$r = ssc_upload_image($db, $owner, $cid, $file($big));
check('a big picture is saved as WebP at most 1600 px wide, keeping its shape', $r['ok'] && $r['width'] === 1600 && $r['height'] === 1067 && str_ends_with($r['path'], '.webp') && is_file(public_path($r['path'])), json_encode($r));
check('hidden data is gone', !str_contains(file_get_contents(public_path($r['path'])), 'SECRETGPS'));
$small = $img(500, 300, 'png'); $r2 = ssc_upload_image($db, $owner, $cid, $file($small, 'b.png'));
check('a small picture is never enlarged', $r2['ok'] && $r2['width'] === 500 && getimagesize(public_path($r2['path']))[0] === 500);
$txt = tempnam(sys_get_temp_dir(), 'sscx'); file_put_contents($txt, '<?php echo 1;');
check('a script renamed to .jpg is refused', !ssc_upload_image($db, $owner, $cid, $file($txt))['ok']);
check('a picture over 10 MB is refused', !ssc_upload_image($db, $owner, $cid, $file($small, 'c.png', 11 * 1048576))['ok']);
check('a "Can view" tutor cannot upload a picture', refused(fn() => ssc_upload_image($db, $viewerT, $cid, $file($small))) === 403);
$pdf = tempnam(sys_get_temp_dir(), 'sscf'); file_put_contents($pdf, "%PDF-1.4\n" . str_repeat('x', 2000));
$r = ssc_attach_file($db, $owner, $cid, $tc, $file($pdf, 'cleaning notes.pdf'));
check('a PDF is attached to the topic and stored privately (not under public/)', $r['ok'] && $r['file']['name'] === 'cleaning notes.pdf' && is_file(storage_path('selfstudy/' . $db->query('SELECT file_path FROM self_study_files WHERE id = ' . $r['file']['id'])->fetchColumn())) && !is_dir(public_path('uploads/selfstudy/' . $cid . '/notes')));
$fid = $r['file']['id'];
check('the file is listed under the topic', ssc_load_topic($db, $owner, $cid, $tc)['files'][0]['id'] === $fid);
check('an .exe or .php file is refused', !ssc_attach_file($db, $owner, $cid, $tc, $file($pdf, 'virus.exe'))['ok'] && !ssc_attach_file($db, $owner, $cid, $tc, $file($pdf, 'shell.php'))['ok']);
check('a text file renamed to .pdf is refused (the real content is checked)', !ssc_attach_file($db, $owner, $cid, $tc, $file($txt, 'fake.pdf'))['ok']);
check('a file over 20 MB is refused', !ssc_attach_file($db, $owner, $cid, $tc, $file($pdf, 'big.pdf', 21 * 1048576))['ok']);
check('a "Can view" tutor can download it but not remove it', ssc_file_for_tutor($db, $viewerT, $fid) !== null && refused(fn() => ssc_remove_file($db, $viewerT, $cid, $fid)) === 403);
check('a stranger tutor cannot download it', ssc_file_for_tutor($db, $stranger, $fid) === null);
check('a student sees it in the Resources list; the download needs the topic to be open', in_array($fid, array_column(ss2_files($db, $cid), 'id')) && ss2_file_for_student($db, $fid, $s1) === null);   // the course is a draft: no access

echo "\n== Questions: the rules\n";
$mc = fn(array $o = ['a', 'b', 'c', 'd'], int $c = 1, string $text = 'Which?') => ['type' => 'multiple_choice', 'text' => $text, 'options' => $o, 'correct' => $c];
check('a multiple choice question with 4 options saves in a topic bank', ($q1 = ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(), $now)) > 0);
check('one option, seven options, empty option, duplicate options and a wrong correct index are refused', refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a'], 0))) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a', 'b', 'c', 'd', 'e', 'f', 'g'], 0))) === 422
    && refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a', ''], 0))) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a', 'A'], 0))) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a', 'b'], 5))) === 422);
check('six options is the maximum allowed', ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['a', 'b', 'c', 'd', 'e', 'f'], 5)) > 0);
check('a short answer needs accepted answers (duplicates by the marking rule are merged)', refused(fn() => ssc_save_question($db, $owner, $cid, "topic:$tc", ['type' => 'short_answer', 'text' => 'Q', 'accepted' => ['', ' ']])) === 422);
$qs = ssc_save_question($db, $owner, $cid, "topic:$tc", ['type' => 'short_answer', 'text' => 'Which method counts empty cells?', 'accepted' => ['isnull().sum()', 'ISNULL().SUM().', 'isna().sum()']]);
check('"isnull().sum()" and "ISNULL().SUM()." count as the same answer', count(json_decode((string) $db->query("SELECT accepted_answers FROM self_study_questions WHERE id = $qs")->fetchColumn(), true)) === 2);
check('a long answer is allowed in a topic bank', ($ql = ssc_save_question($db, $owner, $cid, "topic:$tc", ['type' => 'long_answer', 'text' => 'In your own words…'])) > 0);
check('a long answer is refused in the Knowledge Check, a module quiz and both exams', refused(fn() => ssc_save_question($db, $owner, $cid, 'kb', ['type' => 'long_answer', 'text' => 'Q'])) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, "module:$m1", ['type' => 'long_answer', 'text' => 'Q', 'tag' => $ta])) === 422
    && refused(fn() => ssc_save_question($db, $owner, $cid, 'checkpoint', ['type' => 'long_answer', 'text' => 'Q', 'tag' => $ta])) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, 'final', ['type' => 'long_answer', 'text' => 'Q', 'tag' => $ta])) === 422);
check('the Knowledge Check is multiple choice only', refused(fn() => ssc_save_question($db, $owner, $cid, 'kb', ['type' => 'short_answer', 'text' => 'Q', 'accepted' => ['x']])) === 422 && ssc_save_question($db, $owner, $cid, 'kb', $mc()) > 0);
check('a quiz or exam question must be tagged with a topic', refused(fn() => ssc_save_question($db, $owner, $cid, "module:$m1", $mc())) === 422 && refused(fn() => ssc_save_question($db, $owner, $cid, 'checkpoint', $mc())) === 422);
check('a module quiz question must be tagged with a topic of THAT module', refused(fn() => ssc_save_question($db, $owner, $cid, "module:$m1", $mc() + ['tag' => $te])) === 422 && ssc_save_question($db, $owner, $cid, "module:$m1", $mc() + ['tag' => $ta]) > 0);
check('an exam question can be tagged with any topic of the course', ssc_save_question($db, $owner, $cid, 'checkpoint', $mc() + ['tag' => $te]) > 0);
check('a "Can view" tutor cannot add a question', refused(fn() => ssc_save_question($db, $viewerT, $cid, 'kb', $mc())) === 403);
$qe = ssc_save_question($db, $owner, $cid, "topic:$tc", ['id' => $q1] + $mc(['x', 'y'], 0, 'Edited?'), $now);
$bank = ssc_questions($db, $viewerT, $cid, "topic:$tc");
check('a question can be edited', $qe === $q1 && array_values(array_filter($bank['questions'], fn($q) => $q['id'] === $q1))[0]['text'] === 'Edited?');
check('a topic bank says students get 6 of the bank', $bank['draw'] === 6 && count($bank['questions']) === 4);
$ids = array_column($bank['questions'], 'id'); $rev = array_reverse($ids);
ssc_reorder_questions($db, $owner, $cid, "topic:$tc", $rev);
check('questions can be reordered', array_column(ssc_questions($db, $owner, $cid, "topic:$tc")['questions'], 'id') === $rev);
$bq = ssc_questions($db, $owner, $cid, "module:$m1");
check('a quiz bank warns "needs 12 questions and has 1"', $bq['warning'] === 'This quiz needs 12 questions and has 1.' && $bq['need'] === 12, (string) $bq['warning']);
check('the checkpoint bank warns it needs 30', ssc_questions($db, $owner, $cid, 'checkpoint')['warning'] === 'This exam needs 30 questions and has 1.');
$ck = array_column(sst_checklist($db, $cid), 'done', 'key');
check('the publish checklist reads the counts: not enough exam questions keeps it unticked', !$ck['exams'] && $ck['content']);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 1, final_questions = 1 WHERE id = ?')->execute([$cid]);
ssc_save_question($db, $owner, $cid, 'final', $mc() + ['tag' => $te]);
check('with enough questions in both exams the item ticks', array_column(sst_checklist($db, $cid), 'done', 'key')['exams']);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 30, final_questions = 40 WHERE id = ?')->execute([$cid]);
check('the Final Exam bank warns when the tutor raises the number (40)', ssc_questions($db, $owner, $cid, 'final')['warning'] === 'This exam needs 40 questions and has 1.');
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 1, final_questions = 1 WHERE id = ?')->execute([$cid]);
$o = ssc_outline($db, $cid);
check('the outline shows each bank\'s count', $o['kb']['count'] === 1 && $o['modules'][0]['quiz']['count'] === 1 && $o['checkpoint']['count'] === 1);

echo "\n== Delete or archive a question\n";
$qdel = ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['p', 'q'], 0, 'Delete me'));
check('an unanswered question is deleted', ssc_delete_question($db, $owner, $cid, $qdel) === false && !$db->query("SELECT 1 FROM self_study_questions WHERE id = $qdel")->fetchColumn());
$qarc = ssc_save_question($db, $owner, $cid, "topic:$tc", $mc(['p', 'q'], 0, 'Answered one'));
$db->prepare('INSERT INTO self_study_answers (student_id, question_id, course_id, answer, is_correct, attempt) VALUES (?, ?, ?, ?, 1, 1)')->execute([$s1, $qarc, $cid, '0']);
check('a question students answered is archived, not deleted (their answer is kept)', ssc_delete_question($db, $owner, $cid, $qarc) === true && (int) $db->query("SELECT COUNT(*) FROM self_study_answers WHERE question_id = $qarc")->fetchColumn() === 1);
check('an archived question is gone from the bank, the counts and the student draw', !in_array($qarc, array_column(ssc_questions($db, $owner, $cid, "topic:$tc")['questions'], 'id')) && !in_array($qarc, array_column(ss2_preview_questions($db, $cid, $tc), 'id')));

echo "\n== Long answers: the tutor's comment\n";
$ins = $db->prepare('INSERT INTO self_study_answers (student_id, question_id, course_id, answer, is_correct, attempt) VALUES (?, ?, ?, ?, NULL, 1)');
$ins->execute([$s1, $ql, $cid, 'Because the average changes.']); $aid = (int) $db->lastInsertId();
$list = ssc_answers_to_mark($db, $viewerT, $cid);
check('the long answer is in "Answers to mark" with the student\'s name', count($list) === 1 && $list[0]['student'] === 'S1 Tester' && !$list[0]['marked']);
check('a "Can view" tutor can read but not mark', refused(fn() => ssc_mark_answer($db, $viewerT, $cid, $aid, 'Good')) === 403);
check('an empty comment is refused', refused(fn() => ssc_mark_answer($db, $owner, $cid, $aid, '   ')) === 422);
ssc_mark_answer($db, $owner, $cid, $aid, 'Good, add an example next time.', $now);
check('the comment is saved with who and when, and the topic was never waiting', ssc_answers_to_mark($db, $owner, $cid)[0]['marked'] && (int) $db->query("SELECT marked_by FROM self_study_answers WHERE id = $aid")->fetchColumn() === $owner['uid']);
check('the student sees the comment under that question', ss2_topic_comments($db, $s1, $cid, [['id' => $ql]])[$ql] === 'Good, add an example next time.' && ss2_topic_comments($db, $s2, $cid, [['id' => $ql]]) === []);
check('only long answers can be marked', refused(fn() => ssc_mark_answer($db, $owner, $cid, (int) $db->query("SELECT id FROM self_study_answers WHERE question_id = $qarc")->fetchColumn(), 'x')) === 404);

echo "\n== Archive or delete a topic or module\n";
$db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, started_at, last_seen_at, done_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$s1, $tc, $cid, ss2_ts($now), ss2_ts($now), ss2_ts($now)]);
$allT = []; foreach (ssc_outline($db, $cid)['modules'] as $mm) foreach ($mm['topics'] as $tt) $allT[$tt['id']] = $tt;
check('the outline marks a topic students started', $allT[$tc]['progress'] === true && $allT[$te]['progress'] === false);
check('a topic students started cannot be deleted', refused(fn() => ssc_delete($db, $owner, $cid, 'topic', $tc)) === 409);
ssc_archive($db, $owner, $cid, 'topic', $tc, $now);
check('it can be archived: hidden from students, listed as archived, progress kept', !isset(ss2_model($db, $cid, $s1)['topics'][$tc]) && count(ssc_outline($db, $cid)['archived']['topics']) === 1 && (int) $db->query("SELECT COUNT(*) FROM self_study_topic_progress WHERE topic_id = $tc")->fetchColumn() === 1);
ssc_restore($db, $owner, $cid, 'topic', $tc, $now);
check('and restored', isset(ss2_model($db, $cid, $s1)['topics'][$tc]) && !ssc_outline($db, $cid)['archived']['topics']);
$tz = ssc_add_topic($db, $owner, $cid, $m3, 'Throwaway', $now);
$qz = ssc_save_question($db, $owner, $cid, "topic:$tz", $mc());
$db->prepare('INSERT INTO self_study_files (course_id, topic_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?, 10)')->execute([$cid, $tz, 'x.txt', $cid . '/none.txt']);
check('a topic with no progress is deleted with its questions and files', refused(fn() => ssc_delete($db, $owner, $cid, 'topic', $tz)) === null && !$db->query("SELECT 1 FROM self_study_topics WHERE id = $tz")->fetchColumn() && !$db->query("SELECT 1 FROM self_study_questions WHERE id = $qz")->fetchColumn() && !$db->query("SELECT 1 FROM self_study_files WHERE topic_id = $tz")->fetchColumn());
check('a "Can view" tutor cannot delete or archive', refused(fn() => ssc_delete($db, $viewerT, $cid, 'topic', $te)) === 403 && refused(fn() => ssc_archive($db, $viewerT, $cid, 'module', $m1)) === 403);
check('a module with a started topic cannot be deleted, only archived', refused(fn() => ssc_delete($db, $owner, $cid, 'module', $m1)) === 409);
ssc_archive($db, $owner, $cid, 'module', $m1, $now);
check('archiving a module hides it and its topics from students and from the module count', count(ss2_model($db, $cid, $s1)['modules']) === 2 && (int) $db->query("SELECT module_count FROM self_study_courses WHERE id = $cid")->fetchColumn() === 2);
ssc_restore($db, $owner, $cid, 'module', $m1, $now);
$m4 = ssc_add_module($db, $owner, $cid, 'Empty module', $now);
check('an untouched empty module is deleted', refused(fn() => ssc_delete($db, $owner, $cid, 'module', $m4)) === null && !$db->query("SELECT 1 FROM self_study_modules WHERE id = $m4")->fetchColumn());

echo "\n== A topic added after a student passed the module is optional for them\n";
$passAt = $now - 5000;
$db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'module_quiz', ?, ?, ?, 100, 1, 'submitted', '[]')")->execute([$s1, $cid, $m1, ss2_ts($passAt - 60), ss2_ts($passAt)]);
$db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at) VALUES (?, ?, 10, ?)')->execute([$cid, $s1, ss2_ts($now - 9000)]);
$before = ss2_model($db, $cid, $s1); $beforeOther = ss2_model($db, $cid, $s2);
$late = ssc_add_topic($db, $owner, $cid, $m1, 'Added later', $now);
$after = ss2_model($db, $cid, $s1); $other = ss2_model($db, $cid, $s2);
check('for the student who passed Module 1 the new topic is optional and the quiz stays passed', $after['topics'][$late]['optional'] === true && $after['modules'][0]['quiz']['passed'] && $after['modules'][1]['open']);
check('for a student who has not reached Module 1\'s end it is compulsory', empty($other['topics'][$late]['optional']) && !$other['modules'][0]['quiz']['passed']);
check('the optional topic does not change the passed student\'s bar, but adds to the other student\'s items', $after['percent'] === $before['percent'] && $other['items'] === $beforeOther['items'] + 1 && $after['items'] === $before['items'], "{$after['percent']} vs {$before['percent']}; items {$other['items']} vs {$beforeOther['items']}");
check('the optional topic can still be opened by the student who passed', $after['topics'][$late]['open']);
$p = $db->query("SELECT percent_complete, last_studied_at FROM self_study_progress WHERE course_id = $cid AND student_id = $s1")->fetch(PDO::FETCH_ASSOC);
check('the saved percent was recalculated by itself and the "last studied" date was not touched', (int) $p['percent_complete'] === $after['percent'] && ss2_parse($p['last_studied_at']) === $now - 9000, json_encode($p));
$db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) VALUES (?, ?, ?, ?)')->execute([$s1, $late, $cid, ss2_ts($now)]);
check('if the student does read it, it counts as done', ss2_model($db, $cid, $s1)['topics'][$late]['done'] && ss2_model($db, $cid, $s1)['items'] === $after['items'] + 1);

echo "\n== A finished course stays finished when the tutor adds content\n";
$db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'final_exam', NULL, ?, ?, 100, 1, 'submitted', '[]')")->execute([$s1, $cid, ss2_ts($passAt), ss2_ts($passAt)]);
$db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, 'checkpoint_exam', NULL, ?, ?, 100, 1, 'submitted', '[]')")->execute([$s1, $cid, ss2_ts($passAt), ss2_ts($passAt)]);
$db->prepare('UPDATE self_study_progress SET percent_complete = 100, completed_at = ? WHERE course_id = ? AND student_id = ?')->execute([ss2_ts($passAt), $cid, $s1]);
ssc_add_module($db, $owner, $cid, 'A brand new module', $now);
check('a student who passed the Final Exam keeps the Completed date when a new module is added', $db->query("SELECT completed_at FROM self_study_progress WHERE course_id = $cid AND student_id = $s1")->fetchColumn() !== null);

echo "\n== Old or imported text is cleaned again when the editor opens it\n";
$db->prepare('UPDATE self_study_topics SET content_html = ? WHERE id = ?')->execute(['<p onclick="x()">Hi</p><script>alert(1)</script><img src="https://evil.example/x.png"><iframe src="https://evil"></iframe>', $tc]);
$lt = ssc_load_topic($db, $viewerT, $cid, $tc);
check('unsafe text saved by any other route never reaches the editor', !preg_match('/<script|onclick|evil|<iframe/i', $lt['html']) && str_contains($lt['html'], 'Hi'), $lt['html']);
$answeredMany = ssc_questions($db, $owner, $cid, "topic:$tc");
check('the question list says which questions were answered (one grouped lookup)', isset($answeredMany['questions'][0]['answered']));

echo "\n== Clean up\n";
foreach (glob(public_path('uploads/selfstudy/' . $cid . '/*')) ?: [] as $f) @unlink($f); @rmdir(public_path('uploads/selfstudy/' . $cid));
foreach (glob(storage_path('selfstudy/' . $cid . '/*')) ?: [] as $f) @unlink($f); @rmdir(storage_path('selfstudy/' . $cid));
foreach (['self_study_answers', 'self_study_attempts', 'self_study_topic_progress', 'self_study_progress', 'self_study_access', 'self_study_files', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_editors'] as $t) $db->exec("DELETE FROM $t WHERE course_id = $cid");
$db->exec("DELETE FROM self_study_courses WHERE id = $cid");
foreach ([$big, $small, $txt, $pdf] as $f) @unlink($f);
$all = implode(',', [$owner['uid'], $editor['uid'], $viewerT['uid'], $stranger['uid'], $adminUid, $s1, $s2]);
$db->exec("DELETE FROM portal_notifications WHERE user_id IN ($all)");
$db->exec('DELETE FROM tutors WHERE id IN (' . implode(',', array_column([$owner, $editor, $viewerT, $stranger], 'tutor_id')) . ')');
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($all)");
$db->exec("DELETE FROM users WHERE id IN ($all)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
