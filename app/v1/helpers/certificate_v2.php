<?php
// app/v1/helpers/certificate_v2.php
// The certificate layout (A4 landscape, dompdf): guilloche security pattern, corner swooshes, NITA block, Dazu logo,
// CERTIFICATE OF COMPLETION, the name (locked), the wording under it, the faint Dazu watermark, the verification column
// (certificate number, QR, "Verify Certificate"), tutor and director signatures with the round Dazu seal between them,
// and the "Date of Issue | Certificate No" footer. Every green is the Dazu logo green.
//
// TEXT UNDER THE NAME, two boxes never mixed:
//   wording_text  (certificates.wording_text, ≤ 200): the default wording; NULL = the wording in Settings. Fields {course},
//                 {duration}, {student_name}.
//   taught_skills (≤ 160): the tutor's extra lines, empty until someone writes them.
//   The name, the registration number and the certificate number are never editable.
// ISSUED = FROZEN: when a certificate is issued, everything it prints (wording, extra lines, NITA settings, logos, names and
// signatures) is saved in certificates.issued_snapshot (cert_freeze) and the PDF is always drawn from that. Later edits and
// settings apply only to certificates issued later, or when the admin presses Reissue (cert_reissue): the certificate is drawn
// again with today's wording, settings and layout, same certificate number, registration number and QR code; the old version
// goes to certificate_history with who reissued it and when. Certificates issued before this layout keep their old look until
// reissued (cert_v2_applies).

require_once __DIR__ . '/../services/TranscriptService.php';   // tr_snapshot_file(), tr_abs(), tr_store_image()

if (!defined('CV2_GREEN')) {
    define('CV2_GREEN', '#048C44');
    define('CV2_DARK', '#037A3B');
    define('CV2_TINT', '#E3F3EA');
    define('CV2_WORDING_MAX', 200);
    define('CV2_EXTRA_MAX', 160);
    define('CV2_DEFAULT_WORDING', 'Has successfully completed a {duration} programme of study in the {course} at Dazu Training Institute.');
    define('CV2_NITA_DEFAULTS', ['logo' => '', 'number' => 'TRN/2947', 'position' => 'top_right', 'style' => 'block', 'size' => 'medium', 'color' => '#048C44']);
}

