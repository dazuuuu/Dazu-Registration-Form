<?php
// Superadmin: record a payment made at the office or by M-Pesa. Approved straight away, receipt made and emailed, portal unlocked.
require_once __DIR__ . '/../models/PaymentModel.php';
require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../services/ReceiptGenerator.php';
require_once __DIR__ . '/../services/StudentNotifier.php';

if (!function_exists('sa_record_payment')) {
    /** @return array{0:string,1:string} flash type and message */
    function sa_record_payment(PDO $pdo, int $rid, float $amount, string $reference, ?string $notes, int $by): array
    {
        if ($amount <= 0) return ['error', 'Enter an amount greater than zero.'];
        $payments = new \Models\PaymentModel($pdo);
        $paymentId = $payments->recordManual($rid, $amount, $reference, $by, $notes);
        (new \Models\StudentModel($pdo))->unlockPortal($rid);
        $mail = ['ok' => false];
        try {
            $payment = $payments->getById($paymentId);
            $receiptNo = $payment['receipt_no'] ?: $payments->generateReceiptNo($paymentId);
            $html = \ReceiptGenerator::generate($payment, array_merge($payment, ['receipt_no' => $receiptNo]), $payments->getTotalApproved($rid));
            $pdo->prepare('UPDATE payments SET receipt_no = ?, receipt_html = ? WHERE id = ?')->execute([$receiptNo, $html, $paymentId]);
            $payment = $payments->getById($paymentId);
            $mail = (new StudentNotifier($pdo, null, true))->sendPaymentApproved($payment, $payment, (string) $payment['receipt_html']);
        } catch (Throwable $e) {
            error_log('[sa_record_payment] ' . $e->getMessage());
        }
        return [!empty($mail['ok']) ? 'success' : 'warning', 'Payment of KES ' . number_format($amount) . ' recorded and the portal unlocked.'
            . (!empty($mail['ok']) ? ' Receipt emailed to the student.' : ' The receipt email did not send.')];
    }
}
