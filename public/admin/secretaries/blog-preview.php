<?php
// Admin: preview a waiting post or change as the public would see it. Normal admin only. The secretary's text is shown inside a sandboxed frame (no scripts run, no access to this site).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

use Models\Connection;

secretary_admin_require();
$pdo = Connection::getInstance()->getConnection();
$svc = new SecretaryBlogService($pdo);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$s = $svc->submission((int) ($_GET['id'] ?? 0));
if (!$s) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('That item was not found.'); }
$d = $s['data'];
$cat = '';
if (!empty($d['category_id'])) { $q = $pdo->prepare('SELECT name FROM blog_categories WHERE id = ?'); $q->execute([(int) $d['category_id']]); $cat = (string) $q->fetchColumn(); }

$doc = '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base target="_blank">'
     . '<style>body{font-family:Georgia,serif;max-width:760px;margin:0 auto;padding:18px;color:#1b1f1d;line-height:1.7}img,iframe{max-width:100%}h1{font-family:Arial,sans-serif;line-height:1.25}.m{font-family:Arial,sans-serif;color:#6b7570;font-size:.9rem}.cv{width:100%;border-radius:12px;margin:12px 0}</style>'
     . '<h1>' . $h($d['title'] ?? '') . '</h1><div class="m">' . $h($cat) . ($cat !== '' ? ' · ' : '') . 'by ' . $h($s['by_name']) . '</div>'
     . (!empty($d['featured_image']) ? '<img class="cv" src="' . $h($d['featured_image']) . '" alt="">' : '')
     . (!empty($d['excerpt']) ? '<p><em>' . $h($d['excerpt']) . '</em></p>' : '')
     . '<div>' . ($d['content'] ?? '') . '</div>';

$page_title = 'Preview';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Blog approvals', 'url' => 'blog-approvals.php'], ['label' => 'Preview', 'active' => true]];
ob_start();
?>
<div class="d-flex gap-2 flex-wrap mb-3">
    <a class="btn btn-outline-success" href="blog-approvals.php"><i class="fas fa-arrow-left me-1"></i> Back to approvals</a>
    <?php if ($s['kind'] === 'change'): ?><a class="btn btn-outline-success" href="blog-compare.php?id=<?php echo (int) $s['id']; ?>"><i class="fas fa-code-compare me-1"></i> Compare with the live blog</a><?php endif; ?>
</div>
<div class="alert alert-light border small">This is how it will look after you approve it. Status: <strong><?php echo $h($s['status']); ?></strong>. Pictures and videos load from the web.</div>
<iframe title="Preview" sandbox="" srcdoc="<?php echo $h($doc); ?>" style="width:100%;height:75vh;border:1px solid #d9e6de;border-radius:14px;background:#fff"></iframe>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
