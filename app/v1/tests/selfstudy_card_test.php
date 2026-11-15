<?php
// Self-study (step 8): the "Self-study" section of the Student Progress card and its PDF. Read-only; the card's own status word, marks and average stay as they were.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_card_test.php     (a scratch database with every migration applied, 110 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyProgress.php';
if (!class_exists('Dompdf\Dompdf') && is_file(__DIR__ . '/../lib/dompdf/autoload.inc.php')) require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../helpers/progress_pdf.php';
require_once __DIR__ . '/../../../public/components/progress/selfstudy_section.php';

$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["ssc8_{$k}_$run", "ssc8_{$k}_$run@selfstudy-test.local", $pw, $roles[$role]]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, ucfirst($k), 'Tester']);
    return $i;
};
$mkTutor = function (string $k) use ($db, $mk): array {
    $u = $mk($k, 'tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$u]);
    return ['uid' => $u, 'role' => 'tutor', 'tutor_id' => (int) $db->lastInsertId(), 'name' => ucfirst($k) . ' Tester'];
};
$O = $mkTutor('owner'); $O2 = $mkTutor('other');
$adminUid = $mk('admin', 'admin'); $A = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Admin Tester'];
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 12, 100000, 1, 'diploma')")->execute(["Card Course $run", 'CC' . strtoupper(substr($run, 0, 4))]); $RC = (int) $db->lastInsertId();
$stu = function (string $k, ?array $tutor) use ($db, $mk, $run, $RC): int {
    $uid = $mk($k, 'student');
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T', ?)")->execute(['SC8-' . $k . $run, ucfirst($k) . ' Student', "$k$run@s.test", $RC, $uid, gmdate('Y-m-d H:i:s', time() - 100 * 86400)]);
    if ($tutor) $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tutor['tutor_id'], $uid, gmdate('Y-m-d', time() - 100 * 86400)]);
    return $uid;
};
$s = $stu('s', $O); $s2 = $stu('s2', $O); $plain = $stu('plain', $O);
$now = strtotime('2026-11-13 10:00:00 +03:00'); $day = 86400; $ts = fn($t) => gmdate('Y-m-d H:i:s', $t);
$course = function (string $title, array $owner, string $status = 'published') use ($db, $now, $ts): int {
    $db->prepare("INSERT INTO self_study_courses (owner_tutor_id, title, short_description, level, status, module_count, cover_400, created_at, updated_at) VALUES (?, ?, 'd', 'beginner', ?, 3, NULL, ?, ?)")->execute([$owner['tutor_id'], $title, $status, $ts($now), $ts($now)]);
    return (int) $db->lastInsertId();
};
$cA = $course("A Owned by O $run", $O); $cB = $course("B Private of O2 $run", $O2); $cC = $course("C Added by O $run", $O2); $cD = $course("D Shared view $run", $O2); $cF = $course("F Draft $run", $O, 'draft'); $cG = $course("G Removed $run", $O);
$acc = fn(int $c, int $st, ?array $by, ?string $removed = null) => $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by, added_by_tutor_id, added_at, removed_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$c, $st, $by ? $by['uid'] : $adminUid, $by ? $by['tutor_id'] : null, $ts($now - 20 * $day), $removed]);
$acc($cA, $s, $O); $acc($cB, $s, $O2); $acc($cC, $s, $O); $acc($cD, $s, $O2); $acc($cF, $s, $O); $acc($cG, $s, $O, $ts($now - $day));
$db->prepare("INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, 'view', ?, ?)")->execute([$cD, $O['tutor_id'], $O2['uid'], $ts($now)]);

