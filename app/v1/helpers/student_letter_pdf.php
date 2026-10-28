<?php
/**
 * The letter of recommendation page: ONE A4 portrait page, used for the screen view and the PDF (dompdf).
 * Logo in the centre, the title, Student No and the date it was made on the right, "To Whom It May Concern", the fixed wording,
 * "Best Regards, Dazu Training Institute", the director's signature, the stamp row (the official stamp, with room for more badges
 * such as ISO), and the footer (Email, Website, LinkedIn). No marks, no grades, no QR.
 * Input comes from sl_print_data() only, so a final letter prints exactly what was locked when it was made.
 */
require_once __DIR__ . '/transcript_pdf.php';   // tr_img_uri(), dompdf, cap_letter_flat_image(), CERT_GREEN / CERT_DARK

if (!function_exists('sl_html')) {
    /** The green and dark wave at the bottom of the page (drawn once and kept in the cache folder). */
    function sl_wave_uri(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) return null;
        $file = storage_path('cache/letter_wave_v1.png');
        if (!is_file($file)) {
            @mkdir(dirname($file), 0775, true);
            $w = 2100; $h = 200;
            $im = imagecreatetruecolor($w, $h);
            imagealphablending($im, true);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
            $green = imagecolorallocate($im, 0x44, 0xA0, 0x24);
            $dark = imagecolorallocate($im, 0x37, 0x34, 0x35);
            for ($x = 0; $x < $w; $x++) {
                $t = $x / $w;
                $g = (int) round(95 - 70 * $t + 22 * sin($t * M_PI * 1.6));    // top of the green band
                $d = (int) round(150 - 40 * $t + 18 * sin($t * M_PI * 1.3 + 0.8)); // top of the dark band
                imageline($im, $x, max(0, $g), $x, $h, $green);
                imageline($im, $x, max(0, $d), $x, $h, $dark);
            }
            imagepng($im, $file, 6);
        }
        return is_file($file) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($file)) : null;
    }

    /** Escape, then turn **x** into bold. */
    function sl_para_html(string $p): string
    {
        $h = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $h);
    }

    function sl_html(array $d, bool $forPdf): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = CERT_GREEN; $D = CERT_DARK;
        $logo = tr_img_uri($d['logo']);
        $sig = $d['signature'] ? cap_letter_flat_image($d['signature']) : null;
        $stamp = tr_img_uri($d['stamp']);
        $wave = sl_wave_uri();
        $date = $d['date'] ? date('d/m/Y', strtotime($d['date'])) : 'Draft';

        $body = '';
        foreach ($d['paragraphs'] as $i => $p) $body .= '<p class="sl-p"' . ($i === 0 ? ' style="text-align:left"' : '') . '>' . sl_para_html($p) . '</p>';

        // The stamp row: the official stamp first, then any other badges (ISO and so on) the office adds later.
        $cells = '';
        foreach (array_merge([$stamp], array_map('tr_img_uri', $d['badges'])) as $i => $img) {
            $cells .= '<td style="text-align:center;padding:0 4mm">' . ($img ? '<img src="' . $img . '" style="width:' . ($i === 0 ? 36 : 28) . 'mm" alt="">'
                : '<div style="font-size:8pt;color:#999;line-height:1.5">The director\'s signature and the official stamp<br>are added when the letter is final.</div>') . '</td>';
        }
        $icon = fn(string $t) => '<div style="width:9mm;height:9mm;border-radius:50%;background:#e8f4e2;color:' . $G . ';font-weight:bold;font-size:' . (strlen($t) > 2 ? 6.5 : 9) . 'pt;text-align:center;line-height:9mm">' . $t . '</div>';
        $foot = '';
        foreach ([['@', 'EMAIL', $d['email']], ['www', 'WEBSITE', $d['website']], ['in', 'LinkedIn', $d['linkedin']]] as [$ic, $label, $val]) {
            $foot .= '<td style="vertical-align:middle;width:33%"><table><tr><td style="width:11mm;vertical-align:middle">' . $icon($ic) . '</td>'
                   . '<td style="vertical-align:middle;font-size:8pt;line-height:1.35"><b style="color:' . $D . '">' . $e($label) . '</b><br><span style="color:#555">' . $e($val) . '</span></td></tr></table></td>';
        }

        $css = '
.sl-sheet{position:relative;width:210mm;height:297mm;background:#fff;color:' . $D . ';font-family:Poppins,"Helvetica Neue",Helvetica,Arial,sans-serif;overflow:hidden}
.sl-sheet table{border-collapse:collapse}
.sl-in{position:absolute;left:17mm;top:14mm;width:176mm}
.sl-p{font-size:10.5pt;line-height:1.6;margin:0 0 3.2mm 0;text-align:justify}
.sl-title{text-align:center;font-weight:bold;font-size:22pt;line-height:1.15;margin-top:8mm}
.sl-meta{text-align:right;font-size:10pt;font-weight:bold;line-height:1.9;margin:7mm 0 5mm}
.sl-stamps{width:176mm;margin-top:6mm}
.sl-foot{position:absolute;left:12mm;top:254mm;width:186mm}
.sl-wave{position:absolute;left:0;top:273mm;width:210mm;height:24mm}
.sl-draft{position:absolute;left:0;top:115mm;width:210mm;text-align:center;font-size:90pt;font-weight:bold;color:rgba(190,190,190,.4)}';

        $html = '<div class="sl-sheet"><div class="sl-in">'
            . '<div style="text-align:center">' . ($logo ? '<img src="' . $logo . '" style="height:24mm" alt="Dazu Training Institute">' : '<b style="font-size:18pt">DAZU TRAINING INSTITUTE</b>') . '</div>'
            . '<div class="sl-title">Letter of<br>Recommendation</div>'
            . '<div class="sl-meta">Student No: <span style="color:' . $G . '">' . $e($d['student_no']) . '</span><br>' . $e($date) . '</div>'
            . $body
            . '<p class="sl-p" style="margin:5mm 0 0;text-align:left">Best Regards,<br><b>Dazu Training Institute</b></p>'
            . '<div style="height:16mm;margin-top:1mm">' . ($sig ? '<img src="' . $sig . '" style="max-height:16mm;max-width:60mm" alt="">' : '') . '</div>'
            . '<div style="border-top:0.3mm dotted #555;width:80mm;padding-top:1mm;font-weight:bold;font-size:10pt">Director</div>'
            . '<table class="sl-stamps"><tr><td style="text-align:center"><table style="margin:0 auto"><tr>' . $cells . '</tr></table></td></tr></table>'
            . '</div>'
            . '<table class="sl-foot"><tr>' . $foot . '</tr></table>'
            . ($wave ? '<img class="sl-wave" src="' . $wave . '" alt="">' : '')
            . ($d['draft'] ? '<div class="sl-draft">DRAFT</div>' : '')
            . '</div>';

        if (!$forPdf) return '<style>' . $css . '</style>' . $html;
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 portrait;margin:0}body{margin:0}' . $css . '</style></head><body>' . $html . '</body></html>';
    }

    function sl_pdf(array $d): string
    {
        $opts = new \Dompdf\Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(150);
        $opts->setDefaultFont('Helvetica');
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->loadHtml(sl_html($d, true), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
