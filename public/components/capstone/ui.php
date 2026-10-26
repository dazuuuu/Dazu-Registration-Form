<?php
// Shared look and small helpers for the Capstone pages (tutor and student). Include once (the boot files do).
// Builds on the Assignments look (components/assignments/ui.php): same pills, cards and colours.

if (!function_exists('cap_chip')) {
    /** Status pill: symbol + word, never colour alone. */
    function cap_chip(string $state, ?string $text = null): string
    {
        static $map = [
            'draft'            => ['Draft', 'fa-pen', 'st-locked'],
            'scheduled'        => ['Scheduled', 'fa-clock', 'st-progress'],
            'open'             => ['Open', 'fa-lock-open', 'st-open'],
            'closed'           => ['Closed', 'fa-lock', 'st-closed'],
            'not_started'      => ['Not started', 'fa-circle', 'st-locked'],
            'in_progress'      => ['In progress', 'fa-pen-to-square', 'st-progress'],
            'behind'           => ['Behind', 'fa-triangle-exclamation', 'st-closed'],
            'to_mark'          => ['To mark', 'fa-clipboard-check', 'st-submitted'],
            'returned'         => ['Returned', 'fa-rotate-left', 'st-returned'],
            'final_try'        => ['Final try', 'fa-flag-checkered', 'st-extension'],
            'marked'           => ['Marked', 'fa-circle-check', 'st-graded'],
            'awaiting_payment' => ['Waiting for retake fee', 'fa-wallet', 'st-returned'],
            'held'             => ['Held', 'fa-lock', 'st-locked'],
        ];
        [$label, $icon, $cls] = $map[$state] ?? [$state, 'fa-circle', 'st-open'];
        return '<span class="asg-chip ' . $cls . '"><i class="fas ' . $icon . '" aria-hidden="true"></i>' . cap_e($text ?? $label) . '</span>';
    }

    /** On time / Late / Missing / Not yet / Approved word for a milestone cell. */
    function cap_ms_chip(string $word): string
    {
        $cls = ['On time' => 'st-graded', 'Approved' => 'st-graded', 'Late' => 'st-returned', 'Missing' => 'st-closed', 'Not yet' => 'st-locked'][$word] ?? 'st-locked';
        $icon = ['On time' => 'fa-check', 'Approved' => 'fa-circle-check', 'Late' => 'fa-clock', 'Missing' => 'fa-xmark', 'Not yet' => 'fa-minus'][$word] ?? 'fa-minus';
        return '<span class="asg-chip ' . $cls . '"><i class="fas ' . $icon . '" aria-hidden="true"></i>' . cap_e($word) . '</span>';
    }

    /** Link-check result for a stored item: "Link works" or "Needs access", as in the Assignments update. */
    function cap_link_chip(?string $status): string
    {
        if ($status === null || $status === '') return '';
        $bad = LinkRules::isBad($status);
        if ($status === 'not_checked' || $status === 'unknown') return '<span class="asg-chip st-locked"><i class="fas fa-circle-question"></i>Not checked</span>';
        return $bad ? '<span class="asg-chip lc-bad"><i class="fas fa-triangle-exclamation"></i>Needs access</span>'
                    : '<span class="asg-chip st-graded"><i class="fas fa-link"></i>Link works</span>';
    }

    function cap_file_url(string $kind, int $id): string
    {
        return url('capstone/file.php?t=' . $kind . '&id=' . $id);
    }

    function cap_pretty_marks($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') ?: '0';
    }
}

$extra_css = ($extra_css ?? '') . <<<'CSS'
<style>
.cap-banner{background:#373435;color:#fff;font-weight:800;letter-spacing:.08em;text-align:center;padding:10px 14px;border-radius:12px 12px 0 0;font-size:.85rem}
.cap-example{border:2px solid #373435;border-radius:14px;overflow:hidden;background:#fff;margin-bottom:12px}
.cap-example .body{padding:14px 16px}
.cap-rules{background:#fff8e6;border:1px solid #f1d38a;border-radius:14px;padding:12px 16px;font-size:.88rem}
.cap-rules strong{color:#7a4b00}
.cap-bands span{display:inline-block;margin:2px 4px 2px 0;padding:3px 10px;border-radius:999px;font-size:.74rem;font-weight:700;background:#eef8e9;color:#36851b}
.cap-bands span.f{background:#fbe3e1;color:#b42318}
.cap-part{display:grid;grid-template-columns:1fr 110px;gap:8px;align-items:start;margin-bottom:12px}
.cap-total{font-size:2rem;font-weight:800;line-height:1}
.cap-ms{display:grid;grid-template-columns:1.6fr 1fr 1.1fr 90px 34px;gap:8px;align-items:center;margin-bottom:8px}
@media(max-width:767.98px){.cap-ms{grid-template-columns:1fr 1fr}.cap-part{grid-template-columns:1fr}}
.cap-sticky{position:sticky;bottom:calc(84px + env(safe-area-inset-bottom,0px));z-index:6;background:linear-gradient(to top,#fff 75%,rgba(255,255,255,0));padding:10px 0 4px}
@media(min-width:992px){.cap-sticky{position:static;background:none;padding:0}}
.cap-hist li{margin-bottom:6px}
.cap-evidence td,.cap-evidence th{font-size:.82rem;vertical-align:middle}
.cap-track th,.cap-track td{font-size:.76rem;vertical-align:middle;white-space:normal;padding:.55rem .4rem}
.cap-track .asg-chip{font-size:.58rem;padding:3px 8px}
.cap-track thead th{font-size:.66rem;line-height:1.2}
.cap-locked{filter:grayscale(.3);opacity:.9}
</style>
CSS;
