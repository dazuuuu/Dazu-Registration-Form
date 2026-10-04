<?php
// app/v1/models/PayrollModel.php
// Database side of tutor payroll. All the arithmetic lives in PayrollCalculator.
namespace Models;

require_once __DIR__ . '/PayrollCalculator.php';

class PayrollModel
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    /** False until migrations 047 and 048 have been applied, so pages can say so instead of crashing. */
    public function isInstalled(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM tutor_payments LIMIT 1');
            $this->db->query('SELECT monthly_rate FROM tutor_students LIMIT 1');
            $this->db->query('SELECT earned_months, registration_id FROM tutor_payroll_history LIMIT 1');
            $this->db->query('SELECT months_offset FROM tutor_students LIMIT 1');
            $this->db->query('SELECT 1 FROM tutor_pauses LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private const NAME_SQL = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username)";

    // ---------------------------------------------------------------- tutors

    /** Every tutor with a live payroll summary, for the admin payroll list. */
    public function listTutorSummaries(?string $asOf = null): array
    {
        $rows = $this->db->query(
            "SELECT t.id, t.is_active, u.email, " . self::NAME_SQL . " AS full_name
             FROM tutors t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             ORDER BY full_name"
        )->fetchAll();
        foreach ($rows as &$r) {
            $l = $this->getLedger((int) $r['id'], $asOf);
            $r['student_count'] = count($l['current_students']);
            $r['this_month_earned'] = $l['this_month']['earned'];
            $r['earned_to_date'] = $l['earned_to_date'];
            $r['paid_to_date'] = $l['paid_to_date'];
            $r['balance'] = $l['balance'];
            $r['pending_confirmation'] = $l['pending_confirmation'];
            $r['students'] = array_map(fn($st) => ['name' => $st['student_name'], 'paused' => $st['paused_now']], $l['current_students']);
            $r['status'] = $l['tutor_status'];
        }
        return $rows;
    }

    /** id and name of every tutor, for pick lists. */
    public function listTutorNames(): array
    {
        return $this->db->query(
            "SELECT t.id, t.is_active, " . self::NAME_SQL . " AS full_name
             FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
             ORDER BY full_name"
        )->fetchAll();
    }

    /** Tutors a student is currently assigned to (id, full_name). */
    public function getStudentTutors(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, " . self::NAME_SQL . " AS full_name
             FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id
             JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE ts.user_id = ? ORDER BY full_name"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getTutor(int $tutorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.user_id, t.is_active, u.email, " . self::NAME_SQL . " AS full_name
             FROM tutors t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE t.id = :id"
        );
        $stmt->execute([':id' => $tutorId]);
        return $stmt->fetch() ?: null;
    }

    // -------------------------------------------------------------- students

    /**
     * Students currently assigned to the tutor. The course used for payroll is the student's most recent
     * approved registration. A student with no approved registration is listed but earns nothing (months = 0).
     */
    public function getCurrentStudents(int $tutorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ts.user_id, ts.assigned_at, ts.monthly_rate, ts.months_offset,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), r.full_name, u.username) AS student_name,
                    u.email, r.id AS registration_id, r.registration_no,
                    c.id AS course_id, c.title AS course_title, c.abbreviation,
                    c.duration_months, c.weeks_of_study, c.days_of_study
             FROM tutor_students ts
             JOIN users u ON u.id = ts.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             LEFT JOIN registrations r ON r.id = (
                 SELECT MAX(r2.id) FROM registrations r2 WHERE r2.user_id = ts.user_id AND r2.status = 'approved')
             LEFT JOIN courses c ON c.id = r.course_id
             WHERE ts.tutor_id = :tid
             ORDER BY student_name"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    /** Students taken off this tutor earlier; what they earned is kept. */
    public function getFormerStudents(int $tutorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tutor_payroll_history WHERE tutor_id = :tid ORDER BY removed_at DESC, id DESC"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    /**
     * Called just before students are taken off a tutor: freezes the months the tutor earned for them so nothing
     * is lost or changed afterwards. Never throws, so it cannot stop an unassign.
     */
    public function snapshotRemoved(int $tutorId, array $userIds): void
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds), fn($v) => $v > 0));
        if (!$userIds) {
            return;
        }
        try {
            $ins = $this->db->prepare(
                "INSERT INTO tutor_payroll_history
                    (tutor_id, user_id, student_name, course_title, abbreviation, duration_months, weeks_of_study,
                     days_of_study, monthly_rate, assigned_at, removed_at, registration_id, earned_months)
                 VALUES (:tid, :uid, :name, :title, :abbr, :dm, :ws, :ds, :rate, :assigned, NOW(), :reg, :months)"
            );
            foreach ($this->getLedger($tutorId)['current_students'] as $s) {
                if (!in_array((int) $s['user_id'], $userIds, true)) {
                    continue;
                }
                $ins->execute([
                    ':tid' => $tutorId, ':uid' => $s['user_id'], ':name' => $s['student_name'],
                    ':title' => $s['course_title'], ':abbr' => $s['abbreviation'], ':dm' => $s['duration_months'],
                    ':ws' => $s['weeks_of_study'], ':ds' => $s['days_of_study'], ':rate' => $s['monthly_rate'],
                    ':assigned' => $s['assigned_at'], ':reg' => $s['registration_id'],
                    ':months' => implode(',', $s['earned_month_keys']),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('PayrollModel::snapshotRemoved failed: ' . $e->getMessage());
        }
    }

    /**
     * Set a custom monthly rate for the chosen students of this tutor (null = back to the default rate).
     * Only students actually assigned to the tutor are touched. Returns how many students were updated.
     */
    public function setStudentRates(int $tutorId, array $userIds, ?float $rate): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($v) => $v > 0)));
        if (!$userIds) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("UPDATE tutor_students SET monthly_rate = ? WHERE tutor_id = ? AND user_id IN ($in)");
        $stmt->execute(array_merge([$rate === null ? null : round($rate, 2), $tutorId], $userIds));
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------- payments

    public function getPayments(int $tutorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tutor_payments WHERE tutor_id = :tid ORDER BY paid_for_month DESC, id DESC"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    /** @return array{ok:bool, error?:string} */
    public function addPayment(int $tutorId, $amount, string $forMonth, string $paidOn, ?string $note, ?int $adminUserId): array
    {
        if (!is_numeric($amount) || (float) $amount <= 0) {
            return ['ok' => false, 'error' => 'Enter an amount greater than zero.'];
        }
        $amount = round((float) $amount, 2);
        if ($amount > 10000000) {
            return ['ok' => false, 'error' => 'That amount is too large.'];
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $forMonth)) {
            return ['ok' => false, 'error' => 'Choose a valid month.'];
        }
        if ($forMonth > PayrollCalculator::currentMonth()) {
            return ['ok' => false, 'error' => 'A payment cannot be recorded against a future month.'];
        }
        $d = \DateTime::createFromFormat('Y-m-d', $paidOn);
        if (!$d || $d->format('Y-m-d') !== $paidOn) {
            return ['ok' => false, 'error' => 'Choose a valid payment date.'];
        }
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && mb_strlen($note) > 255) {
            return ['ok' => false, 'error' => 'The note is too long (255 characters maximum).'];
        }
        if (!$this->getTutor($tutorId)) {
            return ['ok' => false, 'error' => 'Tutor not found.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO tutor_payments (tutor_id, amount, paid_for_month, paid_on, note, recorded_by)
             VALUES (:tid, :amount, :month, :paid_on, :note, :admin)"
        );
        $stmt->execute([
            ':tid' => $tutorId, ':amount' => $amount, ':month' => $forMonth . '-01', ':paid_on' => $paidOn,
            ':note' => ($note === '' ? null : $note), ':admin' => $adminUserId ?: null,
        ]);
        return ['ok' => true];
    }

    /** Remove a mistaken payment. Payments the tutor has already confirmed cannot be deleted. */
    public function deletePayment(int $paymentId, int $tutorId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM tutor_payments WHERE id = :id AND tutor_id = :tid AND confirmed_at IS NULL"
        );
        $stmt->execute([':id' => $paymentId, ':tid' => $tutorId]);
        return $stmt->rowCount() > 0;
    }

    /** Tutor confirms receipt of one payment. Returns false if it is not theirs or already confirmed. */
    public function confirmPayment(int $paymentId, int $tutorId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tutor_payments SET confirmed_at = NOW() WHERE id = :id AND tutor_id = :tid AND confirmed_at IS NULL"
        );
        $stmt->execute([':id' => $paymentId, ':tid' => $tutorId]);
        return $stmt->rowCount() > 0;
    }

    /** Tutor confirms every payment still waiting. Returns how many were confirmed. */
    public function confirmAllPayments(int $tutorId): int
    {
        $stmt = $this->db->prepare("UPDATE tutor_payments SET confirmed_at = NOW() WHERE tutor_id = :tid AND confirmed_at IS NULL");
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->rowCount();
    }

    // ------------------------------------------------------------ assignment

    private function normaliseIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    }

    /** Run $work in a transaction, or inside the caller's one if a transaction is already open. */
    private function atomically(callable $work)
    {
        if ($this->db->inTransaction()) {
            return $work();
        }
        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * What earlier assignments (to any tutor) already earned for this student's current course.
     * @return array{0:int,1:bool} months already covered, and whether this month is one of them
     */
    private function priorEarnings(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.earned_months FROM tutor_payroll_history h
             WHERE h.user_id = :uid AND h.earned_months IS NOT NULL AND h.registration_id = (
                 SELECT MAX(r.id) FROM registrations r WHERE r.user_id = :uid2 AND r.status = 'approved')"
        );
        $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
        $count = 0;
        $thisMonth = false;
        $current = PayrollCalculator::currentMonth();
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $csv) {
            foreach (array_filter(explode(',', (string) $csv)) as $key) {
                $count++;
                $thisMonth = $thisMonth || $key === $current;
            }
        }
        return [$count, $thisMonth];
    }

    /**
     * Assign students to a tutor (students the tutor already has are skipped). A student who already earned a
     * tutor part of the course only earns this tutor the months that are left. If this month was already
     * paid to a previous tutor, the new tutor starts from next month, so the month is never paid twice.
     * @return int how many were added
     */
    public function assignStudents(int $tutorId, array $userIds): int
    {
        $userIds = $this->normaliseIds($userIds);
        if (!$userIds) {
            return 0;
        }
        return $this->atomically(function () use ($tutorId, $userIds) {
            $have = $this->db->prepare("SELECT user_id FROM tutor_students WHERE tutor_id = ?");
            $have->execute([$tutorId]);
            $toAdd = array_diff($userIds, array_map('intval', $have->fetchAll(\PDO::FETCH_COLUMN)));
            $ins = $this->db->prepare(
                "INSERT INTO tutor_students (tutor_id, user_id, months_offset, assigned_at) VALUES (:tid, :uid, :off, :at)"
            );
            $nextMonth = (new \DateTimeImmutable('first day of next month 00:00:00', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d H:i:s');
            foreach ($toAdd as $uid) {
                [$offset, $paidThisMonth] = $this->priorEarnings($uid);
                $ins->execute([
                    ':tid' => $tutorId, ':uid' => $uid, ':off' => min(255, $offset),
                    ':at' => $paidThisMonth ? $nextMonth : $now,
                ]);
            }
            if ($toAdd) {
                require_once __DIR__ . '/../services/PortalNotifications.php';
                \PortalNotifications::assignmentNotices($this->db, $tutorId, array_values($toAdd));
            }
            return count($toAdd);
        });
    }

    /** Take a student off a tutor. What the tutor earned for them is kept, and their pauses are cleared. */
    public function unassignStudent(int $tutorId, int $userId): bool
    {
        return $this->atomically(function () use ($tutorId, $userId) {
            $chk = $this->db->prepare("SELECT 1 FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
            $chk->execute([$tutorId, $userId]);
            if (!$chk->fetchColumn()) {
                return false;
            }
            $this->snapshotRemoved($tutorId, [$userId]);
            $this->db->prepare("DELETE FROM tutor_pauses WHERE tutor_id = ? AND user_id = ?")->execute([$tutorId, $userId]);
            $this->db->prepare("DELETE FROM tutor_students WHERE tutor_id = ? AND user_id = ?")->execute([$tutorId, $userId]);
            return true;
        });
    }

    /** @return array{ok:bool, error?:string} */
    public function moveStudent(int $fromTutorId, int $userId, int $toTutorId): array
    {
        if ($fromTutorId === $toTutorId || !$this->getTutor($toTutorId)) {
            return ['ok' => false, 'error' => 'Choose a different tutor to move the student to.'];
        }
        $moved = $this->atomically(function () use ($fromTutorId, $userId, $toTutorId) {
            if (!$this->unassignStudent($fromTutorId, $userId)) {
                return false;
            }
            $this->assignStudents($toTutorId, [$userId]);
            return true;
        });
        return $moved ? ['ok' => true] : ['ok' => false, 'error' => 'That student is not assigned to this tutor.'];
    }

    /** Put a student who was taken off this tutor back with them. @return array{ok:bool, error?:string} */
    public function reassignFormer(int $tutorId, int $historyId): array
    {
        $stmt = $this->db->prepare("SELECT user_id FROM tutor_payroll_history WHERE id = ? AND tutor_id = ?");
        $stmt->execute([$historyId, $tutorId]);
        $uid = (int) $stmt->fetchColumn();
        if ($uid <= 0) {
            return ['ok' => false, 'error' => 'That student was not found in this tutor\'s history.'];
        }
        return $this->assignStudents($tutorId, [$uid]) > 0
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'That student is already assigned to this tutor.'];
    }

    // ---------------------------------------------------------------- pauses

    public const PAUSE_KINDS = ['pause' => 'Paused', 'break' => 'On break', 'holiday' => 'On holiday'];

    /** Every pause for the tutor (whole-tutor ones have user_id NULL), newest first. */
    public function getPauses(int $tutorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, " . self::NAME_SQL . " AS student_name
             FROM tutor_pauses p
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE p.tutor_id = :tid ORDER BY p.start_date DESC, p.id DESC"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    /**
     * The pause that applies today to one student ($userId) or to the whole tutor (null), or null if none.
     * A pause that starts in the future comes back with state 'scheduled'.
     */
    public static function currentPause(array $pauses, ?int $userId, ?string $today = null): ?array
    {
        $today = $today ?? (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
        $found = null;
        foreach ($pauses as $p) {
            if (($p['user_id'] === null ? null : (int) $p['user_id']) !== $userId) {
                continue;
            }
            if (!empty($p['end_date']) && $p['end_date'] <= $today) {
                continue;
            }
            $state = $p['start_date'] <= $today ? 'active' : 'scheduled';
            if ($found === null || ($state === 'active' && $found['state'] !== 'active')) {
                $found = $p + ['state' => $state];
            }
        }
        return $found;
    }

    /** @return array{ok:bool, error?:string} */
    public function startPause(int $tutorId, ?int $userId, string $kind, string $start, ?string $end, ?string $note): array
    {
        if (!isset(self::PAUSE_KINDS[$kind])) {
            return ['ok' => false, 'error' => 'Choose whether this is a pause, a break or a holiday.'];
        }
        $isDate = function (?string $d): bool {
            $x = $d ? \DateTime::createFromFormat('Y-m-d', $d) : false;
            return $x && $x->format('Y-m-d') === $d;
        };
        if (!$isDate($start)) {
            return ['ok' => false, 'error' => 'Choose a valid start date.'];
        }
        $end = ($end === null || $end === '') ? null : $end;
        if ($end !== null && (!$isDate($end) || $end <= $start)) {
            return ['ok' => false, 'error' => 'The return date must be after the start date.'];
        }
        $note = $note !== null ? trim($note) : null;
        if ($note !== null && mb_strlen($note) > 255) {
            return ['ok' => false, 'error' => 'The note is too long (255 characters maximum).'];
        }
        if ($userId !== null) {
            $chk = $this->db->prepare("SELECT 1 FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
            $chk->execute([$tutorId, $userId]);
            if (!$chk->fetchColumn()) {
                return ['ok' => false, 'error' => 'That student is not assigned to this tutor.'];
            }
        } elseif (!$this->getTutor($tutorId)) {
            return ['ok' => false, 'error' => 'Tutor not found.'];
        }

        // Refuse a pause that overlaps one already recorded for the same student or tutor.
        $overlap = $this->db->prepare(
            "SELECT 1 FROM tutor_pauses WHERE tutor_id = :tid AND user_id <=> :uid
               AND (end_date IS NULL OR end_date > :start) AND (:end IS NULL OR start_date < :end2) LIMIT 1"
        );
        $overlap->execute([':tid' => $tutorId, ':uid' => $userId, ':start' => $start, ':end' => $end, ':end2' => $end]);
        if ($overlap->fetchColumn()) {
            return ['ok' => false, 'error' => 'There is already a pause covering those dates. Resume it first.'];
        }

        $this->db->prepare(
            "INSERT INTO tutor_pauses (tutor_id, user_id, kind, start_date, end_date, note) VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$tutorId, $userId, $kind, $start, $end, ($note === '' ? null : $note)]);
        return ['ok' => true];
    }

    /** Resume pay from $date (default today). A pause that never got going is simply removed. */
    public function endPause(int $pauseId, int $tutorId, ?string $date = null): bool
    {
        $date = $date ?? (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
        $stmt = $this->db->prepare("SELECT start_date, end_date FROM tutor_pauses WHERE id = ? AND tutor_id = ?");
        $stmt->execute([$pauseId, $tutorId]);
        $p = $stmt->fetch();
        if (!$p || (!empty($p['end_date']) && $p['end_date'] <= $date)) {
            return false;
        }
        if ($date <= $p['start_date']) {
            $this->db->prepare("DELETE FROM tutor_pauses WHERE id = ? AND tutor_id = ?")->execute([$pauseId, $tutorId]);
        } else {
            $this->db->prepare("UPDATE tutor_pauses SET end_date = ? WHERE id = ? AND tutor_id = ?")->execute([$date, $pauseId, $tutorId]);
        }
        return true;
    }

    // ---------------------------------------------------------------- ledger

    /**
     * Everything the payroll screens need for one tutor.
     * Adds to the calculator's result: current_students, former_students, payments, pending_confirmation.
     */
    public function getLedger(int $tutorId, ?string $asOf = null): array
    {
        $current = $this->getCurrentStudents($tutorId);
        $former = $this->getFormerStudents($tutorId);
        $payments = $this->getPayments($tutorId);
        $pauses = $this->getPauses($tutorId);
        $asOf = $asOf ?? PayrollCalculator::currentMonth();

        $tutorPauses = [];
        $studentPauses = [];
        foreach ($pauses as $p) {
            $range = ['start' => $p['start_date'], 'end' => $p['end_date']];
            if ($p['user_id'] === null) {
                $tutorPauses[] = $range;
            } else {
                $studentPauses[(int) $p['user_id']][] = $range;
            }
        }

        $lines = [];
        foreach ($current as $s) {
            $courseMonths = $s['registration_id'] ? PayrollCalculator::courseMonths($s) : 0;
            $lines[] = $s + [
                'kind'   => 'current',
                'start'  => $s['assigned_at'],
                'months' => max(0, $courseMonths - (int) $s['months_offset']),
                'rate'   => $s['monthly_rate'],
                'pauses' => $studentPauses[(int) $s['user_id']] ?? [],
            ];
        }
        foreach ($former as $f) {
            $line = $f + ['kind' => 'former', 'start' => $f['assigned_at'], 'rate' => $f['monthly_rate']];
            if ($f['earned_months'] !== null) {
                $line['fixed_months'] = array_values(array_filter(explode(',', $f['earned_months'])));
            } else {
                // Recorded before pauses existed: earned from the month assigned through the month removed.
                $startIdx = PayrollCalculator::monthIndex($f['assigned_at']);
                $removedIdx = PayrollCalculator::monthIndex($f['removed_at']);
                $line['months'] = min(PayrollCalculator::courseMonths($f), max(0, $removedIdx - $startIdx + 1));
            }
            $lines[] = $line;
        }

        $payLines = array_map(fn($p) => ['amount' => $p['amount'], 'month' => $p['paid_for_month']], $payments);
        $ledger = PayrollCalculator::ledger($lines, $payLines, $asOf, $tutorPauses);

        $ledger['current_students'] = array_values(array_filter($ledger['students'], fn($s) => $s['kind'] === 'current'));
        $ledger['former_students'] = array_values(array_filter($ledger['students'], fn($s) => $s['kind'] === 'former'));
        unset($ledger['students']);
        foreach ($ledger['current_students'] as &$cs) {
            $cs['pause'] = self::currentPause($pauses, (int) $cs['user_id']);
        }
        unset($cs);
        $ledger['payments'] = $payments;
        $ledger['pauses'] = $pauses;
        $ledger['tutor_status'] = self::currentPause($pauses, null);
        $ledger['pending_confirmation'] = count(array_filter($payments, fn($p) => $p['confirmed_at'] === null));
        // Students earning this month, not just assigned (a paused student, or one whose course has finished, is assigned but not earning).
        $ledger['active_students'] = count(array_filter($ledger['current_students'], fn($s) => $s['earning_now']));
        return $ledger;
    }
}
