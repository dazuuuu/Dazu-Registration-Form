<?php
// My Classes (student): only this student's classes, in their own time zone. Month calendar or list,
// attendance summary, phone calendar feed, email reminder setting and month timetable PDF.
require __DIR__ . '/_boot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        if (($_POST['act'] ?? '') === 'reset_feed') {
            cal_feed_token($db, $me, true);
            SessionModel::setFlash('success', 'Calendar link reset. The old link no longer works — add the new one to your phone.');
        } elseif (($_POST['act'] ?? '') === 'reminders') {
            $db->prepare('INSERT INTO user_profiles (user_id, class_email_reminders) VALUES (?, ?) ON DUPLICATE KEY UPDATE class_email_reminders = VALUES(class_email_reminders)')
               ->execute([$me, !empty($_POST['on']) ? 1 : 0]);
            SessionModel::setFlash('success', !empty($_POST['on']) ? 'Email reminders are on.' : 'Email reminders are off. You still see reminders in the portal.');
        }
    }
    header('Location: index.php');
    exit;
}

$view = ($_GET['view'] ?? '') === 'list' ? 'list' : 'month';
[$ym, $from, $to] = cal_month_bounds((string) ($_GET['m'] ?? ''), $tz);
$first = new DateTime($ym . '-01', new DateTimeZone($tz));
$classes = cal_student_classes($db, $me, $from, $to);
$byDay = [];
foreach ($classes as $c) $byDay[cal_fmt($c['start_utc'], $tz, 'Y-m-d')][] = $c;
$upcoming = array_slice(array_values(array_filter(cal_student_classes($db, $me, cal_now_utc(), gmdate('Y-m-d H:i:s', strtotime('+90 days'))), fn($c) => $c['status'] !== 'cancelled')), 0, 4);
$sum = cal_attendance_summary($db, $me);
$token = cal_feed_token($db, $me);
$feed = PortalNotifications::absolute('calendar/feed.php?t=' . $token);
$emailOn = true;
try {
    $st = $db->prepare('SELECT class_email_reminders FROM user_profiles WHERE user_id = ?');
    $st->execute([$me]);
    $emailOn = $st->fetchColumn() !== '0';
} catch (Throwable $e) {
}
$today = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');
$page_title = 'My Classes';
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-0"><i class="fas fa-calendar-days text-success me-2"></i>My Classes</h1>
        <div class="cl-muted">Times in your time zone: <strong><?php echo cal_e($tz); ?></strong> · <a href="#settings">change</a></div></div>
    <a class="btn btn-outline-secondary" target="_blank" href="print.php?m=<?php echo $ym; ?>"><i class="fas fa-print me-1"></i>Print <?php echo $first->format('F'); ?></a>
