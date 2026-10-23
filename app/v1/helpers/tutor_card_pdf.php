<?php
/**
 * Tutor staff ID card PDF — portrait design (Dazu_Tutor_ID_System_Build_Prompt.pdf, images 1 and 2).
 *
 * This is the one generator for tutor cards: the tutor's preview, the admin's preview, the emailed copy and the
 * print file all come from tcard_pdf_bytes(). idc_pdf_bytes() hands every '_kind' => 'staff' card to it.
 *
 * Card: 50 × 85 mm portrait, Poppins, Dazu green #44A024 / charcoal #373435. Per card (staff_card()):
 *  - photo (+ optional admin crop), printed name and role, ID number, work email, verification URL (QR, vector SVG)
 *  - '_director' => ['file', 'name', 'title'] and '_settings' (phone, email, websites, branches) for the back
 *  - '_watermark' => 'PREVIEW' for on-screen previews (never on print files)
 * Layout coordinates are millimetres from the top-left corner of the trimmed card.
 */

require_once __DIR__ . '/id_card_pdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

const TCARD_W = 50.0;
const TCARD_H = 85.0;
const TCARD_BLEED = 3.0;
const TCARD_TEXT_W = 44.0;   // widest a line of text may be (3 mm side margins)

if (!function_exists('tcard_defaults')) {
    /** Card wording when nothing is saved in ID card settings. */
    function tcard_defaults(): array
    {
        return [
            'property_line' => 'This card is the property of', 'org_name' => 'Dazu Training Institute',
            'contact_line' => 'If found, please contact', 'phone' => '+254 722 922 454', 'email' => 'admin@dazuhub.com',
            'website_1' => 'www.dazu.ac.ke', 'website_2' => 'www.dazuhub.com', 'locations_line' => 'Our locations',
            'branch_1_name' => 'Thika Branch', 'branch_1_address' => 'Witeithie House, 5th floor, Room 529',
            'branch_2_name' => 'Nairobi Branch', 'branch_2_address' => 'Cianda House, 4th floor, Room 406B, Koinange Street',
            'verify_base' => 'https://dazu.ac.ke', 'show_photo_on_verify' => '1',
        ];
    }
}

if (!function_exists('tcard_background_png')) {
    /** White card with the diagonal corner bars (shared by front and back), drawn once over card + bleed. */
    function tcard_background_png(): string
    {
        $file = idc_cache_dir() . '/tutorcard_bg_v1.png';
        if (is_file($file)) return $file;
        @ini_set('memory_limit', '512M');
        $k = 2; $s = 24 * $k;
        $W = (int) round((TCARD_W + 2 * TCARD_BLEED) * $s); $H = (int) round((TCARD_H + 2 * TCARD_BLEED) * $s);
        $im = imagecreatetruecolor($W, $H);
        $white = imagecolorallocate($im, 255, 255, 255);
        $green = imagecolorallocate($im, 68, 160, 36);
        $dark = imagecolorallocate($im, 55, 52, 53);
        imagefill($im, 0, 0, $white);
        $poly = function (array $pts, $c) use ($im, $s) {
            $flat = [];
            foreach ($pts as [$x, $y]) { $flat[] = (int) round(($x + TCARD_BLEED) * $s); $flat[] = (int) round(($y + TCARD_BLEED) * $s); }
            imagefilledpolygon($im, $flat, $c);
        };
        $L = -TCARD_BLEED; $R = TCARD_W + TCARD_BLEED;
        // Top left: green, charcoal, green. Top right: green, charcoal.
        $poly([[$L, 1.6], [22.8, 1.6], [21.9, 3.6], [$L, 3.6]], $green);
        $poly([[$L, 4.4], [31.1, 4.4], [29.5, 7.4], [$L, 7.4]], $dark);
        $poly([[$L, 8.1], [8.4, 8.1], [7.5, 9.9], [$L, 9.9]], $green);
        $poly([[38.6, 0.2], [$R, 0.2], [$R, 2.3], [37.0, 2.3]], $green);
        $poly([[40.4, 4.4], [$R, 4.4], [$R, 7.4], [42.1, 7.4]], $dark);
        // Bottom: charcoal and green on both sides.
        $poly([[$L, 78.4], [7.4, 78.4], [8.4, 80.6], [$L, 80.6]], $dark);
        $poly([[$L, 81.6], [8.9, 81.6], [10.0, 83.3], [$L, 83.3]], $green);
        $poly([[16.4, 78.4], [$R, 78.4], [$R, 80.6], [14.6, 80.6]], $dark);
        $poly([[22.2, 81.6], [$R, 81.6], [$R, 83.3], [21.1, 83.3]], $green);
        return _idc_save($im, $file, $k);
    }
}

