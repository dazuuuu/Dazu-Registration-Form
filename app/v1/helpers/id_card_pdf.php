<?php
/**
 * Student ID Card PDF Generator — Dazu Training Institute
 *
 * The design is fixed in code (Dazu_Student_ID_Card_Design.pdf). Only these values change per card:
 *  - student photo                 (passport photo from the student's profile; a placeholder when missing)
 *  - full name, course             (from the enrolment, snapshotted when the card is generated)
 *  - registration number           (front, back and as a Code 128 barcode)
 *  - valid until                   (year)
 *  - QR code                       (links to verify/id.php?k=<qr_hash>)
 *
 * Card: CR80, 85.6 × 54 mm landscape, Poppins, Dazu green #44A024 / charcoal #373435.
 * Two outputs, both two pages (front, back):
 *  - idc_pdf_bytes($card)          trimmed to 85.6 × 54 mm — the copy in the student's portal
 *  - idc_pdf_bytes($card, true)    print file: 3 mm bleed, crop marks and a caption on a 105.6 × 74 mm page
 *
 * Layout coordinates are millimetres from the top-left corner of the trimmed card.
 */

require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../lib/qrcode/barcode.php';
require_once __DIR__ . '/../services/IdCardService.php';

use Dompdf\Dompdf;
use Dompdf\Options;

const IDC_W = 85.6;
const IDC_H = 54.0;
const IDC_BLEED = 3.0;
const IDC_PRINT_MARGIN = 10.0;   // trim edge → page edge on the print file
const IDC_GREEN = '#44A024';
const IDC_DARK  = '#373435';
const IDC_PHONE = '0722 922 454';
const IDC_EMAIL = 'admin@dazuhub.com';
const IDC_SITE  = 'dazu.ac.ke';
const IDC_CAP   = 0.59;          // distance from a line-height:1 box top to Poppins' cap line, in em (measured in dompdf)