if (!function_exists('cv2_settings')) {

    function cv2_setting_model(PDO $db): SettingModel
    {
        if (!class_exists('SettingModel')) require_once __DIR__ . '/../models/SettingModel.php';
        return new SettingModel($db);
    }

    /** ['wording' => default wording, 'nita' => NITA settings]. */
    function cv2_settings(PDO $db): array
    {
        $m = cv2_setting_model($db);
        $w = trim((string) $m->get('cert_wording'));
        $n = json_decode((string) $m->get('cert_nita'), true);
        return ['wording' => $w !== '' ? $w : CV2_DEFAULT_WORDING, 'nita' => cv2_nita_clean(is_array($n) ? $n : [])];
    }

    function cv2_nita_clean(array $in): array
    {
        $o = CV2_NITA_DEFAULTS;
        if (isset($in['logo']) && ($in['logo'] === '' || tr_abs((string) $in['logo']))) $o['logo'] = (string) $in['logo'];
        if (isset($in['number'])) $o['number'] = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $in['number'])), 0, 40) ?: CV2_NITA_DEFAULTS['number'];
        if (in_array($in['position'] ?? '', ['top_right', 'under_qr', 'footer'], true)) $o['position'] = $in['position'];
        if (in_array($in['style'] ?? '', ['block', 'line'], true)) $o['style'] = $in['style'];
        if (in_array($in['size'] ?? '', ['small', 'medium', 'large'], true)) $o['size'] = $in['size'];
        if (preg_match('/^#[0-9a-f]{6}$/i', (string) ($in['color'] ?? ''))) $o['color'] = strtolower($in['color']);
        return $o;
    }

    /** Save the default wording and/or NITA settings, with a log line. */
    function cv2_settings_save(PDO $db, ?string $wording, ?array $nita, int $actor): void
    {
        $m = cv2_setting_model($db);
        $old = cv2_settings($db);
        if ($wording !== null) $m->set('cert_wording', mb_substr(trim($wording), 0, CV2_WORDING_MAX));
        if ($nita !== null) $m->set('cert_nita', json_encode(cv2_nita_clean($nita), JSON_UNESCAPED_SLASHES));
        cv2_log($db, null, $actor, 'settings_saved', ['old' => $old, 'new' => cv2_settings($db)]);
    }

    function cv2_log(PDO $db, ?int $certId, int $actor, string $event, array $details): void
    {
        try {
            $db->prepare('INSERT INTO certificate_template_events (actor_user_id, certificate_id, event, old_json, new_json) VALUES (?, ?, ?, ?, ?)')
               ->execute([$actor ?: null, $certId, $event, json_encode($details['old'] ?? null, JSON_UNESCAPED_UNICODE), json_encode($details['new'] ?? $details, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) {
            error_log('certificate log: ' . $e->getMessage());
        }
    }

    /** Clean the two text boxes: one line of wording up to 200 characters; extra lines up to 160, at most 2 lines. */
    function cv2_clean_wording(string $t): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $t)), 0, CV2_WORDING_MAX);
    }

    function cv2_clean_extra(string $t): string
    {
        $lines = array_slice(array_filter(array_map(fn($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), explode("\n", str_replace("\r", '', $t))), 'strlen'), 0, 2);
        return mb_substr(implode("\n", $lines), 0, CV2_EXTRA_MAX);
    }

    /** Save a certificate's two boxes (admin or the tutor). The issued PDF changes only after a reissue. */
    function cv2_save_text(PDO $db, int $certId, string $wording, string $extra, int $actor): array
    {
        $st = $db->prepare('SELECT wording_text, taught_skills FROM certificates WHERE id = ?');
        $st->execute([$certId]);
        $old = $st->fetch(PDO::FETCH_ASSOC);
        if (!$old) return ['ok' => false, 'error' => 'Certificate not found.'];
        $w = cv2_clean_wording($wording);
        $x = cv2_clean_extra($extra);
        $db->prepare('UPDATE certificates SET wording_text = ?, taught_skills = ? WHERE id = ?')->execute([$w === '' ? null : $w, $x, $certId]);
        cv2_log($db, $certId, $actor, 'text_saved', ['old' => $old, 'new' => ['wording_text' => $w, 'taught_skills' => $x]]);
        return ['ok' => true];
    }

    /* ---------------- which layout ---------------- */

    /** New layout: frozen ones, and anything not issued yet (drafts, previews, samples). Old issued ones keep the old layout until reissued. */
    function cv2_applies(array $cert): bool
    {
        if (!empty($cert['issued_snapshot'])) return true;
        return !(in_array($cert['status'] ?? '', ['issued', 'collected'], true) && !empty($cert['id']));
    }

    /* ---------------- the data a certificate prints ---------------- */

    /** Live data: today's settings and the certificate's own boxes ($over: unsaved boxes for the editor's preview). */
    function cv2_live(PDO $db, array $cert, array $over = []): array
    {
        $cert = cert_enrich($cert);
        $set = cv2_settings($db);
        $nita = $over['nita'] ?? $set['nita'];
        $tutorSig = sig_abs($cert['tutor_signature_path'] ?? null);
        $dirSig = sig_abs($cert['director_signature_path'] ?? null);
        if (!$dirSig && ($ds = dsig_active($db))) {   // the director signature in Settings (as on tutor IDs)
            $dirSig = $ds['file'];
            $cert['ceo_name'] = $cert['ceo_name'] ?? $ds['name'];
        }
        return [
            'name' => (string) ($cert['snapshot_name'] ?? ''), 'course' => (string) ($cert['snapshot_course'] ?? ''),
            'months' => max(1, (int) ($cert['snapshot_duration_months'] ?? 1)), 'cert_no' => (string) ($cert['certificate_no'] ?? ''),
            'qr' => (string) ($cert['qr_code_hash'] ?? ''), 'issued_at' => (string) ($cert['issued_at'] ?? date('Y-m-d H:i:s')),
            'wording' => array_key_exists('wording', $over) ? cv2_clean_wording((string) $over['wording']) : (trim((string) ($cert['wording_text'] ?? '')) ?: ($over['default_wording'] ?? $set['wording'])),
            'extra' => array_key_exists('extra', $over) ? cv2_clean_extra((string) $over['extra']) : cv2_clean_extra((string) ($cert['taught_skills'] ?? '')),
            'tutor_name' => trim((string) ($cert['tutor_name'] ?? '')) ?: '—', 'tutor_title' => trim((string) ($cert['tutor_title'] ?? '')) ?: 'Course tutor',
            'director_name' => trim((string) ($cert['ceo_name'] ?? '')) ?: CERT_DIRECTOR_NAME, 'director_title' => trim((string) ($cert['director_title'] ?? '')) ?: 'Founder & Director',
            'tutor_sig' => $tutorSig, 'director_sig' => $dirSig, 'logo' => cert_logo_file(), 'nita' => $nita, 'nita_logo' => tr_abs($nita['logo'] ?: null),
            'watermark' => $cert['_watermark'] ?? null,
        ];
    }

    /** The frozen data of an issued certificate (file paths resolved). */
    function cv2_from_snapshot(array $cert): ?array
    {
        $s = json_decode((string) ($cert['issued_snapshot'] ?? ''), true);
        if (!is_array($s)) return null;
        foreach (['tutor_sig', 'director_sig', 'logo', 'nita_logo'] as $k) $s[$k] = tr_abs($s[$k] ?? null);
        $s['watermark'] = $cert['_watermark'] ?? null;
        return $s;
    }

    /** Freeze what a certificate prints (on issue and on reissue). Pictures are copied, so later uploads never change it. */
    function cert_freeze(PDO $db, int $certId): bool
    {
        if (!class_exists('Models\\CertificateModel')) require_once __DIR__ . '/../models/CertificateModel.php';
        $cert = (new \Models\CertificateModel($db))->getCertificateById($certId);
        if (!$cert) return false;
        $d = cv2_live($db, $cert);
        $key = crc32('cert-' . $certId . '-' . microtime(true));
        $copy = fn(?string $abs, string $kind) => $abs ? tr_snapshot_file($abs, $key, $kind) : null;
        $d['tutor_sig'] = $copy($d['tutor_sig'], 'ctsig');
        $d['director_sig'] = $copy($d['director_sig'], 'cdsig');
        $d['logo'] = $copy($d['logo'], 'clogo');
        $d['nita_logo'] = $copy($d['nita_logo'], 'cnita');
        unset($d['watermark']);
        $d['layout'] = 'v2';
        $d['frozen_at'] = date('Y-m-d H:i:s');
        $db->prepare('UPDATE certificates SET issued_snapshot = ? WHERE id = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $certId]);
        return true;
    }

    /**
     * Reissue: keep the old version in certificate_history (who, when), then build it again with today's wording, settings and
     * layout. Certificate number, registration number, QR code and issue date stay the same, so verification keeps working.
     */
    function cert_reissue(PDO $db, int $certId, int $actor): array
    {
        $st = $db->prepare('SELECT * FROM certificates WHERE id = ?');
        $st->execute([$certId]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) return ['ok' => false, 'error' => 'Certificate not found.'];
        if (!in_array($c['status'], ['issued', 'collected'], true)) return ['ok' => false, 'error' => $c['certificate_no'] . ' is not issued yet; it already uses the current wording and settings.'];
        $db->prepare('INSERT INTO certificate_history (certificate_id, layout, snapshot_json, record_json, reissued_by, reissued_at) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$certId, $c['issued_snapshot'] ? 'v2' : 'v1', $c['issued_snapshot'], json_encode($c, JSON_UNESCAPED_UNICODE), $actor ?: null, date('Y-m-d H:i:s')]);
        cert_freeze($db, $certId);
        cv2_log($db, $certId, $actor, 'reissued', ['certificate_no' => $c['certificate_no']]);
        return ['ok' => true];
    }

    /* ---------------- pictures drawn once (cached) ---------------- */

    function cv2_cache(string $name): string
    {
        $f = storage_path('cache/' . $name);
        @mkdir(dirname($f), 0775, true);
        return $f;
    }

    function cv2_uri(string $file): string
    {
        return 'data:image/png;base64,' . base64_encode((string) file_get_contents($file));
    }

    /** The page background: guilloche security pattern (thin green lines, low strength) and the corner swooshes. 297 × 210 mm. */
    function cv2_background(): string
    {
        $file = cv2_cache('cert_v2_bg_3.png');
        if (is_file($file)) return $file;
        $k = 6; $W = 297 * $k; $H = 210 * $k;   // 6 px per mm
        $im = imagecreatetruecolor($W, $H);
        imageantialias($im, true);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        // Guilloche: two families of crossing waves plus slow envelopes, very light.
        $line = imagecolorallocate($im, 0xDE, 0xEF, 0xE5);   // #048C44 at about 13 % on white: a fine, light pattern
        for ($f = 0; $f < 2; $f++) {
            for ($n = -8; $n < 48; $n++) {
                $prev = null;
                for ($x = 0; $x <= $W; $x += 4) {
                    $t = $x / $W;
                    $y = $n * $H / 40 + 9 * $k * sin($t * M_PI * 7 + $n * 0.35 + $f * M_PI) + 3 * $k * sin($t * M_PI * 23 + $n * 0.9) * ($f ? 1 : -1);
                    if ($prev) imageline($im, $prev[0], (int) $prev[1], $x, (int) $y, $line);
                    $prev = [$x, $y];
                }
            }
        }
        // Corner swooshes: black then green at the top left, mirrored at the bottom right, and thin green arcs.
        $black = imagecolorallocate($im, 0x11, 0x11, 0x11);
        $green = imagecolorallocate($im, 0x04, 0x8C, 0x44);
        $dark = imagecolorallocate($im, 0x03, 0x7A, 0x3B);
        $shape = function (callable $edge, int $color, bool $topLeft) use ($im, $W, $H) {
            $pts = [];
            for ($i = 0; $i <= 60; $i++) { [$x, $y] = $edge($i / 60); $pts[] = (int) $x; $pts[] = (int) $y; }
            if ($topLeft) { $pts[] = 0; $pts[] = 0; } else { $pts[] = $W; $pts[] = $H; }
            imagefilledpolygon($im, $pts, $color);
        };
        // top left: curve from the left edge up to the top edge
        $shape(fn($t) => [100 * $k * $t, 42 * $k * (1 - $t) * (1 - 0.25 * sin($t * M_PI))], $green, true);
        $shape(fn($t) => [78 * $k * $t, 30 * $k * (1 - $t) * (1 - 0.3 * sin($t * M_PI))], $black, true);
        // bottom right
        $shape(fn($t) => [$W - 92 * $k * (1 - $t), $H - 44 * $k * $t * (1 - 0.25 * sin($t * M_PI))], $green, false);
        $shape(fn($t) => [$W - 70 * $k * (1 - $t), $H - 32 * $k * $t * (1 - 0.3 * sin($t * M_PI))], $black, false);
        imagesetthickness($im, (int) round(0.9 * $k));
        imagearc($im, (int) (300 * $k), (int) (-150 * $k), (int) (440 * $k), (int) (330 * $k), 20, 160, $green);
        imagepng($im, $file, 6);
        return $file;
    }

    /** The round Dazu seal (green, scalloped, laurel, stars). Transparent PNG. */
    function cv2_seal(): string
    {
        $file = cv2_cache('cert_v2_seal_1.png');
        if (is_file($file)) return $file;
        $n = 720; $c = $n / 2;
        $im = imagecreatetruecolor($n, $n);
        imagealphablending($im, false); imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);
        imageantialias($im, true);
        $green = imagecolorallocate($im, 0x04, 0x8C, 0x44);
        $dark = imagecolorallocate($im, 0x03, 0x7A, 0x3B);
        $white = imagecolorallocate($im, 255, 255, 255);
        $pts = [];
        for ($i = 0; $i < 480; $i++) { $a = $i / 480 * 2 * M_PI; $r = 340 + 14 * cos($a * 36); $pts[] = (int) round($c + $r * cos($a)); $pts[] = (int) round($c + $r * sin($a)); }
        imagefilledpolygon($im, $pts, $green);
        imagefilledellipse($im, $c, $c, 560, 560, $dark);
        imagefilledellipse($im, $c, $c, 540, 540, $green);
        imagesetthickness($im, 5);
        imagearc($im, $c, $c, 600, 600, 0, 360, $white);
        // laurel: leaves along both sides
        for ($side = -1; $side <= 1; $side += 2) {
            for ($j = 0; $j < 11; $j++) {
                $a = M_PI / 2 + $side * (0.55 + $j * 0.17);
                $x = $c + 225 * cos($a); $y = $c + 225 * sin($a);
                imagefilledellipse($im, (int) round($x - $side * 14), (int) round($y), 34, 16, $white);
                imagefilledellipse($im, (int) round($x + $side * 10), (int) round($y - 6), 30, 14, $white);
            }
        }
        $serif = __DIR__ . '/../lib/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Bold.ttf';
        $sans = __DIR__ . '/../lib/dompdf/vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';
        $center = function (string $t, int $size, int $y, string $font) use ($im, $c, $white) {
            $b = imagettfbbox($size, 0, $font, $t);
            imagettftext($im, $size, 0, (int) round($c - ($b[2] - $b[0]) / 2), $y, $white, $font, $t);
        };
        // graduation cap
        imagefilledpolygon($im, [$c - 62, 236, $c, 206, $c + 62, 236, $c, 266], $white);
        imagefilledrectangle($im, $c - 34, 250, $c + 34, 282, $white);
        $center('DAZU', 64, 370, $serif);
        $center('TRAINING', 34, 420, $serif);
        $center('INSTITUTE', 34, 462, $serif);
        $center('★ ★ ★', 30, 520, $sans);
        imagealphablending($im, false);
        imagepng($im, $file, 6);
        return $file;
    }

    /** The Dazu logo as a very faint watermark (its own colours, about 6 % strength). */
    function cv2_watermark(?string $logo): ?string
    {
        if (!$logo || !is_file($logo)) return null;
        $file = cv2_cache('cert_v2_wm_' . md5($logo . filemtime($logo)) . '.png');
        if (!is_file($file)) {
            $src = @imagecreatefromstring((string) file_get_contents($logo));
            if (!$src) return null;
            $w = imagesx($src); $h = imagesy($src);
            $out = imagecreatetruecolor($w, $h);
            imagealphablending($out, false); imagesavealpha($out, true);
            for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
                $p = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                $ink = (1 - $p['alpha'] / 127) * (1 - (0.3 * $p['red'] + 0.59 * $p['green'] + 0.11 * $p['blue']) / 255 * 0.85);
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $p['red'], $p['green'], $p['blue'], (int) round(127 - min(1, $ink) * 127 * 0.06)));
            }
            imagepng($out, $file, 6);
        }
        return $file;
    }

    /* ---------------- the page ---------------- */

    /** The wording as HTML: {course} bold green, {duration}, {student_name}. */
    function cv2_wording_html(string $wording, array $d): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        return strtr($e($wording), [
            '{course}' => '<b style="color:' . CV2_GREEN . '">' . $e($d['course']) . '</b>',
            '{duration}' => $e(cert_duration_label((int) $d['months'])),
            '{student_name}' => $e($d['name']),
        ]);
    }

    /** The NITA block or line for its place. */
    function cv2_nita_html(array $d, string $where): string
    {
        $n = $d['nita'];
        if ($n['position'] !== $where) return '';
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $k = ['small' => 0.85, 'medium' => 1.0, 'large' => 1.15][$n['size']];
        $col = $n['color'];
        $logo = $d['nita_logo'] ? '<img src="' . cv2_uri_any($d['nita_logo']) . '" alt="NITA" style="width:' . round(46 * $k * ($where === 'top_right' ? 1 : 0.7), 1) . 'mm">' : '';
        if ($n['style'] === 'line') {
            return '<span style="color:' . $col . ';font-weight:bold;font-size:' . round(10 * $k, 1) . 'pt">NITA Accreditation No. ' . $e($n['number']) . '</span>';
        }
        if ($where === 'footer') {
            return ($d['nita_logo'] ? '<img src="' . cv2_uri_any($d['nita_logo']) . '" style="height:' . round(6 * $k, 1) . 'mm;vertical-align:middle"> ' : '')
                . '<span style="font-size:' . round(11 * $k, 1) . 'pt">Accreditation No. </span><span style="background:' . $col . ';color:#fff;font-weight:bold;border-radius:3mm;padding:0.4mm 2.5mm;font-size:' . round(11 * $k, 1) . 'pt">' . $e($n['number']) . '</span>';
        }
        $s = $where === 'top_right' ? 1 : 0.72;
        return '<div style="text-align:center">' . $logo
            . '<div style="font-family:\'Times New Roman\',serif;font-weight:bold;font-size:' . round(16 * $k * $s, 1) . 'pt;color:#111;margin-top:' . round(2 * $s, 1) . 'mm">Accreditation No.</div>'
            . '<div style="margin:' . round(1.4 * $s, 1) . 'mm auto 0;width:' . round(45 * $k * $s, 1) . 'mm;background:' . $col . ';color:#fff;border-radius:' . round(5 * $s, 1) . 'mm;font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:' . round(18 * $k * $s, 1) . 'pt;padding:' . round(1.2 * $s, 1) . 'mm 0">' . $e($n['number']) . '</div></div>';
    }

    function cv2_uri_any(string $abs): string
    {
        $mime = @getimagesize($abs)['mime'] ?? 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
    }

    function cv2_html(array $d): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = CV2_GREEN; $abs = 'position:absolute;';
        $name = trim($d['name']);
        if ($name === mb_strtolower($name)) $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        $len = mb_strlen($name);
        $nameSize = $len > 22 ? max(28, (int) floor(48 * 22 / $len)) : 48;
        $issued = date('j F Y', strtotime($d['issued_at']) ?: time());
        $qr = cert_qr_data_uri(cert_verify_url($d['qr']));
        $host = preg_replace('#^https?://#', '', rtrim(cert_verify_url(), '/'));
        $wm = cv2_watermark($d['logo']);
        $sig = fn(?string $f) => $f ? '<img src="' . cv2_uri_any($f) . '" style="max-height:15mm;max-width:46mm">' : '';
        $serif = "font-family:'Times New Roman',Times,serif;";
        $sans = 'font-family:Helvetica,Arial,sans-serif;';
        $line = fn($l, $t, $w) => '<div style="' . $abs . 'left:' . $l . 'mm;top:' . $t . 'mm;width:' . $w . 'mm;border-top:0.5mm solid ' . $G . '"></div>';
        $extra = trim($d['extra']);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 landscape;margin:0}*{margin:0;padding:0}body{width:297mm;height:210mm;color:#111}</style></head><body>'
            . '<img src="' . cv2_uri(cv2_background()) . '" style="' . $abs . 'left:0;top:0;width:297mm;height:210mm">'
            . ($wm ? '<img src="' . cv2_uri($wm) . '" style="' . $abs . 'left:96mm;top:108mm;width:105mm">' : '')
            // logo and titles
            . ($d['logo'] ? '<div style="' . $abs . 'left:0;width:297mm;top:14mm;text-align:center"><img src="' . cv2_uri_any($d['logo']) . '" style="height:31mm"></div>' : '')
            . '<div style="' . $abs . 'left:0;width:297mm;top:50mm;text-align:center;' . $serif . 'font-weight:bold;font-size:58pt;line-height:1;letter-spacing:1pt">CERTIFICATE</div>'
            . $line(73.5, 76.2, 18.5) . $line(205, 76.2, 18.5)
            . '<div style="' . $abs . 'left:0;width:297mm;top:72.6mm;text-align:center;' . $serif . 'font-weight:bold;font-size:22pt;line-height:1;letter-spacing:8.5pt;color:' . $G . '">OF COMPLETION</div>'
            . '<div style="' . $abs . 'left:0;width:297mm;top:87mm;text-align:center;' . $serif . 'font-weight:bold;font-size:11pt;line-height:1;letter-spacing:4.6pt">THIS CERTIFICATE IS PROUDLY PRESENTED TO</div>'
            // name (locked)
            . '<div style="' . $abs . 'left:62mm;width:173mm;top:' . (113.5 - $nameSize * 0.42) . 'mm;text-align:center;' . $serif . 'font-weight:bold;font-style:italic;font-size:' . $nameSize . 'pt;line-height:1;color:' . $G . ';white-space:nowrap">' . $e($name) . '</div>'
            . $line(92, 115.8, 52) . $line(153, 115.8, 52)
            . '<div style="' . $abs . 'left:146.8mm;top:114.4mm;width:2.4mm;height:2.4mm;background:' . $G . ';transform:rotate(45deg)"></div>'
            // the two boxes under the name
            . '<div style="' . $abs . 'left:66mm;width:165mm;top:120.5mm;text-align:center;' . $serif . 'font-size:14pt;line-height:1.35">' . cv2_wording_html($d['wording'], $d)
            . ($extra !== '' ? '<div style="margin-top:1mm;font-size:12.5pt">' . nl2br($e($extra)) . '</div>' : '') . '</div>'
            // verification column
            . '<div style="' . $abs . 'left:7mm;width:41mm;top:98mm;text-align:center;' . $sans . 'font-size:9.5pt">' . $e($d['cert_no']) . '</div>'
            . '<div style="' . $abs . 'left:14.5mm;top:104.5mm;width:26mm;height:26mm">'
            . '<img src="' . $qr . '" style="' . $abs . 'left:2mm;top:2mm;width:22mm;height:22mm">'
            . '<div style="' . $abs . 'left:0;top:0;width:4.5mm;height:4.5mm;border-left:0.7mm solid ' . $G . ';border-top:0.7mm solid ' . $G . '"></div>'
            . '<div style="' . $abs . 'right:0;top:0;width:4.5mm;height:4.5mm;border-right:0.7mm solid ' . $G . ';border-top:0.7mm solid ' . $G . '"></div>'
            . '<div style="' . $abs . 'left:0;bottom:0;width:4.5mm;height:4.5mm;border-left:0.7mm solid ' . $G . ';border-bottom:0.7mm solid ' . $G . '"></div>'
            . '<div style="' . $abs . 'right:0;bottom:0;width:4.5mm;height:4.5mm;border-right:0.7mm solid ' . $G . ';border-bottom:0.7mm solid ' . $G . '"></div></div>'
            . '<div style="' . $abs . 'left:9mm;width:37mm;top:132.6mm;' . $sans . 'font-weight:bold;font-size:10.5pt;color:' . $G . '">Verify Certificate</div>'
            . '<div style="' . $abs . 'left:9mm;width:40mm;top:137.6mm;' . $serif . 'font-style:italic;font-size:8.8pt;line-height:1.3">Scan the QR code or visit<br>' . $e($host) . '</div>'
            . (($u = cv2_nita_html($d, 'under_qr')) !== '' ? '<div style="' . $abs . 'left:6mm;width:43mm;top:148mm">' . $u . '</div>' : '')
            // NITA top right
            . (($t = cv2_nita_html($d, 'top_right')) !== '' ? '<div style="' . $abs . 'left:225.5mm;top:23mm;width:0;height:31mm;border-left:0.5mm solid ' . $G . '"></div>'
                . '<div style="' . $abs . 'left:231mm;width:52mm;top:17.5mm">' . $t . '</div>' : '')
            // signatures and seal
            . '<div style="' . $abs . 'left:69.5mm;width:40mm;top:152mm;height:16mm;text-align:center">' . $sig($d['tutor_sig']) . '</div>'
            . $line(69.5, 169.3, 40)
            . '<div style="' . $abs . 'left:59.5mm;width:60mm;top:171mm;text-align:center;' . $serif . 'font-weight:bold;font-size:17pt;line-height:1;color:' . $G . '">' . $e($d['tutor_name']) . '</div>'
            . '<div style="' . $abs . 'left:59.5mm;width:60mm;top:178.3mm;text-align:center;' . $serif . 'font-size:9.5pt;letter-spacing:2.6pt;line-height:1">' . $e(mb_strtoupper($d['tutor_title'])) . '</div>'
            . '<img src="' . cv2_uri(cv2_seal()) . '" style="' . $abs . 'left:132.75mm;top:151.5mm;width:31.5mm">'
            . '<div style="' . $abs . 'left:187.5mm;width:51mm;top:152mm;height:16mm;text-align:center">' . $sig($d['director_sig']) . '</div>'
            . $line(187.5, 169.3, 51)
            . '<div style="' . $abs . 'left:183mm;width:60mm;top:171mm;text-align:center;' . $serif . 'font-weight:bold;font-size:17pt;line-height:1;color:' . $G . '">' . $e($d['director_name']) . '</div>'
            . '<div style="' . $abs . 'left:178mm;width:70mm;top:178.3mm;text-align:center;' . $serif . 'font-size:9.5pt;letter-spacing:2.6pt;line-height:1">' . $e(mb_strtoupper($d['director_title'])) . '</div>'
            // footer
            . '<div style="' . $abs . 'left:40mm;width:96mm;top:189.5mm;text-align:right;' . $serif . 'font-size:13.5pt"><i>Date of Issue:</i>&nbsp; ' . $e($issued) . '</div>'
            . '<div style="' . $abs . 'left:147.2mm;top:188.5mm;width:0;height:6.5mm;border-left:0.5mm solid ' . $G . '"></div>'
            . '<div style="' . $abs . 'left:158.5mm;width:100mm;top:189.5mm;' . $serif . 'font-size:13.5pt"><i>Certificate No:</i> ' . $e($d['cert_no']) . '</div>'
            . (($f = cv2_nita_html($d, 'footer')) !== '' ? '<div style="' . $abs . 'left:0;width:297mm;top:197mm;text-align:center;' . $serif . '">' . $f . '</div>' : '')
            . (!empty($d['watermark']) ? '<div style="' . $abs . 'left:0;width:297mm;top:88mm;text-align:center;font-size:96pt;font-weight:bold;letter-spacing:12pt;color:rgba(55,52,53,0.13);transform:rotate(-18deg)">' . $e($d['watermark']) . '</div>' : '')
            . '</body></html>';
        return $html;
    }
}
