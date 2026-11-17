<?php
// Admin: Blog approvals. The posts and changes secretaries sent, each with Approve and publish, Preview, Compare and Send back (with a short reason).
// For the normal admin ONLY (secretary_admin_require): the super admin, secretaries and everyone else get a plain 403.
// Approving publishes through the existing blog code (BlogController), so the result is the same as if the admin had posted it.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

use Models\Connection;

$adminId = secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$svc = new SecretaryBlogService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $rev = (int) ($_POST['rev'] ?? 0);
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'The security check failed. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'approve') {
        $r = $svc->approve($adminId, $id, $rev);
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? 'Approved and published. The secretary was told.' : $h($r['error'] ?? 'Could not approve.'));
    } elseif (($_POST['action'] ?? '') === 'send_back') {
        $r = $svc->sendBack($adminId, $id, (string) ($_POST['reason'] ?? ''), $rev);
        SessionModel::setFlash(!empty($r['ok']) ? 'success' : 'error', !empty($r['ok']) ? 'Sent back with your reason. The secretary was told.' : $h($r['error'] ?? 'Could not send it back.'));
    }
    header('Location: blog-approvals.php');
    exit();
}

$items = [];
$notReady = false;
try { $items = $svc->waiting(); } catch (Throwable $e) { $notReady = true; }

$page_title = 'Blog approvals';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Blog approvals', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.ba { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; }
.ba-card { background:#fff; border:1px solid #d9e6de; border-radius:16px; padding:18px; margin-bottom:16px; }
.ba-head { display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.ba-head img { width:96px; height:64px; object-fit:cover; border-radius:10px; background:var(--gl); }
.ba-title { font-weight:800; font-size:1.05rem; overflow-wrap:anywhere; } .ba-meta { color:#6b7570; font-size:.85rem; }
.ba-pill { display:inline-block; font-size:.72rem; font-weight:800; padding:3px 10px; border-radius:99px; background:var(--gl); color:var(--gd); margin-right:6px; } .ba-pill.chg { background:#fff4d6; color:#8a6100; }
.ba-act { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; align-items:flex-start; } .ba-act form { margin:0; }
.ba-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:44px; padding:0 16px; border-radius:12px; border:1px solid var(--g); background:#fff; color:var(--gd); font-weight:700; font-size:.9rem; cursor:pointer; text-decoration:none; }
.ba-btn:hover { background:var(--gl); color:var(--gd); } .ba-btn.solid { background:var(--g); color:#fff; } .ba-btn.solid:hover { background:var(--gd); color:#fff; } .ba-btn.warn { border-color:#c8761a; color:#9a5200; }
.ba-back { display:none; margin-top:12px; background:#fff8f0; border:1px solid #f0d9bd; border-radius:12px; padding:12px; } .ba-back.open { display:block; }
.ba-back textarea { width:100%; min-height:80px; border:1px solid #cbd5d0; border-radius:10px; padding:10px; }
.ba-empty { background:#fff; border:1px dashed #cfd8d2; border-radius:16px; padding:36px; text-align:center; color:#6b7570; }
@media (max-width: 767.98px) { .ba-act { display:grid; grid-template-columns:1fr; } .ba-btn { width:100%; min-height:48px; } .ba-head img { width:100%; height:140px; } }
</style>
CSS;
ob_start();
?>
<div class="ba">
<p class="text-muted">Posts and changes your secretaries sent. The public sees nothing until you approve.</p>
<?php if ($notReady): ?>
    <div class="alert alert-warning">The blog approval tables are not in the database yet. Run the database update (migrations 125 and 126) first.</div>
<?php elseif (!$items): ?>
    <div class="ba-empty"><i class="fas fa-check-circle fa-2x mb-2" style="color:#048C44"></i><br><strong>Nothing is waiting.</strong><br>When a secretary sends a post or a change, it will show here.</div>
<?php else: foreach ($items as $s):
    $d = $s['data']; $sid = (int) $s['id']; $isChange = $s['kind'] === 'change'; ?>
    <div class="ba-card" id="item-<?php echo $sid; ?>">
        <div class="ba-head">
            <?php if (!empty($d['featured_image'])): ?><img src="<?php echo $h($d['featured_image']); ?>" alt=""><?php endif; ?>
            <div style="min-width:0;flex:1">
                <div><span class="ba-pill <?php echo $isChange ? 'chg' : ''; ?>"><?php echo $isChange ? 'Change to a live blog' : 'New post'; ?></span></div>
                <div class="ba-title"><?php echo $h($s['title']); ?></div>
                <div class="ba-meta">From <?php echo $h($s['by_name']); ?> · sent <?php echo $h(date('j M Y, g:i a', strtotime((string) $s['updated_at']))); ?></div>
                <?php if (!empty($d['excerpt'])): ?><div class="ba-meta mt-1"><?php echo $h(mb_strimwidth((string) $d['excerpt'], 0, 200, '…')); ?></div><?php endif; ?>
            </div>
        </div>
        <div class="ba-act">
            <form method="POST" onsubmit="return confirm('Approve and publish this? It goes live for the public.');">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?php echo $sid; ?>"><input type="hidden" name="rev" value="<?php echo (int) $s['rev']; ?>">
                <button type="submit" class="ba-btn solid"><i class="fas fa-check"></i> Approve and publish</button>
            </form>
            <a class="ba-btn" href="blog-preview.php?id=<?php echo $sid; ?>"><i class="fas fa-eye"></i> Preview</a>
            <a class="ba-btn" href="blog-compare.php?id=<?php echo $sid; ?>"><i class="fas fa-code-compare"></i> Compare</a>
            <button type="button" class="ba-btn warn" data-back="<?php echo $sid; ?>"><i class="fas fa-rotate-left"></i> Send back</button>
        </div>
        <form method="POST" class="ba-back" id="back-<?php echo $sid; ?>">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="send_back"><input type="hidden" name="id" value="<?php echo $sid; ?>"><input type="hidden" name="rev" value="<?php echo (int) $s['rev']; ?>">
            <label class="form-label fw-bold" for="reason-<?php echo $sid; ?>">Why are you sending it back? (a short reason, she will read it)</label>
            <textarea id="reason-<?php echo $sid; ?>" name="reason" maxlength="300" required placeholder="For example: please add a cover that shows students, and fix the date in the second paragraph."></textarea>
            <div class="mt-2"><button type="submit" class="ba-btn warn"><i class="fas fa-paper-plane"></i> Send back</button></div>
        </form>
    </div>
<?php endforeach; endif; ?>
</div>
<script>document.addEventListener('click', function (e) { var b = e.target.closest('[data-back]'); if (!b) return; var f = document.getElementById('back-' + b.dataset.back); f.classList.toggle('open'); if (f.classList.contains('open')) f.querySelector('textarea').focus(); });</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
