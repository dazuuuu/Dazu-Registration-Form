<?php
// public/admin/settings/edit.php — uploads + DB writes for site settings sections.

require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$settings = new SettingModel($pdo);

$PUBLIC = defined('PUBLIC_ROOT') ? PUBLIC_ROOT : realpath(__DIR__ . '/../..');
$DIRS = [
    'logo'    => ['abs' => $PUBLIC . '/assets/images/branding', 'web' => 'assets/images/branding'],
    'hero'    => ['abs' => $PUBLIC . '/uploads/hero',           'web' => 'uploads/hero'],
    'headers' => ['abs' => $PUBLIC . '/uploads/headers',        'web' => 'uploads/headers'],
    'receipt' => ['abs' => $PUBLIC . '/uploads/receipts',       'web' => 'uploads/receipts'],
];

$section = $_GET['section'] ?? 'logo';
if (!in_array($section, ['logo', 'hero', 'headers', 'contact', 'receipt'], true)) {
    $section = 'logo';
}

function settings_safe_unlink(?string $relPath, string $publicRoot): void
{
    if (!$relPath) {
        return;
    }
    if (preg_match('#^(https?:)?//#', $relPath)) {
        return;
    }
    $abs = realpath($publicRoot . '/' . ltrim($relPath, '/'));
    if ($abs && str_starts_with($abs, $publicRoot) && is_file($abs)) {
        @unlink($abs);
    }
}

function settings_redirect_back(string $section, string $type, string $msg): void
{
    SessionModel::setFlash($type === 'success' ? 'success' : 'error', $msg);
    header('Location: ' . url('admin/settings/edit.php?section=' . urlencode($section)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        settings_redirect_back($section, 'error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_logo') {
        $settings->set('site_name', trim($_POST['site_name'] ?? '') ?: 'Dazu Hub Institute');
        $settings->set('logo_alt', trim($_POST['logo_alt'] ?? '') ?: 'Dazu Hub Institute');

        if (!empty($_FILES['logo']['name'])) {
            $res = upload_image(
                $_FILES['logo'],
                $DIRS['logo']['abs'],
                $DIRS['logo']['web'],
                ['png', 'jpg', 'jpeg', 'webp']
            );
            if (!$res['ok']) {
                settings_redirect_back('logo', 'error', $res['error']);
            }
            settings_safe_unlink($settings->get('logo_path'), $PUBLIC);
            $settings->set('logo_path', $res['path']);
        }
        settings_redirect_back('logo', 'success', 'Branding saved.');
    }

    if ($action === 'add_hero') {
        if (empty($_FILES['image']['name'])) {
            settings_redirect_back('hero', 'error', 'Please choose an image to add.');
        }
        $res = upload_image($_FILES['image'], $DIRS['hero']['abs'], $DIRS['hero']['web']);
        if (!$res['ok']) {
            settings_redirect_back('hero', 'error', $res['error']);
        }
        $settings->addHeroSlide(
            $res['path'],
            trim($_POST['caption'] ?? '') ?: null,
            (int) ($_POST['sort_order'] ?? 0)
        );
        settings_redirect_back('hero', 'success', 'Slide added.');
    }

    if ($action === 'update_hero') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $settings->updateHeroSlide($id, [
                'caption'    => trim($_POST['caption'] ?? '') ?: null,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            ]);
        }
        settings_redirect_back('hero', 'success', 'Slide updated.');
    }

    if ($action === 'delete_hero') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $removedPath = $settings->deleteHeroSlide($id);
            settings_safe_unlink($removedPath, $PUBLIC);
        }
        settings_redirect_back('hero', 'success', 'Slide removed.');
    }

    if ($action === 'save_header') {
        $pageKey = trim($_POST['page_key'] ?? '');
        if ($pageKey === '' || !preg_match('/^[a-z0-9_-]+$/', $pageKey)) {
            settings_redirect_back('headers', 'error', 'Invalid page key (use lowercase letters, numbers, - or _).');
        }
        $fields = [
            'title'    => trim($_POST['title'] ?? '') ?: null,
            'subtitle' => trim($_POST['subtitle'] ?? '') ?: null,
        ];
        if (!empty($_FILES['image']['name'])) {
            $res = upload_image($_FILES['image'], $DIRS['headers']['abs'], $DIRS['headers']['web']);
            if (!$res['ok']) {
                settings_redirect_back('headers', 'error', $res['error']);
            }
            $existing = $settings->getPageHeader($pageKey);
            if ($existing) {
                settings_safe_unlink($existing['image_path'], $PUBLIC);
            }
            $fields['image_path'] = $res['path'];
        }
        $settings->upsertPageHeader($pageKey, $fields);
        settings_redirect_back('headers', 'success', 'Page banner saved.');
    }

    if ($action === 'save_contact') {
        $keys = [
            'contact_phone', 'contact_email', 'contact_address', 'contact_hours',
            'contact_map_embed', 'contact_map_link', 'contact_map_title',
            'contact_map_subtitle', 'contact_map_blurb',
        ];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $settings->set($key, trim($_POST[$key]));
            }
        }
        settings_redirect_back('contact', 'success', 'Contact & map settings saved.');
    }

    if ($action === 'save_receipt') {
        $keys = [
            'sms_receipt_company_name' => trim($_POST['receipt_company_name'] ?? ''),
            'sms_receipt_phone' => trim($_POST['receipt_phone'] ?? ''),
            'sms_receipt_location' => trim($_POST['receipt_location'] ?? ''),
            'sms_receipt_pobox' => trim($_POST['receipt_pobox'] ?? ''),
            'sms_receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
        ];
        foreach ($keys as $key => $val) {
            $settings->set($key, $val);
        }
        if (!empty($_FILES['receipt_logo']['name'])) {
            if (!is_dir($DIRS['receipt']['abs'])) {
                mkdir($DIRS['receipt']['abs'], 0755, true);
            }
            $res = upload_image($_FILES['receipt_logo'], $DIRS['receipt']['abs'], $DIRS['receipt']['web'], ['png', 'jpg', 'jpeg', 'webp']);
            if (!$res['ok']) {
                settings_redirect_back('receipt', 'error', $res['error']);
            }
            settings_safe_unlink($settings->get('sms_receipt_logo_path'), $PUBLIC);
            $settings->set('sms_receipt_logo_path', $res['path']);
        }
        settings_redirect_back('receipt', 'success', 'Receipt settings saved.');
    }

    settings_redirect_back($section, 'error', 'Unknown action.');
}

