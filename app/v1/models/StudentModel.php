<?php
// app/models/StudentModel.php
namespace Models;

class StudentModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function listApproved(): array {
        $stmt = $this->db->query(
            "SELECT r.*, c.title AS course_title, c.abbreviation,
                    u.username, u.is_active, u.account_status, u.email AS user_email
             FROM registrations r
             JOIN courses c ON r.course_id = c.id
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.status = 'approved'
             ORDER BY r.reviewed_at DESC"
        );
        return $stmt->fetchAll();
    }

    public function getByUserId(int $userId): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.*, c.title AS course_title, c.abbreviation, c.duration_months,
                    u.username, u.email AS user_email, u.account_status
             FROM registrations r
             JOIN courses c ON r.course_id = c.id
             JOIN users u ON r.user_id = u.id
             WHERE r.user_id = :uid AND r.status = 'approved'
             ORDER BY r.reviewed_at DESC LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getById(int $registrationId): ?array {
        $stmt = $this->db->prepare(
            "SELECT r.*, c.title AS course_title, c.abbreviation, c.duration_months,
                    u.username, u.email AS user_email, u.account_status
             FROM registrations r
             JOIN courses c ON r.course_id = c.id
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.id = :id"
        );
        $stmt->execute([':id' => $registrationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setCanPostProject(int $registrationId, bool $allowed): bool {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET can_post_project = :allowed WHERE id = :id"
        );
        return $stmt->execute([
            ':allowed' => $allowed ? 1 : 0,
            ':id' => $registrationId,
        ]);
    }

    /**
     * Compute total amount owed (registration fee + course fee).
     */
    public function totalOwed(array $reg): float {
        $regFee = $reg['registration_fee_waived'] ? 0 : (float) $reg['registration_fee'];
        return $regFee + (float) $reg['course_fee'];
    }

    /**
     * Expected next installment amount based on plan progress.
     */
    public function nextInstallmentAmount(array $reg): float {
        $installments = RegistrationModel::calculateInstallments(
            (float) $reg['course_fee'],
            $reg['payment_plan'],
            (int) $reg['duration_months']
        );
        $paid = (int) $reg['installments_paid'];
        if ($paid >= $installments['count']) {
            return 0;
        }
        $isLast = ($paid + 1) === $installments['count'];
        return $isLast ? $installments['last_amount'] : $installments['amount'];
    }

    public function setNextPaymentDue(int $registrationId, string $paymentPlan): void {
        if ($paymentPlan === 'full') {
            $stmt = $this->db->prepare("UPDATE registrations SET next_payment_due = NULL WHERE id = :id");
            $stmt->execute([':id' => $registrationId]);
            return;
        }

        $interval = $paymentPlan === 'monthly' ? '+1 month' : '+3 months';
        $due = date('Y-m-d', strtotime($interval));
        $stmt = $this->db->prepare("UPDATE registrations SET next_payment_due = :due WHERE id = :id");
        $stmt->execute([':due' => $due, ':id' => $registrationId]);
    }

    public function lockPortal(int $registrationId): void {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET portal_locked = 1, portal_locked_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $registrationId]);

        $reg = $this->getById($registrationId);
        if ($reg && $reg['user_id']) {
            $stmt = $this->db->prepare("UPDATE users SET account_status = 'suspended' WHERE id = :uid");
            $stmt->execute([':uid' => $reg['user_id']]);
        }
    }

    public function unlockPortal(int $registrationId): void {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET portal_locked = 0, portal_locked_at = NULL WHERE id = :id"
        );
        $stmt->execute([':id' => $registrationId]);

        $reg = $this->getById($registrationId);
        if ($reg && $reg['user_id']) {
            $stmt = $this->db->prepare("UPDATE users SET account_status = 'active' WHERE id = :uid");
            $stmt->execute([':uid' => $reg['user_id']]);
        }
    }

    public function incrementInstallment(int $registrationId): void {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET installments_paid = installments_paid + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $registrationId]);
    }

    public function markCredentialsSent(int $registrationId): void {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET credentials_sent_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $registrationId]);
    }

    /**
     * Auto-lock portal when payment is overdue (monthly = 1 month, 3months = 3 months).
     * Returns true if portal was just locked.
     */
    public function ensurePortalStatus(array $reg): bool {
        if ($reg['payment_plan'] === 'full') {
            return false;
        }
        if ($reg['portal_locked']) {
            return false;
        }
        if (empty($reg['next_payment_due'])) {
            return false;
        }
        if (strtotime($reg['next_payment_due']) >= strtotime('today')) {
            return false;
        }

        $installments = RegistrationModel::calculateInstallments(
            (float) $reg['course_fee'],
            $reg['payment_plan'],
            (int) $reg['duration_months']
        );
        if ((int) $reg['installments_paid'] >= $installments['count']) {
            return false;
        }

        $this->lockPortal((int) $reg['id']);
        return true;
    }

    public function initializeOnApproval(int $registrationId, string $paymentPlan): void {
        $stmt = $this->db->prepare(
            "UPDATE registrations SET installments_paid = 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $registrationId]);
        $this->setNextPaymentDue($registrationId, $paymentPlan);
    }
}
