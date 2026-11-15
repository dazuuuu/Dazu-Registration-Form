<?php
// Self-study (step 6): who sees a course (students) and who it is shared with (tutors).
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_access_test.php     (a scratch database with every migration applied, 109 included)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyAccess.php';

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
if (!ssa_installed($db)) { fwrite(STDERR, "Apply migration 109 first.\n"); exit(2); }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $k, string $role, ?string $fn = null) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["ssx_{$k}_$run", "ssx_{$k}_$run@selfstudy-test.local", $pw, $roles[$role], ]);
    $i = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$i, $fn ?? ucfirst($k), 'Tester']);
    return $i;
};
$mkTutor = function (string $k) use ($db, $mk): array {
    $u = $mk($k, 'tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$u]);
    return ['uid' => $u, 'role' => 'tutor', 'tutor_id' => (int) $db->lastInsertId(), 'name' => ucfirst($k) . ' Tester'];
};
$O = $mkTutor('owner'); $E = $mkTutor('editor'); $V = $mkTutor('viewer'); $S = $mkTutor('stranger');
$adminUid = $mk('admin', 'admin'); $A = ['uid' => $adminUid, 'role' => 'admin', 'tutor_id' => null, 'name' => 'Admin Tester'];
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 12, 100000, 1, 'diploma')")->execute(["Alpha $run", 'AL' . strtoupper(substr($run, 0, 4))]); $C1 = (int) $db->lastInsertId();
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, ?, 6, 50000, 1, 'certificate')")->execute(["Beta $run", 'BE' . strtoupper(substr($run, 0, 4))]); $C2 = (int) $db->lastInsertId();
$stu = function (string $k, int $course, ?array $tutor, string $status = 'approved') use ($db, $mk, $run): int {
    $uid = $mk($k, 'student');
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', ?, ?, 'thika', 'x.pdf', 1, 100000, 'T', ?)")->execute(['SSX-' . $k . $run, ucfirst($k) . ' Student', "$k$run@s.test", $course, $status, $uid, gmdate('Y-m-d H:i:s', time() - 100 * 86400)]);
    if ($tutor) $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tutor['tutor_id'], $uid, gmdate('Y-m-d', time() - 100 * 86400)]);
    return $uid;
};
$o1 = $stu('o1', $C1, $O); $o2 = $stu('o2', $C1, $O); $o3 = $stu('o3', $C2, $O); $e1 = $stu('e1', $C1, $E); $e2 = $stu('e2', $C1, $E); $v1 = $stu('v1', $C2, $V); $s1 = $stu('s1', $C1, $S); $pend = $stu('pend', $C1, $O, 'pending');
$db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$E['tutor_id'], $o1, gmdate('Y-m-d')]);       // o1 is also a student of the editor
$db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
              VALUES (?, ?, ?, '0700', ?, 'online', 1000, 50000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 50000, 'T', ?)")->execute(['SSX-o1b' . $run, 'O1 Student', "o1b$run@s.test", $C2, $o1, gmdate('Y-m-d H:i:s')]);   // a newer registration for o1
$r = sst_save_details($db, $O, null, ['title' => 'Access test ' . $run, 'short_description' => 'd', 'level' => 'beginner', 'checkpoint_minutes' => 45, 'final_minutes' => 60]);
$cid = (int) $r['id'];
$db->prepare("INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, 'edit', ?, ?)")->execute([$cid, $E['tutor_id'], $O['uid'], ss2_ts(time())]);
$db->prepare("INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, 'view', ?, ?)")->execute([$cid, $V['tutor_id'], $O['uid'], ss2_ts(time())]);
$allU = [$O['uid'], $E['uid'], $V['uid'], $S['uid'], $adminUid, $o1, $o2, $o3, $e1, $e2, $v1, $s1, $pend];
foreach ($allU as $u) $db->prepare("INSERT INTO notification_preferences (user_id, category, enabled) VALUES (?, 'other', 1)")->execute([$u]);      // notifications start switched off
$now = strtotime('2026-11-13 10:00:00 +03:00');
$groupOf = fn(array $pk, int $id) => array_values(array_filter($pk['groups'], fn($g) => $g['id'] === $id))[0] ?? null;
$ids = fn(array $pk) => array_merge(...array_map(fn($g) => array_column($g['students'], 'id'), $pk['groups'] ?: [[ 'students' => [] ]]));

