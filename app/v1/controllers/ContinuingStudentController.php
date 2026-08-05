<?php
// app/v1/controllers/ContinuingStudentController.php
namespace Controllers;

use Models\Connection;
use Models\CourseModel;
use Models\PaymentModel;
use Models\ProgressModel;
use Models\RegistrationModel;
use Models\StudentModel;

require_once __DIR__ . '/StudentController.php';
require_once __DIR__ . '/../helpers/course_duration.php';

class ContinuingStudentController {

    private \PDO $db;
    private RegistrationModel $regModel;
    private CourseModel $courseModel;
    private StudentModel $studentModel;
    private PaymentModel $paymentModel;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->regModel = new RegistrationModel($this->db);
        $this->courseModel = new CourseModel($this->db);
        $this->studentModel = new StudentModel($this->db);
        $this->paymentModel = new PaymentModel($this->db);
    }

    /**
     * Register an existing approved student for a new/continuing enrollment.
     *
     * @param array{
     *   prior_registration_id:int,
     *   course_id:int,
     *   study_mode:string,
     *   payment_plan:string,
     *   current_learning_month:int,
     *   installments_paid:int,
     *   amount_paid_prior:float,
     *   registration_fee_waived:bool,
     *   portal_locked:bool,
     *   fee_balance_note?:string,
     *   payment_reference?:string
     * } $data
     * @return array{ok:bool, error?:string, registration_id?:int, reg_no?:string}
     */
    public function register(array $data, int $adminId): array {
        $priorId = (int) ($data['prior_registration_id'] ?? 0);
        $prior = $this->studentModel->getById($priorId);
        if (!$prior || $prior['status'] !== 'approved' || empty($prior['user_id'])) {
            return ['ok' => false, 'error' => 'Select a valid enrolled student with an active portal account.'];
        }

        $course = $this->courseModel->getById((int) ($data['course_id'] ?? 0));
        if (!$course || empty($course['is_active'])) {
            return ['ok' => false, 'error' => 'Selected course is not available.'];
        }

        $studyMode = $data['study_mode'] ?? 'online';
        $paymentPlan = $data['payment_plan'] ?? 'monthly';
        if (!in_array($studyMode, ['physical', 'online'], true)) {
            return ['ok' => false, 'error' => 'Invalid study mode.'];
        }
        if (!in_array($paymentPlan, ['monthly', '3months', 'full'], true)) {
            return ['ok' => false, 'error' => 'Invalid payment plan.'];
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
        $paymentRef = trim((string) ($data['payment_reference'] ?? 'Continuing enrollment — prior payments'));

        $installments = RegistrationModel::calculateInstallments(
            (float) $course['course_fee'],
            $paymentPlan,
            $durationMonths
        );
        if ($installmentsPaid > $installments['count']) {
            return ['ok' => false, 'error' => 'Installments paid cannot exceed the plan total (' . $installments['count'] . ').'];
        }

        $regFee = $waiveRegFee ? 0 : RegistrationModel::registrationFeeFor($studyMode);
        $resolvedAdmin = resolve_valid_user_id($this->db, $adminId, true);
        if ($resolvedAdmin === null) {
            return ['ok' => false, 'error' => 'No valid admin account found.'];
        }

        $idDoc = $prior['id_document_path'] ?: 'admin/continuing-student';

        try {
            $this->db->beginTransaction();

            $seq = $this->courseModel->claimNextSequence((int) $course['id']);
            $regNo = sprintf('DH-%s%02d/%s', $course['abbreviation'], $seq, date('Y'));

            $stmt = $this->db->prepare(
                "INSERT INTO registrations
                 (registration_no, full_name, email, phone, course_id, study_mode, id_document_path,
                  registration_fee, course_fee, payment_plan, installment_count, installment_amount,
                  payment_reference, possible_returning_student, enrollment_type, prior_registration_id,
                  current_learning_month, fee_balance_note, fee_status, registration_fee_waived,
                  installments_paid, status, reviewed_by, reviewed_at, user_id, portal_locked)
                 VALUES
                 (:reg_no, :full_name, :email, :phone, :course_id, :study_mode, :id_doc,
                  :reg_fee, :course_fee, :plan, :inst_count, :inst_amount,
                  :pay_ref, 1, 'continuing', :prior_id, :learning_month, :fee_note, :fee_status, :waived,
                  :inst_paid, 'approved', :admin, NOW(), :user_id, :locked)"
            );
            $stmt->execute([
                ':reg_no'         => $regNo,
                ':full_name'      => $prior['full_name'],
                ':email'          => $prior['email'],
                ':phone'          => $prior['phone'],
                ':course_id'      => (int) $course['id'],
                ':study_mode'     => $studyMode,
                ':id_doc'         => $idDoc,
                ':reg_fee'        => $regFee,
                ':course_fee'     => $course['course_fee'],
                ':plan'           => $paymentPlan,
                ':inst_count'     => $installments['count'],
                ':inst_amount'    => $installments['amount'],
                ':pay_ref'        => $paymentRef !== '' ? $paymentRef : 'Continuing enrollment',
                ':prior_id'       => $priorId,
                ':learning_month' => $learningMonth,
                ':fee_note'       => $feeNote !== '' ? $feeNote : null,
                ':fee_status'     => $feeStatus,
                ':waived'         => $waiveRegFee ? 1 : 0,
                ':inst_paid'      => $installmentsPaid,
                ':admin'          => $resolvedAdmin,
                ':user_id'        => (int) $prior['user_id'],
                ':locked'         => $portalLocked ? 1 : 0,
            ]);
            $registrationId = (int) $this->db->lastInsertId();

            if ($amountPaidPrior > 0) {
                $stmt = $this->db->prepare(
                    "INSERT INTO payments (registration_id, amount, payer_reference, status, recorded_by, reviewed_by, reviewed_at, notes)
                     VALUES (:rid, :amount, :ref, 'approved', :admin, :admin, NOW(), 'Prior payments — continuing student')"
                );
                $stmt->execute([
                    ':rid'    => $registrationId,
                    ':amount' => $amountPaidPrior,
                    ':ref'    => $paymentRef !== '' ? $paymentRef : 'Prior installments',
                    ':admin'  => $resolvedAdmin,
                ]);
                $paymentId = (int) $this->db->lastInsertId();
                $receiptNo = $this->paymentModel->generateReceiptNo($paymentId);
                $approvedReg = $this->studentModel->getById($registrationId);
                $payment = $this->paymentModel->getById($paymentId);
                $receiptHtml = \ReceiptGenerator::generate($approvedReg, array_merge($payment, ['receipt_no' => $receiptNo]), $amountPaidPrior);
                $this->db->prepare(
                    "UPDATE payments SET receipt_no = :receipt, receipt_html = :html WHERE id = :id"
                )->execute([':receipt' => $receiptNo, ':html' => $receiptHtml, ':id' => $paymentId]);
            }

            $this->studentModel->setNextPaymentDue($registrationId, $paymentPlan);

            if ($portalLocked) {
                $this->studentModel->lockPortal($registrationId);
            } else {
                $this->studentModel->unlockPortal($registrationId);
            }

            $this->db->commit();

            $progressModel = new ProgressModel($this->db);
            $progressModel->initializeForRegistration($registrationId, (int) $course['id'], $learningMonth);

            return ['ok' => true, 'registration_id' => $registrationId, 'reg_no' => $regNo];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[ContinuingStudentController::register] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Update learning month and unlock curriculum units up to that month.
     */
    public function updateLearningMonth(int $registrationId, int $month): array {
        $reg = $this->studentModel->getById($registrationId);
        if (!$reg || $reg['status'] !== 'approved') {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $maxMonth = max(1, (int) $reg['duration_months']);
        $month = max(1, min($maxMonth, $month));

        $stmt = $this->db->prepare(
            "UPDATE registrations SET current_learning_month = :month WHERE id = :id"
        );
        $stmt->execute([':month' => $month, ':id' => $registrationId]);

        $progressModel = new ProgressModel($this->db);
        $progressModel->syncUnlockedThroughMonth($registrationId, (int) $reg['course_id'], $month);

        return ['ok' => true, 'current_learning_month' => $month];
    }

    /**
     * Update fee balance note and portal lock based on outstanding balance.
     */
    public function updateFeeStatus(int $registrationId, ?string $note, bool $portalLocked, string $feeStatus = 'current'): array {
        $reg = $this->studentModel->getById($registrationId);
        if (!$reg || $reg['status'] !== 'approved') {
            return ['ok' => false, 'error' => 'Student not found.'];
        }
        if (!in_array($feeStatus, ['current', 'partial', 'overdue', 'paid_in_full'], true)) {
            $feeStatus = 'current';
        }

        $stmt = $this->db->prepare(
            "UPDATE registrations SET fee_balance_note = :note, fee_status = :status WHERE id = :id"
        );
        $stmt->execute([
            ':note'   => $note !== '' ? $note : null,
            ':status' => $feeStatus,
            ':id'     => $registrationId,
        ]);

        if ($portalLocked) {
            $this->studentModel->lockPortal($registrationId);
        } else {
            $this->studentModel->unlockPortal($registrationId);
        }

        return ['ok' => true];
    }
}
