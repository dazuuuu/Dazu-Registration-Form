<?php
// public/admin/settings/index.php — branding, hero slides, page headers overview.

require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$settings = new SettingModel($pdo);
$logoUrl = settings_asset_url($settings->get('logo_path'));
$siteName = $settings->get('site_name', 'Dazu Institute');
$heroSlides = $settings->getHeroSlides(false);
$pageHeaders = $settings->getAllPageHeaders();

$page_title = 'Site Settings';
$breadcrumbs = [
    ['label' => 'Settings', 'active' => true],
];

ob_start();
?>
<div class="mb-4">
  <p class="text-muted mb-0">Manage your logo, homepage hero images, page banners, contact details, and receipt layout.</p>
</div>

<div class="row g-4">
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-3">
        <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
          <i class="fas fa-image"></i>
        </span>
        <div>
          <h5 class="mb-0">Branding</h5>
          <small class="text-muted">Logo &amp; site name</small>
        </div>
      </div>
      <div class="card-body">
        <div class="border rounded bg-light d-flex align-items-center justify-content-center mb-3" style="height:96px;">
          <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Current logo" class="img-fluid" style="max-height:72px;max-width:85%;object-fit:contain;">
          <?php else: ?>
            <span class="text-muted small">No logo uploaded yet</span>
          <?php endif; ?>
        </div>
        <p class="small text-muted mb-0">Site name: <strong><?php echo htmlspecialchars($siteName); ?></strong></p>
      </div>
      <div class="card-footer bg-white border-top-0 pt-0">
        <a href="<?php echo url('admin/settings/edit.php?section=logo'); ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-pen me-1"></i> Edit branding
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-3">
        <span class="rounded-circle bg-info bg-opacity-10 text-info d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
          <i class="fas fa-images"></i>
        </span>
        <div>
          <h5 class="mb-0">Homepage Hero</h5>
          <small class="text-muted"><?php echo count($heroSlides); ?> slide<?php echo count($heroSlides) === 1 ? '' : 's'; ?></small>
        </div>
      </div>
      <div class="card-body">
        <?php if ($heroSlides): ?>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach (array_slice($heroSlides, 0, 6) as $s): ?>
              <img src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" alt="" class="rounded border" style="width:72px;height:48px;object-fit:cover;">
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0">No slides yet — the homepage will use built-in fallback images.</p>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-white border-top-0 pt-0">
        <a href="<?php echo url('admin/settings/edit.php?section=hero'); ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-sliders me-1"></i> Manage slides
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-3">
        <span class="rounded-circle bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
          <i class="fas fa-heading"></i>
        </span>
        <div>
          <h5 class="mb-0">Page Banners</h5>
          <small class="text-muted"><?php echo count($pageHeaders); ?> page<?php echo count($pageHeaders) === 1 ? '' : 's'; ?></small>
        </div>
      </div>
      <div class="card-body p-0">
        <?php if ($pageHeaders): ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($pageHeaders as $h): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <span class="text-capitalize fw-semibold"><?php echo htmlspecialchars($h['page_key']); ?></span>
                <?php if (!empty($h['image_path'])): ?>
                  <span class="badge bg-success">Image set</span>
                <?php else: ?>
                  <span class="badge bg-secondary">No image</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="card-body"><p class="text-muted small mb-0">No page banners configured yet.</p></div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-white border-top-0 pt-0">
        <a href="<?php echo url('admin/settings/edit.php?section=headers'); ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-pen me-1"></i> Edit banners
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-3">
        <span class="rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
          <i class="fas fa-map-location-dot"></i>
        </span>
        <div>
          <h5 class="mb-0">Contact &amp; Map</h5>
          <small class="text-muted">Phone, email, campus map</small>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-0">Configure the contact page directory and Google Maps embed shown to visitors.</p>
      </div>
      <div class="card-footer bg-white border-top-0 pt-0">
        <a href="<?php echo url('admin/settings/edit.php?section=contact'); ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-pen me-1"></i> Edit contact
        </a>
      </div>
    </div>
  </div>

  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-3">
        <span class="rounded-circle bg-dark bg-opacity-10 text-dark d-inline-flex align-items-center justify-content-center" style="width:40px;height:40px;">
          <i class="fas fa-receipt"></i>
        </span>
        <div>
          <h5 class="mb-0">SmS Receipts</h5>
          <small class="text-muted">PDF receipt layout</small>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-0">Customize logo, address, and footer for black &amp; white SmS payment receipts.</p>
      </div>
      <div class="card-footer bg-white border-top-0 pt-0">
        <a href="<?php echo url('admin/settings/edit.php?section=receipt'); ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-pen me-1"></i> Edit receipts
        </a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
