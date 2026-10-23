<?php
/**
 * Certificate PDF Generator — Dazu Training Institute
 *
 * The design is fixed in code (no uploaded template). Only these values change per certificate:
 *  - student name, course, programme duration           (from the student's enrolment)
 *  - extra programme text                                (typed by admin, stored in certificates.taught_skills)
 *  - tutor name                                          (the student's assigned tutor)
 *  - director name                                       (CERT_DIRECTOR_NAME — Simon Mwangi)
 *  - certificate number, issue date and a real QR code   (links to verify/?c=<qr_code_hash>)
 *
 * Layout coordinates are in millimetres on an A4 landscape page (297 × 210).
 */

require_once __DIR__ . '/../lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../lib/qrcode/barcode.php';
require_once __DIR__ . '/../services/CertificateService.php';
require_once __DIR__ . '/signatures.php';

use Dompdf\Dompdf;
use Dompdf\Options;

const CERT_GREEN = '#5c9e3a';
const CERT_DARK  = '#38373a';

// ─────────────────────────────────────────────────────────────────────────────
// Background artwork (bands, swoosh, badge, wavy lines) drawn once with GD and cached.
// Drawn in "design units" (1622 × 1147 ≈ A4 landscape), supersampled for smooth edges.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('cert_background_png')) {
    function cert_background_png(): string
    {
        $cacheDir = dirname(__DIR__, 3) . '/public/uploads/certificates';
        $cache = $cacheDir . '/cert_background_v1.png';
        if (is_file($cache)) {
            return $cache;
        }
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @ini_set('memory_limit', '512M');

        $W = 1622; $H = 1147; $k = 3;
        $im = imagecreatetruecolor($W * $k, $H * $k);
        $white = imagecolorallocate($im, 255, 255, 255);
        $green = imagecolorallocate($im, 92, 158, 58);
        $dark  = imagecolorallocate($im, 56, 55, 58);
        $wave  = imagecolorallocate($im, 228, 228, 228);
        imagefill($im, 0, 0, $white);

        $poly = function (array $pts, $color) use ($im, $k) {
            $flat = [];
            foreach ($pts as [$x, $y]) { $flat[] = (int) round($x * $k); $flat[] = (int) round($y * $k); }
            imagefilledpolygon($im, $flat, $color);
        };
        $bezier = function (array $p0, array $c, array $p1, int $n = 60): array {
            $pts = [];
            for ($i = 0; $i <= $n; $i++) {
                $t = $i / $n; $u = 1 - $t;
                $pts[] = [$u * $u * $p0[0] + 2 * $u * $t * $c[0] + $t * $t * $p1[0],
                          $u * $u * $p0[1] + 2 * $u * $t * $c[1] + $t * $t * $p1[1]];
            }
            return $pts;
        };
        $stroke = function (array $pts, $color, float $width) use ($im, $k) {
            imagesetthickness($im, max(1, (int) round($width * $k)));
            for ($i = 1, $n = count($pts); $i < $n; $i++) {
                imageline($im, (int) ($pts[$i - 1][0] * $k), (int) ($pts[$i - 1][1] * $k), (int) ($pts[$i][0] * $k), (int) ($pts[$i][1] * $k), $color);
            }
            imagesetthickness($im, 1);
        };
        $circle = function (float $cx, float $cy, float $r, $color) use ($im, $k) {
            imagefilledellipse($im, (int) ($cx * $k), (int) ($cy * $k), (int) (2 * $r * $k), (int) (2 * $r * $k), $color);
        };

        // Wavy guilloche lines behind the signature area
        for ($i = 0; $i < 20; $i++) {
            $pts = [];
            for ($x = 270; $x <= 1395; $x += 6) {
                $d = $x - 270;
                $pts[] = [$x, 840 + $i * 14 + 16 * sin($d / 150 + $i * 0.22) - $d * 0.07];
            }
            $stroke($pts, $wave, 1.4);
        }

        // Top-left: dark corner + green diagonal band
        $poly([[0, 0], [480, 0], [0, 502]], $dark);
        $poly([[480, 0], [587, 0], [0, 622], [0, 502]], $green);

        // Top-right: green swoosh with a thin white line
        $inner = $bezier([962, 0], [1500, 70], [$W, 337]);
        $outer = $bezier([1112, 0], [1545, 25], [$W, 172]);
        $poly(array_merge($inner, array_reverse($outer)), $green);
        $stroke($bezier([1050, 0], [1525, 45], [$W, 262]), $white, 2.2);

        // Bottom-right: green band + dark corner
        $poly([[$W, 652], [$W, 732], [1112, $H], [1002, $H]], $green);
        $poly([[$W, 732], [$W, $H], [1112, $H]], $dark);

        // Name underline with end dots
        $stroke([[560, 527], [1062, 527]], $green, 3);
        $circle(560, 527, 5, $green);
        $circle(1062, 527, 5, $green);

        // "CERTIFIED ORIGINAL DAZU" rosette (text is overlaid in HTML)
        $bx = 1422; $by = 249;
        $poly([[1388, 318], [1432, 334], [1404, 417], [1382, 394], [1345, 416]], $dark);
        $poly([[1432, 334], [1466, 318], [1499, 416], [1462, 396], [1442, 417]], $green);
        $star = [];
        for ($i = 0; $i < 80; $i++) {
            $a = M_PI * 2 * $i / 80;
            $r = ($i % 2) ? 99 : 110;
            $star[] = [$bx + $r * cos($a), $by + $r * sin($a)];
        }
        $poly($star, $dark);
        $circle($bx, $by, 91, $white);
        $circle($bx, $by, 85, $green);
        $circle($bx, $by, 70, $white);
        $circle($bx, $by, 68, $green);
        $stroke([[1378, 236], [1466, 236]], $white, 1.6);
        $stroke([[1378, 270], [1466, 270]], $white, 1.6);

        $outW = (int) ($W * 1.5); $outH = (int) ($H * 1.5);
        $out = imagecreatetruecolor($outW, $outH);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $outW, $outH, $W * $k, $H * $k);
        imagepng($out, $cache, 6);
        return $cache;
    }
}

