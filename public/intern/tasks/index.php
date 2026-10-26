<?php
// My Tasks: Board (kanban), List and Calendar views. Teaching topics from a tutor show with a "Teaching" label.
require __DIR__ . '/../_boot.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

$view = in_array($_GET['view'] ?? '', ['board', 'list', 'calendar'], true) ? $_GET['view'] : 'board';
$tasks = in_tasks($db, (int) $intern['id']);
$teaching = it_topics_for_intern($db, (int) $intern['id']);
$cols = ['todo' => [], 'in_progress' => [], 'submitted' => [], 'revision' => [], 'completed' => []];
foreach ($tasks as $t) $cols[$t['status']][] = $t;
$teachCols = ['todo' => [], 'in_progress' => [], 'submitted' => [], 'revision' => [], 'completed' => []];
foreach ($teaching as $tp) $teachCols[$tp['status'] === 'taught' ? 'completed' : 'todo'][] = $tp;
$done = count($cols['completed']) + count($teachCols['completed']);
$total = count($tasks) + count($teaching);
$pri = ['normal' => 'n', 'medium' => 'o', 'high' => 'r'];
$pill = ['todo' => 'n', 'in_progress' => 'b', 'submitted' => 'o', 'revision' => 'r', 'completed' => 'g'];
$ini = fn($n) => strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $n)), 0, 2))));
$surname = fn($n) => ($p = preg_split('/\s+/', trim((string) $n))) ? (count($p) > 1 ? end($p) : $p[0]) : '';
$fabTask = null;
foreach (['revision', 'in_progress', 'todo'] as $st) if (!$fabTask && $cols[$st]) $fabTask = $cols[$st][0];
$page_title = 'My Tasks';
$page_subtitle = $total . ' tasks · ' . $done . ' completed';
$tlink = fn($t) => url('intern/tasks/view.php?id=' . (int) $t['id']);
$plink = fn($tp) => url('intern/tasks/teaching.php?id=' . (int) $tp['id']);
ob_start();
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="ip-viewtabs">
        <?php foreach (['board' => 'Board', 'list' => 'List', 'calendar' => 'Calendar'] as $k => $l): ?><a href="?view=<?php echo $k; ?>" class="<?php echo $view === $k ? 'on' : ''; ?>"><?php echo $l; ?></a><?php endforeach; ?>
    </div>
    <span class="ip-muted"><?php echo $total; ?> tasks · <?php echo $done; ?> completed</span>
</div>

<?php if ($view === 'board'): ?>
<div class="ip-board">
    <?php foreach (INTERN_TASK_STATUSES as $k => $label): $list = $cols[$k]; $tl = $teachCols[$k]; ?>
    <div class="ip-col <?php echo $k === 'revision' ? 'rev' : ''; ?>">
        <h4 class="<?php echo $k === 'revision' ? 'text-danger' : ''; ?>"><?php echo $label; ?> <span class="n"><?php echo count($list) + count($tl); ?></span></h4>
        <?php foreach ($tl as $tp): ?>
            <a class="ip-task" href="<?php echo $plink($tp); ?>">
                <div class="d-flex justify-content-between"><span class="ip-pill d">Teaching</span><span class="ip-muted"><?php echo $tp['starts_at'] ? date('j M, g:i A', strtotime($tp['starts_at'])) : ''; ?></span></div>
                <div class="ttl"><?php echo in_e($tp['title']); ?></div>
                <div class="who"><b><?php echo in_e($ini($tp['tutor_name'])); ?></b><?php echo in_e($tp['tutor_name']); ?><?php echo $tp['status'] === 'taught' ? ' · ✓ Taught' : ''; ?></div>
            </a>
        <?php endforeach; ?>
        <?php foreach ($list as $t): ?>
            <a class="ip-task" href="<?php echo $tlink($t); ?>">
                <div class="d-flex justify-content-between"><span class="ip-pill <?php echo $pri[$t['priority']]; ?>"><?php echo INTERN_PRIORITIES[$t['priority']]; ?></span>
                    <span class="ip-muted"><?php echo $t['deadline'] ? 'Due ' . date('j M', strtotime($t['deadline'])) : ''; ?></span></div>
                <div class="ttl"><?php echo in_e($t['title']); ?></div>
                <?php if ($k === 'submitted'): ?><div class="ip-muted mb-1"><i class="far fa-clock me-1"></i>Awaiting review</div><?php endif; ?>
                <?php if ($k === 'completed' && $t['completed_at']): ?><div class="small text-success mb-1">✓ Approved · <?php echo date('j M', strtotime($t['completed_at'])); ?></div><?php endif; ?>
                <?php if ($k === 'revision' && $t['revision_comment']): ?><div class="ip-quote">“<?php echo in_e(mb_strimwidth($t['revision_comment'], 0, 120, '…')); ?>”</div><?php endif; ?>
                <div class="who mt-1"><b><?php echo in_e($ini($t['assigned_by_name'])); ?></b><?php echo in_e($t['assigned_by_name']); ?></div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($view === 'list'): ?>