$siteName = $settings->get('site_name', 'Dazu Hub Institute');
$logoAlt = $settings->get('logo_alt', 'Dazu Hub Institute');
$logoUrl = settings_asset_url($settings->get('logo_path'));
$heroSlides = $settings->getHeroSlides(false);
$pageHeaders = $settings->getAllPageHeaders();

$titles = [
    'logo' => 'Branding',
    'hero' => 'Homepage Hero',
    'headers' => 'Page Banners',
    'contact' => 'Contact & Map',
    'receipt' => 'Receipts',
];
$contactPhone = $settings->get('contact_phone', '+254 750 116 600');
$contactEmail = $settings->get('contact_email', 'admin@dazuhub.com');
$contactAddress = $settings->get('contact_address', 'Witeithie House, Room 529, Thika Town');
$contactHours = $settings->get('contact_hours', 'Monday - Saturday: 8:00 AM - 5:00 PM');
$contactMapEmbed = $settings->get('contact_map_embed', '');
$contactMapLink = $settings->get('contact_map_link', '');
$contactMapTitle = $settings->get('contact_map_title', 'Witeithie House, Thika');
$contactMapSubtitle = $settings->get('contact_map_subtitle', 'Room 529 (5th Floor)');
$contactMapBlurb = $settings->get('contact_map_blurb', 'Located directly within Thika Town, near principal transport stages, making daily commutes seamless for local students.');
$receiptCompany = $settings->get('sms_receipt_company_name', $siteName);
$receiptPhone = $settings->get('sms_receipt_phone', $contactPhone);
$receiptLocation = $settings->get('sms_receipt_location', $contactAddress);
$receiptPobox = $settings->get('sms_receipt_pobox', '');
$receiptFooter = $settings->get('sms_receipt_footer', 'Thank you for your payment. Please retain this receipt for your records.');
$receiptLogoUrl = settings_asset_url($settings->get('sms_receipt_logo_path') ?: $settings->get('logo_path'));

$page_title = $titles[$section];
$breadcrumbs = [
    ['label' => 'Settings', 'url' => url('admin/settings/index.php')],
    ['label' => $titles[$section], 'active' => true],
];

