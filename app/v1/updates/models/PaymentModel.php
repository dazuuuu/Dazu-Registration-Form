<?php
// app/models/PaymentModel.php
namespace Models;

class PaymentModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT p.*, r.full_name, r.email, r.phone, r.registration_no, r.course_fee,
                    r.registration_fee, r.registration_fee_waived, r.payment_plan,
                    r.installment_count, r.installment_amount, r.user_id,
                    c.title AS course_title
             FROM payments p
             JOIN registrations r ON p.registration_id = r.id
             JOIN courses c ON r.course_id = c.id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getTotalApproved(int $registrationId): float {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE registration_id = :rid AND status = 'approved'"
        );
        $stmt->execute([':rid' => $registrationId]);
        return (float) $stmt->fetchColumn();
    }

    public function listPending(): array {
        $stmt = $this->db->query(
            "SELECT p.*, r.full_name, r.email, r.registration_no, c.title AS course_title
             FROM payments p
             JOIN registrations r ON p.registration_id = r.id
             JOIN courses c ON r.course_id = c.id
             WHERE p.status = 'pending'
             ORDER BY p.paid_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function listByRegistration(int $registrationId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM payments WHERE registration_id = :rid ORDER BY paid_at DESC"
        );
        $stmt->execute([':rid' => $registrationId]);
        return $stmt->fetchAll();
    }

    public function hasPending(int $registrationId): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM payments WHERE registration_id = :rid AND status = 'pending' LIMIT 1"
        );
        $stmt->execute([':rid' => $registrationId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Student submits an M-Pesa payment for admin review.
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function submitPending(int $registrationId, float $amount, string $mpesaCode, string $payerReference, int $submittedBy): array {
        if ($this->hasPending($registrationId)) {
            return ['ok' => false, 'error' => 'You already have a payment awaiting admin approval.'];
        }
        if (empty(trim($mpesaCode)) || empty(trim($payerReference))) {
            return ['ok' => false, 'error' => 'M-Pesa code and payer details are required.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO payments (registration_id, amount, payer_reference, mpesa_code, status, submitted_by, recorded_by)
             VALUES (:rid, :amount, :ref, :code, 'pending', :submitted, :submitted)"
        );
        $stmt->execute([
            ':rid'       => $registrationId,
            ':amount'    => $amount,
            ':ref'       => trim($payerReference),
            ':code'      => trim($mpesaCode),
            ':submitted' => $submittedBy,
        ]);

        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    /**
     * Record the initial payment at registration approval (already verified by admin).
     */
    public function recordInitial(int $registrationId, float $amount, string $payerReference, int $adminId, string $receiptNo, string $receiptHtml): int {
        $stmt = $this->db->prepare(
            "INSERT INTO payments (registration_id, amount, payer_reference, status, recorded_by, reviewed_by, reviewed_at, receipt_no, receipt_html, notes)
             VALUES (:rid, :amount, :ref, 'approved', :admin, :admin, NOW(), :receipt, :html, 'Initial registration payment')"
        );
        $stmt->execute([
            ':rid'     => $registrationId,
            ':amount'  => $amount,
            ':ref'     => $payerReference,
            ':admin'   => $adminId,
            ':receipt' => $receiptNo,
            ':html'    => $receiptHtml,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function approve(int $paymentId, int $adminId, string $receiptNo, string $receiptHtml): bool {
        $stmt = $this->db->prepare(
            "UPDATE payments
             SET status = 'approved', reviewed_by = :admin, reviewed_at = NOW(),
                 receipt_no = :receipt, receipt_html = :html
             WHERE id = :id AND status = 'pending'"
        );
        return $stmt->execute([
            ':admin'   => $adminId,
            ':receipt' => $receiptNo,
            ':html'    => $receiptHtml,
            ':id'      => $paymentId,
        ]);
    }

    public function reject(int $paymentId, int $adminId, string $reason): bool {
        $stmt = $this->db->prepare(
            "UPDATE payments
             SET status = 'rejected', reviewed_by = :admin, reviewed_at = NOW(), rejection_reason = :reason
             WHERE id = :id AND status = 'pending'"
        );
        return $stmt->execute([':admin' => $adminId, ':reason' => trim($reason), ':id' => $paymentId]);
    }

    public function generateReceiptNo(int $paymentId): string {
        return sprintf('RCP-DH-%05d/%s', $paymentId, date('Y'));
    }
}
