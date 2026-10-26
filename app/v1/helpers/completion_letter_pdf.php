<?php
/**
 * Letter of recommendation for a student who completed a course with a capstone (A4 portrait, dompdf, same look as the certificate).
 * Released together with the transcript when the student is Ready (CapstoneService::cap_release_sync).
 *
 * Shows: logo, title, date, registration number, the student's name, the course, the real start and finish dates (and that the
 * course was finished early when it was), the capstone title and the RESULT WORD (Distinction, Credit or Pass), an optional personal
 * sentence from the tutor, the director's signature (the active signature from admin Settings), and a verification QR.
 * It never shows a mark. Before release the tutor can preview it with a DRAFT watermark.
 */
require_once __DIR__ . '/certificate_pdf.php';      // dompdf, cert_logo_file(), cert_qr_data_uri(), CERT_GREEN / CERT_DARK
require_once __DIR__ . '/tutor_card_pdf.php';       // tcard_logo(): a fast, flattened copy of the logo
require_once __DIR__ . '/../services/StaffIdService.php';   // dsig_active(): the director's signature

if (!function_exists('cap_letter_verify_url')) {
    function cap_letter_verify_url(string $hash): string
    {
        return course_share_absolute_url(url('verify/letter.php')) . '?k=' . rawurlencode($hash);
    }

    /** A transparent signature PNG flattened on white as a JPEG data URI: dompdf renders it in milliseconds instead of seconds. */
    function cap_letter_flat_image(string $abs): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $src = @imagecreatefromstring((string) file_get_contents($abs));
        if (!$src) return null;
        $w = min(600, imagesx($src));
        $h = (int) round(imagesy($src) * $w / max(1, imagesx($src)));
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopyresampled($flat, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($flat, null, 90);
        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }

    /** "12 months", "11.5 months". */
    function cap_letter_months($m): string
    {
        return rtrim(rtrim(number_format((float) $m, 1, '.', ''), '0'), '.') . ' months';
    }

    /** The paragraphs of the letter (plain text, already free of marks). */
    function cap_letter_paragraphs(array $d): array
    {
        $first = explode(' ', $d['name'])[0];
        $from = $d['start'] ? date('F Y', strtotime($d['start'])) : '';
        $to = $d['finish'] ? date('F Y', strtotime($d['finish'])) : '';
        $p = ['To Whom It May Concern,'];
        $p[] = 'Dazu Training Institute is pleased to recommend ' . $d['name'] . ' (Registration No. ' . $d['registration_no'] . '), who completed the '
             . $d['course'] . ' programme with us' . ($from && $to ? ($from === $to ? ' in ' . $from : ' between ' . $from . ' and ' . $to) : '') . '.';
        if (!empty($d['shortened'])) {
            $p[] = $first . ' finished the programme in ' . cap_letter_months($d['months']) . ', earlier than the planned ' . (int) $d['planned'] . ' months, '
                 . 'after showing that the required skills were already in place.';
        }
        if (!empty($d['capstone_title'])) {
            $p[] = 'As the final project of the course, ' . $first . ' completed a capstone project, "' . $d['capstone_title'] . '"'
                 . (!empty($d['result']) ? ', and achieved the result of ' . $d['result'] . '.' : '.');
        }
        if (!empty($d['note'])) $p[] = $d['note'];
        $p[] = 'Throughout the course, ' . $first . ' showed commitment and discipline, completed the practical work and assessments, and applied new skills with confidence. '
             . 'We recommend ' . $first . ' without reservation.';
        return $p;
    }

    function cap_letter_html(PDO $db, array $d, bool $draft): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = CERT_GREEN; $D = CERT_DARK;
        $logo = function_exists('tcard_logo') && ($l = tcard_logo()) ? $l[0] : (($f = cert_logo_file()) ? _cert_data_uri($f) : null);
        $host = preg_replace('#^https?://#', '', rtrim(cert_verify_url(), '/'));
        $phone = function_exists('site_phone_display') ? site_phone_display() : '';
        $dir = dsig_active($db);
        $dirSig = $dir && !empty($dir['file']) ? cap_letter_flat_image($dir['file']) : null;
        $date = $d['dated'] ? date('j F Y', strtotime($d['dated'])) : date('j F Y');
        $paras = cap_letter_paragraphs($d);
        $body = '';
        foreach ($paras as $i => $para) $body .= '<p class="p"' . ($i === 0 ? ' style="margin-bottom:3mm"' : '') . '>' . nl2br($e($para)) . '</p>';
        $qr = !empty($d['hash']) ? cert_qr_data_uri(cap_letter_verify_url($d['hash'])) : null;

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { size: A4 portrait; margin: 0; }
* { margin: 0; padding: 0; }
body { width: 210mm; height: 297mm; font-family: "Times New Roman", Times, serif; color: ' . $D . '; }
.p { font-size: 12pt; line-height: 1.55; margin: 0 0 4.2mm 0; text-align: justify; }
</style></head><body>'
            . '<div style="position:absolute;left:0;top:0;width:210mm;height:4mm;background:' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:10mm;width:210mm;text-align:center;">' . ($logo ? '<img src="' . $logo . '" style="height:17mm;" />' : '') . '</div>'
            . '<div style="position:absolute;left:0;top:30mm;width:210mm;text-align:center;font-size:17pt;font-weight:bold;letter-spacing:3pt;">DAZU TRAINING INSTITUTE</div>'
            . '<div style="position:absolute;left:0;top:37.5mm;width:210mm;text-align:center;font-size:9.5pt;color:#555;letter-spacing:0.5pt;">' . $e(trim(($phone ? $phone . '  ·  ' : '') . $host)) . '</div>'
            . '<div style="position:absolute;left:22mm;top:44mm;width:166mm;border-top:0.5mm solid ' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:50mm;width:210mm;text-align:center;font-size:19pt;font-weight:bold;letter-spacing:4pt;color:' . $G . ';">LETTER OF RECOMMENDATION</div>'
            . '<table style="position:absolute;left:22mm;top:63mm;width:166mm;font-size:11pt;border-collapse:collapse;"><tr>'
            . '<td><b>Registration No:</b> ' . $e($d['registration_no']) . '</td><td style="text-align:right;"><b>Date:</b> ' . $e($date) . '</td></tr></table>'
            . '<div style="position:absolute;left:22mm;top:76mm;width:166mm;">' . $body
            . '<p class="p" style="margin-top:6mm;">Yours faithfully,</p>'
            . '<div style="height:18mm;margin-top:2mm;">' . ($dirSig ? '<img src="' . $dirSig . '" style="max-height:17mm;max-width:60mm;" />' : '') . '</div>'
            . '<div style="border-top:0.35mm solid ' . $D . ';width:62mm;padding-top:1.5mm;font-weight:bold;font-size:11.5pt;">' . $e($dir['name'] ?? CERT_DIRECTOR_NAME) . '</div>'
            . '<div style="font-size:9pt;letter-spacing:1pt;color:#555;">' . $e(mb_strtoupper($dir['title'] ?? 'Director')) . '</div></div>'
            . ($qr ? '<div style="position:absolute;left:22mm;top:250mm;width:26mm;"><img src="' . $qr . '" style="width:24mm;height:24mm;" /></div>'
                    . '<div style="position:absolute;left:50mm;top:258mm;width:130mm;font-size:8.5pt;color:#555;">Scan the QR code, or go to ' . $e($host) . ', to confirm this letter.</div>' : '')
            . ($draft ? '<div style="position:absolute;left:0;top:110mm;width:210mm;text-align:center;font-size:90pt;font-weight:bold;color:#d9d9d9;transform:rotate(-30deg);">DRAFT</div>' : '')
            . '<div style="position:absolute;left:0;top:283mm;width:210mm;height:14mm;background:' . $G . ';"></div>'
            . '<div style="position:absolute;left:0;top:287.5mm;width:210mm;text-align:center;font-size:9.5pt;color:#fff;">Dazu Training Institute  ·  ' . $e($host) . '</div>'
            . '</body></html>';
    }

    /** The PDF bytes. $draft = true adds a DRAFT watermark (the tutor's preview before release). */
    function cap_letter_pdf(PDO $db, array $d, bool $draft = false): string
    {
        $opts = new \Dompdf\Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(150);
        $opts->setDefaultFont('Times-Roman');
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->loadHtml(cap_letter_html($db, $d, $draft), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
