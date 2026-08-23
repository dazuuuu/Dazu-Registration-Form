<?php
// app/models/RegistrationModel.php
namespace Models;

class RegistrationModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public const REG_FEE_ONLINE   = 1000;
    public const REG_FEE_PHYSICAL = 1500;

    /**
     * Pure calculation, no DB access — keep this trustworthy in isolation.
     * Splits $courseFee into $installmentCount parts; every part is equal except
     * the last, which absorbs whatever the division didn't divide evenly.
     *
     * @return array{count:int, amount:float, last_amount:float}
     */
    public static function calculateInstallments(float $courseFee, string $plan, int $durationMonths): array {
        switch ($plan) {
            case 'monthly':
                $count = max(1, $durationMonths);
                break;
            case '3months':
                $count = 3;
                break;
            case 'full':
                $count = 1;
                break;
            default:
                $count = 1;
        }

        // Work in cents to avoid float rounding drift, then convert back.
        $totalCents = (int) round($courseFee * 100);
        $baseCents  = intdiv($totalCents, $count);
        $remainder  = $totalCents - ($baseCents * $count);

        $amount     = $baseCents / 100;
        $lastAmount = ($baseCents + $remainder) / 100;

        return ['count' => $count, 'amount' => $amount, 'last_amount' => $lastAmount];
    }

    public static function registrationFeeFor(string $studyMode): int {
        return $studyMode === 'physical' ? self::REG_FEE_PHYSICAL : self::REG_FEE_ONLINE;
    }

    /**
     * Checks for a prior APPROVED registration with the same email or phone.
     * This only sets a flag for the admin to review — it never waives a fee on its own.
     */
    public function findPossibleReturningStudent(string $email, string $phone): ?array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, registration_no, email, phone FROM registrations
             WHERE status = 'approved' AND (email = :email OR phone = :phone)
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([':email' => $email, ':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{ok:bool, error?:string, id?:int, registration_no?:string}
     */
    public function submit(array $data, CourseModel $courseModel): array {
        $course = $courseModel->getById((int) $data['course_id']);
        if (!CourseModel::isShareable($course)) {
            return ['ok' => false, 'error' => 'Selected course is not available.'];
        }
        $isFree = !empty($course['is_free']);

        if (empty(trim($data['full_name'] ?? ''))) {
            return ['ok' => false, 'error' => 'Full name is required.'];
        }
        if (empty(trim($data['email'] ?? ''))) {
            return ['ok' => false, 'error' => 'Email address is required.'];
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid email address is required.'];
        }
        if (!in_array($data['study_mode'] ?? '', ['physical', 'online'], true)) {
            return ['ok' => false, 'error' => 'Please select a study mode.'];
        }

        require_once __DIR__ . '/../helpers/course_fee_structure.php';
        require_once __DIR__ . '/../helpers/registration_number.php';

        $studyMode = $data['study_mode'];
        $courseFee = course_fee_for_mode($course, $studyMode);
        if (!$isFree && $courseFee <= 0) {
            $courseFee = (float) ($course['course_fee'] ?? 0);
        }

        $paymentPlan = $data['payment_plan'] ?? 'full';
        if (!in_array($paymentPlan, ['monthly', '3months', 'full'], true)) {
            $paymentPlan = 'full';
        }

        $installments = self::calculateInstallments($courseFee, $paymentPlan, (int) $course['duration_months']);
        $regFee = $isFree ? 0 : self::registrationFeeFor($studyMode);
        $phone = trim((string) ($data['phone'] ?? ''));
        $returning = $phone !== ''
            ? $this->findPossibleReturningStudent($data['email'], $phone)
            : $this->findPossibleReturningStudent($data['email'], $data['email']);

        $referralNo = !empty($data['referral_registration_no']) ? trim($data['referral_registration_no']) : null;
        $idDoc = $data['id_document_path'] ?? null;
        $paymentRef = trim((string) ($data['payment_reference'] ?? ''));
        if ($paymentRef === '') {
            $paymentRef = $isFree ? 'FREE COURSE ENROLLMENT' : 'ENROLLMENT SUBMITTED — PAYMENT PENDING';
        }

        $regNo = null;
        try {
            $this->db->beginTransaction();

            $seq = registration_number_claim($this->db, (int) $course['id']);
            $regNo = registration_number_format((string) $course['abbreviation'], $seq);

            $params = [
                ':registration_no'    => $regNo,
                ':full_name'          => trim($data['full_name']),
                ':email'              => strtolower(trim($data['email'])),
                ':phone'              => $phone !== '' ? $phone : null,
                ':course_id'          => $course['id'],
                ':study_mode'         => $studyMode,
                ':id_document_path'   => $idDoc,
                ':registration_fee'   => $regFee,
                ':course_fee'         => $courseFee,
                ':payment_plan'       => $paymentPlan,
                ':installment_count'  => $installments['count'],
                ':installment_amount' => $installments['amount'],
                ':payment_reference'  => $paymentRef,
                ':free_course_reason' => $isFree ? trim($data['free_course_reason'] ?? '') : null,
                ':returning'          => $returning ? 1 : 0,
                ':referral'           => $referralNo,
            ];

            $sql = "INSERT INTO registrations
                    (registration_no, full_name, email, phone, course_id, study_mode, id_document_path,
                     registration_fee, course_fee, payment_plan, installment_count, installment_amount,
                     payment_reference, free_course_reason, referral_registration_no, possible_returning_student)
                    VALUES
                    (:registration_no, :full_name, :email, :phone, :course_id, :study_mode, :id_document_path,
                     :registration_fee, :course_fee, :payment_plan, :installment_count, :installment_amount,
                     :payment_reference, :free_course_reason, :referral, :returning)";

            try {
                $this->db->prepare($sql)->execute($params);
            } catch (\PDOException $e) {
                if (stripos($e->getMessage(), 'registration_no') !== false && str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
                // Fallback without registration_no column assignment at insert — older DB
                unset($params[':registration_no']);
                $sql = "INSERT INTO registrations
                        (full_name, email, phone, course_id, study_mode, id_document_path,
                         registration_fee, course_fee, payment_plan, installment_count, installment_amount,
                         payment_reference, free_course_reason, referral_registration_no, possible_returning_student)
                        VALUES
                        (:full_name, :email, :phone, :course_id, :study_mode, :id_document_path,
                         :registration_fee, :course_fee, :payment_plan, :installment_count, :installment_amount,
                         :payment_reference, :free_course_reason, :referral, :returning)";
                $this->db->prepare($sql)->execute($params);
                $regNo = null;
            }

            $id = (int) $this->db->lastInsertId();

            if ($regNo !== null) {
                $this->db->prepare('UPDATE registrations SET registration_no = :reg_no WHERE id = :id AND registration_no IS NULL')
                    ->execute([':reg_no' => $regNo, ':id' => $id]);
            }

            $this->db->commit();
            return ['ok' => true, 'id' => $id, 'registration_no' => $regNo];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[RegistrationModel::submit] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save enrollment. Please try again.'];
        }
    }

    public function getById(int $id): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT r.*, c.title AS course_title, c.abbreviation, c.duration_months, c.is_free
                 FROM registrations r JOIN courses c ON r.course_id = c.id WHERE r.id = :id"
            );
            $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            // DB not yet migrated (missing courses.is_free) — fall back rather than fatal,
            // run databases/migrations/033_course_requirements_and_free_flag.sql to fix.
            $stmt = $this->db->prepare(
                "SELECT r.*, c.title AS course_title, c.abbreviation, c.duration_months
                 FROM registrations r JOIN courses c ON r.course_id = c.id WHERE r.id = :id"
            );
            $stmt->execute([':id' => $id]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Permanently remove a registration. Deletes linked student user account if approved.
     */
    public function delete(int $id): array {
        $reg = $this->getById($id);
        if (!$reg) {
            return ['ok' => false, 'error' => 'Registration not found.'];
        }

        $userId = !empty($reg['user_id']) ? (int) $reg['user_id'] : null;

        try {
            $this->db->beginTransaction();

            $this->db->prepare('DELETE FROM registrations WHERE id = :id')->execute([':id' => $id]);

            if ($userId) {
                $this->db->prepare('DELETE FROM users WHERE id = :uid')->execute([':uid' => $userId]);
            }

            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[RegistrationModel::delete] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not delete registration: ' . $e->getMessage()];
        }
    }

    public function listByStatus(string $status): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, c.title AS course_title, c.abbreviation
             FROM registrations r JOIN courses c ON r.course_id = c.id
             WHERE r.status = :status ORDER BY r.created_at DESC"
        );
        $stmt->execute([':status' => $status]);
        return $stmt->fetchAll();
    }
}