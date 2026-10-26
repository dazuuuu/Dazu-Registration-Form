<?php
// Attendance: clock in/out with live working time, month calendar, stats and history (CSV export).
require __DIR__ . '/../_boot.php';

$s = in_settings($db);
$stats = in_attendance_stats($db, $intern);
$today = in_today($db, (int) $intern['id']);
$history = in_attendance_rows($db, (int) $intern['id'], date('Y-m-d', strtotime('-120 days')));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Clock in', 'Clock out', 'Hours', 'Status', 'Excused', 'Note'], ',', '"', '');
    foreach ($history as $r) {
        fputcsv($out, [$r['work_date'], $r['clock_in'] ? date('H:i', strtotime($r['clock_in'])) : '', $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '',
                       in_hm(in_minutes($r)), INTERN_ATT_STATUSES[$r['status']], $r['excused'] ? 'yes' : '', $r['note']], ',', '"', '');
    }
    exit;
}

$m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
$first = strtotime($m . '-01');
$workDays = in_work_days($db);
$byDay = [];
foreach (in_attendance_rows($db, (int) $intern['id'], date('Y-m-01', $first), date('Y-m-t', $first)) as $r) $byDay[$r['work_date']] = $r;
$minutes = $today ? in_minutes($today) : 0;
$target = (int) round((float) $s['daily_hours'] * 60);
$frac = $target ? min(1, $minutes / $target) : 0;
$circ = 2 * M_PI * 76;
$badge = ['on_time' => 'g', 'late' => 'o', 'early_out' => 'o', 'absent' => 'r'];
$page_title = 'Attendance';
ob_start();
?>
<?php if ($s['attendance_enabled'] !== '1'): ?><div class="alert alert-secondary">Clock in and out is turned off by the office.</div><?php endif; ?>
<div class="row g-3">
    <div class="col-xl-5">
        <div class="ip-card text-center">
            <div class="ip-muted mb-2"><?php echo date('l, j F'); ?></div>
            <div class="ip-ring mb-2">
                <svg width="170" height="170" viewBox="0 0 170 170"><circle cx="85" cy="85" r="76" fill="none" stroke="#e8f3e2" stroke-width="10"/>
                    <circle cx="85" cy="85" r="76" fill="none" stroke="#44A024" stroke-width="10" stroke-linecap="round" stroke-dasharray="<?php echo round($circ * $frac, 1) . ' ' . round($circ, 1); ?>"/></svg>
                <div class="c"><b><?php if ($today && $today['clock_in'] && !$today['clock_out']): ?><span data-live-since="<?php echo strtotime($today['clock_in']); ?>"><?php echo in_hm($minutes); ?></span><?php else: echo in_hm($minutes); endif; ?></b>
                    <span class="ip-muted">of <?php echo in_e($s['daily_hours']); ?>h</span></div>
            </div>
            <?php if ($today && $today['clock_in']): ?>
                <span class="ip-pill <?php echo $badge[$today['status']]; ?> mb-3">● <?php echo $today['clock_out'] ? 'Clocked out ' . date('g:i A', strtotime($today['clock_out'])) : 'Working · clocked in ' . date('g:i A', strtotime($today['clock_in'])); ?> · <?php echo INTERN_ATT_STATUSES[$today['status']]; ?></span>
            <?php endif; ?>
            <?php if ($s['attendance_enabled'] === '1'): ?>
                <?php if (!$today || !$today['clock_in']): ?>
                    <form method="POST" action="clock.php" data-clock-form <?php echo $s['save_location'] === '1' ? 'data-location' : ''; ?>><?php echo csrf_field(); ?><input type="hidden" name="action" value="in">
                        <button class="btn ip-btn-in w-100 py-3 fs-6 mt-2">Clock in</button></form>
                <?php elseif (!$today['clock_out']): ?>
                    <form method="POST" action="clock.php" data-clock-form><?php echo csrf_field(); ?><input type="hidden" name="action" value="out"><button class="btn ip-btn-out w-100 py-3 fs-6">Clock out</button></form>
                <?php else: ?><div class="ip-muted">See you tomorrow!</div><?php endif; ?>
            <?php endif; ?>
            <div class="ip-muted mt-3">Work hours <?php echo date('g:i A', strtotime($s['work_start'])); ?> – <?php echo date('g:i A', strtotime($s['work_end'])); ?>
                · late after <?php echo (int) $s['late_after_minutes']; ?> min<?php echo $s['save_location'] === '1' ? ' · your location is saved when you clock in' : ''; ?></div>
        </div>
        <div class="ip-stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="ip-stat"><div class="k">Attendance</div><div class="v text-success"><?php echo $stats['rate']; ?>%</div><div class="s"><?php echo $stats['present']; ?> of <?php echo $stats['days']; ?> days</div></div>
            <div class="ip-stat"><div class="k">Hours this week</div><div class="v"><?php echo in_hm($stats['week_minutes']); ?></div><div class="s">target <?php echo (int) $stats['week_target']; ?>h</div></div>
            <div class="ip-stat"><div class="k">Late arrivals</div><div class="v" style="color:#e08a00"><?php echo $stats['late_month']; ?></div><div class="s">this month</div></div>
            <div class="ip-stat"><div class="k">Absences</div><div class="v" style="color:#c9392c"><?php echo $stats['absent']; ?></div><div class="s"><?php echo $stats['excused']; ?> excused</div></div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="ip-card">
            <div class="ip-head"><div class="d-flex align-items-center gap-2"><a class="ip-link" href="?m=<?php echo date('Y-m', strtotime('-1 month', $first)); ?>">←</a><h3><?php echo date('F Y', $first); ?></h3><a class="ip-link" href="?m=<?php echo date('Y-m', strtotime('+1 month', $first)); ?>">→</a></div>
                <div class="ip-legend"><span><i style="background:#44A024"></i>Present</span><span><i style="background:#e08a00"></i>Late</span><span><i style="background:#c9392c"></i>Absent</span></div></div>
            <div class="ip-cal">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="h"><?php echo $d; ?></div><?php endforeach; ?>
                <?php for ($i = 1; $i < (int) date('N', $first); $i++): ?><div class="d blank"></div><?php endfor; ?>
                <?php for ($d = 1; $d <= (int) date('t', $first); $d++):
                    $key = $m . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                    $r = $byDay[$key] ?? null;
                    $work = in_array((int) date('N', strtotime($key)), $workDays, true);
                    $before = $intern['start_date'] && $key < $intern['start_date'];
                    if ($key > date('Y-m-d') || $before) $cls = 'future';
                    elseif ($r && $r['clock_in']) $cls = $r['status'] === 'late' ? 'late' : 'present';
                    elseif ($r && $r['excused']) $cls = 'excused';
                    elseif ($work && $key < date('Y-m-d')) $cls = 'absent';
                    else $cls = 'off'; ?>
                    <div class="d <?php echo $cls; ?>" title="<?php echo in_e($key . ($r ? ' · ' . INTERN_ATT_STATUSES[$r['status']] : '')); ?>"><?php echo $d; ?></div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="ip-card">
            <div class="ip-head"><h3>Attendance history</h3><a href="?export=csv">Export</a></div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Clock in</th><th>Clock out</th><th>Hours</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$history): ?><tr><td colspan="5" class="ip-muted text-center py-4">No attendance yet.</td></tr><?php endif; ?>
                <?php foreach (array_slice($history, 0, 30) as $r): ?>
                    <tr><td class="fw-semibold"><?php echo date('D j M', strtotime($r['work_date'])); ?></td>
                        <td><?php echo $r['clock_in'] ? date('g:i A', strtotime($r['clock_in'])) : '—'; ?></td>
                        <td><?php echo $r['clock_out'] ? date('g:i A', strtotime($r['clock_out'])) : '—'; ?></td>
                        <td><?php echo in_hm(in_minutes($r)) . ($r['clock_in'] && !$r['clock_out'] && $r['work_date'] === date('Y-m-d') ? '*' : ''); ?></td>
                        <td><span class="ip-pill <?php echo $r['excused'] ? 'n' : $badge[$r['status']]; ?>"><?php echo $r['excused'] ? 'Excused' : INTERN_ATT_STATUSES[$r['status']]; ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