if (!function_exists('cert_qr_data_uri')) {
    function cert_qr_data_uri(string $text): string
    {
        $gen = new barcode_generator();
        $img = $gen->render_image('qr-m', $text, ['sf' => 8, 'p' => 0]);
        ob_start();
        imagepng($img);
        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }
}

if (!function_exists('_cert_data_uri')) {
    function _cert_data_uri(string $abs): string
    {
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
    }
}

if (!function_exists('cert_logo_file')) {
    /** The navbar logo (app/v1/assets/brand), else the logo uploaded in Admin → Settings → Branding. */
    function cert_logo_file(): ?string
    {
        require_once __DIR__ . '/brand.php';
        if ($f = brand_file('logo')) {
            return $f;
        }
        require_once __DIR__ . '/site_logo.php';
        $rel = site_logo_rel_path();
        if ($rel && is_file($abs = dirname(__DIR__, 3) . '/public/' . ltrim($rel, '/'))) {
            return $abs;
        }
        return null;
    }
}

if (!function_exists('_cert_sig_img')) {
    /** Uploaded signature image (private storage or public path), or nothing (the line is left blank for a wet signature). */
    function _cert_sig_img(?string $relPath): string
    {
        $abs = sig_abs($relPath);
        if (!$abs) {
            return '';
        }
        return '<img src="' . _cert_data_uri($abs) . '" style="max-height:16mm;max-width:50mm;" />';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HTML for one certificate
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('cert_render_html')) {
    function cert_render_html(array $cert): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $name     = trim((string) ($cert['snapshot_name'] ?? ''));
        if ($name === mb_strtolower($name)) {
            $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }
        $course   = (string) ($cert['snapshot_course'] ?? '');
        $months   = max(1, (int) ($cert['snapshot_duration_months'] ?? 1));
        $certNo   = (string) ($cert['certificate_no'] ?? '');
        $tutor    = trim((string) ($cert['tutor_name'] ?? '')) ?: '—';
        $director = trim((string) ($cert['ceo_name'] ?? '')) ?: CERT_DIRECTOR_NAME;
        $issued   = date('j F Y', strtotime($cert['issued_at'] ?? 'now') ?: time());
        $extra    = trim((string) ($cert['taught_skills'] ?? ''));

        $body = 'Has successfully completed a ' . cert_duration_label($months) . ' programme of study in the <b>'
              . $e($course) . '</b> at Dazu Training Institute'
              // Admin text that starts lowercase ("and has met all…") continues the sentence; otherwise it follows it.
              . ($extra === '' ? '.' : (preg_match('/^\p{Ll}/u', $extra) ? ', ' : '. ') . nl2br($e($extra)));
        if ($extra !== '' && !preg_match('/[.!?]$/', $extra)) {
            $body .= '.';
        }

        // Shrink long names so they stay on one line
        $len = mb_strlen($name);
        $nameSize = $len > 24 ? max(26, (int) floor(44 * 24 / $len)) : 44;

        $bg = _cert_data_uri(cert_background_png());
        $logoAbs = cert_logo_file();
        $logo = $logoAbs
            ? '<img src="' . _cert_data_uri($logoAbs) . '" style="height:19mm;" />'
            : '<span style="font-size:30pt;font-weight:bold;color:' . CERT_DARK . ';">DAZU</span>';
        $qr = cert_qr_data_uri(cert_verify_url((string) ($cert['qr_code_hash'] ?? '')));
        $verifyHost = preg_replace('#^https?://#', '', rtrim(cert_verify_url(), '/'));

        $G = CERT_GREEN; $D = CERT_DARK;
        $abs = 'position:absolute;';
        $center = 'text-align:center;';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { size: A4 landscape; margin: 0; }
* { margin: 0; padding: 0; }
body { width: 297mm; height: 210mm; font-family: "Times New Roman", Times, serif; color: ' . $D . '; }
</style></head><body>'
        . '<img src="' . $bg . '" style="' . $abs . 'left:0;top:0;width:297mm;height:210mm;" />'

        // Logo
        . '<div style="' . $abs . 'left:0;width:297mm;top:8mm;' . $center . '">' . $logo . '</div>'

        // Titles
        . '<div style="' . $abs . 'left:0;width:297mm;top:35mm;' . $center . 'font-size:56pt;font-weight:bold;letter-spacing:2pt;line-height:1;">CERTIFICATE</div>'
        . '<div style="' . $abs . 'left:0;width:297mm;top:55mm;' . $center . 'font-size:22pt;font-weight:bold;letter-spacing:8pt;color:' . $G . ';line-height:1;">OF COMPLETION</div>'
        . '<div style="' . $abs . 'left:0;width:297mm;top:70.5mm;' . $center . 'font-size:11pt;font-weight:bold;letter-spacing:4pt;line-height:1;">THIS CERTIFICATE IS PROUDLY PRESENTED TO</div>'

        // Student name
        . '<div style="' . $abs . 'left:55mm;width:187mm;top:' . (94 - $nameSize * 0.36) . 'mm;' . $center . 'font-size:' . $nameSize . 'pt;font-weight:bold;font-style:italic;color:' . $G . ';line-height:1;white-space:nowrap;">' . $e($name) . '</div>'

        // Programme text
        . '<div style="' . $abs . 'left:66mm;width:165mm;top:104mm;' . $center . 'font-size:13.5pt;line-height:1.4;">' . $body . '</div>'

        // Badge text
        . '<div style="' . $abs . 'left:240.4mm;width:40mm;top:37.4mm;' . $center . 'color:#fff;font-size:6.5pt;font-weight:bold;letter-spacing:1.2pt;line-height:1;">CERTIFIED</div>'
        . '<div style="' . $abs . 'left:240.4mm;width:40mm;top:43mm;' . $center . 'color:#fff;font-size:11.5pt;font-weight:bold;line-height:1;">ORIGINAL</div>'
        . '<div style="' . $abs . 'left:240.4mm;width:40mm;top:50.6mm;' . $center . 'color:#fff;font-size:7pt;font-weight:bold;letter-spacing:2.5pt;line-height:1;">DAZU</div>'

        // QR panel
        . '<div style="' . $abs . 'left:17.8mm;top:102mm;width:42mm;font-size:10pt;font-style:italic;">Valid Certificate ID</div>'
        . '<img src="' . $qr . '" style="' . $abs . 'left:17mm;top:106mm;width:34mm;height:34mm;" />'
        . '<div style="' . $abs . 'left:17.8mm;top:142.5mm;width:60mm;font-size:11pt;font-weight:bold;">' . $e($certNo) . '</div>'
        . '<div style="' . $abs . 'left:17.8mm;top:147.6mm;width:60mm;font-size:9pt;font-style:italic;line-height:1.3;">Scan to verify, or visit<br>' . $e($verifyHost) . '</div>'

        // Signatures
        . '<div style="' . $abs . 'left:88.3mm;width:56.3mm;top:151mm;height:17mm;' . $center . '">' . _cert_sig_img($cert['tutor_signature_path'] ?? null) . '</div>'
        . '<div style="' . $abs . 'left:158.7mm;width:56.5mm;top:151mm;height:17mm;' . $center . '">' . _cert_sig_img($cert['director_signature_path'] ?? null) . '</div>'
        . '<div style="' . $abs . 'left:88.3mm;width:56.3mm;top:168.6mm;border-top:0.45mm solid ' . $D . ';"></div>'
        . '<div style="' . $abs . 'left:158.7mm;width:56.5mm;top:168.6mm;border-top:0.45mm solid ' . $D . ';"></div>'
        . '<div style="' . $abs . 'left:78mm;width:77mm;top:171mm;' . $center . 'font-size:14pt;font-weight:bold;line-height:1;">' . $e($tutor) . '</div>'
        . '<div style="' . $abs . 'left:78mm;width:77mm;top:176.6mm;' . $center . 'font-size:9pt;letter-spacing:1.5pt;line-height:1;">' . $e(mb_strtoupper(trim((string) ($cert['tutor_title'] ?? '')) ?: 'Course tutor')) . '</div>'
        . '<div style="' . $abs . 'left:148.5mm;width:77mm;top:171mm;' . $center . 'font-size:14pt;font-weight:bold;line-height:1;">' . $e($director) . '</div>'
        . '<div style="' . $abs . 'left:148.5mm;width:77mm;top:176.6mm;' . $center . 'font-size:9pt;letter-spacing:1.5pt;line-height:1;">' . $e(mb_strtoupper(trim((string) ($cert['director_title'] ?? '')) ?: 'Director')) . '</div>'

        // Footer line
        . '<div style="' . $abs . 'left:66mm;width:165mm;top:186.4mm;' . $center . 'font-size:12pt;line-height:1;">Certificate No: ' . $e($certNo)
        . ' &nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp; Date of issue: ' . $e($issued) . '</div>'
        // Optional diagonal watermark ('SAMPLE' on the tutor's sample certificate).
        . (!empty($cert['_watermark']) ? '<div style="' . $abs . 'left:0;width:297mm;top:88mm;' . $center . 'font-size:96pt;font-weight:bold;letter-spacing:12pt;color:rgba(55,52,53,0.13);transform:rotate(-18deg);">'
            . $e($cert['_watermark']) . '</div>' : '')
        . '</body></html>';
    }
}

