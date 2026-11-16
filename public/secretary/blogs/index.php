<?php
// Blogs (secretary portal): every blog with its status and date, filters, Edit, Unpublish and Publish again, and New blog. There is NO delete: only the admin deletes.
// The server checks on every request that she is an active secretary (On leave is fine) with the Blogs switch on (secretary_require).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

$sec = secretary_require('blogs');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryBlogService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string) ($_POST['action'] ?? '');
    if ($act === 'delete' || isset($_POST['delete'])) {   // a secretary can never delete a blog
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Not allowed. Only the admin can delete a blog.');
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'The security check failed. Please try again.');
    } elseif ($act === 'unpublish' || $act === 'publish') {
        $r = $svc->setPublished($sec, (int) ($_POST['blog_id'] ?? 0), $act === 'publish');
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? ($act === 'publish' ? 'Published again.' : 'Unpublished. The public no longer sees it.') : $h($r['error'] ?? 'Could not change it.'));
    }
    header('Location: index.php' . (isset(SecretaryBlogService::FILTERS[$_POST['f'] ?? '']) && $_POST['f'] !== 'all' ? '?f=' . $_POST['f'] : ''));
    exit();
}

[$rows, $counts, $filter, $sentBack] = $svc->listing((string) ($_GET['f'] ?? 'all'));
$needs = SecretaryBlogService::approvalNeeded($db, (int) $sec['id']);
$statusText = ['published' => 'Published', 'unpublished' => 'Unpublished', 'waiting' => 'Waiting for approval', 'sent_back' => 'Sent back'];
$date = static fn(string $d): string => $d !== '' && strtotime($d) ? date('j M Y', strtotime($d)) : '';

