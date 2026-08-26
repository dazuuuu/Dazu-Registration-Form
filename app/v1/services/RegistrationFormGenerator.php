<?php
// app/v1/services/RegistrationFormGenerator.php

require_once __DIR__ . '/../helpers/currency.php';
class RegistrationFormGenerator
{
    public static function fileName(array $reg): string
    {
        $regNo = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($reg['registration_no'] ?? $reg['id'] ?? 'registration'));
        return 'registration-form-' . trim($regNo, '-') . '.pdf';
    }

    public static function html(array $reg): string
    {
        $company = self::companyName();
        $logo = self::logoDataUri();
        $logoHtml = $logo !== ''
            ? '<img src="' . self::e($logo) . '" alt="' . self::e($company) . '">'
            : '';
        $submitted = !empty($reg['created_at']) ? date('d M Y H:i', strtotime($reg['created_at'])) : date('d M Y H:i');
        $reviewed = !empty($reg['reviewed_at']) ? date('d M Y H:i', strtotime($reg['reviewed_at'])) : '';
        $status = ucfirst((string) ($reg['status'] ?? 'pending'));

        $rows = [
            'Registration No.' => $reg['registration_no'] ?? '',
            'Student Name' => $reg['full_name'] ?? '',
            'Email' => $reg['email'] ?? '',
            'Phone' => $reg['phone'] ?? '',
            'Course' => $reg['course_title'] ?? '',
            'Study Mode' => ($reg['study_mode'] ?? '') === 'physical' ? 'Physical' : 'Online',
            'Payment Plan' => self::paymentPlanLabel((string) ($reg['payment_plan'] ?? '')),
            'Registration Fee' => !empty($reg['registration_fee_waived'])
                ? format_price(0) . ' (waived)'
                : format_price($reg['registration_fee'] ?? 0),
            'Course Fee' => format_price($reg['course_fee'] ?? 0),
            'Installments' => (string) ($reg['installment_count'] ?? ''),
            'Installment Amount' => format_price($reg['installment_amount'] ?? 0),
            'Payment Reference' => $reg['payment_reference'] ?? '',
            'Status' => $status,
            'Submitted' => $submitted,
        ];
        if ($reviewed !== '') {
            $rows['Reviewed'] = $reviewed;
        }
        if (!empty($reg['referral_registration_no'])) {
            $rows['Referral Reg. No.'] = $reg['referral_registration_no'];
        }
        if (!empty($reg['free_course_reason'])) {
            $rows['Selection Reason'] = $reg['free_course_reason'];
        }

        $body = '';
        foreach ($rows as $label => $value) {
            $body .= '<tr><th>' . self::e($label) . '</th><td>' . nl2br(self::e($value)) . '</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page{margin:32px}
            body{font-family:Arial,Helvetica,sans-serif;color:#111827;font-size:12px}
            .head{text-align:center;border-bottom:3px solid #15A24A;padding-bottom:16px;margin-bottom:18px}
            .head img{max-height:72px;max-width:190px;object-fit:contain;margin-bottom:8px}
            h1{font-size:21px;margin:0;color:#15A24A}
            .doc-title{text-align:center;font-size:16px;font-weight:bold;letter-spacing:.08em;margin:18px 0}
            table{width:100%;border-collapse:collapse}
            th,td{border:1px solid #d1d5db;padding:9px 10px;text-align:left;vertical-align:top}
            th{width:34%;background:#f3f4f6;color:#374151}
            .footer{margin-top:22px;padding-top:12px;border-top:1px solid #d1d5db;color:#6b7280;text-align:center;font-size:11px}
            .signatures{margin-top:28px;width:100%;border-collapse:collapse}
            .signatures td{border:0;border-top:1px solid #111827;width:50%;padding-top:8px;color:#374151}
        </style></head><body>
            <div class="head">
                ' . $logoHtml . '
                <h1>' . self::e($company) . '</h1>
            </div>
            <div class="doc-title">STUDENT REGISTRATION FORM</div>
            <table>' . $body . '</table>
            <table class="signatures"><tr><td>Student signature</td><td>Admin signature</td></tr></table>
            <div class="footer">Generated ' . self::e(date('d M Y H:i')) . ' by ' . self::e($company) . '</div>
        </body></html>';
    }

    public static function pdf(array $reg): ?string
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
            $dompdf->loadHtml(self::html($reg));
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (Throwable $e) {
            error_log('[RegistrationFormGenerator] PDF render failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function writeTempPdf(array $reg): ?string
    {
        $pdf = self::pdf($reg);
        if ($pdf === null) {
            return null;
        }
        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('registration-form-', true) . '.pdf';
        return file_put_contents($path, $pdf) !== false ? $path : null;
    }

    private static function loadDompdf(): void
    {
        require_once __DIR__ . '/../helpers/dompdf.php';
        dazu_load_dompdf();
    }

    private static function companyName(): string
    {
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                return 'Dazu Hub Institute';
            }
            $settings = new SettingModel($pdo);
            return trim((string) $settings->get('site_name', '')) ?: 'Dazu Hub Institute';
        } catch (Throwable $e) {
            return 'Dazu Hub Institute';
        }
    }

    private static function logoDataUri(): string
    {
        try {
            if (!class_exists('SettingModel')) {
                require_once __DIR__ . '/../models/SettingModel.php';
            }
            global $pdo;
            if (!isset($pdo)) {
                return '';
            }
            $settings = new SettingModel($pdo);
            $rel = trim((string) $settings->get('logo_path', ''));
            if ($rel === '' || preg_match('#^https?://#i', $rel)) {
                return '';
            }
            if (!defined('PROJECT_ROOT')) {
                require_once __DIR__ . '/../config/paths.php';
            }
            $path = public_path(ltrim($rel, '/'));
            if (!is_file($path)) {
                return '';
            }
            $bytes = @file_get_contents($path);
            if ($bytes === false || $bytes === '') {
                return '';
            }
            $mime = 'image/png';
            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($path);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }
            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function paymentPlanLabel(string $plan): string
    {
        return [
            'monthly' => 'Monthly',
            '3months' => '3-Month',
            'full' => 'Full payment',
        ][$plan] ?? $plan;
    }

    private static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
