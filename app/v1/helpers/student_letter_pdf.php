<?php
/**
 * The letter of recommendation page: ONE A4 portrait page, used for the screen view and the PDF (dompdf).
 * The site's current brand logo in the centre, the title, Student No and the date it was made on the right, "To Whom It May Concern", the fixed wording,
 * "Best Regards, Dazu Training Institute", the director's signature, the stamp row (the official stamp, with room for more badges
 * such as ISO), and the footer (Email, Website, LinkedIn). No marks, no grades, no QR.
 * Input comes from sl_print_data() only, so a final letter prints exactly what was locked when it was made.
 */
require_once __DIR__ . '/transcript_pdf.php';   // tr_img_uri(), dompdf, cap_letter_flat_image(), CERT_GREEN / CERT_DARK

if (!function_exists('sl_html')) {
    require_once __DIR__ . '/id_card_pdf.php';   // idc_font_css(): Poppins for the PDF

    /** The green and dark waves along the bottom of the page (drawn once and kept in the cache folder). 210 × 22 mm. */
    function sl_wave_uri(): ?string
    {
        if (!function_exists('imagecreatetruecolor')) return null;
        $file = storage_path('cache/letter_wave_v4.png');
        if (!is_file($file)) {
            @mkdir(dirname($file), 0775, true);
            $k = 10; $w = 210 * $k; $h = 22 * $k;   // 10 px per mm
            $im = imagecreatetruecolor($w, $h);
            imagealphablending($im, true);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
            $green = imagecolorallocate($im, 0x04, 0x8C, 0x44);
            $dark = imagecolorallocate($im, 0x37, 0x34, 0x35);
            for ($x = 0; $x < $w; $x++) {
                $t = $x / $w;
                $g = (8.4 - 6.4 * $t + 1.4 * sin($t * M_PI * 1.6)) * $k;           // top of the green wave (low on the left, high on the right)
                $d = (15.5 - 3.5 * $t + 4.0 * sin($t * M_PI)) * $k;  // top of the dark wave under it
                imageline($im, $x, (int) max(0, $g), $x, $h, $green);
                imageline($im, $x, (int) max(0, $d), $x, $h, $dark);
            }
            imagepng($im, $file, 6);
        }
        return is_file($file) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($file)) : null;
    }

    /** The logo as a very light grey watermark for the middle of the page (cached per logo file). */
    function sl_watermark_uri(?string $logo): ?string
    {
        if (!$logo || !is_file($logo) || !function_exists('imagecreatefromstring')) return null;
        $file = storage_path('cache/letter_wm_' . md5($logo . filemtime($logo)) . '.png');
        if (!is_file($file)) {
            $src = @imagecreatefromstring((string) file_get_contents($logo));
            if (!$src) return null;
            $w = imagesx($src); $h = imagesy($src);
            $out = imagecreatetruecolor($w, $h);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                    $lum = 0.3 * $c['red'] + 0.59 * $c['green'] + 0.11 * $c['blue'];
                    $ink = (1 - $c['alpha'] / 127) * (1 - $lum / 255);       // how much "logo" is on this pixel
                    $a = (int) round(127 - min(1, $ink) * 127 * 0.055);      // 5.5 % at most: a faint grey
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 90, 90, 90, max(0, min(127, $a))));
                }
            }
            @mkdir(dirname($file), 0775, true);
            imagepng($out, $file, 6);
        }
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($file));
    }

    /** Footer icons (envelope, globe, LinkedIn): the letter green on a light green circle, drawn once as PNG. */
    function sl_icon_uri(string $kind): string
    {
        $file = storage_path('cache/letter_icon_' . $kind . '_v1.png');
        if (!is_file($file) && function_exists('imagecreatetruecolor')) {
            $n = 192; $im = imagecreatetruecolor($n, $n);
            imagealphablending($im, true); imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
            imagefilledellipse($im, 96, 96, 190, 190, imagecolorallocate($im, 0xE3, 0xF3, 0xEA));
            $g = imagecolorallocate($im, 0x04, 0x8C, 0x44);
            imagesetthickness($im, 10);
            if ($kind === 'email') {
                imagerectangle($im, 50, 64, 142, 128, $g);
                imageline($im, 54, 68, 96, 102, $g); imageline($im, 96, 102, 138, 68, $g);
            } elseif ($kind === 'website') {
                imagesetthickness($im, 8);
                imagearc($im, 96, 96, 92, 92, 0, 360, $g); imagearc($im, 96, 96, 91, 91, 0, 360, $g);
                imagearc($im, 96, 96, 40, 92, 0, 360, $g); imageline($im, 50, 96, 142, 96, $g);
                imageline($im, 58, 74, 134, 74, $g); imageline($im, 58, 118, 134, 118, $g);
            } else {
                imagerectangle($im, 52, 52, 140, 140, $g);
                imagefilledrectangle($im, 70, 88, 80, 122, $g); imagefilledellipse($im, 75, 72, 13, 13, $g);
                imagefilledrectangle($im, 92, 88, 102, 122, $g); imagearc($im, 112, 104, 32, 34, 180, 360, $g);
                imagefilledrectangle($im, 123, 102, 130, 122, $g);
            }
            @mkdir(dirname($file), 0775, true);
            imagepng($im, $file, 6);
        }
        return is_file($file) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($file)) : '';
    }

    /** Escape, then turn **x** into bold. */
    function sl_para_html(string $p): string
    {
        $h = htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        return preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $h);
    }

    /** The page, laid out like the approved design (A4, 1 page). */
    function sl_html(array $d, bool $forPdf): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = SL_GREEN; $D = '#2b2a2c';
        $logo = tr_img_uri($d['logo']);
        $wm = sl_watermark_uri($d['logo']);
        $sig = $d['signature'] ? cap_letter_flat_image($d['signature']) : null;
        $stamp = tr_img_uri($d['stamp']);
        $wave = sl_wave_uri();
        $date = $d['date'] ? date('d/m/Y', strtotime($d['date'])) : 'Draft';

        $body = '';
        foreach ($d['paragraphs'] as $p) $body .= '<p class="sl-p">' . sl_para_html($p) . '</p>';

        // Right of the signature: other badges (ISO and so on, added later) then the official stamp.
        $cells = '';
        foreach (array_map('tr_img_uri', $d['badges']) as $img) {
            if ($img) $cells .= '<td style="vertical-align:middle;padding-left:4mm"><img src="' . $img . '" style="width:24mm" alt=""></td>';
        }
        $cells .= '<td style="vertical-align:middle;padding-left:4mm">' . ($stamp ? '<img src="' . $stamp . '" style="width:38mm" alt="Official stamp">'
            : '<div style="width:35mm;font-size:7.5pt;color:#999;text-align:center;line-height:1.4">The director\'s signature and the official stamp are added when the letter is final.</div>') . '</td>';

        $foot = '';
        foreach ([['email', 'EMAIL', $d['email'], 14], ['website', 'WEBSITE', $d['website'], 76], ['linkedin', 'LinkedIn', $d['linkedin'], 127]] as [$ic, $label, $val, $x]) {
            $foot .= '<div style="position:absolute;left:' . $x . 'mm;top:260mm"><table><tr><td style="vertical-align:middle;padding-right:2.6mm"><img src="' . sl_icon_uri($ic) . '" style="width:8.4mm;height:8.4mm" alt=""></td>'
                   . '<td class="sl-ft" style="vertical-align:middle;line-height:7.5pt"><div style="font-weight:bold;font-size:7.4pt;color:' . $D . '">' . $e($label) . '</div><div style="font-size:7.4pt;color:#444">' . $e($val) . '</div></td></tr></table></div>';
        }

        $css = '
