<?php
// Intern dashboard: welcome banner with clock in/out, six cards, and the Tasks / Announcements / Learning / Supervisor / Reports panels.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';
require_once __DIR__ . '/../../../app/v1/services/AnnouncementService.php';

$teach = it_summary_for_intern($db, (int) $intern['id']);
$prog = in_progress($db, $intern, $teach);
$period = in_period($intern);
$tasks = in_tasks($db, (int) $intern['id']);
$today = in_today($db, (int) $intern['id']);
$s = in_settings($db);
$modules = in_modules($db, (int) $intern['id']);
$reports = array_slice(in_reports($db, (int) $intern['id']), 0, 3);
$anns = array_slice(ann_received($db, (int) $intern['user_id']), 0, 3);
$unreadAnn = count(array_filter(ann_received($db, (int) $intern['user_id']), fn($a) => array_key_exists('read_at', $a) && !$a['read_at']));
$open = array_values(array_filter($tasks, fn($t) => $t['status'] !== 'completed'));
$next = null;
foreach ($open as $t) if ($t['deadline'] && $t['deadline'] >= date('Y-m-d') && (!$next || $t['deadline'] < $next['deadline'])) $next = $t;
foreach ($teach['topics'] as $tp) if ($tp['status'] === 'planned' && $tp['starts_at'] && substr($tp['starts_at'], 0, 10) >= date('Y-m-d')
        && (!$next || substr($tp['starts_at'], 0, 10) < $next['deadline'])) $next = ['title' => $tp['title'], 'deadline' => substr($tp['starts_at'], 0, 10), 'priority' => 'teaching'];
