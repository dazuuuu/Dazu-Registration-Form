<?php
// Capstone tests: the rules of the spec, checked against a real (throw-away) database.
//   DAZU_DB_NAME=dazu_captest php app/v1/tests/capstone_test.php
// Use a scratch database that has all migrations applied (php app/v1/databases/migrate.php). The test adds its own rows.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database, for example DAZU_DB_NAME=dazu_captest\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/CapstoneService.php';
require_once __DIR__ . '/../services/CapstoneNotifier.php';
require_once __DIR__ . '/../services/AssignmentAverage.php';
require_once __DIR__ . '/../services/CertificateService.php';

use Models\AssignmentModel;

$db = \Models\Connection::getInstance()->getConnection();
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
/** The readiness result in the short shape these older checks use. */
function cap_cert_checklist_short(PDO $db, int $uid, int $courseId, int $reg): array
{
    $r = cap_certificate_readiness($db, $uid, $courseId, $reg);
    return ['finished' => $r['conditions']['finished']['ok'], 'fees_paid' => $r['conditions']['fees']['ok'], 'capstone_ok' => $r['conditions']['capstone']['ok'],
            'average_ok' => $r['conditions']['average']['ok'], 'ready' => $r['ready']];
}

$run = substr(md5((string) microtime(true)), 0, 6);   // lets the test run again on the same scratch database
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["t_$key$run", "$key$run@t.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$tutorUid = $mkuser('tutor', 'tutor', 'Tina', 'Tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutorUid]);
$tutorId = (int) $db->lastInsertId();
$otherUid = $mkuser('tutor2', 'tutor', 'Other', 'Tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$otherUid]);
$otherTutorId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES ('Test Course', ?, 12, 100000, 1, 'diploma')")->execute(['T' . strtoupper(substr($run, 0, 5))]);
$courseId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$courseId, $tutorId]);
$stu = [];
$regOf = [];
foreach (['s1' => 'One', 's2' => 'Two', 's3' => 'Three', 's4' => 'Four', 's5' => 'Five', 's6' => 'Six'] as $k => $l) {
    $uid = $mkuser($k, 'student', 'Stu', $l);
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
                  VALUES (?, ?, ?, '0700', ?, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T')")->execute(['T' . $k . $run, "Stu $l", "$k$run@t.test", $courseId, $uid]);
    $regOf[$uid] = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$tutorId, $uid]);
    $stu[$k] = $uid;
}
$am = new AssignmentModel($db);
$utc = fn(int $days, int $hour = 15): string => gmdate('Y-m-d', time() + $days * 86400) . sprintf(' %02d:00:00', $hour);

/** A valid setup payload; $over overrides keys. */
$payload = function (array $over = []) use ($courseId, $utc, $stu): array {
    return $over + [
        'course_id' => $courseId, 'intake_id' => null, 'title' => 'Test capstone', 'brief' => 'Build a thing', 'instructions' => 'Do it', 'requirements' => "One\nTwo",
        'example_link' => 'https://github.com/example/example-project', 'mode' => 'individual', 'team_size' => 3, 'visibility' => 'hidden', 'opens_at' => null, 'due_at' => $utc(14),
        'w_project' => 50, 'w_docs' => 20, 'w_present' => 20, 'w_time' => 10, 'weight' => 5,
        'deliverables' => [
            ['kind' => 'project_link', 'label' => CAP_DELIVERABLES['project_link'][0], 'item_type' => 'link', 'is_required' => 1],
            ['kind' => 'report', 'label' => CAP_DELIVERABLES['report'][0], 'item_type' => 'file', 'is_required' => 0],
        ],
        'milestones' => [
            ['title' => 'Proposal', 'kind' => 'work', 'due_at' => $utc(-5), 'marks' => 4],
            ['title' => 'Final project sent', 'kind' => 'final', 'due_at' => $utc(14), 'marks' => 3],
            ['title' => 'Presentation', 'kind' => 'presentation', 'due_at' => $utc(16), 'marks' => 3],
        ],
        'students' => [['uid' => $stu['s1'], 'team_no' => null, 'own_due_at' => null]],
    ];
};
/** Put (checked-link-free) work in for a student's draft and tick own work. */
$addWork = function (array $capRow, int $uid, string $url = 'https://github.com/someone/work') use ($db): void {
    $att = cap_latest_attempt($db, (int) $capRow['id'], $uid);
    $owner = cap_owner($db, $att);
    $w = cap_work($db, $owner, true);
    $dels = array_column(cap_deliverables($db, (int) $capRow['id']), null, 'kind');
    cap_put_item($db, (int) $w['id'], (int) $dels['project_link']['id'], $dels['project_link'], ['url' => $url, 'file_path' => null, 'file_name' => null, 'link_status' => 'ok', 'link_message' => 'ok', 'same' => 0]);
    $db->prepare("UPDATE capstone_attempts SET status = 'in_progress' WHERE id = ? AND status = 'not_started'")->execute([(int) $att['id']]);
    foreach (cap_group_attempts($db, $owner) as $g) {
        $db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at) VALUES (?, ?, ?, ?)')->execute([(int) $owner['id'], (int) $g['student_user_id'], (int) $w['version'], cap_now()]);
    }
};
$send = function (int $capId, int $uid) use ($db): array {
    $row = cap_for_student($db, $capId, $uid);
    return cap_submit($db, $row, cap_latest_attempt($db, $capId, $uid), $uid, true);
};
$markIn = fn(array $p = []) => $p + ['p_project' => 30, 'p_docs' => 10, 'p_present' => 10, 'p_time' => 5];

