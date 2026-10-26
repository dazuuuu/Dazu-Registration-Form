<?php
// app/v1/models/PayrollRequestModel.php
// A tutor asks to correct their shares; nothing changes until the superadmin approves (migration 084).
//  unassign: the tutor stops teaching the student. On approval the unpaid money for that student is removed
//            (an adjustment of minus their unpaid earnings, never more than the tutor's unpaid balance), and the
//            student is taken off the tutor. Months already paid stay paid.
//  complete: the student finished. On approval no more months are earned after that month.
//  adjust:   change the balance by an amount (+ or -) with a reason.
namespace Models;

use PDO;

require_once __DIR__ . '/PayrollModel.php';

class PayrollRequestModel
{
    public const TYPES = ['unassign' => 'Unassign a student', 'complete' => 'Student completed', 'adjust' => 'Correct my balance'];

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
        if ($q['type'] === 'adjust') return (float) $q['amount'];
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
