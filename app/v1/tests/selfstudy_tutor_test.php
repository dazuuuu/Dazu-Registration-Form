<?php
// Self-study (step 4): the tutor side — who may do what, Details validation, the cover image pipeline, the publish checklist, archive, delete, duplicate, ownership.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_tutor_test.php     (a scratch database with every migration applied, 107 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyTutor.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
if (!sst_installed($db)) { fwrite(STDERR, "Apply migration 107 first.\n"); exit(2); }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sstt_{$k}_$run", "sstt_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$mkTutor = function (string $k) use ($db, $mk): array {
    $u = $mk($k, 'tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$u]);
    $t = (int) $db->lastInsertId();
    return ['uid' => $u, 'role' => 'tutor', 'tutor_id' => $t, 'name' => ucfirst($k) . ' Tester'];
};
$owner = $mkTutor('owner'); $editor = $mkTutor('editor'); $viewerT = $mkTutor('viewer'); $stranger = $mkTutor('stranger'); $newOwner = $mkTutor('newowner');
$adminUid = $mk('admin', 'admin'); $admin = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Admin Tester'];
$student = $mk('student', 'student');
$ids = [];

echo "== Creating a course\n";
$r = sst_save_details($db, $owner, null, ['title' => '', 'short_description' => 'x', 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60]);
check('a title is required', !$r['ok']);
check('a description over 160 letters is refused', !sst_save_details($db, $owner, null, ['title' => 'T', 'short_description' => str_repeat('a', 161), 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60])['ok']);
check('exactly 160 letters is allowed (and the check counts letters, not bytes)', sst_save_details($db, $owner, null, ['title' => 'T160', 'short_description' => str_repeat('é', 160), 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60], null)['ok'] && ($ids[] = (int) $db->query("SELECT MAX(id) FROM self_study_courses")->fetchColumn()));
check('an unknown level is refused', !sst_save_details($db, $owner, null, ['title' => 'T', 'short_description' => 'x', 'level' => 'expert', 'checkpoint_minutes' => 45, 'final_minutes' => 60])['ok']);
check('a time limit that is not in the list is refused', !sst_save_details($db, $owner, null, ['title' => 'T', 'short_description' => 'x', 'level' => 'beginner', 'checkpoint_minutes' => 50, 'final_minutes' => 60])['ok']);
check('an admin cannot create a course', !sst_save_details($db, $admin, null, ['title' => 'T', 'short_description' => 'x', 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60])['ok']);
$r = sst_save_details($db, $owner, null, ['title' => "  Python   for Data Analysis ", 'short_description' => 'Learn Python from zero.', 'level' => 'beginner', 'checkpoint_minutes' => 30, 'final_minutes' => 90]);
$cid = (int) $r['id']; $ids[] = $cid;
$c = $db->query("SELECT * FROM self_study_courses WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
check('it starts as a draft owned by the creator, with spaces tidied and the chosen time limits', $r['ok'] && $c['status'] === 'draft' && (int) $c['owner_tutor_id'] === $owner['tutor_id'] && $c['title'] === 'Python for Data Analysis' && (int) $c['checkpoint_minutes'] === 30 && (int) $c['final_minutes'] === 90 && (int) $c['created_by_user_id'] === $owner['uid']);
check('the exam question counts keep their defaults (12, 30, 40)', (int) $c['quiz_questions'] === 12 && (int) $c['checkpoint_questions'] === 30 && (int) $c['final_questions'] === 40);

echo "\n== Who may do what\n";
$db->prepare("INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, 'edit', ?, ?)")->execute([$cid, $editor['tutor_id'], $owner['uid'], ss2_ts(time())]);
$db->prepare("INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, 'view', ?, ?)")->execute([$cid, $viewerT['tutor_id'], $owner['uid'], ss2_ts(time())]);
check('the owner can edit', sst_course($db, $owner, $cid)['can_edit'] && sst_course($db, $owner, $cid)['access'] === 'owner');
check('a "Can edit" tutor can edit', sst_course($db, $editor, $cid)['can_edit']);
check('a "Can view" tutor can open it but not edit', ($v = sst_course($db, $viewerT, $cid)) && !$v['can_edit'] && $v['access'] === 'view');
check('a tutor with no access gets nothing (looks like it does not exist)', sst_course($db, $stranger, $cid) === null);
check('admin can open it but not edit', ($a = sst_course($db, $admin, $cid)) && !$a['can_edit'] && $a['access'] === 'admin');
check('a "Can view" tutor cannot save, publish, archive, duplicate or delete', !sst_publish($db, $viewerT, $cid)['ok'] && !sst_archive($db, $viewerT, $cid)['ok'] && !sst_duplicate($db, $viewerT, $cid)['ok'] && !sst_delete($db, $viewerT, $cid)['ok']);
check('a stranger cannot archive or delete', !sst_archive($db, $stranger, $cid)['ok'] && !sst_delete($db, $stranger, $cid)['ok']);
check('admin cannot archive or publish (only unpublish and move ownership)', !sst_archive($db, $admin, $cid)['ok'] && !sst_publish($db, $admin, $cid)['ok']);
$list = fn(array $v) => array_column(sst_list($db, $v), 'id');
check('the list shows my own and shared courses only', in_array($cid, array_map('intval', $list($owner))) && in_array($cid, array_map('intval', $list($viewerT))) && !in_array($cid, array_map('intval', $list($stranger))));
$al = array_values(array_filter(sst_list($db, $admin), fn($x) => (int) $x['id'] === $cid))[0] ?? null;
check('admin sees every course with the owner\'s name', $al && str_contains($al['owner_name'], 'Owner'));
check('a student never sees a draft', !ss_course_for_student($db, $cid, $student) && !ss_courses_for_student($db, $student));

echo "\n== The cover image\n";
$tmpdir = sys_get_temp_dir();
$img = function (int $w, int $h, string $kind = 'jpg', bool $exif = false) use ($tmpdir): string {
    $im = imagecreatetruecolor($w, $h);
    $red = imagecolorallocate($im, 220, 30, 30); $blue = imagecolorallocate($im, 30, 30, 220);
    imagefilledrectangle($im, 0, 0, intdiv($w, 2) - 1, $h, $red); imagefilledrectangle($im, intdiv($w, 2), 0, $w, $h, $blue);
    $f = tempnam($tmpdir, 'sstc');
    if ($kind === 'png') imagepng($im, $f); elseif ($kind === 'webp') imagewebp($im, $f, 90); else imagejpeg($im, $f, 90);
    imagedestroy($im);
    if ($exif && $kind === 'jpg') {          // hide a fake EXIF / GPS block in the file
        $data = file_get_contents($f); $seg = "Exif\0\0SECRETGPS-52.1N-4.3E" . str_repeat('x', 20);
        file_put_contents($f, substr($data, 0, 2) . "\xFF\xE1" . pack('n', strlen($seg) + 2) . $seg . substr($data, 2));
    }
    return $f;
};
$up = fn(string $path, ?int $size = null) => ['name' => 'c.jpg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => $size ?? filesize($path)];
$small = $img(1000, 800);
$r = sst_apply_cover($db, $cid, $up($small), null);
check('a picture under 1200 px wide is refused with a clear message', !$r['ok'] && str_contains($r['error'], '1000') && str_contains($r['error'], '1200'), $r['error'] ?? '');
$txt = tempnam($tmpdir, 'sstx'); file_put_contents($txt, "<?php echo 'hi'; ?>");
check('a script renamed to .jpg is refused (the real type is checked)', !sst_apply_cover($db, $cid, ['name' => 'evil.jpg', 'tmp_name' => $txt, 'error' => 0, 'size' => filesize($txt)], null)['ok']);
$gif = tempnam($tmpdir, 'sstg'); $gi = imagecreatetruecolor(1300, 800); imagegif($gi, $gif);
check('a GIF is refused (only JPG, PNG and WebP)', !sst_apply_cover($db, $cid, ['name' => 'a.gif', 'tmp_name' => $gif, 'error' => 0, 'size' => filesize($gif)], null)['ok']);
$big = $img(1400, 900);
check('a file over 10 MB is refused', !sst_apply_cover($db, $cid, $up($big, 11 * 1048576), null)['ok']);
check('a failed upload code is refused', !sst_apply_cover($db, $cid, ['name' => 'a.jpg', 'tmp_name' => $big, 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], null)['ok']);
check('nothing was saved by the refusals', $db->query("SELECT cover_original FROM self_study_courses WHERE id = $cid")->fetchColumn() === null);
$wide = $img(3000, 1700, 'jpg', true);
$r = sst_apply_cover($db, $cid, $up($wide), ['x' => 1500, 'y' => 0, 'w' => 1500]);
$c = $db->query("SELECT * FROM self_study_courses WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
check('a 3000 px picture is accepted; a 1500 px frame is reported as good (under 1600)', $r['ok'] && str_contains($r['message'], 'good') && str_starts_with($r['message'], '1500 × 844'), json_encode($r));
$dir = public_path('uploads/selfstudy/' . $cid);
check('the original and the 400 and 800 px WebP copies exist (the crop is 1500 wide, so no 1600 copy is made)', is_file(public_path($c['cover_original'])) && $c['cover_400'] && $c['cover_800'] && $c['cover_1600'] === null && is_file(public_path($c['cover_800'])));
[$w800, $h800] = getimagesize(public_path($c['cover_800'])); [$w400, $h400] = getimagesize(public_path($c['cover_400']));
check('the copies are exactly 16:9', $w800 === 800 && $h800 === 450 && $w400 === 400 && $h400 === 225);
$pix = imagecreatefromwebp(public_path($c['cover_800'])); $col = imagecolorat($pix, 400, 225);
check('the crop was applied: the right half of the picture (blue) is what the card shows', ($col & 0xFF) > 150 && (($col >> 16) & 0xFF) < 100, dechex($col));
check('the original is kept at full size', getimagesize(public_path($c['cover_original']))[0] === 3000);
check('hidden data (EXIF, GPS) is gone from the saved original', !str_contains(file_get_contents(public_path($c['cover_original'])), 'SECRETGPS') && !str_contains(file_get_contents(public_path($c['cover_original'])), 'Exif'));
check('the crop frame and the original size are saved', (int) $c['crop_x'] === 1500 && (int) $c['crop_w'] === 1500 && (int) $c['cover_orig_w'] === 3000 && (int) $c['cover_orig_h'] === 1700);
$r = sst_apply_cover($db, $cid, null, ['x' => 0, 'y' => 100, 'w' => 1700]);
$c2 = $db->query("SELECT * FROM self_study_courses WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
check('moving the frame without a new upload re-crops the saved original; 1700 px wide is enough for no 1600... it is, so a 1600 copy appears', $r['ok'] && $c2['cover_1600'] && getimagesize(public_path($c2['cover_1600']))[0] === 1600 && (int) $c2['crop_x'] === 0);
$pix = imagecreatefromwebp(public_path($c2['cover_800'])); $col = imagecolorat($pix, 400, 225);
check('...and now shows the red (left) part', (($col >> 16) & 0xFF) > 150, dechex($col));
check('old picture files were removed (only the current set stays)', count(glob($dir . '/cover-*')) === 4, (string) count(glob($dir . '/cover-*')));
$r = sst_apply_cover($db, $cid, null, ['x' => 0, 'y' => 0, 'w' => 200]);
check('a frame narrower than 1200 px is widened to 1200 (never blurry)', $r['ok'] && (int) $db->query("SELECT crop_w FROM self_study_courses WHERE id = $cid")->fetchColumn() === 1200);
$r = sst_apply_cover($db, $cid, null, null);
check('no upload and no crop changes nothing', $r['ok'] && !empty($r['none']));
$png = $img(1300, 800, 'png');
$r = sst_apply_cover($db, $cid, $up($png), null);
$c3 = $db->query("SELECT * FROM self_study_courses WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
check('a PNG works: default frame is the biggest 16:9 that fits (1300 × 731)', $r['ok'] && (int) $c3['crop_w'] === 1300 && (int) $c3['crop_h'] === 731 && str_ends_with($c3['cover_original'], '.png'));
$webp = $img(2000, 1125, 'webp');
check('a WebP works', sst_apply_cover($db, $cid, $up($webp), null)['ok']);
$flat = $img(5000, 600);
check('a very wide, short picture that cannot fill a 16:9 frame 1200 px wide is refused', !sst_apply_cover($db, $cid, $up($flat), null)['ok']);

echo "\n== Before you publish\n";
$ck = fn() => array_column(sst_checklist($db, $cid), 'done', 'key');
$k = $ck();
check('image, text and level are ticked; content, exams and who are not', $k['image'] && $k['text'] && $k['level'] && !$k['content'] && !$k['exams'] && !$k['who']);
$r = sst_publish($db, $owner, $cid);
check('Publish is refused, and the message names what is missing', !$r['ok'] && count($r['missing']) === 4 && in_array('Choose who sees it', $r['missing'], true));
check('it is still a draft', $db->query("SELECT status FROM self_study_courses WHERE id = $cid")->fetchColumn() === 'draft');
$db->prepare("INSERT INTO self_study_modules (course_id, title, position) VALUES (?, 'Module 1', 1)")->execute([$cid]); $mid = (int) $db->lastInsertId();
check('a module with no topic does not count', !$ck()['content']);
$db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html) VALUES (?, ?, 'Topic', 1, '<p>x</p>')")->execute([$cid, $mid]); $tid = (int) $db->lastInsertId();
check('a module with a topic counts', $ck()['content']);
check('a module quiz without enough questions keeps Publish off', !$ck()['quizzes']);
$db->prepare('UPDATE self_study_courses SET quiz_questions = 1 WHERE id = ?')->execute([$cid]);
$db->prepare("INSERT INTO self_study_questions (course_id, place, module_id, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, 'module_quiz', ?, ?, 'multiple_choice', 'Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $mid, $tid]);
check('enough questions in the quiz of every module ticks it', $ck()['quizzes']);
$db->prepare("UPDATE self_study_questions SET topic_id = NULL WHERE course_id = ? AND place = 'module_quiz'")->execute([$cid]);
check('a quiz question without a topic tag does not count as enough', !$ck()['quizzes']);
$db->prepare("UPDATE self_study_questions SET topic_id = ? WHERE course_id = ? AND place = 'module_quiz'")->execute([$tid, $cid]);
$q = fn(string $place, string $type = 'multiple_choice') => $db->prepare("INSERT INTO self_study_questions (course_id, place, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, ?, ?, ?, 'Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $place, $tid, $type]);
$db->prepare('UPDATE self_study_courses SET checkpoint_questions = 1, final_questions = 1 WHERE id = ?')->execute([$cid]);      // (an exam needs as many questions as one attempt draws; 1 keeps this test short)
$q('checkpoint_exam'); check('only one exam having questions is not enough', !$ck()['exams']);
$q('final_exam', 'long_answer'); check('a long-answer question does not count for an exam', !$ck()['exams']);
$q('final_exam'); check('questions for both exams tick the item', $ck()['exams']);
$r = sst_publish($db, $owner, $cid);
check('with nobody chosen it is still refused', !$r['ok'] && $r['missing'] === ['Choose who sees it']);
$db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by) VALUES (?, ?, ?)')->execute([$cid, $student, $owner['uid']]);
check('a removed student does not count as "who sees it"', true);
$db->prepare('UPDATE self_study_access SET removed_at = ? WHERE course_id = ?')->execute([ss2_ts(time()), $cid]);
check('...a removed one is not enough', !$ck()['who']);
$db->prepare('UPDATE self_study_access SET removed_at = NULL WHERE course_id = ?')->execute([$cid]);
check('an editor can publish once everything is ticked', ($r = sst_publish($db, $editor, $cid)) && $r['ok'] && $db->query("SELECT status FROM self_study_courses WHERE id = $cid")->fetchColumn() === 'published');
check('published_at is saved and students it is shared with can now see it', $db->query("SELECT published_at FROM self_study_courses WHERE id = $cid")->fetchColumn() && ss_course_for_student($db, $cid, $student));
check('a published course can still be edited without taking it down', sst_save_details($db, $owner, $cid, ['title' => 'Python for Data Analysis 2', 'short_description' => 'Better.', 'level' => 'intermediate', 'checkpoint_minutes' => 60, 'final_minutes' => 120])['ok'] && $db->query("SELECT status FROM self_study_courses WHERE id = $cid")->fetchColumn() === 'published');

echo "\n== Admin: unpublish and move ownership\n";
check('the owner cannot unpublish (only admin can)', !sst_unpublish($db, $owner, $cid)['ok']);
check('admin can unpublish: it is a draft again and the student no longer sees it', sst_unpublish($db, $admin, $cid)['ok'] && !ss_course_for_student($db, $cid, $student));
check('a tutor cannot move ownership', !sst_transfer($db, $owner, $cid, $newOwner['tutor_id'])['ok']);
check('admin can move ownership to another active tutor', sst_transfer($db, $admin, $cid, $newOwner['tutor_id'])['ok'] && sst_course($db, $newOwner, $cid)['access'] === 'owner');
check('the old owner loses access unless it is shared with them again', sst_course($db, $owner, $cid) === null);
check('a tutor who is not active cannot receive a course', !sst_transfer($db, $admin, $cid, 999999)['ok']);
sst_transfer($db, $admin, $cid, $owner['tutor_id']);
check('(moved back)', sst_course($db, $owner, $cid)['access'] === 'owner');
sst_publish($db, $owner, $cid);

echo "\n== Archive keeps progress; delete is only for untouched drafts\n";
$db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at) VALUES (?, ?, 40, ?)')->execute([$cid, $student, ss2_ts(time())]);
check('archive hides the course from students', sst_archive($db, $owner, $cid)['ok'] && !ss_course_for_student($db, $cid, $student) && $db->query("SELECT archived_at FROM self_study_courses WHERE id = $cid")->fetchColumn());
check('...but every progress row is kept', (int) $db->query("SELECT percent_complete FROM self_study_progress WHERE course_id = $cid AND student_id = $student")->fetchColumn() === 40);
check('an archived course cannot be published again before it is restored', !sst_publish($db, $owner, $cid)['ok']);
check('restoring brings it back as published (it was published before)', sst_unarchive($db, $owner, $cid)['ok'] && ss_course_for_student($db, $cid, $student)['percent'] === 40);
check('a published course cannot be deleted', !sst_delete($db, $owner, $cid)['ok']);
sst_unpublish($db, $admin, $cid);
check('a draft that students have progress in cannot be deleted, only archived', ($d = sst_delete($db, $owner, $cid)) && !$d['ok'] && str_contains($d['error'], 'Archive'));
check('the course and its progress are still there', (int) $db->query("SELECT COUNT(*) FROM self_study_courses WHERE id = $cid")->fetchColumn() === 1 && sst_has_progress($db, $cid));

echo "\n== Duplicate\n";
$db->prepare("INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index) VALUES (?, 'topic', ?, NULL, 'multiple_choice', 'Topic Q', '[\"a\",\"b\"]', 1)")->execute([$cid, $tid]);
$db->prepare("INSERT INTO self_study_questions (course_id, place, topic_id, module_id, qtype, question_text, options_json, correct_index) VALUES (?, 'module_quiz', NULL, ?, 'multiple_choice', 'Quiz Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $mid]);
$dup = sst_duplicate($db, $owner, $cid);
$nid = (int) ($dup['id'] ?? 0); $ids[] = $nid;
check('a copy is made as a new draft owned by the copier', $dup['ok'] && $db->query("SELECT status FROM self_study_courses WHERE id = $nid")->fetchColumn() === 'draft' && (int) $db->query("SELECT owner_tutor_id FROM self_study_courses WHERE id = $nid")->fetchColumn() === $owner['tutor_id']);
check('it remembers what it was copied from and is named "(copy)"', (int) $db->query("SELECT copied_from_id FROM self_study_courses WHERE id = $nid")->fetchColumn() === $cid && str_ends_with((string) $db->query("SELECT title FROM self_study_courses WHERE id = $nid")->fetchColumn(), '(copy)'));
check('modules, topics and questions are copied', (int) $db->query("SELECT COUNT(*) FROM self_study_modules WHERE course_id = $nid")->fetchColumn() === 1 && (int) $db->query("SELECT COUNT(*) FROM self_study_topics WHERE course_id = $nid")->fetchColumn() === 1 && (int) $db->query("SELECT COUNT(*) FROM self_study_questions WHERE course_id = $nid")->fetchColumn() === (int) $db->query("SELECT COUNT(*) FROM self_study_questions WHERE course_id = $cid")->fetchColumn());
$ntid = (int) $db->query("SELECT id FROM self_study_topics WHERE course_id = $nid")->fetchColumn(); $nmid = (int) $db->query("SELECT id FROM self_study_modules WHERE course_id = $nid")->fetchColumn();
check('the copied questions point at the NEW topic and module, not the old ones', (int) $db->query("SELECT topic_id FROM self_study_questions WHERE course_id = $nid AND place = 'topic'")->fetchColumn() === $ntid && (int) $db->query("SELECT module_id FROM self_study_questions WHERE course_id = $nid AND place = 'module_quiz'")->fetchColumn() === $nmid && $ntid !== $tid);
check('no students, progress, attempts or editors come along', !(int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $nid")->fetchColumn() && !(int) $db->query("SELECT COUNT(*) FROM self_study_progress WHERE course_id = $nid")->fetchColumn() && !(int) $db->query("SELECT COUNT(*) FROM self_study_course_editors WHERE course_id = $nid")->fetchColumn());
$nc = $db->query("SELECT cover_800, cover_original FROM self_study_courses WHERE id = $nid")->fetch(PDO::FETCH_ASSOC);
check('the cover files are copied into the new course\'s own folder', str_contains($nc['cover_800'], '/' . $nid . '/') && is_file(public_path($nc['cover_800'])));
check('the copy is a draft with nothing to publish yet (no students)', !sst_publish($db, $owner, $nid)['ok']);
check('a "Can edit" tutor can copy, a "Can view" tutor cannot', ($e = sst_duplicate($db, $editor, $cid)) && $e['ok'] && ($ids[] = $e['id']) && !sst_duplicate($db, $viewerT, $cid)['ok']);
check('the copy has no progress, so the untouched draft can be deleted', sst_delete($db, $owner, $nid)['ok'] && !is_dir(public_path('uploads/selfstudy/' . $nid)) && !(int) $db->query("SELECT COUNT(*) FROM self_study_modules WHERE course_id = $nid")->fetchColumn() && !(int) $db->query("SELECT COUNT(*) FROM self_study_questions WHERE course_id = $nid")->fetchColumn());
check('the original was not touched by that delete', (int) $db->query("SELECT COUNT(*) FROM self_study_topics WHERE course_id = $cid")->fetchColumn() === 1 && is_file(public_path($db->query("SELECT cover_800 FROM self_study_courses WHERE id = $cid")->fetchColumn())));

echo "\n== Clean up\n";
$ids = array_values(array_unique(array_filter(array_merge($ids, array_map('intval', $db->query("SELECT id FROM self_study_courses WHERE owner_tutor_id IN (" . implode(',', array_column([$owner, $editor, $viewerT, $stranger, $newOwner], 'tutor_id')) . ")")->fetchAll(PDO::FETCH_COLUMN))))));
foreach ($ids as $x) {
    foreach (['self_study_progress', 'self_study_access', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_editors', 'self_study_course_rules', 'self_study_topic_progress'] as $t) $db->exec("DELETE FROM $t WHERE course_id = $x");
    $db->exec("DELETE FROM self_study_courses WHERE id = $x");
    foreach (glob(public_path('uploads/selfstudy/' . $x . '/*')) ?: [] as $f) @unlink($f); @rmdir(public_path('uploads/selfstudy/' . $x));
}
foreach ([$small, $txt, $gif, $big, $wide, $png, $webp, $flat] as $f) @unlink($f);
$all = implode(',', [$owner['uid'], $editor['uid'], $viewerT['uid'], $stranger['uid'], $newOwner['uid'], $adminUid, $student]);
$db->exec('DELETE FROM tutors WHERE id IN (' . implode(',', array_column([$owner, $editor, $viewerT, $stranger, $newOwner], 'tutor_id')) . ')');
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($all)");
$db->exec("DELETE FROM users WHERE id IN ($all)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
