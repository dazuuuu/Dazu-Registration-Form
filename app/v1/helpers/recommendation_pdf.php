<?php
/**
 * Letter of recommendation PDF (A4 portrait), built with dompdf like the certificate.
 * Header: logo, institute name, title. Then the student's registration number and the date the letter was
 * generated, the body (recommendation_letters.body), the signatures, and a faint centred logo watermark.
 */
require_once __DIR__ . '/certificate_pdf.php';   // dompdf, cert_logo_file(), _cert_data_uri(), signatures

if (!function_exists('lor_render_html')) {
    function lor_render_html(array $l, ?array $director, ?string $tutorSig): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = CERT_GREEN; $D = CERT_DARK;
        $logoAbs = cert_logo_file();
        $logo = $logoAbs ? _cert_data_uri($logoAbs) : null;
        $host = preg_replace('#^https?://#', '', rtrim(cert_verify_url(), '/'));
        $phone = function_exists('site_phone_display') ? site_phone_display() : '';

        $paras = array_filter(array_map('trim', preg_split("/\n\s*\n/", (string) $l['body'])), 'strlen');
        $body = implode('', array_map(fn($p) => '<p class="p">' . nl2br($e($p)) . '</p>', $paras));

        $sig = function (?string $abs, string $name, string $role) use ($e, $D) {
            return '<td style="width:50%;vertical-align:bottom;padding-right:10mm;">'
                . '<div style="height:16mm;">' . ($abs ? '<img src="' . _cert_data_uri($abs) . '" style="max-height:15mm;max-width:55mm;" />' : '') . '</div>'
                . '<div style="border-top:0.35mm solid ' . $D . ';width:60mm;padding-top:1.5mm;font-weight:bold;font-size:11.5pt;">' . $e($name) . '</div>'
                . '<div style="font-size:9pt;letter-spacing:1pt;color:#555;">' . $e(mb_strtoupper($role)) . '</div></td>';
        };
        $dirSig = $director ? sig_abs($director['signature_path'] ?? null) : null;
        $tutorName = trim((string) ($l['tutor_name'] ?? ''));

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; }
body { width: 210mm; height: 297mm; font-family: "Times New Roman", Times, serif; color: ' . $D . '; }
.p { font-size: 12pt; line-height: 1.55; margin: 0 0 4.2mm 0; text-align: justify; }
</style></head><body>'
            // Watermark: the logo, large and very faint, behind everything
            . ($logo ? '<img src="' . $logo . '" style="position:absolute;left:35mm;top:98mm;width:140mm;opacity:0.05;" />' : '')
            // Side accent + header
            . '<div style="position:absolute;left:0;top:0;width:210mm;height:4mm;background:' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:10mm;width:210mm;text-align:center;">'
            . ($logo ? '<img src="' . $logo . '" style="height:17mm;" />' : '') . '</div>'
            . '<div style="position:absolute;left:0;top:30mm;width:210mm;text-align:center;font-size:17pt;font-weight:bold;letter-spacing:3pt;">DAZU TRAINING INSTITUTE</div>'
            . '<div style="position:absolute;left:0;top:37.5mm;width:210mm;text-align:center;font-size:9.5pt;color:#555;letter-spacing:0.5pt;">'
            . $e(trim(($phone ? $phone . '  ·  ' : '') . $host)) . '</div>'
            . '<div style="position:absolute;left:22mm;top:44mm;width:166mm;border-top:0.5mm solid ' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:50mm;width:210mm;text-align:center;font-size:19pt;font-weight:bold;letter-spacing:4pt;color:' . $G . ';">LETTER OF RECOMMENDATION</div>'
            // Registration number + date
            . '<table style="position:absolute;left:22mm;top:63mm;width:166mm;font-size:11pt;border-collapse:collapse;"><tr>'
            . '<td><b>Registration No:</b> ' . $e($l['registration_no']) . '</td>'
            . '<td style="text-align:right;"><b>Date:</b> ' . $e(date('j F Y', strtotime($l['created_at'] ?? 'now'))) . '</td></tr></table>'
            // Body
            . '<div style="position:absolute;left:22mm;top:76mm;width:166mm;">' . $body
            . '<p class="p" style="margin-top:6mm;">Yours faithfully,</p>'
            . '<table style="width:166mm;border-collapse:collapse;margin-top:2mm;"><tr>'
            . $sig($dirSig, $director['full_name'] ?? CERT_DIRECTOR_NAME, $director['title'] ?? 'Director')
            . ($tutorName !== '' ? $sig($tutorSig, $tutorName, 'Course Tutor') : '<td></td>')
            . '</tr></table></div>'
            // Footer
            . '<div style="position:absolute;left:0;top:283mm;width:210mm;height:14mm;background:' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:287.5mm;width:210mm;text-align:center;font-size:9.5pt;color:#fff;">'
            . 'Verify this letter at ' . $e($host) . ' with registration number ' . $e($l['registration_no']) . '</div>'
            . '</body></html>';
    }

    function lor_render_pdf(array $l): string
    {
        $director = null;
        $tutorSig = null;
        if (class_exists('\\Models\\Connection')) {
            $db = \Models\Connection::getInstance()->getConnection();
            $director = sig_certificate_director($db);
            if (!empty($l['tutor_id'])) {
                try {
                    $st = $db->prepare('SELECT signature_path FROM tutors WHERE user_id = ? LIMIT 1');
                    $st->execute([(int) $l['tutor_id']]);
                    $tutorSig = sig_abs($st->fetchColumn() ?: null);
                } catch (Throwable $e) {
                }
            }
        }
        $opts = new \Dompdf\Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(150);
        $opts->setDefaultFont('Times-Roman');
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->loadHtml(lor_render_html($l, $director, $tutorSig), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
