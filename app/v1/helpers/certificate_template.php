<?php
// app/v1/helpers/certificate_template.php
// The certificate's text lines, editable in Admin Settings → Certificate (one template for every certificate).
// Each line: text (with {placeholders} and **bold**), font, size, colour, bold, italic, letter spacing, distance from the top, shown or hidden.
// Left/width of each line stay fixed so the lines keep their place on the artwork. Saved as JSON in site_settings 'cert_template';
// every save is written to certificate_template_events (migration 097).

if (!function_exists('cert_tpl_defaults')) {

    /** Fonts the PDF maker has built in. */
    function cert_tpl_fonts(): array
    {
        return [
            'times'       => ['Times (serif)', "'Times New Roman', Times, serif"],
            'helvetica'   => ['Helvetica (sans)', 'Helvetica, Arial, sans-serif'],
            'courier'     => ['Courier (typewriter)', "Courier, 'Courier New', monospace"],
            'dejavusans'  => ['DejaVu Sans', "'DejaVu Sans', sans-serif"],
            'dejavuserif' => ['DejaVu Serif', "'DejaVu Serif', serif"],
        ];
    }

    /** Placeholders the text may use. */
    function cert_tpl_placeholders(): array
    {
        return [
            '{name}' => 'Student name', '{course}' => 'Course', '{duration}' => 'Length, e.g. "6-month"', '{cert_no}' => 'Certificate number',
            '{date}' => 'Date of issue', '{tutor}' => 'Tutor name', '{tutor_title}' => 'Tutor title', '{director}' => 'Director name',
            '{director_title}' => 'Director title', '{verify_host}' => 'Website to verify on', '{extra}' => 'Extra programme text typed on the certificate',
        ];
    }

    /** Every line with the look the certificate had before the editor (left/width are fixed, not editable). */
    function cert_tpl_defaults(): array
    {
        $D = '#38373a'; $G = '#5c9e3a';
        $l = fn($label, $text, $left, $width, $top, $size, $color, $bold, $italic, $sp, $align = 'center') => compact('label', 'text', 'left', 'width', 'top', 'size', 'color', 'bold', 'italic', 'sp', 'align')
            + ['font' => 'times', 'show' => true];
        return [
            'title'          => $l('Title', 'CERTIFICATE', 0, 297, 35, 56, $D, true, false, 2),
            'subtitle'       => $l('Subtitle', 'OF COMPLETION', 0, 297, 55, 22, $G, true, false, 8),
            'presented'      => $l('Presented to line', 'THIS CERTIFICATE IS PROUDLY PRESENTED TO', 0, 297, 70.5, 11, $D, true, false, 4),
            'name'           => $l('Student name (shrinks for long names)', '{name}', 55, 187, 78, 44, $G, true, true, 0),
            'body'           => $l('Programme text', 'Has successfully completed a {duration} programme of study in the **{course}** at Dazu Training Institute{extra}', 66, 165, 104, 13.5, $D, false, false, 0),
            'badge1'         => $l('Badge, line 1', 'CERTIFIED', 240.4, 40, 37.4, 6.5, '#ffffff', true, false, 1.2),
            'badge2'         => $l('Badge, line 2', 'ORIGINAL', 240.4, 40, 43, 11.5, '#ffffff', true, false, 0),
            'badge3'         => $l('Badge, line 3', 'DAZU', 240.4, 40, 50.6, 7, '#ffffff', true, false, 2.5),
            'qr_label'       => $l('Above the QR code', 'Valid Certificate ID', 17.8, 42, 102, 10, $D, false, true, 0, 'left'),
            'qr_no'          => $l('Under the QR code', '{cert_no}', 17.8, 60, 142.5, 11, $D, true, false, 0, 'left'),
            'qr_verify'      => $l('Verify text', "Scan to verify, or visit\n{verify_host}", 17.8, 60, 147.6, 9, $D, false, true, 0, 'left'),
            'tutor_name'     => $l('Tutor name', '{tutor}', 78, 77, 171, 14, $D, true, false, 0),
            'tutor_title'    => $l('Tutor title', '{tutor_title}', 78, 77, 176.6, 9, $D, false, false, 1.5),
            'director_name'  => $l('Director name', '{director}', 148.5, 77, 171, 14, $D, true, false, 0),
            'director_title' => $l('Director title', '{director_title}', 148.5, 77, 176.6, 9, $D, false, false, 1.5),
            'footer'         => $l('Bottom line', 'Certificate No: {cert_no}     |     Date of issue: {date}', 66, 165, 186.4, 12, $D, false, false, 0),
        ];
    }

    /** The saved template merged over the defaults ($override: an unsaved one, for the live preview). */
    function cert_tpl(?array $override = null): array
    {
        static $saved = null;
        if ($override === null) {
            if ($saved === null) {
                $saved = [];
                try {
                    if (!class_exists('SettingModel')) require_once __DIR__ . '/../models/SettingModel.php';
                    if (!class_exists('Models\\Connection')) require_once __DIR__ . '/../models/Connection.php';
                    $saved = json_decode((string) (new SettingModel(\Models\Connection::getInstance()->getConnection()))->get('cert_template'), true) ?: [];
                } catch (Throwable $e) {
                }
            }
            $override = $saved;
        }
        $out = cert_tpl_defaults();
        foreach ($out as $k => $def) {
            if (isset($override[$k]) && is_array($override[$k])) $out[$k] = cert_tpl_clean($override[$k], $def);
        }
        return $out;
    }

    /** One line from a form or the saved JSON, checked; anything wrong falls back to the default. */
    function cert_tpl_clean(array $in, array $def): array
    {
        $o = $def;
        if (isset($in['text'])) $o['text'] = mb_substr(str_replace("\r", '', (string) $in['text']), 0, 400);
        if (isset($in['font']) && isset(cert_tpl_fonts()[$in['font']])) $o['font'] = $in['font'];
        if (isset($in['size']) && is_numeric($in['size'])) $o['size'] = max(4, min(96, round((float) $in['size'], 1)));
        if (isset($in['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string) $in['color'])) $o['color'] = strtolower($in['color']);
        if (isset($in['sp']) && is_numeric($in['sp'])) $o['sp'] = max(0, min(20, round((float) $in['sp'], 1)));
        if (isset($in['top']) && is_numeric($in['top'])) $o['top'] = max(0, min(205, round((float) $in['top'], 1)));
        foreach (['bold', 'italic', 'show'] as $b) if (array_key_exists($b, $in)) $o[$b] = (bool) $in[$b];
        return $o;
    }

    /** What is stored: only the editable fields. */
    function cert_tpl_from_post(array $post): array
    {
        $out = [];
        foreach (cert_tpl_defaults() as $k => $def) {
            $in = (array) ($post['tpl'][$k] ?? []);
            $in += ['bold' => 0, 'italic' => 0, 'show' => 0];   // unticked boxes are not sent
            $c = cert_tpl_clean($in, $def);
            $out[$k] = array_intersect_key($c, array_flip(['text', 'font', 'size', 'color', 'bold', 'italic', 'sp', 'top', 'show']));
        }
        return $out;
    }

    /**
     * Save (null = back to the original design / back to the template) and log it.
     * $certId 0 = the template for every certificate; otherwise that one certificate's own edits (certificates.template_json).
     */
    function cert_tpl_save(PDO $db, ?array $tpl, int $actor, int $certId = 0): void
    {
        $new = $tpl === null ? null : json_encode($tpl, JSON_UNESCAPED_UNICODE);
        if ($certId) {
            $st = $db->prepare('SELECT template_json FROM certificates WHERE id = ?');
            $st->execute([$certId]);
            $old = $st->fetchColumn() ?: null;
            $db->prepare('UPDATE certificates SET template_json = ? WHERE id = ?')->execute([$new, $certId]);
        } else {
            if (!class_exists('SettingModel')) require_once __DIR__ . '/../models/SettingModel.php';
            $m = new SettingModel($db);
            $old = $m->get('cert_template');
            $m->set('cert_template', $new);
        }
        try {
            $db->prepare('INSERT INTO certificate_template_events (actor_user_id, certificate_id, event, old_json, new_json) VALUES (?, ?, ?, ?, ?)')
               ->execute([$actor, $certId ?: null, $tpl === null ? 'reset' : 'saved', $old, $new]);
        } catch (Throwable $e) {
            error_log('certificate_template_events: ' . $e->getMessage());
        }
    }

    /** The text of a line as HTML: escaped, placeholders filled in (values escaped too), **bold**, new lines. */
    function cert_tpl_text(string $text, array $vars): string
    {
        $h = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $rep = [];
        // values are already HTML; in the editor each one is a chip that stays a placeholder when saved
        $live = !empty($GLOBALS['cert_tpl_live']);
        foreach ($vars as $k => $v) $rep[htmlspecialchars($k, ENT_QUOTES, 'UTF-8')] = $live ? '<span class="ph" contenteditable="false" data-ph="' . $k . '">' . $v . '</span>' : $v;
        $h = strtr($h, $rep);
        $h = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $h);
        $h = preg_replace_callback('/ {2,}/', fn($m) => str_repeat('&nbsp;', strlen($m[0])), $h);   // keep extra spaces
        return nl2br($h);
    }

    /** One positioned line. $sizeOverride: the shrunk name size. */
    function cert_tpl_div(array $el, string $html, ?float $sizeOverride = null, ?float $topOverride = null, string $key = ''): string
    {
        // The editor's live preview ($GLOBALS['cert_tpl_live']) gets every line, hidden ones too, tagged so the page can change them as you type.
        $live = !empty($GLOBALS['cert_tpl_live']);
        if (!$live && (!$el['show'] || trim(strip_tags($html)) === '')) return '';
        $size = $sizeOverride ?? $el['size'];
        return '<div' . ($live ? ' data-k="' . $key . '"' : '') . ' style="' . ($live && !$el['show'] ? 'display:none;' : '') . 'position:absolute;left:' . $el['left'] . 'mm;width:' . $el['width'] . 'mm;top:' . ($topOverride ?? $el['top']) . 'mm;'
            . 'text-align:' . $el['align'] . ';font-family:' . cert_tpl_fonts()[$el['font']][1] . ';font-size:' . $size . 'pt;color:' . $el['color'] . ';'
            . 'font-weight:' . ($el['bold'] ? 'bold' : 'normal') . ';font-style:' . ($el['italic'] ? 'italic' : 'normal') . ';'
            . 'letter-spacing:' . $el['sp'] . 'pt;line-height:' . ($el['size'] >= 16 ? 1 : 1.35) . ';' . ($sizeOverride !== null ? 'white-space:nowrap;' : '') . '">' . $html . '</div>';
    }
}
