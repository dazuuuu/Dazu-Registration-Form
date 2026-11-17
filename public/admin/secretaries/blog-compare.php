<?php
// Admin: Compare. What a secretary's change would alter in the live blog: the short fields, the cover, and the text line by line (red = removed, green = added). Normal admin only.
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
$cmp = $svc->compare($s);

$page_title = 'Compare';
$breadcrumbs = [['label' => 'Secretaries', 'url' => 'index.php'], ['label' => 'Blog approvals', 'url' => 'blog-approvals.php'], ['label' => 'Compare', 'active' => true]];
$extra_css = <<<'CSS'
<style>
.cp-t { width:100%; background:#fff; border-radius:14px; overflow:hidden; border-collapse:separate; border-spacing:0; border:1px solid #d9e6de; }
.cp-t th { background:#E3F3EA; color:#037A3B; font-size:.74rem; text-transform:uppercase; padding:10px 12px; text-align:left; } .cp-t td { padding:10px 12px; border-top:1px solid #e6efe9; vertical-align:top; overflow-wrap:anywhere; }
.cp-old { background:#fdecea; color:#8a2a22; } .cp-new { background:#e6f6ec; color:#14532d; }
.cp-line { padding:6px 12px; border-bottom:1px solid #eef2ee; overflow-wrap:anywhere; } .cp-line.del { background:#fdecea; color:#8a2a22; text-decoration:line-through; } .cp-line.add { background:#e6f6ec; color:#14532d; } .cp-line.same { color:#6b7570; }
.cp-box { background:#fff; border:1px solid #d9e6de; border-radius:14px; margin-top:16px; overflow:hidden; } .cp-box h3 { font-size:1rem; font-weight:800; margin:0; padding:12px 14px; background:#f6faf7; border-bottom:1px solid #d9e6de; }
.cp-img { max-width:100%; width:260px; border-radius:10px; }
@media (max-width: 767.98px) { .cp-t, .cp-t tbody, .cp-t tr, .cp-t td { display:block; } .cp-t thead { display:none; } .cp-t td { border-top:0; } .cp-t tr { border-bottom:8px solid #f4f5f2; } }
</style>
CSS;
ob_start();
?>
<div class="d-flex gap-2 flex-wrap mb-3">
    <a class="btn btn-outline-success" href="blog-approvals.php"><i class="fas fa-arrow-left me-1"></i> Back to approvals</a>
    <a class="btn btn-outline-success" href="blog-preview.php?id=<?php echo (int) $s['id']; ?>"><i class="fas fa-eye me-1"></i> Preview</a>
</div>
<h2 class="h5 fw-bold"><?php echo $h($s['title']); ?> <small class="text-muted fw-normal">· from <?php echo $h($s['by_name']); ?></small></h2>
<?php if ($s['kind'] !== 'change' || !$cmp['blog']): ?>
    <div class="alert alert-info"><?php echo $s['kind'] === 'change' ? 'The blog this change was for no longer exists.' : 'This is a new post, so there is nothing to compare it with. Use Preview to read it.'; ?></div>
<?php else: ?>
    <?php if ($cmp['live_changed']): ?><div class="alert alert-warning"><i class="fas fa-triangle-exclamation me-1"></i> The live blog was changed after this change was started. Approving replaces those newer edits with the secretary's version.</div><?php endif; ?>
    <?php if (!$cmp['fields'] && !array_filter($cmp['lines'], fn($l) => $l[0] !== '=')): ?><div class="alert alert-light border">Nothing is different from the live blog.</div><?php endif; ?>
    <?php if ($cmp['fields']): ?>
    <table class="cp-t"><thead><tr><th style="width:18%">Field</th><th>Live now</th><th>After approval</th></tr></thead><tbody>
    <?php foreach ($cmp['fields'] as $name => [$o, $n]): ?>
        <tr><td><strong><?php echo $h($name); ?></strong></td>
            <td class="cp-old"><?php echo $name === 'Cover image' ? ($o !== '' ? '<img class="cp-img" src="' . $h($o) . '" alt="">' : '(none)') : ($o !== '' ? $h($o) : '(empty)'); ?></td>
            <td class="cp-new"><?php echo $name === 'Cover image' ? ($n !== '' ? '<img class="cp-img" src="' . $h($n) . '" alt="">' : '(none)') : ($n !== '' ? $h($n) : '(empty)'); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
    <?php if (array_filter($cmp['lines'], fn($l) => $l[0] !== '=')): ?>
    <div class="cp-box"><h3>Text <span class="text-muted fw-normal small">· red = removed, green = added</span></h3>
        <?php foreach ($cmp['lines'] as [$op, $text]): ?><div class="cp-line <?php echo $op === '-' ? 'del' : ($op === '+' ? 'add' : 'same'); ?>"><?php echo $op === '-' ? '− ' : ($op === '+' ? '+ ' : ''); echo $h($text); ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
