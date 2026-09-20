<?php
// Run: php app/v1/tests/payroll_calculator_test.php
require_once __DIR__ . '/../models/PayrollCalculator.php';
use Models\PayrollCalculator as C;

$fail = 0;
function check(string $name, $actual, $expected): void {
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) { $fail++; }
    echo ($ok ? "ok   " : "FAIL ") . $name . ($ok ? '' : ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true)) . "\n";
}

// 1-month course started in March: earns 5,000 once, then nothing.
$l = C::ledger([['start' => '2026-03-10', 'months' => 1, 'rate' => null]], [], '2026-08');
check('1 month course earns 5000', $l['earned_to_date'], 5000.0);
check('1 month course no longer earning', $l['active_students'], 0);

// 6-month course: earns every month until the course ends.
$l = C::ledger([['start' => '2026-03-10', 'months' => 6, 'rate' => null]], [], '2026-05');
check('6 month course, 3 months in', $l['earned_to_date'], 15000.0);
$l = C::ledger([['start' => '2026-03-10', 'months' => 6, 'rate' => null]], [], '2027-01');
check('6 month course stops after 6', $l['earned_to_date'], 30000.0);

// Student paused for April and May: the course runs on, ending two months later.
$pause = [['start' => '2026-04-01', 'end' => '2026-06-01']];
$l = C::ledger([['start' => '2026-03-10', 'months' => 6, 'rate' => null, 'pauses' => $pause]], [], '2026-06');
check('paused 2 months: earned 2 of 6 so far', $l['students'][0]['months_elapsed'], 2);
check('paused 2 months: ends in October', $l['students'][0]['end_month'], '2026-10');
$l = C::ledger([['start' => '2026-03-10', 'months' => 6, 'rate' => null, 'pauses' => $pause]], [], '2027-01');
check('paused 2 months: total still 6 months', $l['earned_to_date'], 30000.0);

// Open-ended pause: nothing more accrues.
$open = [['start' => '2026-04-01', 'end' => null]];
$l = C::ledger([['start' => '2026-03-10', 'months' => 6, 'rate' => null, 'pauses' => $open]], [], '2026-09');
check('open pause stops accrual', $l['earned_to_date'], 5000.0);
check('open pause flagged', $l['students'][0]['paused_now'], true);

// A short pause (under half a month) does not stop pay.
$l = C::ledger([['start' => '2026-03-01', 'months' => 6, 'rate' => null, 'pauses' => [['start' => '2026-04-03', 'end' => '2026-04-10']]]], [], '2026-04');
check('one week pause still earns the month', $l['earned_to_date'], 10000.0);

// Tutor on break in May pauses every student.
$students = [
    ['start' => '2026-03-01', 'months' => 6, 'rate' => null],
    ['start' => '2026-03-01', 'months' => 6, 'rate' => 7000],
];
$l = C::ledger($students, [], '2026-05', [['start' => '2026-05-01', 'end' => '2026-06-01']]);
check('tutor break: 2 months x 12000', $l['earned_to_date'], 24000.0);
check('tutor break: nobody earning in May', $l['active_students'], 0);

// Payments reduce the balance, with carry-forward.
$l = C::ledger([['start' => '2026-03-01', 'months' => 6, 'rate' => null]], [['amount' => 7000, 'month' => '2026-03']], '2026-04');
check('balance = 10000 - 7000', $l['balance'], 3000.0);
check('paid', $l['paid_to_date'], 7000.0);

// Fixed months (student taken off): earned never changes, later months earn nothing.
$l = C::ledger([['start' => '2026-03-01', 'fixed_months' => ['2026-03', '2026-04'], 'rate' => null]], [], '2026-12');
check('fixed months', $l['earned_to_date'], 10000.0);

// Employee: 60,000 a month, pay day 28, started 10 Jan.
$e = C::employeeLedger(60000, '2026-01-10', null, 28, [['amount' => 60000, 'month' => '2026-01']], '2026-03-15');
check('employee earned Jan+Feb', $e['earned_to_date'], 120000.0);
check('employee balance', $e['balance'], 60000.0);
check('employee next pay date', $e['next_pay_date'], '2026-03-28');
$e = C::employeeLedger(60000, '2026-01-30', null, 28, [], '2026-02-27');
check('employee started after pay day: nothing yet', $e['earned_to_date'], 0.0);
$e = C::employeeLedger(60000, '2026-01-01', '2026-02-10', 28, [], '2026-06-01');
check('employee left before Feb pay day', $e['earned_to_date'], 60000.0);

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
