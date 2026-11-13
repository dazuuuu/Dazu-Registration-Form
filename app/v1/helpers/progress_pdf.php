<?php
// app/v1/helpers/progress_pdf.php — Student Progress cards as a PDF (dompdf), one student per page, in the Dazu look with the logo
// and the date the PDF was made. The status word and the system comment are on the card; PRIVATE NOTES NEVER ARE.
// Many students: the marks come from the database in batches of SP_PDF_BATCH students (one query set per batch), and each batch's pages are
// appended to a temporary stream as they are built, so memory grows with the pages, not with the data. dompdf then lays the pages out once.

require_once __DIR__ . '/dompdf.php';
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/../services/StudentProgress.php';
require_once __DIR__ . '/../services/SelfStudyProgress.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!function_exists('sp_pdf_build')) {
    define('SP_PDF_BATCH', 40);

    function spp_e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    /** The student's photo as a small square JPEG data URI, or null (initials are used instead). */
    function spp_photo(array $c): ?string
    {
        $file = $c['photo']['file'] ?? null;
        if (!$file || !function_exists('imagecreatefromstring')) return null;
        $im = @imagecreatefromstring((string) @file_get_contents($file));
        if (!$im) return null;
        $w = imagesx($im); $h = imagesy($im); $side = min($w, $h);
        $out = imagecreatetruecolor(110, 110);
        imagecopyresampled($out, $im, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 110, 110, $side, $side);
        ob_start();
        imagejpeg($out, null, 82);
        $jpg = (string) ob_get_clean();
        imagedestroy($im); imagedestroy($out);
        return 'data:image/jpeg;base64,' . base64_encode($jpg);
    }

    /** Colours per status: [main, dark, light]. */
    function spp_colors(string $key): array
    {
        return ['good' => ['#048C44', '#037A3B', '#E3F3EA'], 'slow' => ['#F59E0B', '#B45309', '#FEF3C7'], 'bad' => ['#DC2626', '#B91C1C', '#FEE2E2']][$key];
    }

    /** One card = one page. */
    /** A tiny course picture for the Self-study section (48 x 27 px JPEG), or null. */
    function spp_cover(array $cover): ?string
    {
        $rel = $cover['cover_400'] ?: ($cover['cover_800'] ?: $cover['cover_original']);
        $file = $rel ? dirname(__DIR__, 3) . '/public/' . ltrim((string) $rel, '/') : null;
        if (!$file || !is_file($file) || !function_exists('imagecreatefromstring')) return null;
        $im = @imagecreatefromstring((string) @file_get_contents($file));
        if (!$im) return null;
        $out = imagecreatetruecolor(96, 54);
        imagecopyresampled($out, $im, 0, 0, 0, 0, 96, 54, imagesx($im), imagesy($im));
        ob_start(); imagejpeg($out, null, 80); $jpg = (string) ob_get_clean();
        imagedestroy($im); imagedestroy($out);
        return 'data:image/jpeg;base64,' . base64_encode($jpg);
    }

    /** The Self-study section of the card (same words and colours as the screen). Empty when the student has no self-study course. Private tutor notes are never part of it. */
    function spp_selfstudy_html(array $rows): string
    {
        if (!$rows) return '';
        $col = ['working' => ['#E3F3EA', '#037A3B', '#048C44'], 'slowing' => ['#FEF3C7', '#B45309', '#F59E0B'], 'notworking' => ['#FEE2E2', '#B91C1C', '#DC2626'], 'notstarted' => ['#eef0ee', '#4a4f53', '#9ca3af']];
        $h = '<div style="margin-top:6mm;font-size:9pt;font-weight:bold;letter-spacing:1pt;color:#6b7280;">SELF-STUDY <span style="background:#048C44;color:#ffffff;font-size:6.5pt;padding:0.3mm 1.8mm;border-radius:2mm;letter-spacing:0;">NEW</span></div>'
            . '<div style="font-size:7.5pt;color:#6b7280;margin:0.5mm 0 1mm;">Shown here only. It does not change marks or the course average.</div><table style="width:100%;border-collapse:collapse;">';
        foreach (array_slice($rows, 0, 6) as $r) {
            [$light, $dark, $main] = $col[$r['status']['key']] ?? $col['notstarted'];
            $pic = spp_cover($r['cover']);
            $pct = max(0, min(100, (int) $r['percent']));
            $h .= '<tr><td style="width:38%;padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;vertical-align:top;">'
                . '<table style="border-collapse:collapse;"><tr><td style="width:9mm;padding-right:2mm;vertical-align:middle;">' . ($pic ? '<img src="' . $pic . '" style="width:8.5mm;height:4.8mm;" />' : '') . '</td>'
                . '<td style="font-size:9pt;font-weight:bold;vertical-align:middle;">' . spp_e($r['title']) . '</td></tr></table>'
                . '<div style="font-size:7.5pt;color:#6b7280;margin-top:0.6mm;">' . (int) $r['modules_passed'] . ' of ' . (int) $r['modules_total'] . ' modules passed · ' . $pct . '%</div>'
                . '<div style="height:1.4mm;background:#eceff1;border-radius:1mm;margin-top:0.8mm;"><div style="height:1.4mm;width:' . $pct . '%;background:' . $main . ';border-radius:1mm;"></div></div></td>'
                . '<td style="width:24%;padding:1.6mm 2mm;border-bottom:0.2mm solid #e5e7eb;vertical-align:top;font-size:8pt;"><div style="color:#6b7280;">Latest quiz</div><b>' . ($r['latest_quiz'] !== null ? (int) $r['latest_quiz'] . '%' : 'No quiz yet') . '</b>'
                . '<div style="color:#6b7280;margin-top:0.8mm;">Exams</div><b>' . spp_e($r['exams']) . '</b></td>'
                . '<td style="padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;vertical-align:top;font-size:8pt;"><span style="background:' . $light . ';color:' . $dark . ';border:0.3mm solid ' . $main . ';border-radius:3mm;padding:0.6mm 2.6mm;font-weight:bold;">' . spp_e($r['status']['word']) . '</span>'
                . '<div style="margin-top:1mm;color:#374151;">' . spp_e($r['status']['reason']) . '</div></td></tr>';
        }
        if (count($rows) > 6) $h .= '<tr><td colspan="3" style="font-size:7.5pt;color:#6b7280;padding:1mm 0;">and ' . (count($rows) - 6) . ' more self-study course' . (count($rows) - 6 === 1 ? '' : 's') . '</td></tr>';
        return $h . '</table>';
    }

    function spp_card_html(array $c, ?string $logo, string $made, string $madeBy, bool $last, array $selfStudy = []): string
    {
        [$main, $dark, $light] = spp_colors($c['status']['key']);
        $photo = spp_photo($c);
        $ava = $photo
            ? '<img src="' . $photo . '" style="width:17mm;height:17mm;border-radius:50%;" />'
            : '<div style="width:17mm;height:17mm;border-radius:50%;background:#E3F3EA;color:#037A3B;text-align:center;line-height:17mm;font-size:16pt;font-weight:bold;">' . spp_e(sp_initials($c['full_name'])) . '</div>';
        $w = max(0, min(100, round($c['actual_pct'], 1)));
        $should = $c['should_be'];
        $bar = '<div style="position:relative;height:3mm;background:#eceff1;border-radius:2mm;">'
            . '<div style="position:absolute;left:0;top:0;height:3mm;width:' . $w . '%;background:' . $main . ';border-radius:2mm;"></div>'
            . ($should !== null ? '<div style="position:absolute;left:' . round($should, 1) . '%;top:-1.2mm;height:5.4mm;width:0.5mm;background:#111827;"></div>' : '')
            . '</div>';
        $shouldText = $should === null ? 'Course start date or length not set' : 'Should be at ' . (int) round($should) . '% today';

        $stat = fn(string $v, string $l) => '<td style="width:25%;padding:2.5mm 3mm;border:0.3mm solid #e5e7eb;background:#f8faf9;"><div style="font-size:15pt;font-weight:bold;">' . $v . '</div><div style="font-size:8pt;color:#6b7280;">' . $l . '</div></td>';
        $missing = (int) $c['missing'];
        $stats = '<table style="width:100%;border-collapse:separate;border-spacing:2mm 0;margin:5mm -2mm 0;"><tr>'
            . $stat($c['average'] === null ? 'no mark yet' : spp_e(sp_pct($c['average'])), 'Average so far')
            . $stat((int) $c['handed'] . ' of ' . (int) $c['total'], 'Handed in · ' . $missing . ' missing')
            . $stat($c['project_average'] === null ? 'no mark yet' : spp_e(sp_pct($c['project_average'])), 'Monthly projects average')
            . $stat(spp_e(sp_last_text_pdf($c)), 'Last hand-in')
            . '</tr></table>';

        $subs = '';
        $sl = $c['subject_lines'];
        foreach ($sl['lines'] as $l) {
            $sw = $l['total'] ? round($l['handed'] / $l['total'] * 100) : 0;
            $subs .= '<tr><td style="padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;width:42%;font-size:10pt;">' . spp_e($l['name']) . '<div style="font-size:8pt;color:#6b7280;">' . (int) $l['handed'] . ' of ' . (int) $l['total'] . ' handed in</div></td>'
                . '<td style="padding:1.6mm 2mm;border-bottom:0.2mm solid #e5e7eb;width:34%;"><div style="height:1.8mm;background:#eceff1;border-radius:1mm;"><div style="height:1.8mm;width:' . $sw . '%;background:' . $main . ';border-radius:1mm;"></div></div></td>'
                . '<td style="padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;text-align:right;font-size:10pt;font-weight:bold;">' . spp_e($l['text']) . '</td></tr>';
        }
        if (!$sl['lines'] && !$sl['not_started']) $subs = '<tr><td style="font-size:10pt;color:#6b7280;padding:2mm 0;">No subjects set up for this course yet.</td></tr>';
        if ($sl['not_started']) $subs .= '<tr><td colspan="3" style="font-size:9.5pt;color:#6b7280;font-style:italic;padding:2mm 0;">' . (int) $sl['not_started'] . ' more subject' . ($sl['not_started'] === 1 ? '' : 's') . ' not started yet</td></tr>';

        $proj = '';
        foreach ($c['projects']['items'] as $p) {
            $proj .= '<tr><td style="padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;font-size:10pt;">' . spp_e($p['label']) . '<div style="font-size:8pt;color:#6b7280;">' . spp_e($p['title']) . '</div></td>'
                . '<td style="padding:1.6mm 0;border-bottom:0.2mm solid #e5e7eb;text-align:right;font-size:10pt;' . ($p['state'] === 'missing' ? 'color:#B91C1C;' : '') . 'font-weight:bold;">' . spp_e($p['text']) . '</td></tr>';
        }
        if ($c['projects']['items']) $proj .= '<tr><td style="padding:2mm 0;font-size:10pt;font-weight:bold;">Project average</td><td style="text-align:right;font-size:10pt;font-weight:bold;">' . ($c['projects']['average'] === null ? 'no mark yet' : spp_e(sp_pct($c['projects']['average']))) . '</td></tr>';
        else $proj = '<tr><td style="font-size:10pt;color:#6b7280;padding:2mm 0;">No monthly projects given yet.</td></tr>';

        $reasons = '';
        foreach ($c['status']['reasons'] as $r) $reasons .= '<li>' . spp_e($r) . '</li>';

        return '<div style="' . ($last ? '' : 'page-break-after:always;') . 'position:relative;">'
            . '<div style="height:3mm;background:#048C44;margin:0 -15mm;"></div>'
            . '<table style="width:100%;margin-top:6mm;border-collapse:collapse;"><tr>'
            . '<td>' . ($logo ? '<img src="' . $logo . '" style="height:13mm;" />' : '<b style="font-size:14pt;color:#048C44;">DAZU TRAINING INSTITUTE</b>') . '</td>'
            . '<td style="text-align:right;vertical-align:middle;"><div style="font-size:15pt;font-weight:bold;color:#048C44;letter-spacing:1pt;">STUDENT PROGRESS CARD</div>'
            . '<div style="font-size:8.5pt;color:#6b7280;">Made on ' . spp_e($made) . '</div></td></tr></table>'
            . '<div style="border-top:0.5mm solid #048C44;margin:4mm 0 5mm;"></div>'
            . '<table style="width:100%;border-collapse:collapse;"><tr><td style="width:20mm;vertical-align:middle;">' . $ava . '</td>'
            . '<td style="vertical-align:middle;"><div style="font-size:16pt;font-weight:bold;">' . spp_e($c['full_name']) . '</div>'
            . '<div style="font-size:10pt;color:#6b7280;">' . spp_e($c['registration_no']) . ' · ' . spp_e($c['course_title']) . '</div></td>'
            . '<td style="text-align:right;vertical-align:middle;"><span style="background:' . $light . ';color:' . $dark . ';border:0.4mm solid ' . $main . ';border-radius:4mm;padding:1.5mm 4mm;font-weight:bold;font-size:11pt;">' . spp_e($c['status']['word']) . '</span></td></tr></table>'
            . '<div style="margin-top:6mm;font-size:9pt;color:#6b7280;">Progress through the course</div><div style="margin-top:1.5mm;">' . $bar . '</div>'
            . '<div style="margin-top:2mm;font-size:9pt;color:#374151;">' . spp_e($shouldText) . '</div>'
            . $stats
            . spp_selfstudy_html($selfStudy)
            . '<div style="margin-top:6mm;font-size:9pt;font-weight:bold;letter-spacing:1pt;color:#6b7280;">SUBJECTS</div><table style="width:100%;border-collapse:collapse;margin-top:1mm;">' . $subs . '</table>'
            . '<div style="margin-top:6mm;font-size:9pt;font-weight:bold;letter-spacing:1pt;color:#6b7280;">MONTHLY PROJECTS</div><table style="width:100%;border-collapse:collapse;margin-top:1mm;">' . $proj . '</table>'
            . '<div style="margin-top:6mm;background:' . $light . ';border-left:1.2mm solid ' . $main . ';padding:3mm 4mm;">'
            . '<div style="font-weight:bold;color:' . $dark . ';font-size:10.5pt;">' . spp_e($c['status']['word']) . '</div>'
            . ($reasons !== '' ? '<ul style="margin:1.5mm 0 1.5mm 5mm;padding:0;font-size:10pt;">' . $reasons . '</ul>' : '')
            . '<div style="font-size:10pt;"><b>What to do:</b> ' . spp_e($c['status']['action']) . '</div></div>'
            . '<div style="margin-top:8mm;border-top:0.3mm solid #e5e7eb;padding-top:2mm;font-size:8pt;color:#9ca3af;text-align:center;">Dazu Training Institute · made on ' . spp_e($made) . ' by ' . spp_e($madeBy) . '</div>'
            . '</div>';
    }

    function sp_last_text_pdf(array $c): string
    {
        return $c['last_handin'] ? sp_local($c['last_handin'], 'j M Y') : 'Nothing yet';
    }

    /**
     * The PDF bytes for these students, in this order. The caller has already checked the viewer may see every one of them.
     * @param int[] $userIds
     */
    function sp_pdf_build(PDO $db, array $viewer, array $userIds, ?int $now = null): string
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        if (!dazu_load_dompdf()) throw new RuntimeException('The PDF library is not installed.');
        $now = $now ?? time();
        $logoFile = brand_file('logo');
        $logo = $logoFile ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoFile)) : null;
        $made = (new DateTime('@' . $now))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j F Y, H:i');
        $fh = fopen('php://temp/maxmemory:16777216', 'w+b');   // stays in memory up to 16 MB, then spills to a temporary file
        $head = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{size:A4 portrait;margin:0 15mm 12mm 15mm}body{font-family:Helvetica,Arial,sans-serif;color:#1f2937;margin:0}</style></head><body>';
        fwrite($fh, $head);
        $total = count($userIds);
        $done = 0;
        foreach (array_chunk($userIds, SP_PDF_BATCH) as $chunk) {
            $students = sp_students($db, $viewer, ['ids' => $chunk]);
            $cards = sp_compute($db, $students, true, $now);          // one set of queries for the whole batch
            $selfStudy = ssd_cards($db, $viewer, $chunk, $now);       // and one set for the Self-study sections of the whole batch
            foreach ($chunk as $uid) {
                if (!isset($cards[$uid])) continue;
                $done++;
                fwrite($fh, spp_card_html($cards[$uid], $logo, $made, $viewer['name'], $done >= $total, $selfStudy[$uid] ?? []));
            }
            unset($students, $cards);
        }
        fwrite($fh, '</body></html>');
        rewind($fh);
        $html = (string) stream_get_contents($fh);
        fclose($fh);
        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $opt->set('isHtml5ParserEnabled', true);
        $opt->set('defaultFont', 'Helvetica');
        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }
}
