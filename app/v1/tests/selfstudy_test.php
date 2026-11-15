<?php
// Self-study (step 1): who sees which course, the card numbers, the "last studied" line, and the cover image pipeline.
//   php app/v1/tests/selfstudy_test.php      (needs migration 104; creates its own users/courses and removes them at the end)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudy.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
if (!ss_installed($db)) { fwrite(STDERR, "Apply migration 104 first.\n"); exit(2); }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sst_{$k}_$run", "sst_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, ucfirst($k), 'Tester']);
    return $id;
};
$tutorUser = $mk('tutor', 'tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutorUser]);
$tutorId = (int) $db->lastInsertId();
$me = $mk('me', 'student');
$other = $mk('other', 'student');

$course = function (string $title, string $status, string $level = 'beginner') use ($db, $tutorId): int {
    $db->prepare('INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count) VALUES (?, ?, ?, ?, ?, 4)')->execute([$tutorId, $title, 'desc', $level, $status]);
    return (int) $db->lastInsertId();
};
$share = fn(int $c, int $s, ?string $removed = null) => $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by, removed_at) VALUES (?, ?, ?, ?)')->execute([$c, $s, $tutorUser, $removed]);
$prog = fn(int $c, int $s, int $p, ?string $last, ?string $done = null) => $db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at, completed_at) VALUES (?, ?, ?, ?, ?)')->execute([$c, $s, $p, $last, $done]);

echo "\n== Who sees which course\n";
$pub = $course("Published $run", 'published', 'intermediate');
$draft = $course("Draft $run", 'draft');
$arch = $course("Archived $run", 'archived');
$gone = $course("Removed $run", 'published');
$never = $course("Never shared $run", 'published');
$done = $course("Finished $run", 'published', 'advanced');
$idle = $course("Not started $run", 'published');
foreach ([$pub, $draft, $arch, $idle, $done] as $c) $share($c, $me);
$share($gone, $me, gmdate('Y-m-d H:i:s'));
$share($pub, $other);
$prog($pub, $me, 34, gmdate('Y-m-d H:i:s', time() - 86400));
$prog($gone, $me, 80, gmdate('Y-m-d H:i:s', time() - 86400));
$prog($done, $me, 100, gmdate('Y-m-d H:i:s', time() - 5 * 86400), gmdate('Y-m-d H:i:s', time() - 5 * 86400));
$prog($pub, $other, 90, gmdate('Y-m-d H:i:s'));

$list = ss_courses_for_student($db, $me);
$titles = array_column($list, 'title');
check('the student sees exactly the published, shared courses', count($list) === 3 && in_array("Published $run", $titles, true) && in_array("Finished $run", $titles, true) && in_array("Not started $run", $titles, true), implode(' | ', $titles));
check('a draft never appears', !in_array("Draft $run", $titles, true));
check('an archived course never appears', !in_array("Archived $run", $titles, true));
check('a course with removed access never appears', !in_array("Removed $run", $titles, true));
check('a course never shared never appears', !in_array("Never shared $run", $titles, true));
check('another student sees only their own', array_column(ss_courses_for_student($db, $other), 'title') === ["Published $run"]);
check('a student with nothing shared gets an empty list', ss_courses_for_student($db, $tutorUser) === []);
check('opening a draft by id is refused', ss_course_for_student($db, $draft, $me) === null);
check('opening an archived course by id is refused', ss_course_for_student($db, $arch, $me) === null);
check('opening a removed course by id is refused', ss_course_for_student($db, $gone, $me) === null);
check('opening a course never shared is refused', ss_course_for_student($db, $never, $me) === null);
check('opening a shared published course works', (ss_course_for_student($db, $pub, $me)['title'] ?? '') === "Published $run");
check('another student cannot open it as me', ss_course_for_student($db, $done, $other) === null);
check('id 0 or a negative student gets nothing', ss_course_for_student($db, 0, $me) === null && ss_courses_for_student($db, -1) === []);

echo "\n== The numbers on the card\n";
$by = array_column($list, null, 'title');
$p = $by["Published $run"]; $f = $by["Finished $run"]; $n = $by["Not started $run"];
check('percent comes from the saved progress', $p['percent'] === 34 && $p['state'] === 'in_progress');
check('the other student\'s progress does not leak in', $p['percent'] !== 90);
check('no progress row = 0% and Not started', $n['percent'] === 0 && $n['state'] === 'not_started' && $n['last_text'] === 'Not started');
check('a completed date = 100% and Completed', $f['percent'] === 100 && $f['state'] === 'completed');
check('buttons: Start, Continue, none for completed', ss_button($n) === 'Start' && ss_button($p) === 'Continue' && ss_button($f) === '');
check('the tutor name is on the row', $p['tutor_name'] === 'Tutor Tester', $p['tutor_name']);
check('level and module count come through', $p['level'] === 'intermediate' && (int) $p['module_count'] === 4);
check('counts for the chips', ss_counts($list) === ['all' => 3, 'in_progress' => 1, 'not_started' => 1, 'completed' => 1], json_encode(ss_counts($list)));
check('a percent over 100 or below 0 is kept inside 0..100', ss_decorate(['percent' => 250, 'level' => 'beginner', 'completed_at' => null])['percent'] === 100 && ss_decorate(['percent' => -5, 'level' => 'beginner', 'completed_at' => null])['percent'] === 0);
check('an unknown level falls back to beginner', ss_decorate(['percent' => 0, 'level' => 'expert', 'completed_at' => null])['level'] === 'beginner');

