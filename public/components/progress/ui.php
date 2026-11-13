<?php
// public/components/progress/ui.php — drawing helpers for Student Progress (used by the tutor and admin pages).
// Colours: logo green #048C44 (dark #037A3B, light #E3F3EA) = Working well; amber = Slowing down; red = Not working.
// The status is always written as a word as well as shown in colour.

if (!function_exists('sp_e')) {
    function sp_e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    function sp_css(): string
    {
        return <<<'CSS'
<style>
.sp{--g:#048C44;--gd:#037A3B;--gl:#E3F3EA;--am:#92600A;--aml:#FCF1DC;--amb:#D98E04;--rd:#B42318;--rdl:#FBE4E2;--rdb:#CC3A31;--ink:#1f2326;--mut:#6b7280;--line:#ebebe6;--tile:#f5f5f2;--ch:#373435}
.sp-good{--c:var(--g);--cd:var(--gd);--cl:var(--gl)}
.sp-slow{--c:var(--amb);--cd:var(--am);--cl:var(--aml)}
.sp-bad{--c:var(--rdb);--cd:var(--rd);--cl:var(--rdl)}
.sp-ava{width:56px;height:56px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;background:var(--ch);color:#fff;font-weight:700;font-size:19px;overflow:hidden}
.sp-ava img{width:100%;height:100%;object-fit:cover}
.sp-ava.lg{width:86px;height:86px;font-size:28px}
.sp-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 11px;border-radius:999px;background:var(--cl);color:var(--cd);font-weight:600;font-size:12.5px;white-space:nowrap}
.sp-pill:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--c)}
.sp-bar{position:relative;height:10px;border-radius:999px;background:#e8e8e4}
.sp-bar>i{display:block;height:100%;border-radius:999px;background:var(--c)}
.sp-bar>b{position:absolute;top:-4px;bottom:-4px;width:2px;background:#1f2326;border-radius:1px}
.sp-bar.thin{height:7px}
.sp-bar.thin>b{top:-3px;bottom:-3px}
.sp-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:18px}
.sp-tabs{display:flex;gap:8px;flex-wrap:wrap}
.sp-tabs a{padding:8px 16px;border-radius:999px;color:#374151;text-decoration:none;font-size:14px;background:#fff;border:1px solid transparent}
.sp-tabs a.on{background:var(--g);color:#fff}
.sp-tabs a span{margin-left:5px;opacity:.7}
.sp-find{display:flex;flex-wrap:wrap;gap:10px}
.sp-find .srch{position:relative}
.sp-find .srch i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px}
.sp-find input,.sp-find select{border:1px solid var(--line);border-radius:12px;background:#fff;padding:9px 14px;font-size:14px;height:auto}
.sp-find input{padding-left:36px;min-width:270px}
.sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:20px}
.sp-mini{position:relative;background:#fff;border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.sp-mini:hover{box-shadow:0 6px 20px rgba(0,0,0,.08)}
.sp-mini .open{position:absolute;inset:0;border-radius:20px;z-index:1}
.sp-mini .head{display:flex;gap:14px;align-items:flex-start}
.sp-mini .head .who{flex:1;min-width:0;padding-top:4px}
.sp-mini .nm{font-weight:600;font-size:16.5px;color:var(--ink);line-height:1.25}
.sp-mini .no{font-size:13px;color:var(--mut)}
.sp-mini .tk{position:absolute;right:14px;bottom:14px;z-index:2;width:18px;height:18px;accent-color:var(--g)}
.sp-lbl{display:flex;justify-content:space-between;font-size:13.5px;color:var(--mut);margin-bottom:7px}
.sp-lbl b{color:var(--ink);font-weight:700}
.sp-tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.sp-tiles div{background:var(--tile);border-radius:14px;padding:12px 14px}
.sp-tiles small{display:block;font-size:12.5px;color:var(--mut)}
.sp-tiles b{display:block;font-size:20px;color:var(--ink);line-height:1.25;margin-top:2px}
.sp-tiles b.sm{font-size:14.5px;padding-top:5px}
.sp-last{font-size:13.5px;color:var(--mut)}
.sp-last b{color:var(--ink)}
.sp-note{font-size:14px;color:var(--cd);background:var(--cl);border-radius:12px;padding:11px 14px;line-height:1.4}
.sp-legend{display:flex;flex-wrap:wrap;gap:8px 30px;font-size:13.5px;color:var(--mut);margin-top:20px}
.sp-card{background:#fff;border-radius:20px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.sp-stack{display:flex;flex-direction:column;gap:18px}
.sp-head{display:flex;flex-wrap:wrap;gap:20px;align-items:center}
.sp-head h3{margin:0;font-size:26px;font-weight:700}
.sp-head .meta{color:var(--mut);font-size:14.5px;line-height:1.6}
.sp-four{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
@media(max-width:991.98px){.sp-four{grid-template-columns:repeat(2,minmax(0,1fr))}}
.sp-four>div{background:#fff;border-radius:20px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.04);border:3px solid var(--tile)}
.sp-four small{color:var(--mut);font-size:13.5px}
.sp-four b{display:block;font-size:30px;color:var(--ink);line-height:1.25;font-weight:700}
.sp-four b.sm{font-size:22px;padding:5px 0}
.sp-four .sub{font-size:13px;color:var(--mut)}
.sp-cols{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(0,1fr);gap:18px;align-items:start}
@media(max-width:991.98px){.sp-cols{grid-template-columns:minmax(0,1fr)}}
.sp-sec{font-size:17px;font-weight:700;margin:0 0 4px;display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap}
.sp-sec small{font-weight:400;font-size:13px;color:var(--mut)}
.sp-tbl{width:100%;border-collapse:collapse;margin-top:10px}
.sp-tbl th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--mut);font-weight:600;padding:8px 8px;text-align:left}
.sp-tbl td{padding:13px 8px;border-top:1px solid var(--line);font-size:14.5px;vertical-align:middle}
.sp-tbl .sub{font-size:12.5px;color:var(--mut)}
.sp-tbl .barcell{min-width:130px}
.sp-tbl .barcell small{display:block;margin-bottom:5px;color:var(--mut);font-size:13px}
.sp-tbl .barcell small b{color:var(--ink)}
.sp-word{display:inline-block;padding:3px 12px;border-radius:999px;font-size:12.5px;font-weight:600;background:var(--gl);color:var(--gd)}
.sp-word.credit{background:#E7F0FB;color:#1D4ED8}.sp-word.pass{background:#FCF1DC;color:#92600A}.sp-word.failed{background:#FBE4E2;color:#B42318}
.sp-fold{font-size:13.5px;color:var(--mut);padding:12px 14px;margin-top:6px;background:#fafaf8;border-radius:10px}
.sp-tutor{background:var(--cl);border-radius:16px;padding:16px 18px;margin-top:12px}
.sp-tutor ul{list-style:none;margin:8px 0;padding:0}
.sp-tutor li{display:flex;gap:9px;padding:3px 0;font-size:14.5px}
.sp-tutor li:before{content:"\2713";color:var(--g);font-weight:700}
.sp-tutor li.warn:before{content:"\26A0";color:var(--amb)}
.sp-tutor li.bad:before{content:"\26A0";color:var(--rdb)}
.sp-tutor .todo{font-size:14.5px;margin-top:6px}
.sp-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.sp .btn{border-radius:12px;font-weight:600;padding:9px 18px}
.sp .btn-g{background:var(--g);border-color:var(--g);color:#fff}
.sp .btn-g:hover{background:var(--gd);border-color:var(--gd);color:#fff}
.sp .btn-line{background:#fff;border:1px solid var(--line);color:var(--ink)}
.sp-notes .n{border-top:1px solid var(--line);padding:10px 0;font-size:14.5px;white-space:pre-wrap}
.sp-notes .n small{display:block;color:var(--mut)}
.sp-lbl2{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--mut);margin-top:18px}
.sp-list .r{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:11px 0;border-top:1px solid var(--line);font-size:14.5px}
.sp-list .r:first-child{border-top:0}
.sp-list .r .sub{font-size:12.5px;color:var(--mut)}
.sp-chip{padding:3px 11px;border-radius:999px;font-size:12.5px;font-weight:600;background:#eeeeea;color:#4b5563;white-space:nowrap}
.sp-chip.marked{background:var(--gl);color:var(--gd)}.sp-chip.late{background:#FCF1DC;color:#92600A}.sp-chip.missing{background:#FBE4E2;color:#B42318}
.sp-proj{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 36px}
@media(max-width:767.98px){.sp-proj{grid-template-columns:minmax(0,1fr)}.sp-tiles b{font-size:17px}.sp-four b{font-size:24px}.sp-card{padding:16px}.sp-tbl .hide-sm{display:none}}
.sp-sel{position:sticky;bottom:0;background:#f4f4f1;padding:12px 0;margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>
CSS;
    }

    /** Photo if they uploaded one, otherwise initials in a round badge. */
    function sp_avatar(array $c, bool $large = false): string
    {
        $inner = !empty($c['photo']['url']) ? '<img src="' . sp_e($c['photo']['url']) . '" alt="" loading="lazy">' : sp_e(sp_initials($c['full_name']));
        return '<div class="sp-ava' . ($large ? ' lg' : '') . '" aria-hidden="true">' . $inner . '</div>';
    }

    function sp_pill(array $c): string
    {
        return '<span class="sp-pill sp-' . sp_e($c['status']['key']) . '">' . sp_e($c['status']['word']) . '</span>';
    }

    /** The course bar: how far through, with a thin line where they should be today. */
    function sp_bar(array $c, bool $thin = false): string
    {
        $w = max(0, min(100, round($c['actual_pct'], 1)));
        $mark = $c['should_be'] !== null ? '<b style="left:' . round($c['should_be'], 1) . '%" title="Should be here today"></b>' : '';
        return '<div class="sp-bar' . ($thin ? ' thin' : '') . '" role="progressbar" aria-valuenow="' . (int) round($w) . '" aria-valuemin="0" aria-valuemax="100"><i style="width:' . $w . '%"></i>' . $mark . '</div>';
    }

    function sp_should_text(array $c): string
    {
        return $c['should_be'] === null ? 'Course start date or length not set' : 'Should be at ' . (int) round($c['should_be']) . '% today';
    }

    /** "today", "yesterday", "3 days ago". */
    function sp_ago(?int $days): string
    {
        return $days === null ? '' : ($days === 0 ? 'today' : ($days === 1 ? 'yesterday' : $days . ' days ago'));
    }

    /** The small overview card. $base is the portal's progress folder URL. */
    function sp_mini(array $c, string $base, bool $showTutor = false): string
    {
        $k = sp_e($c['status']['key']);
        $bad = $c['status']['key'] !== 'good' && $c['status']['reasons'];
        $foot = $bad
            ? '<div class="sp-note">' . sp_e(implode('. ', $c['status']['reasons']) . '.') . '</div>'
            : ($c['last_handin'] ? '<div class="sp-last">Last hand-in: <b>' . sp_e(sp_ago($c['days_since'])) . '</b></div>' : '');
        return '<div class="sp-mini sp-' . $k . '">'
            . '<a class="open" href="' . sp_e($base . 'student.php?u=' . (int) $c['user_id']) . '" aria-label="Open ' . sp_e($c['full_name']) . '"></a>'
            . '<div class="head">' . sp_avatar($c)
            . '<div class="who"><div class="nm">' . sp_e($c['full_name']) . '</div><div class="no">' . sp_e($c['registration_no']) . '</div>'
            . ($showTutor && $c['tutors'] ? '<div class="no">' . sp_e(implode(', ', $c['tutors'])) . '</div>' : '') . '</div>'
            . '<div>' . sp_pill($c) . '</div></div>'
            . '<div><div class="sp-lbl"><span>Course progress</span><b>' . (int) round($c['actual_pct']) . '%</b></div>' . sp_bar($c, true)
            . '<div class="sp-last mt-1">' . sp_e(sp_should_text($c)) . '</div></div>'
            . '<div class="sp-tiles"><div><small>Assignments</small><b>' . (int) $c['handed'] . '/' . (int) $c['total'] . '</b></div>'
            . '<div><small>Average</small>' . ($c['average'] === null ? '<b class="sm">no mark yet</b>' : '<b>' . sp_e(sp_pct($c['average'])) . '</b>') . '</div>'
            . '<div><small>Project avg</small>' . ($c['project_average'] === null ? '<b class="sm">no mark yet</b>' : '<b>' . sp_e(sp_pct($c['project_average'])) . '</b>') . '</div></div>'
            . $foot
            . '<input class="tk" type="checkbox" name="ids[]" value="' . (int) $c['user_id'] . '" form="spSel" aria-label="Select ' . sp_e($c['full_name']) . '" title="Select for download">'
            . '</div>';
    }

    function sp_last_text(array $c): string
    {
        return $c['last_handin'] ? sp_local($c['last_handin'], 'j M Y') : 'Nothing yet';
    }
}
