<?php
// Run against a THROWAWAY database only (it writes rows):
//   ASG_TEST_DSN="mysql:host=localhost;dbname=dazu_asg_e2e" ASG_TEST_USER=root php app/v1/tests/assignment_flow_test.php
// Needs migrations 065, 066, 083 and the users/tutors/courses/registrations/tutor_students tables with
// tutor 1 (users.id 1) teaching students 11, 12, 13 in course 1, and tutor 2 (users.id 2) with no students.
// Drives the model directly: create, publish, lock, submit, Late, held grades, release, return, resubmit, average,
// groups, practical, quiz, reminders, nudges, copy, permissions.
require_once __DIR__ . '/../models/AssignmentModel.php';
require_once __DIR__ . '/../services/AssignmentAverage.php';

use Models\AssignmentModel as M;

$dsn = getenv('ASG_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'test') && !str_contains($dsn, 'e2e')) exit("Set ASG_TEST_DSN to a throwaway database (its name must contain 'test' or 'e2e').\n");
$db = new PDO($dsn, getenv('ASG_TEST_USER') ?: 'root', getenv('ASG_TEST_PASS') ?: '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$am = new M($db);

$fail = 0;
function check(string $name, $actual, $expected): void {
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) { $fail++; }
    echo ($ok ? "ok   " : "FAIL ") . $name . ($ok ? '' : ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true)) . "\n";
}
$base = ['course_id' => 1, 'description' => 'd', 'details' => '<p>x</p>', 'instructions' => null, 'sequential_lock' => 1, 'close_after_due' => 0,
         'max_extension_days' => 7, 'submit_link' => 0, 'submit_text' => 1, 'submit_images' => 0, 'submit_file' => 0, 'link_sites' => null,
         'grading_mode' => 'marks', 'max_score' => 20, 'pass_mark_percent' => 50];
$make = function (array $over, array $students = [11, 12, 13]) use ($am, $base) {
    $d = $over + $base + ['title' => 'T', 'due_at' => gmdate('Y-m-d H:i:s', time() + 86400)];
    $id = $am->save(1, null, $d, [], [], $students, 1)['id'];
    $am->publish($id, 1);
    return $id;
};
$send = function (int $id, int $uid, string $text = 'my answer') use ($am) {
    $am->saveDraft($id, $uid, [], [0 => $text], null);
    return $am->submit($am->itemForStudent($uid, $id), $uid);
};
$grade = function (int $id, int $uid, $score, string $action = 'publish') use ($am) {
    $ctx = $am->submissionForGrader((int) $am->submission($id, $uid)['id'], 1);
    return $am->grade($ctx, ['score' => $score, 'why' => 'Because.', 'improve' => 'More detail.', 'return_items' => ['Add 3 examples'], 'resubmit_by' => null], $action, 1);
};
$avgOf = fn(int $uid) => AssignmentAverage::forStudent($db, $uid)[1] ?? null;

// Old data: an assignment made before the update is Daily, keeps Return, and is in the average as before.
$old = $make(['title' => 'Old one']);
$db->exec("UPDATE tutor_assignments SET allow_resubmission = NULL WHERE id = $old");
check('old assignment is daily', $am->get($old)['kind'], 'daily');
check('old assignment keeps Return', M::allowsReturn($am->get($old)), true);

// Daily and weekly keep their own order and lock.
$d1 = $old;
$d2 = $make(['title' => 'Day 2']);
$w1 = $make(['title' => 'Week 1', 'kind' => 'weekly', 'weight' => 2]);
check('daily 2 is locked until daily 1 is sent', $am->itemForStudent(11, $d2)['state'], 'locked');
check('weekly 1 is not locked by daily work', $am->itemForStudent(11, $w1)['state'], 'open');
check('weekly numbered on its own', $am->number($am->get($w1)), 1);

// Submit, then the next daily unlocks.
check('submit ok', $send($d1, 11)['ok'], true);
check('daily 2 unlocks', $am->itemForStudent(11, $d2)['state'], 'open');

