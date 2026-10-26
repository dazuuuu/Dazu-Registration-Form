<?php
// Phone home screen for the tutor portal (below 992px, glass look from components/app_shell/glass.php).
// Expects $tc, $myStudents, $pending, $tutor, $stats from tutor/dashboard/index.php.
$th = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../../../app/v1/helpers/user_photo.php';
$thIni = fn(string $n) => strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2))));
$thProg = [];
foreach (array_slice($myStudents, 0, 30) as $s) {
    $thProg[(int) $s['id']] = $tc->progressFor($s);
}
$thPct = array_map(fn($p) => (int) $p['percent'], array_values($thProg));
$thAvg = $thPct ? (int) round(array_sum($thPct) / count($thPct)) : 0;
$thDone = count(array_filter($myStudents, fn($s) => !empty($s['studies_completed_at'])));
$thTotal = count($myStudents);
// Small line chart from the students' progress (flat line when there is nothing yet).
$thLine = function (array $vals, int $w = 150, int $h = 46): array {
    $vals = $vals ?: [0, 0];
    if (count($vals) === 1) $vals[] = $vals[0];
    $n = count($vals) - 1;
    $pts = [];
    foreach ($vals as $i => $v) {
        $pts[] = [round($i / $n * $w, 1), round($h - 4 - max(0, min(100, $v)) / 100 * ($h - 10), 1)];
    }
    $d = 'M' . implode(' L', array_map(fn($p) => $p[0] . ',' . $p[1], $pts));
    return [$d, $d . " L$w,$h L0,$h Z", end($pts)];
};
[$thLineD, $thAreaD, $thLast] = $thLine(array_slice($thPct, 0, 12));
$thRing = $thTotal ? $thDone / $thTotal : 0;
$thCirc = 2 * M_PI * 30;
?>
<style>
.th{display:none}
@media(max-width:991.98px){
  .th{display:block}.th-desktop{display:none}
  .th-sec{display:flex;justify-content:space-between;align-items:center;margin:20px 2px 10px}
  .th-sec h2{font-size:1.25rem;font-weight:700;color:#1f2421;margin:0}.th-sec a{font-size:.82rem;color:#2f7a14;text-decoration:none;font-weight:600}
  .th-row{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;margin:0 -16px;padding:2px 16px 8px}
  .th-row::-webkit-scrollbar{display:none}
  .th-glass{background:rgba(255,255,255,.58);border:1px solid rgba(255,255,255,.8);-webkit-backdrop-filter:blur(18px) saturate(1.6);backdrop-filter:blur(18px) saturate(1.6);
    box-shadow:0 10px 26px rgba(47,122,20,.1),inset 0 1px 0 #fff}
  .th-card{flex:0 0 78%;scroll-snap-align:start;border-radius:20px;padding:14px;color:#1f2421;text-decoration:none}
  .th-card:hover{color:#1f2421}
  .th-who{display:flex;gap:10px;align-items:center;margin-bottom:12px}
  .th-who .av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#7ed957,#44A024);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:none;border:2px solid #fff}
  .th-who b{display:block;font-size:.98rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .th-who small{color:#5f6b61;font-size:.78rem;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .th-when{display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid rgba(68,160,36,.14)}
  .th-when .ic{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#5cb83a,#44A024);color:#fff;display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 6px 12px rgba(68,160,36,.3)}
  .th-when b{display:block;font-size:.95rem;line-height:1.2}.th-when span{font-size:.8rem;color:#5f6b61}
  .th-foot{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:.85rem;color:#1f2421}
  .th-foot i{color:#44A024}
  .th-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .th-stat{position:relative;border-radius:20px;padding:14px;min-height:150px;overflow:hidden;color:#1f2421;text-decoration:none;display:block}
  .th-stat:hover{color:#1f2421}
  .th-stat .k{font-size:.9rem;font-weight:600;padding-right:36px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .th-stat .v{font-size:1.75rem;font-weight:800;line-height:1.15}.th-stat .v small{font-size:.8rem;font-weight:400;color:#5f6b61}
  .th-stat .badge-ic{position:absolute;top:12px;right:12px;width:34px;height:34px;border-radius:10px;background:rgba(68,160,36,.12);display:flex;align-items:center;justify-content:center;color:#2f7a14}
  .th-stat svg{display:block;width:100%;margin-top:8px}
  .th-bars{display:flex;gap:5px;align-items:flex-end;height:30px;margin-top:16px}
  .th-bars i{flex:1;border-radius:4px;background:rgba(68,160,36,.22)}.th-bars i.hi{background:linear-gradient(180deg,#7ed957,#44A024)}
  .th-ring{position:absolute;right:10px;bottom:12px;width:64px;height:64px}
  .th-list{border-radius:20px;overflow:hidden}
  .th-list a{display:flex;align-items:center;gap:12px;padding:12px 14px;color:#1f2421;text-decoration:none;border-bottom:1px solid rgba(68,160,36,.12)}
  .th-list a:last-child{border-bottom:0}
  .th-list .ic{width:38px;height:38px;border-radius:12px;background:rgba(68,160,36,.12);color:#2f7a14;display:flex;align-items:center;justify-content:center;flex:none}
  .th-list b{display:block;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.th-list small{font-size:.76rem;color:#5f6b61}
  .th-empty{border-radius:20px;border:1px dashed rgba(68,160,36,.35);padding:18px;text-align:center;color:#5f6b61;font-size:.88rem;background:rgba(255,255,255,.45)}
  .th-fab{position:fixed;left:50%;transform:translateX(-50%);bottom:calc(92px + env(safe-area-inset-bottom,0px));z-index:1035;padding:12px 26px;border-radius:999px;
    background:linear-gradient(135deg,rgba(92,184,58,.92),rgba(68,160,36,.92));border:1px solid rgba(255,255,255,.6);font-weight:600;text-decoration:none;white-space:nowrap;
    -webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px);box-shadow:0 10px 26px rgba(68,160,36,.4)}
  body.as-glass a.th-fab,.th-fab:hover{color:#fff}
  .th-spacer{height:56px}
  body.as-glass a.th-card,body.as-glass a.th-stat,body.as-glass .th-list a{color:#1f2421}
}
</style>
<div class="th">
    <div class="th-sec" style="margin-top:8px"><h2>My students</h2><a href="<?php echo url('tutor/students/index.php'); ?>">See all</a></div>
    <?php if (!$myStudents): ?>
        <div class="th-empty"><i class="fas fa-user-graduate me-1"></i>No students assigned yet.</div>
    <?php else: ?>
    <div class="th-row">
        <?php foreach (array_slice($myStudents, 0, 12) as $s): $p = $thProg[(int) $s['id']] ?? $tc->progressFor($s); ?>
        <a class="th-glass th-card" href="<?php echo url('tutor/students/view.php?id=' . (int) $s['id']); ?>">
            <div class="th-who"><?php $avUrl = user_photo_url($pdo, (int) ($s['user_id'] ?? 0)); ?><span class="av" style="overflow:hidden"><?php if ($avUrl): ?><img src="<?php echo $th($avUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: echo $th($thIni($s['full_name'])); endif; ?></span>
                <div style="min-width:0"><b><?php echo $th($s['full_name']); ?></b><small><?php echo $th($s['course_title']); ?></small></div></div>
            <div class="th-when"><span class="ic"><i class="fas fa-chart-line"></i></span>
                <div class="flex-grow-1"><b><?php echo $p['total'] ? (int) $p['percent'] . '% done' : 'Not started'; ?></b>
                    <span><?php echo (int) $p['done']; ?> of <?php echo (int) $p['total']; ?> modules<?php echo $p['grade_percent'] !== null ? ' · grade ' . (int) $p['grade_percent'] . '%' : ''; ?></span></div>
                <i class="fas fa-chevron-right" style="color:#9aa39b"></i></div>
            <div class="th-foot"><i class="fas <?php echo !empty($s['studies_completed_at']) ? 'fa-flag-checkered' : (($s['study_mode'] ?? '') === 'physical' ? 'fa-school' : 'fa-laptop'); ?>"></i>
                <?php echo !empty($s['studies_completed_at']) ? 'Studies completed' : ((($s['study_mode'] ?? '') === 'physical' ? 'Physical' : 'Online') . ' student'); ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th-sec"><h2>Statistics</h2></div>
    <div class="th-stats">
        <a class="th-glass th-stat" href="<?php echo url('tutor/students/index.php'); ?>">
            <div class="k">Average progress</div><div class="v"><?php echo $thAvg; ?> <small>%</small></div>
            <span class="badge-ic"><i class="fas fa-wave-square"></i></span>
            <svg viewBox="0 0 150 46" preserveAspectRatio="none" height="46"><defs><linearGradient id="thA" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#44A024" stop-opacity=".45"/><stop offset="1" stop-color="#44A024" stop-opacity="0"/></linearGradient></defs>
                <path d="<?php echo $thAreaD; ?>" fill="url(#thA)"/><path d="<?php echo $thLineD; ?>" fill="none" stroke="#44A024" stroke-width="2.2" stroke-linejoin="round"/>
                <circle cx="<?php echo $thLast[0]; ?>" cy="<?php echo $thLast[1]; ?>" r="3.5" fill="#44A024"/></svg>
        </a>
        <a class="th-glass th-stat" href="<?php echo url('tutor/submissions/index.php'); ?>">
            <div class="k">To grade</div><div class="v"><?php echo (int) $stats['pending']; ?> <small>waiting</small></div>
            <svg viewBox="0 0 150 46" height="46" preserveAspectRatio="none">
                <?php for ($x = 30; $x <= 120; $x += 30): ?><line x1="<?php echo $x; ?>" x2="<?php echo $x; ?>" y1="0" y2="46" stroke="rgba(68,160,36,.12)"/><?php endfor; ?>
                <path d="M0,34 C25,34 30,18 55,22 S90,38 105,24 S135,10 150,14" fill="none" stroke="#44A024" stroke-width="2.2"/>
                <circle cx="55" cy="22" r="3.5" fill="#44A024"/><circle cx="105" cy="24" r="3.5" fill="#44A024"/></svg>
        </a>
        <a class="th-glass th-stat" href="<?php echo url('tutor/courses/index.php'); ?>">
            <div class="k">Courses</div><div class="v"><?php echo (int) $stats['courses']; ?> <small>teaching</small></div>
            <div class="th-bars"><?php $bars = array_pad(array_slice($thPct, 0, 12), 12, 8); foreach ($bars as $i => $b): ?><i class="<?php echo $i === 4 ? 'hi' : ''; ?>" style="height:<?php echo max(20, min(100, $b)); ?>%"></i><?php endforeach; ?></div>
        </a>
        <a class="th-glass th-stat" href="<?php echo url('tutor/students/index.php'); ?>">
            <div class="k">Completed</div><div class="v"><?php echo $thDone; ?><small>/<?php echo $thTotal; ?></small></div><div style="font-size:.8rem;color:#5f6b61;max-width:calc(100% - 70px)">students</div>
            <svg class="th-ring" width="76" height="76" viewBox="0 0 76 76"><circle cx="38" cy="38" r="30" fill="none" stroke="rgba(68,160,36,.14)" stroke-width="7"/>
                <circle cx="38" cy="38" r="30" fill="none" stroke="#44A024" stroke-width="7" stroke-linecap="round" stroke-dasharray="<?php echo round($thCirc * $thRing, 1) . ' ' . round($thCirc, 1); ?>" transform="rotate(-90 38 38)"/></svg>
        </a>
    </div>

    <div class="th-sec"><h2>My courses</h2><a href="<?php echo url('tutor/modules/index.php'); ?>">Course builder</a></div>
    <?php if (empty($tutor['courses'])): ?>
        <div class="th-empty"><i class="fas fa-book me-1"></i>No courses yet.</div>
    <?php else: ?>
    <div class="th-row">
        <?php foreach ($tutor['courses'] as $c): $n = count(array_filter($myStudents, fn($s) => (int) ($s['course_id'] ?? 0) === (int) $c['id'])); ?>
        <a class="th-glass th-card" style="flex-basis:62%" href="<?php echo url('tutor/modules/index.php?course_id=' . (int) $c['id']); ?>">
            <div class="th-when" style="border:0;padding:0"><span class="ic"><i class="fas fa-book-open"></i></span>
                <div style="min-width:0"><b style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo $th($c['title']); ?></b><span><?php echo $n; ?> student<?php echo $n === 1 ? '' : 's'; ?></span></div></div>
            <div class="th-foot"><i class="fas fa-layer-group"></i>Modules, questions &amp; materials</div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="th-sec"><h2>Assignments</h2><a href="<?php echo url('tutor/assignments/index.php'); ?>">All assignments</a></div>
    <?php if (!$pending): ?>
        <div class="th-empty"><i class="fas fa-mug-hot me-1"></i>Nothing waiting for a grade.</div>
    <?php else: ?>
    <div class="th-glass th-list">
        <?php foreach (array_slice($pending, 0, 5) as $sub): ?>
        <a href="<?php echo url('tutor/submissions/index.php#sub-' . (int) $sub['id']); ?>"><span class="ic"><i class="fas fa-file-pen"></i></span>
            <div style="min-width:0;flex:1"><b><?php echo $th($sub['item_title']); ?></b><small><?php echo $th($sub['full_name']); ?><?php echo $sub['submitted_at'] ? ' · ' . date('j M', strtotime($sub['submitted_at'])) : ''; ?></small></div>
            <i class="fas fa-chevron-right" style="color:#9aa39b"></i></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="th-spacer"></div>
    <a class="th-fab" href="<?php echo url('tutor/announcements/index.php?new=1'); ?>"><i class="fas fa-bullhorn me-2"></i>New Announcement</a>
</div>
