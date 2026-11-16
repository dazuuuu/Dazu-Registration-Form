<?php
// Admin: the Room and Visitors diary, READ ONLY (the same entries the secretaries see, with who added each). Normal admin ONLY: the super admin, secretaries and everyone else get a plain 403.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryDiaryService.php';

use Models\Connection;

secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$svc = new SecretaryDiaryService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$filter = isset(SecretaryDiaryService::FILTERS[$_GET['f'] ?? '']) ? (string) $_GET['f'] : 'today';
$notReady = false; $rows = [];
try { $rows = $svc->rows($filter, 0); } catch (Throwable $e) { $notReady = true; }
$dayLabel = static fn(string $d): string => $d === SecretaryDiaryService::today() ? 'Today' : date('D j M', strtotime($d));

$page_title = 'Room & Visitors diary';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Diary', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.dr-chip { font-size:.75rem; font-weight:800; padding:4px 12px; border-radius:99px; background:#eceeea; color:#4a524e; } .dr-chip.expected { background:#fff4d6; color:#8a6100; } .dr-chip.arrived { background:#E3F3EA; color:#037A3B; } .dr-chip.cancelled { background:#fde2e0; color:#b42318; }
.dr-filters { display:flex; gap:8px; overflow-x:auto; margin-bottom:14px; } .dr-filters a { flex:0 0 auto; min-height:44px; display:inline-flex; align-items:center; padding:0 18px; border-radius:99px; background:#eceeea; color:#2b332f; font-weight:700; text-decoration:none; } .dr-filters a.on { background:#E3F3EA; color:#037A3B; }
.dr-day { font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#6b7570; padding:14px 0 4px; }
.dr-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #eef3ef; } .dr-item .m { flex:1; min-width:0; overflow-wrap:anywhere; } .dr-item small { color:#6b7570; display:block; }
</style>
CSS;
ob_start();
?>
<div class="alert alert-light border"><i class="fas fa-eye me-1"></i> Read only. The secretaries keep this diary. It lists room bookings, visitors, and bookings that come from the Staff Calendar.</div>
<?php if ($notReady): ?><div class="alert alert-warning">The diary tables are not in the database yet. Run the database update (migrations 129 to 132) first.</div><?php else: ?>
<div class="dr-filters"><?php foreach (SecretaryDiaryService::FILTERS as $k => $l): ?><a href="diary.php?f=<?php echo $h($k); ?>" class="<?php echo $filter === $k ? 'on' : ''; ?>"><?php echo $h($l); ?></a><?php endforeach; ?></div>
<div class="card"><div class="card-body">
<?php if (!$rows): ?><div class="text-muted text-center py-3">Nothing here.</div><?php endif; ?>
<?php $last = ''; foreach ($rows as $r): if ($r['date'] !== $last): $last = $r['date']; ?><div class="dr-day"><?php echo $h($dayLabel($r['date'])); ?></div><?php endif; ?>
    <div class="dr-item"><div class="m"><strong><?php echo $h($r['title']); ?></strong><small><?php echo $h($r['sub']); ?><?php echo $r['by'] !== '' ? ' · added by ' . $h($r['by']) : ''; ?></small></div><span class="dr-chip <?php echo $h($r['status']); ?>"><?php echo $h($r['label']); ?></span></div>
<?php endforeach; ?>
</div></div>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
