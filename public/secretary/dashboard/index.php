<?php
// Secretary dashboard. Needs no switch, only a signed-in secretary who has not Left and is not suspended (checked in secretary_require on every request).
// A box only shows when its switch is on; the tasks, enquiries, diary and blogs boxes show an empty state until prompts 4 to 6 fill secretary_dash_data().
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';

$sec = secretary_require();
$db = \Models\Connection::getInstance()->getConnection();
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$sw = $sec['switches'];
$on = static fn(string $k): bool => ($sw[$k] ?? 'off') !== 'off';
$now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
$d = secretary_dash_data($db, $sec, $now);
$next = secretary_next_event($db, $sec, $now);
$first = secretary_first_name((string) $sec['full_name']);
$showBlogs = ($sw['blogs_approval'] ?? 'off') === 'on';   // "Blogs waiting" is only for a secretary whose posts need approval

$stats = [];
if ($on('tasks')) $stats[] = ['Tasks today', (int) $d['tasks_today']];
if ($on('enquiries')) $stats[] = ['Pending enquiries', (int) $d['enquiries_pending']];
if ($showBlogs) $stats[] = ['Blogs waiting', (int) $d['blogs_waiting']];
$bits = [$now->format('l, j F')];
if ($on('tasks')) $bits[] = (int) $d['tasks_today'] . ' task' . ((int) $d['tasks_today'] === 1 ? '' : 's') . ' today';
if ($on('enquiries')) $bits[] = (int) $d['enquiries_pending'] . ' enquir' . ((int) $d['enquiries_pending'] === 1 ? 'y' : 'ies') . ' waiting';

$page_title = 'Dashboard';
$sp_active = 'dashboard';
ob_start();
?>
<section class="sp-hero" aria-label="Greeting">
    <div>
        <h2><?php echo $h(secretary_greeting($now) . ($first !== '' ? ', ' . $first : '')); ?></h2>
        <p><?php echo $h(implode(' · ', $bits)); ?></p>
    </div>
    <?php if ($stats): ?>
    <div class="sp-stats">
        <?php foreach ($stats as [$label, $n]): ?><div class="sp-stat"><small><?php echo $h($label); ?></small><b><?php echo (int) $n; ?></b></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<div class="sp-grid">
    <?php if ($on('tasks')): ?>
    <section class="sp-box" aria-label="My tasks">
        <div class="sp-box-h"><h3>My tasks</h3><span>From the admin</span></div>
        <?php if (!$d['tasks']): ?>
            <div class="sp-empty"><i class="fas fa-list-check"></i><b>No tasks yet</b>Tasks the admin gives you will show here.</div>
        <?php else: foreach (array_slice($d['tasks'], 0, 3) as $t): ?>
            <div class="sp-row"><div><b><?php echo $h($t['title'] ?? ''); ?></b><small><?php echo $h($t['due'] ?? ''); ?></small></div>
                <?php if (!empty($t['chip'])): ?><span class="sp-chip <?php echo $h($t['chip_class'] ?? ''); ?>"><?php echo $h($t['chip']); ?></span><?php endif; ?></div>
        <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($on('enquiries')): ?>
    <section class="sp-box" aria-label="Pending enquiries">
        <div class="sp-box-h"><h3>Pending enquiries</h3><span>All secretaries see these</span></div>
        <?php if (!$d['enquiries']): ?>
            <div class="sp-empty"><i class="fas fa-inbox"></i><b>No pending enquiries</b>New enquiries will show here.</div>
        <?php else: foreach (array_slice($d['enquiries'], 0, 3) as $q): ?>
            <div class="sp-row"><div><b><?php echo $h(trim(($q['name'] ?? '') . (!empty($q['course']) ? ' · ' . $q['course'] : ''))); ?></b>
                <small><?php echo $h(trim(($q['how'] ?? '') . (!empty($q['added_by']) ? ' · added by ' . $q['added_by'] : ''), ' ·')); ?></small></div>
                <?php if (!empty($q['status'])): ?><span class="sp-chip <?php echo $h($q['status_class'] ?? ''); ?>"><?php echo $h($q['status']); ?></span><?php endif; ?></div>
        <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($on('staff_calendar')): ?>
    <section class="sp-box sp-next" aria-label="Up next on the Staff Calendar">
        <div class="sp-box-h"><h3>Up next on the Staff Calendar</h3><?php if ($next): ?><span class="sp-chip <?php echo $h($next['in_class']); ?>"><?php echo $h($next['in']); ?></span><?php endif; ?></div>
        <?php if (!$next): ?>
            <div class="sp-empty"><i class="fas fa-calendar-days"></i><b>Nothing coming up</b>The next staff event will show here.</div>
        <?php else: ?>
            <h4><a href="<?php echo $h(url('secretary/staffcalendar/index.php')); ?>" style="color:inherit"><?php echo $h($next['title']); ?></a></h4>
            <p><?php echo $h($next['when'] . ($next['place'] !== '' ? ' · ' . $next['place'] : '')); ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($on('room_visitors')): ?>
    <section class="sp-box" aria-label="Room and visitors today">
        <div class="sp-box-h"><h3>Room and visitors today</h3><span>Diary</span></div>
        <?php if (!$d['diary']): ?>
            <div class="sp-empty"><i class="fas fa-door-open"></i><b>Nothing in the diary today</b>Room bookings and expected visitors will show here.</div>
        <?php else: foreach ($d['diary'] as $r): ?>
            <div class="sp-row"><div><b><?php echo $h(trim(($r['title'] ?? '') . (!empty($r['time']) ? ' · ' . $r['time'] : ''))); ?></b><small><?php echo $h($r['note'] ?? ''); ?></small></div></div>
        <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>
</div>

<div class="sp-note"><strong>What this dashboard never shows:</strong> students, money and fees, settings, other admins’ details and student progress.</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
