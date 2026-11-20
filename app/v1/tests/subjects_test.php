<?php
// Subjects for assignments: the automatic subject mark, ownership, removal rules, the audit log, and the admin / no-typed-mark rules.
//   DAZU_DB_NAME=<scratch db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=] php app/v1/tests/subjects_test.php
// Uses a scratch database that has all migrations applied. Writes the logins it made to <temp>/subjects_users.json for tests/subjects_http.sh.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SubjectService.php';

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
function near($a, $b, float $eps = 0.011): bool { return $a !== null && abs((float) $a - (float) $b) <= $eps; }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$logins = [];
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run, &$logins): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["s_$key$run", "$key$run@s.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    $logins[$key] = ['id' => $id, 'email' => "$key$run@s.test"];
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
$mkcourse = function (string $title, string $abbr) use ($db): int {
    $db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 12, 100000, 1, 'diploma')")->execute([$title, $abbr]);
    return (int) $db->lastInsertId();
};
$course = $mkcourse('Data Science ' . $run, 'DS' . strtoupper(substr($run, 0, 4)));
$plain = $mkcourse('Plain Course ' . $run, 'PL' . strtoupper(substr($run, 0, 4)));
$mkstudent = function (string $key, int $courseId, int $tutorId) use ($db, $mkuser, $run): int {
    $uid = $mkuser($key, 'student', 'Stu', ucfirst($key));
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T')")->execute(['S' . $key . $run, 'Stu ' . ucfirst($key), "$key$run@s.test", $courseId, $uid]);
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$tutorId, $uid]);
    return $uid;
};
$s1 = $mkstudent('ann', $course, $tId);
$s2 = $mkstudent('bob', $course, $tId);
$sPlain = $mkstudent('pat', $plain, $tId);
$sCat = $mkstudent('cat', $course, $t2Id);   // a student of the other tutor, so that tutor also teaches this course
$db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$course, $tId]);
$db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$plain, $tId]);

/** One assignment for a tutor and, if $score is not null, a released mark for a student. Returns the assignment id. */
$mk = function (int $tutorId, int $courseId, ?int $subjectId, string $kind, float $weight, ?float $score, array $o = []) use ($db, $tUid, &$order): int {
    $order = ($order ?? 0) + 1;
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, subject_id, title, kind, weight, status, max_score, grading_mode, sort_order, due_at, release_grades_at, is_template, audience)
                  VALUES (?, ?, ?, ?, ?, ?, 'published', 100, ?, ?, ?, ?, 0, 'selected')")
       ->execute([$tutorId, $courseId, $subjectId, ($o['title'] ?? ucfirst($kind)) . " $order", $kind, $weight, $o['mode'] ?? 'marks', $order, gmdate('Y-m-d H:i:s', time() - 86400), $o['release_at'] ?? null]);
    $id = (int) $db->lastInsertId();
    foreach ((array) ($o['students'] ?? []) as $su) {
        $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at) VALUES (?, ?, ?)')->execute([$id, $su, gmdate('Y-m-d H:i:s')]);
    }
    return $id;
};
$mark = function (int $aid, int $uid, float $score, array $o = []) use ($db): void {
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, is_late, pass_result)
                  VALUES (?, ?, 'graded', ?, ?, ?, ?, ?)")
       ->execute([$aid, $uid, gmdate('Y-m-d H:i:s', time() - 7200), $score, $o['published'] ?? gmdate('Y-m-d H:i:s', time() - 3600), (int) ($o['late'] ?? 0), $o['pass'] ?? null]);
};
$find = function (array $marks, int $sid): ?array { foreach ($marks as $m) if ($m['subject_id'] === $sid) return $m; return null; };