// content of course A: 5 modules? keep it small: 3 modules, 1 topic each, a quiz each, exams
$mod = function (int $c, string $t, int $p) use ($db): int { $db->prepare('INSERT INTO self_study_modules (course_id, title, position) VALUES (?, ?, ?)')->execute([$c, $t, $p]); return (int) $db->lastInsertId(); };
$top = function (int $c, int $m, string $t, int $p) use ($db): int { $db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html) VALUES (?, ?, ?, ?, '<p>x</p>')")->execute([$c, $m, $t, $p]); return (int) $db->lastInsertId(); };
$qn = fn(int $c, string $place, ?int $m, ?int $t) => $db->prepare("INSERT INTO self_study_questions (course_id, place, module_id, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, ?, ?, ?, 'multiple_choice', 'Q', '[\"a\",\"b\"]', 0)")->execute([$c, $place, $m, $t]);
$m1 = $mod($cA, 'M1', 1); $m2 = $mod($cA, 'M2', 2); $m3 = $mod($cA, 'M3', 3);
$t1 = $top($cA, $m1, 'T1', 1); $t2 = $top($cA, $m2, 'T2', 2); $t3 = $top($cA, $m3, 'T3', 3);
foreach ([[$m1, $t1], [$m2, $t2], [$m3, $t3]] as [$m, $t]) $qn($cA, 'module_quiz', $m, $t);
$qn($cA, 'checkpoint_exam', null, $t1); $qn($cA, 'final_exam', null, $t1);
$qT2 = (int) $db->query("SELECT id FROM self_study_questions WHERE course_id = $cA AND module_id = $m2")->fetchColumn();
$att = function (int $st, int $c, string $kind, ?int $m, int $at, bool $pass, int $score) use ($db, $ts) {
    $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, submitted_at, score_percent, passed, status, question_ids) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'submitted', '[]')")->execute([$st, $c, $kind, $m, $ts($at), $ts($at + 300), $score, $pass ? 1 : 0]);
    return (int) $db->lastInsertId();
};
$prog = fn(int $st, int $c, int $pct, ?int $last, ?int $done = null) => $db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at, completed_at) VALUES (?, ?, ?, ?, ?)')->execute([$c, $st, $pct, $last ? $ts($last) : null, $done ? $ts($done) : null]);
// s on course A: passed M1 (88%), failed M2 twice in a row (wrong answers tagged with topic 1.0), studied yesterday
$prog($s, $cA, 40, $now - $day); $att($s, $cA, 'module_quiz', $m1, $now - 6 * $day, true, 88);
foreach ([2, 1] as $d) { $a = $att($s, $cA, 'module_quiz', $m2, $now - $d * $day, false, 30); $db->prepare('INSERT INTO self_study_attempt_answers (attempt_id, question_id, answer, is_correct) VALUES (?, ?, ?, 0)')->execute([$a, $qT2, '1']); }
$db->prepare('UPDATE self_study_questions SET topic_id = ? WHERE id = ?')->execute([$t2, $qT2]);
// s on B, C, D: not started (added 20 days ago); the progress rows of B..D do not exist
// s2 on course A: finished everything
$acc($cA, $s2, $O); $prog($s2, $cA, 100, $now - 2 * $day, $now - 2 * $day);
foreach ([[$m1, 80], [$m2, 90], [$m3, 100]] as [$m, $sc]) $att($s2, $cA, 'module_quiz', $m, $now - 8 * $day, true, $sc);
$att($s2, $cA, 'checkpoint_exam', null, $now - 5 * $day, true, 78); $att($s2, $cA, 'final_exam', null, $now - 3 * $day, true, 82);
$ids = [$s, $s2, $plain];
$names = fn(array $rows) => array_map(fn($r) => $r['course_id'], $rows);

echo "== Which courses each person sees\n";
$ca = ssd_cards($db, $A, $ids, $now);
check('admin sees every published course the student is on (A, B, C, D), not the draft, not the removed one', ($n = $names($ca[$s] ?? [])) && count($n) === 4 && !in_array($cF, $n) && !in_array($cG, $n) && in_array($cB, $n), json_encode($n));
$co = ssd_cards($db, $O, $ids, $now);
$n = $names($co[$s] ?? []);
check('a tutor sees the courses they own (A), were given access to (D) or added the student to (C)', in_array($cA, $n) && in_array($cD, $n) && in_array($cC, $n), json_encode($n));
check('a tutor never sees another tutor\'s private course (B)', !in_array($cB, $n) && count($n) === 3);
$co2 = ssd_cards($db, $O2, $ids, $now);
check('the other tutor sees B, C and D (their own) but not O\'s course A', ($n2 = $names($co2[$s] ?? [])) && in_array($cB, $n2) && !in_array($cA, $n2), json_encode($n2));
check('a student with no self-study course has no section', !isset($ca[$plain]) && !isset($co[$plain]));
check('students and staff get nothing, whatever ids they ask for', ssd_cards($db, ['role' => 'student', 'uid' => $s, 'tutor_id' => null, 'name' => 'x'], $ids, $now) === [] && ssd_cards($db, ['role' => 'employee', 'uid' => 1, 'tutor_id' => null, 'name' => 'x'], $ids, $now) === [] && ssd_cards($db, $O, [], $now) === []);
check('a draft course and a removed student are not listed for anyone', !in_array($cF, $names($ca[$s])) && !in_array($cG, $names($ca[$s])));

