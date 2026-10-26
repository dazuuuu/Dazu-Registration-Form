<?php
// Run: php app/v1/tests/accounting_test.php
// Pure checks (no database): repeating expenses, reading a receipt's total, request items, profit or loss.
require_once __DIR__ . '/../models/ExpenseModel.php';
require_once __DIR__ . '/../services/ReceiptReader.php';
require_once __DIR__ . '/../services/AccountingService.php';

use Models\ExpenseModel as E;

$fail = 0;
function check(string $name, $actual, $expected): void {
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) { $fail++; }
    echo ($ok ? "ok   " : "FAIL ") . $name . ($ok ? '' : ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true)) . "\n";
}

// Repeating expenses
$rent = ['start_date' => '2026-01-31', 'end_date' => null, 'repeat_every' => 'monthly'];
check('monthly rent on the 31st falls on the last day of February', E::occurrences($rent, '2026-02-01', '2026-02-28'), ['2026-02-28']);
check('monthly rent counted once a month', count(E::occurrences($rent, '2026-01-01', '2026-12-31')), 12);
check('monthly rent not before it started', E::occurrences($rent, '2025-12-01', '2025-12-31'), []);
check('stopped rent not counted after the end date', count(E::occurrences(array_merge($rent, ['end_date' => '2026-04-15']), '2026-01-01', '2026-12-31')), 3);
check('daily expense every day of a 30-day month', count(E::occurrences(['start_date' => '2026-01-01', 'end_date' => null, 'repeat_every' => 'daily'], '2026-04-01', '2026-04-30')), 30);
check('weekly expense keeps its weekday', E::occurrences(['start_date' => '2026-10-05', 'end_date' => null, 'repeat_every' => 'weekly'], '2026-10-10', '2026-10-25'), ['2026-10-12', '2026-10-19']);
check('one-time expense only on its day', E::occurrences(['start_date' => '2026-10-05', 'end_date' => null, 'repeat_every' => 'once'], '2026-10-01', '2026-10-31'), ['2026-10-05']);
check('one-time expense outside the range', E::occurrences(['start_date' => '2026-09-05', 'end_date' => null, 'repeat_every' => 'once'], '2026-10-01', '2026-10-31'), []);

// Request items and the "when"
$items = E::cleanItems(['Printer paper', '', 'Toner'], [2, 1, 1], ['650', '99', '3,500']);
check('empty rows skipped, totals worked out', array_column($items, 'total'), [1300.0, 3500.0]);
check('today', E::neededOn('today'), E::today());
check('a past date is refused', E::neededOn('date', '2020-01-01'), null);

// Receipt totals
check('total line', ReceiptReader::amountFromText("Bread 60.00\nMilk 55.00\nSUBTOTAL 115.00\nVAT 18.40\nTOTAL KES 133.40\nCash 200.00"), 133.4);
check('total in the next column', ReceiptReader::amountFromText("Item  Qty  Price\nPaper 2 650\nGrand Total\nKSh 1,300.00"), 1300.0);
check('amount paid wins over sub total', ReceiptReader::amountFromText("Sub total 2,000\nDiscount 200\nAmount paid 1,800"), 1800.0);
check('M-Pesa message', ReceiptReader::amountFromText("QJK3XY Confirmed. Ksh2,450.00 paid to NAIVAS THIKA. on 20/10/26"), 2450.0);
check('no clear total = no guess', ReceiptReader::amountFromText("Thank you for shopping\nCall 0722 000 000"), null);
$pdf = "%PDF-1.4\n1 0 obj<< /Length 60 /Filter /FlateDecode >>\nstream\n" . gzcompress("BT (Paper 650.00) Tj T* (TOTAL KES 1,950.00) Tj ET") . "\nendstream\nendobj\n%%EOF";
check('PDF receipt text is read', ReceiptReader::amountFromText(ReceiptReader::pdfText($pdf)), 1950.0);

// Profit or loss by branch
$sum = AccountingService::summarise([
    ['date' => '2026-10-01', 'kind' => 'in', 'type' => 'School fees', 'branch' => 'thika', 'amount' => 50000.0],
    ['date' => '2026-10-02', 'kind' => 'in', 'type' => 'School fees', 'branch' => 'online', 'amount' => 20000.0],
    ['date' => '2026-10-03', 'kind' => 'out', 'type' => 'Expenses', 'branch' => 'thika', 'amount' => 30000.0, 'category' => 'rent'],
    ['date' => '2026-10-04', 'kind' => 'out', 'type' => 'Expenses', 'branch' => 'nairobi', 'amount' => 45000.0, 'category' => 'rent'],
    ['date' => '2026-10-05', 'kind' => 'out', 'type' => 'Tutor shares', 'branch' => 'general', 'amount' => 10000.0],
]);
check('money in', $sum['in'], 70000.0);
check('money out', $sum['out'], 85000.0);
check('loss for the month', [$sum['net'], $sum['result']], [-15000.0, 'Loss']);
check('Thika made a profit', $sum['by_branch']['thika']['net'], 20000.0);
check('Nairobi spent the most', array_search(max(array_column($sum['by_branch'], 'out')), array_combine(array_keys($sum['by_branch']), array_column($sum['by_branch'], 'out'))), 'nairobi');
check('online fees counted for Online', $sum['by_branch']['online']['in'], 20000.0);
check('fee branch: online student', AccountingService::feeBranch(['study_mode' => 'online', 'study_location' => 'thika']), 'online');
check('fee branch: Nairobi student', AccountingService::feeBranch(['study_mode' => 'physical', 'study_location' => 'nairobi']), 'nairobi');
check('fee branch: no location', AccountingService::feeBranch(['study_mode' => 'physical', 'study_location' => null]), 'unset');

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
