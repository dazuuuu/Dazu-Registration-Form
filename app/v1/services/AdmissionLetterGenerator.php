<?php
// app/v1/services/AdmissionLetterGenerator.php
// Admission letter PDF — same visual language as the course fee-structure document.

require_once __DIR__ . '/../helpers/currency.php';
require_once __DIR__ . '/../helpers/site_logo.php';
require_once __DIR__ . '/../helpers/course_duration.php';
require_once __DIR__ . '/../helpers/payment_credentials.php';

class AdmissionLetterGenerator
{
    public static function fileName(array $reg): string
    {
        $regNo = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($reg['registration_no'] ?? $reg['id'] ?? 'admission'));
        return 'admission-letter-' . trim($regNo, '-') . '.pdf';
    }

    public static function html(array $reg, array $course = []): string
    {
        $title = (string) ($course['title'] ?? $reg['course_title'] ?? 'Course');
        $abbr = strtoupper(trim((string) ($course['abbreviation'] ?? $reg['abbreviation'] ?? '')));
        $logo = site_logo_data_uri() ?: '';
        $student = (string) ($reg['full_name'] ?? '');
        $regNo = (string) ($reg['registration_no'] ?? '');
        $email = (string) ($reg['email'] ?? '');
        $phone = (string) ($reg['phone'] ?? '');
        $studyMode = ($reg['study_mode'] ?? '') === 'physical' ? 'Physical classes' : 'Online classes';
        $issued = date('d M Y');
        $duration = $course !== [] ? course_study_summary($course) : ((int) ($reg['duration_months'] ?? $course['duration_months'] ?? 0) . ' months');
        $isFree = !empty($course['is_free']) || !empty($reg['is_free']);
        $payment = function_exists('payment_credentials')
            ? payment_credentials()
            : ['business_short_code' => '880100', 'account_number' => '681158', 'phone' => '0722 922 454'];

        $rows = [
            ['Student name', $student],
            ['Registration no.', $regNo],
            ['Email', $email],
            ['Phone', $phone],
            ['Programme', $title . ($abbr !== '' ? ' (' . $abbr . ')' : '')],
            ['Study mode', $studyMode],
            ['Programme duration', $duration ?: '—'],
        ];
        if (!$isFree) {
            $rows[] = ['Registration fee', format_price($reg['registration_fee'] ?? 0)];
            $rows[] = ['Course fee', format_price($reg['course_fee'] ?? 0)];
        }

        $body = '';
        $i = 0;
        foreach ($rows as [$label, $value]) {
            $bg = $i % 2 === 0 ? '#fafafa' : '#ffffff';
            $body .= '<tr><td style="padding:13px 18px;background:' . $bg . ';font-size:13px;">' . self::e($label) . '</td>'
                . '<td style="padding:13px 18px;background:' . $bg . ';text-align:right;font-size:13px;font-weight:800;">'
                . self::e($value) . '</td></tr>';
            $i++;
        }

        $logoHtml = $logo !== ''
            ? '<img src="' . htmlspecialchars($logo) . '" alt="Dazu Hub" style="max-height:78px;max-width:100px;object-fit:contain;">'
            : '';

        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:720px;margin:0 auto;background:#ffffff;color:#111827;padding:46px 52px;">'
            . '<div style="display:table;width:100%;margin-bottom:42px;">'
            . '<div style="display:table-cell;width:120px;vertical-align:middle;">' . $logoHtml . '</div>'
            . '<div style="display:table-cell;vertical-align:middle;"><h1 style="margin:0;font-size:24px;letter-spacing:.02em;">DAZU HUB</h1>'
            . '<p style="margin:8px 0 0;font-size:13px;font-weight:800;text-transform:uppercase;color:#374151;">Admission Letter</p></div>'
            . '</div>'
            . '<div style="border-top:1px solid #d1d5db;padding-top:30px;">'
            . '<h2 style="margin:0 0 18px;font-size:17px;">' . self::e($title)
            . ($abbr !== '' ? ' (' . self::e($abbr) . ')' : '')
            . '</h2>'
            . '<p style="margin:0 0 34px;font-size:12px;line-height:1.65;color:#374151;max-width:600px;">'
            . 'This letter confirms that <strong>' . self::e($student) . '</strong> has been admitted to Dazu Hub Institute for the programme named above. Please keep this letter with your course brochure and fee structure.'
            . '</p>'
            . '<div style="display:table;width:100%;background:#f5f6f8;border-radius:10px;margin-bottom:28px;">'
            . '<div style="display:table-cell;width:50%;padding:18px 20px;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:8px;">Registration number</div><div style="font-size:12px;">' . self::e($regNo !== '' ? $regNo : '—') . '</div></div>'
            . '<div style="display:table-cell;width:50%;padding:18px 20px;"><div style="font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:8px;">Date of issue</div><div style="font-size:12px;">' . self::e($issued) . '</div></div>'
            . '</div>'
            . '<div style="page-break-inside:avoid;margin-top:8px;">'
            . '<h3 style="margin:0 0 10px;font-size:13px;color:#111827;text-transform:uppercase;letter-spacing:.02em;">Student details</h3>'
            . '<table style="width:100%;border-collapse:collapse;margin:0;border:0;">'
            . '<thead><tr><th style="padding:12px 18px;background:#e5e7eb;text-align:left;font-size:11px;text-transform:uppercase;">Item</th>'
            . '<th style="padding:12px 18px;background:#e5e7eb;text-align:right;font-size:11px;text-transform:uppercase;">Details</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>'
            . '<div style="display:table;width:100%;background:#111827;color:#ffffff;border-radius:7px;margin-top:0;">'
            . '<div style="display:table-cell;padding:15px 18px;font-size:13px;font-weight:800;text-transform:uppercase;">Admission status</div>'
            . '<div style="display:table-cell;padding:15px 18px;font-size:13px;font-weight:800;text-align:right;">Enrolled</div></div>'
            . '</div>'
            . (!$isFree
                ? '<div style="display:table;width:100%;background:#f5f6f8;border-radius:10px;margin-top:30px;">'
                    . '<div style="display:table-cell;padding:14px 18px;width:33%;"><strong style="display:block;font-size:11px;text-transform:uppercase;">Payment details</strong><span style="font-size:11px;">M-PESA Paybill: ' . self::e((string) ($payment['paybill'] ?? $payment['business_short_code'] ?? '880100')) . '</span></div>'
                    . '<div style="display:table-cell;padding:14px 18px;width:34%;font-size:11px;vertical-align:bottom;">Account: ' . self::e((string) ($payment['account_number'] ?? '681158')) . '</div>'
                    . '<div style="display:table-cell;padding:14px 18px;width:33%;font-size:11px;text-align:right;vertical-align:bottom;">Phone: ' . self::e((string) ($payment['phone'] ?? '0722 922 454')) . '</div>'
                    . '</div>'
                : '')
            . '<p style="margin:10px 0 0;font-size:9px;color:#6b7280;">Issued ' . self::e($issued) . ' - dazuhub.ac.ke</p>'
            . '</div></div>';
    }

    public static function pdf(array $reg, array $course = []): ?string
    {
        self::loadDompdf();
        if (!class_exists('Dompdf\\Dompdf')) {
            return null;
        }
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . self::html($reg, $course) . '</body></html>');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (Throwable $e) {
            error_log('[AdmissionLetterGenerator] PDF render failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function writeTempPdf(array $reg, array $course = []): ?string
    {
        $pdf = self::pdf($reg, $course);
        if ($pdf === null) {
            return null;
        }
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('admission-letter-', true) . '.pdf';
        return file_put_contents($path, $pdf) !== false ? $path : null;
    }

    private static function loadDompdf(): void
    {
        require_once __DIR__ . '/../helpers/dompdf.php';
        dazu_load_dompdf();
    }

    private static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
