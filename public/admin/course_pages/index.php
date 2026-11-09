<?php
// Admin → Courses → Course pages: the courses that have a landing page of their own (dazu.ac.ke/<slug>), with the link to copy
// for ads, views, and "Create a page" (pick a course; the page is written from the course and can then be edited).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CoursePageService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$me = (int) ($_SESSION['user_id'] ?? 0);
$self = url('admin/course_pages/index.php');
$installed = cp_installed($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $installed) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $r = cp_create($db, (int) ($_POST['course_id'] ?? 0), $me);
        if ($r['ok']) { $_SESSION['success_msg'] = 'Page created from the course. Check it, change anything you like, then share the link.'; header('Location: ' . url('admin/course_pages/edit.php?id=' . $r['id'])); exit; }
        $_SESSION['error_msg'] = $r['error'];
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $db->prepare('DELETE FROM course_pages WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        $_SESSION['success_msg'] = 'Page deleted. Its link now shows "Page not found".';
    }
    header('Location: ' . $self);
    exit;
}

$pages = $installed ? $db->query('SELECT p.*, c.title AS course_title, c.cover_image FROM course_pages p JOIN courses c ON c.id = p.course_id ORDER BY p.updated_at DESC')->fetchAll(PDO::FETCH_ASSOC) : [];
$hasAppr = (bool) $db->query("SHOW COLUMNS FROM courses LIKE 'approval_status'")->fetch();
$free = $installed ? $db->query("SELECT id, title FROM courses WHERE is_active = 1" . ($hasAppr ? " AND (approval_status IS NULL OR approval_status = 'approved')" : '') . " AND id NOT IN (SELECT course_id FROM course_pages) ORDER BY title")->fetchAll(PDO::FETCH_ASSOC) : [];
$page_title = 'Course pages';
ob_start();
?>
<div class="container-fluid py-3">
  <?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $k => $c): if (!empty($_SESSION[$k])): ?><div class="alert alert-<?php echo $c; ?>"><?php echo $e($_SESSION[$k]); unset($_SESSION[$k]); ?></div><?php endif; endforeach; ?>
  <?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version (migration 101) to use course pages.</div><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div><h3 class="mb-1"><i class="fas fa-file-lines text-success me-2"></i>Course pages</h3>
      <div class="small text-muted">A page of its own for a course, at <code><?php echo $e(preg_replace('#^https?://#', '', course_share_absolute_url(url('')))); ?>course-name</code>. Not shown in the menu: share the link in ads. Shared links show the course cover, title and description.</div></div>
    <?php if ($installed): ?>
    <form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
      <select name="course_id" class="form-select form-select-sm" required style="min-width:240px"><option value="">Choose a course…</option>
        <?php foreach ($free as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo $e($c['title']); ?></option><?php endforeach; ?></select>
      <button class="btn btn-success btn-sm text-nowrap" <?php echo $free ? '' : 'disabled'; ?>><i class="fas fa-wand-magic-sparkles me-1"></i>Create page</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th class="ps-3">Course</th><th>Link (for ads)</th><th>State</th><th>Views</th><th class="text-end pe-3"></th></tr></thead>
    <tbody>
    <?php if (!$pages): ?><tr><td colspan="5" class="text-center text-muted py-5">No course pages yet. Choose a course above and press Create page.</td></tr><?php endif; ?>
    <?php foreach ($pages as $p): $link = cp_url($p); ?>
      <tr><td class="ps-3"><div class="fw-semibold"><?php echo $e($p['course_title']); ?></div><div class="small text-muted"><?php echo $e($p['meta_title']); ?></div></td>
        <td><div class="input-group input-group-sm" style="max-width:380px"><input class="form-control" value="<?php echo $e($link); ?>" readonly onclick="this.select()">
          <button type="button" class="btn btn-outline-success" onclick="navigator.clipboard.writeText(<?php echo $e(json_encode($link)); ?>);this.innerHTML='Copied';setTimeout(()=>this.innerHTML='<i class=&quot;fas fa-copy&quot;></i> Copy',1500)"><i class="fas fa-copy"></i> Copy</button></div></td>
        <td><span class="badge <?php echo $p['is_published'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $p['is_published'] ? 'Live' : 'Hidden'; ?></span></td>
        <td><?php echo (int) $p['views']; ?></td>
        <td class="text-end pe-3 text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo $e($link); ?>" title="Open the page"><i class="fas fa-up-right-from-square"></i></a>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo url('admin/course_pages/edit.php?id=' . (int) $p['id']); ?>" title="Edit"><i class="fas fa-pen"></i> Edit</a>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete this page? Its link will stop working.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>"><button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button></form>
        </td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
