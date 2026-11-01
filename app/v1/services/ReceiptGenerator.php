<?php
// app/services/ReceiptGenerator.php
// Generates HTML payment receipts for course fee payments.
// The look comes from Admin Settings → Receipts (title, colour, header lines, which rows show, footer and the NB printed on every
// receipt). A receipt can also carry its own NB (payments.receipt_note), typed on the receipt page by the office.

require_once __DIR__ . '/../helpers/currency.php';
require_once __DIR__ . '/../helpers/site_logo.php';

class ReceiptGenerator {

    /** Every setting with its default. Keys are saved in site_settings as rcpt_<key>. */
    public const DEFAULTS = [
        'title'      => 'Official Payment Receipt',
        'accent'     => '#44A024',
        'company'    => 'Dazu Training Institute',
        'header'     => '',            // extra lines under the name: phone, address, P.O. Box (one per line)
        'show_logo'  => '1',
        'show_email' => '1',
        'show_phone' => '1',
        'show_plan'  => '1',
        'nb'         => '',            // NB printed on every receipt
        'footer'     => 'This is an official receipt. Keep it for your records.',
    ];

    public static function settings(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $s = self::DEFAULTS;
        try {
            if (!class_exists('SettingModel')) require_once __DIR__ . '/../models/SettingModel.php';
            if (!class_exists('Models\\Connection')) require_once __DIR__ . '/../models/Connection.php';
            $m = new SettingModel(\Models\Connection::getInstance()->getConnection());
            foreach ($s as $k => $v) {
                $val = $m->get('rcpt_' . $k);
                if ($val !== null) $s[$k] = $val;
            }
        } catch (Throwable $e) {
            // defaults
        }
        if (!preg_match('/^#[0-9a-f]{6}$/i', $s['accent'])) $s['accent'] = self::DEFAULTS['accent'];
        return $cache = $s;
    }

    /**
     * @param array $reg   Registration row with course_title
     * @param array $payment Payment row (receipt_note = the NB for this receipt only)
     * @param float $totalPaid  Sum of all approved payments including this one
     * @param string|null $companyName  null = the name from Settings
     * @param array|null $s  settings to use instead of the saved ones (the live preview)
     */
    public static function generate(array $reg, array $payment, float $totalPaid, ?string $companyName = null, ?array $s = null): string {
        $s = $s ?? self::settings();
        $companyName = $companyName ?? ($s['company'] !== '' ? $s['company'] : self::DEFAULTS['company']);
        $a = self::e($s['accent']);
        $totalOwed = self::totalOwed($reg);
        $balance = max(0, $totalOwed - $totalPaid);
        $receiptNo = $payment['receipt_no'] ?? ('RCP-PENDING');
        $logo = '';
        if (!empty($s['show_logo'])) {
            require_once __DIR__ . '/../helpers/course_share.php';
            $logo = course_share_absolute_url(site_logo_url());   // full address: the receipt is also emailed
        }

        $lines = '';
        foreach (array_filter(array_map('trim', explode("\n", (string) $s['header']))) as $l) {
            $lines .= '<div style="font-size:12px;color:#64748b;margin-top:2px;">' . self::e($l) . '</div>';
        }
        $nbs = array_filter([trim((string) ($payment['receipt_note'] ?? '')), trim((string) $s['nb'])]);

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;background:#fff;">'
            . '<div style="background:#fff;color:#0f172a;padding:20px 24px;text-align:center;border-bottom:3px solid ' . $a . ';">'
            . ($logo ? '<img src="' . self::e($logo) . '" alt="" style="height:56px;margin-bottom:8px;">' : '')
            . '<h1 style="margin:0;font-size:20px;color:' . $a . ';font-weight:800;">' . self::e($companyName) . '</h1>'
            . $lines
            . '<p style="margin:10px 0 0;font-size:13px;color:#0f172a;letter-spacing:.12em;text-transform:uppercase;font-weight:700;">' . self::e($s['title']) . '</p>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Receipt No.', self::e($receiptNo))
            . self::row('Date', self::e(date('d M Y H:i', strtotime($payment['reviewed_at'] ?? $payment['paid_at'] ?? 'now'))))
            . self::row('Admission Number', self::e($reg['registration_no'] ?? '—'))
            . self::row('Student Name', self::e($reg['full_name']))
            . (!empty($s['show_email']) ? self::row('Email', self::e($reg['email'])) : '')
            . (!empty($s['show_phone']) ? self::row('Phone', self::e($reg['phone'])) : '')
            . self::row('Course', self::e($reg['course_title']))
            . (!empty($s['show_plan']) ? self::row('Payment Plan', self::e(self::planLabel((string) $reg['payment_plan']))) : '')
            . '</table>'
            . '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">'
            . '<table style="width:100%;font-size:14px;border-collapse:collapse;">'
            . self::row('Payer Reference', self::e($payment['payer_reference']))
            . (!empty($payment['mpesa_code']) ? self::row('M-Pesa Code', self::e($payment['mpesa_code'])) : '')
            . self::row('Amount Paid', '<strong style="color:' . $a . ';">' . format_price($payment['amount']) . '</strong>')
            . self::row('Total Paid to Date', format_price($totalPaid))
            . self::row('Total Course Cost', format_price($totalOwed))
            . self::row('Balance Remaining', $balance > 0
                ? '<strong style="color:#dc2626;">' . format_price($balance) . '</strong>'
                : '<strong style="color:#16a34a;">Fully Paid</strong>')
            . '</table>';
        foreach ($nbs as $nb) {
            $html .= '<div style="margin-top:18px;border-left:4px solid ' . $a . ';background:#f6faf3;padding:10px 14px;font-size:13px;color:#1f2937;border-radius:0 6px 6px 0;">'
                . '<strong>NB:</strong> ' . nl2br(self::e($nb)) . '</div>';
        }
        $html .= '</div>'
            . '<div style="background:#f8f9fa;padding:16px 24px;text-align:center;font-size:12px;color:#666;">'
            . nl2br(self::e($s['footer'] !== '' ? $s['footer'] : 'This is an official receipt from ' . $companyName . '. Keep for your records.'))
            . '</div>'
            . '</div>';

