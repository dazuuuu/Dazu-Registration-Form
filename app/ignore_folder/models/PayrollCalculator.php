<?php
// app/v1/models/PayrollCalculator.php
// Pure payroll maths for tutors. No database, no session: give it data, get numbers back,
// so it can be tested on its own (see app/v1/tests/payroll_calculator_test.php).
//
// Rules:
//  - A tutor earns a monthly rate (default KES 5,000, overridable per student) for every month of the
//    student's course, starting in the month the student was assigned to the tutor.
//  - A month in which the student (or the tutor) is paused, on break or on holiday for at least half of it earns
//    nothing and does not use up one of the student's course months: the course, and the pay, run on afterwards.
//  - Every month the tutor is owed what they earned that month plus anything unpaid from earlier months.
//  - A payment is counted against the month it was recorded for, and reduces the balance.
namespace Models;

class PayrollCalculator
{
    public const DEFAULT_MONTHLY_RATE = 5000.00;

    /** Months a course lasts, for payroll: months, else weeks/4 rounded up, else days/30 rounded up, else 1. */
    public static function courseMonths(array $course): int
    {
        $months = (int) ($course['duration_months'] ?? 0);
        if ($months > 0) {
            return $months;
        }
        $weeks = (int) ($course['weeks_of_study'] ?? 0);
        if ($weeks > 0) {
            return (int) ceil($weeks / 4);
        }
        $days = (int) ($course['days_of_study'] ?? 0);
        if ($days > 0) {
            return (int) ceil($days / 30);
        }
        return 1;
    }

    /** Current month as 'YYYY-MM' (Nairobi time). */
    public static function currentMonth(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m');
    }

    /** Last month as 'YYYY-MM'. Tutors are paid in arrears: a payment made this month is for last month. */
    public static function previousMonth(): string
    {
        return self::indexToKey(self::monthIndex(self::currentMonth()) - 1);
    }