if (!function_exists('tcard_qr_svg_uri')) {
    /** QR code (error correction M) as a vector SVG data URI, modules only; the white box around it is the quiet zone. */
    function tcard_qr_svg_uri(string $text): string
    {
        $gen = new barcode_generator();
        $code = (fn($d) => $this->dispatch_encode('qr-m', $d, []))->call($gen, $text);
        $rows = $code['b'];
        $n = count($rows);
        $rects = '';
        foreach ($rows as $y => $row) {
            $run = -1;
            $row[] = 0;   // sentinel closes the last run
            foreach ($row as $x => $v) {
                if ($v && $run < 0) $run = $x;
                elseif (!$v && $run >= 0) { $rects .= '<rect x="' . $run . '" y="' . $y . '" width="' . ($x - $run) . '" height="1"/>'; $run = -1; }
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" width="' . $n . '" height="' . $n . '" shape-rendering="crispEdges"><g fill="#1d1d1b">' . $rects . '</g></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('tcard_fit')) {
    /**
     * Fitting rule for the name and role: one line at the largest size from $max down to $min; else two lines
     * (each at the largest size that fits, from $max2 down to $min2). 'warn' when even that does not fit.
     * @return array{lines: string[], size: float, warn: bool}
     */
    function tcard_fit(string $text, string $weight, float $max, float $min, float $max2, float $min2, float $letterEm = 0.0, float $width = TCARD_TEXT_W): array
    {
        $w = fn(string $t, float $pt) => idc_text_mm($t, $weight, $pt) + $letterEm * mb_strlen($t) * $pt * 25.4 / 72;
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') return ['lines' => [''], 'size' => $max, 'warn' => false];
        for ($pt = $max; $pt >= $min - 0.001; $pt -= 0.25) {
            if ($w($text, $pt) <= $width) return ['lines' => [$text], 'size' => $pt, 'warn' => false];
        }
        // Two lines: split at the word boundary that balances the lines best.
        $words = explode(' ', $text);
        if (count($words) < 2) return ['lines' => [$text], 'size' => $min, 'warn' => true];
        $best = null;
        for ($i = 1; $i < count($words); $i++) {
            $a = implode(' ', array_slice($words, 0, $i)); $b = implode(' ', array_slice($words, $i));
            $m = max($w($a, 1), $w($b, 1));
            if ($best === null || $m < $best[0]) $best = [$m, [$a, $b]];
        }
        for ($pt = $max2; $pt >= $min2 - 0.001; $pt -= 0.25) {
            if ($best[0] * $pt <= $width) return ['lines' => $best[1], 'size' => $pt, 'warn' => false];
        }
        return ['lines' => $best[1], 'size' => $min2, 'warn' => true];
    }

    /** Fitting used for the printed name (bold capitals). */
    function tcard_fit_name(string $name): array
    {
        return tcard_fit(mb_strtoupper($name), 'Bold', 10, 7.5, 8, 6.25);
    }

    /** Fitting used for the role (bold green capitals with wide letter spacing). */
    function tcard_fit_role(string $role): array
    {
        return tcard_fit(mb_strtoupper($role), 'Bold', 5.8, 4.6, 5.2, 4.0, 0.16);
    }
}

if (!function_exists('tcard_logo')) {
    /** [data URI, width / height] of the Dazu logo, or null. */
    function tcard_logo(): ?array
    {
        static $logo = false;
        if ($logo === false) {
            require_once __DIR__ . '/certificate_pdf.php';
            $abs = cert_logo_file();
            $sz = $abs ? @getimagesize($abs) : null;
            $logo = $abs && $sz ? [_idc_file_uri($abs), $sz[0] / max(1, $sz[1])] : null;
        }
        return $logo;
    }
}

if (!function_exists('tcard_front_html')) {
    function tcard_front_html(array $card, float $ox, float $oy): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = IDC_GREEN; $D = IDC_DARK; $cx = TCARD_W / 2;
        $at = fn(float $x, float $y) => 'position:absolute;left:' . round($ox + $x, 2) . 'mm;top:' . round($oy + $y, 2) . 'mm;';
        $T = fn(float $capTop, float $pt, string $html, string $style) => _idc_text($ox + $cx, $oy + $capTop, $pt, $html, $style, 'center', TCARD_TEXT_W + 4);
        $mm = fn(float $pt) => $pt * 25.4 / 72;

        $out = '';
        if ($logo = tcard_logo()) {
            $lh = 6.6; $lw = $lh * $logo[1];
            $out .= '<img src="' . $logo[0] . '" style="' . $at($cx - $lw / 2, 7.7) . 'width:' . round($lw, 2) . 'mm;height:' . $lh . 'mm;" />';
        }

        // Photo in the green rounded frame (inside 18.4 × 23 mm = 4:5).
        $fw = 20.0; $fh = 24.6; $b = 0.8; $fx = $cx - $fw / 2; $fy = 15.2;
        $photo = idc_photo_uri($card['_photo_file'] ?? null, $fw - 2 * $b, $fh - 2 * $b, 1.5, $card['_photo_crop'] ?? null, 0.15);
        $out .= '<div style="' . $at($fx, $fy) . 'width:' . $fw . 'mm;height:' . $fh . 'mm;background:' . $G . ';border-radius:2.3mm;"></div>'
              . '<img src="' . $photo . '" style="' . $at($fx + $b, $fy + $b) . 'width:' . ($fw - 2 * $b) . 'mm;height:' . ($fh - 2 * $b) . 'mm;" />';

        // Name and role flow down from under the photo; the pill, email and QR follow them.
        $y = 41.5;
        $name = tcard_fit_name((string) ($card['snapshot_name'] ?? ''));
        foreach ($name['lines'] as $line) {
            $out .= $T($y, $name['size'], $e($line), 'font-family:Poppins;font-weight:bold;color:' . $D . ';');
            $y += $mm($name['size']) * 1.08;
        }
        $y += 0.9;
        $role = tcard_fit_role((string) ($card['snapshot_course'] ?? ''));
        foreach ($role['lines'] as $line) {
            $out .= $T($y, $role['size'], $e($line), 'font-family:Poppins;font-weight:bold;color:' . $G . ';letter-spacing:0.16em;');
            $y += $mm($role['size']) * 1.25;
        }

        // ID number pill
        $no = (string) ($card['snapshot_reg_no'] ?? '');
        $noPt = idc_fit_size($no, 'Bold', 7.4, 5, 36);
        $pw = max(26.8, min(TCARD_TEXT_W, idc_text_mm($no, 'Bold', $noPt) + 8)); $ph = 4.6; $py = max($y + 1.4, 49.0);
        $out .= '<div style="' . $at($cx - $pw / 2, $py) . 'width:' . round($pw, 2) . 'mm;height:' . $ph . 'mm;background:' . $G . ';border-radius:2.3mm;"></div>'
              . $T($py + ($ph - 0.7 * $mm($noPt)) / 2, $noPt, $e($no), 'font-family:Poppins;font-weight:bold;color:#fff;');
        $y = $py + $ph;

        // Work email (left out when there is none, the spacing stays)
        $email = trim((string) ($card['_work_email'] ?? ''));
        if ($email !== '') {
            $ePt = idc_fit_size($email, 'Regular', 6.4, 4.4, 42);
            $out .= $T($y + 2.1, $ePt, $e($email), 'font-family:Poppins;color:' . $D . ';');
        }
        $y += 4.2;

        // QR code in a rounded box with a thin grey border
        $q = 12.6; $qy = $y + 0.8;
        $out .= '<div style="' . $at($cx - $q / 2, $qy) . 'width:' . ($q - 0.5) . 'mm;height:' . ($q - 0.5) . 'mm;border:0.25mm solid #9b9b9b;border-radius:1.5mm;background:#fff;"></div>';
        if (!empty($card['_verify_url'])) {
            $qi = 10.2;
            $out .= '<img src="' . tcard_qr_svg_uri((string) $card['_verify_url']) . '" style="' . $at($cx - $qi / 2, $qy + ($q - $qi) / 2 - 0.02) . 'width:' . $qi . 'mm;height:' . $qi . 'mm;" />';
        }
        return $out;
    }
}

if (!function_exists('tcard_back_html')) {
    function tcard_back_html(array $card, float $ox, float $oy): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $s = ($card['_settings'] ?? []) + tcard_defaults();
        $G = IDC_GREEN; $D = IDC_DARK; $cx = TCARD_W / 2;
        $at = fn(float $x, float $y) => 'position:absolute;left:' . round($ox + $x, 2) . 'mm;top:' . round($oy + $y, 2) . 'mm;';
        $T = fn(float $capTop, float $pt, string $html, string $style) => _idc_text($ox + $cx, $oy + $capTop, $pt, $html, $style, 'center', TCARD_TEXT_W + 4);
        $L = fn(float $x, float $capTop, float $pt, string $html, string $style) => _idc_text($ox + $x, $oy + $capTop, $pt, $html, $style, 'left', 44);
        $fit = fn(string $t, string $w, float $max, float $min, float $width = TCARD_TEXT_W, float $ls = 0) => idc_fit_size($t, $w, $max, $min, $width, $ls);
        $spaced = fn(float $em) => 'letter-spacing:' . $em . 'em;';

        $out = '';
        if ($logo = tcard_logo()) {
            $lh = 6.6; $lw = $lh * $logo[1];
            $out .= '<img src="' . $logo[0] . '" style="' . $at($cx - $lw / 2, 7.7) . 'width:' . round($lw, 2) . 'mm;height:' . $lh . 'mm;" />';
        }
        $prop = mb_strtoupper($s['property_line']); $org = mb_strtoupper($s['org_name']);
        $out .= $T(16.3, $fit($prop, 'Bold', 4.8, 3.6, 44, 0.16), $e($prop), 'font-family:Poppins;font-weight:bold;color:' . $G . ';' . $spaced(0.16))
              . $T(19.1, $fit($org, 'Bold', 9.6, 6.5), $e($org), 'font-family:Poppins;font-weight:bold;color:' . $D . ';')
              . '<div style="' . $at($cx - 4.3, 23.1) . 'width:8.6mm;height:0.4mm;background:' . $G . ';"></div>';

        $contact = mb_strtoupper($s['contact_line']);
        $out .= $T(25.3, $fit($contact, 'Regular', 4.8, 3.6, 44, 0.2), $e($contact), 'font-family:Poppins;color:#555;' . $spaced(0.2))
              . $T(28.0, $fit($s['phone'], 'Bold', 10, 7), $e($s['phone']), 'font-family:Poppins;font-weight:bold;color:' . $D . ';')
              . $T(32.6, $fit($s['email'], 'Regular', 6.8, 5), $e($s['email']), 'font-family:Poppins;color:' . $D . ';');
        $y = 35.5;
        foreach (['website_1', 'website_2'] as $k) {
            if (trim($s[$k]) === '') continue;
            $out .= $T($y, $fit($s[$k], 'Bold', 6.4, 4.8), $e($s[$k]), 'font-family:Poppins;font-weight:bold;color:' . $G . ';');
            $y += 2.9;
        }

        $loc = mb_strtoupper($s['locations_line']);
        $out .= $T(42.4, 4.8, $e($loc), 'font-family:Poppins;font-weight:bold;color:' . $G . ';' . $spaced(0.16));
        $by = 44.9;
        foreach ([1, 2] as $i) {
            $nm = mb_strtoupper(trim($s["branch_{$i}_name"])); $ad = trim($s["branch_{$i}_address"]);
            if ($nm === '' && $ad === '') continue;
            $out .= '<div style="' . $at(2.4, $by) . 'width:45.2mm;height:5.6mm;background:#efefef;border-radius:1mm;"></div>'
                  . '<div style="' . $at(2.4, $by) . 'width:0.7mm;height:5.6mm;background:' . $G . ';border-radius:0.35mm;"></div>'
                  . $L(4.3, $by + 1.45, $fit($nm, 'Bold', 5, 3.8, 42), $e($nm), 'font-family:Poppins;font-weight:bold;color:' . $D . ';')
                  . $L(4.3, $by + 3.5, $fit($ad, 'Regular', 4.8, 3.4, 42.4), $e($ad), 'font-family:Poppins;color:' . $D . ';');
            $by += 6.4;
        }

        // Director signature (the active version from admin Settings), name and title.
        $dir = $card['_director'] ?? [];
        if (!empty($dir['file']) && is_file($dir['file']) && ($sz = @getimagesize($dir['file']))) {
            $mh = 5.8; $mw = 18.0;
            $k = min($mw / $sz[0], $mh / $sz[1]);
            $w = $sz[0] * $k; $h = $sz[1] * $k;
            $out .= '<img src="' . _idc_file_uri($dir['file']) . '" style="' . $at($cx - $w / 2, 57.7 + ($mh - $h)) . 'width:' . round($w, 2) . 'mm;height:' . round($h, 2) . 'mm;" />';
        }
        $dn = (string) ($dir['name'] ?? 'Mwangi Ngigi'); $dt = mb_strtoupper((string) ($dir['title'] ?? 'CEO & Founder'));
        $out .= $T(64.9, $fit($dn, 'Bold', 7.4, 5.5), $e($dn), 'font-family:Poppins;font-weight:bold;color:' . $D . ';')
              . $T(67.8, $fit($dt, 'Bold', 4.8, 3.6, 44, 0.16), $e($dt), 'font-family:Poppins;font-weight:bold;color:' . $G . ';' . $spaced(0.16));
        return $out;
    }
}

if (!function_exists('tcard_side')) {
    /** One side of a card with its trim box top-left at ($ox, $oy): background (with bleed), contents, watermark. */
    function tcard_side(array $card, string $side, float $ox, float $oy): string
    {
        static $bg = null;
        $bg ??= _idc_file_uri(tcard_background_png());
        $B = TCARD_BLEED;
        $html = '<img src="' . $bg . '" style="position:absolute;left:' . round($ox - $B, 2) . 'mm;top:' . round($oy - $B, 2) . 'mm;width:' . (TCARD_W + 2 * $B) . 'mm;height:' . (TCARD_H + 2 * $B) . 'mm;" />'
              . ($side === 'front' ? tcard_front_html($card, $ox, $oy) : tcard_back_html($card, $ox, $oy));
        if (!empty($card['_watermark'])) {
            $html .= '<div style="position:absolute;left:' . round($ox - 15, 2) . 'mm;top:' . round($oy + TCARD_H / 2 - 6, 2) . 'mm;width:' . (TCARD_W + 30) . 'mm;text-align:center;font-family:Poppins;font-weight:bold;'
                   . 'font-size:26pt;color:rgba(55,52,53,0.15);letter-spacing:3pt;transform:rotate(-55deg);">' . htmlspecialchars((string) $card['_watermark'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
        return $html;
    }

    /** Crop marks around a trim box, kept outside the bleed. */
    function tcard_marks(float $ox, float $oy): string
    {
        $line = fn(float $x, float $y, float $w, float $h) => '<div style="position:absolute;left:' . round($x, 2) . 'mm;top:' . round($y, 2) . 'mm;width:' . $w . 'mm;height:' . $h . 'mm;background:#000;"></div>';
        $gap = TCARD_BLEED + 0.8; $len = 3.2; $t = 0.12; $out = '';
        foreach ([$ox, $ox + TCARD_W] as $x) $out .= $line($x - $t / 2, $oy - $gap - $len, $t, $len) . $line($x - $t / 2, $oy + TCARD_H + $gap, $t, $len);
        foreach ([$oy, $oy + TCARD_H] as $y) $out .= $line($ox - $gap - $len, $y - $t / 2, $len, $t) . $line($ox + TCARD_W + $gap, $y - $t / 2, $len, $t);
        return $out;
    }
}

if (!function_exists('tcard_render_html')) {
    /**
     * $opt['mode']: 'trim'   50 × 85 mm pages, front then back (preview, email copy)
     *               'single' one card side per page with 3 mm bleed (+ crop marks when $opt['marks']), front then back per tutor
     *               'a4'     A4 landscape sheets of 8 cards (4 × 2): a sheet of fronts, then the sheet of backs with the
     *                        columns mirrored, so long-edge duplex printing lines each back up with its front.
     * @return array{0:string,1:float,2:float} html, page width, page height (mm)
     */
    function tcard_render_html(array $cards, array $opt = []): array
    {
        $mode = $opt['mode'] ?? 'trim';
        $marks = !empty($opt['marks']);
        $pages = [];
        if ($mode === 'a4') {
            $pw = 297.0; $ph = 210.0; $gx = 10.0; $gy = 12.0;
            $x0 = ($pw - (4 * TCARD_W + 3 * $gx)) / 2; $y0 = ($ph - (2 * TCARD_H + $gy)) / 2;
            foreach (array_chunk($cards, 8) as $sheet) {
                foreach (['front', 'back'] as $side) {
                    $p = '';
                    foreach ($sheet as $i => $card) {
                        $col = $i % 4; $row = intdiv($i, 4);
                        if ($side === 'back') $col = 3 - $col;
                        $ox = $x0 + $col * (TCARD_W + $gx); $oy = $y0 + $row * (TCARD_H + $gy);
                        $p .= tcard_side($card, $side, $ox, $oy) . ($marks ? tcard_marks($ox, $oy) : '');
                    }
                    $pages[] = $p;
                }
            }
        } else {
            $m = $mode === 'single' ? ($marks ? 8.0 : TCARD_BLEED) : 0.0;
            $pw = TCARD_W + 2 * $m; $ph = TCARD_H + 2 * $m;
            foreach ($cards as $card) {
                foreach (['front', 'back'] as $side) {
                    $pages[] = tcard_side($card, $side, $m, $m) . ($marks && $mode === 'single' ? tcard_marks($m, $m) : '');
                }
            }
        }
        $body = '';
        foreach ($pages as $i => $p) {
            $body .= '<div class="page"' . ($i ? ' style="page-break-before:always;"' : '') . '>' . $p . '</div>';
        }
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . idc_font_css()
              . '@page{size:' . $pw . 'mm ' . $ph . 'mm;margin:0;}*{margin:0;padding:0;}body{font-family:Poppins;color:' . IDC_DARK . ';}'
              . '.page{position:relative;width:' . $pw . 'mm;height:' . $ph . 'mm;overflow:hidden;}</style></head><body>' . $body . '</body></html>';
        return [$html, $pw, $ph];
    }

    function tcard_pdf_bytes(array $cards, array $opt = []): string
    {
        @ini_set('memory_limit', '1024M');
        [$html, $pw, $ph] = tcard_render_html($cards, $opt);
        $fontCache = dirname(__DIR__) . '/storage/cache/dompdf_fonts';
        if (!is_dir($fontCache)) @mkdir($fontCache, 0775, true);
        $o = new Options();
        $o->setIsRemoteEnabled(false);
        $o->setIsHtml5ParserEnabled(true);
        $o->setIsPhpEnabled(false);
        $o->setDpi(300);
        $o->setFontDir($fontCache);
        $o->setFontCache($fontCache);
        $o->setChroot([dirname(__DIR__) . '/assets', dirname(__DIR__) . '/lib/dompdf']);
        $o->setDefaultFont('Poppins');
        $pdf = new Dompdf($o);
        $pdf->loadHtml($html, 'UTF-8');
        $mm = 72 / 25.4;
        $pdf->setPaper([0, 0, $pw * $mm, $ph * $mm]);
        $pdf->render();
        return $pdf->output();
    }
}