if (!function_exists('cert_pdf_bytes')) {
    function cert_pdf_bytes(array $cert): string
    {
        // Older certificates may predate the saved tutor name — fall back to the currently assigned tutor.
        if (trim((string) ($cert['tutor_name'] ?? '')) === '' && !empty($cert['user_id']) && class_exists('\\Models\\Connection')) {
            $t = cert_assigned_tutor(\Models\Connection::getInstance()->getConnection(), (int) $cert['user_id']);
            $cert['tutor_name'] = $t['full_name'] ?? null;
            $cert['tutor_id'] = $t['user_id'] ?? null;
        }
        if (class_exists('\\Models\\Connection')) {
            $db = \Models\Connection::getInstance()->getConnection();
            // The trainer's own signature (certificates.tutor_id is the tutor's users.id).
            if (empty($cert['tutor_signature_path']) && !empty($cert['tutor_id'])) {
                try {
                    $st = $db->prepare('SELECT signature_path FROM tutors WHERE user_id = ? LIMIT 1');
                    $st->execute([(int) $cert['tutor_id']]);
                    $cert['tutor_signature_path'] = $st->fetchColumn() ?: null;
                } catch (Throwable $e) {
                }
            }
            // The director saved on the certificate, or (older certificates) whoever signs certificates now.
            $dir = null;
            try {
                if (!empty($cert['director_id'])) {
                    $st = $db->prepare('SELECT * FROM directors WHERE id = ?');
                    $st->execute([(int) $cert['director_id']]);
                    $dir = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            } catch (Throwable $e) {
            }
            // Issued with a director signature version (admin Settings): always that version.
            try {
                if (!empty($cert['director_signature_id'])) {
                    $st = $db->prepare('SELECT name AS full_name, title, file_path AS signature_path FROM director_signatures WHERE id = ?');
                    $st->execute([(int) $cert['director_signature_id']]);
                    $dir = $st->fetch(PDO::FETCH_ASSOC) ?: $dir;
                }
            } catch (Throwable $e) {
            }
            $dir = $dir ?: sig_certificate_director($db);
            if ($dir) {
                $cert['ceo_name'] = $dir['full_name'];
                $cert['director_title'] = $dir['title'];
                if (empty($cert['director_signature_path'])) {
                    $cert['director_signature_path'] = $dir['signature_path'];
                }
            }
        }
        $opts = new Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(150);
        $opts->setDefaultFont('Times-Roman');

        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml(cert_render_html($cert), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return $dompdf->output();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Generate + save to disk (used by download / email)
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('generate_certificate_pdf')) {
    function generate_certificate_pdf(array $cert, string $templatePath, string $outputDir): array
    {
        if (!is_dir($outputDir) && !@mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            return ['ok' => false, 'error' => 'Cannot create output directory.'];
        }
        try {
            $bytes = cert_pdf_bytes($cert);
        } catch (\Throwable $ex) {
            return ['ok' => false, 'error' => $ex->getMessage()];
        }
        $filename = 'certificate_' . ($cert['id'] ?? uniqid()) . '_' . date('Ymd') . '.pdf';
        $outputPath = rtrim($outputDir, '/') . '/' . $filename;
        if (file_put_contents($outputPath, $bytes) === false) {
            return ['ok' => false, 'error' => 'Failed to write PDF to disk.'];
        }
        return [
            'ok'       => true,
            'path'     => $outputPath,
            'web_path' => 'uploads/certificates/generated/' . $filename,
            'filename' => $filename,
        ];
    }
}

if (!function_exists('get_certificate_template_path')) {
    /** Kept for older callers — the design is now built in, so there is no template file. */
    function get_certificate_template_path(string $uploadsDir): ?string
    {
        return null;
    }
}