    /** Accepts 'YYYY-MM' or any date string starting YYYY-MM and returns 'YYYY-MM'. */
    public static function monthKey(string $date): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})/', $date, $m)) {
            throw new \InvalidArgumentException("Not a month or date: {$date}");
        }
        return $m[1] . '-' . $m[2];
    }

    public static function monthIndex(string $date): int
    {
        $key = self::monthKey($date);
        return ((int) substr($key, 0, 4)) * 12 + ((int) substr($key, 5, 2)) - 1;
    }

    public static function indexToKey(int $index): string
    {
        return sprintf('%04d-%02d', intdiv($index, 12), ($index % 12) + 1);
    }

    public static function monthLabel(string $key): string
    {
        return date('F Y', strtotime(self::monthKey($key) . '-01'));
    }

    public static function money(float $amount): string
    {
        $whole = abs($amount - round($amount)) < 0.005;
        return 'KES ' . number_format($amount, $whole ? 0 : 2);
    }

    private static function cents($value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /**
     * Months (as indexes, between $from and $to) in which the pauses cover at least half of the month.
     * @param array $pauses Each: ['start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD'|null]. 'end' is the day pay resumes;
     *                      null means still paused, which counts through $to.
     * @return array<int,true>
     */
    public static function pausedMonths(array $pauses, int $from, int $to): array
    {
        $set = [];
        foreach ($pauses as $p) {
            $start = strtotime(substr((string) $p['start'], 0, 10) . ' UTC');
            $end = !empty($p['end']) ? strtotime(substr((string) $p['end'], 0, 10) . ' UTC') : PHP_INT_MAX;
            $lastIdx = $end === PHP_INT_MAX ? $to : min($to, self::monthIndex(gmdate('Y-m', $end)));
            for ($i = max($from, self::monthIndex(gmdate('Y-m', $start))); $i <= $lastIdx; $i++) {
                $monthStart = strtotime(self::indexToKey($i) . '-01 UTC');
                $monthEnd = strtotime(self::indexToKey($i + 1) . '-01 UTC');
                $overlap = (min($end, $monthEnd) - max($start, $monthStart)) / 86400;
                if ($overlap > 0 && $overlap * 2 >= ($monthEnd - $monthStart) / 86400) {
                    $set[$i] = true;
                }
            }
        }
        return $set;
    }

    /**
     * @param array $students Each: ['start' => date, 'months' => int, 'rate' => float|null, 'pauses' => [...]] (extra keys are kept).
     *                        'months' is how many months this student earns for (0 = nothing). Instead of 'months', a student
     *                        can carry 'fixed_months' => ['YYYY-MM', ...]: exactly the months already earned (used for students
     *                        taken off the tutor, so what was earned never changes afterwards).
     * @param array $payments Each: ['amount' => float, 'month' => 'YYYY-MM' or date].
     * @param string|null $asOf Month to calculate up to (default: this month).
     * @param array $tutorPauses Pauses that stop pay for every student (the tutor is on break or holiday).
     * @return array {as_of, default_rate, students[], months[], earned_to_date, paid_to_date, balance,
     *                this_month{month,brought_forward,earned,paid,balance}, monthly_run_rate, active_students}
     */
    public static function ledger(array $students, array $payments, ?string $asOf = null, array $tutorPauses = []): array
    {
        $asOfIdx = self::monthIndex($asOf ?? self::currentMonth());
        $defaultCents = self::cents(self::DEFAULT_MONTHLY_RATE);

        $tutorPaused = [];
        if ($tutorPauses) {
            $tutorPaused = self::pausedMonths($tutorPauses, 0, $asOfIdx);
        }

        $rows = [];
        foreach ($students as $s) {
            $isCustom = isset($s['rate']) && $s['rate'] !== null && $s['rate'] !== '';
            $rateCents = $isCustom ? self::cents($s['rate']) : $defaultCents;
            $startIdx = self::monthIndex((string) $s['start']);

            // The months this student earns, in order. Months paused before or at the as-of month are skipped.
            $earn = [];
            if (isset($s['fixed_months'])) {
                foreach ($s['fixed_months'] as $key) {
                    $earn[] = self::monthIndex($key);
                }
                sort($earn);
                $months = count($earn);
            } else {
                $months = max(0, (int) ($s['months'] ?? 0));
                $studentPaused = !empty($s['pauses']) ? self::pausedMonths($s['pauses'], $startIdx, $asOfIdx) : [];
                for ($i = $startIdx; count($earn) < $months; $i++) {
                    if ($i <= $asOfIdx && (isset($tutorPaused[$i]) || isset($studentPaused[$i]))) {
                        continue;
                    }
                    $earn[] = $i;
                }
            }
            $set = array_fill_keys($earn, true);
            $elapsed = count(array_filter($earn, fn($i) => $i <= $asOfIdx));
            $studentPausedNow = !empty($s['pauses']) && isset(self::pausedMonths($s['pauses'], $asOfIdx, $asOfIdx)[$asOfIdx]);

            $rows[] = array_merge($s, [
                'is_custom'         => $isCustom,
                'rate'              => $rateCents / 100.0,
                'months'            => $months,
                'start_month'       => self::indexToKey($startIdx),
                'end_month'         => $earn ? self::indexToKey(end($earn)) : null,
                'months_elapsed'    => $elapsed,
                'months_remaining'  => max(0, $months - $elapsed),
                'total_for_course'  => ($rateCents * $months) / 100.0,
                'earned_to_date'    => ($rateCents * $elapsed) / 100.0,
                'earning_now'       => isset($set[$asOfIdx]),
                'paused_now'        => $studentPausedNow || isset($tutorPaused[$asOfIdx]),
                'earned_month_keys' => array_map([self::class, 'indexToKey'], array_filter($earn, fn($i) => $i <= $asOfIdx)),
                '_set' => $set, '_rate' => $rateCents,
            ]);
        }

        // Payments count against their month; a payment dated after the as-of month is counted in the as-of month.
        $paidByMonth = [];
        $paymentIdxs = [];
        foreach ($payments as $p) {
            $idx = min(self::monthIndex((string) $p['month']), $asOfIdx);
            $paidByMonth[$idx] = ($paidByMonth[$idx] ?? 0) + self::cents($p['amount']);
            $paymentIdxs[] = $idx;
        }

        $starts = [];
        foreach ($rows as $r) {
            $first = $r['_set'] ? min(array_keys($r['_set'])) : null;
            if ($first !== null && $first <= $asOfIdx) {
                $starts[] = $first;
            }
        }
        $firstIdx = $starts || $paymentIdxs ? min(array_merge($starts, $paymentIdxs)) : null;

        $months = [];
        $balance = 0;
        $earnedTotal = 0;
        $paidTotal = 0;
        if ($firstIdx !== null) {
            for ($i = $firstIdx; $i <= $asOfIdx; $i++) {
                $earned = 0;
                $active = 0;
                foreach ($rows as $r) {
                    if (isset($r['_set'][$i])) {
                        $earned += $r['_rate'];
                        $active++;
                    }
                }
                $paid = $paidByMonth[$i] ?? 0;
                $broughtForward = $balance;
                $balance = $balance + $earned - $paid;
                $earnedTotal += $earned;
                $paidTotal += $paid;
                $months[] = [
                    'month'           => self::indexToKey($i),
                    'brought_forward' => $broughtForward / 100.0,
                    'earned'          => $earned / 100.0,
                    'paid'            => $paid / 100.0,
                    'balance'         => $balance / 100.0,
                    'active_students' => $active,
                ];
            }
        }

        $runRate = 0;
        $activeNow = 0;
        foreach ($rows as $r) {
            if ($r['earning_now']) {
                $runRate += $r['_rate'];
                $activeNow++;
            }
        }

        foreach ($rows as &$r) {
            unset($r['_set'], $r['_rate']);
        }
        unset($r);

        $thisMonth = $months ? end($months) : [
            'month' => self::indexToKey($asOfIdx), 'brought_forward' => 0.0, 'earned' => 0.0,
            'paid' => 0.0, 'balance' => 0.0, 'active_students' => 0,
        ];

        return [
            'as_of'            => self::indexToKey($asOfIdx),
            'default_rate'     => self::DEFAULT_MONTHLY_RATE,
            'students'         => $rows,
            'months'           => $months,
            'earned_to_date'   => $earnedTotal / 100.0,
            'paid_to_date'     => $paidTotal / 100.0,
            'balance'          => $balance / 100.0,
            'this_month'       => $thisMonth,
            'monthly_run_rate' => $runRate / 100.0,
            'active_students'  => $activeNow,
        ];
    }

    /** Day of the month an employee is paid in the given month (a pay day of 31 means the last day of a short month). */
    private static function payDate(int $monthIdx, int $payDay): string
    {
        $key = self::indexToKey($monthIdx);
        $daysInMonth = (int) date('t', strtotime($key . '-01'));
        return sprintf('%s-%02d', $key, max(1, min($payDay, $daysInMonth)));
    }

    /**
     * Fixed monthly salary for an employee. The salary for a month is earned on that month's pay day, and only if the
     * employee was employed on that day (started on or before it, and had not left before it).
     * @param array $payments Each: ['amount' => float, 'month' => 'YYYY-MM' or date].
     * @param string|null $today 'YYYY-MM-DD' (default: today, Nairobi time).
     * @return array {months[], earned_to_date, paid_to_date, balance, next_pay_date, salary}
     */
    public static function employeeLedger(float $salary, string $startDate, ?string $endDate, int $payDay, array $payments, ?string $today = null): array
    {
        $today = $today ?? (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
        $todayIdx = self::monthIndex($today);
        $salaryCents = self::cents($salary);
        $startDate = substr($startDate, 0, 10);
        $endDate = $endDate ? substr($endDate, 0, 10) : null;

        $earnsIn = function (int $i) use ($startDate, $endDate, $payDay, $today): bool {
            $pay = self::payDate($i, $payDay);
            return $pay >= $startDate && ($endDate === null || $pay <= $endDate) && $pay <= $today;
        };

        $paidByMonth = [];
        foreach ($payments as $p) {
            $idx = min(self::monthIndex((string) $p['month']), $todayIdx);
            $paidByMonth[$idx] = ($paidByMonth[$idx] ?? 0) + self::cents($p['amount']);
        }

        $first = self::monthIndex($startDate);
        if ($paidByMonth) {
            $first = min($first, min(array_keys($paidByMonth)));
        }
        $months = [];
        $balance = 0;
        $earnedTotal = 0;
        $paidTotal = 0;
        for ($i = $first; $i <= $todayIdx; $i++) {
            $earned = $earnsIn($i) ? $salaryCents : 0;
            $paid = $paidByMonth[$i] ?? 0;
            $brought = $balance;
            $balance += $earned - $paid;
            $earnedTotal += $earned;
            $paidTotal += $paid;
            $months[] = [
                'month' => self::indexToKey($i), 'pay_date' => self::payDate($i, $payDay),
                'brought_forward' => $brought / 100.0, 'earned' => $earned / 100.0,
                'paid' => $paid / 100.0, 'balance' => $balance / 100.0,
            ];
        }

        // Next pay date: the first later pay day while still employed.
        $next = null;
        for ($i = $todayIdx; $i <= $todayIdx + 2; $i++) {
            $pay = self::payDate($i, $payDay);
            if ($pay > $today && $pay >= $startDate && ($endDate === null || $pay <= $endDate)) {
                $next = $pay;
                break;
            }
        }

        return [
            'salary'         => $salaryCents / 100.0,
            'months'         => $months,
            'earned_to_date' => $earnedTotal / 100.0,
            'paid_to_date'   => $paidTotal / 100.0,
            'balance'        => $balance / 100.0,
            'next_pay_date'  => $next,
        ];
    }
}
