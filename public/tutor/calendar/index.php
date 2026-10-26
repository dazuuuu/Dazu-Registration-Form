<?php
// Class Calendar (tutor): month / week / day views in the tutor's time zone, upcoming classes, filters, print timetable.
require __DIR__ . '/_boot.php';

$view = in_array($_GET['view'] ?? '', ['month', 'week', 'day'], true) ? $_GET['view'] : 'month';
$local = new DateTimeZone($tz);
$day = DateTime::createFromFormat('Y-m-d', (string) ($_GET['d'] ?? ''), $local) ?: new DateTime('now', $local);
$day->setTime(0, 0);
if ($view === 'month') {
    $from = (clone $day)->modify('first day of this month');
    $gridStart = (clone $from)->modify('-' . ((int) $from->format('N') - 1) . ' days');
    $gridEnd = (clone $from)->modify('last day of this month')->modify('+' . (7 - (int) (clone $from)->modify('last day of this month')->format('N')) . ' days')->modify('+1 day');
    $prev = (clone $from)->modify('-1 month');
    $next = (clone $from)->modify('+1 month');
    $label = $from->format('F Y');
} elseif ($view === 'week') {
    $gridStart = (clone $day)->modify('-' . ((int) $day->format('N') - 1) . ' days');
    $gridEnd = (clone $gridStart)->modify('+7 days');
    $prev = (clone $gridStart)->modify('-7 days');
    $next = (clone $gridStart)->modify('+7 days');
    $label = $gridStart->format('j M') . ' – ' . (clone $gridEnd)->modify('-1 day')->format('j M Y');
} else {
    $gridStart = clone $day;
    $gridEnd = (clone $day)->modify('+1 day');
    $prev = (clone $day)->modify('-1 day');
    $next = (clone $day)->modify('+1 day');
    $label = $day->format('l j F Y');
}
$utc = new DateTimeZone('UTC');
$filters = ['student' => (int) ($_GET['student'] ?? 0), 'mode' => (string) ($_GET['mode'] ?? ''), 'course' => (int) ($_GET['course'] ?? 0)];
$classes = cal_tutor_classes($db, $me, (clone $gridStart)->setTimezone($utc)->format('Y-m-d H:i:s'), (clone $gridEnd)->setTimezone($utc)->format('Y-m-d H:i:s'), $filters);
$byDay = [];
foreach ($classes as $c) $byDay[cal_fmt($c['start_utc'], $tz, 'Y-m-d')][] = $c;
$upcoming = cal_tutor_classes($db, $me, cal_now_utc(), gmdate('Y-m-d H:i:s', strtotime('+60 days')), $filters);
$upcoming = array_slice(array_values(array_filter($upcoming, fn($c) => $c['status'] !== 'cancelled')), 0, 6);
$students = cal_tutor_students($db, $me);
$courses = [];
foreach ($students as $s) $courses[(int) $s['course_id']] = $s['course'];
$today = (new DateTime('now', $local))->format('Y-m-d');
$q = fn(array $extra) => '?' . http_build_query(array_filter(['view' => $view, 'd' => $day->format('Y-m-d')] + $filters) + $extra);
$ev = function (array $c) use ($tz) {
    $cls = $c['status'] === 'scheduled' ? '' : $c['status'];
    return '<a class="cl-ev ' . $cls . '" href="class.php?id=' . (int) $c['id'] . '" title="' . cal_e($c['title'] . ' · ' . cal_fmt($c['start_utc'], $tz) . '–' . cal_fmt($c['end_utc'], $tz) . ' · ' . CAL_MODES[$c['mode']] . ($c['status'] !== 'scheduled' ? ' · ' . ucfirst($c['status']) : '')) . '">'
         . '<span class="dot ' . cal_e($c['mode']) . '"></span>' . cal_e(cal_fmt($c['start_utc'], $tz) . ' ' . $c['title']) . '</a>';
};
$page_title = 'Class Calendar';
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-0"><i class="fas fa-calendar-days text-success me-2"></i>Class Calendar</h1>
        <div class="cl-muted">Times in your time zone: <strong><?php echo cal_e($tz); ?></strong> · <a href="#tz" data-bs-toggle="collapse">change</a></div></div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="summary.php"><i class="fas fa-user-check me-1"></i>Attendance summary</a>
        <a class="btn btn-outline-secondary" data-bs-toggle="collapse" href="#print"><i class="fas fa-print me-1"></i>Print timetable</a>
        <a class="btn btn-success" href="edit.php?date=<?php echo $view === 'month' ? $today : $day->format('Y-m-d'); ?>"><i class="fas fa-plus me-1"></i>New class</a>
    </div>
