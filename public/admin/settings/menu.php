<?php
// Admin: drag the sidebar's top-level menu items into the order you want (like WordPress' menu ordering).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/admin_nav.php';

Middleware::requireAdmin();

$settings = new SettingModel($pdo);
$items = admin_nav_items();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Your form session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        $settings->set('admin_nav_order', null);
        $_SESSION['flash']['success'] = 'Sidebar menu reset to the default order.';
    } else {
        $order = array_values(array_intersect((array) ($_POST['order'] ?? []), array_keys($items)));
        $settings->set('admin_nav_order', json_encode($order));
        $_SESSION['flash']['success'] = 'Sidebar menu order saved.';
    }
    header('Location: menu.php');
    exit;
}

$order = admin_nav_order($settings);
$page_title = 'Sidebar Menu Order';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Sidebar Menu Order', 'active' => true]];

ob_start();
?>
<style>
.nav-order { list-style: none; margin: 0; padding: 0; }
.nav-order li { display: flex; align-items: center; gap: 12px; padding: 12px 14px; margin-bottom: 8px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; cursor: grab; user-select: none; transition: box-shadow .15s, border-color .15s; }
.nav-order li:hover { border-color: #86efac; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.nav-order li:active { cursor: grabbing; }
.nav-order .grip { color: #9ca3af; }
.nav-order .ico { width: 34px; height: 34px; border-radius: 8px; background: #f0fdf4; color: #15803d; display: flex; align-items: center; justify-content: center; flex: none; }
.nav-order .pos { margin-left: auto; color: #9ca3af; font-size: 12px; font-weight: 600; }
.nav-order li.is-divider { background: #f9fafb; border-style: dashed; }
.nav-order .sortable-ghost { opacity: .35; background: #dcfce7; }
.nav-order .sortable-chosen { box-shadow: 0 8px 22px rgba(0,0,0,.12); }
.nav-order .mv { border: 0; background: transparent; color: #6b7280; padding: 2px 6px; }
.nav-order .mv:hover { color: #15803d; }
</style>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-bars-staggered me-2"></i>Sidebar menu order</h5>
        <small class="text-muted" id="dirty" style="display:none"><i class="fas fa-circle text-warning me-1" style="font-size:8px"></i>Unsaved changes</small>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Drag items up or down (or use the arrows) to put the menus you use most at the top, then click <strong>Save order</strong>. Items inside dropdowns move together with their dropdown. <em>Logout</em> always stays last.</p>
        <form method="post" id="orderForm">
          <?php echo csrf_field(); ?>
          <ul class="nav-order" id="navOrder">
            <?php foreach ($order as $key): [$label, $icon] = $items[$key]; ?>
              <li data-key="<?php echo $key; ?>" class="<?php echo $key === 'divider' ? 'is-divider' : ''; ?>">
                <i class="fas fa-grip-vertical grip"></i>
                <span class="ico"><i class="fas <?php echo $icon; ?>"></i></span>
                <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
                <span class="pos"></span>
                <span class="d-flex">
                  <button type="button" class="mv" data-dir="-1" title="Move up" aria-label="Move <?php echo htmlspecialchars($label); ?> up"><i class="fas fa-chevron-up"></i></button>
                  <button type="button" class="mv" data-dir="1" title="Move down" aria-label="Move <?php echo htmlspecialchars($label); ?> down"><i class="fas fa-chevron-down"></i></button>
                </span>
                <input type="hidden" name="order[]" value="<?php echo $key; ?>">
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-success" type="submit"><i class="fas fa-save me-1"></i>Save order</button>
            <button class="btn btn-outline-secondary" type="submit" name="action" value="reset" formnovalidate onclick="return confirm('Reset the sidebar to its default order?');"><i class="fas fa-undo me-1"></i>Reset to default</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body small text-muted">
      <h6 class="text-dark"><i class="fas fa-circle-info me-1 text-success"></i>How it works</h6>
      <ul class="ps-3 mb-0">
        <li>The order applies to the admin sidebar for every admin as soon as you save.</li>
        <li>Use the <strong>Divider line</strong> to separate your important menus at the top from the rest below.</li>
        <li>Dropdowns like <em>System</em> and <em>Website Content</em> move as one item with everything inside them.</li>
      </ul>
    </div></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
  const list = document.getElementById('navOrder'), dirty = document.getElementById('dirty');
  const renumber = () => list.querySelectorAll('li').forEach((li, i) => li.querySelector('.pos').textContent = '#' + (i + 1));
  const changed = () => { renumber(); dirty.style.display = ''; };
  renumber();
  if (window.Sortable) Sortable.create(list, {animation: 150, onEnd: changed});
  list.addEventListener('click', e => {
    const b = e.target.closest('.mv'); if (!b) return;
    const li = b.closest('li');
    if (b.dataset.dir === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
    else if (b.dataset.dir === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
    else return;
    changed();
  });
  window.addEventListener('beforeunload', e => { if (dirty.style.display === '') { e.preventDefault(); e.returnValue = ''; } });
  document.getElementById('orderForm').addEventListener('submit', () => { dirty.style.display = 'none'; });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
