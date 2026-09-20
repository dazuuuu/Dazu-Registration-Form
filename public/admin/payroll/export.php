<?php
// Downloads the salary sheet as a CSV file that opens in Excel.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/EmployeeModel.php';

use Models\Connection;
use Models\PayrollModel;
use Models\EmployeeModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$sheet = ($_GET['sheet'] ?? 'tutors') === 'employees' ? 'employees' : 'tutors';

// A cell that starts with = + - @ would run as a formula in Excel, so it is prefixed with an apostrophe.
$safe = function ($v): string {
    $v = (string) $v;
    return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;
};

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="salaries-' . $sheet . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

if ($sheet === 'tutors') {
    $payroll = new PayrollModel($pdo);
    fputcsv($out, ['Tutor', 'Assigned students', 'Student names', 'Status', 'Earnings (total)', 'Paid', 'Balance'], ',', '"', '');
    foreach ($payroll->isInstalled() ? $payroll->listTutorSummaries() : [] as $t) {
        fputcsv($out, [
            $safe($t['full_name']), $t['student_count'], $safe(implode('; ', array_column($t['students'], 'name'))),
            $t['status'] ? PayrollModel::PAUSE_KINDS[$t['status']['kind']] : 'Active',
            $t['earned_to_date'], $t['paid_to_date'], $t['balance'],
        ], ',', '"', '');
    }
} else {
    $employees = new EmployeeModel($pdo);
    fputcsv($out, ['Employee', 'Role', 'Salary / month', 'Pay day', 'Earnings (total)', 'Paid', 'Balance', 'Next pay date'], ',', '"', '');
    foreach ($employees->isInstalled() ? $employees->listWithBalances() : [] as $e) {
        fputcsv($out, [
            $safe($e['full_name']), $safe($e['job_title']), $e['monthly_salary'], $e['pay_day'],
            $e['earned_to_date'], $e['paid_to_date'], $e['balance'], $e['next_pay_date'] ?? '',
        ], ',', '"', '');
    }
}
fclose($out);