// Late is only a label, judged against the student's own due date.
$late = $make(['title' => 'Late one', 'sequential_lock' => 0, 'due_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);
$am->setStudentDue($late, 12, gmdate('Y-m-d H:i:s', time() + 3600), 1, 'due_override');
$send($late, 11); $send($late, 12);
check('sent after due = Late', (int) $am->submission($late, 11)['is_late'], 1);
check('own later due date = not Late', (int) $am->submission($late, 12)['is_late'], 0);
$grade($late, 11, 20);
check('Late work is not reduced', (float) $am->submission($late, 11)['score'], 20.0);

// Held grades: hidden from the student (page data and average), shown at release.
$held = $make(['title' => 'Held', 'sequential_lock' => 0, 'release_grades_at' => gmdate('Y-m-d H:i:s', time() + 86400)]);
$send($held, 11);
$grade($held, 11, 4);
$it = $am->itemForStudent(11, $held);
check('held: student state is Submitted', $it['state'], 'submitted');
check('held: score removed for the student', $it['score'], null);
check('held: student cannot change graded work', M::canEdit($it), false);
check('held: tutor still sees it', $am->chain(11, 1, 1, true)[array_search($held, array_column($am->chain(11, 1, 1, true), 'id'))]['state'], 'graded');
$before = $avgOf(11)['average'];
check('held: not in the average', in_array('held', array_column($avgOf(11)['not_counted'], 'reason'), true), true);
$am->releaseGradesNow($held, 1);
$rel = $am->dueGradeReleases();
check('release: assignment is due for notices', in_array($held, array_map(fn($x) => (int) $x['id'], $rel), true), true);
$ha = $am->get($held);
check('release: one student to tell', count($am->heldGradedSubmissions($ha)), 1);
check('release: claimed once', [$am->markReleaseNotified($ha), $am->markReleaseNotified($ha)], [true, false]);
check('release: student sees the mark', $am->itemForStudent(11, $held)['state'], 'graded');
check('release: average changed', $avgOf(11)['average'] !== $before, true);

// Return and resubmit: never Late because of the return, keeps the first sent time, newest mark counts.
$rs = $make(['title' => 'Resub', 'sequential_lock' => 0, 'allow_resubmission' => 1]);
$send($rs, 13, 'v1');
$first = $am->submission($rs, 13)['submitted_at'];
check('return ok', $grade($rs, 13, 5, 'return')['ok'], true);
check('returned state', $am->itemForStudent(13, $rs)['state'], 'returned');
check('what to fix saved', json_decode((string) $am->submission($rs, 13)['return_items'], true), ['Add 3 examples']);
$db->exec("UPDATE tutor_assignments SET due_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = $rs");
$r = $send($rs, 13, 'v2');
check('resubmit ok', [$r['ok'], $r['version']], [true, 2]);
check('resubmission not Late', (int) $am->submission($rs, 13)['is_late'], 0);
check('first sent time kept', $am->submission($rs, 13)['first_submitted_at'], $first);
check('both versions in history', count($am->versions((int) $am->submission($rs, 13)['id'], 'submission')), 2);
check('returned work not in average', in_array($rs, array_column(array_filter($avgOf(13)['items'], fn($i) => $i['counts']), 'id'), true), false);
$grade($rs, 13, 18);
check('newest mark counts', (float) array_values(array_filter($avgOf(13)['items'], fn($i) => $i['id'] === $rs))[0]['score'], 18.0);
$noRet = $make(['title' => 'No return', 'allow_resubmission' => 0]);
check('switch off hides Return', M::allowsReturn($am->get($noRet)), false);

// Practical marks and quiz.
$prac = $make(['title' => 'Practical', 'sequential_lock' => 0, 'no_submission' => 1, 'submit_text' => 0]);
check('practical mark saved', $am->recordPractical($am->get($prac), 12, '15', 1)['ok'], true);
check('practical counts in the average', in_array($prac, array_column(array_filter($avgOf(12)['items'], fn($i) => $i['counts']), 'id'), true), true);
check('practical shows as done', $am->itemForStudent(12, $prac)['state'], 'graded');
$quiz = $make(['title' => 'Quiz', 'sequential_lock' => 0, 'submit_quiz' => 1, 'submit_text' => 0]);
$am->saveQuizQuestions($quiz, [[0, 'mcq', 'Pick B', ['A', 'B', 'C'], 1, 2], [0, 'truefalse', 'Sky is blue', ['True', 'False'], 0, 1]]);
$q = $am->quizQuestions($quiz);
$am->saveQuizAnswers($quiz, 12, [$q[0]['id'] => 1, $q[1]['id'] => 1]);
check('quiz marked by the system', $am->quizScore($quiz, (int) $am->submission($quiz, 12)['id']), ['got' => 2.0, 'of' => 3.0]);
check('quiz-only work can be sent', $am->submit($am->itemForStudent(12, $quiz), 12)['ok'], true);

// Groups: one submission, same mark for every member, only one row to grade.
$grp = $make(['title' => 'Group', 'sequential_lock' => 0, 'group_size' => 3]);
$am->saveGroups($grp, [11 => 1, 12 => 1, 13 => 0]);
check('group members', $am->groupMembers($grp, 12), [11, 12]);
$owner = $am->workOwner($grp, 12);
$am->saveDraft($grp, $owner, [], [0 => 'group work'], null);
$am->submit($am->itemForStudent($owner, $grp), $owner);
$am->mirrorGroupSubmission($grp, $owner);
check('member counts as submitted', $am->itemForStudent(12, $grp)['state'], 'submitted');
check('only one group row to grade', count(array_filter($am->queue(1, 'awaiting'), fn($r) => (int) $r['assignment_id'] === $grp)), 1);
$grade($grp, 11, 16);
$am->mirrorGroupGrade($grp, 11);
check('member gets the same mark', (float) $am->submission($grp, 12)['score'], 16.0);

// Reminders: only students who have not submitted, never twice.
$rem = $make(['title' => 'Remind', 'sequential_lock' => 0, 'remind_enabled' => 1, 'due_at' => gmdate('Y-m-d H:i:s', time() + 1800)]);
$send($rem, 11);
$due = array_values(array_filter($am->remindersDue('1h'), fn($r) => (int) $r['assignment_id'] === $rem));
check('reminder skips the student who submitted', array_map(fn($r) => (int) $r['student_user_id'], $due), [12, 13]);
check('reminder claimed once', [$am->claimReminder($rem, 12, '1h'), $am->claimReminder($rem, 12, '1h')], [true, false]);
check('old 24h reminder does not double up', count(array_filter($am->dueSoon(), fn($r) => (int) $r['assignment_id'] === $rem)), 0);

// Nudges: one per student per assignment every 6 hours.
check('nudge limit', [$am->recordNudge($rem, 12, 1, 1), $am->recordNudge($rem, 12, 1, 1)], [true, false]);

// Copy to the next intake: no due date, no students, no work or marks.
$copy = $am->copyAssignment($rs, 1, 1, false, 1);
$c = $am->get($copy);
check('copy is a draft without due date', [$c['status'], $c['due_at'], $am->recipientIds($copy)], ['draft', null, []]);
check('copy has no student work', $am->hasSubmissions($copy), false);
$tpl = $am->copyAssignment($rs, 1, null, true, 1);
check('template hidden from the list', in_array($tpl, array_map(fn($x) => (int) $x['id'], $am->listAll(1)), true), false);

// Permissions.
check('another tutor cannot open the assignment', $am->getForTutor($rs, 2), null);
check('another tutor cannot grade the work', $am->submissionForGrader((int) $am->submission($rs, 13)['id'], 2), null);
check('a student cannot open work not given to them', $am->itemForStudent(13, $am->save(1, null, ['title' => 'Only 11', 'due_at' => null] + $base, [], [], [11], 1)['id']), null);

// No row was deleted: every submission sent above is still there.
check('no submissions lost', (int) $db->query("SELECT COUNT(*) FROM tutor_assignment_submissions WHERE submitted_at IS NOT NULL")->fetchColumn(), 9);

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