$page_title = 'Blogs';
$sp_active = 'blogs';
$sp_subtitle = 'Secretary portal';
ob_start();
?>
<style>
.sb-top { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.sb-filters { display:flex; gap:8px; flex-wrap:wrap; }
.sb-filters a { display:inline-flex; align-items:center; gap:8px; min-height:44px; padding:0 16px; border-radius:99px; background:#fff; border:1px solid var(--line); color:#2b332f; font-weight:700; font-size:.88rem; }
.sb-filters a.on { background:var(--g); border-color:var(--g); color:#fff; } .sb-filters a b { background:rgba(0,0,0,.08); border-radius:99px; padding:0 8px; font-size:.75rem; } .sb-filters a.on b { background:rgba(255,255,255,.25); }
.sb-new { background:var(--g); color:#fff; border-radius:12px; min-height:46px; padding:0 20px; display:inline-flex; align-items:center; gap:8px; font-weight:800; } .sb-new:hover { background:var(--gd); color:#fff; }
.sb-note { background:var(--gl); border:1px solid #cfe6d8; border-radius:14px; padding:12px 16px; margin-bottom:14px; color:#1d4d33; font-size:.9rem; }
.sb-warn { background:#fff4d6; border-color:#f0dc9d; color:#6f4f00; }
.sb-table { width:100%; background:#fff; border-radius:20px; border-collapse:separate; border-spacing:0; overflow:hidden; }
.sb-table th { background:var(--gl); color:var(--gd); font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; padding:12px 14px; text-align:left; }
.sb-table td { padding:14px; border-top:1px solid var(--line); vertical-align:middle; }
.sb-title { display:flex; align-items:center; gap:12px; min-width:0; } .sb-title img { width:64px; height:44px; object-fit:cover; border-radius:8px; background:var(--gl); flex:0 0 auto; }
.sb-title b { display:block; font-weight:700; overflow-wrap:anywhere; } .sb-title small { color:var(--mut); }
.sb-badge { display:inline-block; font-size:.74rem; font-weight:800; padding:4px 11px; border-radius:99px; white-space:nowrap; background:#eceeea; color:#4a524e; }
.sb-badge.published { background:var(--gl); color:var(--gd); } .sb-badge.waiting { background:#fff4d6; color:#8a6100; } .sb-badge.sent_back { background:#fde2e0; color:#b42318; }
.sb-reason { margin-top:6px; font-size:.82rem; color:#8a2a22; background:#fff3f1; border-radius:10px; padding:6px 10px; overflow-wrap:anywhere; }
.sb-act { display:flex; gap:8px; flex-wrap:wrap; } .sb-act form { margin:0; }
.sb-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:44px; padding:0 16px; border-radius:12px; border:1px solid var(--g); background:#fff; color:var(--gd); font-weight:700; font-size:.88rem; cursor:pointer; }
.sb-btn:hover { background:var(--gl); } .sb-btn.solid { background:var(--g); color:#fff; } .sb-btn.warn { border-color:#c8761a; color:#9a5200; }
.sb-empty { background:#fff; border-radius:20px; padding:36px 20px; text-align:center; color:var(--mut); } .sb-empty i { font-size:2rem; color:#b9c7be; display:block; margin-bottom:8px; }
@media (max-width: 991.98px) {
  .sb-table, .sb-table tbody, .sb-table tr, .sb-table td { display:block; } .sb-table { background:transparent; } .sb-table thead { display:none; }
  .sb-table tr { background:#fff; border-radius:20px; padding:14px; margin-bottom:14px; }
  .sb-table td { border:0; padding:8px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; }
  .sb-table td::before { content:attr(data-label); font-size:.72rem; font-weight:800; color:var(--mut); text-transform:uppercase; letter-spacing:.04em; flex:0 0 30%; }
  .sb-table td.sb-first { display:block; } .sb-table td.sb-first::before, .sb-table td.sb-actions::before { display:none; } .sb-table td.sb-actions { display:block; padding-top:10px; }
  .sb-act { display:grid; grid-template-columns:1fr 1fr; } .sb-btn { width:100%; min-height:48px; } .sb-filters a { min-height:46px; }
  .sb-new { width:100%; justify-content:center; min-height:50px; }
}
</style>
<div class="sb-top">
    <div class="sb-filters" role="tablist" aria-label="Filter blogs">
        <?php foreach (SecretaryBlogService::FILTERS as $k => $label): ?>
            <a href="index.php<?php echo $k === 'all' ? '' : '?f=' . $h($k); ?>" class="<?php echo $filter === $k ? 'on' : ''; ?>"><?php echo $h($label); ?> <b><?php echo (int) $counts[$k]; ?></b></a>
        <?php endforeach; ?>
    </div>
    <a class="sb-new" href="edit.php"><i class="fas fa-plus"></i> New blog</a>
</div>
<?php if ($needs): ?><div class="sb-note"><i class="fas fa-circle-info me-1"></i> Your new posts and your changes wait for the admin to approve them. The public keeps seeing the last approved version.</div><?php endif; ?>
<?php if ($sentBack): ?><div class="sb-note sb-warn"><i class="fas fa-rotate-left me-1"></i> <?php echo (int) $sentBack; ?> of your posts <?php echo $sentBack === 1 ? 'was' : 'were'; ?> sent back. Open <strong>Edit</strong> to see the reason, fix it and send it again.</div><?php endif; ?>

<?php if (!$rows): ?>
    <div class="sb-empty"><i class="fas fa-newspaper"></i><b>No blogs here</b><?php echo $filter === 'all' ? 'Tap New blog to write the first one.' : 'Nothing matches this filter.'; ?></div>
<?php else: ?>
<table class="sb-table">
    <thead><tr><th>Blog</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $editUrl = $r['blog_id'] ? 'edit.php?id=' . (int) $r['blog_id'] : 'edit.php?sub=' . (int) $r['sub_id'];
        $pend = $r['pending']; ?>
    <tr>
        <td class="sb-first" data-label="Blog">
            <div class="sb-title">
                <?php if ($r['cover'] !== ''): ?><img src="<?php echo $h($r['cover']); ?>" alt=""><?php endif; ?>
                <div style="min-width:0"><b><?php echo $h($r['title']); ?></b><small>by <?php echo $h($r['author']); ?></small></div>
            </div>
        </td>
        <td data-label="Status">
            <div>
                <span class="sb-badge <?php echo $h($r['status']); ?>"><?php echo $h($statusText[$r['status']]); ?></span>
                <?php if ($pend === 'waiting'): ?><div style="margin-top:6px"><span class="sb-badge waiting">Change waiting for approval</span></div><?php endif; ?>
                <?php if ($pend === 'sent_back'): ?><div style="margin-top:6px"><span class="sb-badge sent_back">Change sent back</span></div><?php endif; ?>
                <?php if (($r['status'] === 'sent_back' || $pend === 'sent_back') && $r['reason'] !== ''): ?><div class="sb-reason"><strong>Reason:</strong> <?php echo $h($r['reason']); ?></div><?php endif; ?>
            </div>
        </td>
        <td data-label="Date"><?php echo $h($r['date_label'] . ' ' . $date($r['date'])); ?></td>
        <td class="sb-actions" data-label="Actions">
            <div class="sb-act">
                <a class="sb-btn solid" href="<?php echo $h($editUrl); ?>"><i class="fas fa-pen"></i> Edit</a>
                <?php if ($r['blog_id'] && $r['status'] === 'published'): ?>
                <form method="POST" onsubmit="return confirm('Unpublish this blog? The public will no longer see it.');">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="unpublish"><input type="hidden" name="blog_id" value="<?php echo (int) $r['blog_id']; ?>"><input type="hidden" name="f" value="<?php echo $h($filter); ?>">
                    <button type="submit" class="sb-btn warn"><i class="fas fa-eye-slash"></i> Unpublish</button>
                </form>
                <?php elseif ($r['blog_id'] && $r['status'] === 'unpublished' && (!$needs || !empty($r['was_published']))): ?>
                <form method="POST">
                    <?php echo csrf_field(); ?><input type="hidden" name="action" value="publish"><input type="hidden" name="blog_id" value="<?php echo (int) $r['blog_id']; ?>"><input type="hidden" name="f" value="<?php echo $h($filter); ?>">
                    <button type="submit" class="sb-btn"><i class="fas fa-eye"></i> Publish again</button>
                </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