/* ------------------------------------------------------------------ */
section('Subjects: create, validate, default weight');
$py = subj_create($db, $tId, $tUid, $course, ['name' => 'Python Programming', 'code' => 'ds-02', 'monthly_expected' => 2]);
check('create the subject Python with 2 monthly projects expected', $py['ok'] ?? false, json_encode($py));
$py = (int) $py['id'];
$row = subj_get_own($db, $py, $tId);
check('the short code is saved in capitals and monthly projects expected is 2', $row['code'] === 'DS-02' && (int) $row['monthly_expected'] === 2);
$sql = subj_create($db, $tId, $tUid, $course, ['name' => 'Databases and SQL', 'monthly_expected' => 1]); $sql = (int) $sql['id'];
$ml = subj_create($db, $tId, $tUid, $course, ['name' => 'Machine Learning', 'monthly_expected' => 1]); $ml = (int) $ml['id'];
$w = subj_effective_weights($db, $course, $tId);
check('no capstone: three subjects with no weight share 100 equally (33.33 each)', near($w[$py], 33.33) && near($w[$sql], 33.33) && near($w[$ml], 33.33), json_encode($w));
subj_update($db, $tId, $tUid, $ml, ['name' => 'Machine Learning', 'weight' => '40', 'monthly_expected' => 1]);
$w = subj_effective_weights($db, $course, $tId);
check('a weight the tutor set (40) is kept and the others share what is left (30 each)', near($w[$ml], 40) && near($w[$py], 30) && near($w[$sql], 30), json_encode($w));
$db->prepare("INSERT INTO capstones (tutor_id, course_id, title, weight) VALUES (?, ?, 'Cap', 20)")->execute([$tId, $course]);
$w = subj_effective_weights($db, $course, $tId);
check('with a capstone of 20, the default is an equal share of what is left after the capstone and the weights set (20 each)', near($w[$py], 20) && near($w[$sql], 20) && near($w[$ml], 40), json_encode($w));
$db->exec("DELETE FROM capstones WHERE course_id = $course");
subj_update($db, $tId, $tUid, $ml, ['name' => 'Machine Learning', 'weight' => '', 'monthly_expected' => 1]);
check('an empty weight goes back to the default', near(subj_effective_weights($db, $course, $tId)[$ml], 33.33));
$bad = [subj_create($db, $tId, $tUid, $course, ['name' => '', 'monthly_expected' => 1]), subj_create($db, $tId, $tUid, $course, ['name' => 'X', 'monthly_expected' => 0]),
        subj_create($db, $tId, $tUid, $course, ['name' => 'X', 'weight' => '150', 'monthly_expected' => 1]), subj_create($db, $tId, $tUid, $course, ['name' => 'python programming', 'monthly_expected' => 1])];
check('empty name, 0 monthly projects, weight 150 and a duplicate name are all refused', !$bad[0]['ok'] && !$bad[1]['ok'] && !$bad[2]['ok'] && !$bad[3]['ok']);

/* ------------------------------------------------------------------ */
section('The automatic subject mark (Python: 5 assignments + 2 monthly projects)');
$for = [$s1, $s2];
$py5 = [];
foreach ([80, 60, 70, 90, 50] as $sc) { $a = $mk($tId, $course, $py, 'daily', 1, null, ['students' => $for]); $mark($a, $s1, $sc); $py5[] = $a; }
$m1 = $mk($tId, $course, $py, 'monthly', 3, null, ['students' => $for]); $mark($m1, $s1, 70);
$before = $find(subjectMarks($db, $s1, $course), $py);
$expect = (350 + 3 * 70) / 8;
check('before the second monthly project is released the status is In progress', $before['status'] === 'In progress', $before['status']);
check('...and the mark so far is the weighted average of the released marks (70)', near($before['mark'], $expect), (string) $before['mark']);
check('...5 assignments and 1 of 2 monthly projects are marked', $before['assignments_marked'] === 5 && $before['monthly_marked'] === 1 && $before['monthly_expected'] === 2);
$m2 = $mk($tId, $course, $py, 'monthly', 3, null, ['students' => $for]); $mark($m2, $s1, 90);
$after = $find(subjectMarks($db, $s1, $course), $py);
$expect = (350 + 3 * 70 + 3 * 90) / 11;
check('after the second monthly project is released the status is Final', $after['status'] === 'Final', $after['status']);
check('...and the subject mark equals the weighted average of the released marks (75.45)', near($after['mark'], $expect), (string) $after['mark']);
check('...result word Distinction (70 and above)', $after['word'] === 'Distinction');
$svc = AssignmentAverage::compute(array_values(array_filter(AssignmentAverage::rowsForStudent($db, $s1), fn($r) => (int) ($r['subject_id'] ?? 0) === $py)));
check('the number is the existing course average service\'s number for those items (one copy of the maths)', near($svc['average'], $after['mark']));
$bobBefore = $find(subjectMarks($db, $s2, $course), $py);
check('a student with no marks yet is Not yet, with no mark', $bobBefore['status'] === 'Not yet' && $bobBefore['mark'] === null && $bobBefore['word'] === null);