        return $html;
    }

    /** Build a stored receipt again (after its NB or the Settings changed). The totals are as they were when it was paid. */
    public static function rebuild(PDO $pdo, int $paymentId): bool {
        if (!class_exists('Models\\PaymentModel')) require_once __DIR__ . '/../models/PaymentModel.php';
        $p = (new \Models\PaymentModel($pdo))->getById($paymentId);
        if (!$p || $p['status'] !== 'approved' || empty($p['receipt_no'])) return false;
        $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE registration_id = ? AND status = 'approved' AND id <= ?");
        $st->execute([$p['registration_id'], $paymentId]);
        $html = self::generate($p, $p, (float) $st->fetchColumn());
        return $pdo->prepare('UPDATE payments SET receipt_html = ? WHERE id = ?')->execute([$html, $paymentId]);
    }

    /** A made-up receipt for the Settings preview. */
    public static function sample(array $s): string {
        $reg = ['registration_no' => 'DTI-2026-0042', 'full_name' => 'Jane Wanjiku', 'email' => 'jane@example.com', 'phone' => '0712 345 678',
            'course_title' => 'Web Development', 'payment_plan' => '3months', 'course_fee' => 30000, 'registration_fee' => 1000, 'registration_fee_waived' => 0];
        $pay = ['receipt_no' => 'RCP-2026-00123', 'payer_reference' => '0712 345 678', 'mpesa_code' => 'TJK4ABC12X', 'amount' => 10000,
            'reviewed_at' => date('Y-m-d H:i:s')];
        return self::generate($reg, $pay, 11000, null, $s);
    }

    private static function totalOwed(array $reg): float {
        $regFee = !empty($reg['registration_fee_waived']) ? 0 : (float) ($reg['registration_fee'] ?? 0);
        return $regFee + (float) ($reg['course_fee'] ?? 0);
    }

    private static function planLabel(string $plan): string {
        return ['monthly' => 'Monthly Installments', '3months' => '3-Month Plan', 'full' => 'Full Payment'][$plan] ?? $plan;
    }

    /** $value is HTML: escape plain text before passing it. */
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
