<?php
// app/v1/helpers/fee_documents.php
// Two PDFs a student downloads from Finance:
//  - Fee structure: every installment of their course with its due date (the first on the enrolment day, then the 5th of each
//    following month), the amount, and how much of it is paid (approved payments, oldest installment first).
//  - Fee statement: every payment they sent, dated when it was PAID (payments.paid_at: the moment the M-Pesa code was submitted,
//    never the approval time), its status, and the balance after each approved payment.
// Nothing here changes a payment or a balance.

require_once __DIR__ . '/certificate_pdf.php';            // dompdf, cert_logo_file()
require_once __DIR__ . '/../models/RegistrationModel.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!defined('FEE_DUE_DAY')) define('FEE_DUE_DAY', 5);   // every month after the first is paid by the 5th

if (!function_exists('fee_schedule')) {

    /** The enrolment day: when the office approved the enrolment, else when it was made. */
    function fee_start(array $reg): DateTimeImmutable
    {
        return new DateTimeImmutable(substr((string) ($reg['reviewed_at'] ?: $reg['created_at']), 0, 10));
    }

    /**
     * Rows: label, due (Y-m-d), amount, paid, left, state (Paid | Part paid | Overdue | Due | Upcoming).
     * $paid = the student's approved total, applied to the registration fee first, then each installment in order.
     */
    function fee_schedule(array $reg, float $paid): array
    {
        $rows = [];
        $start = fee_start($reg);
        if (empty($reg['registration_fee_waived']) && (float) $reg['registration_fee'] > 0) {
            $rows[] = ['label' => 'Registration fee', 'due' => $start->format('Y-m-d'), 'amount' => (float) $reg['registration_fee']];
        }
        $plan = (string) $reg['payment_plan'];
        $inst = Models\RegistrationModel::calculateInstallments((float) $reg['course_fee'], $plan, (int) $reg['duration_months']);
        for ($i = 1; $i <= $inst['count']; $i++) {
            $m = $start->modify('first day of +' . ($i - 1) . ' month');
            $due = $i === 1 ? $start : $m->setDate((int) $m->format('Y'), (int) $m->format('n'), FEE_DUE_DAY);
            $label = $inst['count'] === 1 ? 'Course fee (full payment)' : 'Month ' . $i . ' of ' . $inst['count'];
            $rows[] = ['label' => $label, 'due' => $due->format('Y-m-d'), 'amount' => $i === $inst['count'] ? $inst['last_amount'] : $inst['amount']];
        }
        $left = $paid;
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $r['paid'] = min($r['amount'], max(0, $left));
            $left -= $r['paid'];
            $r['left'] = round($r['amount'] - $r['paid'], 2);
            $r['state'] = $r['left'] <= 0 ? 'Paid' : ($r['due'] < $today ? 'Overdue' : ($r['paid'] > 0 ? 'Part paid' : ($r['due'] <= date('Y-m-d', strtotime('+30 days')) ? 'Due' : 'Upcoming')));
        }
        unset($r);
        return $rows;
    }

    /** Every payment, oldest first, with the balance after each approved one. */
    function fee_statement(PDO $db, array $reg, float $owed): array
    {
        $st = $db->prepare('SELECT id, amount, status, mpesa_code, payer_reference, receipt_no, paid_at FROM payments WHERE registration_id = ? ORDER BY paid_at, id');
        $st->execute([(int) $reg['id']]);
        $bal = $owed;
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if ($p['status'] === 'approved') $bal -= (float) $p['amount'];
            $rows[] = $p + ['balance' => $p['status'] === 'approved' ? max(0, $bal) : null];
        }
        return $rows;
    }

    function fee_money(float $v): string
    {
        return 'KSh ' . number_format($v, fmod($v, 1) ? 2 : 0);
    }

    function fee_plan_label(string $plan): string
    {
        return ['monthly' => 'Monthly installments', '3months' => '3-month plan', 'full' => 'Full payment'][$plan] ?? $plan;
    }

    /** The page around both documents: logo, title, the student's details, body, footer. */
    function fee_page(string $title, array $reg, string $body): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $logo = cert_logo_file();
        $logoTag = $logo ? '<img src="' . _cert_data_uri($logo) . '" style="height:16mm">' : '<b style="font-size:20pt">DAZU</b>';
        $phone = function_exists('site_phone_display') ? site_phone_display() : '';
        $host = preg_replace('#^https?://#', '', rtrim(preg_replace('#/verify.*$#', '', cert_verify_url()), '/'));
        $mode = ($reg['study_mode'] ?? '') === 'physical' ? 'Physical' . (trim((string) $reg['study_location']) ? ' (' . ucfirst(trim((string) $reg['study_location'])) . ')' : '') : 'Online';
        $G = '#048C44';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page{size:A4 portrait;margin:14mm 14mm 18mm}
