<?php
// public/components/staffcalendar/page.php — the Staff Calendar screen for BOTH portals (tutor and super admin). Sets $content, $page_title; the entry file then loads the layout.
// Needs $sc_portal. boot.php checks the login and the real role first. Month / Week / Day / Agenda, search, "Up next", and the type filter.
$page_title = 'Staff Calendar';
$sa_custom_header = true;   // the super admin layout: this page draws its own heading
require __DIR__ . '/boot.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
if (!$sc_ready) {
    $content = '<div class="alert alert-warning">The Staff Calendar is not installed yet. Ask the office to run System Updates (migrations 111 and 112).</div>';
    return;
}

$tz = scal_tz();
$now = scal_now();
$todayS = $now->format('Y-m-d');
$view = in_array($_GET['view'] ?? '', ['month', 'week', 'day', 'agenda'], true) ? $_GET['view'] : 'month';
$dRaw = (string) ($_GET['d'] ?? '');
$day = DateTime::createFromFormat('!Y-m-d', $dRaw, $tz);
if (!$day || $day->format('Y-m-d') !== $dRaw || (int) $day->format('Y') < 2 || (int) $day->format('Y') > 9997) $day = new DateTime($todayS, $tz);
$dayS = $day->format('Y-m-d');
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

// The range on screen. Only these days are loaded.
$monday = fn(DateTime $d): DateTime => (clone $d)->modify('-' . ((int) $d->format('N') - 1) . ' days');
$monthFirst = (clone $day)->modify('first day of this month');
if ($view === 'week') {
    $from = $monday($day); $to = (clone $from)->modify('+7 days');
    $prev = (clone $day)->modify('-7 days'); $next = (clone $day)->modify('+7 days');
    $last = (clone $to)->modify('-1 day');
    $label = $from->format('j M') . ' – ' . $last->format('j M Y');
} elseif ($view === 'day') {
    $from = clone $day; $to = (clone $day)->modify('+1 day');
    $prev = (clone $day)->modify('-1 day'); $next = (clone $day)->modify('+1 day');
    $label = $day->format('l j F Y');
} else {   // month and agenda: the month of the chosen day, laid out as 6 full weeks in the month view
    $from = $view === 'month' ? $monday($monthFirst) : clone $monthFirst;
    $to = $view === 'month' ? (clone $from)->modify('+42 days') : (clone $monthFirst)->modify('+1 month');
    $prev = (clone $monthFirst)->modify('-1 month'); $next = (clone $monthFirst)->modify('+1 month');
    $label = $monthFirst->format('F Y');
}
$occ = scal_range($db, $actor, $from, $to);
// Birthdays: worked out for every year from their own table and drawn as pink chips. If anything is wrong there the calendar still works.
require_once __DIR__ . '/../../../app/v1/services/StaffBirthdays.php';
$bdOn = false; $bdToday = []; $bdUpcoming = []; $bdRows = [];
try {
    $bdOn = sbd_installed($db);
    if ($bdOn) {
        if (scal_type_is_on($db, 'birthday')) $occ = array_merge($occ, sbd_range($db, $from, $to));
        usort($occ, fn($a, $b) => [$a['occ_start'], !empty($a['all_day']) ? 0 : 1, $a['id']] <=> [$b['occ_start'], !empty($b['all_day']) ? 0 : 1, $b['id']]);
        $bdToday = sbd_today($db, $actor, scal_now());   // the first person to open the calendar on a birthday makes that day's message
        $bdUpcoming = sbd_upcoming($db, scal_now(), 5);
        $bdRows = sbd_rows($db);
    }
} catch (Throwable $bdErr) { error_log('[StaffCalendar] birthdays: ' . $bdErr->getMessage()); $bdOn = false; $bdToday = []; $bdUpcoming = []; }
// Holidays (Kenyan public holidays) as teal all-day chips, the settings and the person's own switches. Each is skipped quietly when its migration has not run.
require_once __DIR__ . '/../../../app/v1/services/StaffHolidays.php';
require_once __DIR__ . '/../../../app/v1/services/StaffSettings.php';
$nextHol = null; $sstOn = false; $myPrefs = []; $teamDef = [];
try {
    if (sh_installed($db)) {
        if (scal_type_is_on($db, 'holiday')) {
            $occ = array_merge($occ, sh_range($db, $from, $to, scal_type_color($db, 'holiday') ?? '#1A9A9A'));
            usort($occ, fn($a, $b) => [$a['occ_start'], !empty($a['all_day']) ? 0 : 1, $a['id']] <=> [$b['occ_start'], !empty($b['all_day']) ? 0 : 1, $b['id']]);
        }
        $nextHol = sh_next($db, scal_now());
    }
    $sstOn = sst_installed($db);
    if ($sstOn) { $myPrefs = sst_user_prefs($db, $actor['uid']); $teamDef = sst_team_defaults($db); }
} catch (Throwable $hErr) { error_log('[StaffCalendar] holidays/settings: ' . $hErr->getMessage()); $nextHol = null; $sstOn = false; }
$byDay = scal_by_day($occ, $from, $to);
// The gift tracker: the first person to open the calendar on a birthday makes that year's gift list. It only records who says they sent a gift (no money moves).
require_once __DIR__ . '/../../../app/v1/services/StaffGifts.php';
$gfOn = false; $gf = ['panels' => [], 'cards' => [], 'notes' => []];
try {
    $gfOn = $bdOn && sg_installed($db);
    if ($gfOn) {
        $t0 = new DateTime(scal_now()->format('Y-m-d'), scal_tz());
        foreach (sbd_range($db, $t0, (clone $t0)->modify('+1 day')) as $gb) sg_ensure_list($db, $gb['user_id'], $gb['occ_date']);
        $gf = sg_for_viewer($db, $actor, scal_now());
    }
} catch (Throwable $gfErr) { error_log('[StaffCalendar] gifts: ' . $gfErr->getMessage()); $gfOn = false; $gf = ['panels' => [], 'cards' => [], 'notes' => []]; }
$byDay = scal_by_day($occ, $from, $to);
$types = scal_types($db);

