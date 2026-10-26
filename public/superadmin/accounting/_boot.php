<?php
// Common start for the superadmin Accounting pages (overview, requests, expenses, statement).
// Gives $pdo, $ex (ExpenseModel), $acc (AccountingService), $me, $e() for escaping, acc_back(), acc_tabs().
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/ExpenseModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollRequestModel.php';
require_once __DIR__ . '/../../../app/v1/services/AccountingService.php';

use Models\Connection;
use Models\ExpenseModel;

Middleware::requireSuperPortal();
$pdo = Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
$ex = new ExpenseModel($pdo);
$acc = new AccountingService($pdo);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

try {
    $pdo->query("SELECT 1 FROM expenses LIMIT 0");
    $accInstalled = true;
} catch (Throwable $t) {
    $accInstalled = false;
}

function acc_back(string $to, ?string $ok, ?string $err = null): void
{
    if ($ok) SessionModel::setFlash('success', htmlspecialchars($ok));
    if ($err) SessionModel::setFlash('error', htmlspecialchars($err));
    header('Location: ' . $to);
    exit();
}

/** Tab bar shared by the Accounting pages; $pending: counts to show on Requests. */
function acc_tabs(string $on, int $pending = 0): string
{
    $tabs = ['index.php' => ['Overview', 'fa-chart-pie'], 'requests.php' => ['Requests', 'fa-inbox'], 'expenses.php' => ['Expense tracker', 'fa-receipt'],
             'statement.php' => ['Statement', 'fa-file-invoice-dollar']];
    $h = '<div class="d-flex flex-wrap gap-2 mb-3">';
    foreach ($tabs as $file => [$label, $icon]) {
        $h .= '<a href="' . $file . '" class="btn btn-sm ' . ($on === $file ? 'btn-primary' : 'btn-outline-secondary') . '"><i class="fas ' . $icon . ' me-1"></i>' . $label
            . ($file === 'requests.php' && $pending ? ' <span class="badge bg-danger ms-1">' . $pending . '</span>' : '') . '</a>';
    }
    return $h . '</div>';
}

/** Pending requests waiting for the superadmin (branch purchases + tutor share corrections). */
function acc_pending(PDO $pdo): int
{
    try {
        return (int) $pdo->query("SELECT (SELECT COUNT(*) FROM expense_requests WHERE status = 'pending') + (SELECT COUNT(*) FROM tutor_payroll_requests WHERE status = 'pending')")->fetchColumn();
    } catch (Throwable $t) {
        return 0;
    }
}

/** Date range from the query string: ?month=YYYY-MM, or ?from=&to=. Default: this month. @return array{0:string,1:string,2:string} */
function acc_range(): array
{
    $isDate = fn($v) => is_string($v) && \DateTimeImmutable::createFromFormat('!Y-m-d', $v) !== false;
    if ($isDate($_GET['from'] ?? null) && $isDate($_GET['to'] ?? null) && $_GET['from'] <= $_GET['to']) {
        return [$_GET['from'], $_GET['to'], date('j M Y', strtotime($_GET['from'])) . ' – ' . date('j M Y', strtotime($_GET['to']))];
    }
    $ym = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? $_GET['month'] : substr(ExpenseModel::today(), 0, 7);
    [$f, $t] = AccountingService::monthRange($ym);
    return [$f, $t, date('F Y', strtotime($f))];
}
