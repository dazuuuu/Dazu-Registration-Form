<?php
// Admin: Enquiries (READ ONLY). The same list, totals and search as the secretaries see. The admin cannot change anything here. Normal admin ONLY (secretary_admin_require):
// the super admin, secretaries and everyone else get a plain 403. Prospects only: no student record is read or shown.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryEnquiryService.php';

use Models\Connection;

secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$svc = new SecretaryEnquiryService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$q = trim((string) ($_GET['q'] ?? ''));
$f = isset(SecretaryEnquiryService::STATUSES[$_GET['f'] ?? '']) ? (string) $_GET['f'] : '';
$fuToday = ($_GET['fu'] ?? '') === 'today';
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 50;
$notReady = false; $rows = []; $total = 0; $stats = ['pending' => 0, 'follow_today' => 0, 'called_back' => 0, 'registered' => 0];
try { $stats = $svc->stats(); [$rows, $total] = $svc->search($q, $f, $per, ($page - 1) * $per, $fuToday); } catch (Throwable $e) { $notReady = true; }
$statuses = SecretaryEnquiryService::STATUSES; $sources = SecretaryEnquiryService::SOURCES;
$today = SecretaryEnquiryService::today();
$d = static fn(?string $x): string => $x ? date('D j M', strtotime($x)) : '';

$page_title = 'Enquiries';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Enquiries', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.ae { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; }
.ae-stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:16px; }
.ae-stat { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:16px 18px; color:inherit; } .ae-stat small { color:#6b7570; display:block; } .ae-stat b { font-size:1.9rem; display:block; }
.ae-box { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:18px; }
.ae-tools { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; } .ae-tools input, .ae-tools select { min-height:44px; border:1px solid #cbd5d0; border-radius:12px; padding:0 12px; } .ae-tools input { flex:1 1 220px; }
.ae-btn { background:var(--g); color:#fff; border:0; border-radius:12px; min-height:44px; padding:0 18px; font-weight:700; }
.ae-t { width:100%; border-collapse:collapse; } .ae-t th { font-size:.72rem; text-transform:uppercase; color:#6b7570; text-align:left; padding:10px 8px; border-bottom:1px solid #e6efe9; } .ae-t td { padding:12px 8px; border-bottom:1px solid #eef3ef; vertical-align:middle; } .ae-t small { display:block; color:#6b7570; }
.ae-chip { display:inline-block; font-size:.76rem; font-weight:800; padding:4px 12px; border-radius:99px; background:#eceeea; color:#555e59; } .ae-chip.pending { background:#fff4d6; color:#8a6100; } .ae-chip.called_back { background:var(--gl); color:var(--gd); } .ae-chip.registered { background:#e5e9fb; color:#2f3f94; }
.ae-ro { background:var(--gl); border-radius:12px; padding:10px 14px; color:#1d4d33; font-size:.9rem; margin-bottom:14px; }
@media (max-width: 767.98px) { .ae-stats { grid-template-columns:1fr 1fr; } .ae-t, .ae-t tbody, .ae-t tr, .ae-t td { display:block; } .ae-t thead { display:none; } .ae-t tr { border:1px solid #e6efe9; border-radius:14px; padding:10px; margin-bottom:10px; } .ae-t td { border:0; padding:5px 0; display:flex; justify-content:space-between; gap:10px; } .ae-t td::before { content:attr(data-label); font-size:.72rem; font-weight:800; color:#6b7570; text-transform:uppercase; } }
</style>
CSS;
ob_start();
?>
<div class="ae">
<div class="ae-ro"><i class="fas fa-eye me-1"></i> Read only. The secretaries add and update the enquiries; this page only shows them. It lists people who asked about a course, never registered students.</div>
<?php if ($notReady): ?><div class="alert alert-warning">The enquiries tables are not in the database yet. Run the database update (migrations 127 and 128) first.</div><?php else: ?>
<div class="ae-stats">
    <a class="ae-stat" href="enquiries.php?f=pending"><small>Pending</small><b><?php echo (int) $stats['pending']; ?></b></a>
    <a class="ae-stat" href="enquiries.php?fu=today"><small>Follow up today</small><b><?php echo (int) $stats['follow_today']; ?></b></a>
    <a class="ae-stat" href="enquiries.php?f=called_back"><small>Called back</small><b><?php echo (int) $stats['called_back']; ?></b></a>
    <a class="ae-stat" href="enquiries.php?f=registered"><small>Registered</small><b><?php echo (int) $stats['registered']; ?></b></a>
</div>
<div class="ae-box">
    <form class="ae-tools" method="GET" role="search">
        <input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="Search name, phone, email or course" maxlength="100" aria-label="Search enquiries">
        <?php if ($fuToday): ?><input type="hidden" name="fu" value="today"><?php endif; ?>
        <select name="f" aria-label="Status"><option value="">Status: All</option><?php foreach ($statuses as $k => $l): ?><option value="<?php echo $h($k); ?>" <?php echo $f === $k ? 'selected' : ''; ?>><?php echo $h($l); ?></option><?php endforeach; ?></select>
        <button class="ae-btn" type="submit">Search</button>
    </form>
    <?php if (!$rows): ?><div class="text-muted text-center py-4">No enquiries to show.</div><?php else: ?>
    <table class="ae-t"><thead><tr><th>Name and course</th><th>Source</th><th>Date</th><th>Added by</th><th>Follow up</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $fu = (string) ($r['follow_up_date'] ?? ''); ?>
        <tr><td data-label="Enquiry"><a href="enquiry.php?id=<?php echo (int) $r['id']; ?>"><strong><?php echo $h($r['name']); ?></strong></a><small><?php echo $h($r['course']); ?> · <?php echo $h($r['phone']); ?></small></td>
            <td data-label="Source"><?php echo $h($sources[$r['source']] ?? $r['source']); ?></td><td data-label="Date"><?php echo $h($d($r['enquiry_date'])); ?></td>
            <td data-label="Added by"><?php echo $h($r['added_by_name']); ?><?php if ($r['handled_by']): ?><small>taken over by <?php echo $h($r['handled_by_name']); ?></small><?php endif; ?></td>
            <td data-label="Follow up"><?php echo $fu === '' ? '—' : ($fu === $today ? '<strong>Today</strong>' : $h($d($fu))); ?></td>
            <td data-label="Status"><span class="ae-chip <?php echo $h($r['status']); ?>"><?php echo $h($statuses[$r['status']] ?? $r['status']); ?></span></td></tr>
    <?php endforeach; ?></tbody></table>
    <div class="text-muted small mt-2"><?php echo (int) $total; ?> enquir<?php echo $total === 1 ? 'y' : 'ies'; ?><?php if ($total > $per): ?> · <?php if ($page > 1): ?><a href="?<?php echo $h(http_build_query(array_filter(['q' => $q, 'f' => $f, 'fu' => $fuToday ? 'today' : '', 'p' => $page - 1]))); ?>">Newer</a> <?php endif; ?><?php if ($page * $per < $total): ?><a href="?<?php echo $h(http_build_query(array_filter(['q' => $q, 'f' => $f, 'fu' => $fuToday ? 'today' : '', 'p' => $page + 1]))); ?>">Older</a><?php endif; ?><?php endif; ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