echo "\n== Re-adding a removed student keeps their progress\n";
$db->prepare('UPDATE self_study_access SET removed_at = NULL WHERE course_id = ? AND student_id = ?')->execute([$gone, $me]);
$again = ss_course_for_student($db, $gone, $me);
check('after being added back the course shows with the old 80%', $again && $again['percent'] === 80);

echo "\n== Last studied line (Nairobi days)\n";
$now = strtotime('2026-11-13 09:00:00 UTC');   // 12:00 in Nairobi
check('same day', ss_last_text('in_progress', '2026-11-13 05:00:00', null, $now) === 'Last studied today');
check('late last night in UTC is already today in Nairobi', ss_last_text('in_progress', '2026-11-12 21:30:00', null, $now) === 'Last studied today');
check('yesterday', ss_last_text('in_progress', '2026-11-12 08:00:00', null, $now) === 'Last studied yesterday');
check('days ago', ss_last_text('in_progress', '2026-11-08 08:00:00', null, $now) === 'Last studied 5 days ago');
check('a long time ago shows the date', ss_last_text('in_progress', '2026-10-01 08:00:00', null, $now) === 'Last studied 1 Oct 2026');
check('finished shows the date', ss_last_text('completed', null, '2026-11-03 10:00:00', $now) === 'Finished 3 Nov 2026');

echo "\n== Cover images\n";
$img = function (int $w, int $h): string {
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 20, 140, 70));
    imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), (int) ($h / 2), (int) ($h / 2), imagecolorallocate($im, 255, 255, 255));
    $f = tempnam(sys_get_temp_dir(), 'sst') . '.png';
    imagepng($im, $f);
    imagedestroy($im);
    return $f;
};
$cid = 900000 + random_int(1, 90000);
$big = $img(2000, 1125);
$r = ss_cover_from_file($big, 'png', $cid);
check('a large cover is accepted', $r['ok'], $r['error'] ?? '');
$P = $r['paths'] ?? [];
$size = fn(?string $rel) => $rel && is_file(public_path($rel)) ? getimagesize(public_path($rel)) : null;
check('the original is kept as uploaded', $size($P['cover_original'] ?? null)[0] === 2000 && md5_file(public_path($P['cover_original'])) === md5_file($big));
foreach ([400, 800, 1600] as $w) {
    $s = $size($P['cover_' . $w] ?? null);
    check("a $w px WebP copy is made", $s && $s[0] === $w && $s[2] === IMAGETYPE_WEBP && $s[1] === (int) round(1125 * $w / 2000), json_encode($s));
}
$attrs = ss_cover_attrs(['cover_original' => $P['cover_original'], 'cover_400' => $P['cover_400'], 'cover_800' => $P['cover_800'], 'cover_1600' => $P['cover_1600']]);
check('srcset lists all three widths and sizes is set', substr_count($attrs['srcset'], 'w') >= 3 && str_contains($attrs['srcset'], ' 400w') && str_contains($attrs['srcset'], ' 1600w') && $attrs['sizes'] !== '');

$mid = $img(900, 506);
$r2 = ss_cover_from_file($mid, 'png', $cid);
check('a 900 px original gets 400 and 800 only (never enlarged)', $r2['ok'] && $r2['paths']['cover_400'] && $r2['paths']['cover_800'] && $r2['paths']['cover_1600'] === null);
$small = $img(300, 169);
$r3 = ss_cover_from_file($small, 'png', $cid);
check('a 300 px original gets no copies and the card falls back to the original', $r3['ok'] && !$r3['paths']['cover_400'] && !$r3['paths']['cover_800'] && ss_cover_attrs($r3['paths'] + [])['srcset'] === '' && str_contains(ss_cover_attrs($r3['paths'])['src'], 'original'));
check('a course without a cover gives no image', ss_cover_attrs(['cover_original' => null, 'cover_400' => null, 'cover_800' => null, 'cover_1600' => null]) === null);
$txt = tempnam(sys_get_temp_dir(), 'sst'); file_put_contents($txt, 'not an image');
check('a file that is not an image is refused', ss_cover_from_file($txt, 'png', $cid)['ok'] === false);
check('no file chosen is reported', (ss_cover_save(['error' => UPLOAD_ERR_NO_FILE], $cid)['none'] ?? false) === true);
$up = ss_cover_save(['name' => 'c.png', 'tmp_name' => $big, 'error' => UPLOAD_ERR_OK, 'size' => filesize($big)], $cid);
check('the upload wrapper validates then makes the copies', $up['ok'] && $up['paths']['cover_1600'] !== null, $up['error'] ?? '');

echo "\n== Clean up\n";
foreach (glob(public_path('uploads/selfstudy/' . $cid . '/*')) ?: [] as $x) @unlink($x);
@rmdir(public_path('uploads/selfstudy/' . $cid));
foreach ([$big, $mid, $small, $txt] as $x) @unlink($x);
$ids = implode(',', [$pub, $draft, $arch, $gone, $never, $done, $idle]);
$db->exec("DELETE FROM self_study_progress WHERE course_id IN ($ids)");
$db->exec("DELETE FROM self_study_access WHERE course_id IN ($ids)");
$db->exec("DELETE FROM self_study_courses WHERE id IN ($ids)");
$db->exec("DELETE FROM tutors WHERE id = $tutorId");
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($tutorUser, $me, $other)");
$db->exec("DELETE FROM users WHERE id IN ($tutorUser, $me, $other)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
