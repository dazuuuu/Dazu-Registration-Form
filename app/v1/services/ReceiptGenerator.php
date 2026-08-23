<?php
// app/services/ReceiptGenerator.php
// Generates HTML payment receipts for course fee payments.

require_once __DIR__ . '/../helpers/currency.php';
require_once __DIR__ . '/../helpers/site_logo.php';

class ReceiptGenerator {

    /**
     * @param array $reg   Registration row with course_title
     * @param array $payment Payment row
     * @param float $totalPaid  Sum of all approved payments including this one
     * @param string $companyName
     */
    public static function generate(array $reg, array $payment, float $totalPaid, string $companyName = 'Dazu Hub Institute'): string {
        $totalOwed = self::totalOwed($reg);
        $balance = max(0, $totalOwed - $totalPaid);
        $receiptNo = $payment['receipt_no'] ?? ('RCP-PENDING');
        $logo = site_logo_data_uri() ?: site_logo_url();
        $companyEsc = self::e($companyName);

        $html = '<div style="font-family:Georgia,\'Times New Roman\',serif;max-width:640px;margin:0 auto;border:1px solid #171717;border-radius:0;overflow:hidden;background:#fff;">'
            . '<div style="background:#fff;color:#171717;padding:20px 24px;text-align:center;border-bottom:2px solid #171717;">'
            . '<img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="' . $companyEsc . '" style="max-height:56px;max-width:160px;object-fit:contain;margin-bottom:8px;filter:grayscale(100%);">'
            . '<h1 style="margin:0;font-size:18px;color:#171717;letter-spacing:.06em;text-transform:uppercase;">' . $companyEsc . '</h1>'
            . '<p style="margin:8px 0 0;font-size:12px;color:#525252;letter-spacing:.12em;text-transform:uppercase;">Official Payment Receipt</p>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Receipt No.', $receiptNo)
            . self::row('Date', date('d M Y H:i', strtotime($payment['reviewed_at'] ?? $payment['paid_at'] ?? 'now')))
            . self::row('Student ID', $reg['registration_no'] ?? null)
            . self::row('Student Name', $reg['full_name'] ?? null)
            . self::row('Email', $reg['email'] ?? null)
            . self::row('Phone', $reg['phone'] ?? null)
            . self::row('Course', $reg['course_title'] ?? null)
            . self::row('Payment Plan', self::planLabel($reg['payment_plan'] ?? null))
            . '</table>'
            . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Payer Reference', $payment['payer_reference'] ?? null)
            . (!empty($payment['mpesa_code']) ? self::row('M-Pesa Code', $payment['mpesa_code']) : '')
            . self::row('Amount Paid', '<strong>' . format_price($payment['amount']) . '</strong>', true)
            . self::row('Total Paid to Date', format_price($totalPaid))
            . self::row('Total Course Cost', format_price($totalOwed))
            . self::row('Balance Remaining', $balance > 0
                ? '<strong>' . format_price($balance) . '</strong>'
                : '<strong>Fully Paid</strong>', true)
            . '</table>'
            . '</div>'
            . '<div style="background:#f8f9fa;padding:16px 24px;text-align:center;font-size:12px;color:#666;">'
            . 'This is an official receipt from ' . self::e($companyName) . '. Keep for your records.'
            . '</div>'
            . '</div>';

        return $html;
    }

    private static function totalOwed(array $reg): float {
        $regFee = !empty($reg['registration_fee_waived']) ? 0 : (float) ($reg['registration_fee'] ?? 0);
        return $regFee + (float) ($reg['course_fee'] ?? 0);
    }

    private static function planLabel(?string $plan): string {
        if ($plan === null || trim($plan) === '') {
            return '—';
        }
        return ['monthly' => 'Monthly Installments', '3months' => '3-Month Plan', 'full' => 'Full Payment'][$plan] ?? $plan;
    }

    private static function row(string $label, ?string $value, bool $allowHtml = false): string {
        $display = $allowHtml ? (string) $value : self::e(self::displayValue($value));
        return '<tr>'
            . '<td style="padding:8px 0;color:#666;width:45%;">' . self::e($label) . '</td>'
            . '<td style="padding:8px 0;text-align:right;">' . $display . '</td>'
            . '</tr>';
    }

    private static function displayValue(?string $value): string {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : '—';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
