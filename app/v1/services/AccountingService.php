<?php
// app/v1/services/AccountingService.php
// Money in and out for the superadmin's Accounting pages. Reads only; it never changes a payment.
//  In:  school fees (approved fee payments, by the day they were paid).
//  Out: tutor shares paid (tutor_payments), employee salaries paid (employee_payments), and expenses (the tracker,
//       which also holds every branch purchase once its receipt is in).
//  Branch of a fee: Online for online students, else the student's study location (Thika / Nairobi), else "Not set".
//  Tutor shares and salaries belong to the whole institute; expenses carry their own branch.
require_once __DIR__ . '/../models/ExpenseModel.php';
require_once __DIR__ . '/../models/PayrollModel.php';
require_once __DIR__ . '/../models/EmployeeModel.php';

use Models\ExpenseModel;
use Models\PayrollModel;
use Models\EmployeeModel;

class AccountingService
{
    public const BRANCHES = ['thika' => 'Thika', 'nairobi' => 'Nairobi', 'online' => 'Online', 'general' => 'Whole institute', 'unset' => 'Branch not set'];

    private PDO $db;
    private ExpenseModel $ex;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ex = new ExpenseModel($db);
    }

    /** First and last day of a month 'YYYY-MM'. */
    public static function monthRange(string $ym): array
    {
        $d = new DateTimeImmutable($ym . '-01');
        return [$d->format('Y-m-d'), $d->format('Y-m-t')];
    }

    /** Every money line in the range, oldest first: [date, kind in|out, type, title, branch, amount, who]. */
    public function lines(string $from, string $to): array
    {
        $out = [];
        $st = $this->db->prepare(
            "SELECT DATE(p.paid_at) AS d, p.amount, p.receipt_no, r.full_name, r.registration_no, r.study_mode, r.study_location, c.title AS course
             FROM payments p JOIN registrations r ON r.id = p.registration_id LEFT JOIN courses c ON c.id = r.course_id
             WHERE p.status = 'approved' AND DATE(p.paid_at) BETWEEN ? AND ?"
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $out[] = ['date' => $p['d'], 'kind' => 'in', 'type' => 'School fees', 'title' => trim($p['full_name'] . ' · ' . ($p['course'] ?? '')),
                      'branch' => self::feeBranch($p), 'amount' => (float) $p['amount'], 'who' => (string) $p['registration_no']];
        }
        try {
            $st = $this->db->prepare(
                "SELECT tp.paid_on AS d, tp.amount, tp.paid_for_month, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS name
                 FROM tutor_payments tp JOIN tutors t ON t.id = tp.tutor_id JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
                 WHERE tp.paid_on BETWEEN ? AND ?"
            );
            $st->execute([$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $out[] = ['date' => $p['d'], 'kind' => 'out', 'type' => 'Tutor shares', 'title' => $p['name'] . ' · for ' . date('M Y', strtotime($p['paid_for_month'])),
                          'branch' => 'general', 'amount' => (float) $p['amount'], 'who' => 'Tutor'];
            }
            $st = $this->db->prepare(
                "SELECT ep.paid_on AS d, ep.amount, ep.paid_for_month, e.job_title, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS name
                 FROM employee_payments ep JOIN employees e ON e.id = ep.employee_id JOIN users u ON u.id = e.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
                 WHERE ep.paid_on BETWEEN ? AND ?"
            );
            $st->execute([$from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $out[] = ['date' => $p['d'], 'kind' => 'out', 'type' => 'Salaries', 'title' => $p['name'] . ' (' . $p['job_title'] . ') · for ' . date('M Y', strtotime($p['paid_for_month'])),
                          'branch' => 'general', 'amount' => (float) $p['amount'], 'who' => $p['job_title']];
            }
        } catch (Throwable $e) {
            // payroll not installed yet
        }
        foreach ($this->ex->expenseLines($from, $to) as $e) {
            $out[] = ['date' => $e['date'], 'kind' => 'out', 'type' => 'Expenses', 'title' => $e['title'] . ' · ' . (ExpenseModel::CATEGORIES[$e['category']] ?? $e['category']),
                      'branch' => $e['branch'], 'amount' => $e['amount'], 'who' => ExpenseModel::REPEATS[$e['repeat']] ?? '', 'category' => $e['category']];
        }
        usort($out, fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['kind'], $b['kind']));
        return $out;
    }

    public static function feeBranch(array $reg): string
    {
        if (($reg['study_mode'] ?? '') === 'online') return 'online';
        return in_array($reg['study_location'] ?? '', ['thika', 'nairobi'], true) ? $reg['study_location'] : 'unset';
    }

    /** Totals for the range (pure, from lines()). */
    public static function summarise(array $lines): array
    {
        $s = ['in' => 0.0, 'out' => 0.0, 'by_type' => [], 'by_branch' => [], 'by_category' => []];
        foreach (array_keys(self::BRANCHES) as $b) $s['by_branch'][$b] = ['in' => 0.0, 'out' => 0.0];
        foreach ($lines as $l) {
            $s[$l['kind']] += $l['amount'];
            $s['by_type'][$l['type']] = ($s['by_type'][$l['type']] ?? 0) + $l['amount'];
            $s['by_branch'][$l['branch']][$l['kind']] += $l['amount'];
            if (!empty($l['category'])) $s['by_category'][$l['category']] = ($s['by_category'][$l['category']] ?? 0) + $l['amount'];
        }
        foreach ($s['by_branch'] as &$b) $b['net'] = $b['in'] - $b['out'];
        unset($b);
        arsort($s['by_category']);
        $s['net'] = $s['in'] - $s['out'];
        $s['result'] = $s['net'] > 0.005 ? 'Profit' : ($s['net'] < -0.005 ? 'Loss' : 'Break even');
        return $s;
    }

    /** In, out and net for each of the last $n months, oldest first. */
    public function trend(int $n = 12): array
    {
        $out = [];
        $now = new DateTimeImmutable(ExpenseModel::today());
        for ($i = $n - 1; $i >= 0; $i--) {
            $ym = $now->modify('first day of this month')->modify("-$i months")->format('Y-m');
            [$f, $t] = self::monthRange($ym);
            $sum = self::summarise($this->lines($f, $t));
            $out[] = ['month' => $ym, 'in' => $sum['in'], 'out' => $sum['out'], 'net' => $sum['net']];
        }
        return $out;
    }

    /** Money still owed or held: to tutors, to employees, by students, and cash given to branches not yet accounted for. */
    public function balances(): array
    {
        $b = ['tutors' => 0.0, 'tutor_rows' => [], 'employees' => 0.0, 'employee_rows' => [], 'fees_owed' => 0.0, 'cash_out' => 0.0, 'cash_rows' => [],
              'branch_change' => 0.0];
        try {
            $pm = new PayrollModel($this->db);
            if ($pm->isInstalled()) {
                foreach ($pm->listTutorSummaries() as $t) {
                    $b['tutor_rows'][] = ['name' => $t['full_name'], 'balance' => (float) $t['balance'], 'students' => (int) $t['student_count']];
                    $b['tutors'] += max(0, (float) $t['balance']);
                }
            }
            $em = new EmployeeModel($this->db);
            if ($em->isInstalled()) {
                foreach ($em->listWithBalances() as $e) {
                    $bal = (float) ($e['balance'] ?? 0);
                    $b['employee_rows'][] = ['name' => $e['full_name'] ?? ($e['username'] ?? 'Employee'), 'role' => $e['job_title'] ?? '', 'balance' => $bal];
                    $b['employees'] += max(0, $bal);
                }
            }
        } catch (Throwable $e) {
            error_log('[AccountingService balances] ' . $e->getMessage());
        }
        // Fees: what each approved student was charged minus what they have paid (estimate; waived fees left out).
        $st = $this->db->query(
            "SELECT SUM(GREATEST(0, COALESCE(r.course_fee, 0) + IF(COALESCE(r.registration_fee_waived, 0) = 1, 0, COALESCE(r.registration_fee, 0))
                    - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved'), 0)))
             FROM registrations r WHERE r.status = 'approved'"
        );
        $b['fees_owed'] = (float) $st->fetchColumn();
        foreach ($this->ex->requests('approved') as $r) {
            $b['cash_out'] += (float) $r['amount_given'];
            $b['cash_rows'][] = $r;
        }
        $st = $this->db->query("SELECT COALESCE(SUM(amount_given - spent_amount), 0) FROM expense_requests WHERE status = 'closed'");
        $b['branch_change'] = (float) $st->fetchColumn();
        return $b;
    }
}
