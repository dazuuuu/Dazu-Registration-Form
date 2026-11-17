<?php
// Admin: the Activity record. What each secretary did, with her name and the time: blogs posted, edited and unpublished, enquiries added and changed, tasks ticked, diary entries.
// Filter by secretary and by date, and search. Normal admin ONLY (secretary_admin_require): the super admin, secretaries and everyone else get a plain 403.
// The records are written by one shared function (secretary_activity), so nothing is listed twice.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';

use Models\Connection;

secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$q = trim((string) ($_GET['q'] ?? ''));
$sid = (int) ($_GET['s'] ?? 0);
$area = in_array($_GET['a'] ?? '', ['blogs', 'enquiries', 'tasks', 'diary'], true) ? (string) $_GET['a'] : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '';
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 50;
$notReady = false; $rows = []; $total = 0; $people = [];
try {
    $w = ['1 = 1']; $a = [];
    if ($sid > 0) { $w[] = 'secretary_id = ?'; $a[] = $sid; }
    if ($area !== '') { $w[] = 'area = ?'; $a[] = $area; }
    if ($from !== '') { $w[] = 'created_at >= ?'; $a[] = $from . ' 00:00:00'; }
    if ($to !== '') { $w[] = 'created_at < ?'; $a[] = (new DateTime($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; }
    if ($q !== '') {
        $like = '%' . strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q)) . '%';
        $w[] = "(LOWER(actor_name) LIKE ? ESCAPE '!' OR LOWER(subject) LIKE ? ESCAPE '!' OR LOWER(COALESCE(detail, '')) LIKE ? ESCAPE '!' OR LOWER(action) LIKE ? ESCAPE '!')";
        array_push($a, $like, $like, $like, $like);
    }
    $c = $pdo->prepare('SELECT COUNT(*) FROM secretary_activity WHERE ' . implode(' AND ', $w)); $c->execute($a); $total = (int) $c->fetchColumn();
    $s = $pdo->prepare('SELECT * FROM secretary_activity WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)); $s->execute($a); $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pdo->query("SELECT DISTINCT secretary_id, actor_name FROM secretary_activity WHERE secretary_id IS NOT NULL ORDER BY actor_name")->fetchAll(PDO::FETCH_ASSOC) as $p) $people[(int) $p['secretary_id']] = $p['actor_name'];
} catch (Throwable $e) { $notReady = true; }
$areas = ['blogs' => 'Blogs', 'enquiries' => 'Enquiries', 'tasks' => 'Tasks', 'diary' => 'Diary'];
$icon = ['blogs' => 'fa-newspaper', 'enquiries' => 'fa-inbox', 'tasks' => 'fa-list-check', 'diary' => 'fa-door-open'];
$qs = static fn(array $o) => http_build_query(array_filter($o + ['q' => $GLOBALS['q'], 's' => $GLOBALS['sid'] ?: '', 'a' => $GLOBALS['area'], 'from' => $GLOBALS['from'], 'to' => $GLOBALS['to']], fn($v) => $v !== '' && $v !== null));

$page_title = 'Activity record';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Activity', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.ac-card { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:18px; margin-bottom:16px; }
.ac-f { display:grid; grid-template-columns:2fr 1.4fr 1.2fr 1fr 1fr auto; gap:10px; align-items:end; } .ac-f label { font-size:.75rem; font-weight:700; display:block; margin-bottom:3px; }
.ac-f input, .ac-f select { width:100%; min-height:44px; border:1px solid #cbd5d0; border-radius:10px; padding:0 10px; }
.ac-btn { background:#048C44; color:#fff; border:0; border-radius:12px; min-height:44px; padding:0 20px; font-weight:800; } .ac-btn:hover { background:#037A3B; }
.ac-row { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid #eef3ef; align-items:flex-start; } .ac-row:last-child { border:0; }
.ac-ico { width:38px; height:38px; flex:0 0 auto; border-radius:12px; background:#E3F3EA; color:#037A3B; display:flex; align-items:center; justify-content:center; }
.ac-m { flex:1; min-width:0; overflow-wrap:anywhere; } .ac-m small { color:#6b7570; display:block; } .ac-t { color:#6b7570; font-size:.82rem; white-space:nowrap; text-align:right; }
@media (max-width: 767.98px) { .ac-f { grid-template-columns:1fr 1fr; } .ac-f .w { grid-column:1 / -1; } .ac-row { flex-wrap:wrap; } .ac-t { white-space:normal; text-align:left; width:100%; padding-left:50px; } .ac-btn { width:100%; min-height:48px; } }
</style>
CSS;
ob_start();
?>
<?php if ($notReady): ?><div class="alert alert-warning">The activity table is not in the database yet. Run the database update (migrations 129 to 132) first.</div><?php else: ?>
<form class="ac-card ac-f" method="GET" action="activity.php" role="search">
    <div class="w"><label for="q">Search</label><input id="q" type="search" name="q" value="<?php echo $h($q); ?>" maxlength="100" placeholder="Name, title, word…"></div>
    <div><label for="s">Secretary</label><select id="s" name="s"><option value="">Everyone</option><?php foreach ($people as $pid => $nm): ?><option value="<?php echo (int) $pid; ?>" <?php echo $sid === $pid ? 'selected' : ''; ?>><?php echo $h($nm); ?></option><?php endforeach; ?></select></div>
    <div><label for="a">What</label><select id="a" name="a"><option value="">Everything</option><?php foreach ($areas as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo $area === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?></select></div>
    <div><label for="from">From</label><input id="from" type="date" name="from" value="<?php echo $h($from); ?>"></div>
    <div><label for="to">To</label><input id="to" type="date" name="to" value="<?php echo $h($to); ?>"></div>
    <div><button class="ac-btn" type="submit">Filter</button></div>
</form>
<div class="ac-card">
    <?php if (!$rows): ?><div class="text-muted text-center py-3">No activity matches.</div><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <div class="ac-row"><span class="ac-ico"><i class="fas <?php echo $h($icon[$r['area']] ?? 'fa-circle'); ?>"></i></span>
        <div class="ac-m"><strong><?php echo $h($r['actor_name']); ?></strong> · <?php echo $h($areas[$r['area']] ?? $r['area']); ?> · <?php echo $h($r['action']); ?><small><?php echo $h($r['subject']); ?><?php echo $r['detail'] !== null && $r['detail'] !== '' ? ' — ' . $h(mb_strimwidth((string) $r['detail'], 0, 160, '…')) : ''; ?></small></div>
        <div class="ac-t"><?php echo $h(date('D j M Y', strtotime((string) $r['created_at']))); ?><br><?php echo $h(date('g:i a', strtotime((string) $r['created_at']))); ?></div></div>
    <?php endforeach; ?>
    <div class="text-muted small mt-2"><?php echo (int) $total; ?> record<?php echo $total === 1 ? '' : 's'; ?><?php if ($total > $per): ?> · <?php if ($page > 1): ?><a href="?<?php echo $h($qs(['p' => $page - 1])); ?>">Newer</a> <?php endif; ?><?php if ($page * $per < $total): ?><a href="?<?php echo $h($qs(['p' => $page + 1])); ?>">Older</a><?php endif; ?><?php endif; ?></div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
