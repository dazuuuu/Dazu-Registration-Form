<?php
// app/v1/models/PayrollRequestModel.php
// A tutor asks to correct their shares; nothing changes until the superadmin approves (migration 084).
//  unassign: the tutor stops teaching the student. On approval the unpaid money for that student is removed
//            (an adjustment of minus their unpaid earnings, never more than the tutor's unpaid balance), and the
//            student is taken off the tutor. Months already paid stay paid.
//  complete: the student finished. On approval no more months are earned after that month.
//  adjust:   change the balance by an amount (+ or -) with a reason.
//  claim:    a payment the tutor was owed but did not get (a student, and a number of months or an amount). On approval the money is
//            added to this month's balance, so it shows as owed until the superadmin pays it and the tutor confirms it.
namespace Models;

use PDO;

require_once __DIR__ . '/PayrollModel.php';

class PayrollRequestModel
{
    public const TYPES = ['unassign' => 'Unassign a student', 'complete' => 'Student completed', 'adjust' => 'Correct my balance', 'claim' => 'Missed payment'];

    private PDO $db;
    private PayrollModel $payroll;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->payroll = new PayrollModel($pdo);
    }

    public function isInstalled(): bool
    {
        try {
            $this->db->query("SELECT 1 FROM tutor_payroll_requests LIMIT 0");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array{ok:bool, error?:string, id?:int} */
    public function create(int $tutorId, string $type, ?int $studentUserId, ?float $amount, ?string $completedOn, string $reason): array
    {
        if (!isset(self::TYPES[$type])) return ['ok' => false, 'error' => 'Choose what you want to change.'];
        $reason = trim($reason);
        if ($reason === '') return ['ok' => false, 'error' => 'Say why, so the superadmin can decide.'];
        if ($type === 'claim') {
            if (!$studentUserId || !$this->taughtBy($tutorId, $studentUserId)) return ['ok' => false, 'error' => 'Choose one of your students.'];
            $months = $completedOn !== null && ctype_digit($completedOn) ? (int) $completedOn : 0;   // for a claim, the months come in this argument
            if ($months > 0) $amount = $months * $this->rateFor($tutorId, $studentUserId);
            if ($amount === null || $amount < 1) return ['ok' => false, 'error' => 'Enter the number of months or the amount not paid.'];
            $this->db->prepare("INSERT INTO tutor_payroll_requests (tutor_id, type, student_user_id, amount, months, completed_on, reason, status, created_at) VALUES (?, 'claim', ?, ?, ?, NULL, ?, 'pending', NOW())")
                ->execute([$tutorId, $studentUserId, round((float) $amount, 2), $months ?: null, mb_substr($reason, 0, 2000)]);
            return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
        }
        if ($type !== 'adjust') {
            $mine = array_map(fn($s) => (int) $s['user_id'], $this->payroll->getCurrentStudents($tutorId));
            if (!$studentUserId || !in_array($studentUserId, $mine, true)) return ['ok' => false, 'error' => 'Choose one of your students.'];
            $st = $this->db->prepare("SELECT 1 FROM tutor_payroll_requests WHERE tutor_id = ? AND student_user_id = ? AND status = 'pending'");
            $st->execute([$tutorId, $studentUserId]);
            if ($st->fetchColumn()) return ['ok' => false, 'error' => 'You already asked about this student. Wait for the superadmin.'];
        } else {
            $studentUserId = null;
            if ($amount === null || abs($amount) < 1) return ['ok' => false, 'error' => 'Enter the amount to add (or a minus amount to take off).'];
        }
        if ($type === 'complete') {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $completedOn);
            if (!$d) return ['ok' => false, 'error' => 'Choose the date the student completed.'];
            $completedOn = $d->format('Y-m-d');
        } else {
            $completedOn = null;
        }
        $this->db->prepare("INSERT INTO tutor_payroll_requests (tutor_id, type, student_user_id, amount, completed_on, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())")
            ->execute([$tutorId, $type, $studentUserId, $type === 'adjust' ? round((float) $amount, 2) : null, $completedOn, mb_substr($reason, 0, 2000)]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    /** Is (or was) this student with this tutor? */
    public function taughtBy(int $tutorId, int $userId): bool
    {
        $st = $this->db->prepare("SELECT (SELECT COUNT(*) FROM tutor_students WHERE tutor_id = ? AND user_id = ?) + (SELECT COUNT(*) FROM tutor_payroll_history WHERE tutor_id = ? AND user_id = ?)");
        $st->execute([$tutorId, $userId, $tutorId, $userId]);
        return (int) $st->fetchColumn() > 0;
    }

    /** The monthly rate for this student (their custom rate, else the default). */
    public function rateFor(int $tutorId, int $userId): float
    {
        $st = $this->db->prepare("SELECT monthly_rate FROM tutor_students WHERE tutor_id = ? AND user_id = ? UNION ALL SELECT monthly_rate FROM tutor_payroll_history WHERE tutor_id = ? AND user_id = ? LIMIT 1");
        $st->execute([$tutorId, $userId, $tutorId, $userId]);
        $r = $st->fetchColumn();
        return ($r !== false && $r !== null && (float) $r > 0) ? (float) $r : PayrollCalculator::DEFAULT_MONTHLY_RATE;
    }

    /** Every student this tutor has or had, for the claim search: [user_id, name, registration_no, course]. */
    public function studentsForSearch(int $tutorId): array
    {
        $st = $this->db->prepare("SELECT u.id AS user_id,
                   COALESCE((SELECT r.full_name FROM registrations r WHERE r.user_id = u.id ORDER BY r.id DESC LIMIT 1), u.username) AS name,
                   (SELECT r.registration_no FROM registrations r WHERE r.user_id = u.id ORDER BY r.id DESC LIMIT 1) AS reg_no,
                   (SELECT c.title FROM registrations r JOIN courses c ON c.id = r.course_id WHERE r.user_id = u.id ORDER BY r.id DESC LIMIT 1) AS course
            FROM users u WHERE u.id IN (SELECT user_id FROM tutor_students WHERE tutor_id = ? UNION SELECT user_id FROM tutor_payroll_history WHERE tutor_id = ?)
            ORDER BY name");
        $st->execute([$tutorId, $tutorId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cancel(int $id, int $tutorId): bool
    {
        $st = $this->db->prepare("UPDATE tutor_payroll_requests SET status = 'cancelled' WHERE id = ? AND tutor_id = ? AND status = 'pending'");
        $st->execute([$id, $tutorId]);
        return $st->rowCount() > 0;
    }

    /** Requests with tutor and student names. $tutorId null = every tutor. */
    public function list(?int $tutorId = null, string $status = ''): array
    {
        $sql = "SELECT q.*, " . "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', tp.first_name, tp.last_name)), ''), tu.username) AS tutor_name,
                       COALESCE((SELECT r.full_name FROM registrations r WHERE r.user_id = q.student_user_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1), su.username) AS student_name
                FROM tutor_payroll_requests q
                JOIN tutors t ON t.id = q.tutor_id JOIN users tu ON tu.id = t.user_id LEFT JOIN user_profiles tp ON tp.user_id = tu.id
                LEFT JOIN users su ON su.id = q.student_user_id WHERE 1 = 1";
        $args = [];
        if ($tutorId !== null) { $sql .= " AND q.tutor_id = ?"; $args[] = $tutorId; }
        if ($status !== '') { $sql .= " AND q.status = ?"; $args[] = $status; }
        $st = $this->db->prepare($sql . " ORDER BY FIELD(q.status, 'pending', 'approved', 'declined', 'cancelled'), q.id DESC LIMIT 300");
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $id): ?array
    {
        foreach ($this->list() as $r) if ((int) $r['id'] === $id) return $r;
        return null;
    }

    /** What approving would change, in money, for the superadmin to see first. */
    public function preview(array $q): float
    {
        if ($q['type'] === 'unassign') return -$this->payroll->unpaidForStudent((int) $q['tutor_id'], (int) $q['student_user_id']);
        if ($q['type'] === 'adjust' || $q['type'] === 'claim') return (float) $q['amount'];
        return 0.0;
    }

    /** Approve or decline. Approving applies the change at once, in one transaction. @return array{ok:bool, error?:string, applied?:float} */
    public function decide(int $id, bool $approve, string $note, int $actor): array
    {
        $q = $this->get($id);
        if (!$q || $q['status'] !== 'pending') return ['ok' => false, 'error' => 'This request was already decided.'];
        if (!$approve) {
            $this->db->prepare("UPDATE tutor_payroll_requests SET status = 'declined', decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ? AND status = 'pending'")
                ->execute([$actor, trim($note) ?: null, $id]);
            return ['ok' => true, 'applied' => 0.0];
        }
        $tutorId = (int) $q['tutor_id'];
        $uid = $q['student_user_id'] !== null ? (int) $q['student_user_id'] : null;
        $applied = 0.0;
        $month = PayrollCalculator::currentMonth();
        $this->db->beginTransaction();
        try {
            if ($q['type'] === 'unassign') {
                $applied = -$this->payroll->unpaidForStudent($tutorId, $uid);
                if ($applied < 0) {
                    $this->payroll->addAdjustment($tutorId, $uid, $applied, $month, 'Unassigned ' . $q['student_name'] . ': unpaid shares removed', $id, $actor);
                }
                if (!$this->payroll->unassignStudent($tutorId, $uid)) throw new \RuntimeException('The student is no longer with this tutor.');
            } elseif ($q['type'] === 'complete') {
                if (!$this->payroll->markCompleted($tutorId, $uid, (string) $q['completed_on'])) throw new \RuntimeException('The student is no longer with this tutor.');
            } elseif ($q['type'] === 'claim') {
                $applied = (float) $q['amount'];
                $this->payroll->addAdjustment($tutorId, $uid, $applied, $month, 'Missed payment: ' . $q['student_name'] . ($q['months'] ? ' (' . (int) $q['months'] . ' month' . ((int) $q['months'] > 1 ? 's' : '') . ')' : ''), $id, $actor);
            } else {
                $applied = (float) $q['amount'];
                $this->payroll->addAdjustment($tutorId, null, $applied, $month, 'Balance corrected: ' . mb_substr((string) $q['reason'], 0, 200), $id, $actor);
            }
            $this->db->prepare("UPDATE tutor_payroll_requests SET status = 'approved', decided_by = ?, decided_at = NOW(), decision_note = ?, applied_amount = ? WHERE id = ? AND status = 'pending'")
                ->execute([$actor, trim($note) ?: null, round($applied, 2), $id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'error' => $e instanceof \RuntimeException ? $e->getMessage() : 'Could not apply the change. Nothing was saved.'];
        }
        return ['ok' => true, 'applied' => round($applied, 2)];
    }
}