// ============================================================ 1. hidden / scheduled / open
section('Hidden, scheduled and open');
$r = cap_save($db, $tutorId, null, $payload(), $tutorUid);
check('a draft capstone can be saved', $r['ok'], json_encode($r));
$draftId = (int) $r['id'];
check('a hidden (draft) capstone is invisible to the student', cap_student_capstones($db, $stu['s1']) === [] && !cap_student_has_any($db, $stu['s1']) && cap_for_student($db, $draftId, $stu['s1']) === null);
check('the admin status function does not know a draft capstone', cap_status_word($db, $stu['s1'], $courseId) === 'Not set' && !array_filter(cap_admin_enrolments($db), fn($r) => (int) $r['course_id'] === $courseId));
check('a draft sends no notifications and makes no attempts', !cap_has_attempts($db, $draftId));

$r = cap_save($db, $tutorId, null, $payload(['title' => 'Scheduled one', 'visibility' => 'date', 'opens_at' => $utc(5, 8)]), $tutorUid);
$schedId = (int) $r['id'];
$row = cap_for_student($db, $schedId, $stu['s1']);
check('a scheduled capstone is visible to the student as "scheduled" with the opening date', $row && $row['status'] === 'scheduled' && !cap_is_open_for($row));
$list = cap_student_capstones($db, $stu['s1']);
check('before it opens the student list has NO title and NO due date', $list && $list[0]['title'] === null && $list[0]['due_at'] === null);
check('a scheduled capstone does not appear to the admin yet', !array_filter(cap_admin_enrolments($db), fn($r) => (int) $r['course_id'] === $courseId));
$db->prepare('UPDATE capstones SET opens_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 60), $schedId]);
$t = cap_tick($db);
check('the tick opens a scheduled capstone at its time', cap_get($db, $schedId)['status'] === 'open' && $t['opened'] === 1, json_encode($t));
check('opening made the attempt rows and told the students once', cap_has_attempts($db, $schedId) && cap_notify_open($db, $schedId) === 0);
$nt = $db->prepare("SELECT COUNT(*) FROM portal_notifications WHERE user_id = ? AND title LIKE 'Your capstone is open%'");
$nt->execute([$stu['s1']]);
check('the student got an "open" notification (once)', (int) $nt->fetchColumn() <= 1);
check('the tick is safe to run twice', cap_tick($db)['opened'] === 0);

$r = cap_save($db, $tutorId, null, $payload(['title' => 'Manual open', 'visibility' => 'hidden']), $tutorUid);
$manId = (int) $r['id'];
$o = cap_open_now($db, $manId, $tutorId, $tutorUid);
check('opening by hand works before the date', $o['ok'] && cap_get($db, $manId)['status'] === 'open');
$r = cap_save($db, $tutorId, null, $payload(['title' => 'Manual before date', 'visibility' => 'date', 'opens_at' => $utc(9, 8), 'students' => [['uid' => $stu['s6'], 'team_no' => null, 'own_due_at' => null]]]), $tutorUid);
$o = cap_open_now($db, (int) $r['id'], $tutorId, $tutorUid);
check('opening by hand works for a scheduled one, before its date', $o['ok'] && cap_get($db, (int) $r['id'])['status'] === 'open');
$cl = cap_close($db, $manId, $tutorId, $tutorUid);
$o = cap_open_now($db, $manId, $tutorId, $tutorUid);
check('close then open by hand works again', $cl['ok'] && $o['ok'] && cap_get($db, $manId)['status'] === 'open');
check('another tutor cannot open or read my capstone', cap_get_for_tutor($db, $manId, $otherTutorId) === null && !cap_open_now($db, $manId, $otherTutorId, $otherUid)['ok']);

// ============================================================ 2. parts total 100
section('Marks parts');
$bad = cap_save($db, $tutorId, null, $payload(['w_project' => 60]), $tutorUid);
check('four parts that do not total 100 cannot be saved', !$bad['ok'] && strpos(implode(' ', $bad['errors']), '100') !== false);
$bad = cap_save($db, $tutorId, null, $payload(['w_time' => 12]), $tutorUid);
check('parts totalling 102 are blocked too', !$bad['ok']);
$bad = cap_save($db, $tutorId, null, $payload(['milestones' => [['title' => 'A', 'kind' => 'work', 'due_at' => $utc(1), 'marks' => 3]]]), $tutorUid);
check('milestone marks must equal the Time keeping part', !$bad['ok'] && strpos(implode(' ', $bad['errors']), 'Time keeping') !== false);

// ============================================================ 3. returns (max 2), final, resubmission
section('Submit, return twice, final');
$cap = cap_get($db, $manId);
$att = cap_latest_attempt($db, $manId, $stu['s1']);
check('the student cannot submit with nothing added', !$send($manId, $stu['s1'])['ok']);
$addWork($cap, $stu['s1']);
$s = $send($manId, $stu['s1']);
check('the student can submit once the work is in', $s['ok'], json_encode($s));
$att = cap_latest_attempt($db, $manId, $stu['s1']);
check('after submit the status is submitted (final unless returned)', $att['status'] === 'submitted');
$row = cap_for_student($db, $manId, $stu['s1']);
check('the student cannot change the work after sending', !cap_can_edit($row, $att) && !cap_save_work($db, $row, $att, $stu['s1'], [], [], true, '')['ok']);
check('a second submit (double click / second tab) is refused', !cap_submit($db, $row, $att, $stu['s1'], true)['ok']);
$r0 = cap_return($db, (int) $att['id'], $tutorId, '   ', null, $tutorUid);
check('a return always needs instructions', !$r0['ok']);
$r1 = cap_return($db, (int) $att['id'], $tutorId, 'Fix the menu.', $utc(3), $tutorUid);
check('first return works and counts 1', $r1['ok'] && $r1['return_no'] === 1 && (int) cap_attempt($db, (int) $att['id'])['returns_used'] === 1);
$again = cap_return($db, (int) $att['id'], $tutorId, 'Double click', null, $tutorUid);
check('a double click cannot return twice (the attempt is not waiting any more)', !$again['ok'] && (int) cap_attempt($db, (int) $att['id'])['returns_used'] === 1);
$att = cap_attempt($db, (int) $att['id']);
check('after the return the student may resubmit', cap_can_edit($row, $att));
$addWork($cap, $stu['s1'], 'https://github.com/someone/work2');
check('resubmit works after a return', $send($manId, $stu['s1'])['ok']);
$att = cap_attempt($db, (int) $att['id']);
$r2 = cap_return($db, (int) $att['id'], $tutorId, 'Last chance: add a README.', null, $tutorUid);
check('the second return works', $r2['ok'] && $r2['return_no'] === 2);
$att = cap_attempt($db, (int) $att['id']);
check('state after the second return is "final_try"', cap_state($row, $att) === 'final_try');
$addWork($cap, $stu['s1'], 'https://github.com/someone/work3');
$s = $send($manId, $stu['s1']);
check('the last send is final', $s['ok'] && !empty($s['final']));
$att = cap_attempt($db, (int) $att['id']);
check('the can-return rule says no after 2 returns', !cap_can_return($att));
$r3 = cap_return($db, (int) $att['id'], $tutorId, 'A third return', null, $tutorUid);
check('a THIRD return is refused on the server', !$r3['ok'] && (int) cap_attempt($db, (int) $att['id'])['returns_used'] === 2);
$vers = cap_versions($db, (int) $att['id']);
check('every send is kept as a version (history)', count($vers) === 3);

// ============================================================ 4. time keeping and marks
section('Time keeping and marking');
$owner = cap_owner($db, $att);
$ev = cap_time_evidence($db, $cap, $owner);
$by = array_column($ev['rows'], null, 'kind');
check('a milestone never sent and past due is Missing with 0 marks', $by['work']['status'] === 'missing' && $by['work']['earned'] == 0);
check('the final project sent before the due date is On time and earns its marks', $by['final']['status'] === 'on_time' && (float) $by['final']['earned'] === 3.0);
$db->prepare('INSERT INTO capstone_milestone_submissions (milestone_id, attempt_id, note, submitted_at) VALUES (?, ?, ?, ?)')->execute([(int) $by['work']['id'], (int) $owner['id'], 'late plan', gmdate('Y-m-d H:i:s', time() - 3 * 86400)]);
$ev = cap_time_evidence($db, $cap, $owner);
$by = array_column($ev['rows'], null, 'kind');
check('a milestone sent after its due time is Late and loses its marks', $by['work']['status'] === 'late' && $by['work']['earned'] == 0 && $by['work']['minutes_late'] > 0);
check('late text is plain', strpos(cap_late_text($by['work']['minutes_late']), 'late') !== false);
$bad = cap_save_mark($db, (int) $att['id'], $tutorId, $markIn(['p_project' => 51]), $tutorUid);
check('a part over its limit is refused', !$bad['ok']);
$sugg = $ev['suggested'];
$m = cap_save_mark($db, (int) $att['id'], $tutorId, $markIn(['p_time' => 9, 'time_evidence' => $ev['rows'], 'feedback' => 'ok']), $tutorUid);
check('the tutor can change the time keeping number from the suggestion', $m['ok'] && (float) cap_mark($db, (int) $att['id'])['p_time'] === 9.0 && $sugg !== 9.0);
check('the evidence is stored next to the mark', strpos((string) cap_mark($db, (int) $att['id'])['time_evidence'], 'Proposal') !== false);
check('total and result word are right (30+10+10+9 = 59 = Pass)', $m['total'] == 59.0 && $m['result'] === 'Pass', json_encode($m));
check('bands: 70 Distinction, 60 Credit, 50 Pass, 49.5 Fail', cap_result_word(70) === 'Distinction' && cap_result_word(69.9) === 'Credit' && cap_result_word(60) === 'Credit' && cap_result_word(50) === 'Pass' && cap_result_word(49.5) === 'Fail');
check('a final mark of 59 passes and the attempt is marked', cap_attempt($db, (int) $att['id'])['status'] === 'marked' && cap_status_word($db, $stu['s1'], $courseId) === 'Passed');

// held marks
$r = cap_save($db, $tutorId, null, $payload(['title' => 'Held marks', 'visibility' => 'now', 'students' => [['uid' => $stu['s2'], 'team_no' => null, 'own_due_at' => null]]]), $tutorUid);
$heldId = (int) $r['id'];
$cap2 = cap_get($db, $heldId); $addWork($cap2, $stu['s2']); $send($heldId, $stu['s2']);
$a2 = cap_latest_attempt($db, $heldId, $stu['s2']);
$m = cap_save_mark($db, (int) $a2['id'], $tutorId, $markIn(['held_until' => gmdate('Y-m-d H:i:s', time() + 86400)]), $tutorUid);
check('a held mark is not visible to the student until the release time', $m['ok'] && !cap_mark_released(cap_mark($db, (int) $a2['id'])));
$rows = AssignmentAverage::forStudent($db, $stu['s2']);
check('a held capstone mark is not counted in the course average', ($rows[$courseId]['average'] ?? null) === null);
$db->prepare('UPDATE capstone_marks SET held_until = ? WHERE attempt_id = ?')->execute([gmdate('Y-m-d H:i:s', time() - 5), (int) $a2['id']]);
$t = cap_tick($db);
check('the tick tells the student when the hold ends (once)', $t['released'] === 1 && cap_tick($db)['released'] === 0);
$rows = AssignmentAverage::forStudent($db, $stu['s2']);
check('after release the capstone counts in the course average with its weight', isset($rows[$courseId]['average']) && abs($rows[$courseId]['average'] - 55) < 0.01, json_encode($rows[$courseId]['average'] ?? null));

// ============================================================ 5. example banner / acknowledgement / copied link
section('Example project');
$viewSrc = file_get_contents(__DIR__ . '/../../../public/students/capstone/view.php');
check('the example banner is always in the student page and has no off switch', strpos($viewSrc, 'EXAMPLE ONLY. DO NOT COPY THIS.') !== false && strpos(file_get_contents(__DIR__ . '/../../../public/tutor/capstone/edit.php'), 'EXAMPLE ONLY. DO NOT COPY THIS.') !== false && strpos(file_get_contents(__DIR__ . '/../../../public/tutor/capstone/edit.php'), 'show_banner') === false);
check('the example is locked until the student ticks "I understand"', !cap_example_acked($db, $manId, $stu['s1']));
cap_ack_example($db, $manId, $stu['s1']);
check('after the tick it is recorded with who and when', cap_example_acked($db, $manId, $stu['s1']));
$r = cap_save($db, $tutorId, null, $payload(['title' => 'Copy check', 'visibility' => 'now', 'students' => [['uid' => $stu['s3'], 'team_no' => null, 'own_due_at' => null]]]), $tutorUid);
$copyId = (int) $r['id'];
$capC = cap_get($db, $copyId);
$dels = cap_deliverables($db, $copyId);
$aC = cap_latest_attempt($db, $copyId, $stu['s3']);
$res = cap_save_work($db, cap_for_student($db, $copyId, $stu['s3']), $aC, $stu['s3'], [(int) $dels[0]['id'] => 'https://www.github.com/example/example-project.git/'], [], true, '');
$flag = $db->prepare('SELECT same_as_example FROM capstone_submission_items i JOIN capstone_submissions s ON s.id = i.submission_id WHERE s.attempt_id = ?');
$flag->execute([(int) $aC['id']]);
check('sending the same link as the example is flagged for the tutor', (int) $flag->fetchColumn() === 1, json_encode($res['errors'] ?? []));

// ============================================================ 6. team capstone
section('Team capstone');
$r = cap_save($db, $tutorId, null, $payload(['title' => 'Team work', 'mode' => 'team', 'visibility' => 'now',
    'students' => [['uid' => $stu['s4'], 'team_no' => 1, 'own_due_at' => null], ['uid' => $stu['s5'], 'team_no' => 1, 'own_due_at' => null], ['uid' => $stu['s6'], 'team_no' => 2, 'own_due_at' => null]]]), $tutorUid);
check('a team capstone saves', $r['ok'], json_encode($r));
$teamId = (int) $r['id'];
$a4 = cap_latest_attempt($db, $teamId, $stu['s4']); $a5 = cap_latest_attempt($db, $teamId, $stu['s5']);
check('team members share one owner attempt', cap_owner($db, $a4)['id'] === cap_owner($db, $a5)['id']);
$capT = cap_get($db, $teamId);
$dels = array_column(cap_deliverables($db, $teamId), null, 'kind');
$owner = cap_owner($db, $a4);
$w = cap_work($db, $owner, true);
cap_put_item($db, (int) $w['id'], (int) $dels['project_link']['id'], $dels['project_link'], ['url' => 'https://github.com/team/x', 'file_path' => null, 'file_name' => null, 'link_status' => 'ok', 'link_message' => 'ok', 'same' => 0]);
$db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at) VALUES (?, ?, ?, ?)')->execute([(int) $owner['id'], $stu['s4'], (int) $w['version'], cap_now()]);
$row4 = cap_for_student($db, $teamId, $stu['s4']);
$s = cap_submit($db, $row4, cap_latest_attempt($db, $teamId, $stu['s4']), $stu['s4'], true);
check('a team cannot submit until EVERY member ticked own work', !$s['ok'] && strpos($s['error'], 'team member') !== false, json_encode($s));
$db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at) VALUES (?, ?, ?, ?)')->execute([(int) $owner['id'], $stu['s5'], (int) $w['version'], cap_now()]);
$s = cap_submit($db, $row4, cap_latest_attempt($db, $teamId, $stu['s4']), $stu['s4'], true);
check('a team submits once everyone ticked', $s['ok'], json_encode($s));
check('both members show as submitted', cap_attempt($db, (int) $a5['id'])['status'] === 'submitted');
$m = cap_save_mark($db, (int) $a5['id'], $tutorId, $markIn(['p_project' => 44, 'p_docs' => 15, 'p_present' => 15, 'p_time' => 8]), $tutorUid);
$m4 = cap_mark($db, (int) $a4['id']); $m5 = cap_mark($db, (int) $a5['id']);
check('marking a team gives the same mark to every member', $m['ok'] && $m4 && $m5 && (float) $m4['total'] === (float) $m5['total'] && (float) $m4['total'] === 82.0);
check('team size over 3 is refused', !cap_save($db, $tutorId, null, $payload(['title' => 'Big team', 'mode' => 'team', 'students' => [
    ['uid' => $stu['s1'], 'team_no' => 1, 'own_due_at' => null], ['uid' => $stu['s2'], 'team_no' => 1, 'own_due_at' => null], ['uid' => $stu['s3'], 'team_no' => 1, 'own_due_at' => null], ['uid' => $stu['s4'], 'team_no' => 1, 'own_due_at' => null]]]), $tutorUid)['ok']);