echo "== Who may use these tabs\n";
$_SESSION = ['user_id' => $o1, 'role' => 'student'];
check('a student cannot use them', sst_viewer($db, 'tutor') === null && sst_viewer($db, 'admin') === null);
$_SESSION = ['user_id' => $mk('staff', 'employee'), 'role' => 'employee'];
check('staff cannot use them', sst_viewer($db, 'tutor') === null && sst_viewer($db, 'admin') === null);
check('a tutor with no access to the course gets 404 (looks like it does not exist)', refused(fn() => ssa_picker($db, $S, $cid)) === 404 && refused(fn() => ssa_save_students($db, $S, $cid, [$s1])) === 404);

echo "\n== The picker: own students only, grouped by course, each once\n";
$pk = ssa_picker($db, $O, $cid, $now);
$all = $ids($pk);
check('the owner sees only their own students (o1, o2, o3 + the pending one is not shown)', count($all) === 3 && !in_array($e1, $all) && !in_array($s1, $all) && !in_array($pend, $all), json_encode($all));
check('a student with two registrations appears once, under the newest course', count(array_keys($all, $o1)) === 1 && in_array($o1, array_column($groupOf($pk, $C2)['students'], 'id')) && !in_array($o1, array_column($groupOf($pk, $C1)['students'], 'id') ?: []));
check('each student has a name, number and initials', ($s = $groupOf($pk, $C1)['students'][0]) && $s['name'] !== '' && str_starts_with($s['number'], 'SSX-') && strlen($s['initials']) >= 1);
check('the editor sees their own students (e1, e2 and o1), not the owner\'s others', ($p2 = $ids(ssa_picker($db, $E, $cid))) && in_array($e1, $p2) && in_array($o1, $p2) && !in_array($o2, $p2) && !in_array($v1, $p2));
$pa = ssa_picker($db, $A, $cid);
check('admin can pick any approved student (not the pending one)', count($ids($pa)) >= 7 && in_array($s1, $ids($pa)) && !in_array($pend, $ids($pa)));
check('a "Can view" tutor can pick their own students', in_array($v1, $ids(ssa_picker($db, $V, $cid))));

echo "\n== Saving: a draft tells nobody\n";
$r = ssa_save_students($db, $O, $cid, [$o1, $o2, $o3], $now);
check('the owner adds three students', $r['added'] === 3 && $r['total'] === 3 && $r['removed'] === 0);
$rows = $db->query("SELECT student_id, added_by_tutor_id, notified_at FROM self_study_access WHERE course_id = $cid")->fetchAll(PDO::FETCH_ASSOC);
check('they are saved with who added them and nobody was told (it is a draft)', count($rows) === 3 && array_unique(array_column($rows, 'added_by_tutor_id')) === [$O['tutor_id']] && array_filter(array_column($rows, 'notified_at')) === []);
check('a draft is seen by nobody', ss_courses_for_student($db, $o1) === [] && ss_course_for_student($db, $cid, $o1) === null);
check('the choices are kept with the draft', $groupOf(ssa_picker($db, $O, $cid), $C2)['students'][0]['selected'] === true);
check('the change is in the activity log with who did it', ($lg = $db->query("SELECT actor_name, actor_role, action, item_count FROM self_study_activity_log WHERE course_id = $cid")->fetch(PDO::FETCH_ASSOC)) && $lg['action'] === 'students_added' && (int) $lg['item_count'] === 3 && $lg['actor_role'] === 'tutor' && $lg['actor_name'] === 'Owner Tester');

echo "\n== Refusing students that are not yours (the server, not the page)\n";
check('an editor naming the owner\'s student (o2) or a stranger\'s student is refused as a whole', refused(fn() => ssa_save_students($db, $E, $cid, [$e1, $o2], $now)) === 403 && refused(fn() => ssa_save_students($db, $E, $cid, [$s1], $now)) === 403);
check('nothing was changed by the refused requests', !$db->query("SELECT 1 FROM self_study_access WHERE course_id = $cid AND student_id IN ($e1, $s1)")->fetchColumn());
check('a made-up id and a pending (not approved) student are refused, even for admin', refused(fn() => ssa_save_students($db, $A, $cid, [999999], $now)) === 403 && refused(fn() => ssa_save_students($db, $A, $cid, [$pend], $now)) === 403);
check('a tutor with no access cannot add anyone, and a student cannot be a viewer', refused(fn() => ssa_save_students($db, $S, $cid, [$s1])) === 404);