body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#2b2b2b}
h1{font-size:15pt;margin:4mm 0 1mm;color:#222}
.sub{color:#666;font-size:8.5pt}
table{border-collapse:collapse;width:100%}
.info td{padding:1.2mm 0;vertical-align:top}.info .k{color:#666;width:30mm}
.t{margin-top:5mm}.t th{background:' . $G . ';color:#fff;text-align:left;padding:2mm;font-size:8pt}
.t td{padding:2mm;border-bottom:0.2mm solid #ddd}.t .r{text-align:right}
.t tr.tot td{font-weight:bold;border-top:0.4mm solid #333;border-bottom:0;background:#f1f8f4}
.s-Paid{color:#16794a;font-weight:bold}.s-Overdue,.s-rejected{color:#b42318;font-weight:bold}.s-pending{color:#9a6700;font-weight:bold}.s-approved{color:#16794a;font-weight:bold}
.box{margin-top:5mm;border:0.3mm solid ' . $G . ';background:#f1f8f4;padding:3mm;border-radius:2mm}
.foot{position:fixed;bottom:-12mm;left:0;right:0;text-align:center;color:#888;font-size:7.5pt;border-top:0.2mm solid #ddd;padding-top:2mm}
</style></head><body>
<div class="foot">Dazu Training Institute' . ($phone ? ' · ' . $e($phone) : '') . ' · ' . $e($host) . ' · Generated ' . date('j M Y, H:i') . ' EAT · This is a system-generated document.</div>
<table><tr><td>' . $logoTag . '</td><td style="text-align:right;vertical-align:bottom"><h1>' . $e($title) . '</h1><div class="sub">Dazu Training Institute</div></td></tr></table>
<div style="border-bottom:0.6mm solid ' . $G . ';margin:3mm 0 4mm"></div>
<table class="info"><tr><td class="k">Student name</td><td><b>' . $e(mb_convert_case(trim((string) $reg['full_name']), MB_CASE_TITLE, 'UTF-8')) . '</b></td>
<td class="k">Student No.</td><td><b>' . $e($reg['registration_no']) . '</b></td></tr>
<tr><td class="k">Course</td><td>' . $e($reg['course_title']) . '</td><td class="k">Mode</td><td>' . $e($mode) . '</td></tr>
<tr><td class="k">Payment plan</td><td>' . $e(fee_plan_label((string) $reg['payment_plan'])) . '</td><td class="k">Enrolled</td><td>' . $e(fee_start($reg)->format('j M Y')) . '</td></tr></table>'
            . $body . '</body></html>';
    }

    function fee_structure_html(array $reg, float $owed, float $paid): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rows = fee_schedule($reg, $paid);
        $tr = '';
        foreach ($rows as $i => $r) {
            $tr .= '<tr><td>' . ($i + 1) . '</td><td>' . $e($r['label']) . '</td><td>' . $e(date('D j M Y', strtotime($r['due']))) . '</td><td class="r">' . fee_money($r['amount']) . '</td>'
                 . '<td class="r">' . fee_money($r['paid']) . '</td><td class="s-' . $e(str_replace(' ', '', $r['state'])) . '">' . $e($r['state']) . '</td></tr>';
        }
        $next = null;
        foreach ($rows as $r) if ($r['left'] > 0) { $next = $r; break; }
        $monthly = count(array_filter($rows, fn($r) => str_starts_with($r['label'], 'Month '))) > 1;
        return fee_page('Fee Structure', $reg,
            '<table class="t"><thead><tr><th>#</th><th>Item</th><th>Due by</th><th class="r">Amount</th><th class="r">Paid</th><th>Status</th></tr></thead><tbody>' . $tr
            . '<tr class="tot"><td></td><td>Total</td><td></td><td class="r">' . fee_money($owed) . '</td><td class="r">' . fee_money(min($paid, $owed)) . '</td><td></td></tr></tbody></table>'
            . '<div class="box"><b>Balance:</b> ' . fee_money(max(0, $owed - $paid))
            . ($next ? ' &nbsp;·&nbsp; <b>Next payment:</b> ' . fee_money($next['left']) . ' by ' . $e(date('j M Y', strtotime($next['due']))) : ' &nbsp;·&nbsp; <b>Fully paid.</b> Thank you.')
            . '<br>' . ($monthly ? 'The first month is paid on enrolment. Every next month is paid by the <b>' . FEE_DUE_DAY . 'th</b> of the month. ' : '')
            . 'Pay by M-Pesa (NCBA Paybill, account: your Student No. ' . $e($reg['registration_no']) . ') and submit the code under Finance in the student portal. '
            . 'Your portal is locked when a payment is overdue.</div>');
    }

    function fee_statement_html(PDO $db, array $reg, float $owed, float $paid): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $tr = '<tr><td colspan="5"><i>Total fees for the course</i></td><td class="r"><b>' . fee_money($owed) . '</b></td></tr>';
        foreach (fee_statement($db, $reg, $owed) as $p) {
            $tr .= '<tr><td>' . $e(date('j M Y, H:i', strtotime($p['paid_at']))) . '</td><td>' . $e($p['mpesa_code'] ?: '—') . '</td><td>' . $e($p['receipt_no'] ?: '—') . '</td>'
                 . '<td class="s-' . $e($p['status']) . '">' . $e(ucfirst($p['status'])) . '</td><td class="r">' . fee_money((float) $p['amount']) . '</td>'
                 . '<td class="r">' . ($p['balance'] === null ? '<span style="color:#999">not counted</span>' : fee_money($p['balance'])) . '</td></tr>';
        }
        return fee_page('Fee Statement', $reg,
            '<table class="t"><thead><tr><th>Date paid</th><th>M-Pesa code</th><th>Receipt No.</th><th>Status</th><th class="r">Amount</th><th class="r">Balance</th></tr></thead><tbody>' . $tr
            . '<tr class="tot"><td colspan="4">Total paid (approved)</td><td class="r">' . fee_money($paid) . '</td><td class="r">' . fee_money(max(0, $owed - $paid)) . '</td></tr></tbody></table>'
            . '<div class="box"><b>Balance left: ' . fee_money(max(0, $owed - $paid)) . '</b><br>'
            . '"Date paid" is when the payment was made and its M-Pesa code sent, not when the office approved it. '
            . 'Only approved payments reduce the balance; pending ones are added once the office approves them.</div>');
    }

    function fee_pdf(string $html): string
    {
        $o = new Options();
        $o->setIsRemoteEnabled(false);
        $o->setIsPhpEnabled(false);
        $o->setDefaultFont('DejaVu Sans');
        $d = new Dompdf($o);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper('A4', 'portrait');
        $d->render();
        return $d->output();
    }
}