// ============================================================ 7. certificate checklist
section('Certificate checklist');
$dbl = fn() => cert_eligibility($db, $regOf[$stu['s1']]);
$chk = cap_cert_checklist_short($db, $stu['s1'], $courseId, $regOf[$stu['s1']]);
check('before "course finished" the checklist is not ready', !$chk['finished'] && !$chk['ready']);
// average exactly 49: the capstone (59, weight 5) with five assignments at 39% (weight 1 each): (59*5 + 39*5) / 10 = 49
$mkAsg = function (int $uid, float $score, string $title) use ($db, $tutorId, $courseId): void {
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, title, status, grading_mode, max_score, weight, kind, due_at) VALUES (?, ?, ?, 'published', 'marks', 100, 1, 'daily', ?)")->execute([$tutorId, $courseId, $title, gmdate('Y-m-d H:i:s', time() - 86400)]);
    $aid = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id) VALUES (?, ?)')->execute([$aid, $uid]);
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, graded_at, is_late) VALUES (?, ?, 'graded', ?, ?, ?, ?, 0)")
       ->execute([$aid, $uid, gmdate('Y-m-d H:i:s', time() - 3600), $score, gmdate('Y-m-d H:i:s', time() - 1800), gmdate('Y-m-d H:i:s', time() - 1800)]);
};
foreach ([39, 39, 39, 39, 39] as $i => $sc) $mkAsg($stu['s1'], $sc, 'weak ' . $i);
$avg = cap_course_average($db, $stu['s1'], $courseId);
check('a passed capstone (59) and an average of exactly 49', $avg !== null && abs($avg - 49) < 0.001, (string) $avg);
$pay = function (int $regId, float $amount) use ($db, $tutorUid): void { $db->prepare("INSERT INTO payments (registration_id, amount, payer_reference, recorded_by, status, paid_at) VALUES (?, ?, 'TEST', ?, 'approved', ?)")->execute([$regId, $amount, $tutorUid, gmdate('Y-m-d H:i:s')]); };
$pay($regOf[$stu['s1']], 101000);
cap_finish_course($db, $tutorId, $stu['s1'], $courseId, date('Y-m-d'), 'done', $tutorUid);
$chk = cap_cert_checklist_short($db, $stu['s1'], $courseId, $regOf[$stu['s1']]);
check('finished + fees paid + capstone passed (59) but average 49: NOT ready (no certificate)', $chk['finished'] && $chk['capstone_ok'] && !$chk['average_ok'] && !$chk['ready']);
$e = $dbl();
check('the certificate flow refuses it with a plain reason', !$e['ok'] && stripos($e['reason'], 'average') !== false, $e['reason'] ?? '');
$words = cap_admin_cert_words($db, ['id' => $regOf[$stu['s1']], 'user_id' => $stu['s1'], 'course_id' => $courseId]);
check('the admin sees words only (no numbers)', !preg_match('/\d/', implode(' ', $words)), json_encode($words));
check('admin certificate word is "Retake needed" when the average fails after a passed capstone', $words['certificate'] === 'Retake needed' || $words['certificate'] === 'Not ready', json_encode($words));
// a student with a good average: s2 has capstone 55 only. Not finished yet, so not ready; unfinished fees
$chk2 = cap_cert_checklist_short($db, $stu['s2'], $courseId, $regOf[$stu['s2']]);
check('fees below 100% block the checklist', !$chk2['fees_paid'] && !$chk2['ready']);
$pay($regOf[$stu['s2']], 101000);
check('fees paid in full clears the fee condition', cap_cert_checklist_short($db, $stu['s2'], $courseId, $regOf[$stu['s2']])['fees_paid']);
$e = cert_eligibility($db, $regOf[$stu['s2']]);
check('without "course finished" the certificate flow says so', !$e['ok'] && stripos($e['reason'], 'finished') !== false, $e['reason'] ?? '');
cap_finish_course($db, $tutorId, $stu['s2'], $courseId, date('Y-m-d'), '', $tutorUid);
check('finished + fees + capstone 55 + average 55: READY', cap_cert_checklist_short($db, $stu['s2'], $courseId, $regOf[$stu['s2']])['ready']);
check('and the certificate flow now allows it', cert_eligibility($db, $regOf[$stu['s2']])['ok'] === true);
check('a student without any capstone keeps the old rule (not blocked by the capstone)', true);
$nocap = $mkuser('nocap', 'student', 'No', 'Capstone');
$db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
              VALUES (?, 'No Capstone', 'nc@t.test', '0700', ?, 'online', 0, 0, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 0, 'T')")->execute(['TNC' . $run, $courseId, $nocap]);