section('Held, missing, resubmission, Pass/Not yet, late');
$held = $mk($tId, $course, $py, 'daily', 1, null, ['students' => $for, 'release_at' => gmdate('Y-m-d H:i:s', time() + 86400)]); $mark($held, $s1, 0);
$a1 = $find(subjectMarks($db, $s1, $course), $py);
check('a held (unreleased) mark does not count (mark unchanged)', near($a1['mark'], $after['mark']) && $a1['assignments_marked'] === 5, (string) $a1['mark']);
$db->prepare('UPDATE tutor_assignments SET release_grades_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 60), $held]);
$a2 = $find(subjectMarks($db, $s1, $course), $py);
check('once released, the same mark counts (mark falls)', $a2['mark'] < $after['mark'] && $a2['assignments_marked'] === 6, (string) $a2['mark']);
$db->prepare('DELETE FROM tutor_assignment_submissions WHERE assignment_id = ?')->execute([$held]);
$miss = $mk($tId, $course, $py, 'daily', 1, null, ['students' => $for]);   // past due, no submission
$a3 = $find(subjectMarks($db, $s1, $course), $py);
check('missing work is not counted as zero', near($a3['mark'], $after['mark']), (string) $a3['mark']);
$db->prepare('UPDATE tutor_assignment_submissions SET score = 100 WHERE assignment_id = ? AND student_user_id = ?')->execute([$py5[1], $s1]);
$a4 = $find(subjectMarks($db, $s1, $course), $py);
check('a resubmission uses the newest mark (60 became 100: the subject mark rises by 40/11)', near($a4['mark'], $after['mark'] + 40 / 11), (string) $a4['mark']);
$pn = $mk($tId, $course, $py, 'daily', 1, null, ['students' => $for, 'mode' => 'pass']); $mark($pn, $s1, 0, ['pass' => 'pass']);
$a5 = $find(subjectMarks($db, $s1, $course), $py);
check('a Pass/Not yet item is not counted in the mark', near($a5['mark'], $a4['mark']), (string) $a5['mark']);
$late = $mk($tId, $course, $py, 'daily', 1, null, ['students' => $for]); $mark($late, $s1, 100, ['late' => 1]);
$a6 = $find(subjectMarks($db, $s1, $course), $py);
check('late work is counted without any reduction (100 counted as 100)', $a6['mark'] > $a5['mark'] && near($a6['mark'], ($a5['mark'] * 11 + 100) / 12, 0.05), (string) $a6['mark']);
$db->prepare('UPDATE tutor_assignment_submissions SET score = 20 WHERE assignment_id = ? AND student_user_id = ?')->execute([$late, $s1]);
$fail = $mk($tId, $course, $sql, 'daily', 1, null, ['students' => $for]); $mark($fail, $s1, 45);
$sm = $find(subjectMarks($db, $s1, $course), $sql);
check('a mark of 45 gives the result word Failed and passed = false', $sm['word'] === 'Failed' && $sm['passed'] === false);
check('result words: 70 Distinction, 69 Credit, 60 Credit, 59 Pass, 50 Pass, 49 Failed', [subj_result_word(70), subj_result_word(69), subj_result_word(60), subj_result_word(59), subj_result_word(50), subj_result_word(49)] === ['Distinction', 'Credit', 'Credit', 'Pass', 'Pass', 'Failed']);

section('As at a time (for a transcript that is already released)');
$tNow = time();
$old = $find(subjectMarks($db, $s1, $course, $tNow - 5 * 3600), $py);
check('the service returns the marks as at an earlier time (nothing was released then)', $old['mark'] === null && $old['status'] === 'Not yet');
$mid = $find(subjectMarks($db, $s2, $course), $py);
check('a time argument of null means now', $a6['mark'] !== null && $mid['mark'] === null);

section('Course average and courses with no subjects');
$expectAvg = AssignmentAverage::forStudent($db, $s1)[$course]['average'];
$bak = $db->query("SELECT id, subject_id FROM tutor_assignments WHERE subject_id IS NOT NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
$db->exec('UPDATE tutor_assignments SET subject_id = NULL');
$noSubj = AssignmentAverage::forStudent($db, $s1)[$course]['average'];
foreach ($bak as $id => $sid) $db->prepare('UPDATE tutor_assignments SET subject_id = ? WHERE id = ?')->execute([$sid, $id]);
check('the course average is the same with and without subjects on the assignments', near($expectAvg, $noSubj, 0.0001), "$expectAvg vs $noSubj");
$pa = $mk($tId, $plain, null, 'daily', 1, null, ['students' => [$sPlain]]); $mark($pa, $sPlain, 64);
$pb = $mk($tId, $plain, null, 'weekly', 2, null, ['students' => [$sPlain]]); $mark($pb, $sPlain, 82);
$pAvg = AssignmentAverage::forStudent($db, $sPlain)[$plain];
check('a course with no subjects: the average is exactly as before ((64 + 2x82) / 3 = 76)', near($pAvg['average'], (64 + 164) / 3, 0.0001), (string) $pAvg['average']);
check('...subjectMarks returns nothing for it', subjectMarks($db, $sPlain, $plain) === []);
check('...no subject is needed there', subj_check_choice($db, $plain, $tId, 0) === null && !subj_required($db, $plain, $tId));
check('in a course with subjects, no subject is refused with a clear message', (string) subj_check_choice($db, $course, $tId, 0) !== '' && stripos((string) subj_check_choice($db, $course, $tId, 0), 'Choose a subject') === 0);

section('Remove, rename, inactive, reorder, audit log');
$rm = subj_remove($db, $tId, $tUid, $py);
check('a subject used by an assignment cannot be removed (with a clear message)', !$rm['ok'] && strpos($rm['error'], 'cannot be removed') !== false, json_encode($rm));
check('...it still exists', subj_get_own($db, $py, $tId) !== null);
$markBeforeRename = $find(subjectMarks($db, $s1, $course), $py)['mark'];
$rn = subj_update($db, $tId, $tUid, $py, ['name' => 'Python Programming 101', 'code' => 'DS-02', 'monthly_expected' => 2]);
check('...but it can be renamed', $rn['ok'] && subj_get_own($db, $py, $tId)['name'] === 'Python Programming 101');
check('...and the marks under it are unchanged by the rename', near($find(subjectMarks($db, $s1, $course), $py)['mark'], $markBeforeRename, 0.0001) && $find(subjectMarks($db, $s1, $course), $py)['name'] === 'Python Programming 101');
subj_set_active($db, $tId, $tUid, $py, false);
$inactive = $find(subjectMarks($db, $s1, $course), $py);
check('...and set inactive (its marks are still returned)', $inactive !== null && $inactive['active'] === false && $inactive['mark'] !== null);
check('an inactive subject cannot be chosen on a new assignment', subj_check_choice($db, $course, $tId, $py, 0) !== null && strpos((string) subj_check_choice($db, $course, $tId, $py, 0), 'inactive') !== false);
check('...but an assignment that already has it can keep it', subj_check_choice($db, $course, $tId, $py, $py) === null);
subj_set_active($db, $tId, $tUid, $py, true);
$tmp = (int) subj_create($db, $tId, $tUid, $course, ['name' => 'Scratch subject', 'monthly_expected' => 1])['id'];
check('a subject that no assignment uses can be removed', subj_remove($db, $tId, $tUid, $tmp)['ok'] && subj_get_own($db, $tmp, $tId) === null);
$ids = array_map(fn($r) => (int) $r['id'], subj_list($db, $course, $tId));
subj_move($db, $tId, $tUid, $ml, -1);
$ids2 = array_map(fn($r) => (int) $r['id'], subj_list($db, $course, $tId));
check('reorder moves a subject up one place', $ids2 === [$ids[0], $ml, $ids[1]], json_encode([$ids, $ids2]));
// Move an assignment to another subject.
$db->prepare('UPDATE tutor_assignments SET subject_id = ? WHERE id = ?')->execute([$sql, $py5[0]]);
subj_log_assignment_change($db, $py5[0], $course, $tUid, $py, $sql);
$moved = subjectMarks($db, $s1, $course);
check('moving an assignment to another subject updates both subject marks at once', $find($moved, $sql)['assignments_marked'] === 2 && $find($moved, $py)['assignments_marked'] === 6, json_encode([$find($moved, $sql)['assignments_marked'], $find($moved, $py)['assignments_marked']]));
$ev = $db->query("SELECT event, COUNT(*) FROM course_subject_events WHERE course_id = $course GROUP BY event")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['subject_created', 'subject_renamed', 'subject_changed', 'subject_inactive', 'subject_activated', 'subject_reordered', 'subject_removed', 'assignment_subject_changed'] as $e) {
    check("audit log has '$e'", !empty($ev[$e]), json_encode($ev));
}
$last = $db->query("SELECT details FROM course_subject_events WHERE event = 'assignment_subject_changed' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('the audit entry for a moved assignment says from which subject to which', json_decode($last, true) === ['from_subject_id' => $py, 'to_subject_id' => $sql]);

section('Another tutor cannot touch it');
$t2course = $db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)'); $t2course->execute([$course, $t2Id]);
check('another tutor cannot open the subject', subj_get_own($db, $py, $t2Id) === null);
check('...rename it', !subj_update($db, $t2Id, $t2Uid, $py, ['name' => 'Hacked', 'monthly_expected' => 1])['ok'] && subj_get_own($db, $py, $tId)['name'] === 'Python Programming 101');
check('...set it inactive', !subj_set_active($db, $t2Id, $t2Uid, $py, false)['ok'] && (int) subj_get_own($db, $py, $tId)['is_active'] === 1);
check('...remove it', !subj_remove($db, $t2Id, $t2Uid, $tmp + 0)['ok'] && !subj_remove($db, $t2Id, $t2Uid, $ml)['ok'] && subj_get_own($db, $ml, $tId) !== null);
check('...reorder it', !subj_move($db, $t2Id, $t2Uid, $ml, 1)['ok']);
check('...and a subject of another tutor cannot be put on its own assignment', subj_check_choice($db, $course, $t2Id, $py, 0) === 'That subject is not one of yours in this course.');
check('...their list of subjects for the course is empty', subj_list($db, $course, $t2Id) === []);
check('a subject cannot be used in another course', subj_check_choice($db, $plain, $tId, $py, 0) === 'That subject is not one of yours in this course.');

section('Nobody can type a subject mark; the admin never reads one');
$cols = $db->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'course_subjects'")->fetchAll(PDO::FETCH_COLUMN);
check('the subjects table has no column that could hold a mark, average or result', !array_filter($cols, fn($c) => preg_match('/mark|score|average|result|grade|percent/i', $c)), implode(',', $cols));
$root = realpath(__DIR__ . '/../../..');
$bad = [];
foreach (['public/subject_mark', 'public/admin', 'public/superadmin', 'public/students', 'public/templates/admin'] as $dir) {
    if (!is_dir("$root/$dir")) continue;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() !== 'php') continue;
        // The admin's Course subjects page sets subjects up (names, months); it never reads or types a mark.
        if (str_ends_with($f->getPathname(), 'admin/courses/subjects.php')) continue;
        $src = file_get_contents($f->getPathname());
        if (preg_match('/subjectMarks|SubjectService|subj_[a-z_]+\(|course_subjects|course_subject_events/', $src)) $bad[] = str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname());
    }
}
check('no admin, superadmin or student page uses the subject service or tables', !$bad, implode(', ', $bad));
$setters = [];
foreach (['app/v1', 'public'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->getExtension() !== 'php' || strpos($f->getPathname(), 'vendor') !== false || strpos($f->getPathname(), 'tests') !== false) continue;
        $src = file_get_contents($f->getPathname());
        if (preg_match('/UPDATE\s+course_subjects\s+SET[^;]*(mark|score|average|result)/i', $src) || preg_match('/INSERT\s+INTO\s+course_subjects[^;]*(mark|score|average)/i', $src)) $setters[] = $f->getPathname();
    }
}
check('no code writes a mark, score, average or result into the subjects table', !$setters, implode(', ', $setters));
$page = file_get_contents("$root/public/tutor/assignments/subjects.php");
check('the Subjects page has no field named mark, score or average', !preg_match('/name="(mark|score|average|subject_mark)/', $page));
check('every Subjects page form has the CSRF field and checks it', substr_count($page, 'csrf_field()') === 2 && strpos($page, 'csrf_verify') !== false);