<div class="ip-card">
    <?php if (!$tasks && !$teaching): ?><div class="ip-muted">No tasks yet. Your supervisor adds them here.</div><?php endif; ?>
    <?php foreach ($tasks as $t): ?>
        <a class="ip-row" href="<?php echo $tlink($t); ?>"><span class="ip-pill <?php echo $pri[$t['priority']]; ?>"><?php echo INTERN_PRIORITIES[$t['priority']]; ?></span>
            <div class="grow"><div class="nm"><?php echo in_e($t['title']); ?></div><div class="ip-muted"><?php echo $t['deadline'] ? 'Due ' . date('j M Y', strtotime($t['deadline'])) . ' · ' : ''; ?><?php echo in_e($t['assigned_by_name']); ?></div>
                <?php if ($t['status'] === 'revision' && $t['revision_comment']): ?><div class="ip-quote">“<?php echo in_e($t['revision_comment']); ?>”</div><?php endif; ?></div>
            <span class="ip-pill <?php echo $pill[$t['status']]; ?>"><?php echo INTERN_TASK_STATUSES[$t['status']]; ?></span></a>
    <?php endforeach; ?>
    <?php if ($teaching): ?><h3 class="mt-3 mb-2" id="teaching">Teaching</h3><?php endif; ?>
    <?php foreach ($teaching as $tp): ?>
        <a class="ip-row" href="<?php echo $plink($tp); ?>"><span class="ip-pill d">Teaching</span>
            <div class="grow"><div class="nm"><?php echo in_e($tp['title']); ?></div><div class="ip-muted"><?php echo $tp['starts_at'] ? date('D j M, g:i A', strtotime($tp['starts_at'])) : 'Date to be set'; ?><?php echo $tp['duration_min'] ? ' · ' . (int) $tp['duration_min'] . ' min' : ''; ?> · <?php echo in_e($tp['tutor_name']); ?></div></div>
            <span class="ip-pill <?php echo $tp['status'] === 'taught' ? 'g' : 'n'; ?>"><?php echo $tp['status'] === 'taught' ? 'Taught' : 'Planned'; ?></span></a>
    <?php endforeach; ?>
</div>

<?php else:
    $m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
    $first = strtotime($m . '-01');
    $events = [];
    foreach ($tasks as $t) if ($t['deadline']) $events[$t['deadline']][] = ['t' => $t['title'], 'u' => $tlink($t), 'c' => $pill[$t['status']]];
    foreach ($teaching as $tp) if ($tp['starts_at']) $events[substr($tp['starts_at'], 0, 10)][] = ['t' => '🎓 ' . $tp['title'], 'u' => $plink($tp), 'c' => 'd'];
?>
<div class="ip-card">
    <div class="ip-head"><a href="?view=calendar&m=<?php echo date('Y-m', strtotime('-1 month', $first)); ?>">←</a><h3><?php echo date('F Y', $first); ?></h3><a href="?view=calendar&m=<?php echo date('Y-m', strtotime('+1 month', $first)); ?>">→</a></div>
    <div class="ip-cal" style="gap:4px">
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="h"><?php echo $d; ?></div><?php endforeach; ?>
        <?php for ($i = 1; $i < (int) date('N', $first); $i++): ?><div></div><?php endfor; ?>
        <?php for ($d = 1; $d <= (int) date('t', $first); $d++): $key = $m . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT); ?>
            <div class="border rounded-3 p-1" style="min-height:76px;<?php echo $key === date('Y-m-d') ? 'border-color:#44A024!important;border-width:2px!important' : ''; ?>">
                <div class="ip-muted"><?php echo $d; ?></div>
                <?php foreach ($events[$key] ?? [] as $ev): ?><a href="<?php echo $ev['u']; ?>" class="ip-pill <?php echo $ev['c']; ?> d-block text-truncate text-decoration-none mb-1" style="max-width:100%"><?php echo in_e($ev['t']); ?></a><?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($fabTask): ?><a class="ip-fab" href="<?php echo $tlink($fabTask); ?>#submit"><i class="fas fa-plus me-2"></i>Submit work</a><?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