.sl-sheet{position:relative;width:210mm;height:297mm;background:#fff;color:' . $D . ';font-family:Poppins,"Helvetica Neue",Helvetica,Arial,sans-serif;overflow:hidden}
.sl-sheet table{border-collapse:collapse}
.sl-sheet b{font-weight:bold}
.sl-wm{position:absolute;left:47mm;top:133mm;width:116mm}
.sl-in{position:absolute;left:18mm;top:14mm;width:174mm}
.sl-title{text-align:center;font-weight:bold;font-size:20.5pt;line-height:16.5pt;margin-top:8mm;color:#333}
.sl-meta{font-size:9pt;font-weight:bold;line-height:10.5pt;white-space:nowrap;text-align:left;padding:0}
.sl-p{font-size:8.7pt;line-height:8.5pt;margin:0 0 2.6mm 0;text-align:justify}
.sl-wave{position:absolute;left:0;top:275mm;width:210mm;height:22mm}
.sl-draft{position:absolute;left:0;top:115mm;width:210mm;text-align:center;font-size:90pt;font-weight:bold;color:rgba(190,190,190,.4)}';

        $html = '<div class="sl-sheet">'
            . ($wm ? '<img class="sl-wm" src="' . $wm . '" alt="">' : '')
            . '<div class="sl-in">'
            . '<div style="text-align:center">' . ($logo ? '<img src="' . $logo . '" style="height:23mm" alt="Dazu Training Institute">' : '<b style="font-size:18pt">DAZU TRAINING INSTITUTE</b>') . '</div>'
            . '<div class="sl-title">Letter of<br>Recommendation</div>'
            . '<table style="margin:4mm 0 5mm auto"><tr><td class="sl-meta">Student No: <span style="color:' . $G . '">' . $e($d['student_no']) . '</span><br>' . $e($date) . '</td></tr></table>'
            . $body
            . '<table style="width:174mm;margin-top:3mm"><tr>'
            . '<td class="sl-close" style="vertical-align:top;font-size:8.7pt;line-height:8.5pt">Best Regards,<br><b>Dazu Training Institute</b>'
            . '<div style="height:19mm;margin-top:1mm">' . ($sig ? '<img src="' . $sig . '" style="max-height:19mm;max-width:52mm" alt="">' : '') . '</div>'
            . '<div style="border-top:0.3mm dotted #444;width:52mm;padding-top:0.8mm;font-weight:bold">Director</div></td>'
            . '<td style="vertical-align:middle;text-align:right"><table style="margin:0 0 0 auto"><tr>' . $cells . '</tr></table></td>'
            . '</tr></table>'
            . '</div>'
            . $foot
            . ($wave ? '<img class="sl-wave" src="' . $wave . '" alt="">' : '')
            . ($d['draft'] ? '<div class="sl-draft">DRAFT</div>' : '')
            . '</div>';

        // dompdf draws Poppins lines about 1.65× taller than the line-height asks, so the values above are set for the PDF;
        // the browser view gets the same spacing with these.
        $screen = '.sl-p{line-height:13.2pt}.sl-title{line-height:27pt}.sl-meta{line-height:17pt}.sl-close{line-height:14pt!important}.sl-ft{line-height:12pt!important}';
        if (!$forPdf) return '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet"><style>' . $css . $screen . '</style>' . $html;
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . idc_font_css() . '@page{size:A4 portrait;margin:0}body{margin:0}' . $css . '</style></head><body>' . $html . '</body></html>';
    }

    function sl_pdf(array $d): string
    {
        $opts = new \Dompdf\Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $fontCache = dirname(__DIR__) . '/storage/cache/dompdf_fonts';   // Poppins, as on the ID cards
        if (!is_dir($fontCache)) @mkdir($fontCache, 0775, true);
        $opts->setFontDir($fontCache);
        $opts->setFontCache($fontCache);
        $opts->setChroot([dirname(__DIR__) . '/assets', dirname(__DIR__) . '/lib/dompdf', dirname(__DIR__) . '/storage']);
        $opts->setDefaultFont('Poppins');
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->loadHtml(sl_html($d, true), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
