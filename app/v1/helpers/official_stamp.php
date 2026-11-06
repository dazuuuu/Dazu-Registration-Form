<?php
// app/v1/helpers/official_stamp.php
// The official stamp printed on letters of recommendation and transcripts.
//  - stamp_clean_background(): on upload, the paper/white around and inside the stamp becomes transparent. Only pixels that match the
//    background are touched; every other pixel keeps its exact colour (no recolouring, tinting or sharpening).
//  - The admin marks two areas on it once (Settings → Transcript): the date and the "Sign:" line. Saved as fractions of the picture,
//    {"x":..,"y":..,"w":..,"h":..}, in transcript_settings (stamp_date_box, stamp_sign_box).
//  - stamp_compose(): when a document is issued, the old date inside the stamp is cleared and the issue date ("21 MAY 2026") is
//    written in the same colour, at the same size and place; the director's signature is scaled onto the Sign: line in the stamp's blue.
//    The result is saved with the document, so a later stamp or signature never changes a document already issued.

if (!defined('STAMP_FONT')) define('STAMP_FONT', __DIR__ . '/../lib/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

if (!function_exists('stamp_compose')) {

    function stamp_rgba(GdImage $im, int $x, int $y): array
    {
        $c = imagecolorat($im, $x, $y);
        return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127];
    }

    function stamp_truecolor(GdImage $src): GdImage
    {
        $w = imagesx($src); $h = imagesy($src);
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagecopy($im, $src, 0, 0, 0, 0, $w, $h);
        return $im;
    }

    /**
     * Make the background transparent. The background colour is read from the picture's border (white, or slightly grey paper);
     * pixels close to it become transparent, pixels a little further fade (the soft edge of the ink), everything else is untouched.
     */
    function stamp_clean_background(GdImage $src): GdImage
    {
        $im = stamp_truecolor($src);
        $w = imagesx($im); $h = imagesy($im);
        $border = [];
        for ($x = 0; $x < $w; $x += max(1, intdiv($w, 60))) { $border[] = stamp_rgba($im, $x, 0); $border[] = stamp_rgba($im, $x, $h - 1); }
        for ($y = 0; $y < $h; $y += max(1, intdiv($h, 60))) { $border[] = stamp_rgba($im, 0, $y); $border[] = stamp_rgba($im, $w - 1, $y); }
        $opaque = array_filter($border, fn($p) => $p[3] < 64);
        $med = function (array $v) { sort($v); return $v[intdiv(count($v), 2)]; };
        $bg = $opaque ? [$med(array_column($opaque, 0)), $med(array_column($opaque, 1)), $med(array_column($opaque, 2))] : [255, 255, 255];
        if (min($bg) < 170) $bg = [255, 255, 255];   // a dark border is part of the stamp, not paper
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                [$r, $g, $b, $a] = stamp_rgba($im, $x, $y);
                if ($a === 127) continue;
                $d = max(abs($r - $bg[0]), abs($g - $bg[1]), abs($b - $bg[2]));
                if ($d >= 60) continue;   // ink: kept exactly
                $na = $d <= 22 ? 127 : (int) round(127 - ($d - 22) / 38 * (127 - $a));
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $g, $b, max($a, min(127, $na))));
            }
        }
        return $im;
    }

    /** A box in fractions → pixels [x, y, w, h], kept inside the picture. */
    function stamp_box_px(array $box, int $w, int $h): array
    {
        $x = (int) round(max(0, min(1, (float) $box['x'])) * $w);
        $y = (int) round(max(0, min(1, (float) $box['y'])) * $h);
        return [$x, $y, max(1, min($w - $x, (int) round((float) $box['w'] * $w))), max(1, min($h - $y, (int) round((float) $box['h'] * $h)))];
    }

    /** A box from the settings (JSON) or null when it is not marked or not valid. */
    function stamp_box(?string $json): ?array
    {
        $b = json_decode((string) $json, true);
        if (!is_array($b)) return null;
        foreach (['x', 'y', 'w', 'h'] as $k) if (!isset($b[$k]) || !is_numeric($b[$k])) return null;
        return $b['w'] > 0.01 && $b['h'] > 0.01 ? ['x' => (float) $b['x'], 'y' => (float) $b['y'], 'w' => (float) $b['w'], 'h' => (float) $b['h']] : null;
    }

    /** "21 MAY 2026". */
    function stamp_date_text(DateTimeInterface $d): string
    {
        return strtoupper($d->format('j M Y'));
    }

    /**
     * The stamp for one document. $date = the document's issue date. $sigAbs = the director's signature (or null).
     * Returns PNG bytes, or null when the stamp cannot be read.
     */
    function stamp_compose(string $stampAbs, array $dateBox, array $signBox, DateTimeInterface $date, ?string $sigAbs): ?string
    {
        $src = @imagecreatefromstring((string) @file_get_contents($stampAbs));
        if (!$src) return null;
        $im = stamp_truecolor($src);
        $W = imagesx($im); $H = imagesy($im);
        [$dx, $dy, $dw, $dh] = stamp_box_px($dateBox, $W, $H);

        // 1 The date's colour: the most saturated ink inside the date area.
        $ink = [];
        for ($y = $dy; $y < $dy + $dh; $y++) for ($x = $dx; $x < $dx + $dw; $x++) {
            [$r, $g, $b, $a] = stamp_rgba($im, $x, $y);
            if ($a < 50) $ink[] = [$r, $g, $b, max($r, $g, $b) - min($r, $g, $b), $x, $y];
        }
        usort($ink, fn($p, $q) => $q[3] <=> $p[3]);
        $top = array_slice($ink, 0, max(1, intdiv(count($ink), 3)));
        $col = $ink ? [(int) round(array_sum(array_column($top, 0)) / count($top)), (int) round(array_sum(array_column($top, 1)) / count($top)), (int) round(array_sum(array_column($top, 2)) / count($top))] : [200, 30, 40];
        $main = array_search(max($col), $col, true);   // the date's leading channel (red for a red date)

        // 2 Where the old date's letters are, then clear them (its soft edges too). Other colours in the area are left alone.
        $near = fn($r, $g, $b) => sqrt(($r - $col[0]) ** 2 + ($g - $col[1]) ** 2 + ($b - $col[2]) ** 2) < 120;
        $bx0 = PHP_INT_MAX; $by0 = PHP_INT_MAX; $bx1 = -1; $by1 = -1;
        $clear = imagecolorallocatealpha($im, 255, 255, 255, 127);
        for ($y = $dy; $y < $dy + $dh; $y++) for ($x = $dx; $x < $dx + $dw; $x++) {
            [$r, $g, $b, $a] = stamp_rgba($im, $x, $y);
            if ($a === 127) continue;
            $rgb = [$r, $g, $b];
            $tinted = array_search(max($rgb), $rgb, true) === $main && max($rgb) - min($rgb) > 15;
            if ($near($r, $g, $b) || $tinted) {   // the date's ink and its pale edges
                if ($a < 50 && $near($r, $g, $b)) { $bx0 = min($bx0, $x); $by0 = min($by0, $y); $bx1 = max($bx1, $x); $by1 = max($by1, $y); }
                imagesetpixel($im, $x, $y, $clear);
            }
        }
        if ($bx1 < 0) {   // nothing printed there: use the middle of the marked area
            $bx0 = $dx + (int) ($dw * 0.06); $bx1 = $dx + (int) ($dw * 0.94); $by0 = $dy + (int) ($dh * 0.2); $by1 = $dy + (int) ($dh * 0.8);
        }
        $tw = $bx1 - $bx0 + 1; $th = $by1 - $by0 + 1;

        // 3 The new date, in the same colour, drawn large and fitted into exactly the old date's place and size.
        $text = stamp_date_text($date);
        if (is_file(STAMP_FONT) && function_exists('imagettftext')) {
            $size = 200;
            $bb = imagettfbbox($size, 0, STAMP_FONT, $text);
            $cw = $bb[2] - $bb[0] + 20; $ch = $bb[1] - $bb[7] + 20;
            $t = imagecreatetruecolor($cw, $ch);
            imagealphablending($t, false); imagesavealpha($t, true);
            imagefill($t, 0, 0, imagecolorallocatealpha($t, $col[0], $col[1], $col[2], 127));
            imagealphablending($t, true);
            imagettftext($t, $size, 0, 10 - $bb[0], 10 - $bb[7], imagecolorallocate($t, $col[0], $col[1], $col[2]), STAMP_FONT, $text);
            // crop to the ink
            $x0 = $cw; $y0 = $ch; $x1 = 0; $y1 = 0;
            for ($y = 0; $y < $ch; $y++) for ($x = 0; $x < $cw; $x++) if (((imagecolorat($t, $x, $y) >> 24) & 127) < 120) { $x0 = min($x0, $x); $y0 = min($y0, $y); $x1 = max($x1, $x); $y1 = max($y1, $y); }
            if ($x1 > $x0) {
                // Same height as the old date; natural letter shapes, narrowed only if it would be wider than the old date.
                $fh = $th;
                $fw = min($tw, (int) round($fh * ($x1 - $x0 + 1) / ($y1 - $y0 + 1)));
                imagealphablending($im, true);
                imagecopyresampled($im, $t, $bx0 + intdiv($tw - $fw, 2), $by0, $x0, $y0, $fw, $fh, $x1 - $x0 + 1, $y1 - $y0 + 1);
            }
        }

        // 4 The director's signature on the Sign: line, in the stamp's blue.
        if ($sigAbs && ($sig = @imagecreatefromstring((string) @file_get_contents($sigAbs)))) {
            $blue = stamp_blue($im, [$dx, $dy, $dw, $dh]);
            $tinted = stamp_tint_signature($sig, $blue);
            if ($tinted) {
                [$sx, $sy, $sw, $sh] = stamp_box_px($signBox, $W, $H);
                $iw = imagesx($tinted); $ih = imagesy($tinted);
                $k = min($sw * 0.95 / $iw, $sh * 1.35 / $ih);
                $nw = max(1, (int) round($iw * $k)); $nh = max(1, (int) round($ih * $k));
                imagealphablending($im, true);
                imagecopyresampled($im, $tinted, $sx + intdiv($sw - $nw, 2), $sy + $sh - $nh + (int) round($sh * 0.1), 0, 0, $nw, $nh, $iw, $ih);
            }
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
        ob_start();
        imagepng($im, null, 6);
        return (string) ob_get_clean();
    }

    /** The stamp's blue: the average of its blue ink (outside the date area). */
    function stamp_blue(GdImage $im, array $skip): array
    {
        [$sx, $sy, $sw, $sh] = $skip;
        $sum = [0, 0, 0]; $n = 0;
        $W = imagesx($im); $H = imagesy($im);
        $step = max(1, intdiv(max($W, $H), 400));
        for ($y = 0; $y < $H; $y += $step) for ($x = 0; $x < $W; $x += $step) {
            if ($x >= $sx && $x < $sx + $sw && $y >= $sy && $y < $sy + $sh) continue;
            [$r, $g, $b, $a] = stamp_rgba($im, $x, $y);
            if ($a < 30 && $b - $r > 40 && $b >= $g) { $sum[0] += $r; $sum[1] += $g; $sum[2] += $b; $n++; }
        }
        return $n ? [intdiv($sum[0], $n), intdiv($sum[1], $n), intdiv($sum[2], $n)] : [20, 40, 160];
    }

    /** The signature as ink only (its strokes), coloured $rgb, cropped to the strokes. */
    function stamp_tint_signature(GdImage $src, array $rgb): ?GdImage
    {
        $s = stamp_truecolor($src);
        $w = imagesx($s); $h = imagesy($s);
        $dark = [];
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b, $a] = stamp_rgba($s, $x, $y);
            $dark[$y * $w + $x] = (1 - $a / 127) * (1 - (0.3 * $r + 0.59 * $g + 0.11 * $b) / 255);
        }
        $sorted = array_values(array_filter($dark, fn($v) => $v > 0.05));
        if (!$sorted) return null;
        sort($sorted);
        $full = max(0.2, $sorted[(int) floor(count($sorted) * 0.9)]);   // the strongest strokes count as full ink
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false); imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], 127));
        $x0 = $w; $y0 = $h; $x1 = 0; $y1 = 0;
        foreach ($dark as $i => $v) {
            $ink = min(1, $v / $full);
            if ($ink < 0.08) continue;
            $x = $i % $w; $y = intdiv($i, $w);
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], (int) round(127 * (1 - $ink))));
            $x0 = min($x0, $x); $y0 = min($y0, $y); $x1 = max($x1, $x); $y1 = max($y1, $y);
        }
        $cw = $x1 - $x0 + 1; $ch = $y1 - $y0 + 1;
        $crop = imagecreatetruecolor($cw, $ch);
        imagealphablending($crop, false); imagesavealpha($crop, true);
        imagecopy($crop, $out, 0, 0, $x0, $y0, $cw, $ch);
        return $crop;
    }
}
