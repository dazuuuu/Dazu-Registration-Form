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
        $logo = site_logo_url();

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#fff;color:#0f172a;padding:20px 24px;text-align:center;border-bottom:3px solid #15A24A;">'
            . '<img src="' . htmlspecialchars($logo) . '" alt="' . self::e($companyName) . '" style="max-height:56px;max-width:160px;object-fit:contain;margin-bottom:8px;">'
            . '<h1 style="margin:0;font-size:20px;color:#15A24A;">' . self::e($companyName) . '</h1>'
            . '<p style="margin:8px 0 0;opacity:0.85;font-size:14px;color:#475569;">Official Payment Receipt</p>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Receipt No.', $receiptNo)
            . self::row('Date', date('d M Y H:i', strtotime($payment['reviewed_at'] ?? $payment['paid_at'] ?? 'now')))
            . self::row('Student ID', $reg['registration_no'] ?? '—')
            . self::row('Student Name', $reg['full_name'])
            . self::row('Email', $reg['email'])
            . self::row('Phone', $reg['phone'])
            . self::row('Course', $reg['course_title'])
            . self::row('Payment Plan', self::planLabel($reg['payment_plan']))
            . '</table>'
            . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Payer Reference', $payment['payer_reference'])
            . ($payment['mpesa_code'] ? self::row('M-Pesa Code', $payment['mpesa_code']) : '')
            . self::row('Amount Paid', '<strong style="color:#0D9488;">' . format_price($payment['amount']) . '</strong>')
            . self::row('Total Paid to Date', format_price($totalPaid))
            . self::row('Total Course Cost', format_price($totalOwed))
            . self::row('Balance Remaining', $balance > 0
                ? '<strong style="color:#dc2626;">' . format_price($balance) . '</strong>'
                : '<strong style="color:#16a34a;">Fully Paid</strong>')
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

    private static function planLabel(string $plan): string {
        return ['monthly' => 'Monthly Installments', '3months' => '3-Month Plan', 'full' => 'Full Payment'][$plan] ?? $plan;
    }

    private static function row(string $label, string $value): string {
        return '<tr>'
            . '<td style="padding:8px 0;color:#666;width:45%;">' . self::e($label) . '</td>'
            . '<td style="padding:8px 0;text-align:right;">' . $value . '</td>'
            . '</tr>';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