</div>
<form method="POST" action="<?php echo url('auth/timezone.php'); ?>" class="collapse cl-card" id="tz"><?php echo csrf_field(); ?><input type="hidden" name="back" value="<?php echo cal_e($_SERVER['REQUEST_URI']); ?>">
    <div class="d-flex gap-2 flex-wrap align-items-center"><label class="small fw-semibold">Your time zone</label>
        <select name="timezone" class="form-select form-select-sm" style="max-width:280px"><?php foreach (DateTimeZone::listIdentifiers() as $z): ?><option <?php echo $z === $tz ? 'selected' : ''; ?>><?php echo cal_e($z); ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-success">Save</button></div>
</form>
<form method="GET" action="print.php" target="_blank" class="collapse cl-card" id="print">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Month</label><input type="month" name="m" value="<?php echo $day->format('Y-m'); ?>" class="form-control form-control-sm" required></div>
        <div class="col-md-4"><label class="form-label small mb-1">One student</label><select name="student" class="form-select form-select-sm"><option value="">—</option>
            <?php foreach ($students as $uid => $s): ?><option value="<?php echo $uid; ?>"><?php echo cal_e($s['full_name']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small mb-1">…or a course class</label><select name="course" class="form-select form-select-sm"><option value="">—</option>
            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo $cid; ?>"><?php echo cal_e($ct); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-success w-100"><i class="fas fa-file-pdf me-1"></i>PDF</button></div>
    </div>
</form>

<div class="row g-3">
<div class="col-xl-9">
    <div class="cl-card">
        <div class="cl-head">
            <div class="cl-nav"><a href="<?php echo $q(['d' => $prev->format('Y-m-d')]); ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
                <a href="<?php echo $q(['d' => $today]); ?>" style="width:auto;padding:0 12px;border-radius:999px;font-size:.8rem">Today</a>
                <a href="<?php echo $q(['d' => $next->format('Y-m-d')]); ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
                <h3 class="ms-2"><?php echo cal_e($label); ?></h3></div>
            <div class="cl-tabs"><?php foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $k => $l): ?><a class="<?php echo $view === $k ? 'on' : ''; ?>" href="<?php echo $q(['view' => $k]); ?>"><?php echo $l; ?></a><?php endforeach; ?></div>
        </div>
        <div class="cl-legend mb-2 text-end"><span><span class="dot physical d-inline-block rounded-circle" style="width:8px;height:8px"></span> Physical</span><span><span class="dot online d-inline-block rounded-circle" style="width:8px;height:8px"></span> Online</span>
            <span><span class="dot hybrid d-inline-block rounded-circle" style="width:8px;height:8px"></span> Hybrid</span><span class="text-danger">Cancelled</span><span style="color:#b45309">Moved</span></div>

        <?php if ($view === 'month'): ?>
        <div class="cl-month">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $h): ?><div class="h"><?php echo $h; ?></div><?php endforeach; ?>
            <?php for ($d = clone $gridStart; $d < $gridEnd; $d->modify('+1 day')): $k = $d->format('Y-m-d'); ?>
                <div class="cl-day <?php echo $d->format('m') !== $from->format('m') ? 'out' : ''; ?> <?php echo $k === $today ? 'today' : ''; ?>">
                    <span class="n"><?php echo $d->format('j'); ?></span>
                    <?php if ($k >= $today): ?><a class="add" href="edit.php?date=<?php echo $k; ?>" title="Add a class">+ add</a><?php endif; ?>
                    <?php foreach (array_slice($byDay[$k] ?? [], 0, 4) as $c) echo $ev($c); ?>
                    <?php if (count($byDay[$k] ?? []) > 4): ?><a class="cl-muted text-decoration-none" style="font-size:.7rem" href="<?php echo $q(['view' => 'day', 'd' => $k]); ?>">+<?php echo count($byDay[$k]) - 4; ?> more</a><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <?php elseif ($view === 'week'): ?>
        <div class="cl-week">
            <?php for ($d = clone $gridStart; $d < $gridEnd; $d->modify('+1 day')): $k = $d->format('Y-m-d'); ?>
                <div class="col <?php echo $k === $today ? 'today' : ''; ?>">
                    <div class="d-flex justify-content-between mb-2"><a class="fw-semibold small text-decoration-none text-dark" href="<?php echo $q(['view' => 'day', 'd' => $k]); ?>"><?php echo $d->format('D j'); ?></a>
                        <?php if ($k >= $today): ?><a class="small text-decoration-none" href="edit.php?date=<?php echo $k; ?>">+</a><?php endif; ?></div>
                    <?php foreach ($byDay[$k] ?? [] as $c): ?><div class="mb-1"><?php echo $ev($c); ?></div><?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
        <?php else: ?>
            <?php $list = $byDay[$day->format('Y-m-d')] ?? []; ?>
            <?php if (!$list): ?><div class="cl-muted py-3">No classes on this day. <?php if ($day->format('Y-m-d') >= $today): ?><a href="edit.php?date=<?php echo $day->format('Y-m-d'); ?>">Add one</a><?php endif; ?></div><?php endif; ?>
            <?php foreach ($list as $c): ?>
                <a class="cl-row" href="class.php?id=<?php echo (int) $c['id']; ?>">
                    <div class="cl-date"><b><?php echo cal_fmt($c['start_utc'], $tz); ?></b><small><?php echo cal_fmt($c['end_utc'], $tz); ?></small></div>
                    <div class="flex-grow-1"><div class="fw-semibold"><?php echo cal_e($c['title']); ?></div>
                        <div class="cl-muted"><?php echo cal_e(($c['course_title'] ?? '') . ' · ' . $c['students'] . ' student' . ($c['students'] == 1 ? '' : 's') . ($c['campus'] ? ' · ' . $c['campus'] . ($c['room'] ? ', ' . $c['room'] : '') : '')); ?></div></div>
                    <span class="cl-pill <?php echo cal_e($c['status'] === 'scheduled' ? $c['mode'] : $c['status']); ?>"><?php echo cal_e($c['status'] === 'scheduled' ? CAL_MODES[$c['mode']] : ucfirst($c['status'])); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div class="col-xl-3">
    <form method="GET" class="cl-card"><input type="hidden" name="view" value="<?php echo $view; ?>"><input type="hidden" name="d" value="<?php echo $day->format('Y-m-d'); ?>">
        <h3 class="mb-2">Filters</h3>
        <select name="student" class="form-select form-select-sm mb-2"><option value="">All students</option>
            <?php foreach ($students as $uid => $s): ?><option value="<?php echo $uid; ?>" <?php echo $filters['student'] === $uid ? 'selected' : ''; ?>><?php echo cal_e($s['full_name']); ?></option><?php endforeach; ?></select>
        <select name="course" class="form-select form-select-sm mb-2"><option value="">All courses / groups</option>
            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo $cid; ?>" <?php echo $filters['course'] === $cid ? 'selected' : ''; ?>><?php echo cal_e($ct); ?></option><?php endforeach; ?></select>
        <select name="mode" class="form-select form-select-sm mb-2"><option value="">All modes</option>
            <?php foreach (CAL_MODES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $filters['mode'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select>
        <div class="d-flex gap-2"><button class="btn btn-sm btn-success flex-fill">Apply</button><a class="btn btn-sm btn-outline-secondary" href="?view=<?php echo $view; ?>">Clear</a></div>
    </form>
    <div class="cl-card">
        <h3 class="mb-2">Upcoming classes</h3>
        <?php if (!$upcoming): ?><div class="cl-muted">Nothing planned. <a href="edit.php">Add a class</a>.</div><?php endif; ?>
        <?php foreach ($upcoming as $c): $s = cal_from_utc($c['start_utc'], $tz); ?>
            <a class="cl-row" href="class.php?id=<?php echo (int) $c['id']; ?>"><div class="cl-date"><b><?php echo $s->format('j'); ?></b><small><?php echo $s->format('M'); ?></small></div>
                <div class="flex-grow-1" style="min-width:0"><div class="fw-semibold small text-truncate"><?php echo cal_e($c['title']); ?></div>
                    <div class="cl-muted"><?php echo $s->format('D H:i'); ?> · <span class="dot <?php echo cal_e($c['mode']); ?> d-inline-block rounded-circle" style="width:7px;height:7px"></span> <?php echo CAL_MODES[$c['mode']]; ?><?php echo $c['status'] === 'moved' ? ' · moved' : ''; ?></div></div></a>
        <?php endforeach; ?>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