$sections = [
    'logo' => ['label' => 'Branding', 'icon' => 'fa-image'],
    'hero' => ['label' => 'Hero', 'icon' => 'fa-images'],
    'headers' => ['label' => 'Page banners', 'icon' => 'fa-heading'],
    'contact' => ['label' => 'Contact & map', 'icon' => 'fa-map-location-dot'],
    'receipt' => ['label' => 'Receipts', 'icon' => 'fa-receipt'],
];

ob_start();
?>
<ul class="nav nav-pills mb-4 flex-wrap gap-1">
  <?php foreach ($sections as $key => $meta): ?>
    <li class="nav-item">
      <a class="nav-link <?php echo $section === $key ? 'active' : ''; ?>"
         href="<?php echo url('admin/settings/edit.php?section=' . $key); ?>">
        <i class="fas <?php echo $meta['icon']; ?> me-1"></i><?php echo htmlspecialchars($meta['label']); ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($section === 'logo'): ?>
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Branding</h5></div>
    <div class="card-body">
      <p class="text-muted small">The logo is used across the site and in admission letters. Recommended: transparent PNG or WebP.</p>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_logo">

        <div class="border rounded bg-dark d-flex align-items-center justify-content-center mb-3" style="height:120px;">
          <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($logoAlt); ?>" style="max-height:72px;max-width:70%;object-fit:contain;">
          <?php else: ?>
            <span class="text-white-50 small">No logo uploaded yet</span>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Replace logo</label>
          <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
        </div>

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Site name</label>
            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($siteName); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Logo alt text</label>
            <input type="text" name="logo_alt" class="form-control" value="<?php echo htmlspecialchars($logoAlt); ?>">
          </div>
        </div>

        <button class="btn btn-primary mt-4" type="submit"><i class="fas fa-floppy-disk me-1"></i> Save branding</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($section === 'hero'): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Add hero slide</h5></div>
    <div class="card-body">
      <p class="text-muted small">These images appear in the homepage hero slideshow. Landscape images (1920×1080) look best.</p>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_hero">
        <div class="mb-3">
          <label class="form-label">Image</label>
          <input type="file" name="image" class="form-control" accept="image/*" required>
        </div>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Caption (optional)</label>
            <input type="text" name="caption" class="form-control" placeholder="e.g. Creative workspace">
          </div>
          <div class="col-md-4">
            <label class="form-label">Order</label>
            <input type="number" name="sort_order" class="form-control" value="0">
          </div>
        </div>
        <button class="btn btn-primary mt-3" type="submit"><i class="fas fa-plus me-1"></i> Add slide</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Current slides</h5>
      <span class="badge bg-secondary"><?php echo count($heroSlides); ?></span>
    </div>
    <div class="card-body">
      <?php if (empty($heroSlides)): ?>
        <p class="text-muted mb-0">No slides yet. The homepage will use built-in fallback images until you add some.</p>
      <?php else: ?>
        <?php foreach ($heroSlides as $s): ?>
          <div class="border rounded p-3 mb-3">
            <div class="row g-3 align-items-start">
              <div class="col-md-3 col-lg-2">
                <img src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" alt="" class="img-fluid rounded border w-100" style="max-height:90px;object-fit:cover;">
              </div>
              <div class="col-md-9 col-lg-8">
                <form method="POST" class="row g-2 align-items-end">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="update_hero">
                  <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                  <div class="col-md-7">
                    <label class="form-label small mb-1">Caption</label>
                    <input type="text" name="caption" class="form-control form-control-sm" value="<?php echo htmlspecialchars($s['caption'] ?? ''); ?>">
                  </div>
                  <div class="col-md-2">
                    <label class="form-label small mb-1">Order</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" value="<?php echo (int) $s['sort_order']; ?>">
                  </div>
                  <div class="col-md-3">
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="is_active" id="hero_active_<?php echo (int) $s['id']; ?>" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                      <label class="form-check-label small" for="hero_active_<?php echo (int) $s['id']; ?>">Active</label>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-rotate me-1"></i> Update</button>
                  </div>
                </form>
              </div>
              <div class="col-lg-2 text-lg-end">
                <form method="POST" onsubmit="return confirm('Remove this slide?');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete_hero">
                  <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($section === 'headers'): ?>
  <?php foreach ($pageHeaders as $h): ?>
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0 text-capitalize"><?php echo htmlspecialchars($h['page_key']); ?> page banner</h5></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save_header">
          <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($h['page_key']); ?>">

          <?php if (!empty($h['image_path'])): ?>
            <img src="<?php echo htmlspecialchars(settings_asset_url($h['image_path'])); ?>" alt="" class="img-fluid rounded border mb-3 w-100" style="max-height:160px;object-fit:cover;">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($h['title'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Subtitle</label>
            <input type="text" name="subtitle" class="form-control" value="<?php echo htmlspecialchars($h['subtitle'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label"><?php echo !empty($h['image_path']) ? 'Replace banner image' : 'Banner image'; ?></label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
          <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk me-1"></i> Save banner</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">Add a new page banner</h5></div>
    <div class="card-body">
      <p class="text-muted small">Use a short key like <code>about</code> or <code>contact</code>. Your page references it via <code>$page_key</code>.</p>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_header">
        <div class="mb-3">
          <label class="form-label">Page key</label>
          <input type="text" name="page_key" class="form-control" placeholder="about" required pattern="[a-z0-9_-]+">
        </div>
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Subtitle</label>
          <input type="text" name="subtitle" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Banner image (optional)</label>
          <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-plus me-1"></i> Add page banner</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($section === 'contact'): ?>
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Contact page &amp; campus map</h5></div>
    <div class="card-body">
      <p class="text-muted small">These details appear on the public Contact page. Paste a Google Maps <strong>embed URL</strong> (iframe src) for the live map.</p>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_contact">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Phone / WhatsApp (display)</label>
            <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($contactPhone); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="text" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($contactEmail); ?>">
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Campus address</label>
          <input type="text" name="contact_address" class="form-control" value="<?php echo htmlspecialchars($contactAddress); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Office hours</label>
          <input type="text" name="contact_hours" class="form-control" value="<?php echo htmlspecialchars($contactHours); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Google Maps embed URL (iframe src)</label>
          <textarea name="contact_map_embed" rows="3" class="form-control" placeholder="https://www.google.com/maps/embed?pb=..."><?php echo htmlspecialchars($contactMapEmbed); ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Google Maps link (Open in Maps button)</label>
          <input type="url" name="contact_map_link" class="form-control" value="<?php echo htmlspecialchars($contactMapLink); ?>" placeholder="https://maps.google.com/...">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Map card title</label>
            <input type="text" name="contact_map_title" class="form-control" value="<?php echo htmlspecialchars($contactMapTitle); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Map card subtitle</label>
            <input type="text" name="contact_map_subtitle" class="form-control" value="<?php echo htmlspecialchars($contactMapSubtitle); ?>">
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Directions blurb</label>
          <textarea name="contact_map_blurb" rows="3" class="form-control"><?php echo htmlspecialchars($contactMapBlurb); ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk me-1"></i> Save contact settings</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($section === 'receipt'): ?>
  <div class="card">
    <div class="card-header"><h5 class="mb-0">SmS receipt layout</h5></div>
    <div class="card-body">
      <p class="text-muted small">Professional black &amp; white receipts for SmS student payments. Logo, address and footer appear on generated PDF receipts.</p>
      <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_receipt">

        <div class="border rounded bg-white d-flex align-items-center justify-content-center mb-3" style="height:96px;">
          <?php if ($receiptLogoUrl): ?>
            <img src="<?php echo htmlspecialchars($receiptLogoUrl); ?>" alt="Receipt logo" style="max-height:64px;max-width:70%;object-fit:contain;">
          <?php else: ?>
            <span class="text-muted small">No receipt logo — site logo will be used if set</span>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Receipt logo (optional)</label>
          <input type="file" name="receipt_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="mb-3">
          <label class="form-label">Company / institute name</label>
          <input type="text" name="receipt_company_name" class="form-control" value="<?php echo htmlspecialchars($receiptCompany); ?>">
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="receipt_phone" class="form-control" value="<?php echo htmlspecialchars($receiptPhone); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">P.O. Box</label>
            <input type="text" name="receipt_pobox" class="form-control" value="<?php echo htmlspecialchars($receiptPobox); ?>">
          </div>
        </div>
        <div class="mb-3 mt-3">
          <label class="form-label">Location / address</label>
          <input type="text" name="receipt_location" class="form-control" value="<?php echo htmlspecialchars($receiptLocation); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Receipt footer note</label>
          <textarea name="receipt_footer" rows="3" class="form-control"><?php echo htmlspecialchars($receiptFooter); ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk me-1"></i> Save receipt settings</button>
      </form>
    </div>
  </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