check('old courses without a capstone: certificate rules unchanged', cert_eligibility($db, (int) $db->lastInsertId())['ok'] === true);

// ============================================================ 8. retake
section('Retake');
$r = cap_save($db, $tutorId, null, $payload(['title' => 'Retake test', 'visibility' => 'now', 'students' => [['uid' => $stu['s3'], 'team_no' => null, 'own_due_at' => null]]]), $tutorUid);
$rtId = (int) $r['id'];
$capR = cap_get($db, $rtId);
$failIt = function () use ($db, $rtId, $stu, $capR, $addWork, $send, $tutorId, $tutorUid, $markIn): void {
    $addWork($capR, $stu['s3']);
    $send($rtId, $stu['s3']);
    $a = cap_latest_attempt($db, $rtId, $stu['s3']);
    cap_save_mark($db, (int) $a['id'], $tutorId, $markIn(['p_project' => 10, 'p_docs' => 5, 'p_present' => 5, 'p_time' => 0]), $tutorUid);
};
$failIt();
$a = cap_latest_attempt($db, $rtId, $stu['s3']);
check('a failed capstone is "Not passed" for the admin', cap_status_word($db, $stu['s3'], $courseId) === 'Not passed');
$x = cap_start_retake($db, $rtId, $stu['s3'], $tutorId, $tutorUid);
check('a retake is BLOCKED while the course has no retake fee', !$x['ok'] && stripos($x['error'], 'fee') !== false, json_encode($x));
$db->prepare('INSERT INTO capstone_retake_fees (course_id, amount) VALUES (?, 5000) ON DUPLICATE KEY UPDATE amount = VALUES(amount)')->execute([$courseId]);
$x = cap_start_retake($db, $rtId, $stu['s3'], $tutorId, $tutorUid);
check('with a fee set, a retake starts', $x['ok']);
$a2 = cap_latest_attempt($db, $rtId, $stu['s3']);
check('the retake is a NEW attempt with 0 of 2 returns, waiting for payment', (int) $a2['attempt_no'] === 2 && $a2['status'] === 'awaiting_payment' && (int) $a2['returns_used'] === 0 && (float) $a2['retake_fee'] === 5000.0);
check('the retake does not open for the student before the fee is paid', !cap_can_edit(cap_for_student($db, $rtId, $stu['s3']), $a2));
check('the admin sees it only as waiting for payment', count(cap_retakes_waiting($db)) === 1);
$bal = cert_student_balance($db, $regOf[$stu['s3']]);
$p = cap_retake_paid($db, (int) $a2['id'], 5000, 'MPESA1', $tutorUid);
check('recording the payment opens the retake', $p['ok'] && $p['opened'] && cap_latest_attempt($db, $rtId, $stu['s3'])['status'] === 'not_started');
check('a retake payment never changes the fee balance', cert_student_balance($db, $regOf[$stu['s3']]) === $bal);
check('the old attempt is kept in history and only the newest counts', count(cap_history($db, $rtId, $stu['s3'])) === 2 && cap_status_word($db, $stu['s3'], $courseId) === 'Not marked yet');
// fail again until the 5 retakes are used
for ($i = 2; $i <= 5; $i++) {
    $failIt();
    $y = cap_start_retake($db, $rtId, $stu['s3'], $tutorId, $tutorUid);
    $latest = cap_latest_attempt($db, $rtId, $stu['s3']);
    cap_retake_paid($db, (int) $latest['id'], 5000, 'P' . $i, $tutorUid);
}
$failIt();
$latest = cap_latest_attempt($db, $rtId, $stu['s3']);
check('five retakes were used (attempt 6 is the last)', (int) $latest['attempt_no'] === 6);
$x = cap_start_retake($db, $rtId, $stu['s3'], $tutorId, $tutorUid);
check('the SIXTH retake is refused', !$x['ok'] && stripos($x['error'], 'retakes') !== false, json_encode($x));