echo "\n== Tutors with access add and remove only their own\n";
$r = ssa_save_students($db, $E, $cid, [$e1, $e2, $o1], $now);
check('the editor adds e1 and e2; o1 (already added by the owner) is left alone', $r['added'] === 2 && $r['total'] === 5);
$pe = ssa_picker($db, $E, $cid); $o1row = array_values(array_filter($groupOf($pe, $C2)['students'], fn($s) => $s['id'] === $o1))[0];
check('o1 shows as selected but locked: "added by Owner Tester"', $o1row['selected'] && $o1row['locked'] && $o1row['by'] === 'Owner Tester');
$r = ssa_save_students($db, $E, $cid, [], $now);
check('the editor clearing everything removes only their own students, never the owner\'s o1', $r['removed'] === 2 && $r['total'] === 3 && (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND student_id = $o1 AND removed_at IS NULL")->fetchColumn() === 1);
$db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at) VALUES (?, ?, 40, ?)')->execute([$cid, $e1, ss2_ts($now)]);
ssa_save_students($db, $E, $cid, [$e1, $e2], $now + 10);
check('adding a removed student again works and keeps their progress row', (int) $db->query("SELECT percent_complete FROM self_study_progress WHERE course_id = $cid AND student_id = $e1")->fetchColumn() === 40 && (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND student_id = $e1")->fetchColumn() === 1);
ssa_save_students($db, $V, $cid, [$v1], $now + 20);
check('a "Can view" tutor can add their own student too', (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND student_id = $v1 AND removed_at IS NULL")->fetchColumn() === 1);
check('a viewer clearing their list cannot touch the editor\'s or owner\'s students', ($r = ssa_save_students($db, $V, $cid, [], $now + 30)) && $r['removed'] === 1 && $r['total'] === 5);
ssa_save_students($db, $V, $cid, [$v1], $now + 40);

echo "\n== The owner and admin see and manage everyone\n";
$po = ssa_picker($db, $O, $cid);
check('the owner also sees the students other tutors added, and can remove them', in_array($e1, $ids($po)) && in_array($v1, $ids($po)) && ($x = array_values(array_filter($groupOf($po, $C1)['students'], fn($s) => $s['id'] === $e1))[0]) && $x['selected'] && !$x['locked'] && $x['by'] === 'Editor Tester');
$ad = $po['audience']['adders']; $byName = array_column($ad, 'n', 'name');
check('"Editor Tester added 2 students" and the owner\'s 3 are listed for the owner', ($byName['Editor Tester'] ?? 0) === 2 && ($byName['Owner Tester'] ?? 0) === 3, json_encode($byName));
check('the right-hand box counts students per course', $po['audience']['total'] === 6 && array_sum(array_column($po['audience']['courses'], 'n')) === 6 && count($po['audience']['courses']) === 2);
check('an editor does not get the "who added how many" lines', ssa_picker($db, $E, $cid)['audience']['adders'] === []);
$r = ssa_save_students($db, $O, $cid, array_merge([$o1, $o2, $o3], [$e2, $v1]), $now + 50);           // owner leaves e1 unticked
check('the owner removes a student another tutor added (e1)', $r['removed'] === 1 && !(int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND student_id = $e1 AND removed_at IS NULL")->fetchColumn());
$r = ssa_save_students($db, $A, $cid, [$s1, $e2, $v1, $o1, $o2, $o3], $now + 60);
check('admin adds a student nobody else could (s1); the row records the admin, not a tutor', $r['added'] === 1 && $db->query("SELECT added_by_tutor_id FROM self_study_access WHERE course_id = $cid AND student_id = $s1")->fetchColumn() === null && (int) $db->query("SELECT added_by FROM self_study_access WHERE course_id = $cid AND student_id = $s1")->fetchColumn() === $adminUid);

echo "\n== Publishing: students are told once\n";
$r = ssa_save_and_publish($db, $O, $cid, [$o1, $o2, $o3], $now + 70);
check('publish is refused while the checklist is not finished, and the students are still saved', $r['published'] === false && str_contains((string) $r['publish_error'], 'Course image') && $db->query("SELECT status FROM self_study_courses WHERE id = $cid")->fetchColumn() === 'draft');
// finish the checklist
$db->prepare("UPDATE self_study_courses SET cover_original = 'uploads/x.png', cover_800 = 'uploads/x.webp', checkpoint_questions = 1, final_questions = 1, quiz_questions = 1 WHERE id = ?")->execute([$cid]);
$db->prepare("INSERT INTO self_study_modules (course_id, title, position) VALUES (?, 'M1', 1)")->execute([$cid]); $mid = (int) $db->lastInsertId();
$db->prepare("INSERT INTO self_study_topics (course_id, module_id, title, position, content_html) VALUES (?, ?, 'T1', 1, '<p>x</p>')")->execute([$cid, $mid]); $tpid = (int) $db->lastInsertId();
$db->prepare("INSERT INTO self_study_questions (course_id, place, module_id, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, 'module_quiz', ?, ?, 'multiple_choice', 'Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $mid, $tpid]);
foreach (['checkpoint_exam', 'final_exam'] as $pl) $db->prepare("INSERT INTO self_study_questions (course_id, place, topic_id, qtype, question_text, options_json, correct_index) VALUES (?, ?, ?, 'multiple_choice', 'Q', '[\"a\",\"b\"]', 0)")->execute([$cid, $pl, $tpid]);
$r = ssa_save_and_publish($db, $O, $cid, [$o1, $o2, $o3], $now + 80);
$cnt = fn(int $u) => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $u AND title LIKE 'New self-study course%'")->fetchColumn();
check('publishing works once everything is ticked', $r['published'] === true && $db->query("SELECT status FROM self_study_courses WHERE id = $cid")->fetchColumn() === 'published');
check('o1, o2 and o3 got exactly one notification each', $cnt($o1) === 1 && $cnt($o2) === 1 && $cnt($o3) === 1);
check('their Self-study page now lists the course', count(ss_courses_for_student($db, $o1)) === 1 && ss_course_for_student($db, $cid, $o2) !== null);
$r = ssa_save_students($db, $O, $cid, [$o1, $o2, $o3], $now + 90);
check('saving again tells nobody a second time', $cnt($o1) === 1 && $r['notified'] === 0);
$s2 = $stu('o4', $C1, $O); $db->prepare("INSERT INTO notification_preferences (user_id, category, enabled) VALUES (?, 'other', 1)")->execute([$s2]); $allU[] = $s2;
$r = ssa_save_students($db, $O, $cid, [$o1, $o2, $o3, $s2], $now + 100);
check('a student added to a published course is told at once', $r['notified'] === 1 && $cnt($s2) === 1 && ss_course_for_student($db, $cid, $s2) !== null);

echo "\n== Removing hides the course but keeps progress\n";
$db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at) VALUES (?, ?, 55, ?)')->execute([$cid, $s2, ss2_ts($now)]);
$db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, done_at) SELECT ?, id, course_id, ? FROM self_study_topics WHERE course_id = ? LIMIT 1')->execute([$s2, ss2_ts($now), $cid]);
ssa_save_students($db, $O, $cid, [$o1, $o2, $o3], $now + 110);
check('the removed student no longer sees the course', ss_course_for_student($db, $cid, $s2) === null && ss_courses_for_student($db, $s2) === []);
check('their progress is all still there', (int) $db->query("SELECT percent_complete FROM self_study_progress WHERE course_id = $cid AND student_id = $s2")->fetchColumn() === 55 && (int) $db->query("SELECT COUNT(*) FROM self_study_topic_progress WHERE course_id = $cid AND student_id = $s2")->fetchColumn() === 1);
ssa_save_students($db, $O, $cid, [$o1, $o2, $o3, $s2], $now + 120);
check('adding them back opens it exactly where they stopped (55%) and tells them again', ss_courses_for_student($db, $s2)[0]['percent'] === 55 && $cnt($s2) === 2);
check('the removal and the re-adding are in the activity log', (int) $db->query("SELECT COUNT(*) FROM self_study_activity_log WHERE course_id = $cid AND action = 'students_removed'")->fetchColumn() >= 1);

echo "\n== Sharing with other tutors\n";
ssa_save_students($db, $E, $cid, [$e1, $e2, $o1], $now + 125);       // (the owner's earlier saves left out the editor's students on purpose: the owner may remove anyone)
$tl = ssa_tutors($db, $O, $cid); $byT = array_column($tl, null, 'id');
check('the tutor list excludes the owner and shows each tutor\'s access and how many students they added', !isset($byT[$O['tutor_id']]) && $byT[$E['tutor_id']]['access'] === 'edit' && $byT[$V['tutor_id']]['access'] === 'view' && $byT[$S['tutor_id']]['access'] === 'none' && $byT[$E['tutor_id']]['added'] >= 1 && str_contains($byT[$E['tutor_id']]['sub'], 'added'));
check('only the owner and admin can change the sharing: an editor and a viewer are refused', refused(fn() => ssa_set_editor($db, $E, $cid, $S['tutor_id'], 'view')) === 403 && refused(fn() => ssa_set_editor($db, $V, $cid, $S['tutor_id'], 'edit')) === 403);
check('a tutor with no access cannot see or change anything', refused(fn() => ssa_set_editor($db, $S, $cid, $S['tutor_id'], 'edit')) === 404);
$r = ssa_set_editor($db, $O, $cid, $S['tutor_id'], 'view', $now + 130);
check('the owner gives a tutor "Can view"; the course is now in their list "shared by" the owner', $r['changed'] && ($lr = array_values(array_filter(sst_list($db, $S), fn($x) => (int) $x['id'] === $cid))[0] ?? null) && $lr['shared_by'] === 'Owner Tester' && $lr['access_label'] === 'Can view');
check('the tutor was told', (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = {$S['uid']} AND type = 'selfstudy_share'")->fetchColumn() === 1);
check('the tutor can now open it read-only but cannot edit', ($c = sst_course($db, $S, $cid)) && !$c['can_edit'] && $c['access'] === 'view');
check('the same choice again changes nothing and tells nobody again', ssa_set_editor($db, $O, $cid, $S['tutor_id'], 'view')['changed'] === false && (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = {$S['uid']} AND type = 'selfstudy_share'")->fetchColumn() === 1);
ssa_set_editor($db, $A, $cid, $S['tutor_id'], 'edit', $now + 140);
check('admin can change the sharing; Can edit now lets them change content', sst_course($db, $S, $cid)['can_edit'] === true);
$before = (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND removed_at IS NULL")->fetchColumn();
ssa_save_students($db, $E, $cid, [$e1, $e2, $o1], $now + 150);
$mine = (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND added_by_tutor_id = {$E['tutor_id']} AND removed_at IS NULL")->fetchColumn();
ssa_set_editor($db, $O, $cid, $E['tutor_id'], 'none', $now + 160);
check('changing a tutor to No access removes their access but NOT the students they added', sst_course($db, $E, $cid) === null && (int) $db->query("SELECT COUNT(*) FROM self_study_access WHERE course_id = $cid AND added_by_tutor_id = {$E['tutor_id']} AND removed_at IS NULL")->fetchColumn() === $mine && $mine >= 1);
check('the students that tutor added still see the course', ss_course_for_student($db, $cid, $e2) !== null);
check('the owner can still remove them from Who sees it', in_array($e2, $ids(ssa_picker($db, $O, $cid))));
check('a tutor with No access can no longer pick students for the course', refused(fn() => ssa_save_students($db, $E, $cid, [$e1])) === 404);
check('the owner cannot be given access to their own course, an unknown tutor and a bad level are refused', refused(fn() => ssa_set_editor($db, $O, $cid, $O['tutor_id'], 'edit')) === 422 && refused(fn() => ssa_set_editor($db, $O, $cid, 999999, 'edit')) === 404 && refused(fn() => ssa_set_editor($db, $O, $cid, $V['tutor_id'], 'owner')) === 422);
check('every sharing change is in the activity log with who did it', (int) $db->query("SELECT COUNT(*) FROM self_study_activity_log WHERE course_id = $cid AND action IN ('access_view', 'access_edit', 'access_none')")->fetchColumn() === 3 && $db->query("SELECT actor_role FROM self_study_activity_log WHERE course_id = $cid AND action = 'access_edit' ORDER BY id DESC LIMIT 1")->fetchColumn() === 'admin');

echo "\n== Clean up\n";
foreach (['self_study_activity_log', 'self_study_topic_progress', 'self_study_progress', 'self_study_access', 'self_study_questions', 'self_study_topics', 'self_study_modules', 'self_study_course_editors'] as $t) $db->exec("DELETE FROM $t WHERE course_id = $cid");
$db->exec("DELETE FROM self_study_courses WHERE id = $cid");
$all = implode(',', array_merge($allU, [(int) $db->query("SELECT id FROM users WHERE username = 'ssx_staff_$run'")->fetchColumn()]));
$db->exec("DELETE FROM portal_notifications WHERE user_id IN ($all)"); $db->exec("DELETE FROM notification_preferences WHERE user_id IN ($all)");
$db->exec("DELETE FROM tutor_students WHERE user_id IN ($all)"); $db->exec("DELETE FROM registrations WHERE user_id IN ($all)");
$db->exec('DELETE FROM tutors WHERE id IN (' . implode(',', [$O['tutor_id'], $E['tutor_id'], $V['tutor_id'], $S['tutor_id']]) . ')');
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($all)"); $db->exec("DELETE FROM users WHERE id IN ($all)");
$db->exec("DELETE FROM courses WHERE id IN ($C1, $C2)");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