$revision = array_values(array_filter($tasks, fn($t) => $t['status'] === 'revision' && $t['revision_comment']));
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = $intern['first_name'] ?: strtok($intern['full_name'], ' ');
$minutes = $today ? in_minutes($today) : 0;
$pill = ['todo' => 'n', 'in_progress' => 'b', 'submitted' => 'o', 'revision' => 'r', 'completed' => 'g'];
$ini = fn($n) => strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $n)), 0, 2))));
$page_title = 'Dashboard';
ob_start();
?>
<section class="ip-hero">
    <div>
        <h1><?php echo in_e($greet . ', ' . $first); ?> 👋</h1>
        <div class="sub"><?php echo in_e(($intern['department'] ? $intern['department'] . ' Department' : 'Internship') . ($intern['supervisor_name'] ? ' · Supervisor: ' . $intern['supervisor_name'] : '')); ?></div>
        <div class="chips">
            <span class="<?php echo $intern['status'] === 'active' ? 'on' : ''; ?>">● Internship <?php echo ucfirst($intern['status']); ?></span>
            <?php if ($period['start']): ?><span><?php echo date('j M Y', $period['start']); ?> → <?php echo $period['end'] ? date('j M Y', $period['end']) : '…'; ?></span><?php endif; ?>
        </div>
    </div>
    <?php if ($s['attendance_enabled'] === '1'): ?>
    <div class="ip-clockbox">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="ip-muted">Today · <?php echo $today && $today['clock_in'] ? 'Clocked in ' . date('g:i A', strtotime($today['clock_in'])) : 'Not clocked in'; ?></span>
            <?php if ($today && $today['clock_in']): ?><span class="ip-pill <?php echo $today['status'] === 'late' ? 'o' : 'g'; ?>"><?php echo INTERN_ATT_STATUSES[$today['status']]; ?></span><?php endif; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-3">
            <div class="t"><?php if ($today && $today['clock_in'] && !$today['clock_out']): ?><span data-live-since="<?php echo strtotime($today['clock_in']); ?>"><?php echo in_hm($minutes); ?></span><?php else: echo in_hm($minutes); endif; ?></div>
            <?php if (!$today || !$today['clock_in']): ?>
                <form method="POST" action="<?php echo url('intern/attendance/clock.php'); ?>" data-clock-form <?php echo $s['save_location'] === '1' ? 'data-location' : ''; ?> class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="in"><button class="btn ip-btn-in">Clock in</button></form>
            <?php elseif (!$today['clock_out']): ?>
                <form method="POST" action="<?php echo url('intern/attendance/clock.php'); ?>" data-clock-form class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="out"><button class="btn ip-btn-out">Clock out</button></form>
            <?php else: ?><span class="ip-pill n">Day done</span><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<div class="ip-stats six">
    <div class="ip-stat"><div class="k">Internship Status</div><div class="v"><?php echo ucfirst($intern['status']); ?></div><div class="s"><?php echo $period['week'] ? '● Week ' . $period['week'] . ($period['weeks'] ? ' of ' . $period['weeks'] : '') : 'Not started'; ?></div></div>
    <div class="ip-stat"><div class="k">Tasks</div><div class="v"><?php echo $prog['task_done']; ?> / <?php echo $prog['task_total']; ?></div><div class="s">completed</div>
        <div class="ip-bar"><div style="width:<?php echo $prog['parts']['tasks']; ?>%"></div></div></div>
    <div class="ip-stat"><div class="k">Attendance</div><div class="v text-success"><?php echo $prog['attendance']['rate']; ?>%</div><div class="s"><?php echo $prog['attendance']['present']; ?> of <?php echo $prog['attendance']['days']; ?> days</div></div>
    <div class="ip-stat"><div class="k">Progress</div><div class="v"><?php echo $prog['overall']; ?>%</div><div class="s">overall</div><div class="ip-bar"><div style="width:<?php echo $prog['overall']; ?>%"></div></div></div>
    <div class="ip-stat"><div class="k">Days Remaining</div><div class="v"><?php echo $period['days_left'] ?? '—'; ?></div><div class="s"><?php echo $period['end'] ? 'ends ' . date('j M Y', $period['end']) : 'no end date'; ?></div></div>
    <div class="ip-stat"><div class="k">Next Deadline</div><div class="v" style="color:#c9392c"><?php echo $next ? date('j M', strtotime($next['deadline'])) : '—'; ?></div>
        <div class="s"><?php echo $next ? in_e(mb_strimwidth($next['title'], 0, 26, '…')) . ' · ' . (($dl = max(0, (int) round((strtotime($next['deadline']) - strtotime(date('Y-m-d'))) / 86400))) === 0 ? 'today' : ($dl === 1 ? 'tomorrow' : 'in ' . $dl . ' days')) : 'nothing due'; ?></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="ip-card">
            <div class="ip-head"><h3>My Tasks</h3><a href="<?php echo url('intern/tasks/index.php'); ?>">View board →</a></div>
            <?php if (!$open && !$teach['topics']): ?><div class="ip-muted">No open tasks.</div><?php endif; ?>
            <?php foreach (array_slice($open, 0, 5) as $t): ?>
                <a class="ip-row" href="<?php echo url('intern/tasks/view.php?id=' . (int) $t['id']); ?>"><div class="grow"><div class="nm"><?php echo in_e($t['title']); ?></div>
                    <div class="ip-muted"><?php echo $t['deadline'] ? 'Due ' . date('j M', strtotime($t['deadline'])) . ' · ' : ''; ?>Priority: <?php echo INTERN_PRIORITIES[$t['priority']]; ?></div></div>
                    <span class="ip-pill <?php echo $pill[$t['status']]; ?>"><?php echo INTERN_TASK_STATUSES[$t['status']]; ?></span></a>
            <?php endforeach; ?>
            <?php foreach (array_slice(array_filter($teach['topics'], fn($t) => $t['status'] === 'planned'), 0, 2) as $tp): ?>
                <a class="ip-row" href="<?php echo url('intern/tasks/teaching.php?id=' . (int) $tp['id']); ?>"><div class="grow"><div class="nm"><?php echo in_e($tp['title']); ?></div>
                    <div class="ip-muted"><?php echo $tp['starts_at'] ? date('D j M, g:i A', strtotime($tp['starts_at'])) : 'Date to be set'; ?></div></div><span class="ip-pill d">Teaching</span></a>
            <?php endforeach; ?>
            <?php if ($revision): ?><div class="ip-alert mt-2"><i class="fas fa-triangle-exclamation me-1"></i><strong>Supervisor comment:</strong> “<?php echo in_e($revision[0]['revision_comment']); ?>” <span class="opacity-75">— <?php echo in_e($revision[0]['title']); ?></span></div><?php endif; ?>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="ip-card">
            <div class="ip-head"><h3>Announcements</h3><span class="ip-muted"><?php echo $unreadAnn; ?> unread</span></div>
            <?php if (!$anns): ?><div class="ip-muted">No announcements.</div><?php endif; ?>
            <?php foreach ($anns as $a): $p = $a['priority'] ?? 'normal'; ?>
                <div class="ip-ann <?php echo in_e($p); ?>">
                    <span class="ip-pill <?php echo $p === 'urgent' ? 'r' : ($p === 'important' ? 'o' : 'n'); ?>"><?php echo ucfirst($p); ?></span>
                    <div class="fw-semibold mt-1"><a class="text-reset text-decoration-none" href="<?php echo url('intern/announcements/index.php?a=' . (int) $a['id']); ?>"><?php echo in_e($a['title']); ?></a></div>
                    <div class="small"><?php echo in_e($a['summary']); ?></div>
                    <div class="ip-muted"><?php echo in_e($a['author_name']); ?> · <?php echo date('j M', strtotime($a['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="ip-card">
            <div class="ip-head"><h3>Learning</h3><a href="<?php echo url('intern/learning/index.php'); ?>">Open</a></div>
            <?php if (!$modules): ?><div class="ip-muted">No learning modules yet.</div><?php endif; ?>
            <?php foreach (array_slice($modules, 0, 4) as $m): ?>
                <div class="mb-3"><div class="d-flex justify-content-between small"><strong><?php echo in_e($m['title']); ?></strong><span><?php echo $m['percent']; ?>%</span></div>
                    <div class="ip-bar"><div style="width:<?php echo $m['percent']; ?>%"></div></div></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="ip-card">
            <h3 class="mb-3">My Supervisor</h3>
            <?php if (!$intern['supervisor_id']): ?><div class="ip-muted">No supervisor assigned yet.</div>
            <?php else: ?>
                <div class="d-flex align-items-center gap-3 mb-3"><span class="as-ava" style="width:48px;height:48px;background:var(--g);color:#fff"><?php echo in_e($ini($intern['supervisor_name'])); ?></span>
                    <div><div class="fw-semibold"><?php echo in_e($intern['supervisor_name']); ?></div><div class="ip-muted"><?php echo in_e($intern['department'] ?? ''); ?></div></div></div>
                <div class="small mb-1"><i class="fas fa-envelope me-2 text-muted"></i><?php echo in_e($intern['supervisor_email']); ?></div>
                <a class="btn btn-success w-100 mt-2" href="<?php echo url('intern/messages/index.php'); ?>"><i class="fas fa-paper-plane me-1"></i>Message Supervisor</a>
            <?php endif; ?>
        </div>
        <div class="ip-card">
            <div class="ip-head"><h3>Reports &amp; Logbook</h3><a href="<?php echo url('intern/reports/index.php'); ?>">+ New</a></div>
            <?php $loggedToday = false; foreach ($reports as $r) if ($r['type'] === 'daily' && $r['period_start'] === date('Y-m-d')) $loggedToday = true; ?>
            <?php foreach ($reports as $r): ?>
                <div class="d-flex justify-content-between align-items-center small py-2 border-bottom"><span><?php echo in_e(in_report_label($r)); ?></span>
                    <span class="ip-pill <?php echo ['approved' => 'g', 'pending' => 'o', 'rejected' => 'r'][$r['status']]; ?>"><?php echo ucfirst($r['status']); ?></span></div>
            <?php endforeach; ?>
            <?php if (!$loggedToday): ?><div class="d-flex justify-content-between align-items-center small py-2"><span>Daily log · today</span><span class="ip-pill n">Not filled</span></div><?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
