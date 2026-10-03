<?php
// Admin: create, rename, recolour and delete blog categories. Categories appear as filter tabs on the public Blog page.
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$page_title = 'Blog Categories';
$breadcrumbs = [['label' => 'Blog', 'url' => 'index.php'], ['label' => 'Categories', 'active' => true]];

$slugify = static function (string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'category';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Your form session expired. Please try again.';
        header('Location: categories.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $pdo->prepare('UPDATE blogs SET category_id = NULL WHERE category_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM blog_categories WHERE id = ?')->execute([$id]);
        $_SESSION['flash']['success'] = 'Category deleted. Its posts are now uncategorised.';
    } elseif ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? '')) ?: null;
        $color = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#16a34a';
        if ($name === '') {
            $_SESSION['flash']['error'] = 'Category name is required.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM blog_categories WHERE (LOWER(name) = LOWER(?) OR slug = ?) AND id <> ?');
            $dup->execute([$name, $slugify($name), $id]);
            if ($dup->fetchColumn()) {
                $_SESSION['flash']['error'] = 'A category called “' . $name . '” already exists.';
            } elseif ($id) {
                $pdo->prepare('UPDATE blog_categories SET name = ?, slug = ?, description = ?, color = ? WHERE id = ?')
                    ->execute([$name, $slugify($name), $desc, $color, $id]);
                $_SESSION['flash']['success'] = 'Category updated.';
            } else {
                $pdo->prepare('INSERT INTO blog_categories (name, slug, description, color, created_by) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$name, $slugify($name), $desc, $color, $_SESSION['user_id'] ?? null]);
                $_SESSION['flash']['success'] = 'Category “' . $name . '” added.';
            }
        }
    }
    header('Location: categories.php');
    exit;
}

$cats = $pdo->query(
    "SELECT c.*, COUNT(b.id) AS posts, SUM(b.status = 'published') AS published
     FROM blog_categories c LEFT JOIN blogs b ON b.category_id = c.id
     GROUP BY c.id ORDER BY c.name"
)->fetchAll(PDO::FETCH_ASSOC);
$uncat = (int) $pdo->query('SELECT COUNT(*) FROM blogs WHERE category_id IS NULL')->fetchColumn();
$editing = null;
foreach ($cats as $c) {
    if ((int) ($_GET['edit'] ?? 0) === (int) $c['id']) $editing = $c;
}
$form = $editing ?: ['id' => '', 'name' => '', 'description' => '', 'color' => '#16a34a'];

ob_start();
?>
<style>
.cat-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; flex: none; }
.form-control-color { width: 56px; }
</style>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><?php echo $editing ? 'Edit category' : 'Add category'; ?></h5></div>
      <div class="card-body">
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?php echo (int) $form['id']; ?>">
          <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" required maxlength="100" placeholder="e.g. Tech Guides" value="<?php echo htmlspecialchars($form['name']); ?>"></div>
          <div class="mb-3"><label class="form-label">Description <small class="text-muted">(optional)</small></label>
            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars((string) $form['description']); ?></textarea></div>
          <div class="mb-3"><label class="form-label">Colour</label>
            <input type="color" class="form-control form-control-color" name="color" value="<?php echo htmlspecialchars($form['color'] ?: '#16a34a'); ?>"></div>
          <button class="btn btn-success" type="submit"><i class="fas fa-save me-1"></i><?php echo $editing ? 'Save changes' : 'Add category'; ?></button>
          <?php if ($editing): ?><a href="categories.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Categories</h5>
        <small class="text-muted">Shown as filter tabs on the Blog page<?php echo $uncat ? ' · ' . $uncat . ' uncategorised post' . ($uncat === 1 ? '' : 's') : ''; ?></small>
      </div>
      <div class="card-body p-0">
        <?php if (!$cats): ?>
          <p class="p-4 mb-0 text-muted">No categories yet. Add your first one on the left.</p>
        <?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
          <thead><tr><th>Category</th><th class="text-center">Posts</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($cats as $c): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="cat-dot" style="background:<?php echo htmlspecialchars($c['color'] ?: '#16a34a'); ?>"></span>
                  <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                  <span class="text-muted small">/<?php echo htmlspecialchars($c['slug']); ?></span>
                </div>
                <?php if ($c['description']): ?><div class="small text-muted mt-1"><?php echo htmlspecialchars($c['description']); ?></div><?php endif; ?>
              </td>
              <td class="text-center"><?php echo (int) $c['posts']; ?><?php if ((int) $c['posts']): ?><div class="small text-muted"><?php echo (int) $c['published']; ?> published</div><?php endif; ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int) $c['id']; ?>" title="Edit"><i class="fas fa-edit"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode('Delete “' . $c['name'] . '”?' . ((int) $c['posts'] ? ' Its ' . (int) $c['posts'] . ' post(s) will become uncategorised.' : '')), ENT_QUOTES); ?>);">
                  <?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