</div>
<div class="row g-3">
<div class="col-xl-8">
    <div class="cl-card">
        <div class="cl-head">
            <div class="cl-nav"><a href="?m=<?php echo $prev; ?>&view=<?php echo $view; ?>" aria-label="Previous month"><i class="fas fa-chevron-left"></i></a>
                <a href="?m=<?php echo $next; ?>&view=<?php echo $view; ?>" aria-label="Next month"><i class="fas fa-chevron-right"></i></a><h3 class="ms-2"><?php echo $first->format('F Y'); ?></h3></div>
            <div class="cl-tabs"><a class="<?php echo $view === 'month' ? 'on' : ''; ?>" href="?m=<?php echo $ym; ?>">Month</a><a class="<?php echo $view === 'list' ? 'on' : ''; ?>" href="?m=<?php echo $ym; ?>&view=list">List</a></div>
        </div>
        <?php if ($view === 'month'): ?>
        <div class="cl-month">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $h): ?><div class="h"><?php echo $h; ?></div><?php endforeach; ?>
            <?php for ($i = 1; $i < (int) $first->format('N'); $i++): ?><div></div><?php endfor; ?>
            <?php for ($d = 1; $d <= (int) $first->format('t'); $d++): $k = $ym . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT); ?>
                <div class="cl-day <?php echo $k === $today ? 'today' : ''; ?>"><span class="n"><?php echo $d; ?></span>
                    <?php foreach ($byDay[$k] ?? [] as $c): ?>
                        <a class="cl-ev <?php echo $c['status'] === 'scheduled' ? '' : cal_e($c['status']); ?>" href="class.php?id=<?php echo (int) $c['id']; ?>" title="<?php echo cal_e($c['title'] . ' · ' . cal_fmt($c['start_utc'], $tz)); ?>">
                            <span class="dot <?php echo cal_e($c['mode']); ?>"></span><?php echo cal_e(cal_fmt($c['start_utc'], $tz) . ' ' . $c['title']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
        <?php else: ?>
            <?php if (!$classes): ?><div class="cl-muted py-3">No classes this month.</div><?php endif; ?>
            <?php foreach ($classes as $c): $s = cal_from_utc($c['start_utc'], $tz); ?>
                <a class="cl-row" href="class.php?id=<?php echo (int) $c['id']; ?>">
                    <div class="cl-date"><b><?php echo $s->format('j'); ?></b><small><?php echo $s->format('D'); ?></small></div>
                    <div class="flex-grow-1" style="min-width:0"><div class="fw-semibold"><?php echo cal_e($c['title']); ?></div>
                        <div class="cl-muted"><?php echo cal_e($s->format('H:i') . '–' . cal_fmt($c['end_utc'], $tz) . ' ' . $tz . ' · ' . $c['tutor'] . ($c['topic'] ? ' · ' . $c['topic'] : '')); ?></div>
                        <?php if ($c['status'] === 'moved' && $c['moved_from_utc']): ?><div class="small" style="color:#b45309">Moved from <?php echo cal_e(cal_fmt($c['moved_from_utc'], $tz, 'D j M H:i')); ?></div><?php endif; ?></div>
                    <span class="cl-pill <?php echo cal_e($c['status'] === 'scheduled' ? $c['mode'] : $c['status']); ?>"><?php echo cal_e($c['status'] === 'scheduled' ? CAL_MODES[$c['mode']] : ucfirst($c['status'])); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div class="col-xl-4">
    <div class="cl-card">
        <h3 class="mb-2">Upcoming classes</h3>
        <?php if (!$upcoming): ?><div class="cl-muted">No upcoming classes.</div><?php endif; ?>
        <?php foreach ($upcoming as $c): $s = cal_from_utc($c['start_utc'], $tz); ?>
            <a class="cl-row" href="class.php?id=<?php echo (int) $c['id']; ?>"><div class="cl-date"><b><?php echo $s->format('j'); ?></b><small><?php echo $s->format('M'); ?></small></div>
                <div class="flex-grow-1" style="min-width:0"><div class="fw-semibold small text-truncate"><?php echo cal_e($c['title']); ?></div><div class="cl-muted"><?php echo $s->format('D H:i'); ?> · <?php echo CAL_MODES[$c['mode']]; ?></div></div></a>
        <?php endforeach; ?>
    </div>
    <div class="cl-card">
        <h3 class="mb-2">My attendance</h3>
        <div class="d-flex align-items-center gap-3">
            <div class="fs-2 fw-bold text-success"><?php echo $sum['percent'] === null ? '—' : $sum['percent'] . '%'; ?></div>
            <div class="cl-muted"><?php echo (int) ($sum['attended'] ?? 0); ?> attended · <?php echo $sum['absent']; ?> missed<?php echo $sum['excused'] ? ' · ' . $sum['excused'] . ' excused' : ''; ?></div>
        </div>
    </div>
    <div class="cl-card" id="settings">
        <h3 class="mb-2">Settings</h3>
        <form method="POST" action="<?php echo url('auth/timezone.php'); ?>" class="d-flex gap-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="back" value="<?php echo cal_e($_SERVER['REQUEST_URI']); ?>">
            <select name="timezone" class="form-select form-select-sm"><?php foreach (DateTimeZone::listIdentifiers() as $z): ?><option <?php echo $z === $tz ? 'selected' : ''; ?>><?php echo cal_e($z); ?></option><?php endforeach; ?></select>
            <button class="btn btn-sm btn-success">Save</button></form>
        <form method="POST" class="mb-3"><?php echo csrf_field(); ?><input type="hidden" name="act" value="reminders">
            <label class="form-check form-switch"><input type="checkbox" name="on" value="1" class="form-check-input" <?php echo $emailOn ? 'checked' : ''; ?> onchange="this.form.submit()"> Email me class reminders</label></form>
        <div class="small fw-semibold">Add all my classes to my phone calendar</div>
        <div class="input-group input-group-sm my-1"><input class="form-control" value="<?php echo cal_e($feed); ?>" readonly id="feedUrl"><button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('feedUrl').value)">Copy</button></div>
        <div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm btn-outline-success" href="<?php echo cal_e(preg_replace('#^https?://#', 'webcal://', $feed)); ?>"><i class="fas fa-calendar-plus me-1"></i>Subscribe</a>
            <form method="POST" class="m-0" onsubmit="return confirm('Make a new private link? The old one stops working.')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="reset_feed"><button class="btn btn-sm btn-outline-secondary">Reset link</button></form></div>
        <div class="cl-muted mt-1">Keep this link private — anyone with it can see your timetable.</div>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