echo "\n== What each row shows\n";
$ra = array_values(array_filter($co[$s], fn($r) => $r['course_id'] === $cA))[0];
check('course title, "1 of 3 modules passed" and the percentage', $ra['title'] === "A Owned by O $run" && $ra['modules_passed'] === 1 && $ra['modules_total'] === 3 && $ra['percent'] === 40, json_encode($ra));
check('the score of the latest quiz (30% on the second try of Module 2)', $ra['latest_quiz'] === 30);
check('the exams line: "Not yet open" while a module is not passed', $ra['exams'] === 'Not yet open');
check('the comment is the Students page one: Slowing down, "Failed the Module 2 quiz 2 times", with the topic to read again', $ra['status']['key'] === 'slowing' && $ra['status']['word'] === 'Slowing down' && str_contains($ra['status']['reason'], 'Failed the Module 2 quiz 2 times') && str_contains($ra['status']['reason'], 'topic 2.0'), $ra['status']['reason']);
$rd = array_values(array_filter($co[$s], fn($r) => $r['course_id'] === $cD))[0];
check('a course never started shows "Not yet open" exams, 0 modules, "Not started yet" is not a warning after 20 days: it is Not working', $rd['modules_passed'] === 0 && $rd['latest_quiz'] === null && $rd['status']['key'] === 'notworking' && str_contains($rd['status']['reason'], 'Has not opened the course'));
$rs = array_values(array_filter($ca[$s2], fn($r) => $r['course_id'] === $cA))[0];
check('a finished student: "Checkpoint 78% · Final 82%", 100%, "Completed badge earned" (Working well)', $rs['exams'] === 'Checkpoint 78% · Final 82%' && $rs['percent'] === 100 && $rs['status']['word'] === 'Working well' && $rs['status']['reason'] === 'Completed badge earned.' && $rs['modules_passed'] === 3 && $rs['latest_quiz'] === 100, json_encode($rs));
$att($s, $cA, 'module_quiz', $m2, $now - 10, true, 70); $att($s, $cA, 'module_quiz', $m3, $now - 5, true, 60);
$rx = array_values(array_filter(ssd_cards($db, $O, [$s], $now)[$s], fn($r) => $r['course_id'] === $cA))[0];
check('all modules passed and no exam yet: "Checkpoint not yet taken"', $rx['exams'] === 'Checkpoint not yet taken' && $rx['modules_passed'] === 3);
$att($s, $cA, 'checkpoint_exam', null, $now - 3, false, 45);
check('a failed checkpoint is shown with its score', array_values(array_filter(ssd_cards($db, $O, [$s], $now)[$s], fn($r) => $r['course_id'] === $cA))[0]['exams'] === 'Checkpoint 45% (not passed yet)');
check('"can open": the owner and a tutor with access can open the Students page; a tutor who only added the student cannot', $ra['can_open'] === true && $rd['can_open'] === true && array_values(array_filter($co[$s], fn($r) => $r['course_id'] === $cC))[0]['can_open'] === false);

echo "\n== It changes nothing\n";
$sp = sp_students($db, $A, ['ids' => [$s]]);
$before = sp_compute($db, $sp, true, $now)[$s];
$cnt = fn() => [(int) $db->query('SELECT COUNT(*) FROM self_study_progress')->fetchColumn(), (int) $db->query('SELECT COUNT(*) FROM self_study_attempts')->fetchColumn(), (int) $db->query('SELECT COUNT(*) FROM self_study_resets')->fetchColumn()];
$c0 = $cnt(); ssd_cards($db, $A, $ids, $now); ssd_cards($db, $O, $ids, $now);
check('reading the section writes nothing', $cnt() === $c0);
$after = sp_compute($db, $sp, true, $now)[$s];
check('the card\'s own status word, marks, average and reasons are exactly as before', $before['status'] === $after['status'] && $before['average'] === $after['average'] && $before['handed'] === $after['handed'] && $before['actual_pct'] === $after['actual_pct']);

echo "\n== Many students at once (the PDF for everyone)\n";
$many = []; for ($i = 0; $i < 30; $i++) { $u = $stu('m' . $i, $O); $many[] = $u; $acc($cA, $u, $O); $prog($u, $cA, $i, $now - ($i % 5) * $day); }
$cm = ssd_cards($db, $O, $many, $now);
check('30 students come back from one call, each with their own row', count($cm) === 30 && count(array_filter($cm, fn($r) => count($r) === 1)) === 30 && $cm[$many[7]][0]['percent'] === 7);
check('the status of each follows their own last-studied day', $cm[$many[0]][0]['status']['key'] === 'working' && $cm[$many[3]][0]['status']['key'] === 'working');

