<?php
// Run: php app/v1/tests/assignment_average_test.php
require_once __DIR__ . '/../services/AssignmentAverage.php';

$fail = 0;
function check(string $name, $actual, $expected): void {
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) { $fail++; }
    echo ($ok ? "ok   " : "FAIL ") . $name . ($ok ? '' : ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true)) . "\n";
}

$now = strtotime('2026-10-22 12:00:00 UTC');
function row(array $o): array {
    static $id = 0;
    return $o + [
        'id' => ++$id, 'title' => 'A' . $id, 'kind' => 'daily', 'weight' => 1, 'grading_mode' => 'marks', 'max_score' => 20,
        'score' => null, 'pass_result' => null, 'sub_status' => 'graded', 'submitted_at' => '2026-10-20 08:00:00',
        'result_published_at' => '2026-10-21 08:00:00', 'release_grades_at' => null, 'is_late' => 0, 'due' => '2026-10-20 14:00:00',
    ];
}

// 1. Only released marks count; held marks are not counted and not shown.
$r = AssignmentAverage::compute([row(['score' => 10]), row(['score' => 20, 'release_grades_at' => '2026-10-23 06:00:00'])], $now);
check('held mark not counted', $r['average'], 50.0);
check('held mark not shown', $r['items'][1]['score'], null);
check('held mark listed as not counted yet', $r['not_counted'][0]['reason'], 'held');
check('release time passed: counted', AssignmentAverage::compute([row(['score' => 20, 'release_grades_at' => '2026-10-22 11:00:00'])], $now)['average'], 100.0);
check('draft grade (not published) not counted', AssignmentAverage::compute([row(['score' => 20, 'sub_status' => 'submitted', 'result_published_at' => null])], $now)['display'], 'No marks yet');

// 2 + 3. Percent per assignment, weighted average, exact value kept, display rounded.
$r = AssignmentAverage::compute([row(['score' => 15]), row(['score' => 8, 'max_score' => 10, 'weight' => 2])], $now);
check('weighted average exact', round($r['average'], 6), round((75 + 80 * 2) / 3, 6));
check('display rounds to whole percent', $r['display'], '78%');
$r = AssignmentAverage::compute([row(['score' => 1, 'max_score' => 3])], $now);
check('exact value not rounded', $r['average'] > 33.33 && $r['average'] < 33.34, true);

// 4. Pass or Not yet and Feedback only do not count but still show.
$r = AssignmentAverage::compute([row(['score' => 10]), row(['grading_mode' => 'pass', 'pass_result' => 'pass']), row(['grading_mode' => 'feedback', 'pass_result' => 'reviewed'])], $now);
check('pass / feedback not counted', $r['average'], 50.0);
check('pass / feedback still listed', count($r['items']), 3);
check('counted count', $r['counted'], 1);

// 5. Practical (no submission) marks count the same way.
check('practical counts', AssignmentAverage::compute([row(['score' => 18, 'submitted_at' => null])], $now)['average'], 90.0);

// 6. Resubmission uses the newest mark: the row holds the newest grade; while waiting for the regrade it is not counted.
check('returned work not counted', AssignmentAverage::compute([row(['score' => 5, 'sub_status' => 'returned', 'result_published_at' => null])], $now)['counted'], 0);
check('newest mark used', AssignmentAverage::compute([row(['score' => 19])], $now)['average'], 95.0);

// 7. Late work is not reduced.
check('late not reduced', AssignmentAverage::compute([row(['score' => 20, 'is_late' => 1])], $now)['average'], 100.0);

// 8. Missing work is not zero, unless the tutor gave 0.
$r = AssignmentAverage::compute([row(['score' => 10]), row(['sub_status' => null, 'submitted_at' => null, 'result_published_at' => null])], $now);
check('missing not counted as zero', $r['average'], 50.0);
check('missing labelled', $r['items'][1]['status'], 'missing');
check('not yet due is not missing', AssignmentAverage::compute([row(['sub_status' => null, 'submitted_at' => null, 'result_published_at' => null, 'due' => '2026-10-30 14:00:00'])], $now)['items'][0]['status'], 'not_due');
check('tutor gave 0: counted', AssignmentAverage::compute([row(['score' => 10]), row(['score' => 0])], $now)['average'], 25.0);

// 9. Nothing counted shows "No marks yet".
check('no marks yet', AssignmentAverage::compute([], $now)['display'], 'No marks yet');

// Daily and weekly averages.
$r = AssignmentAverage::compute([row(['score' => 10]), row(['score' => 20, 'kind' => 'weekly'])], $now);
check('daily average', $r['by_kind']['daily'], 50.0);
check('weekly average', $r['by_kind']['weekly'], 100.0);
check('reserved kind counts through weight', AssignmentAverage::compute([row(['score' => 20, 'kind' => 'capstone', 'weight' => 3]), row(['score' => 0])], $now)['average'], 75.0);

// 10. No certificate code reads assignments or the average.
$hits = [];
foreach (array_merge(glob(__DIR__ . '/../services/Certificate*.php'), glob(__DIR__ . '/../helpers/certificate*.php'), glob(__DIR__ . '/../../../public/*/certificates/*.php') ?: []) as $f) {
    if (preg_match('/assignment|AssignmentAverage/i', (string) file_get_contents($f))) $hits[] = basename($f);
}
check('certificate flow does not read assignments', $hits, []);

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