if (!function_exists('idc_cache_dir')) {
    function idc_cache_dir(): string
    {
        $dir = dirname(__DIR__, 3) . '/public/uploads/id_cards';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('idc_font_file')) {
    function idc_font_file(string $weight): string
    {
        return dirname(__DIR__) . '/assets/fonts/Poppins-' . $weight . '.ttf';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Background artwork drawn once with GD and cached. Covers the card plus bleed
// (91.6 × 60 mm) at 24 px/mm, supersampled ×2 for smooth curves.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('_idc_canvas')) {
    /** @return array{0:\GdImage,1:callable,2:callable,3:callable} image + helpers taking card-mm coordinates */
    function _idc_canvas(int $k): array
    {
        $W = (int) round((IDC_W + 2 * IDC_BLEED) * 24 * $k);
        $H = (int) round((IDC_H + 2 * IDC_BLEED) * 24 * $k);
        $im = imagecreatetruecolor($W, $H);
        $px = fn(float $mm) => ($mm + IDC_BLEED) * 24 * $k;
        $poly = function (array $pts, $color) use ($im, $px) {
            $flat = [];
            foreach ($pts as [$x, $y]) { $flat[] = (int) round($px($x)); $flat[] = (int) round($px($y)); }
            imagefilledpolygon($im, $flat, $color);
        };
        $circle = function (float $cx, float $cy, float $r, $color) use ($im, $px, $k) {
            $d = (int) round(2 * $r * 24 * $k);
            imagefilledellipse($im, (int) round($px($cx)), (int) round($px($cy)), $d, $d, $color);
        };
        return [$im, $px, $poly, $circle];
    }

    function _idc_bezier(array $p0, array $c, array $p1, int $n = 80): array
    {
        $pts = [];
        for ($i = 0; $i <= $n; $i++) {
            $t = $i / $n; $u = 1 - $t;
            $pts[] = [$u * $u * $p0[0] + 2 * $u * $t * $c[0] + $t * $t * $p1[0],
                      $u * $u * $p0[1] + 2 * $u * $t * $c[1] + $t * $t * $p1[1]];
        }
        return $pts;
    }

    function _idc_save(\GdImage $im, string $file, int $k): string
    {
        $w = (int) (imagesx($im) / $k); $h = (int) (imagesy($im) / $k);
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
        imagepng($out, $file, 6);
        return $file;
    }
}

if (!function_exists('idc_front_background_png')) {
    /** White card, charcoal + green swoosh top right, green footer band. */
    function idc_front_background_png(): string
    {
        $file = idc_cache_dir() . '/idcard_front_v1.png';
        if (is_file($file)) {
            return $file;
        }
        @ini_set('memory_limit', '512M');
        $k = 2;
        [$im, , $poly] = _idc_canvas($k);
        $white = imagecolorallocate($im, 255, 255, 255);
        $green = imagecolorallocate($im, 68, 160, 36);
        $dark  = imagecolorallocate($im, 55, 52, 53);
        imagefill($im, 0, 0, $white);

        $R = IDC_W + IDC_BLEED; $T = -IDC_BLEED; $B = IDC_H + IDC_BLEED;
        // Swoosh: green band from the top edge sweeping down to the right edge, charcoal above it.
        $greenEdge = _idc_bezier([39.3, $T], [62.0, 13.4], [$R, 25.3]);
        $darkEdge  = _idc_bezier([46.0, $T], [67.0, 9.6], [$R, 18.9]);
        $poly(array_merge($greenEdge, [[$R, $T]]), $green);
        $poly(array_merge($darkEdge, [[$R, $T]]), $dark);

        // Footer band
        $poly([[-IDC_BLEED, 48.2], [$R, 48.2], [$R, $B], [-IDC_BLEED, $B]], $green);

        return _idc_save($im, $file, $k);
    }
}

if (!function_exists('idc_back_background_png')) {
    /** Charcoal card, two soft green circles top right, green footer band. */
    function idc_back_background_png(): string
    {
        $file = idc_cache_dir() . '/idcard_back_v1.png';
        if (is_file($file)) {
            return $file;
        }
        @ini_set('memory_limit', '512M');
        $k = 2;
        [$im, , $poly, $circle] = _idc_canvas($k);
        $dark  = imagecolorallocate($im, 55, 52, 53);
        $green = imagecolorallocate($im, 68, 160, 36);
        $ring  = imagecolorallocate($im, 58, 80, 49);   // green at ~25 % over charcoal
        $core  = imagecolorallocate($im, 60, 88, 48);   // green at ~32 %
        imagefill($im, 0, 0, $dark);

        $circle(84.1, 1.8, 26.4, $ring);
        $circle(84.1, 1.8, 16.1, $core);

        $R = IDC_W + IDC_BLEED; $B = IDC_H + IDC_BLEED;
        $poly([[-IDC_BLEED, 48.8], [$R, 48.8], [$R, $B], [-IDC_BLEED, $B]], $green);

        return _idc_save($im, $file, $k);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Per-card images
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('_idc_png_uri')) {
    function _idc_png_uri(\GdImage $im): string
    {
        ob_start();
        imagepng($im);
        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    function _idc_file_uri(string $abs): string
    {
        $mime = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
    }
}

if (!function_exists('idc_photo_uri')) {
    /**
     * The photo window (inside the green frame): the student's photo cropped to fill it, or the grey
     * silhouette placeholder. Corners are rounded with transparency since dompdf cannot clip images.
     */
    function idc_photo_uri(?string $photoFile, float $wMm, float $hMm, float $radiusMm): string
    {
        $s = 24; // px per mm
        $W = (int) round($wMm * $s); $H = (int) round($hMm * $s);
        $out = imagecreatetruecolor($W, $H);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        $src = null;
        if ($photoFile && is_file($photoFile)) {
            $data = @file_get_contents($photoFile);
            $src = $data !== false ? @imagecreatefromstring($data) : null;
        }
        if ($src) {
            // Cover-fit, biased slightly upward so faces are not cut off.
            $sw = imagesx($src); $sh = imagesy($src);
            $scale = max($W / $sw, $H / $sh);
            $cw = $W / $scale; $ch = $H / $scale;
            $sx = ($sw - $cw) / 2; $sy = max(0, ($sh - $ch) * 0.3);
            imagealphablending($out, true);
            imagecopyresampled($out, $src, 0, 0, (int) $sx, (int) $sy, $W, $H, (int) $cw, (int) $ch);
        } else {
            // Supersampled placeholder: light grey background, round head, rounded shoulders
            $k = 3;
            $big = imagecreatetruecolor($W * $k, $H * $k);
            $bg = imagecolorallocate($big, 230, 232, 227);
            $fg = imagecolorallocate($big, 197, 202, 192);
            imagefill($big, 0, 0, $bg);
            $u = $s * $k;
            $cx = $wMm / 2 * $u;
            imagefilledellipse($big, (int) $cx, (int) (9.85 * $u), (int) (9.3 * $u), (int) (9.3 * $u), $fg);
            imagefilledellipse($big, (int) $cx, (int) (24.9 * $u), (int) (15.0 * $u), (int) (15.6 * $u), $fg);
            imagefilledrectangle($big, (int) ($cx - 7.5 * $u), (int) (24.9 * $u), (int) ($cx + 7.5 * $u), $H * $k, $fg);
            imagealphablending($out, true);
            imagecopyresampled($out, $big, 0, 0, 0, 0, $W, $H, $W * $k, $H * $k);
        }

        // Knock out the corners
        imagealphablending($out, false);
        $clear = imagecolorallocatealpha($out, 255, 255, 255, 127);
        $r = $radiusMm * $s;
        $n = (int) ceil($r);
        foreach ([[$r, $r], [$W - $r, $r], [$r, $H - $r], [$W - $r, $H - $r]] as [$cx, $cy]) {
            $x0 = $cx < $W / 2 ? 0 : $W - $n;
            $y0 = $cy < $H / 2 ? 0 : $H - $n;
            for ($x = $x0; $x < $x0 + $n; $x++) {
                for ($y = $y0; $y < $y0 + $n; $y++) {
                    if (hypot($x + 0.5 - $cx, $y + 0.5 - $cy) > $r) {
                        imagesetpixel($out, $x, $y, $clear);
                    }
                }
            }
        }
        return _idc_png_uri($out);
    }
}

if (!function_exists('idc_qr_uri')) {
    function idc_qr_uri(string $text): string
    {
        $img = (new barcode_generator())->render_image('qr-m', $text, ['sf' => 8, 'p' => 0, 'cm' => '1d1d1b']);
        return _idc_png_uri($img);
    }
}

if (!function_exists('idc_barcode_uri')) {
    /** Code 128 of the registration number, bars only (the number is printed underneath in HTML). */
    function idc_barcode_uri(string $text): string
    {
        $img = (new barcode_generator())->render_image('code128', $text, ['sf' => 6, 'p' => 0, 'h' => 140, 'th' => 0, 'ts' => 1, 'cm' => '1d1d1b']);
        // The library always stamps a tiny label over the bottom of the bars; cut that strip off.
        $img = imagecrop($img, ['x' => 0, 'y' => 0, 'width' => imagesx($img), 'height' => imagesy($img) - 20]) ?: $img;
        return _idc_png_uri($img);
    }
}

if (!function_exists('idc_text_mm')) {
    /** Width of $text in mm at $pt. GD renders 72 pt at 96 dpi, i.e. 96 px per em. */
    function idc_text_mm(string $text, string $weight, float $pt): float
    {
        $box = @imagettfbbox(72, 0, idc_font_file($weight), $text);
        return $box ? abs($box[2] - $box[0]) / 96 * $pt * 25.4 / 72 : mb_strlen($text) * $pt * 0.2;
    }

    /** Greedy word wrap to $widthMm; returns the lines. */
    function idc_wrap(string $text, string $weight, float $pt, float $widthMm): array
    {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($cur !== '' && idc_text_mm($try, $weight, $pt) > $widthMm) {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines;
    }
}

if (!function_exists('idc_fit_size')) {
    /** Largest font size (pt, ≤ $max) at which $text fits $widthMm on one line, or $min. */
    function idc_fit_size(string $text, string $weight, float $max, float $min, float $widthMm, float $letterEm = 0.0): float
    {
        $box = @imagettfbbox(72, 0, idc_font_file($weight), $text);
        if (!$box) {
            return $max;
        }
        // GD renders 72 pt at 96 dpi → 96 px per em
        $em = (abs($box[2] - $box[0]) / 96) + $letterEm * mb_strlen($text);
        $fit = $widthMm / ($em * 25.4 / 72);
        return max($min, min($max, floor($fit * 4) / 4));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HTML
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('idc_font_css')) {
    function idc_font_css(): string
    {
        $css = '';
        foreach ([['Poppins', 'normal', 'normal', 'Regular'], ['Poppins', 'bold', 'normal', 'Bold'],
                  ['Poppins', 'normal', 'italic', 'Italic'], ['PoppinsMedium', 'normal', 'normal', 'Medium'],
                  ['PoppinsSemi', 'normal', 'normal', 'SemiBold']] as [$fam, $w, $st, $file]) {
            $css .= '@font-face{font-family:"' . $fam . '";font-weight:' . $w . ';font-style:' . $st
                  . ';src:url("file://' . idc_font_file($file) . '") format("truetype");}';
        }
        return $css;
    }
}

if (!function_exists('_idc_text')) {
    /**
     * Absolutely placed text. $capTop is where the top of the capital letters should sit (mm), which is how the
     * design was measured; with line-height 1 the cap line sits IDC_CAP em below the box top.
     * $align: 'left' (x = left edge), 'right' (x = right edge), 'center' (x = centre).
     */
    function _idc_text(float $x, float $capTop, float $pt, string $html, string $style = '', string $align = 'left', float $width = 70): string
    {
        $mm = $pt * 25.4 / 72;
        $top = $capTop - IDC_CAP * $mm;
        $left = $align === 'left' ? $x : ($align === 'right' ? $x - $width : $x - $width / 2);
        return '<div style="position:absolute;left:' . round($left, 2) . 'mm;top:' . round($top, 2) . 'mm;width:' . $width . 'mm;'
             . 'text-align:' . $align . ';font-size:' . $pt . 'pt;line-height:1;white-space:nowrap;' . $style . '">' . $html . '</div>';
    }
}

if (!function_exists('idc_front_html')) {
    /** Front side contents, positioned relative to the trim box whose top-left is at ($ox, $oy) mm. */
    function idc_front_html(array $card, float $ox, float $oy): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = IDC_GREEN; $D = IDC_DARK;
        $at = fn(float $x, float $y) => 'position:absolute;left:' . round($ox + $x, 2) . 'mm;top:' . round($oy + $y, 2) . 'mm;';
        $T = function (float $x, float $y, float $pt, string $html, string $style = '', string $align = 'left', float $w = 70) use ($ox, $oy) {
            return _idc_text($ox + $x, $oy + $y, $pt, $html, $style, $align, $w);
        };

        $name = trim((string) ($card['snapshot_name'] ?? $card['full_name'] ?? ''));
        if ($name === mb_strtolower($name) || $name === mb_strtoupper($name)) {
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }
        $course = trim((string) ($card['snapshot_course'] ?? $card['course_name'] ?? ''));
        $regNo  = (string) ($card['snapshot_reg_no'] ?? $card['registration_no'] ?? '');
        $valid  = (string) ($card['valid_until'] ?: date('Y'));

        // Name and course shrink to fit their column; very long ones wrap to a second line.
        $nameSize = idc_fit_size($name, 'Bold', 11.3, 8.5, 48);
        $nameLines = [$name];
        if (idc_text_mm($name, 'Bold', $nameSize) > 48) {
            $nameLines = idc_wrap($name, 'Bold', 8.5, 46);
            if (count($nameLines) > 2) {
                $nameLines = [implode(' ', array_slice($nameLines, 0, -1)), end($nameLines)];
            }
            $nameSize = min(8.5, ...array_map(fn($l) => idc_fit_size($l, 'Bold', 8.5, 6, 46), $nameLines));
        }
        $courseSize = idc_fit_size($course, 'Regular', 7, 6, 54.5);
        $courseWrap = idc_fit_size($course, 'Regular', 7, 1, 54.5) < 6;

        $logoAbs = function_exists('cert_logo_file') ? cert_logo_file() : null;
        if (!$logoAbs) {
            require_once __DIR__ . '/certificate_pdf.php';
            $logoAbs = cert_logo_file();
        }
        $logo = $logoAbs ? '<img src="' . _idc_file_uri($logoAbs) . '" style="' . $at(3.9, 3.6) . 'height:8.8mm;" />' : '';

        $photo = idc_photo_uri($card['_photo_file'] ?? null, 19.9, 24.8, 1.1);
        $label = 'font-family:Poppins;font-weight:bold;color:' . $G . ';letter-spacing:0.27em;';
        $value = 'font-family:Poppins;color:' . $D . ';';

        return $logo
            . $T(81.7, 5.26, 8.5, 'STUDENT ID', 'font-family:Poppins;font-weight:bold;color:#fff;', 'right', 40)

            // Photo: green frame with the cropped photo inside
            . '<div style="' . $at(3.35, 17.3) . 'width:19.9mm;height:24.8mm;border:0.65mm solid ' . $G . ';border-radius:1.75mm;background:#fff;"></div>'
            . '<img src="' . $photo . '" style="' . $at(4.0, 17.95) . 'width:19.9mm;height:24.8mm;" />'
            . (empty($card['_photo_file']) ? $T(13.95, 40.7, 3.2, 'STUDENT PHOTO', 'font-family:Poppins;color:#6b6e68;', 'center', 20) : '')

            // Name + underline
            . implode('', array_map(fn($i) => $T(27.5, 22.14 - 0.697 * $nameSize * 25.4 / 72 - (count($nameLines) - 1 - $i) * $nameSize * 0.45,
                $nameSize, $e($nameLines[$i]), 'font-family:Poppins;font-weight:bold;color:' . $D . ';', 'left', 56), array_keys($nameLines)))
            . '<div style="' . $at(27.5, 23.6) . 'width:8.9mm;height:0.45mm;background:' . $G . ';"></div>'

            // Registration number / valid until / course
            . $T(27.5, 26.19, 3.2, 'REG. NO.', $label)
            . $T(27.5, 28.05, 7, $e($regNo), $value)
            . $T(61.5, 26.19, 3.2, 'VALID UNTIL', $label, 'left', 22)
            . $T(61.5, 28.05, 7, $e($valid), $value, 'left', 22)
            . $T(27.5, 33.54, 3.2, 'COURSE', $label)
            . ($courseWrap
                ? implode('', array_map(fn($i, $line) => $T(27.5, 35.4 + $i * 2.55, 6, $e($line), $value, 'left', 56),
                    array_keys($l = array_slice(idc_wrap($course, 'Regular', 6, 54.5), 0, 3)), $l))
                : $T(27.5, 37.02 - 0.697 * $courseSize * 25.4 / 72, $courseSize, $e($course), $value, 'left', 56))

            // Footer band text
            . $T(4.0, 50.94, 4.3, 'Dazu Training Institute', 'font-family:PoppinsMedium;color:#fff;')
            . $T(IDC_W - 4.1, 50.94, 4.3, IDC_SITE, 'font-family:Poppins;font-weight:bold;color:#fff;', 'right', 30);
    }
}

if (!function_exists('idc_back_html')) {
    function idc_back_html(array $card, float $ox, float $oy): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $D = IDC_DARK;
        $at = fn(float $x, float $y) => 'position:absolute;left:' . round($ox + $x, 2) . 'mm;top:' . round($oy + $y, 2) . 'mm;';
        $T = function (float $x, float $y, float $pt, string $html, string $style = '', string $align = 'left', float $w = 70) use ($ox, $oy) {
            return _idc_text($ox + $x, $oy + $y, $pt, $html, $style, $align, $w);
        };
        $regNo = (string) ($card['snapshot_reg_no'] ?? $card['registration_no'] ?? '');
        $box = 'background:#fff;border-radius:1.8mm;';

        require_once __DIR__ . '/certificate_pdf.php';
        $logoAbs = cert_logo_file();
        $logo = $logoAbs ? '<img src="' . _idc_file_uri($logoAbs) . '" style="' . $at(5.0, 3.85) . 'height:9.3mm;" />' : '';
        $regSize = idc_fit_size($regNo, 'Bold', 4.3, 3.2, 44);

        return '<div style="' . $at(3.5, 3.6) . 'width:30mm;height:10mm;' . $box . 'border-radius:2.2mm;"></div>' . $logo

            . $T(3.5, 17.12, 4.5, 'This card is the property of <span style="font-weight:bold;color:#fff;">Dazu Training Institute</span>. It is not', 'font-family:Poppins;color:#ececec;', 'left', 60)
            . $T(3.5, 19.35, 4.5, 'transferable. Please carry it with you and show it when asked.', 'font-family:Poppins;color:#ececec;', 'left', 60)

            // QR code
            . '<div style="' . $at(67.0, 3.4) . 'width:15mm;height:15mm;' . $box . '"></div>'
            . '<img src="' . idc_qr_uri(idc_verify_url((string) ($card['qr_hash'] ?? ''))) . '" style="' . $at(69.65, 6.05) . 'width:9.7mm;height:9.7mm;" />'
            . $T(74.5, 19.73, 3, 'SCAN TO VERIFY', 'font-family:Poppins;font-weight:bold;color:#b7dca3;letter-spacing:0.25em;', 'center', 24)

            // Barcode
            . '<div style="' . $at(3.5, 30.4) . 'width:48mm;height:11.2mm;' . $box . '"></div>'
            . '<img src="' . idc_barcode_uri($regNo !== '' ? $regNo : 'DAZU') . '" style="' . $at(10.6, 32.15) . 'width:34mm;height:5.6mm;" />'
            . $T(27.5, 40.37 - 0.697 * $regSize * 25.4 / 72, $regSize, $e($regNo), 'font-family:Poppins;font-weight:bold;color:' . $D . ';', 'center', 46)

            // Signature
            . '<div style="' . $at(54.6, 30.4) . 'width:27.35mm;height:11.2mm;' . $box . '"></div>'
            . '<div style="' . $at(56.35, 37.2) . 'width:23.8mm;height:0.14mm;background:#555;"></div>'
            . $T(68.3, 38.85, 3.15, 'Student signature', 'font-family:Poppins;color:#555;', 'center', 26)

            . $T(3.5, 44.9, 3.85, 'If found, please return this card to Dazu Training Institute.', 'font-family:Poppins;font-style:italic;color:#dcdcdc;')

            // Footer band text
            . $T(3.5, 51.07, 4.15, IDC_PHONE, 'font-family:PoppinsMedium;color:#fff;')
            . $T(IDC_W / 2, 51.07, 4.15, IDC_EMAIL, 'font-family:PoppinsMedium;color:#fff;', 'center', 40)
            . $T(IDC_W - 3.5, 51.07, 4.15, IDC_SITE, 'font-family:Poppins;font-weight:bold;color:#fff;', 'right', 30);
    }
}

if (!function_exists('idc_render_html')) {
    function idc_render_html(array $card, bool $print = false): string
    {
        $m = $print ? IDC_PRINT_MARGIN : 0.0;
        $pw = IDC_W + 2 * $m; $ph = IDC_H + 2 * $m;
        $bg = function (string $file) use ($m) {
            return '<img src="' . _idc_file_uri($file) . '" style="position:absolute;left:' . ($m - IDC_BLEED) . 'mm;top:' . ($m - IDC_BLEED)
                 . 'mm;width:' . (IDC_W + 2 * IDC_BLEED) . 'mm;height:' . (IDC_H + 2 * IDC_BLEED) . 'mm;" />';
        };
        $marks = '';
        $caption = fn(string $side) => '';
        if ($print) {
            // Crop marks: short hairlines in the margin, in line with each trim edge, kept clear of the bleed.
            $line = fn(float $x, float $y, float $w, float $h) => '<div style="position:absolute;left:' . $x . 'mm;top:' . $y . 'mm;width:' . $w . 'mm;height:' . $h . 'mm;background:#000;"></div>';
            $gap = IDC_BLEED + 1; $len = 4; $t = 0.12;
            foreach ([$m, $m + IDC_W] as $x) {
                $marks .= $line($x - $t / 2, $m - $gap - $len, $t, $len) . $line($x - $t / 2, $m + IDC_H + $gap, $t, $len);
            }
            foreach ([$m, $m + IDC_H] as $y) {
                $marks .= $line($m - $gap - $len, $y - $t / 2, $len, $t) . $line($m + IDC_W + $gap, $y - $t / 2, $len, $t);
            }
            $who = htmlspecialchars((string) ($card['snapshot_reg_no'] ?? ''), ENT_QUOTES, 'UTF-8');
            $caption = fn(string $side) => '<div style="position:absolute;left:0;width:' . $pw . 'mm;top:' . ($ph - 3.6) . 'mm;text-align:center;font-family:Poppins;font-size:3.6pt;color:#777;">'
                . $who . ' ' . $side . ', print-ready &nbsp;|&nbsp; CR80 85.6 x 54 mm &nbsp;|&nbsp; 3 mm bleed &nbsp;|&nbsp; crop marks shown</div>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
            . idc_font_css()
            . '@page{size:' . $pw . 'mm ' . $ph . 'mm;margin:0;}*{margin:0;padding:0;}'
            . 'body{font-family:Poppins;color:' . IDC_DARK . ';}'
            . '.page{position:relative;width:' . $pw . 'mm;height:' . $ph . 'mm;overflow:hidden;}'
            . '</style></head><body>'
            . '<div class="page">' . $bg(idc_front_background_png()) . idc_front_html($card, $m, $m) . $marks . $caption('FRONT') . '</div>'
            . '<div class="page" style="page-break-before:always;">' . $bg(idc_back_background_png()) . idc_back_html($card, $m, $m) . $marks . $caption('BACK') . '</div>'
            . '</body></html>';
    }
}

if (!function_exists('idc_pdf_bytes')) {
    /** $card: a student_id_cards row (idc_get()). Adds the student's photo itself. */
    function idc_pdf_bytes(array $card, bool $print = false): string
    {
        if (!array_key_exists('_photo_file', $card)) {
            $card['_photo_file'] = class_exists('\\Models\\Connection')
                ? idc_student_photo_file(\Models\Connection::getInstance()->getConnection(), (int) (($card['user_id'] ?? 0) ?: ($card['reg_user_id'] ?? 0)))
                : null;
        }
        $fontCache = dirname(__DIR__) . '/storage/cache/dompdf_fonts';
        if (!is_dir($fontCache)) {
            @mkdir($fontCache, 0775, true);
        }
        $opts = new Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(300);
        $opts->setFontDir($fontCache);
        $opts->setFontCache($fontCache);
        $opts->setChroot([dirname(__DIR__) . '/assets', dirname(__DIR__) . '/lib/dompdf']);
        $opts->setDefaultFont('Poppins');

        $mm = 72 / 25.4;
        $m = $print ? IDC_PRINT_MARGIN : 0.0;
        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml(idc_render_html($card, $print), 'UTF-8');
        $dompdf->setPaper([0, 0, (IDC_W + 2 * $m) * $mm, (IDC_H + 2 * $m) * $mm]);
        $dompdf->render();
        return $dompdf->output();
    }
}

if (!function_exists('idc_pdf_filename')) {
    function idc_pdf_filename(array $card, bool $print = false): string
    {
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) ($card['snapshot_reg_no'] ?: $card['registration_no'] ?: 'card'));
        return 'Dazu_Student_ID_' . trim($base, '_') . ($print ? '_print' : '') . '.pdf';
    }
}
