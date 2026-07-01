<?php
// app/controllers/StudentController.php
namespace Controllers;

use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;
use Models\RegistrationModel;

require_once __DIR__ . '/../services/StudentNotifier.php';
require_once __DIR__ . '/../services/ReceiptGenerator.php';

class StudentController {

    private $db;
    private $studentModel;
    private $paymentModel;
    private $notifier;

    public function __construct() {
        $this->db = Connection::getInstance()->getConnection();
        $this->studentModel = new StudentModel($this->db);
        $this->paymentModel = new PaymentModel($this->db);
        $this->notifier = new \StudentNotifier($this->db);
    }

    /**
     * Generate a new temp password and email credentials to the student.
     * @return array{ok:bool, error?:string, temp_password?:string}
     */
    public function sendCredentials(int $registrationId): array {
        $reg = $this->studentModel->getById($registrationId);
        if (!$reg || $reg['status'] !== 'approved' || !$reg['user_id']) {
            return ['ok' => false, 'error' => 'Student registration not found or not approved.'];
        }

        $tempPassword = bin2hex(random_bytes(8));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid");
        $stmt->execute([':hash' => $hash, ':uid' => $reg['user_id']]);

        $mailResult = $this->notifier->sendCredentials($reg, $tempPassword);
        $this->studentModel->markCredentialsSent($registrationId);

        if (!$mailResult['ok']) {
            return ['ok' => false, 'error' => 'Password reset but email failed: ' . $mailResult['message'], 'temp_password' => $tempPassword];
        }

        return ['ok' => true, 'temp_password' => $tempPassword];
    }

    /**
     * Student submits M-Pesa payment for verification.
     */
    public function submitPayment(int $registrationId, int $userId, string $mpesaCode, string $payerReference): array {
        $reg = $this->studentModel->getById($registrationId);
        if (!$reg || (int) $reg['user_id'] !== $userId) {
            return ['ok' => false, 'error' => 'Registration not found.'];
        }

        $amount = $this->studentModel->nextInstallmentAmount($reg);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'No payment is currently due.'];
        }

        $result = $this->paymentModel->submitPending($registrationId, $amount, $mpesaCode, $payerReference, $userId);
        if (!$result['ok']) {
            return $result;
        }

        $payment = $this->paymentModel->getById($result['id']);
        $this->notifier->sendPaymentSubmitted($reg, $payment);

        return $result;
    }

    /**
     * Admin approves a pending payment — unlocks portal, generates receipt, sends email.
     */
    public function approvePayment(int $paymentId, int $adminId): array {
        $payment = $this->paymentModel->getById($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Payment not found or already processed.'];
        }

        $reg = $this->studentModel->getById((int) $payment['registration_id']);
        if (!$reg) {
            return ['ok' => false, 'error' => 'Registration not found.'];
        }

        $receiptNo = $this->paymentModel->generateReceiptNo($paymentId);
        $totalPaid = $this->paymentModel->getTotalApproved((int) $payment['registration_id']) + (float) $payment['amount'];

        $receiptHtml = \ReceiptGenerator::generate($reg, array_merge($payment, ['receipt_no' => $receiptNo]), $totalPaid);

        if (!$this->paymentModel->approve($paymentId, $adminId, $receiptNo, $receiptHtml)) {
            return ['ok' => false, 'error' => 'Failed to approve payment.'];
        }

        $this->studentModel->incrementInstallment((int) $payment['registration_id']);
        $this->studentModel->unlockPortal((int) $payment['registration_id']);
        $this->studentModel->setNextPaymentDue((int) $payment['registration_id'], $reg['payment_plan']);

        $updatedPayment = $this->paymentModel->getById($paymentId);
        $updatedReg = $this->studentModel->getById((int) $payment['registration_id']);
        $this->notifier->sendPaymentApproved($updatedReg, $updatedPayment, $receiptHtml);

        return ['ok' => true, 'receipt_no' => $receiptNo];
    }

    /**
     * Admin rejects a pending payment.
     */
    public function rejectPayment(int $paymentId, int $adminId, string $reason): array {
        $payment = $this->paymentModel->getById($paymentId);
        if (!$payment || $payment['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Payment not found or already processed.'];
        }
        if (empty(trim($reason))) {
            return ['ok' => false, 'error' => 'A rejection reason is required.'];
        }

        if (!$this->paymentModel->reject($paymentId, $adminId, $reason)) {
            return ['ok' => false, 'error' => 'Failed to reject payment.'];
        }

        $reg = $this->studentModel->getById((int) $payment['registration_id']);
        $this->notifier->sendPaymentRejected($reg, $payment, $reason);

        return ['ok' => true];
    }

    /**
     * Record initial payment and send receipt on registration approval.
     */
    public function recordInitialPayment(array $reg, int $adminId): array {
        $installments = RegistrationModel::calculateInstallments(
            (float) $reg['course_fee'],
            $reg['payment_plan'],
            (int) $reg['duration_months']
        );
        $regFee = $reg['registration_fee_waived'] ? 0 : (float) $reg['registration_fee'];
        $initialAmount = $regFee + $installments['amount'];

        $stmt = $this->db->prepare(
            "INSERT INTO payments (registration_id, amount, payer_reference, status, recorded_by, reviewed_by, reviewed_at, notes)
             VALUES (:rid, :amount, :ref, 'approved', :admin, :admin, NOW(), 'Initial registration payment')"
        );
        $stmt->execute([
            ':rid'    => $reg['id'],
            ':amount' => $initialAmount,
            ':ref'    => $reg['payment_reference'],
            ':admin'  => $adminId,
        ]);
        $paymentId = (int) $this->db->lastInsertId();

        $receiptNo = $this->paymentModel->generateReceiptNo($paymentId);
        $payment = $this->paymentModel->getById($paymentId);
        $receiptHtml = \ReceiptGenerator::generate($reg, array_merge($payment, ['receipt_no' => $receiptNo]), $initialAmount);

        $stmt = $this->db->prepare(
            "UPDATE payments SET receipt_no = :receipt, receipt_html = :html WHERE id = :id"
        );
        $stmt->execute([':receipt' => $receiptNo, ':html' => $receiptHtml, ':id' => $paymentId]);

        $this->studentModel->initializeOnApproval((int) $reg['id'], $reg['payment_plan']);

        return ['ok' => true, 'receipt_no' => $receiptNo, 'receipt_html' => $receiptHtml, 'payment_id' => $paymentId];
    }
}