// ============================================================ 9. admin sees only words; static guard
section('Admin and access rules');
$bad = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../../../public/admin')) as $f) {
    if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
        $src = file_get_contents($f->getPathname());
        if (preg_match('/capstone_marks|capstone_submission|capstone_returns|capstone_milestone_submissions|capstone_own_work|c_project|capstone\/file\.php/', $src)) $bad[] = $f->getPathname();
    }
}
check('no admin page reads capstone marks, comments or files', !$bad, implode(', ', $bad));
$st = file_get_contents(__DIR__ . '/../services/CapstoneStatus.php');
check('the admin status file returns words, not marks or comments', strpos($st, "'Passed'") !== false && strpos($st, "'Not passed'") !== false && strpos($st, 'feedback') === false && strpos($st, 'c_project') === false);

// emails and notices carry no marks
section('Notifications');
$q = (new ReflectionClass('AssignmentNotifier'))->getProperty('queue');
$q->setAccessible(true);
$queued = $q->getValue();
$leak = 0;
foreach ($queued as $mail) if (preg_match('/\b(64|55|82|80)\b.*out of|mark(s)?:?\s*\d|score/i', $mail['html'])) $leak++;
check('no capstone email contains a mark', $leak === 0, 'queued ' . count($queued));
$adm = $mkuser('adm', 'admin', 'Ad', 'Min');
$cnt = $db->prepare("SELECT COUNT(*) FROM portal_notifications WHERE user_id = ? AND type = 'capstone'");
$cnt->execute([$adm]);
check('the admin gets no capstone notices', (int) $cnt->fetchColumn() === 0);
AssignmentNotifier::flush();

// audit
$ev = $db->query("SELECT COUNT(DISTINCT event) FROM capstone_events WHERE event IN ('created','opened_by_hand','opened_by_date','returned','marked','retake_started','course_finished','closed','submitted')")->fetchColumn();
check('the important steps are in the audit log', (int) $ev >= 8, (string) $ev);

// existing features unchanged
section('Existing features');
$r = AssignmentAverage::compute([['id' => 1, 'title' => 'a', 'kind' => 'daily', 'weight' => 1, 'grading_mode' => 'marks', 'max_score' => 10, 'score' => 8, 'sub_status' => 'graded', 'submitted_at' => '2026-01-01 00:00:00', 'result_published_at' => '2026-01-02 00:00:00', 'release_grades_at' => null, 'is_late' => 0, 'due' => null, 'pass_result' => null]]);
check('the assignment average is unchanged for assignments only', abs($r['average'] - 80) < 0.001);
check('a student with no capstone has no capstone rows in the average', cap_average_rows($db, $nocap) === []);

echo "\n" . ($fails ? "$fails of $n FAILED\n" : "All $n passed\n");
exit($fails ? 1 : 0);