// Banner numbers (the viewer's own visible events only).
$todayStart = new DateTime($todayS, $tz);
$todayCount = count(scal_range($db, $actor, $todayStart, (clone $todayStart)->modify('+1 day')));
$weekStart = $monday($todayStart);
$weekCount = count(scal_range($db, $actor, $weekStart, (clone $weekStart)->modify('+7 days')));
$nextEv = scal_next($db, $actor, $now);
$hour = (int) $now->format('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$plural = fn(int $n, string $w) => $n . ' ' . $w . ($n === 1 ? '' : 's');

$link = function (array $over = []) use ($sc_base, $view, $dayS): string {
    $p = $over + ['view' => $view, 'd' => $dayS];
    foreach ($p as $k => $v) if ($v === null || $v === '') unset($p[$k]);
    return $sc_base . '?' . http_build_query($p);
};

// One event as the data the pop-up needs.
$evData = function (array $o) use ($actor, $e): string {
    $s = (string) $o['start_at'];
    return $e(json_encode([
        'id' => $o['id'], 'title' => $o['title'], 'description' => $o['description'], 'location' => $o['location'],
        'cover' => $o['cover'] !== '' ? url($o['cover']) : '', 'type' => $o['type'], 'type_label' => $o['type_label'], 'color' => $o['color'],
        'when' => scal_when($o), 'repeat' => $o['repeat'], 'repeat_label' => SCAL_REPEATS[$o['repeat']] ?? '', 'owner' => $o['owner'],
        'notify' => $o['notify'], 'private' => $o['private'], 'can_change' => scal_can_change($actor, $o), 'all_day' => $o['all_day'],
        'start_date' => substr($s, 0, 10), 'start_time' => substr($s, 11, 5), 'end_date' => substr((string) $o['end_at'], 0, 10), 'end_time' => substr((string) $o['end_at'], 11, 5),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP));
};
$tint = fn(string $c) => $e($c . '22');
$repeatIcon = fn(array $o) => $o['repeat'] !== 'none' ? ' <i class="fas fa-rotate sc-rep" title="' . $e(SCAL_REPEATS[$o['repeat']]) . '" aria-label="Repeats"></i>' : '';
$lockIcon = fn(array $o) => $o['private'] ? ' <i class="fas fa-lock sc-rep" title="Private to you" aria-label="Private"></i>' : '';
// Projects: a dark bar across the days with the progress on it (it splits at the end of each week row), and a row in the lists. Both open the project page.
require_once __DIR__ . '/../../../app/v1/services/StaffProjects.php';
$pjOn = false; $pjProg = []; $pjPeople = [];
try {
    $pjOn = spj_installed($db);
    if ($pjOn) {
        $pjIds = [];
        foreach ($occ as $po) if (($po['type'] ?? '') === 'project' && empty($po['birthday']) && empty($po['holiday'])) $pjIds[] = $po['id'];
        $pjProg = spj_progress_bulk($db, $pjIds, $todayS);
        $pjPeople = array_values(sbd_people($db));
    }
} catch (Throwable $pjErr) { error_log('[StaffCalendar] projects: ' . $pjErr->getMessage()); $pjOn = false; }
$pjBase = url($sc_portal . '/staffcalendar/project.php');
$isPr = fn(array $o): bool => ($o['type'] ?? '') === 'project' && empty($o['birthday']) && empty($o['holiday']);
$prChip = function (array $o, string $dayStr) use ($e, $pjProg, $pjBase, $tz): string {
    $pS = substr($o['occ_start'], 0, 10); $pE = substr($o['occ_end'], 0, 10);
    $d = new DateTime($dayStr, $tz); $mon = (clone $d)->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d'); $sun = (clone $d)->modify('+' . (7 - (int) $d->format('N')) . ' days')->format('Y-m-d');
    $segS = max($pS, $mon); $segE = min($pE, $sun);
    $span = (int) (new DateTime($segS, $tz))->diff(new DateTime($segE, $tz))->format('%a') + 1;
    $cls = 'sc-chip sc-pbar' . ($dayStr === $pS ? ' pb-start' : '') . ($dayStr === $pE ? ' pb-end' : '') . ($dayStr === $segS ? ' pb-rs' : '') . ($dayStr === $segE ? ' pb-re' : '');
    $pr = $pjProg[$o['id']] ?? null; $pct = $pr ? (int) $pr['percent'] : 0;
    return '<a class="' . $cls . '" href="' . $e($pjBase . '?id=' . (int) $o['id']) . '" data-type="project" style="--span:' . $span . '" title="' . $e($o['title'] . ($pr ? ' · ' . $pct . '%' : '')) . '">'
        . ($dayStr === $segS ? '<span class="pb-t">' . $e($o['title']) . '</span>' : '') . ($dayStr === $segE ? '<span class="pb-p">' . $pct . '%</span>' : '') . '</a>';
};
$prRow = function (array $o) use ($e, $pjProg, $pjBase): string {
    $pr = $pjProg[$o['id']] ?? null;
    return '<a class="sc-row sc-prow" data-type="project" style="--c:#373435" href="' . $e($pjBase . '?id=' . (int) $o['id']) . '"><span class="sc-rt">' . ($o['all_day'] ? 'All day' : '') . '</span><span class="sc-rb"><span class="sc-rn">' . $e($o['title']) . ' <i class="fas fa-diagram-project sc-rep"></i></span>'
        . '<span class="sc-rm">Project' . ($pr ? ' · ' . (int) $pr['percent'] . '% · ' . (int) $pr['done'] . ' of ' . (int) $pr['total'] . ' tasks done · ' . $e($pr['state']) : '') . ' · Added by ' . $e($o['owner']) . '</span></span></a>';
};
$holChip = function (array $o) use ($e): string {
    $t = $o['title'] . ($o['label'] !== '' ? ' · ' . $o['label'] : '');
    return '<span class="sc-chip sc-hol" data-type="holiday" style="--c:' . $e($o['color']) . ';background:' . $e($o['color']) . '22" title="' . $e($t) . '"><i class="fas fa-flag sc-rep"></i><span class="sc-n">' . $e($o['title']) . ($o['label'] !== '' ? ' (expected)' : '') . '</span></span>';
};
$holRow = function (array $o) use ($e): string {
    return '<div class="sc-row sc-bdrow" data-type="holiday" style="--c:' . $e($o['color']) . '"><span class="sc-rt">All day</span><span class="sc-rb"><span class="sc-rn">' . $e($o['title']) . ' <i class="fas fa-flag sc-rep"></i></span>'
        . '<span class="sc-rm">Public holiday' . ($o['label'] !== '' ? ' · ' . $e($o['label']) : '') . ($o['note'] !== '' ? ' · ' . $e($o['note']) : '') . '</span></span></div>';
};
$bdAva = function (array $o, string $cls) use ($e): string {
    return '<span class="' . $cls . '">' . $e($o['initials']) . ($o['photo'] !== '' ? '<img src="' . $e($o['photo']) . '" alt="" onerror="this.remove()">' : '') . '</span>';
};
$bdChip = function (array $o) use ($e, $bdAva): string {
    return '<span class="sc-chip sc-bday" data-type="birthday" style="--c:#d6457f;background:#d6457f22" title="' . $e($o['full_name'] . '’s birthday') . '">' . $bdAva($o, 'sc-bava')
        . '<span class="sc-n">' . $e($o['title']) . '</span><i class="fas fa-cake-candles sc-rep"></i></span>';
};
$bdRow = function (array $o) use ($e, $bdAva): string {
    return '<div class="sc-row sc-bdrow" data-type="birthday" style="--c:#d6457f"><span class="sc-rt">All day</span><span class="sc-rb"><span class="sc-rn">' . $bdAva($o, 'sc-bava sc-bava-l') . ' ' . $e($o['full_name']) . '’s birthday <i class="fas fa-cake-candles sc-rep"></i></span>'
        . '<span class="sc-rm">Birthday' . ($o['age'] !== null ? ' · turns ' . (int) $o['age'] : '') . '</span></span></div>';
};
$chip = function (array $o, string $dayStr) use ($e, $evData, $tint, $repeatIcon, $lockIcon, $now, $bdChip, $isPr, $prChip, $holChip): string {
    if (!empty($o['birthday'])) return $bdChip($o);
    if (!empty($o['holiday'])) return $holChip($o);
    if ($isPr($o)) return $prChip($o, $dayStr);
    $past = scal_is_past_deadline($o, $now) ? ' past' : '';
    $time = (!$o['all_day'] && $o['occ_date'] === $dayStr) ? '<span class="sc-t">' . $e(scal_time(new DateTime($o['occ_start'], scal_tz()))) . '</span> ' : '';
    return '<button type="button" class="sc-chip' . $past . '" data-type="' . $e($o['type']) . '" data-d="' . $e($dayStr) . '" data-ev="' . $evData($o) . '" style="--c:' . $e($o['color']) . ';background:' . $tint($o['color']) . '">'
        . $time . '<span class="sc-n">' . $e($o['title']) . '</span>' . $repeatIcon($o) . $lockIcon($o) . '</button>';
};
$row = function (array $o, string $dayStr = '', bool $direct = false) use ($e, $evData, $tint, $repeatIcon, $lockIcon, $now, $bdRow, $isPr, $prRow, $holRow): string {
    if (!empty($o['birthday'])) return $bdRow($o);
    if (!empty($o['holiday'])) return $holRow($o);
    if ($isPr($o)) return $prRow($o);
    $past = scal_is_past_deadline($o, $now) ? ' past' : '';
    $s = new DateTime($o['occ_start'], scal_tz()); $en = new DateTime($o['occ_end'], scal_tz());
    $time = $o['all_day'] ? 'All day' : scal_time($s) . ($en > $s ? ' – ' . scal_time($en) : '');
    return '<button type="button" class="sc-row' . $past . '" data-type="' . $e($o['type']) . '" data-d="' . $e($dayStr !== '' ? $dayStr : $o['occ_date']) . '"' . ($direct ? ' data-panel="1"' : '') . ' data-ev="' . $evData($o) . '" style="--c:' . $e($o['color']) . '">'
        . '<span class="sc-rt">' . $e($time) . '</span><span class="sc-rb"><span class="sc-rn">' . $e($o['title']) . $repeatIcon($o) . $lockIcon($o) . '</span>'
        . '<span class="sc-rm">' . $e($o['type_label']) . ($o['location'] !== '' ? ' · ' . $e($o['location']) : '') . ' · Added by ' . $e($o['owner']) . '</span></span></button>';
};

$dow = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$formUrl = url($sc_portal . '/staffcalendar/action.php');

ob_start();
?>
<style>
.sc{--sc-g:#048C44;--sc-gd:#037A3B;--sc-gl:#E3F3EA;--sc-ink:#1d1b1c;--sc-mut:#6b7067;--sc-line:#e6e9e4;font-family:inherit;color:var(--sc-ink);max-width:1400px}
.sc *{box-sizing:border-box}
.sc [hidden]{display:none!important}
.sc button{font-family:inherit}
.sc-title h1{font-size:1.45rem;font-weight:700;margin:0}
.sc-title p{margin:0;color:var(--sc-mut);font-size:.88rem}
.sc-banner{background:linear-gradient(115deg,#037A3B 0%,#025a2b 100%);color:#fff;border-radius:18px;padding:22px 26px;margin:14px 0 18px;display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;position:relative;overflow:hidden}
.sc-banner:after{content:"";position:absolute;right:-60px;top:-70px;width:260px;height:170px;background:#048C44;transform:rotate(-24deg);border-radius:30px;opacity:.55;pointer-events:none}
.sc-banner h2{font-size:1.5rem;font-weight:700;margin:0 0 4px;position:relative;z-index:1;color:#fff}
.sc-banner p{margin:0;opacity:.92;font-size:.92rem;position:relative;z-index:1}
.sc-stat{background:rgba(255,255,255,.16);border-radius:12px;padding:8px 16px;position:relative;z-index:1}
.sc-stat small{display:block;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
.sc-stat b{font-size:1.15rem}
.sc-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
.sc-nav{display:flex;align-items:center;gap:8px}
.sc-circ{width:40px;height:40px;border-radius:50%;background:#fff;border:1px solid var(--sc-line);color:var(--sc-ink);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.sc-circ:hover{background:var(--sc-gl);color:var(--sc-gd)}
.sc-label{font-size:1.45rem;font-weight:700;margin:0 6px}
.sc-pill{display:inline-flex;align-items:center;border:1px solid var(--sc-line);background:#fff;border-radius:999px;padding:7px 16px;font-size:.86rem;font-weight:600;color:var(--sc-ink);text-decoration:none}
.sc-pill:hover{background:var(--sc-gl);color:var(--sc-gd)}
.sc-views{display:inline-flex;background:#f1f3ef;border-radius:999px;padding:3px}
.sc-views a{padding:6px 14px;border-radius:999px;font-size:.84rem;font-weight:600;color:#4b524a;text-decoration:none}
.sc-views a.on{background:var(--sc-g);color:#fff}
.sc-search{position:relative;flex:1 1 200px;max-width:280px}
.sc-search input{width:100%;border:1px solid var(--sc-line);border-radius:999px;padding:8px 14px 8px 36px;font-size:.86rem;background:#fff;font-family:inherit}
.sc-search i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--sc-mut);font-size:.8rem}
.sc-search input:focus,.sc-form input:focus,.sc-form select:focus,.sc-form textarea:focus{outline:2px solid rgba(4,140,68,.35);border-color:var(--sc-g)}
.sc-btn{background:var(--sc-g);color:#fff;border:0;border-radius:999px;padding:9px 20px;font-weight:600;font-size:.88rem;cursor:pointer;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.sc-btn:hover{background:var(--sc-gd);color:#fff}
.sc-btn.ghost{background:#fff;color:var(--sc-ink);border:1px solid var(--sc-line)}
.sc-btn.ghost:hover{background:var(--sc-gl);color:var(--sc-gd)}
.sc-btn.danger{background:#fff;color:#b42318;border:1px solid #f1c9c4}
.sc-btn.danger:hover{background:#fdeceb;color:#b42318}
.sc-spacer{flex:1}
.sc-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;align-items:start}
.sc-card{background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:0;overflow:hidden}
.sc-pad{padding:18px 20px}
/* month grid */
.sc-month{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
.sc-dh{font-size:.72rem;font-weight:700;letter-spacing:.07em;color:#6b7067;padding:12px 10px;border-bottom:1px solid var(--sc-line)}
.sc-dh.we{color:#c0362c}
.sc-cell{min-height:122px;border-right:1px solid var(--sc-line);border-bottom:1px solid var(--sc-line);padding:6px;display:flex;flex-direction:column;gap:3px;min-width:0}
.sc-cell:nth-child(7n+14){border-right:0}
.sc-cell.out{background:#fafbf9}
.sc-cell.out .sc-dn{color:#b3b8b0}
.sc-cell.today{outline:2px solid var(--sc-g);outline-offset:-2px;background:var(--sc-gl)}
.sc-dn{font-size:.8rem;font-weight:600;color:var(--sc-ink);text-decoration:none;align-self:flex-start;min-width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:0 6px}
.sc-cell.today .sc-dn{background:var(--sc-g);color:#fff}
.sc-chip{display:block;width:100%;text-align:left;border:0;border-left:3px solid var(--c);border-radius:7px;padding:3px 7px;font-size:.74rem;line-height:1.3;color:var(--sc-ink);cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-chip:hover{filter:brightness(.96)}
.sc-chip .sc-t{font-weight:600}
.sc-chip.past .sc-n,.sc-row.past .sc-rn{text-decoration:line-through;opacity:.7}
.sc-rep{font-size:.66rem;opacity:.75;margin-left:2px}
.sc-over{display:none}
.sc-more{font-size:.72rem;color:var(--sc-gd);font-weight:600;text-decoration:none;padding:0 4px}
.sc-more[hidden]{display:none}
/* week / day / agenda */
.sc-week{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
.sc-wcol{min-height:260px;border-right:1px solid var(--sc-line);padding:8px;display:flex;flex-direction:column;gap:4px;min-width:0}
.sc-wcol:last-child{border-right:0}
.sc-wcol.today{background:var(--sc-gl)}
.sc-wh{font-size:.76rem;font-weight:700;color:var(--sc-mut);margin-bottom:4px;text-decoration:none}
.sc-wcol.today .sc-wh{color:var(--sc-gd)}
.sc-list{display:flex;flex-direction:column}
.sc-gh{font-size:.8rem;font-weight:700;color:var(--sc-gd);padding:14px 20px 6px;text-transform:uppercase;letter-spacing:.05em}
.sc-gh a{color:inherit;text-decoration:none}
.sc-row{display:flex;gap:14px;align-items:flex-start;text-align:left;width:100%;background:#fff;border:0;border-left:4px solid var(--c);margin:3px 0;padding:10px 20px 10px 16px;cursor:pointer;border-radius:0 10px 10px 0}
.sc-row:hover{background:#f6faf7}
.sc-rt{flex:0 0 112px;font-size:.82rem;font-weight:600;color:var(--sc-ink)}
.sc-rb{display:flex;flex-direction:column;min-width:0}
.sc-rn{font-weight:600;font-size:.92rem}
.sc-rm{font-size:.78rem;color:var(--sc-mut)}
.sc-empty{color:var(--sc-mut);font-size:.88rem;padding:22px 20px}
/* phone strip */
.sc-strip,.sc-phone-nav{display:none}
/* side panel */
.sc-side{display:flex;flex-direction:column;gap:16px}
.sc-up{overflow:hidden}
.sc-cover{height:138px;background:linear-gradient(135deg,#037A3B,#048C44);background-size:cover;background-position:center;position:relative}
.sc-cover:after{content:"";position:absolute;right:-40px;top:-30px;width:150px;height:100px;background:#025a2b;transform:rotate(-24deg);border-radius:20px;opacity:.5}
.sc-cover.img:after{display:none}
.sc-eyebrow{font-size:.7rem;letter-spacing:.14em;font-weight:700;color:var(--sc-mut);text-transform:uppercase}
.sc-badge{background:var(--sc-gl);color:var(--sc-gd);font-size:.74rem;font-weight:700;border-radius:999px;padding:3px 10px}
.sc-up h3{font-size:1.1rem;font-weight:700;margin:8px 0 2px}
.sc-up p{margin:0;font-size:.82rem;color:var(--sc-mut)}
.sc-side h4{font-size:.7rem;letter-spacing:.14em;font-weight:700;color:var(--sc-mut);text-transform:uppercase;margin:0 0 12px}
.sc-ft{display:flex;align-items:center;gap:10px;padding:5px 0;font-size:.9rem;cursor:pointer}
.sc-ft i{width:12px;height:12px;border-radius:3px;background:var(--c);flex:none}
.sc-ft span{flex:1}
.sc-ft input{width:19px;height:19px;accent-color:var(--sc-g);cursor:pointer;flex:none}
.sc-hide{display:none!important}
.sc-note{font-size:.76rem;color:var(--sc-mut);margin-top:10px}
/* dialogs */
.sc dialog{border:0;border-radius:18px;padding:0;width:min(560px,calc(100vw - 24px));max-height:calc(100vh - 24px);box-shadow:0 20px 60px rgba(0,0,0,.28);color:var(--sc-ink);font-family:inherit}
.sc dialog::backdrop{background:rgba(20,28,22,.5)}
.sc-dh2{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:18px 22px 4px}
.sc-dh2 h3{font-size:1.2rem;font-weight:700;margin:0}
.sc-x{border:0;background:#f1f3ef;border-radius:50%;width:34px;height:34px;cursor:pointer;flex:none}
.sc-dbody{padding:10px 22px 20px;overflow:auto;max-height:calc(100vh - 150px)}
.sc-tag{display:inline-block;font-size:.74rem;font-weight:700;border-radius:999px;padding:3px 10px;color:#fff;margin-bottom:6px}
.sc-dl-row{display:flex;gap:10px;font-size:.9rem;padding:5px 0;align-items:flex-start}
.sc-dl-row i{width:18px;color:var(--sc-gd);margin-top:3px;text-align:center;flex:none}
.sc-dcover{width:100%;max-height:200px;object-fit:cover;border-radius:12px;margin:6px 0 8px;display:block}
.sc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}

/* the event card (desktop: next to the chip; phone: bottom sheet). It is moved to <body> by the script, so it does not use the .sc wrapper. */
.sc-chip.sc-sel{background:var(--c)!important;color:#fff}
.sc-chip.sc-sel .sc-rep{color:#fff;opacity:1}
.sc-row.sc-sel{background:var(--c)}
.sc-row.sc-sel .sc-rt,.sc-row.sc-sel .sc-rn,.sc-row.sc-sel .sc-rm{color:#fff}
.scp,.scp *{box-sizing:border-box}
.scp [hidden]{display:none!important}
.scp{--sc-g:#048C44;--sc-gd:#037A3B;--sc-gl:#E3F3EA;--sc-ink:#1d1b1c;--sc-mut:#6b7067;position:fixed;z-index:1090;left:0;top:0;width:350px;max-width:calc(100vw - 16px);max-height:calc(100vh - 16px);background:#fff;border-radius:18px;
  box-shadow:0 14px 44px rgba(0,0,0,.24),0 0 0 1px rgba(0,0,0,.05);color:var(--sc-ink);font-family:inherit;font-size:.9rem;outline:0}
.scp-box{max-height:calc(100vh - 16px);overflow:auto;border-radius:18px;position:relative}
.scp-arrow{position:absolute;width:14px;height:14px;background:#fff;transform:rotate(45deg);z-index:2}
.scp-arrow.l{left:-7px;border-left:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.1)}
.scp-arrow.r{right:-7px;border-right:1px solid rgba(0,0,0,.1);border-top:1px solid rgba(0,0,0,.1)}
.scp-tools{position:absolute;top:12px;right:12px;display:flex;gap:6px;align-items:center;z-index:3}
.scp-tools button{width:34px;height:34px;border-radius:50%;border:0;background:transparent;color:#5b625a;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.9rem}
.scp-tools .scp-x{background:#eef0ec;color:var(--sc-ink)}
.scp-tools .scp-edit:disabled{color:#c3c8c1;cursor:not-allowed}
.scp-tools .scp-edit:not(:disabled){color:var(--sc-gd)}
.scp-in{padding:18px 20px 14px}
.scp-grab,.scp-cover,.scp-kind,.scp-lbl{display:none}
.scp-title{display:flex;gap:12px;align-items:center;padding-right:84px;min-height:34px}
.scp-sq{width:22px;height:22px;border-radius:7px;background:var(--c,#048C44);flex:none}
.scp-title h3{font-size:1.4rem;font-weight:800;line-height:1.15;margin:0;word-break:break-word}
.scp-when{padding:6px 0 4px 34px;color:#4b524a;font-size:.98rem}
.scp-when .scp-rep{display:block;font-size:.8rem;color:var(--sc-mut);margin-top:2px}
.scp-row{display:flex;gap:14px;align-items:flex-start;padding:7px 0 7px 6px;font-size:.95rem;line-height:1.35}
.scp-row>i{width:22px;text-align:center;color:var(--sc-gd);margin-top:3px;flex:none}
.scp-row .t{min-width:0;white-space:pre-line;word-break:break-word}
.scp-who{display:flex;gap:12px;align-items:center;padding:12px 0 4px 6px}
.scp-ava{width:34px;height:34px;border-radius:50%;background:#373435;color:#fff;font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:none;overflow:hidden;position:relative}
.scp-ava img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.scp-foot{display:flex;gap:12px;align-items:center;justify-content:space-between;border-top:1px solid #e6e9e4;margin-top:12px;padding-top:12px}
.scp-note{font-size:.8rem;color:var(--sc-mut);line-height:1.35}
.scp-more{border:1px solid #d5ddd3;background:#fff;color:var(--sc-gd);border-radius:12px;padding:8px 16px;font-weight:700;font-size:.9rem;white-space:nowrap;font-family:inherit}
.scp-more:disabled{opacity:.55;cursor:not-allowed}
.scp-err{padding:14px 0 6px 34px;color:#b42318;font-size:.95rem}
.scp-load{padding:6px 0 6px 34px;color:var(--sc-mut)}
#sc-pop-bg{display:none}
@media (max-width:767.98px){
  #sc-pop-bg{position:fixed;inset:0;z-index:1089;background:rgba(15,25,18,.5);opacity:0;transition:opacity .25s}
  #sc-pop-bg.show{display:block}#sc-pop-bg.on{opacity:1}
  .scp{left:0!important;right:0;top:auto!important;bottom:0;width:auto;max-width:none;border-radius:22px 22px 0 0;transform:translateY(105%);transition:transform .28s cubic-bezier(.2,.8,.2,1);max-height:90vh;padding-bottom:env(safe-area-inset-bottom)}
  .scp.open{transform:translateY(0)}
  .scp-box{max-height:90vh;border-radius:22px 22px 0 0}
  .scp-arrow{display:none}
  .scp-grab{display:block;position:absolute;top:8px;left:50%;margin-left:-22px;width:44px;height:5px;border-radius:3px;background:rgba(255,255,255,.9);box-shadow:0 0 0 1px rgba(0,0,0,.12);z-index:4}
  .scp-cover{display:block;height:150px;background:#048C44 center/cover no-repeat;position:relative}
  .scp-cover.strip{height:72px;background:linear-gradient(120deg,#037A3B,#048C44);color:#fff;display:flex;align-items:flex-end;padding:0 70px 10px 18px;font-size:.88rem;font-weight:600}
  .scp-cover.strip i{margin-right:7px;margin-bottom:3px}
  .scp-tools button{background:rgba(255,255,255,.95);box-shadow:0 1px 4px rgba(0,0,0,.2);color:var(--sc-ink)}
  .scp-tools .scp-edit:disabled{background:rgba(255,255,255,.7)}
  .scp-in{padding:16px 18px 22px}
  .scp-kind{display:flex;align-items:center;gap:10px;margin-bottom:6px;font-size:.78rem;font-weight:700;color:var(--sc-mut);text-transform:uppercase;letter-spacing:.06em}
  .scp-kind b{display:inline-flex;align-items:center;gap:7px}
  .scp-kind b:before{content:"";width:10px;height:10px;border-radius:3px;background:var(--c)}
  .scp-badge{background:#E3F3EA;color:#037A3B;border-radius:999px;padding:3px 10px;font-size:.74rem;letter-spacing:0;text-transform:none}
  .scp-title{padding-right:0}.scp-sq{display:none}
  .scp-title h3{font-size:1.35rem}
  .scp-lbl{display:block;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--sc-mut);margin-bottom:2px}
  .scp-when{padding:12px 0 6px}
  .scp-when .d,.scp-when .tm{display:block;font-size:1rem}
  .scp-when .dot{display:none}
  .scp-row{padding-left:0}
  .scp-who{padding-left:0}
  .scp-who .pre{display:none}
  .scp-foot{flex-direction:column;align-items:stretch}
  .scp-more{width:100%;padding:11px}
}
@media (min-width:768px){.scp{transition:none}}

/* the add / edit window */
.sc-cell[data-newday],.sc-wcol[data-newday]{cursor:cell}
.sc-cell[data-newday]:hover{background:#f6faf7}.sc-cell.today[data-newday]:hover{background:var(--sc-gl)}
.sc dialog.scf-dlg{width:min(1060px,calc(100vw - 32px));max-height:calc(100vh - 32px);padding:0;border:0;border-radius:22px;background:#fff;color:var(--sc-ink)}
.scf{display:flex;flex-direction:column;max-height:calc(100vh - 32px);font-family:inherit}
.scf-head{display:flex;align-items:center;gap:14px;padding:18px 26px;border-bottom:1px solid var(--sc-line);flex:none}
.scf-ico{width:50px;height:50px;border-radius:14px;background:var(--sc-gl);color:var(--sc-gd);display:inline-flex;align-items:center;justify-content:center;font-size:1.2rem;flex:none}
.scf-head h3{font-size:1.35rem;font-weight:700;margin:0}
.scf-head p{margin:2px 0 0;font-size:.86rem;color:var(--sc-mut)}
.scf-head>div{flex:1;min-width:0}
.scf-x{width:40px;height:40px;border:0;background:transparent;border-radius:50%;cursor:pointer;font-size:1.25rem;color:var(--sc-ink);flex:none}
.scf-x:hover{background:#f1f3ef}
.scf-error{margin:14px 26px 0;background:#fdeceb;color:#b42318;border-radius:12px;padding:10px 14px;font-size:.9rem}
.scf-body{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:28px;padding:20px 26px;overflow:auto;flex:1 1 auto}
.scf label.l{display:block;font-size:.84rem;color:var(--sc-mut);margin:0 0 6px}
.scf .fld{margin-bottom:18px}
.scf input[type=text],.scf input[type=date],.scf input[type=time],.scf select,.scf textarea{width:100%;border:1px solid #d8ddd5;border-radius:14px;padding:12px 14px;font-size:.98rem;font-family:inherit;background:#fff;color:var(--sc-ink)}
.scf input.big{font-size:1.2rem;font-weight:600;padding:16px 18px}
.scf textarea{resize:vertical;min-height:120px}
.scf input:focus,.scf select:focus,.scf textarea:focus{outline:2px solid rgba(4,140,68,.35);border-color:var(--sc-g)}
.scf-pills{display:flex;flex-wrap:wrap;gap:10px}
.scf-pill{position:relative;cursor:pointer}
.scf-pill input{position:absolute;opacity:0;inset:0;cursor:pointer}
.scf-pill span{display:inline-flex;align-items:center;gap:8px;border:1px solid #d8ddd5;border-radius:999px;padding:8px 16px;font-size:.92rem;background:#fff}
.scf-pill i{width:11px;height:11px;border-radius:4px;display:inline-block}
.scf-pill input:checked+span{border-color:var(--sc-g);background:var(--sc-gl);box-shadow:0 0 0 1px var(--sc-g);font-weight:600}
.scf-pill input:focus-visible+span{outline:2px solid var(--sc-g);outline-offset:2px}
.scf-g3{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:14px}
.scf-opts{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin-bottom:18px}
.scf-opts .grow{flex:1 1 200px;min-width:160px}
.scf-opts small,.scf-note2{color:var(--sc-mut);font-size:.82rem}
.scf-swrow{display:flex;align-items:center;gap:12px;cursor:pointer;font-size:.98rem}
.scf-swrow span{flex:1}
input.scf-sw{appearance:none;-webkit-appearance:none;width:48px;height:28px;border-radius:999px;background:#d8ddd5;position:relative;cursor:pointer;flex:none;border:0;margin:0;transition:background .15s}
input.scf-sw:before{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:transform .15s}
input.scf-sw:checked{background:var(--sc-g)}input.scf-sw:checked:before{transform:translateX(20px)}
input.scf-sw:focus-visible{outline:2px solid var(--sc-g);outline-offset:2px}
.scf-loc{position:relative}.scf-loc i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:var(--sc-gd)}.scf .scf-loc input[type=text]{padding-left:42px}
.scf-cover{height:176px;border-radius:16px;background:#e9efe9 center/cover no-repeat;position:relative;overflow:hidden}
.scf-cover.ph{background:linear-gradient(135deg,#037A3B,#048C44)}
.scf-cover.ph:after{content:"\f03e";font-family:"Font Awesome 6 Free","Font Awesome 5 Free";font-weight:900;color:rgba(255,255,255,.45);font-size:3rem;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%)}
.scf-cbtn{position:absolute;right:12px;bottom:12px;display:flex;gap:8px}
.scf-cbtn button{border:0;background:#fff;border-radius:999px;padding:8px 14px;font-weight:600;font-size:.84rem;box-shadow:0 2px 8px rgba(0,0,0,.2);cursor:pointer;font-family:inherit}
.scf-panel{background:#f6f8f5;border-radius:16px;padding:18px 20px;margin-bottom:18px}
.scf-panel h4{font-size:.74rem;letter-spacing:.14em;font-weight:700;color:var(--sc-mut);text-transform:uppercase;margin:0 0 14px}
.scf-panel .scf-swrow{padding:7px 0}
.scf-panel hr{border:0;border-top:1px solid #e2e7df;margin:10px 0}
.scf-cli{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.scf-cli input[type=checkbox]{width:22px;height:22px;accent-color:var(--sc-g);flex:none}
.scf-cli input[type=text]{padding:8px 12px;border-radius:10px}
.scf-cli button{border:0;background:transparent;color:var(--sc-mut);cursor:pointer;width:30px;height:30px;border-radius:50%}
.scf-add{border:0;background:transparent;color:var(--sc-gd);font-weight:600;cursor:pointer;padding:4px 0;font-family:inherit;font-size:.9rem}
.scf-foot{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:16px 26px;border-top:1px solid var(--sc-line);flex:none;background:#fff;border-radius:0 0 22px 22px}
.scf-foot .scf-note2{flex:1 1 220px}
.sc dialog.scf-ask{width:min(420px,calc(100vw - 32px));border:0;border-radius:18px;padding:22px 24px;box-shadow:0 20px 60px rgba(0,0,0,.35);font-family:inherit;color:var(--sc-ink)}
.scf-ask::backdrop{background:rgba(15,25,18,.4)}
.scf-ask h4{font-size:1.1rem;font-weight:700;margin:0 0 6px}.scf-ask p{margin:0 0 18px;color:var(--sc-mut);font-size:.92rem}
.scf-ask div{display:flex;gap:10px;justify-content:flex-end}
@media (max-width:767.98px){
  .sc dialog.scf-dlg{width:100%;max-width:100%;height:100vh;height:100dvh;max-height:none;border-radius:0;margin:0;inset:0}
  .scf{height:100%;max-height:none}
  .scf-head{padding:12px 14px}.scf-ico{width:42px;height:42px}.scf-head h3{font-size:1.15rem}
  .scf-body{grid-template-columns:1fr;gap:0;padding:16px 14px}
  .scf-g3{grid-template-columns:1fr 1fr}.scf-g3>.fld:first-child{grid-column:1/-1}
  .scf-foot{padding:10px 14px calc(10px + env(safe-area-inset-bottom));border-radius:0;gap:8px}
  .scf-foot .scf-note2{flex-basis:100%;font-size:.74rem;order:-1}
  .scf-foot .sc-btn{flex:1;justify-content:center}
  .scf-foot .sc-btn.danger{flex-basis:100%;order:-1}
}

/* the full details panel (slides in from the right; full screen on phones). Moved to <body> by the script, so it does not use the .sc wrapper. */
.scd,.scd *{box-sizing:border-box}
.scd [hidden]{display:none!important}
#scd-bg{display:none;position:fixed;inset:0;z-index:1094;background:rgba(15,25,18,.5);opacity:0;transition:opacity .28s}
#scd-bg.show{display:block}#scd-bg.on{opacity:1}
.scd{--sc-g:#048C44;--sc-gd:#037A3B;--sc-gl:#E3F3EA;--sc-ink:#1d1b1c;--sc-mut:#6b7067;position:fixed;z-index:1095;top:0;right:0;bottom:0;width:40vw;min-width:430px;max-width:720px;background:#fff;color:var(--sc-ink);font-family:inherit;
  display:flex;flex-direction:column;transform:translateX(104%);transition:transform .3s cubic-bezier(.2,.8,.2,1);box-shadow:-12px 0 40px rgba(0,0,0,.25);outline:0}
.scd.open{transform:none}
.scd-x{position:absolute;top:16px;right:16px;z-index:5;width:44px;height:44px;border-radius:50%;border:0;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);cursor:pointer;font-size:1.1rem;color:var(--sc-ink)}
.scd-scroll{flex:1 1 auto;overflow:auto}
.scd-cover{height:230px;background:linear-gradient(135deg,#037A3B,#048C44) center/cover no-repeat;position:relative;display:flex;align-items:flex-end}
.scd-cover.strip{height:120px}
.scd-cover span{color:#fff;font-weight:700;padding:0 20px 14px;font-size:.95rem;text-shadow:0 1px 3px rgba(0,0,0,.35)}
.scd-cover span i{margin-right:8px}
.scd-in{padding:20px 26px 26px}
.scd-tags{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.scd-type{display:inline-flex;gap:7px;align-items:center;border-radius:999px;padding:5px 13px;font-size:.84rem;font-weight:700}
.scd-badge{background:var(--sc-gl);color:var(--sc-gd);border-radius:999px;padding:5px 13px;font-size:.84rem;font-weight:700}
.scd h2{font-size:1.9rem;font-weight:800;line-height:1.15;margin:12px 0 18px;word-break:break-word}
.scd-sec{display:flex;gap:16px;margin-bottom:20px}
.scd-sec>i{width:22px;text-align:center;color:var(--sc-gd);margin-top:3px;flex:none}
.scd-sec small,.scd-who small{display:block;color:var(--sc-mut);font-size:.88rem}
.scd-sec b{display:block;font-size:1.18rem;font-weight:600;line-height:1.3}
.scd-sec span,.scd-who>div>span{display:block;color:var(--sc-mut);font-size:.88rem;line-height:1.4}
.scd-sec p{margin:2px 0 0;font-size:1.05rem;line-height:1.5;white-space:pre-line;word-break:break-word}
.scd-box{background:#f6f8f5;border-radius:16px;padding:16px 18px;margin-bottom:16px}
.scd-box h4,.scd-rem h4{font-size:.74rem;letter-spacing:.14em;font-weight:700;color:var(--sc-mut);text-transform:uppercase;margin:0 0 12px}
.scd-ck{display:flex;gap:12px;align-items:center;padding:6px 0;cursor:pointer;font-size:1.02rem}
.scd-ck input{width:24px;height:24px;accent-color:var(--sc-g);flex:none;cursor:pointer}
.scd-ck input:checked+span{color:var(--sc-mut);text-decoration:line-through}
.scd-ckerr{color:#b42318;font-size:.84rem;margin-top:6px}
.scd-who{display:flex;gap:14px;align-items:center;border:1px solid #e6e9e4;border-radius:18px;padding:16px 18px;margin-bottom:20px}
.scd-who .scp-ava{width:54px;height:54px;font-size:1rem;flex:none}
.scd-who b{display:block;font-size:1.15rem}
.scd-rem{margin-bottom:10px}
.scd-pills{display:flex;flex-wrap:wrap;gap:8px}
.scd-pills span{background:#f1f3ef;border-radius:999px;padding:6px 13px;font-size:.84rem;font-weight:600;display:inline-flex;gap:7px;align-items:center}
.scd-pills i{color:var(--sc-gd);font-size:.74rem}
.scd-rem p{margin:10px 0 0;color:var(--sc-mut);font-size:.9rem}
.scd-bar{flex:none;display:flex;gap:12px;align-items:center;padding:14px 22px calc(14px + env(safe-area-inset-bottom));border-top:1px solid #e6e9e4;background:#fafbf9}
.scd-bar .sc-btn:disabled{opacity:.45;cursor:not-allowed}
.scd-bar .scd-note{flex:1;color:var(--sc-mut);font-size:.82rem;line-height:1.35}
.scd-load,.scd-err{color:var(--sc-mut);padding:6px 0 20px}.scd-err{color:#b42318}
@media (max-width:767.98px){
  .scd{width:100%;min-width:0;max-width:none}
  .scd-cover{height:190px}.scd-cover.strip{height:110px}
  .scd-in{padding:16px 18px 22px}.scd h2{font-size:1.5rem}
  .scd-bar{padding:10px 14px calc(10px + env(safe-area-inset-bottom));flex-wrap:wrap;gap:8px}
  .scd-bar .scd-note{flex-basis:100%;order:-1;font-size:.76rem}
  .scd-bar .sc-btn{flex:1;justify-content:center}
}

/* birthdays: pink chips, the birthday banner, the "coming up" box and the small dialogs */
.sc-bday{display:flex;align-items:center;gap:5px;cursor:default;color:var(--sc-ink)}
.sc-bday .sc-n{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}
.sc-bava{width:17px;height:17px;border-radius:50%;background:#373435;color:#fff;font-size:.5rem;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex:none;position:relative;overflow:hidden;vertical-align:middle}
.sc-bava img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.sc-bava-l{width:24px;height:24px;font-size:.62rem;margin-right:6px}
.sc-bday .fa-cake-candles{color:#d6457f;opacity:1}
.sc-bdrow{cursor:default;border-radius:0 10px 10px 0}.sc-bdrow:hover{background:#fff}
.sc-bd{background:#373435;color:#fff;border-radius:22px;padding:24px 30px;margin:14px 0 4px;display:flex;gap:28px;align-items:center;position:relative;overflow:hidden}
.sc-bd:after{content:"";position:absolute;right:-70px;top:-60px;width:300px;height:230px;background:#048C44;transform:rotate(-28deg);border-radius:34px;opacity:.9;pointer-events:none}
.sc-bd-ava{width:140px;height:140px;border-radius:50%;border:6px solid #d6457f;background:#373435;color:#fff;font-size:2.8rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex:none;position:relative;overflow:hidden;z-index:1;box-shadow:0 0 0 4px rgba(255,255,255,.9) inset}
.sc-bd-ava img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.sc-bd-txt{position:relative;z-index:1;min-width:0}
.sc-bd-top{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:6px}
.sc-bd-pill{background:#d6457f;color:#fff;border-radius:999px;padding:4px 12px;font-size:.74rem;font-weight:800;letter-spacing:.04em}
.sc-bd-meta{font-size:.84rem;opacity:.85}
.sc-bd h2{font-size:1.75rem;font-weight:800;margin:0 0 6px;color:#fff}
.sc-bd p{margin:0;font-size:1.02rem;line-height:1.5;max-width:760px}
.sc-bdi{display:flex;gap:12px;align-items:center;padding:8px 0}
.sc-bdi .sc-bava{width:40px;height:40px;font-size:.8rem;border:2px solid #d6457f}
.sc-bdi b{display:block;font-size:.92rem}.sc-bdi small{color:var(--sc-mut);font-size:.8rem}
.sc-bdi>div{flex:1;min-width:0}.sc-bdi>i{color:#d6457f}
.sc-bdlinks{display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:10px;font-size:.86rem}
.sc-bdlinks button,.sc-bdlinks a{background:none;border:0;padding:0;color:var(--sc-gd);font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit;font-size:inherit}
.sc dialog.scf-mini{width:min(470px,calc(100vw - 24px))}
.scf-mini .scf{max-height:none;display:block}
.scf-mini .g3{display:grid;grid-template-columns:1fr 1.3fr 1fr;gap:10px;margin-bottom:12px}
.scf-mini label.l{margin-bottom:4px}
.scf-mini .scf-error{margin:0 0 10px}
.scf-mini .row-btn{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:14px}
@media (max-width:767.98px){
  .sc-bd{flex-direction:column;text-align:center;padding:22px 18px;gap:14px;border-radius:18px}
  .sc-bd-ava{width:110px;height:110px;font-size:2.2rem;border-width:5px}
  .sc-bd-top{justify-content:center;flex-direction:column;gap:6px}
  .sc-bd h2{font-size:1.4rem}
  .sc-bava{width:14px;height:14px;font-size:.42rem}
  .scf-mini .g3{grid-template-columns:1fr 1fr}.scf-mini .g3>div:last-child{grid-column:1/-1}
}

/* the gift tracker */
.sg-card,.sg-panel{background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(29,27,28,.04),0 6px 20px rgba(29,27,28,.05);padding:18px 22px;margin:14px 0 4px}
.sg-ch{display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.sg-ch b{display:block;font-size:1.05rem}.sg-ch small{color:var(--sc-mut);font-size:.84rem}
.sg-card p{margin:10px 0 0;color:var(--sc-mut);font-size:.88rem;line-height:1.45}
.sg-btn{background:var(--sc-g);color:#fff;border:0;border-radius:14px;padding:11px 22px;font-weight:700;font-size:.92rem;cursor:pointer;font-family:inherit;box-shadow:0 4px 12px rgba(4,140,68,.25);display:inline-flex;gap:8px;align-items:center;justify-content:center}
.sg-btn:hover{background:var(--sc-gd)}.sg-btn:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}
.sg-btn.ghost{background:#fff;color:var(--sc-gd);border:1px solid #cfe3d6;box-shadow:none}
.sg-btn.sm{padding:7px 14px;font-size:.82rem;border-radius:12px}
.sg-ph{display:flex;gap:10px;justify-content:space-between;align-items:baseline;flex-wrap:wrap}.sg-ph h3{font-size:1.1rem;font-weight:700;margin:0}.sg-ph span{color:var(--sc-mut);font-size:.84rem}
.sg-sum{display:flex;gap:16px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin:12px 0 10px}
.sg-big{font-size:1.9rem;font-weight:800;line-height:1.1}.sg-line{color:var(--sc-mut);font-size:.86rem;margin-top:2px}
.sg-bar{height:8px;border-radius:99px;background:#e9ece7;overflow:hidden;margin-bottom:6px}.sg-bar i{display:block;height:100%;background:var(--sc-g);border-radius:99px}
.sg-row{display:flex;gap:12px;align-items:center;padding:10px 0;border-top:1px solid var(--sc-line)}
.sg-ava{width:44px;height:44px;font-size:.78rem}
.sg-who{flex:1;min-width:0}.sg-who b{display:block;font-size:.96rem}.sg-who small{color:var(--sc-mut);font-size:.82rem}
.sg-pill{border-radius:999px;padding:4px 12px;font-size:.78rem;font-weight:700;white-space:nowrap;background:#eee;color:#555}
.sg-pill.received{background:var(--sc-gl);color:var(--sc-gd)}.sg-pill.says_sent{background:#fdf0d5;color:#9a6700}
.sg-msg{margin:8px 0;padding:8px 12px;border-radius:10px;background:#fdeceb;color:#b42318;font-size:.86rem}
.sg-note{display:flex;gap:12px;align-items:center;background:#fff;border-left:4px solid var(--sc-g);border-radius:14px;padding:12px 16px;margin:14px 0 4px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.sg-note i{color:var(--sc-gd)}.sg-note span{flex:1}.sg-note button{background:none;border:0;color:var(--sc-gd);font-weight:700;cursor:pointer;font-family:inherit}
@media (max-width:767.98px){
  .sg-card,.sg-panel{padding:14px;border-radius:16px}
  .sg-ch,.sg-sum{flex-direction:column;align-items:stretch}
  .sg-sum{flex-direction:column-reverse}
  .sg-btn{width:100%;padding:14px}.sg-btn.sm{width:auto;padding:9px 14px}
  .sg-big{font-size:1.6rem}
  .sg-row{flex-wrap:wrap}.sg-row .sg-who{flex-basis:calc(100% - 60px)}
}

/* projects: a dark bar across the days with the progress on it */
.sc-pbar{display:flex;align-items:center;background:#373435!important;color:#fff!important;border:0!important;border-radius:0;margin:0 -7px;width:auto!important;overflow:visible!important;text-overflow:clip!important;text-decoration:none;position:relative;min-height:22px;padding:0!important;cursor:pointer}
.sc-pbar.pb-start{margin-left:0;border-radius:7px 0 0 7px}.sc-pbar.pb-end{margin-right:0;border-radius:0 7px 7px 0}.sc-pbar.pb-start.pb-end{border-radius:7px}
.sc-pbar:hover{background:#2a2828!important}
.pb-t{position:relative;z-index:3;padding-left:8px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:calc(var(--span,1) * 100% - 20px);flex:none}
.pb-p{position:relative;z-index:3;margin-left:auto;margin-right:5px;background:#048C44;border-radius:999px;padding:0 8px;font-size:.68rem;font-weight:800;line-height:18px}
.sc-pbar.pb-rs.pb-re .pb-t{flex:1 1 auto;min-width:0;max-width:none}
.sc-prow{text-decoration:none;color:inherit;border-radius:0 10px 10px 0}.sc-prow:hover{background:#f6faf7}
/* the task list in the form (type Project) */
.scf.is-project .pj-hide{display:none!important}.scf:not(.is-project) .pj-only{display:none!important}
.scf-tk{border:1px solid #e0e5dd;background:#fff;border-radius:12px;padding:10px;margin-bottom:10px}
.scf-tk .l1{display:flex;gap:8px;align-items:center;margin-bottom:6px}
.scf-tk .l1 input[type=checkbox]{width:22px;height:22px;accent-color:var(--sc-g);flex:none}
.scf-tk .l2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:6px}.scf-tk .l2 .tw{order:1}.scf-tk .l2 .tz{order:2}.scf-tk .l2 .ts{order:3}.scf-tk .l2 .td{order:4}
.scf-tk input[type=text],.scf-tk input[type=date],.scf-tk select{padding:7px 9px!important;border-radius:9px!important;font-size:.84rem!important}
.scf-tk button.rm{border:0;background:transparent;color:var(--sc-mut);cursor:pointer;width:28px;height:28px;border-radius:50%;flex:none}


/* holidays, the settings button, My settings */
.sc-hol{display:flex;align-items:center;gap:5px;cursor:default;color:var(--sc-ink)}.sc-hol .sc-n{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}.sc-hol .fa-flag{color:#1A9A9A;opacity:1}
.sc-titlebar{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.sc-gear{width:42px;height:42px;border-radius:50%;background:#fff;border:1px solid var(--sc-line);color:var(--sc-ink);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;flex:none;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.sc-gear:hover{background:var(--sc-gl);color:var(--sc-gd)}
.sc-hico{width:40px;height:40px;border-radius:12px;background:#dff3f3;color:#1A9A9A;display:inline-flex;align-items:center;justify-content:center;flex:none}
.sc-pillx{display:inline-block;margin-top:6px;background:#fdf0d5;color:#9a6700;border-radius:999px;padding:3px 12px;font-size:.76rem;font-weight:700}
/* phone */
@media (max-width:991.98px){.sc-layout{grid-template-columns:1fr}.sc-label{font-size:1.2rem}.sc-banner h2{font-size:1.25rem}}
@media (max-width:767.98px){
  .sc-banner{padding:18px;border-radius:16px}
  .sc-cover{height:84px}
  .sc-bar{gap:8px}.sc-search{max-width:none;flex-basis:100%;order:9}.sc-spacer{display:none}
  .sc-nav{width:100%}.sc-nav .sc-label{flex:1;font-size:1.05rem}
  .sc-views{width:100%;display:flex}.sc-views a{flex:1;text-align:center;padding:6px 4px}
  .sc-btn{flex:1;justify-content:center}
  .sc-month,.sc-dh,.sc-cell{display:none}
  .sc-strip{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;padding:12px 10px;border-bottom:1px solid var(--sc-line)}
  .sc-sd{border:0;background:transparent;border-radius:14px;padding:6px 0 8px;display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;color:var(--sc-ink)}
  .sc-sd small{font-size:.66rem;font-weight:700;letter-spacing:.05em;color:var(--sc-mut);text-transform:uppercase}
  .sc-sd b{font-size:1rem;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%}
  .sc-sd.today b{border:2px solid var(--sc-g)}
  .sc-sd.on{background:var(--sc-gl)}
  .sc-sd.on b{background:var(--sc-g);color:#fff}
  .sc-dots{display:flex;gap:3px;height:6px}.sc-dots i{width:6px;height:6px;border-radius:50%;background:var(--c)}
  .sc-phone-nav{display:flex}
  .sc-desk-nav{display:none}
  .sc-dl{display:none}.sc-dl.on{display:block}
  .sc-week{grid-template-columns:1fr}.sc-wcol{min-height:0;border-right:0;border-bottom:1px solid var(--sc-line)}
  .sc-rt{flex-basis:86px}
}
@media (min-width:768px){.sc-dl{display:none!important}}
</style>

<div class="sc">
<div class="sc-title sc-titlebar"><div><h1>Staff Calendar</h1><p>Private to tutors and the super admin · all times are Nairobi time (EAT)</p></div>
    <?php if ($actor['role'] === 'superadmin'): ?><a class="sc-gear" href="<?php echo $e(url('superadmin/staffcalendar/settings.php')); ?>" title="Settings · Staff Calendar" aria-label="Settings · Staff Calendar"><i class="fas fa-gear"></i></a><?php endif; ?></div>

<?php foreach ($bdToday as $bd): $bdFirst = $bd['first']; ?>
<section class="sc-bd" aria-label="Birthday">
    <?php echo $bdAva($bd, 'sc-bd-ava'); ?>
    <div class="sc-bd-txt">
        <div class="sc-bd-top"><span class="sc-bd-pill"><i class="fas fa-cake-candles"></i> BIRTHDAY · TODAY</span>
            <?php if ($bd['sees_count'] && $bd['message_no']): ?><span class="sc-bd-meta">Message <?php echo (int) $bd['message_no']; ?> of <?php echo (int) $bd['message_total']; ?> · never repeated for <?php echo $bd['own'] ? 'you' : $e($bdFirst); ?></span><?php endif; ?></div>
        <?php if ($bd['own']): ?><h2>Happy Birthday, <?php echo $e($bdFirst); ?>!</h2><?php if ($bd['message'] !== ''): ?><p><?php echo $e($bd['message']); ?></p><?php endif; ?>
        <?php else: ?><h2>Today is <?php echo $e($bdFirst); ?>’s birthday</h2><?php if ($actor['role'] === 'superadmin' && $bd['message'] !== ''): ?><p><?php echo $e($bd['message']); ?></p><?php endif; ?><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php if ($gfOn): ?>
<?php foreach ($gf['notes'] as $gn): ?>
<div class="sg-note" role="status"><i class="fas fa-gift"></i><span><?php echo $e($gn['message']); ?></span><button type="button" data-gift="note_dismiss" data-note="<?php echo (int) $gn['id']; ?>" aria-label="Dismiss">Dismiss</button></div>
<?php endforeach; ?>
<?php foreach ($gf['cards'] as $gc): ?>
<section class="sg-card" aria-label="Your gift">
    <?php if ($gc['status'] === 'not_yet'): ?>
    <div class="sg-ch"><div><b>Your gift to <?php echo $e($gc['first']); ?></b><small><?php echo $e(sg_kes($gc['amount'])); ?> · only you and <?php echo $e($gc['first']); ?> see your status</small></div>
        <button type="button" class="sg-btn" data-gift="send" data-list="<?php echo (int) $gc['list_id']; ?>">I have sent</button></div>
    <p>Dazu does not move any money. Send it the way you always do, then tap the button. <?php echo $e($gc['first']); ?> confirms, and your name turns green.</p>
    <?php else: ?>
    <div class="sg-ch"><div><b>Your gift to <?php echo $e($gc['first']); ?></b><small>You said you sent <?php echo $e(sg_kes($gc['amount'])); ?>. <?php echo $e($gc['first']); ?> will confirm it.</small></div>
        <button type="button" class="sg-btn ghost" data-gift="undo" data-list="<?php echo (int) $gc['list_id']; ?>">Undo</button></div>
    <?php endif; ?>
    <div class="sg-msg" role="alert" hidden></div>
</section>
<?php endforeach; ?>
<?php foreach ($gf['panels'] as $gp): $pct = $gp['expected'] > 0 ? (int) round($gp['received'] * 100 / $gp['expected']) : 0; ?>
<section class="sg-panel" aria-label="Birthday gifts">
    <div class="sg-ph"><h3>Birthday gifts<?php echo $gp['own'] ? '' : ' · ' . $e($gp['first']) . '’s birthday'; ?></h3><span>Only <?php echo $gp['own'] ? 'you' : $e($gp['first']); ?> and the super admin see this list</span></div>
    <div class="sg-sum"><div><div class="sg-big"><?php echo (int) $gp['received']; ?> of <?php echo (int) $gp['expected']; ?> received</div>
        <div class="sg-line"><?php echo $e(sg_kes($gp['received_kes'])); ?> of <?php echo $e(sg_kes($gp['expected_kes'])); ?> · <?php echo $e(sg_kes($gp['amount'])); ?> each</div></div>
        <?php if ($gp['can_remind']): ?><button type="button" class="sg-btn" data-gift="remind" data-list="<?php echo (int) $gp['id']; ?>"<?php echo $gp['reminded_today'] ? ' disabled' : ''; ?>><i class="fas fa-bell"></i> <?php echo $gp['reminded_today'] ? 'Already sent today' : 'Remind those who have not sent'; ?></button><?php endif; ?></div>
    <div class="sg-bar" role="progressbar" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?php echo $pct; ?>%"></i></div>
    <div class="sg-msg" role="alert" hidden></div>
    <div class="sg-rows">
        <?php foreach ($gp['items'] as $it): $lbl = ['received' => 'Received', 'says_sent' => 'Says sent', 'not_yet' => 'Not yet', 'excluded' => 'Excluded'][$it['status']]; ?>
        <div class="sg-row"><?php echo $bdAva($it, 'sc-bava sg-ava'); ?><div class="sg-who"><b><?php echo $e($it['name']); ?></b><small><?php echo $e($it['role']); ?></small></div>
            <?php if ($it['status'] === 'says_sent' && $gp['can_confirm']): ?><button type="button" class="sg-btn sm" data-gift="confirm" data-list="<?php echo (int) $gp['id']; ?>" data-giver="<?php echo (int) $it['giver_id']; ?>">Confirm received</button><?php endif; ?>
            <span class="sg-pill <?php echo $e($it['status']); ?>"<?php echo $it['confirmed_by'] !== '' ? ' title="Confirmed by ' . $e($it['confirmed_by']) . '"' : ''; ?>><?php echo $it['status'] === 'received' ? '<i class="fas fa-check"></i> ' : ''; ?><?php echo $e($lbl); ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php if (!$gp['open']): ?><div class="sg-line" style="margin-top:8px">This list is closed. It is read-only history now.</div><?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<div class="sc-banner">
    <div>
        <h2><?php echo $e($greet . ', ' . $actor['first']); ?></h2>
        <p><?php echo $e($now->format('l, j F')); ?> · <?php echo $e($plural($todayCount, 'event')); ?> today ·
            <?php echo $nextEv ? 'Next: ' . $e($nextEv['title']) . ' ' . $e(scal_in_days_words($nextEv, $now)) : 'Nothing coming up'; ?></p>
    </div>
    <div class="sc-stat"><small>This week</small><b><?php echo $e($plural($weekCount, 'event')); ?></b></div>
</div>

<div class="sc-bar">
    <div class="sc-nav sc-desk-nav">
        <a class="sc-circ" href="<?php echo $e($link(['d' => $prev->format('Y-m-d')])); ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
        <a class="sc-circ" href="<?php echo $e($link(['d' => $next->format('Y-m-d')])); ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
        <h2 class="sc-label"><?php echo $e($label); ?></h2>
        <a class="sc-pill" href="<?php echo $e($link(['d' => $todayS])); ?>">Today</a>
    </div>
    <?php if ($view === 'month'): $wk = $monday($day); $wkEnd = (clone $wk)->modify('+6 days'); ?>
    <div class="sc-nav sc-phone-nav">
        <a class="sc-circ" href="<?php echo $e($link(['d' => (clone $day)->modify('-7 days')->format('Y-m-d')])); ?>" aria-label="Previous week"><i class="fas fa-chevron-left"></i></a>
        <a class="sc-circ" href="<?php echo $e($link(['d' => (clone $day)->modify('+7 days')->format('Y-m-d')])); ?>" aria-label="Next week"><i class="fas fa-chevron-right"></i></a>
        <h2 class="sc-label"><?php echo $e($wk->format('j M') . ' – ' . $wkEnd->format('j M Y')); ?></h2>
        <a class="sc-pill" href="<?php echo $e($link(['d' => $todayS])); ?>">Today</a>
    </div>
    <?php else: ?>
    <div class="sc-nav sc-phone-nav">
        <a class="sc-circ" href="<?php echo $e($link(['d' => $prev->format('Y-m-d')])); ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
        <a class="sc-circ" href="<?php echo $e($link(['d' => $next->format('Y-m-d')])); ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
        <h2 class="sc-label"><?php echo $e($label); ?></h2>
        <a class="sc-pill" href="<?php echo $e($link(['d' => $todayS])); ?>">Today</a>
    </div>
    <?php endif; ?>
    <div class="sc-views" role="tablist" aria-label="View">
        <?php foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'] as $k => $l): ?>
        <a class="<?php echo $view === $k ? 'on' : ''; ?>" href="<?php echo $e($link(['view' => $k])); ?>"><?php echo $l; ?></a>
        <?php endforeach; ?>
    </div>
    <span class="sc-spacer"></span>
    <form class="sc-search" method="get" action="<?php echo $e($sc_base); ?>" role="search">
        <input type="hidden" name="view" value="<?php echo $e($view); ?>"><input type="hidden" name="d" value="<?php echo $e($dayS); ?>">
        <i class="fas fa-magnifying-glass"></i><input type="search" name="q" value="<?php echo $e($q); ?>" placeholder="Search events" aria-label="Search events by title" maxlength="100">
    </form>
    <button type="button" class="sc-btn" data-new="<?php echo $e($dayS); ?>"><i class="fas fa-plus"></i>New event</button>
</div>

<div class="sc-layout">
<div class="sc-main">
<div class="sc-card">
<?php if ($q !== ''): $found = scal_search($db, $actor, $q, $now); ?>
    <div class="sc-gh">Results for “<?php echo $e($q); ?>” · <a href="<?php echo $e($link(['q' => null])); ?>" style="text-transform:none;letter-spacing:0">Clear search</a></div>
    <?php if (!$found): ?><div class="sc-empty">No event has “<?php echo $e($q); ?>” in its title.</div><?php endif; ?>
    <div class="sc-list">
    <?php foreach ($found as $o): ?>
        <div class="sc-gh" style="padding-top:8px;padding-bottom:0"><a href="<?php echo $e($link(['view' => 'day', 'd' => $o['occ_date'], 'q' => null])); ?>"><?php echo $e((new DateTime($o['occ_date'], $tz))->format('D j M Y')); ?><?php echo $o['finished'] ? ' · finished' : ''; ?></a></div>
        <?php echo $row($o, '', true); ?>
    <?php endforeach; ?>
    </div>
<?php elseif ($view === 'month'): ?>
    <div class="sc-month">
        <?php foreach ($dow as $i => $dn): ?><div class="sc-dh<?php echo $i >= 5 ? ' we' : ''; ?>"><?php echo strtoupper($dn); ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < 42; $i++):
            $cd = (clone $from)->modify("+$i days"); $cs = $cd->format('Y-m-d'); $evs = $byDay[$cs] ?? [];
            $cls = ($cd->format('Y-m') !== $monthFirst->format('Y-m') ? ' out' : '') . ($cs === $todayS ? ' today' : ''); ?>
        <div class="sc-cell<?php echo $cls; ?>" data-date="<?php echo $cs; ?>" data-newday="<?php echo $cs; ?>">
            <a class="sc-dn" href="<?php echo $e($link(['view' => 'day', 'd' => $cs])); ?>" aria-label="<?php echo $e($cd->format('l j F')); ?>"><?php echo $cd->format('j') === '1' ? $e($cd->format('j M')) : $cd->format('j'); ?></a>
            <?php $vis = 0; foreach ($evs as $o): if ($isPr($o)) { echo $chip($o, $cs); continue; } echo $vis++ >= 3 ? str_replace('class="sc-chip', 'class="sc-over sc-chip', $chip($o, $cs)) : $chip($o, $cs); endforeach; ?>
            <a class="sc-more" href="<?php echo $e($link(['view' => 'day', 'd' => $cs])); ?>"<?php echo $vis > 3 ? '' : ' hidden'; ?>>+<?php echo max(0, $vis - 3); ?> more</a>
        </div>
        <?php endfor; ?>
    </div>
    <?php // Phones: a one-week strip with the day's events listed below it
    $wk = $monday($day); ?>
    <div class="sc-strip" role="group" aria-label="Week">
        <?php for ($i = 0; $i < 7; $i++): $cd = (clone $wk)->modify("+$i days"); $cs = $cd->format('Y-m-d'); ?>
        <button type="button" class="sc-sd<?php echo ($cs === $dayS ? ' on' : '') . ($cs === $todayS ? ' today' : ''); ?>" data-date="<?php echo $cs; ?>" aria-label="<?php echo $e($cd->format('l j F')); ?>">
            <small><?php echo $e($dow[$i]); ?></small><b><?php echo $cd->format('j'); ?></b>
            <span class="sc-dots"><?php foreach (array_slice($byDay[$cs] ?? [], 0, 3) as $o): ?><i data-type="<?php echo $e($o['type']); ?>" style="--c:<?php echo $e($o['color']); ?>"></i><?php endforeach; ?></span>
        </button>
        <?php endfor; ?>
    </div>
    <?php for ($i = 0; $i < 7; $i++): $cd = (clone $wk)->modify("+$i days"); $cs = $cd->format('Y-m-d'); $evs = $byDay[$cs] ?? []; ?>
    <div class="sc-dl<?php echo $cs === $dayS ? ' on' : ''; ?>" data-date="<?php echo $cs; ?>">
        <div class="sc-gh"><?php echo $e($cd->format('l j F')); ?></div>
        <?php if (!$evs): ?><div class="sc-empty" style="padding-top:4px">Nothing planned.</div><?php endif; ?>
        <div class="sc-list"><?php foreach ($evs as $o) echo $row($o, $cs); ?></div>
    </div>
    <?php endfor; ?>
<?php elseif ($view === 'week'): ?>
    <div class="sc-week">
        <?php for ($i = 0; $i < 7; $i++): $cd = (clone $from)->modify("+$i days"); $cs = $cd->format('Y-m-d'); ?>
        <div class="sc-wcol<?php echo $cs === $todayS ? ' today' : ''; ?>" data-newday="<?php echo $cs; ?>">
            <a class="sc-wh" href="<?php echo $e($link(['view' => 'day', 'd' => $cs])); ?>"><?php echo $e($dow[$i] . ' ' . $cd->format('j')); ?></a>
            <?php foreach ($byDay[$cs] ?? [] as $o) echo $chip($o, $cs); ?>
        </div>
        <?php endfor; ?>
    </div>
<?php elseif ($view === 'day'): $evs = $byDay[$dayS] ?? []; ?>
    <div data-newday="<?php echo $dayS; ?>" style="min-height:320px">
    <?php if (!$evs): ?><div class="sc-empty">Nothing planned for this day. Click here to add an event.</div><?php endif; ?>
    <div class="sc-list" style="padding:8px 0"><?php foreach ($evs as $o) echo $row($o, $dayS); ?></div>
    </div>
<?php else: ?>
    <?php if (!$byDay): ?><div class="sc-empty">No events in <?php echo $e($label); ?>.</div><?php endif; ?>
    <?php foreach ($byDay as $cs => $evs): ?>
        <div class="sc-gh"><a href="<?php echo $e($link(['view' => 'day', 'd' => $cs])); ?>"><?php echo $e((new DateTime($cs, $tz))->format('l j F')); ?></a></div>
        <div class="sc-list"><?php foreach ($evs as $o) echo $row($o, $cs, true); ?></div>
    <?php endforeach; ?>
    <div style="height:10px"></div>
<?php endif; ?>
</div>
</div>

<aside class="sc-side">
    <div class="sc-card sc-up">
        <?php $cv = $nextEv && $nextEv['cover'] !== '' ? url($nextEv['cover']) : ''; ?>
        <div class="sc-cover<?php echo $cv ? ' img' : ''; ?>"<?php echo $cv ? ' style="background-image:url(\'' . $e($cv) . '\')"' : ''; ?>></div>
        <div class="sc-pad">
            <div style="display:flex;justify-content:space-between;align-items:center"><span class="sc-eyebrow">Up next</span><?php if ($nextEv): ?><span class="sc-badge"><?php echo $e(scal_in_days($nextEv, $now)); ?></span><?php endif; ?></div>
            <?php if ($nextEv): ?>
                <h3><?php echo $e($nextEv['title']); ?></h3>
                <p><?php echo $e(scal_when($nextEv)); ?></p>
                <?php if ($nextEv['location'] !== ''): ?><p><?php echo $e($nextEv['location']); ?></p><?php endif; ?>
                <p>Added by <?php echo $e($nextEv['owner']); ?></p>
                <div class="sc-actions"><button type="button" class="sc-btn ghost" data-d="<?php echo $e($nextEv['occ_date']); ?>" data-ev="<?php echo $evData($nextEv); ?>">Details</button></div>
            <?php else: ?>
                <p style="margin-top:10px">Nothing coming up. Use “New event” to add one.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="sc-card sc-pad" id="sc-bdbox"><h4>Birthdays coming up</h4>
        <?php if (!$bdOn || !$bdUpcoming): ?><p class="sc-note" style="margin:0">No birthdays to show yet.</p><?php endif; ?>
        <?php foreach ($bdUpcoming as $u): ?>
        <div class="sc-bdi"><?php echo $bdAva($u, 'sc-bava'); ?><div><b><?php echo $e($u['full_name']); ?></b><small><?php echo $e((new DateTime($u['occ_date'], $tz))->format('D j M')); ?> · <?php echo $e(sbd_in_days_text($u['days'])); ?><?php echo $u['age'] !== null ? ' · turns ' . (int) $u['age'] : ''; ?></small></div><i class="fas fa-cake-candles"></i></div>
        <?php endforeach; ?>
        <?php if ($bdOn): ?><div class="sc-bdlinks">
            <button type="button" data-bday><?php echo isset($bdRows[$actor['uid']]) ? 'Change my birthday' : 'Add my birthday'; ?></button>
            <button type="button" data-suggest>Suggest a message</button>
            <?php if ($actor['role'] === 'superadmin'): ?><a href="<?php echo $e(url('superadmin/staffcalendar/settings.php') . '?tab=messages'); ?>">Birthday messages</a><?php if ($gfOn): ?><a href="<?php echo $e(url('superadmin/staffcalendar/gifts.php')); ?>">Gift history</a><?php endif; ?><?php endif; ?>
        </div><?php endif; ?>
    </div>
    <div class="sc-card sc-pad"><h4>Next holiday</h4>
        <?php if ($nextHol): ?><div class="sc-bdi"><span class="sc-hico"><i class="fas fa-flag"></i></span><div><b><?php echo $e($nextHol['name']); ?></b><small><?php echo $e((new DateTime($nextHol['date'], $tz))->format('D j M')); ?> · <?php echo $e(sbd_in_days_text($nextHol['days'])); ?></small></div></div>
            <?php if ($nextHol['label'] !== ''): ?><span class="sc-pillx"><?php echo $e($nextHol['label']); ?></span><?php endif; ?>
        <?php else: ?><p class="sc-note" style="margin:0">No holiday to show yet.</p><?php endif; ?></div>
    <?php if ($sstOn): ?>
    <div class="sc-card sc-pad" id="sc-mys"><h4>My settings</h4>
        <form id="sc-myform" method="post" action="<?php echo $e($formUrl); ?>"><?php echo csrf_field(); ?><input type="hidden" name="op" value="prefs_save">
            <?php foreach (SST_PREFS as $pk => [$plabel, $psetting]): ?>
            <label class="scf-swrow" style="padding:7px 0"><span><?php echo $e($pk === 'notice_birthday' ? 'Birthday notices' : ($pk === 'notice_gift' ? 'Gift reminders' : $plabel)); ?></span><input type="checkbox" class="scf-sw" name="<?php echo $e($pk); ?>" value="1"<?php echo !empty($myPrefs[$pk]) ? ' checked' : ''; ?>></label>
            <?php endforeach; ?>
        </form>
        <p class="sc-note" id="sc-mymsg" aria-live="polite">Your switches only turn things off for you. The “Only me” reminders you add stay private.</p>
    </div>
    <?php endif; ?>
    <div class="sc-card sc-pad">
        <h4>Show on calendar</h4>
        <?php foreach ($types as $t): ?>
        <label class="sc-ft" style="--c:<?php echo $e($t['color']); ?>"><i></i><span><?php echo $e($t['label']); ?></span><input type="checkbox" class="sc-filter" value="<?php echo $e($t['type_key']); ?>" checked aria-label="Show <?php echo $e($t['label']); ?>"></label>
        <?php endforeach; ?>
    </div>
</aside>
</div>

<div id="scd-bg"></div>
<aside class="scd" id="scd" role="dialog" aria-modal="true" aria-labelledby="scd-title" tabindex="-1" hidden>
    <button type="button" class="scd-x" id="scd-x" aria-label="Close"><i class="fas fa-xmark"></i></button>
    <div class="scd-scroll">
        <div class="scd-cover strip" id="scd-cover"><span id="scd-cover-txt"></span></div>
        <div class="scd-in">
            <div class="scd-tags"><span class="scd-type" id="scd-type"></span><span class="scd-badge" id="scd-badge" hidden></span></div>
            <h2 id="scd-title"></h2>
            <div class="scd-load" id="scd-load">Loading…</div><div class="scd-err" id="scd-err" hidden></div>
            <div id="scd-body" hidden>
                <div class="scd-sec"><i class="fas fa-calendar"></i><div><small>When</small><b id="scd-date"></b><span id="scd-time"></span><span id="scd-rep" hidden></span></div></div>
                <div class="scd-sec" id="scd-where-row"><i class="fas fa-location-dot"></i><div><small>Where</small><b id="scd-w1"></b><span id="scd-w2" hidden></span></div></div>
                <div class="scd-sec" id="scd-desc-row"><i class="fas fa-file-lines"></i><div><small>Details</small><p id="scd-desc"></p></div></div>
                <div class="scd-box" id="scd-ag-row"><h4>Agenda</h4><div id="scd-ag"></div><div class="scd-ckerr" id="scd-ckerr" hidden></div></div>
                <div class="scd-who"><span class="scp-ava" id="scd-ava"></span><div><small>Added by</small><b id="scd-owner"></b><span id="scd-added"></span><span id="scd-edited"></span></div></div>
                <div class="scd-rem"><h4>You will be reminded</h4><div class="scd-pills" id="scd-pills"></div><p id="scd-remnote"></p></div>
            </div>
        </div>
    </div>
    <div class="scd-bar">
        <button type="button" class="sc-btn ghost" id="scd-edit" disabled><i class="fas fa-pen"></i>Edit</button>
        <button type="button" class="sc-btn danger" id="scd-del" disabled><i class="fas fa-trash"></i>Delete</button>
        <span class="scd-note" id="scd-note"></span>
    </div>
</aside>

<div id="sc-pop-bg"></div>
<div class="scp" id="sc-pop" role="dialog" aria-labelledby="scp-title" tabindex="-1" hidden>
    <span class="scp-arrow" id="scp-arrow"></span>
    <div class="scp-box" id="scp-box">
        <div class="scp-grab"></div>
        <div class="scp-tools">
            <button type="button" class="scp-edit" id="scp-edit" aria-label="Edit event" disabled><i class="fas fa-pen"></i></button>
            <button type="button" class="scp-x" id="scp-x" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="scp-cover" id="scp-cover"></div>
        <div class="scp-in">
            <div class="scp-kind"><b id="scp-type"></b><span class="scp-badge" id="scp-badge" hidden></span></div>
            <div class="scp-title"><i class="scp-sq"></i><h3 id="scp-title"></h3></div>
            <div id="scp-load" class="scp-load">Loading…</div>
            <div id="scp-err" class="scp-err" hidden></div>
            <div id="scp-body" hidden>
                <div class="scp-when"><small class="scp-lbl">When</small><span class="d" id="scp-date"></span><span class="dot"> · </span><span class="tm" id="scp-time"></span><span class="scp-rep" id="scp-rep" hidden></span></div>
                <div class="scp-row" id="scp-where-row"><i class="fas fa-location-dot"></i><div class="t"><small class="scp-lbl">Where</small><span id="scp-where"></span></div></div>
                <div class="scp-row" id="scp-desc-row"><i class="fas fa-file-lines"></i><div class="t" id="scp-desc"></div></div>
                <div class="scp-row" id="scp-rem-row"><i class="fas fa-bell"></i><div class="t"><small class="scp-lbl">Reminders</small><span id="scp-rem"></span></div></div>
                <div class="scp-row" id="scp-priv-row"><i class="fas fa-lock"></i><div class="t">Private: only you can see this event.</div></div>
                <div class="scp-who"><span class="scp-ava" id="scp-ava"></span><div><small class="scp-lbl">Added by</small><span><span class="pre">Added by </span><b id="scp-owner"></b></span></div></div>
                <div class="scp-foot"><span class="scp-note" id="scp-note"></span><button type="button" class="scp-more" id="scp-more" disabled>More details</button></div>
            </div>
        </div>
    </div>
</div>

<dialog id="sc-form" class="scf-dlg" aria-labelledby="scf-title">
    <form method="post" action="<?php echo $e($formUrl); ?>" enctype="multipart/form-data" class="scf" id="scf" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value=""><input type="hidden" name="return_view" value="<?php echo $e($view); ?>"><input type="hidden" name="return_d" value="<?php echo $e($dayS); ?>">
        <input type="hidden" name="form_v" value="2"><input type="hidden" name="checklist" value="[]"><input type="hidden" name="tasks" value="[]"><input type="hidden" name="remove_cover" value="">
        <div class="scf-head">
            <span class="scf-ico"><i class="fas fa-calendar-plus"></i></span>
            <div><h3 id="scf-title">New event</h3><p id="scf-owner"></p></div>
            <button type="button" class="scf-x" data-cancel aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="scf-error" id="scf-err" role="alert" hidden></div>
        <div class="scf-body">
            <div class="scf-left">
                <div class="fld"><label class="l" for="scf-t">Title</label><input type="text" id="scf-t" name="title" class="big" maxlength="200" required autocomplete="off"></div>
                <div class="fld"><label class="l">Type</label><div class="scf-pills" role="radiogroup" aria-label="Type"><?php foreach ($types as $i => $t): ?><label class="scf-pill"><input type="radio" name="type" value="<?php echo $e($t['type_key']); ?>"<?php echo $i === 0 ? ' checked' : ''; ?>><span><i style="background:<?php echo $e($t['color']); ?>"></i><?php echo $e($t['label']); ?></span></label><?php endforeach; ?></div></div>
                <div class="scf-g3">
                    <div class="fld"><label class="l" for="scf-sd" id="scf-sdl">Date</label><input type="date" id="scf-sd" name="start_date" required></div>
                    <div class="fld sc-time"><label class="l" for="scf-st">Starts</label><input type="time" id="scf-st" name="start_time" value="09:00"></div>
                    <div class="fld sc-time"><label class="l" for="scf-et">Ends</label><input type="time" id="scf-et" name="end_time" value="10:00"></div>
                </div>
                <div class="fld" id="scf-endrow" hidden><label class="l" for="scf-ed" id="scf-edl">End date</label><input type="date" id="scf-ed" name="end_date"></div>
                <div class="scf-opts">
                    <label class="scf-swrow pj-hide"><input type="checkbox" class="scf-sw" name="all_day" value="1" id="scf-ad"><span>All day</span></label>
                    <select name="repeat" id="scf-r" class="grow pj-hide" aria-label="Repeat"><?php foreach (SCAL_REPEATS as $k => $l): ?><option value="<?php echo $e($k); ?>"><?php echo $e($l); ?></option><?php endforeach; ?></select>
                    <small>Nairobi time (EAT)</small>
                    <button type="button" class="scf-add pj-hide" id="scf-multi">+ Runs over several days</button>
                </div>
                <div class="scf-note2 pj-hide" id="scf-series" hidden style="margin:-8px 0 14px">This event repeats. Changing or deleting it changes the whole series.</div>
                <div class="fld"><label class="l" for="scf-l">Location</label><div class="scf-loc"><i class="fas fa-location-dot"></i><input type="text" id="scf-l" name="location" maxlength="255" autocomplete="off"></div></div>
                <div class="fld"><label class="l" for="scf-d">Description</label><textarea id="scf-d" name="description" rows="4" maxlength="3000"></textarea></div>
            </div>
            <div class="scf-right">
                <div class="fld">
                    <label class="l">Cover image (optional)</label>
                    <div class="scf-cover ph" id="scf-cover">
                        <div class="scf-cbtn"><button type="button" id="scf-cover-pick"><i class="fas fa-arrow-up-from-bracket"></i> Upload image</button><button type="button" id="scf-cover-rm" hidden>Remove</button></div>
                    </div>
                    <input type="file" id="scf-c" name="cover" accept="image/jpeg,image/png,image/webp" hidden>
                    <div class="scf-note2" style="margin-top:6px">JPG, PNG or WebP, up to 5 MB. It is kept exactly as you upload it.</div>
                </div>
                <div class="scf-panel">
                    <h4>Reminders</h4>
                    <label class="scf-swrow"><span>Day before, 8:00 am</span><input type="checkbox" class="scf-sw" name="remind_day_before" value="1" checked></label>
                    <label class="scf-swrow"><span>On the day, 8:00 am</span><input type="checkbox" class="scf-sw" name="remind_on_day" value="1" checked></label>
                    <label class="scf-swrow"><span>1 hour before the start</span><input type="checkbox" class="scf-sw" name="remind_hour_before" value="1" checked></label>
                    <label class="scf-swrow"><span>Also send an email</span><input type="checkbox" class="scf-sw" name="remind_email" value="1" checked></label>
                    <hr>
                    <label class="scf-swrow"><span>Notify every tutor and the super admin</span><input type="checkbox" class="scf-sw" name="notify" value="1" id="scf-n" checked></label>
                    <label class="scf-swrow pj-hide"><span>Only me (private reminder)</span><input type="checkbox" class="scf-sw" name="private" value="1" id="scf-p"></label>
                </div>
                <div class="scf-panel pj-only">
                    <h4>Tasks</h4>
                    <div id="scf-tk"></div>
                    <button type="button" class="scf-add" id="scf-tk-add"><i class="fas fa-plus"></i> Add task</button>
                    <div class="scf-note2" style="margin-top:6px">Each person is notified when a task is given to them, and again the day before it is due. A project always needs a start and an end date, and everyone can see it.</div>
                </div>
                <div class="scf-panel pj-hide">
                    <h4>Checklist (optional)</h4>
                    <div id="scf-cl"></div>
                    <button type="button" class="scf-add" id="scf-cl-add"><i class="fas fa-plus"></i> Add item</button>
                </div>
            </div>
        </div>
        <div class="scf-foot">
            <span class="scf-note2">Only you and the super admin can edit this event after you save it.</span>
            <button type="button" class="sc-btn danger" id="scf-del" hidden><i class="fas fa-trash"></i>Delete event</button>
            <button type="button" class="sc-btn ghost" data-cancel>Cancel</button>
            <button type="submit" class="sc-btn" id="scf-save">Save event</button>
        </div>
    </form>
</dialog>
<?php
$bdPeople = $bdOn && $actor['role'] === 'superadmin' ? sbd_people($db) : [];
$bdData = [];   // what the birthday window starts with: a tutor only gets their own (the year is private), the super admin everyone's
foreach ($bdRows as $uid => $r) if ($uid === $actor['uid'] || $actor['role'] === 'superadmin') $bdData[$uid] = ['d' => $r['day'], 'm' => $r['month'], 'y' => $r['year'], 'age' => $r['show_age']];
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
?>
<?php if ($bdOn): ?>
<dialog id="sc-bday" class="scf-ask scf-mini" aria-labelledby="scb-title">
    <form class="scf" id="scb" method="post" action="<?php echo $e($formUrl); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="op" value="bday_save"><input type="hidden" name="return_view" value="<?php echo $e($view); ?>"><input type="hidden" name="return_d" value="<?php echo $e($dayS); ?>">
        <h4 id="scb-title">My birthday</h4>
        <p>Only the day and the month show. Your age is never shown unless you choose it below.</p>
        <div class="scf-error" id="scb-err" role="alert" hidden></div>
        <?php if ($bdPeople): ?><div class="fld"><label class="l" for="scb-user">Person</label><select name="user_id" id="scb-user"><?php foreach ($bdPeople as $pp): ?><option value="<?php echo (int) $pp['id']; ?>"<?php echo $pp['id'] === $actor['uid'] ? ' selected' : ''; ?>><?php echo $e($pp['name']); ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="g3">
            <div><label class="l" for="scb-d">Day</label><select name="day" id="scb-d" required><option value="">Day</option><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select></div>
            <div><label class="l" for="scb-m">Month</label><select name="month" id="scb-m" required><option value="">Month</option><?php foreach ($months as $i => $mn): ?><option value="<?php echo $i + 1; ?>"><?php echo $e($mn); ?></option><?php endforeach; ?></select></div>
            <div><label class="l" for="scb-y">Year (optional)</label><input type="text" inputmode="numeric" name="year" id="scb-y" maxlength="4" placeholder="e.g. 1990" autocomplete="off"></div>
        </div>
        <label class="scf-swrow"><span>Show my age</span><input type="checkbox" class="scf-sw" name="show_age" value="1" id="scb-age"></label>
        <div class="scf-note2">Needs the year. Switched off, nobody sees an age.</div>
        <div class="row-btn"><button type="button" class="sc-btn danger" id="scb-rm" hidden>Remove</button><button type="button" class="sc-btn ghost" data-dclose>Cancel</button><button type="submit" class="sc-btn" id="scb-save">Save</button></div>
    </form>
</dialog>
<dialog id="sc-suggest" class="scf-ask scf-mini" aria-labelledby="scs-title">
    <form class="scf" id="scs" method="post" action="<?php echo $e($formUrl); ?>">
        <?php echo csrf_field(); ?><input type="hidden" name="op" value="suggest"><input type="hidden" name="return_view" value="<?php echo $e($view); ?>"><input type="hidden" name="return_d" value="<?php echo $e($dayS); ?>">
        <h4 id="scs-title">Suggest a birthday message</h4>
        <p>Short, warm and a little witty. Use {first_name} where the name goes. The super admin approves it before it is used.</p>
        <div class="scf-error" id="scs-err" role="alert" hidden></div>
        <div class="fld"><label class="l" for="scs-tag">Tag</label><select name="tag" id="scs-tag"><?php foreach (SBD_TAGS as $tg): ?><option><?php echo $e($tg); ?></option><?php endforeach; ?></select></div>
        <div class="fld"><label class="l" for="scs-body">Message</label><textarea name="body" id="scs-body" rows="4" maxlength="400" required></textarea></div>
        <div class="row-btn"><button type="button" class="sc-btn ghost" data-dclose>Cancel</button><button type="submit" class="sc-btn" id="scs-save">Send suggestion</button></div>
    </form>
</dialog>
<?php endif; ?>
<dialog id="sc-ask" class="scf-ask" aria-labelledby="ask-t"><h4 id="ask-t"></h4><p id="ask-p"></p><div><button type="button" class="sc-btn ghost" id="ask-no"></button><button type="button" class="sc-btn" id="ask-yes"></button></div></dialog>
</div>

<script>
(function () {
  var root = document.querySelector('.sc'), frm = document.getElementById('sc-form'), form = document.getElementById('scf'), askD = document.getElementById('sc-ask');
  var ACTOR = <?php echo json_encode($actor['name']); ?>, FORMURL = <?php echo json_encode($formUrl); ?>;
  var base = '', editing = false, multi = false, coverBlob = '', lastCard = null, lastDay = '';
  function $(id) { return document.getElementById(id); }
  function F(n) { return form.elements[n]; }
  function err(m) { var b = $('scf-err'); b.textContent = m || ''; b.hidden = !m; if (m) b.scrollIntoView({ block: 'nearest' }); }
  var TEAM = <?php echo json_encode($teamDef ?: new stdClass()); ?>;
  var PEOPLE = <?php echo json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $pjPeople), JSON_UNESCAPED_UNICODE); ?>, PJURL = <?php echo json_encode($pjBase); ?>;
  function isPj() { return F('type').value === 'project'; }
  function timeRows() {
    var off = F('all_day').checked || isPj();
    root.querySelectorAll('.sc-time').forEach(function (d) { d.hidden = off; });
    $('scf-endrow').hidden = !(off || multi);
    $('scf-multi').hidden = off || multi;
  }
  function tkAdd(t, focus) {
    t = t || {};
    var r = document.createElement('div'); r.className = 'scf-tk'; r.setAttribute('data-id', t.id || 0);
    var opts = '<option value="0">Nobody yet</option>'; PEOPLE.forEach(function (p) { opts += '<option value="' + p.id + '">' + p.name.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</option>'; });
    r.innerHTML = '<div class="l1"><input type="checkbox" class="dn" aria-label="Done"><input type="text" class="tt" maxlength="200" placeholder="Task" aria-label="Task title" autocomplete="off"><button type="button" class="rm" aria-label="Remove task"><i class="fas fa-xmark"></i></button></div>'
      + '<div class="l2"><select class="tw" aria-label="Given to">' + opts + '</select><input type="date" class="ts" aria-label="Starts"><input type="date" class="td" aria-label="Due"><select class="tz" aria-label="Status"><option value="todo">To do</option><option value="in_progress">In progress</option><option value="done">Done</option></select></div>';
    r.querySelector('.tt').value = t.t || ''; r.querySelector('.tw').value = String(t.who || 0); r.querySelector('.ts').value = t.start || ''; r.querySelector('.td').value = t.due || '';
    r.querySelector('.tz').value = t.status || 'todo'; r.querySelector('.dn').checked = (t.status === 'done');
    $('scf-tk').appendChild(r); if (focus) r.querySelector('.tt').focus();
  }
  function tkItems() {
    var out = [];
    form.querySelectorAll('#scf-tk .scf-tk').forEach(function (r) {
      var t = r.querySelector('.tt').value.trim(); if (!t) return;
      out.push({ id: parseInt(r.getAttribute('data-id'), 10) || 0, t: t, who: parseInt(r.querySelector('.tw').value, 10) || 0, start: r.querySelector('.ts').value, due: r.querySelector('.td').value, status: r.querySelector('.tz').value });
    });
    return out;
  }
  function pjMode() {
    var pj = isPj(); form.classList.toggle('is-project', pj);
    if (pj) {
      F('all_day').checked = true; F('repeat').value = 'none'; F('private').checked = false; multi = true;
      $('scf-sdl').textContent = 'Starts'; $('scf-edl').textContent = 'Ends';
      if (!form.querySelectorAll('#scf-tk .scf-tk').length) clItems().forEach(function (c) { tkAdd({ t: c.t, status: c.d ? 'done' : 'todo' }, false); });   // an existing simple checklist becomes the first tasks
    } else { $('scf-sdl').textContent = 'Date'; $('scf-edl').textContent = 'End date'; }
    timeRows();
  }
  function clItems() {
    var out = [];
    form.querySelectorAll('#scf-cl .scf-cli').forEach(function (r) { var t = r.querySelector('[type=text]').value.trim(); if (t) out.push({ t: t, d: r.querySelector('[type=checkbox]').checked }); });
    return out;
  }
  function clAdd(t, d, focus) {
    var r = document.createElement('div'); r.className = 'scf-cli';
    r.innerHTML = '<input type="checkbox" aria-label="Done"><input type="text" maxlength="200" placeholder="Item" aria-label="Checklist item" autocomplete="off"><button type="button" aria-label="Remove item"><i class="fas fa-xmark"></i></button>';
    r.querySelector('[type=text]').value = t || ''; r.querySelector('[type=checkbox]').checked = !!d;
    $('scf-cl').appendChild(r); if (focus) r.querySelector('[type=text]').focus();
  }
  function snap() {
    var f = F('cover').files[0];
    return JSON.stringify([F('title').value, F('type').value, F('start_date').value, F('start_time').value, F('end_date').value, F('end_time').value, F('all_day').checked, F('repeat').value,
      F('location').value, F('description').value, F('notify').checked, F('private').checked, F('remind_day_before').checked, F('remind_on_day').checked, F('remind_hour_before').checked,
      F('remind_email').checked, clItems(), F('remove_cover').value, f ? f.name + f.size : '', tkItems()]);
  }
  function setCover(url) {
    var c = $('scf-cover'); c.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : ''; c.classList.toggle('ph', !url);
    $('scf-cover-rm').hidden = !url; $('scf-cover-pick').lastChild.textContent = url ? ' Replace' : ' Upload image';
  }
  function openForm(ev, date) {
    closePop(); form.reset(); err(''); $('scf-cl').innerHTML = ''; $('scf-tk').innerHTML = ''; multi = false;
    if (coverBlob) { URL.revokeObjectURL(coverBlob); coverBlob = ''; }
    editing = !!ev; F('id').value = ev ? ev.id : ''; F('remove_cover').value = '';
    F('return_view').value = <?php echo json_encode($view); ?>; F('return_d').value = <?php echo json_encode($dayS); ?>;
    $('scf-title').textContent = ev ? 'Edit event' : 'New event';
    $('scf-owner').textContent = ev ? 'Owner: ' + ev.owner : 'Owner: ' + ACTOR + ' · picked automatically';
    $('scf-del').hidden = !ev; $('scf-save').textContent = 'Save event';
    if (ev) {
      F('title').value = ev.title; F('type').value = ev.type; F('repeat').value = ev.repeat; F('all_day').checked = !!ev.all_day;
      F('start_date').value = ev.start_date; F('end_date').value = ev.end_date; F('start_time').value = ev.start_time; F('end_time').value = ev.end_time;
      multi = ev.end_date !== ev.start_date;
      F('location').value = ev.location; F('description').value = ev.description; F('notify').checked = !!ev.notify; F('private').checked = !!ev['private'];
      F('remind_day_before').checked = !!ev.rem_day_before; F('remind_on_day').checked = !!ev.rem_on_day; F('remind_hour_before').checked = !!ev.rem_hour_before; F('remind_email').checked = !!ev.rem_email;
      (ev.checklist || []).forEach(function (c) { clAdd(c.t, c.d, false); }); (ev.tasks || []).forEach(function (t) { tkAdd(t, false); }); setCover(ev.cover || '');
    } else { F('start_date').value = date; F('end_date').value = date; setCover(''); ['remind_day_before', 'remind_on_day', 'remind_hour_before', 'remind_email'].forEach(function (k) { if (TEAM[k] === false) F(k).checked = false; }); }
    $('scf-series').hidden = !(ev && ev.repeat !== 'none');
    form.classList.remove('is-project'); pjMode(); if (isPj() && ev) multi = true; timeRows(); base = snap(); frm.showModal(); F('title').focus();
  }
  function ask(title, text, yes, no, danger) {
    return new Promise(function (res) {
      $('ask-t').textContent = title; $('ask-p').textContent = text; var y = $('ask-yes'), n = $('ask-no'); y.textContent = yes; n.textContent = no; y.className = 'sc-btn' + (danger ? ' danger' : '');
      var done = false;
      function fin(v) { if (done) return; done = true; y.onclick = n.onclick = null; askD.onclose = null; if (askD.open) askD.close(); res(v); }
      y.onclick = function () { fin(true); }; n.onclick = function () { fin(false); }; askD.onclose = function () { fin(false); };
      askD.showModal(); n.focus();
    });
  }
  function attemptClose() {
    if (askD.open) return;
    if (snap() === base) { frm.close(); return; }
    ask('Discard this event?', 'What you typed or changed will be lost.', 'Discard', 'Keep editing', false).then(function (ok) { if (ok) frm.close(); });
  }
  frm.addEventListener('cancel', function (e) { e.preventDefault(); attemptClose(); });   // Escape
  var downOut = false;
  frm.addEventListener('mousedown', function (e) { downOut = e.target === frm; });
  frm.addEventListener('click', function (e) { if (e.target === frm && downOut) attemptClose(); });   // the dimmed area
  frm.addEventListener('click', function (e) { if (e.target.closest('[data-cancel]')) attemptClose(); });
  F('all_day').addEventListener('change', timeRows);
  form.querySelectorAll('input[name=type]').forEach(function (r) { r.addEventListener('change', pjMode); });
  $('scf-tk-add').addEventListener('click', function () { tkAdd({}, true); });
  $('scf-tk').addEventListener('click', function (e) { var x = e.target.closest('.rm'); if (x) x.closest('.scf-tk').remove(); });
  $('scf-tk').addEventListener('change', function (e) {   // ticking sets Done, and the other way round
    var r = e.target.closest('.scf-tk'); if (!r) return;
    if (e.target.classList.contains('dn')) r.querySelector('.tz').value = e.target.checked ? 'done' : 'todo';
    if (e.target.classList.contains('tz')) r.querySelector('.dn').checked = e.target.value === 'done';
  });
  $('scf-multi').addEventListener('click', function () { multi = true; timeRows(); F('end_date').focus(); });
  F('start_date').addEventListener('change', function () { if (!F('end_date').value || F('end_date').value < this.value || (!multi && !F('all_day').checked)) F('end_date').value = this.value; });
  F('private').addEventListener('change', function () { if (this.checked) F('notify').checked = false; });
  F('notify').addEventListener('change', function () { if (this.checked) F('private').checked = false; });
  $('scf-cl-add').addEventListener('click', function () { clAdd('', false, true); });
  $('scf-cl').addEventListener('click', function (e) { var x = e.target.closest('button'); if (x) x.closest('.scf-cli').remove(); });
  $('scf-cl').addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.type === 'text') { e.preventDefault(); clAdd('', false, true); } });
  $('scf-cover-pick').addEventListener('click', function () { F('cover').click(); });
  $('scf-cover-rm').addEventListener('click', function () { F('cover').value = ''; F('remove_cover').value = '1'; if (coverBlob) { URL.revokeObjectURL(coverBlob); coverBlob = ''; } setCover(''); });
  F('cover').addEventListener('change', function () {
    var f = this.files[0]; if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { err('Use a JPG, PNG or WebP picture.'); this.value = ''; return; }
    if (f.size > 5 * 1024 * 1024) { err('That picture is too big. Use one of 5 MB or less.'); this.value = ''; return; }
    err(''); if (coverBlob) URL.revokeObjectURL(coverBlob); coverBlob = URL.createObjectURL(f); F('remove_cover').value = ''; setCover(coverBlob);
  });
  function send(fd, btn, label) {
    btn.disabled = true; btn.textContent = 'Please wait…';
    return fetch(FORMURL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); })
      .then(function (d) { if (d.ok) { base = snap(); window.location.href = d.go; } else { err(d.error); btn.disabled = false; btn.textContent = label; } })
      .catch(function () { err('Could not reach the server. Check your connection and try again.'); btn.disabled = false; btn.textContent = label; });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault(); err('');
    if (!F('title').value.trim()) { err('Give the event a title.'); F('title').focus(); return; }
    if (!F('start_date').value) { err('Choose the date.'); F('start_date').focus(); return; }
    var sd = F('start_date').value, ed = F('end_date').value || sd;
    if (F('all_day').checked ? ed < sd : (ed + ' ' + (F('end_time').value || F('start_time').value)) < (sd + ' ' + F('start_time').value)) { err('The event cannot end before it starts.'); return; }
    F('checklist').value = JSON.stringify(clItems()); F('tasks').value = JSON.stringify(tkItems());
    if (isPj() && !F('end_date').value) { err('A project needs a start date and an end date.'); F('end_date').focus(); return; }
    send(new FormData(form), $('scf-save'), 'Save event');
  });
  $('scf-del').addEventListener('click', function () {
    ask('Delete this event?', 'This cannot be undone.' + (F('repeat').value !== 'none' ? ' Every repeat of it is deleted too.' : ''), 'Delete event', 'Keep it', true).then(function (ok) {
      if (!ok) return;
      var fd = new FormData(); ['csrf_token', 'id', 'return_view', 'return_d'].forEach(function (n) { fd.append(n, F(n).value); }); fd.append('op', 'delete');
      send(fd, $('scf-del'), 'Delete event');
    });
  });
  $('scp-edit').addEventListener('click', function () { if (lastCard && lastCard.can_change && lastCard.edit) openForm(lastCard.edit, ''); });
  root.addEventListener('click', function (e) {
    var pn = e.target.closest('[data-panel]'); if (pn) { var pe = JSON.parse(pn.getAttribute('data-ev')); openPanel(pe.id, pn.getAttribute('data-d') || pe.start_date, pe); return; }
    var b = e.target.closest('[data-ev]'); if (b) { togglePop(b); return; }
    var dc = e.target.closest('[data-dclose]'); if (dc) { dc.closest('dialog').close(); return; }
    if (e.target.closest('[data-bday]')) { closePop(); bdFill(); $('scb-err').hidden = true; bdlg.showModal(); return; }
    if (e.target.closest('[data-suggest]')) { closePop(); $('scs-err').hidden = true; sdlg.showModal(); return; }
    var n = e.target.closest('[data-new]'); if (n) { openForm(null, n.getAttribute('data-new')); return; }
    var s = e.target.closest('.sc-sd');
    if (s) {
      root.querySelectorAll('.sc-sd').forEach(function (x) { x.classList.toggle('on', x === s); });
      root.querySelectorAll('.sc-dl').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-date') === s.getAttribute('data-date')); });
      root.querySelectorAll('[data-new]').forEach(function (x) { x.setAttribute('data-new', s.getAttribute('data-date')); });
      return;
    }
    var nd = e.target.closest('[data-newday]');   // an empty part of a day: add an event on that date
    if (nd && !e.target.closest('a,button,[data-ev],.sc-bday,.sc-bdrow,.sc-hol')) openForm(null, nd.getAttribute('data-newday'));
  });

  // ---- the event card -------------------------------------------------------
  var EVURL = <?php echo json_encode(url($sc_portal . '/staffcalendar/event.php')); ?>;
  var pop = $('sc-pop'), bg = $('sc-pop-bg'), selEl = null, reqId = 0, phone = window.matchMedia('(max-width:767.98px)'), closeT = 0;
  document.body.appendChild(bg); document.body.appendChild(pop);   // out of any transformed parent, so "fixed" means the screen
  function setText(id, t) { $(id).textContent = t || ''; }
  function place() {
    if (!selEl || pop.hidden) return;
    if (phone.matches) { pop.style.left = ''; pop.style.top = ''; return; }
    var r = selEl.getBoundingClientRect(), m = 8, gap = 14, w = pop.offsetWidth, h = pop.offsetHeight, vw = window.innerWidth, vh = window.innerHeight;
    var side = (r.right + gap + w <= vw - m) ? 'r' : ((r.left - gap - w >= m) ? 'l' : ((vw - r.right >= r.left) ? 'r' : 'l'));
    var x = side === 'r' ? r.right + gap : r.left - gap - w;
    x = Math.max(m, Math.min(x, vw - w - m));
    var y = Math.max(m, Math.min(r.top - 6, vh - h - m));
    pop.style.left = x + 'px'; pop.style.top = y + 'px';
    var a = $('scp-arrow'); a.className = 'scp-arrow ' + (side === 'r' ? 'l' : 'r');
    a.style.top = Math.max(16, Math.min(r.top + r.height / 2 - y - 7, h - 30)) + 'px';
  }
  function closePop() {
    if (selEl) { selEl.classList.remove('sc-sel'); selEl = null; }
    reqId++;
    if (pop.hidden) return;
    pop.classList.remove('open'); bg.classList.remove('on');
    var done = function () { pop.hidden = true; bg.classList.remove('show'); pop.style.transform = ''; };
    clearTimeout(closeT);
    if (phone.matches) closeT = setTimeout(done, 280); else done();
  }
  function fill(d) {
    setText('scp-type', d.type_label); setText('scp-title', d.title);
    pop.style.setProperty('--c', d.color); var b = $('scp-badge'); b.textContent = d.badge || ''; b.hidden = !d.badge;
    setText('scp-date', d.date_line); setText('scp-time', d.time_line);
    setText('scp-rep', d.repeat_label ? 'Repeats: ' + d.repeat_label.toLowerCase() : ''); $('scp-rep').hidden = !d.repeat_label;
    setText('scp-where', d.location); $('scp-where-row').hidden = !d.location;
    setText('scp-desc', d.description); $('scp-desc-row').hidden = !d.description;
    setText('scp-rem', d.reminders); $('scp-rem-row').hidden = !d.reminders;
    $('scp-priv-row').hidden = !d['private'];
    setText('scp-owner', d.owner); setText('scp-note', 'Only ' + d.owner + ' or the super admin can edit or delete.');
    var ava = $('scp-ava'); ava.textContent = d.initials;
    if (d.photo) { var im = new Image(); im.alt = ''; im.onerror = function () { im.remove(); }; im.src = d.photo; ava.appendChild(im); }
    lastCard = d; $('scp-more').disabled = false; $('scp-edit').disabled = !d.can_change; $('scp-edit').title = d.can_change ? 'Edit event' : 'Only the owner or the super admin can edit';
    var cv = $('scp-cover');
    if (d.cover) { cv.className = 'scp-cover'; cv.style.backgroundImage = 'url("' + d.cover.replace(/"/g, '%22') + '")'; cv.innerHTML = ''; }
    else { cv.className = 'scp-cover strip'; cv.style.backgroundImage = ''; cv.innerHTML = ''; if (d.location) { var i = document.createElement('i'); i.className = 'fas fa-location-dot'; cv.appendChild(i); cv.appendChild(document.createTextNode(d.location)); } }
    $('scp-load').hidden = true; $('scp-err').hidden = true; $('scp-body').hidden = false;
  }
  function openPop(el) {
    var ev = JSON.parse(el.getAttribute('data-ev')), day = el.getAttribute('data-d') || ev.start_date, my = ++reqId;
    if (selEl) selEl.classList.remove('sc-sel');
    selEl = el; el.classList.add('sc-sel'); clearTimeout(closeT); lastCard = null; lastDay = day; $('scp-more').disabled = true;
    // show the card at once with what the chip already knows, then ask the server (it checks the role and privacy again)
    pop.style.setProperty('--c', ev.color); setText('scp-type', ev.type_label); setText('scp-title', ev.title); $('scp-badge').hidden = true;
    $('scp-body').hidden = true; $('scp-err').hidden = true; $('scp-load').hidden = false; $('scp-edit').disabled = true;
    var cv = $('scp-cover'); cv.className = 'scp-cover strip'; cv.style.backgroundImage = ''; cv.innerHTML = '';
    var wasHidden = pop.hidden; pop.hidden = false;
    if (phone.matches) { bg.classList.add('show'); requestAnimationFrame(function () { requestAnimationFrame(function () { pop.classList.add('open'); bg.classList.add('on'); }); }); }
    place(); if (wasHidden) pop.focus({ preventScroll: true });
    fetch(EVURL + '?id=' + encodeURIComponent(ev.id) + '&d=' + encodeURIComponent(day), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 403) throw new Error('Not allowed.');
        if (r.status === 404) throw new Error('This event no longer exists.');
        if (!r.ok || (r.headers.get('content-type') || '').indexOf('json') < 0) throw new Error('Could not load this event.');
        return r.json();
      })
      .then(function (d) { if (my !== reqId) return; fill(d); place(); })
      .catch(function (e) { if (my !== reqId) return; $('scp-load').hidden = true; $('scp-body').hidden = true; setText('scp-err', e.message); $('scp-err').hidden = false; place(); });
  }
  function togglePop(el) { if (selEl === el && !pop.hidden) closePop(); else openPop(el); }
  $('scp-x').addEventListener('click', closePop);
  bg.addEventListener('click', closePop);
  document.addEventListener('click', function (e) {   // anywhere outside the card closes it (another event chip opens its own card instead)
    if (pop.hidden || pop.contains(e.target) || e.target.closest('[data-ev]')) return;
    closePop();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) closePop(); });
  window.addEventListener('resize', place); window.addEventListener('scroll', place, true);
  // phone: swipe the sheet down to close it
  (function () {
    var y0 = null, dy = 0, box = $('scp-box');
    pop.addEventListener('touchstart', function (e) { if (!phone.matches || box.scrollTop > 0) { y0 = null; return; } y0 = e.touches[0].clientY; dy = 0; pop.style.transition = 'none'; }, { passive: true });
    pop.addEventListener('touchmove', function (e) { if (y0 === null) return; dy = e.touches[0].clientY - y0; if (dy > 0) pop.style.transform = 'translateY(' + dy + 'px)'; }, { passive: true });
    pop.addEventListener('touchend', function () {
      if (y0 === null) return; y0 = null; pop.style.transition = '';
      if (dy > 80) closePop(); else pop.style.transform = '';
    });
  })();

  // ---- the full details panel ----------------------------------------------
  var scd = $('scd'), scdBg = $('scd-bg'), panelCard = null, panelReq = 0, panelT = 0;
  document.body.appendChild(scdBg); document.body.appendChild(scd);
  function panelOpen() { return !scd.hidden && scd.classList.contains('open'); }
  function closePanel() {
    panelReq++; if (scd.hidden) return;
    scd.classList.remove('open'); scdBg.classList.remove('on'); clearTimeout(panelT);
    panelT = setTimeout(function () { scd.hidden = true; scdBg.classList.remove('show'); }, 300);
  }
  function pillColors(el, c) { el.style.background = c + '22'; el.style.color = c; }
  function paintCover(d) {
    var cv = $('scd-cover'), t = $('scd-cover-txt'); t.innerHTML = '';
    if (d.cover) { cv.className = 'scd-cover'; cv.style.backgroundImage = 'url("' + d.cover.replace(/"/g, '%22') + '")'; }
    else { cv.className = 'scd-cover strip'; cv.style.backgroundImage = ''; if (d.location) { var i = document.createElement('i'); i.className = 'fas fa-location-dot'; t.appendChild(i); t.appendChild(document.createTextNode(d.location)); } }
  }
  function fillPanel(d) {
    panelCard = d; paintCover(d);
    var ty = $('scd-type'); ty.textContent = d.type_label; pillColors(ty, d.color); setText('scd-title', d.title);
    var b = $('scd-badge'); b.textContent = d.badge || ''; b.hidden = !d.badge;
    setText('scd-date', d.date_full); setText('scd-time', d.time_full); setText('scd-rep', d.repeat_text); $('scd-rep').hidden = !d.repeat_text;
    setText('scd-w1', d.where1); setText('scd-w2', d.where2); $('scd-w2').hidden = !d.where2; $('scd-where-row').hidden = !d.where1;
    setText('scd-desc', d.description); $('scd-desc-row').hidden = !d.description;
    var ag = $('scd-ag'); ag.innerHTML = '';
    (d.checklist_rows || []).forEach(function (it) {
      var l = document.createElement('label'); l.className = 'scd-ck';
      var c = document.createElement('input'); c.type = 'checkbox'; c.checked = !!it.d; c.setAttribute('data-item', it.id);
      var sp = document.createElement('span'); sp.textContent = it.t; l.appendChild(c); l.appendChild(sp); ag.appendChild(l);
    });
    $('scd-ag-row').hidden = !(d.checklist_rows || []).length; $('scd-ckerr').hidden = true;
    setText('scd-owner', d.owner); setText('scd-added', d.added_at); setText('scd-edited', d.edited_text);
    var ava = $('scd-ava'); ava.textContent = d.initials;
    if (d.photo) { var im = new Image(); im.alt = ''; im.onerror = function () { im.remove(); }; im.src = d.photo; ava.appendChild(im); }
    var pl = $('scd-pills'); pl.innerHTML = '';
    (d.reminder_times || []).forEach(function (t) { var sp = document.createElement('span'); var i = document.createElement('i'); i.className = 'fas fa-bell'; sp.appendChild(i); sp.appendChild(document.createTextNode(t)); pl.appendChild(sp); });
    if (!(d.reminder_times || []).length) { var sp = document.createElement('span'); sp.textContent = 'No reminders'; pl.appendChild(sp); }
    setText('scd-remnote', d.reminder_note);
    $('scd-edit').disabled = !d.can_change; $('scd-del').disabled = !d.can_change;
    setText('scd-note', 'Only ' + d.owner + ' or the super admin can edit or delete this event.');
    $('scd-load').hidden = true; $('scd-err').hidden = true; $('scd-body').hidden = false;
  }
  function openPanel(id, day, known) {
    if (known && known.type === 'project') { window.location.href = PJURL + '?id=' + id; return; }
    closePop(); var my = ++panelReq; clearTimeout(panelT); panelCard = null;
    var ty = $('scd-type'); ty.textContent = known.type_label || ''; pillColors(ty, known.color || '#048C44'); setText('scd-title', known.title);
    $('scd-badge').hidden = true; $('scd-body').hidden = true; $('scd-err').hidden = true; $('scd-load').hidden = false;
    $('scd-edit').disabled = true; $('scd-del').disabled = true; setText('scd-note', '');
    var cv = $('scd-cover'); cv.className = 'scd-cover strip'; cv.style.backgroundImage = ''; $('scd-cover-txt').innerHTML = '';
    scd.hidden = false; scdBg.classList.add('show');
    requestAnimationFrame(function () { requestAnimationFrame(function () { scd.classList.add('open'); scdBg.classList.add('on'); }); });
    scd.focus({ preventScroll: true });
    fetch(EVURL + '?id=' + encodeURIComponent(id) + '&d=' + encodeURIComponent(day) + '&full=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.status === 403) throw new Error('Not allowed.');
        if (r.status === 404) throw new Error('This event no longer exists.');
        if (!r.ok || (r.headers.get('content-type') || '').indexOf('json') < 0) throw new Error('Could not load this event.');
        return r.json();
      })
      .then(function (d) { if (my === panelReq) fillPanel(d); })
      .catch(function (e) { if (my !== panelReq) return; $('scd-load').hidden = true; setText('scd-err', e.message); $('scd-err').hidden = false; });
  }
  $('scd-x').addEventListener('click', closePanel);
  scdBg.addEventListener('click', closePanel);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panelOpen() && !frm.open && !askD.open) closePanel(); });
  $('scp-more').addEventListener('click', function () { if (!lastCard) return; if (lastCard.type === 'project') { window.location.href = PJURL + '?id=' + lastCard.id; return; } openPanel(lastCard.id, lastDay, lastCard); });
  $('scd-edit').addEventListener('click', function () { if (panelCard && panelCard.can_change && panelCard.edit) { var ed = panelCard.edit; closePanel(); openForm(ed, ''); } });
  $('scd-del').addEventListener('click', function () {
    if (!panelCard || !panelCard.can_change) return;
    var id = panelCard.id, rep = panelCard.repeat_label !== '', btn = $('scd-del');
    ask('Delete this event?', 'This cannot be undone.' + (rep ? ' Every repeat of it is deleted too.' : ''), 'Delete event', 'Keep it', true).then(function (ok) {
      if (!ok) return;
      var fd = new FormData(); fd.append('csrf_token', F('csrf_token').value); fd.append('id', id); fd.append('return_view', F('return_view').value); fd.append('return_d', F('return_d').value); fd.append('op', 'delete');
      send(fd, btn, 'Delete');
    });
  });
  $('scd-ag').addEventListener('change', function (e) {   // a tick saves at once, for everyone
    var cb = e.target; if (!cb.matches('input[type=checkbox]') || !panelCard) return;
    var want = cb.checked, er = $('scd-ckerr'); cb.disabled = true; er.hidden = true;
    var fd = new FormData(); fd.append('csrf_token', F('csrf_token').value); fd.append('op', 'tick'); fd.append('id', panelCard.id); fd.append('item', cb.getAttribute('data-item')); if (want) fd.append('done', '1');
    fetch(FORMURL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { cb.disabled = false; if (d.ok) cb.checked = d.done; else { cb.checked = !want; er.textContent = d.error || 'Could not save that tick.'; er.hidden = false; } })
      .catch(function () { cb.disabled = false; cb.checked = !want; er.textContent = 'Could not save that tick. Check your connection.'; er.hidden = false; });
  });

  // ---- birthdays: my birthday window and the suggestion window ----------------
  var BDATA = <?php echo json_encode($bdData, JSON_FORCE_OBJECT); ?>, MYUID = <?php echo (int) $actor['uid']; ?>;
  var bdlg = $('sc-bday'), sdlg = $('sc-suggest');
  function post2(f, btn, errId) {
    var er = $(errId); er.hidden = true; var label = btn.textContent; btn.disabled = true; btn.textContent = 'Please wait…';
    fetch(FORMURL, { method: 'POST', body: new FormData(f), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); })
      .then(function (d) { if (d.ok) { window.location.href = d.go; } else { er.textContent = d.error; er.hidden = false; btn.disabled = false; btn.textContent = label; } })
      .catch(function () { er.textContent = 'Could not reach the server. Check your connection.'; er.hidden = false; btn.disabled = false; btn.textContent = label; });
  }
  function bdFill() {
    var u = $('scb-user') ? $('scb-user').value : String(MYUID), r = BDATA[u];
    $('scb-d').value = r ? r.d : ''; $('scb-m').value = r ? r.m : ''; $('scb-y').value = r && r.y ? r.y : ''; $('scb-age').checked = !!(r && r.age);
    $('scb-rm').hidden = !r; $('scb-title').textContent = u === String(MYUID) ? 'My birthday' : 'Birthday';
  }
  if (bdlg) {
    if ($('scb-user')) $('scb-user').addEventListener('change', bdFill);
    $('scb').addEventListener('submit', function (e) { e.preventDefault(); $('scb').elements['op'].value = 'bday_save'; post2($('scb'), $('scb-save'), 'scb-err'); });
    $('scb-rm').addEventListener('click', function () { if (!confirm('Remove this birthday?')) return; $('scb').elements['op'].value = 'bday_remove'; post2($('scb'), $('scb-rm'), 'scb-err'); });
    $('scs').addEventListener('submit', function (e) { e.preventDefault(); post2($('scs'), $('scs-save'), 'scs-err'); });
  }

  // ---- the gift tracker: every tap is sent to the server, which checks who is asking and which row ----
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-gift]'); if (!b || b.disabled) return;
    var fd = new FormData(); fd.append('csrf_token', F('csrf_token').value); fd.append('op', 'gift_' + b.getAttribute('data-gift'));
    ['list', 'giver', 'note'].forEach(function (k) { var v = b.getAttribute('data-' + k); if (v) fd.append(k, v); });
    fd.append('return_view', F('return_view').value); fd.append('return_d', F('return_d').value);
    var box = b.closest('section, .sg-note'), msg = box ? box.querySelector('.sg-msg') : null, label = b.innerHTML; b.disabled = true;
    fetch(FORMURL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Something went wrong. Please try again.' }; }); })
      .then(function (d) {
        if (d.ok) { window.location.href = d.go; return; }
        if (msg) { msg.textContent = d.error; msg.hidden = false; }
        if (d.error === 'Already sent today') b.innerHTML = '<i class="fas fa-bell"></i> Already sent today'; else b.disabled = false;
      })
      .catch(function () { if (msg) { msg.textContent = 'Could not reach the server. Check your connection.'; msg.hidden = false; } b.disabled = false; b.innerHTML = label; });
  });
  var AUTOEDIT = <?php echo (int) ($_GET['edit'] ?? 0); ?>;
  if (AUTOEDIT > 0) {
    fetch(EVURL + '?id=' + AUTOEDIT + '&d=<?php echo $e($dayS); ?>&full=1', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d && d.can_change && d.edit) openForm(d.edit, ''); }).catch(function () {});
  }
  // ---- My settings: each switch saves at once (only for the person who is logged in) ----
  var myf = $('sc-myform');
  if (myf) myf.addEventListener('change', function () {
    var m = $('sc-mymsg'), old = m.textContent; m.textContent = 'Saving…';
    fetch(FORMURL, { method: 'POST', body: new FormData(myf), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); }).then(function (d) { m.textContent = d.ok ? 'Saved.' : (d.error || 'Could not save.'); })
      .catch(function () { m.textContent = 'Could not save. Check your connection.'; });
  });
  // "Show on calendar": hide or show a type everywhere on the page; remembered on this device only.
  var KEY = 'staffcal_hidden_types', hidden = [];
  try { hidden = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (x) { hidden = []; }
  function apply() {
    root.querySelectorAll('.sc-filter').forEach(function (c) { c.checked = hidden.indexOf(c.value) < 0; });
    root.querySelectorAll('.sc-main [data-type]').forEach(function (el) { el.classList.toggle('sc-hide', hidden.indexOf(el.getAttribute('data-type')) >= 0); });
    if (selEl && selEl.classList.contains('sc-hide')) closePop();
    root.querySelectorAll('.sc-cell').forEach(function (cell) {
      var chips = cell.querySelectorAll('.sc-chip:not(.sc-hide):not(.sc-pbar)'), more = cell.querySelector('.sc-more');
      chips.forEach(function (c, i) { c.classList.toggle('sc-over', i >= 3); });
      if (more) { more.hidden = chips.length <= 3; more.textContent = '+' + Math.max(0, chips.length - 3) + ' more'; }
    });
  }
  root.querySelectorAll('.sc-filter').forEach(function (c) {
    c.addEventListener('change', function () {
      hidden = hidden.filter(function (v) { return v !== c.value; }); if (!c.checked) hidden.push(c.value);
      try { localStorage.setItem(KEY, JSON.stringify(hidden)); } catch (x) {}
      apply();
    });
  });
  apply();
})();
</script>
<?php
$content = ob_get_clean();