echo "\n== The section on the screen\n";
check('no courses: the section is hidden (empty string)', sp_selfstudy_html([], $plain, 'tutor') === '');
$_SERVER['HTTP_HOST'] = 'localhost';
$h = sp_selfstudy_html($co[$s], $s, 'tutor');
check('it has the title, the NEW tag and the note', str_contains($h, 'Self-study') && str_contains($h, 'NEW') && str_contains($h, 'Shown here only. It does not change marks or the course average.'));
check('each row shows the modules, bar, latest quiz, exams and the comment word + reason', str_contains($h, '1 of 3 modules passed') && str_contains($h, 'Slowing down') && str_contains($h, 'Not yet open') && str_contains($h, 'Failed the Module 2 quiz'));
check('a row opens the student on the Students page; a row the tutor cannot open has no link', str_contains($h, '/tutor/selfstudy/students?id=' . $cA . '&amp;open=' . $s) && substr_count($h, '<a class="sps-row"') === 2 && substr_count($h, '<div class="sps-row">') === 1, substr_count($h, '<a class="sps-row"') . '/' . substr_count($h, '<div class="sps-row">') . ' ' . (preg_match('#href="([^"]*students[^"]*)"#', $h, $mm) ? $mm[1] : 'nolink'));
check('titles are escaped', !str_contains(sp_selfstudy_html([['course_id' => 1, 'title' => '<script>x</script>', 'cover' => ['cover_original' => null, 'cover_400' => null, 'cover_800' => null, 'cover_1600' => null], 'modules_passed' => 0, 'modules_total' => 1, 'percent' => 0, 'latest_quiz' => null, 'exams' => 'Not yet open', 'status' => ['key' => 'working', 'word' => 'Working well', 'reason' => 'x'], 'can_open' => false]], 1, 'tutor'), '<script>x'));

echo "\n== The PDF\n";
check('the PDF section: title, NEW, the note, a row with word and reason; empty when no course', spp_selfstudy_html([]) === '' && ($p = spp_selfstudy_html($co[$s])) && str_contains($p, 'SELF-STUDY') && str_contains($p, 'Shown here only. It does not change marks or the course average.') && str_contains($p, 'Slowing down') && str_contains($p, 'Failed the Module 2 quiz'));
$note = 'PRIVATE-NOTE-' . $run;
$db->prepare('INSERT INTO progress_notes (student_user_id, author_user_id, author_name, note, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$s, $O['uid'], 'Owner Tester', $note, $ts($now)]);
$students = sp_students($db, $A, ['ids' => [$s]]); $card = sp_compute($db, $students, true, $now)[$s];
$cardHtml = spp_card_html($card, null, '13 November 2026', 'Admin Tester', true, $ca[$s]);
check('the card page of the PDF includes the section and still has the usual parts', str_contains($cardHtml, 'SELF-STUDY') && str_contains($cardHtml, 'SUBJECTS') && str_contains($cardHtml, 'STUDENT PROGRESS CARD'));
check('private tutor notes stay out of the PDF', !str_contains($cardHtml, $note) && !str_contains($cardHtml, 'Owner Tester'));
check('a student without self-study courses gets the same card as before (no section)', !str_contains(spp_card_html(sp_compute($db, sp_students($db, $A, ['ids' => [$plain]]), true, $now)[$plain], null, 'x', 'y', true, []), 'SELF-STUDY'));
try { $bytes = sp_pdf_build($db, $A, [$s, $s2, $plain], $now); check('one PDF for several students builds (one batch, the sections included)', str_starts_with($bytes, '%PDF') && strlen($bytes) > 3000); }
catch (Throwable $e) { check('one PDF for several students builds', false, $e->getMessage()); }
try { $b1 = sp_pdf_build($db, $O, [$s], $now); check('the PDF for one student built as a tutor works', str_starts_with($b1, '%PDF')); } catch (Throwable $e) { check('the PDF for one student as a tutor', false, $e->getMessage()); }

echo "\n== Clean up\n";
$cids = "$cA, $cB, $cC, $cD, $cF, $cG";
foreach (['self_study_attempt_answers' => "attempt_id IN (SELECT id FROM self_study_attempts WHERE course_id IN ($cids))", 'self_study_attempts' => "course_id IN ($cids)", 'self_study_progress' => "course_id IN ($cids)", 'self_study_access' => "course_id IN ($cids)",
         'self_study_questions' => "course_id IN ($cids)", 'self_study_topics' => "course_id IN ($cids)", 'self_study_modules' => "course_id IN ($cids)", 'self_study_course_editors' => "course_id IN ($cids)", 'self_study_courses' => "id IN ($cids)"] as $t => $w) $db->exec("DELETE FROM $t WHERE $w");
$allU = implode(',', array_merge($ids, $many, [$O['uid'], $O2['uid'], $adminUid]));
$db->exec("DELETE FROM progress_notes WHERE student_user_id IN ($allU)");
$db->exec("DELETE FROM tutor_students WHERE user_id IN ($allU)"); $db->exec("DELETE FROM registrations WHERE user_id IN ($allU)");
$db->exec('DELETE FROM tutors WHERE id IN (' . $O['tutor_id'] . ', ' . $O2['tutor_id'] . ')');
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($allU)"); $db->exec("DELETE FROM users WHERE id IN ($allU)");
$db->exec("DELETE FROM courses WHERE id = $RC");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