section('Old assignments (no subject) are left alone');
$subjBefore = array_map(fn($m) => [$m['subject_id'], $m['mark'], $m['assignments_total']], subjectMarks($db, $s1, $course));
$oldA = $mk($tId, $course, null, 'daily', 1, null, ['students' => $for, 'title' => 'Old unsubjected']); $mark($oldA, $s1, 55);
check('NEW work with no subject is refused in a course with subjects', subj_check_choice($db, $course, $tId, 0, 0, true) !== null);
check('EDITING an old assignment that has no subject, without choosing one, is allowed', subj_check_choice($db, $course, $tId, 0, 0, false) === null);
check('an assignment that already has a subject cannot be set back to none', subj_check_choice($db, $course, $tId, 0, $py, false) !== null);
$subjAfter = array_map(fn($m) => [$m['subject_id'], $m['mark'], $m['assignments_total']], subjectMarks($db, $s1, $course));
check('an old assignment with no subject is in no subject mark (every subject mark and count is unchanged)', $subjAfter === $subjBefore);
$avgWithOld = AssignmentAverage::forStudent($db, $s1)[$course];
check('...but it still counts in the existing course average', in_array($oldA, array_column($avgWithOld['items'], 'id'), true));
check('...with its 55% as before', (function () use ($avgWithOld, $oldA) { foreach ($avgWithOld['items'] as $it) if ($it['id'] === $oldA) return near($it['percent'], 55) && $it['counts']; return false; })());

file_put_contents((getenv('TEMP') ?: sys_get_temp_dir()) . '/subjects_users.json', json_encode($logins + ['_run' => $run, '_course' => $course, '_plain' => $plain, '_py' => $py, '_sql' => $sql, '_ml' => $ml, '_assign' => $py5[2], '_s1' => $s1, '_old' => $oldA]));
echo "\n" . ($fails ? "$fails of $n checks FAILED\n" : "All $n passed\n");
exit($fails ? 1 : 0);
