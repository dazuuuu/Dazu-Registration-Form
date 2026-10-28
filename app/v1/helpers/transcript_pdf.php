<?php
/**
 * The academic transcript page: ONE A4 portrait page, used for the screen view and the PDF (dompdf).
 * Shows: logo, institute name, title, transcript number, surname and other names, course, student number, campus, date of admission,
 * the table (Code, Subject, Mark, Grade: no weight column, plain white top), course average, recommendation, grading key,
 * QR (released only), the director's signature and the official stamp (released only), and an optional faded logo watermark.
 * Input comes from tr_print_data() only, so a released transcript prints exactly what was locked at release.
 */
require_once __DIR__ . '/completion_letter_pdf.php';   // dompdf, cert_qr_data_uri(), cap_letter_flat_image(), CERT_GREEN / CERT_DARK

if (!function_exists('tr_html')) {
    function tr_img_uri(?string $abs): ?string
    {
        if (!$abs || !is_file($abs)) return null;
        $mime = strtolower(pathinfo($abs, PATHINFO_EXTENSION)) === 'jpg' ? 'image/jpeg' : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
    }

    /** $d from tr_print_data(). $forPdf: dompdf page (true) or the sheet inside a web page (false). */
    function tr_html(array $d, bool $forPdf): string
    {
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $G = CERT_GREEN; $D = CERT_DARK;
        $host = preg_replace('#^https?://#', '', rtrim(cert_verify_url(), '/'));
        $host = preg_replace('#/.*$#', '', $host);
        $phone = function_exists('site_phone_display') ? site_phone_display() : '';
        $logo = tr_img_uri($d['logo']);
        $stamp = tr_img_uri($d['stamp']);
        $sig = $d['signature'] ? cap_letter_flat_image($d['signature']) : null;
        $qr = $d['qr'] ? cert_qr_data_uri($d['qr']) : null;
        $date = $d['date'] ? date('j F Y', strtotime($d['date'])) : null;
        $admitted = $d['admitted_on'] ? date('F Y', strtotime($d['admitted_on'])) : '—';
        $wordColor = fn($w) => $w === 'Credit' ? '#1f4e9c' : ($w === 'Fail' ? '#b42318' : ($w === '—' ? '#888' : $G));
        $op = ['light' => 0.05, 'medium' => 0.09, 'strong' => 0.15][$d['watermark']] ?? 0;

        // One A4 page: about 84 mm is left for the subject rows, so the row padding (and for very long lists the font) shrinks to fit.
        $n = max(1, count($d['rows']));
        $font = $n <= 18 ? 9 : 8;
        $line = $font === 9 ? 4.0 : 3.6;
        $rowPad = round(max(0.4, min(2.0, (84 / $n - $line) / 2)), 2);

        $rows = '';
        foreach ($d['rows'] as $r) {
            $mark = $r['mark'] === null ? '—' : (int) round((float) $r['mark']) . '%';
            $rows .= '<tr><td class="c">' . $e($r['code']) . '</td><td class="s"><b>' . $e($r['subject']) . '</b>'
                   . ($r['detail'] !== '' ? ' <span class="det">' . $e($r['detail']) . '</span>' : '') . '</td>'
                   . '<td class="m">' . $e($mark) . '</td><td class="g" style="color:' . $wordColor($r['grade']) . '">' . $e($r['grade']) . '</td></tr>';
        }
        $avg = $d['average'] === null ? '—' : (int) round((float) $d['average']) . '%';

        $css = '
.tr-sheet{position:relative;width:210mm;height:297mm;background:#fff;color:' . $D . ';font-family:Poppins,"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:' . $font . 'pt;overflow:hidden}
.tr-in{position:absolute;left:16mm;top:11mm;width:178mm}
.tr-sheet table{border-collapse:collapse;width:100%}
.tr-wm{position:absolute;left:35mm;top:95mm;width:140mm;text-align:center;opacity:' . $op . '}
.tr-no{position:absolute;left:144mm;top:11mm;width:50mm;text-align:right}
.tr-no b{color:' . $G . ';font-size:11pt;letter-spacing:1pt}.tr-no small{display:block;color:#666;font-size:7.5pt}
.tr-h{text-align:center}
.tr-h .n{font-size:13pt;font-weight:bold;letter-spacing:1pt;margin-top:2mm}
.tr-h .a{color:#666;font-size:8.5pt;margin-top:1mm}
.tr-h .t{display:inline-block;font-weight:bold;letter-spacing:1.5pt;font-size:10.5pt;margin-top:3mm;padding-bottom:1mm;border-bottom:0.6mm solid ' . $G . '}
.tr-names td{width:50%;vertical-align:bottom;padding:0}
.tr-names .v{font-weight:bold;font-size:12pt;border-bottom:0.3mm solid #555;padding:0 0 1mm 0;text-transform:uppercase}
.tr-names .l{text-align:center;font-weight:bold;font-size:8.5pt;padding-top:1mm}
.tr-meta td{padding:1mm 0;color:#555;font-size:9pt}.tr-meta b{color:' . $D . '}
.tr-t{border:0.3mm solid #444;margin-top:3mm}
.tr-t th{background:#fff;color:' . $D . ';font-size:8pt;letter-spacing:1pt;text-align:left;padding:2mm 3mm;border-bottom:0.4mm solid #444}
.tr-t td{padding:' . $rowPad . 'mm 3mm;border-bottom:0.2mm solid #ddd}
.tr-t .c{width:30mm;white-space:nowrap}.tr-t .m{width:20mm;text-align:center;font-weight:bold}.tr-t .g{width:30mm;text-align:center;font-weight:bold}
.tr-t th.m,.tr-t th.g{text-align:center}
.tr-t .det{font-size:6.5pt;color:#666;font-weight:normal}
.tr-t tr.avg td{background:#eef6ea;font-weight:bold;font-size:11pt;border-top:0.3mm solid #444;padding:2mm 3mm}
.tr-rec{border:0.3mm solid #444;border-top:0;padding:2mm 4mm;font-size:9pt}.tr-rec b{color:' . $G . ';letter-spacing:.6pt;margin-right:3mm}
.tr-key{font-size:8pt;color:#333;line-height:1.45}.tr-key b{display:inline-block;font-size:9.5pt;border-bottom:0.5mm solid ' . $G . ';margin-bottom:1mm;color:' . $D . '}
.tr-foot{margin-top:2mm}
.tr-foot td{vertical-align:top;padding:0}
.tr-small{font-size:7.5pt;color:#666}
.tr-end{position:absolute;left:0;top:284mm;width:210mm;text-align:center;font-size:7.5pt;color:#666}
.tr-end i{display:block;font-weight:bold;color:' . $D . ';font-size:8pt;margin-bottom:1mm}
.tr-draft{position:absolute;left:0;top:120mm;width:210mm;text-align:center;font-size:80pt;font-weight:bold;color:rgba(200,200,200,.45)}';

        $body = '<div class="tr-sheet">'
            . ($op > 0 && $logo ? '<div class="tr-wm"><img src="' . $logo . '" style="width:140mm" alt=""></div>' : '')
            . '<div class="tr-no"><b>' . $e($d['no'] ?: 'DRAFT') . '</b><small>Transcript number</small></div>'
            . '<div class="tr-in"><div class="tr-h">' . ($logo ? '<img src="' . $logo . '" style="height:17mm" alt="Dazu Training Institute">' : '')
            . '<div class="n">DAZU TRAINING INSTITUTE</div>'
            . '<div class="a">Nairobi and Thika campuses · Online  |  ' . $e($host) . '</div>'
            . '<div class="t">' . $e(mb_strtoupper(course_qualification_word($d['course_title']))) . ' ACADEMIC TRANSCRIPT*</div></div>'
            . '<table class="tr-names" style="margin-top:5mm"><tr><td style="padding-right:8mm"><div class="v">' . $e($d['surname']) . '</div></td><td style="padding-left:8mm"><div class="v">' . $e($d['other_names'] ?: ' ') . '</div></td></tr>'
            . '<tr><td class="l" style="padding-right:8mm">Surname</td><td class="l" style="padding-left:8mm">Other Names</td></tr></table>'
            . '<table class="tr-meta" style="margin-top:2mm"><tr><td>Course: <b>' . $e($d['course_title']) . '</b></td><td>Campus: <span style="color:' . $D . '">' . $e($d['campus']) . '</span></td></tr>'
            . '<tr><td>Student No: <span style="color:' . $D . '">' . $e($d['student_no']) . '</span></td><td>Date of admission: <span style="color:' . $D . '">' . $e($admitted) . '</span></td></tr></table>'
            . '<table class="tr-t"><thead><tr><th class="c">CODE</th><th>SUBJECT</th><th class="m">MARK</th><th class="g">GRADE</th></tr></thead><tbody>' . $rows
            . '<tr class="avg"><td></td><td>COURSE AVERAGE</td><td class="m">' . $e($avg) . '</td><td class="g" style="color:' . $wordColor($d['average_word']) . '">' . $e($d['average_word']) . '</td></tr></tbody></table>'
            . '<div class="tr-rec"><b>RECOMMENDATION</b>' . $e($d['recommendation']) . '</div>'
            . '<table class="tr-foot"><tr><td style="width:55%;padding-top:3mm"><div class="tr-key"><b>Key to the grading system</b><br>Distinction = 70 – 100%<br>Credit = 60 – 69%<br>Pass = 50 – 59%<br>Fail = 49% and below</div></td>'
            . '<td style="width:45%;padding-top:4mm">'
            . ($qr ? '<table><tr><td style="width:24mm"><img src="' . $qr . '" style="width:22mm;height:22mm" alt=""></td><td class="tr-small" style="vertical-align:middle;padding-left:3mm">Scan to verify this transcript.<br>' . $e($host) . '/verify</td></tr></table>'
                   : '<div class="tr-small">The QR code, the official stamp and the director\'s signature are added when the transcript is released.</div>')
            . '</td></tr><tr><td style="vertical-align:bottom;padding-top:2mm">' . ($sig ? '<img src="' . $sig . '" style="max-height:15mm;max-width:55mm" alt="">' : '<div style="height:15mm"></div>')
            . '<div style="border-top:0.3mm dotted #555;width:80mm;padding-top:1mm"><b>Signed</b></div>'
            . '<div><b>' . $e($d['director_name'] ? $d['director_name'] . ', ' : '') . 'Director, Dazu Training Institute</b></div>'
            . '<div>Date: ' . $e($date ?: 'on release') . '</div></td>'
            . '<td style="padding-top:2mm"><table><tr><td style="width:32mm">' . ($stamp ? '<img src="' . $stamp . '" style="width:30mm" alt="Official stamp">' : '<div style="height:30mm;width:30mm;border:0.3mm dashed #bbb;border-radius:50%"></div>') . '</td>'
            . '<td style="vertical-align:middle;padding-left:3mm"><b>Registrar / Academic Office</b><br>Official stamp</td></tr></table></td></tr></table></div>'
            . '<div class="tr-end"><i>* This transcript is issued without any erasures or alterations</i>Dazu Training Institute' . ($phone ? ' · ' . $e($phone) : '') . ' · ' . $e($host) . '</div>'
            . ($d['draft'] ? '<div class="tr-draft">DRAFT</div>' : '')
            . '</div>';

        if (!$forPdf) return '<style>' . $css . '</style>' . $body;
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 portrait;margin:0}body{margin:0}' . $css . '</style></head><body>' . $body . '</body></html>';
    }

    /** "Diploma in Data Science" -> "Diploma"; anything else -> "Course". */
    function course_qualification_word(string $title): string
    {
        foreach (['Higher Diploma', 'Diploma', 'Certificate', 'Degree'] as $w) if (stripos($title, $w) !== false) return $w;
        return 'Course';
    }

    function tr_pdf(array $d): string
    {
        $opts = new \Dompdf\Options();
        $opts->setIsRemoteEnabled(false);
        $opts->setIsHtml5ParserEnabled(true);
        $opts->setIsPhpEnabled(false);
        $opts->setDpi(150);
        $opts->setDefaultFont('Helvetica');
        $dompdf = new \Dompdf\Dompdf($opts);
        $dompdf->loadHtml(tr_html($d, true), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
