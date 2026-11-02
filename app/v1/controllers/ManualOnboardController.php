<?php
// app/v1/controllers/ManualOnboardController.php
namespace Controllers;

require_once __DIR__ . '/../helpers/registration_number.php';
use Models\Connection;
use Models\CourseModel;
use Models\PaymentModel;
use Models\ProgressModel;
use Models\RegistrationModel;
use Models\StudentModel;

require_once __DIR__ . '/StudentController.php';

/**
 * Admin manual onboarding for students already learning (with or without a portal account).
 */
class ManualOnboardController {

    private \PDO $db;
    private CourseModel $courseModel;
    private StudentModel $studentModel;
    private PaymentModel $paymentModel;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->courseModel = new CourseModel($this->db);
        $this->studentModel = new StudentModel($this->db);
        $this->paymentModel = new PaymentModel($this->db);
    }

    /**
     * Onboard a student who does NOT yet have a portal account.
     *
     * @return array{ok:bool, error?:string, registration_id?:int, reg_no?:string, temp_password?:string, login_email?:string}
     */
    public function onboardNew(array $data, int $adminId): array {
        $fullName = trim($data['full_name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');

        if ($fullName === '' || $email === '' || $phone === '') {
            return ['ok' => false, 'error' => 'Full name, email, and phone are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid email address is required.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'An account with this email already exists. Use the "Existing student" tab instead.'];
        }

        $course = $this->courseModel->getById((int) ($data['course_id'] ?? 0));
        if (!$course || empty($course['is_active'])) {
            return ['ok' => false, 'error' => 'Selected course is not available.'];
        }

        $enrollment = $this->normalizeEnrollmentData($data, $course);
        if (!empty($enrollment['error'])) {
            return ['ok' => false, 'error' => $enrollment['error']];
        }

        $resolvedAdmin = resolve_valid_user_id($this->db, $adminId, true);
        if ($resolvedAdmin === null) {
            return ['ok' => false, 'error' => 'No valid admin account found.'];
        }

        $tempPassword = bin2hex(random_bytes(8));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        try {
            $this->db->beginTransaction();

            registration_number_lock($this->db);
            $regNo = registration_number_next($this->db);

            $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = 'student' LIMIT 1");
            $stmt->execute();
            $studentRoleId = (int) $stmt->fetchColumn();
            if (!$studentRoleId) {
                throw new \RuntimeException("'student' role not found.");
            }

            $mustChangePassword = !empty($data['must_change_password']) ? 1 : 0;
            try {
                $stmt = $this->db->prepare(
                    "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password)
                     VALUES (:username, :email, :hash, :role_id, 1, 1, 'active', :mcp)"
                );
                $stmt->execute([
                    ':username' => $regNo,
                    ':email'    => $email,
                    ':hash'     => $hash,
                    ':role_id'  => $studentRoleId,
                    ':mcp'      => $mustChangePassword,
                ]);
            } catch (\PDOException $e) {
                $stmt = $this->db->prepare(
                    "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                     VALUES (:username, :email, :hash, :role_id, 1, 1, 'active')"
                );
                $stmt->execute([
                    ':username' => $regNo,
                    ':email'    => $email,
                    ':hash'     => $hash,
                    ':role_id'  => $studentRoleId,
                ]);
            }
            $userId = (int) $this->db->lastInsertId();

            $nameParts = explode(' ', $fullName, 2);
            $stmt = $this->db->prepare(
                "INSERT INTO user_profiles (user_id, first_name, last_name, phone)
                 VALUES (:uid, :fn, :ln, :phone)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':fn'    => $nameParts[0],
                ':ln'    => $nameParts[1] ?? '',
                ':phone' => $phone,
            ]);

            $registrationId = $this->insertApprovedRegistration(
                $regNo,
                $fullName,
                $email,
                $phone,
                $course,
                $enrollment,
                $userId,
                $resolvedAdmin
            );

            $this->db->commit();

            $this->finalizeEnrollment($registrationId, $course, $enrollment, $resolvedAdmin);

            return [
                'ok'            => true,
                'registration_id' => $registrationId,
                'reg_no'        => $regNo,
                'temp_password' => $tempPassword,
                'login_email'   => $email,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[ManualOnboardController::onboardNew] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Onboarding failed: ' . $e->getMessage()];
        } finally {
            registration_number_unlock($this->db);
        }
    }

    private function normalizeEnrollmentData(array $data, array $course): array {
        $studyMode = $data['study_mode'] ?? 'online';
        $paymentPlan = $data['payment_plan'] ?? 'monthly';
        if (!in_array($studyMode, ['physical', 'online'], true)) {
            return ['error' => 'Invalid study mode.'];
        }
        if (!in_array($paymentPlan, ['monthly', '3months', 'full'], true)) {
            return ['error' => 'Invalid payment plan.'];
        }

        $durationMonths = course_effective_months($course);
        $learningMonth = max(1, min($durationMonths, (int) ($data['current_learning_month'] ?? 1)));
        $installmentsPaid = max(0, (int) ($data['installments_paid'] ?? 0));
        $amountPaidPrior = max(0, (float) ($data['amount_paid_prior'] ?? 0));
        $waiveRegFee = !empty($data['registration_fee_waived']);
        $portalLocked = !empty($data['portal_locked']);
        $feeNote = trim((string) ($data['fee_balance_note'] ?? ''));
        $feeStatus = $data['fee_status'] ?? 'current';
        if (!in_array($feeStatus, ['current', 'partial', 'overdue', 'paid_in_full'], true)) {
            $feeStatus = 'current';
        }
        $paymentRef = trim((string) ($data['payment_reference'] ?? 'Manual onboarding — prior payments'));

        $installments = RegistrationModel::calculateInstallments(
            (float) $course['course_fee'],
            $paymentPlan,
            $durationMonths
        );
        if ($installmentsPaid > $installments['count']) {
            return ['error' => 'Installments paid cannot exceed plan total (' . $installments['count'] . ').'];
        }

        $regFee = $waiveRegFee ? 0 : RegistrationModel::registrationFeeFor($studyMode);

        return [
            'study_mode'             => $studyMode,
            'payment_plan'           => $paymentPlan,
            'learning_month'         => $learningMonth,
            'installments_paid'      => $installmentsPaid,
            'amount_paid_prior'      => $amountPaidPrior,
            'waive_reg_fee'          => $waiveRegFee,
            'portal_locked'          => $portalLocked,
            'fee_note'               => $feeNote,
            'fee_status'             => $feeStatus,
            'payment_ref'            => $paymentRef !== '' ? $paymentRef : 'Manual onboarding',
            'installments'           => $installments,
            'reg_fee'                => $regFee,
        ];
    }

    private function insertApprovedRegistration(
        string $regNo,
        string $fullName,
        string $email,
        string $phone,
        array $course,
        array $enrollment,
        int $userId,
        int $adminId
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO registrations
             (registration_no, full_name, email, phone, course_id, study_mode, id_document_path,
              registration_fee, course_fee, payment_plan, installment_count, installment_amount,
              payment_reference, possible_returning_student, enrollment_type, current_learning_month,
              fee_balance_note, fee_status, registration_fee_waived, installments_paid,
              status, reviewed_by, reviewed_at, user_id, portal_locked)
             VALUES
             (:reg_no, :full_name, :email, :phone, :course_id, :study_mode, :id_doc,
              :reg_fee, :course_fee, :plan, :inst_count, :inst_amount,
              :pay_ref, 0, 'continuing', :learning_month, :fee_note, :fee_status, :waived, :inst_paid,
              'approved', :admin, NOW(), :user_id, :locked)"
        );
        $stmt->execute([
            ':reg_no'         => $regNo,
            ':full_name'      => $fullName,
            ':email'          => $email,
            ':phone'          => $phone,
            ':course_id'      => (int) $course['id'],
            ':study_mode'     => $enrollment['study_mode'],
            ':id_doc'         => 'admin/manual-onboarding',
            ':reg_fee'        => $enrollment['reg_fee'],
            ':course_fee'     => $course['course_fee'],
            ':plan'           => $enrollment['payment_plan'],
            ':inst_count'     => $enrollment['installments']['count'],
            ':inst_amount'    => $enrollment['installments']['amount'],
            ':pay_ref'        => $enrollment['payment_ref'],
            ':learning_month' => $enrollment['learning_month'],
            ':fee_note'       => $enrollment['fee_note'] !== '' ? $enrollment['fee_note'] : null,
            ':fee_status'     => $enrollment['fee_status'],
            ':waived'         => $enrollment['waive_reg_fee'] ? 1 : 0,
            ':inst_paid'      => $enrollment['installments_paid'],
            ':admin'          => $adminId,
            ':user_id'        => $userId,
            ':locked'         => $enrollment['portal_locked'] ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function finalizeEnrollment(int $registrationId, array $course, array $enrollment, int $adminId): void {
        if ($enrollment['amount_paid_prior'] > 0) {
            $stmt = $this->db->prepare(
                "INSERT INTO payments (registration_id, amount, payer_reference, status, recorded_by, reviewed_by, reviewed_at, notes)
                 VALUES (:rid, :amount, :ref, 'approved', :admin, :admin, NOW(), 'Prior payments — manual onboarding')"
            );
            $stmt->execute([
                ':rid'    => $registrationId,
                ':amount' => $enrollment['amount_paid_prior'],
                ':ref'    => $enrollment['payment_ref'],
                ':admin'  => $adminId,
            ]);
            $paymentId = (int) $this->db->lastInsertId();
            $reg = $this->studentModel->getById($registrationId);
            $receiptNo = $this->paymentModel->generateReceiptNo($paymentId);
            $payment = $this->paymentModel->getById($paymentId);
            $receiptHtml = \ReceiptGenerator::generate($reg, array_merge($payment, ['receipt_no' => $receiptNo]), $enrollment['amount_paid_prior']);
            $this->db->prepare(
                "UPDATE payments SET receipt_no = :receipt, receipt_html = :html WHERE id = :id"
            )->execute([':receipt' => $receiptNo, ':html' => $receiptHtml, ':id' => $paymentId]);
        }

        $this->studentModel->setNextPaymentDue($registrationId, $enrollment['payment_plan']);
        if ($enrollment['portal_locked']) {
            $this->studentModel->lockPortal($registrationId);
        } else {
            $this->studentModel->unlockPortal($registrationId);
        }

        $progressModel = new ProgressModel($this->db);
        $progressModel->initializeForRegistration($registrationId, (int) $course['id'], $enrollment['learning_month']);
    }
}
