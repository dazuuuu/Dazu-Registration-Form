<?php
// Desktop tutor dashboard (992px and up), Dazu green #44A024 + charcoal #373435.
// Expects $pdo, $tc, $tutor, $myStudents, $pending, $stats, $greet, $firstName from tutor/dashboard/index.php.
$dh = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uid = (int) $_SESSION['user_id'];
$dhProg = [];
foreach (array_slice($myStudents, 0, 40) as $s) $dhProg[(int) $s['id']] = $tc->progressFor($s);
$done = count(array_filter($dhProg, fn($p) => $p['total'] && $p['percent'] >= 100));
$started = count(array_filter($dhProg, fn($p) => $p['total'] && $p['percent'] > 0 && $p['percent'] < 100));
$notStarted = count($dhProg) - $done - $started;
$avg = $dhProg ? (int) round(array_sum(array_map(fn($p) => (int) $p['percent'], $dhProg)) / count($dhProg)) : 0;
$certs = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE tutor_id = ? AND status <> 'withdrawn'");
    $st->execute([$uid]);
    $certs = (int) $st->fetchColumn();
} catch (Throwable $e) {
}
$covers = [];
if (!empty($tutor['courses'])) {
    $ids = implode(',', array_map(fn($c) => (int) $c['id'], $tutor['courses']));
    foreach ($pdo->query("SELECT id, cover_image FROM courses WHERE id IN ($ids)")->fetchAll(PDO::FETCH_KEY_PAIR) as $cid => $img) $covers[(int) $cid] = $img;
}
$courseProg = [];
foreach ($myStudents as $s) if (isset($dhProg[(int) $s['id']])) $courseProg[(int) ($s['course_id'] ?? 0)][] = $dhProg[(int) $s['id']];
$activity = [];
try {
    require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
    $activity = PortalNotifications::latest($pdo, $uid, 5);
} catch (Throwable $e) {
}
$month = strtotime(date('Y-m-01'));
$due = [];   // days this month with work waiting for a grade
foreach ($pending as $p) if (!empty($p['submitted_at']) && date('Y-m', strtotime($p['submitted_at'])) === date('Y-m')) $due[(int) date('j', strtotime($p['submitted_at']))] = true;
// Classes from the Class Calendar (this month dots + the next ones)
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';
$classDays = [];
$nextClasses = [];
if (cal_installed($pdo)) {
    $ctz = cal_user_tz($pdo, $uid);
    [, $cFrom, $cTo] = cal_month_bounds(date('Y-m'), $ctz);
    foreach (cal_tutor_classes($pdo, $uid, $cFrom, $cTo) as $c) if ($c['status'] !== 'cancelled') $classDays[(int) cal_fmt($c['start_utc'], $ctz, 'j')] = true;
    $nextClasses = array_slice(array_values(array_filter(cal_tutor_classes($pdo, $uid, cal_now_utc(), gmdate('Y-m-d H:i:s', strtotime('+30 days'))), fn($c) => $c['status'] !== 'cancelled')), 0, 3);
}
$circ = 2 * M_PI * 46;
$tones = [['#44A024', '#2f7a14'], ['#373435', '#1d1b1c'], ['#5fb83a', '#36851b'], ['#4a4748', '#2a2829']];
?>
<style>
.dk{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px}
.dk-card{background:#fff;border-radius:18px;box-shadow:var(--shadow);padding:18px 20px}
.dk-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.dk-h h3{font-size:1.02rem;font-weight:600;margin:0}
.dk-h a{font-size:.8rem;font-weight:600;color:var(--g-d);text-decoration:none}
.dk-hero{position:relative;overflow:hidden;border-radius:20px;background:linear-gradient(120deg,#f0f8eb 0%,#e3f2da 100%);padding:26px 28px;min-height:178px}
.dk-hero h1{font-size:1.55rem;margin:0 0 6px;color:var(--char)}
.dk-hero p{color:#5f6460;max-width:360px;margin:0 0 16px;font-size:.9rem}
.dk-hero .art{position:absolute;right:18px;bottom:-14px;font-size:96px;color:rgba(68,160,36,.16)}
.dk-hero>*:not(i){position:relative;z-index:1}
.dk-hero .art2{position:absolute;right:22px;top:18px;font-size:26px;color:rgba(55,52,53,.14)}
.dk-stat{background:#fff;border-radius:18px;box-shadow:var(--shadow);padding:16px 18px;text-decoration:none;color:inherit;display:block}
.dk-stat .ic{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.dk-stat .k{font-size:.8rem;color:var(--muted)}.dk-stat .v{font-size:1.6rem;font-weight:700;line-height:1.1}
.dk-stat .l{font-size:.75rem;font-weight:600;color:var(--g-d)}
.dk-courses{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.dk-course{border-radius:16px;overflow:hidden;border:1px solid var(--line);text-decoration:none;color:inherit;background:#fff;display:block;transition:.15s}
.dk-course:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(29,27,28,.08)}
.dk-course .cv{height:92px;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.8rem}
.dk-course .bd{padding:12px}
.dk-course .t{font-weight:600;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dk-bar{height:6px;border-radius:99px;background:#eceee9;overflow:hidden;margin-top:8px}.dk-bar>div{height:100%;background:var(--g)}
.dk-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;text-align:center;font-size:.78rem}
.dk-cal .h{color:var(--muted);font-weight:600;padding-bottom:4px}
.dk-cal .d{height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;position:relative}
.dk-cal .d.today{background:var(--g);color:#fff;font-weight:700}
.dk-cal .d.due:after{content:"";position:absolute;bottom:3px;width:5px;height:5px;border-radius:50%;background:#e08a00}
.dk-cal .d.today.due:after{background:#fff}
.dk-cal .d.cls{font-weight:700;color:var(--g-d)}.dk-cal .d.cls:before{content:"";position:absolute;top:3px;width:5px;height:5px;border-radius:50%;background:var(--g)}
.dk-cal .d.today.cls{color:#fff}.dk-cal .d.today.cls:before{background:#fff}
.dk-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line);text-decoration:none;color:inherit}
.dk-row:last-child{border-bottom:0}
.dk-row .ic{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:none;background:var(--g-50);color:var(--g-d)}
.dk-row .grow{flex:1;min-width:0}.dk-row b{display:block;font-size:.86rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dk-row small{color:var(--muted);font-size:.75rem}
.dk-links{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.dk-links a{display:flex;flex-direction:column;align-items:center;gap:6px;text-decoration:none;color:var(--ink);font-size:.72rem;text-align:center}
.dk-links a i{width:44px;height:44px;border-radius:13px;background:var(--g-50);color:var(--g-d);display:flex;align-items:center;justify-content:center;font-size:1.05rem}
.dk-goal{display:flex;align-items:center;gap:16px;border-radius:18px;background:linear-gradient(120deg,#373435,#4a4748);color:#fff;padding:18px 22px}
.dk-goal .ic{width:52px;height:52px;border-radius:50%;background:var(--g);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex:none}
@media(max-width:1199.98px){.dk{grid-template-columns:minmax(0,1fr)}.dk-courses{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
<div class="dk">
<div style="min-width:0">
    <div class="row g-3 mb-3">
        <div class="col-xl-6"><div class="dk-hero h-100">
            <i class="fas fa-chalkboard-user art"></i><i class="fas fa-book-open art2"></i>
            <h1><?php echo $dh($greet . ', ' . $firstName); ?>! ☀️</h1>
            <p><?php echo $stats['pending']
                ? 'You have ' . (int) $stats['pending'] . ' submission' . ($stats['pending'] > 1 ? 's' : '') . ' waiting for a grade. Your students are counting on you.'
                : 'You are all caught up on grading. Great teaching builds great careers.'; ?></p>
            <a href="<?php echo url($stats['pending'] ? 'tutor/submissions/index.php' : 'tutor/modules/index.php'); ?>" class="btn btn-success"><?php echo $stats['pending'] ? 'Start grading' : 'Open course builder'; ?> <i class="fas fa-arrow-right ms-1"></i></a>
        </div></div>
        <div class="col-xl-6"><div class="row g-3 h-100">
            <div class="col-4"><a class="dk-stat h-100" href="<?php echo url('tutor/students/index.php'); ?>"><div class="ic" style="background:var(--g-50);color:var(--g-d)"><i class="fas fa-user-graduate"></i></div>
                <div class="k">My students</div><div class="v"><?php echo count($myStudents); ?></div><div class="l">View all →</div></a></div>
            <div class="col-4"><a class="dk-stat h-100" href="<?php echo url('tutor/submissions/index.php'); ?>"><div class="ic" style="background:#fff4e0;color:#b45309"><i class="fas fa-clipboard-check"></i></div>
                <div class="k">To grade</div><div class="v"><?php echo (int) $stats['pending']; ?></div><div class="l">Open →</div></a></div>
            <div class="col-4"><a class="dk-stat h-100" href="<?php echo url('tutor/students/index.php'); ?>"><div class="ic" style="background:#ecebeb;color:var(--char)"><i class="fas fa-award"></i></div>
                <div class="k">Certificates</div><div class="v"><?php echo $certs; ?></div><div class="l">Issued →</div></a></div>
        </div></div>
    </div>

    <div class="dk-card mb-3">
        <div class="dk-h"><h3>My Courses</h3><a href="<?php echo url('tutor/courses/index.php'); ?>">View all →</a></div>
        <?php if (empty($tutor['courses'])): ?><div class="text-muted small">No courses yet.</div><?php endif; ?>
        <div class="dk-courses">
            <?php foreach (array_slice($tutor['courses'], 0, 4) as $i => $c):
                $list = $courseProg[(int) $c['id']] ?? [];
                $pct = $list ? (int) round(array_sum(array_column($list, 'percent')) / count($list)) : 0;
                $img = $covers[(int) $c['id']] ?? null; [$a, $b] = $tones[$i % 4]; ?>
            <a class="dk-course" href="<?php echo url('tutor/modules/index.php?course_id=' . (int) $c['id']); ?>">
                <div class="cv" style="<?php echo $img ? 'background-image:url(' . $dh(settings_asset_url($img)) . ')' : 'background:linear-gradient(135deg,' . $a . ',' . $b . ')'; ?>"><?php if (!$img): ?><i class="fas fa-book-open"></i><?php endif; ?></div>
                <div class="bd"><div class="t"><?php echo $dh($c['title']); ?></div><div class="small text-muted"><?php echo count($list); ?> student<?php echo count($list) === 1 ? '' : 's'; ?></div>
                    <div class="d-flex justify-content-between small mt-1"><span class="text-muted">Avg progress</span><span><?php echo $pct; ?>%</span></div>
                    <div class="dk-bar"><div style="width:<?php echo $pct; ?>%"></div></div></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5"><div class="dk-card h-100">
            <div class="dk-h"><h3>Student Progress</h3></div>
            <div class="d-flex align-items-center gap-4">
                <div style="position:relative;width:116px;height:116px;flex:none">
                    <svg width="116" height="116" viewBox="0 0 116 116" style="transform:rotate(-90deg)"><circle cx="58" cy="58" r="46" fill="none" stroke="#eceee9" stroke-width="12"/>
                        <circle cx="58" cy="58" r="46" fill="none" stroke="#44A024" stroke-width="12" stroke-linecap="round" stroke-dasharray="<?php echo round($circ * $avg / 100, 1) . ' ' . round($circ, 1); ?>"/></svg>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center"><b class="fs-4"><?php echo $avg; ?>%</b><small class="text-muted" style="font-size:.65rem">Average</small></div>
                </div>
                <div class="flex-grow-1 small">
                    <?php foreach ([['Completed', $done, '#44A024'], ['In progress', $started, '#e08a00'], ['Not started', $notStarted, '#9aa0a6']] as [$l, $n, $c]): ?>
                        <div class="d-flex justify-content-between py-1"><span><i class="fas fa-circle me-2" style="color:<?php echo $c; ?>;font-size:.55rem"></i><?php echo $l; ?></span><strong><?php echo $n; ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-3 p-2 rounded-3 small" style="background:#fff8ec;color:#92400e"><i class="fas fa-trophy me-1"></i><?php echo $done ? $done . ' student' . ($done > 1 ? 's' : '') . ' finished every module. Keep it up!' : 'Every module you build moves your students forward.'; ?></div>
        </div></div>
        <div class="col-xl-7"><div class="dk-card h-100">
            <div class="dk-h"><h3>Recent Activity</h3><a href="<?php echo url('tutor/notifications/index.php'); ?>">View all →</a></div>
            <?php if (!$activity): ?><div class="text-muted small">Nothing yet.</div><?php endif; ?>
            <?php foreach ($activity as $n): ?>
                <a class="dk-row" href="<?php echo url('tutor/notifications/index.php?open=' . (int) $n['id']); ?>"><span class="ic"><i class="fas <?php echo ['submission' => 'fa-file-arrow-up', 'student_assigned' => 'fa-user-plus', 'announcement' => 'fa-bullhorn'][$n['type']] ?? 'fa-bell'; ?>"></i></span>
                    <span class="grow"><b><?php echo $dh($n['title']); ?></b><small><?php echo $dh(mb_strimwidth((string) $n['body'], 0, 70, '…')); ?></small></span>
                    <small><?php echo $dh(PortalNotifications::ago($n['created_at'])); ?></small></a>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="dk-goal">
        <span class="ic"><i class="fas fa-bullseye"></i></span>
        <div class="flex-grow-1"><div class="fw-semibold">Build a great course</div><div class="small opacity-75">Add modules, questions and resources once — every student of that course sees them.</div></div>
        <a href="<?php echo url('tutor/modules/index.php'); ?>" class="btn btn-success">Course builder <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
</div>

<div style="min-width:0">
    <div class="dk-card mb-3">
        <div class="dk-h"><h3><a class="text-reset text-decoration-none" href="<?php echo url('tutor/calendar/index.php'); ?>"><?php echo date('F Y', $month); ?></a></h3>
            <span class="small text-muted"><i class="fas fa-circle me-1" style="color:#44A024;font-size:.5rem"></i>class <i class="fas fa-circle ms-2 me-1" style="color:#e08a00;font-size:.5rem"></i>work in</span></div>
        <div class="dk-cal">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="h"><?php echo $d; ?></div><?php endforeach; ?>
            <?php for ($i = 1; $i < (int) date('N', $month); $i++): ?><div></div><?php endfor; ?>
            <?php for ($d = 1; $d <= (int) date('t', $month); $d++): ?>
                <a class="d text-decoration-none text-reset <?php echo $d === (int) date('j') ? 'today' : ''; ?> <?php echo isset($due[$d]) ? 'due' : ''; ?> <?php echo isset($classDays[$d]) ? 'cls' : ''; ?>" href="<?php echo url('tutor/calendar/index.php?view=day&d=' . date('Y-m-') . str_pad((string) $d, 2, '0', STR_PAD_LEFT)); ?>"><?php echo $d; ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <div class="dk-card mb-3">
        <div class="dk-h"><h3>Upcoming classes</h3><a href="<?php echo url('tutor/calendar/index.php'); ?>">Calendar →</a></div>
        <?php if (!$nextClasses): ?><div class="text-muted small"><i class="fas fa-calendar-plus me-1"></i>No classes planned. <a href="<?php echo url('tutor/calendar/edit.php'); ?>">Add one</a>.</div><?php endif; ?>
        <?php foreach ($nextClasses as $c): $cs = cal_from_utc($c['start_utc'], $ctz); ?>
            <a class="dk-row" href="<?php echo url('tutor/calendar/class.php?id=' . (int) $c['id']); ?>"><span class="ic" style="background:var(--g);color:#fff"><i class="fas <?php echo $c['mode'] === 'online' ? 'fa-video' : 'fa-chalkboard'; ?>"></i></span>
                <span class="grow"><b><?php echo $dh($c['title']); ?></b><small><?php echo $dh(CAL_MODES[$c['mode']] . ' · ' . $cs->format('D j M, H:i')); ?></small></span><i class="fas fa-chevron-right text-muted small"></i></a>
        <?php endforeach; ?>
    </div>
    <div class="dk-card mb-3">
        <div class="dk-h"><h3>Waiting for a grade</h3><a href="<?php echo url('tutor/submissions/index.php'); ?>">View all →</a></div>
        <?php if (!$pending): ?><div class="text-muted small"><i class="fas fa-mug-hot me-1"></i>Nothing to grade right now.</div><?php endif; ?>
        <?php foreach (array_slice($pending, 0, 4) as $sub): ?>
            <a class="dk-row" href="<?php echo url('tutor/submissions/index.php#sub-' . (int) $sub['id']); ?>"><span class="ic" style="background:#373435;color:#fff"><i class="fas fa-file-pen"></i></span>
                <span class="grow"><b><?php echo $dh($sub['item_title']); ?></b><small><?php echo $dh($sub['full_name']); ?><?php echo $sub['submitted_at'] ? ' · ' . date('j M, g:i A', strtotime($sub['submitted_at'])) : ''; ?></small></span>
                <i class="fas fa-chevron-right text-muted small"></i></a>
        <?php endforeach; ?>
    </div>
    <div class="dk-card">
        <div class="dk-h"><h3>Quick Links</h3></div>
        <div class="dk-links">
            <a href="<?php echo url('tutor/assignments/index.php'); ?>"><i class="fas fa-file-pen"></i>Assignments</a>
            <a href="<?php echo url('tutor/quizzes/index.php'); ?>"><i class="fas fa-circle-question"></i>Tests &amp; Quizzes</a>
            <a href="<?php echo url('tutor/resources/index.php'); ?>"><i class="fas fa-folder-open"></i>Resources</a>
            <a href="<?php echo url('tutor/announcements/index.php?new=1'); ?>"><i class="fas fa-bullhorn"></i>Announce</a>
        </div>
    </div>
</div>
</div>
