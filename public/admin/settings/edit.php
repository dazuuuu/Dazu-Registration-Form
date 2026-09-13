<?php
// public/admin/settings/edit.php
// Handles uploads + DB writes for: logo, hero slides, page headers.
// Sections via ?section=logo|hero|headers . Uses Post-Redirect-Get.

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';        // BASE_URL + helpers
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';

// --- Admin guard ------------------------------------------------------
Middleware::requireAdmin();

$settings = new SettingModel($pdo);

// Filesystem root of /public  and the upload destinations.
$PUBLIC = defined('PUBLIC_ROOT') ? rtrim(PUBLIC_ROOT, '/\\') : realpath(__DIR__ . '/../..');
$DIRS = [
    'logo'    => ['abs' => $PUBLIC . '/assets/images/branding', 'web' => 'assets/images/branding'],
    'hero'    => ['abs' => $PUBLIC . '/uploads/hero',           'web' => 'uploads/hero'],
    'headers' => ['abs' => $PUBLIC . '/uploads/headers',        'web' => 'uploads/headers'],
    'receipt' => ['abs' => $PUBLIC . '/uploads/receipts',       'web' => 'uploads/receipts'],
];

$section = $_GET['section'] ?? 'logo';
if (!in_array($section, ['logo', 'hero', 'headers', 'contact', 'schedule', 'receipt'], true)) {
    $section = 'logo';
}

/** Remove a stored file, but only if it really lives under /public. */
function safe_unlink(?string $relPath, string $publicRoot): void
{
    if (!$relPath) return;
    if (preg_match('#^(https?:)?//#', $relPath)) return;   // external URL, skip
    $abs = realpath($publicRoot . '/' . ltrim($relPath, '/'));
    if ($abs && str_starts_with($abs, $publicRoot) && is_file($abs)) {
        @unlink($abs);
    }
}

function redirect_back(string $section, string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: edit.php?section=' . urlencode($section));
    exit;
}

/* ====================================================================
   POST HANDLING
   ==================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        redirect_back($section, 'error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    // ---- LOGO -------------------------------------------------------
    if ($action === 'save_logo') {
        $settings->set('site_name', trim($_POST['site_name'] ?? '') ?: 'Ismano');
        $settings->set('logo_alt',  trim($_POST['logo_alt'] ?? '') ?: 'Ismano');

        if (!empty($_FILES['logo']['name'])) {
            $res = upload_image(
                $_FILES['logo'],
                $DIRS['logo']['abs'],
                $DIRS['logo']['web'],
                ['png', 'jpg', 'jpeg', 'webp', 'svg']
            );
            if (!$res['ok']) {
                redirect_back('logo', 'error', $res['error']);
            }
            safe_unlink($settings->get('logo_path'), $PUBLIC);   // remove previous
            $settings->set('logo_path', $res['path']);
        }
        redirect_back('logo', 'success', 'Branding saved.');
    }

    // ---- HERO: add --------------------------------------------------
    if ($action === 'add_hero') {
        if (empty($_FILES['media']['name'])) {
            redirect_back('hero', 'error', 'Please choose an image or video to add.');
        }
        $mediaType = str_starts_with((string) ($_FILES['media']['type'] ?? ''), 'video/') ? 'video' : 'image';
        $res = $mediaType === 'video'
            ? upload_video($_FILES['media'], $DIRS['hero']['abs'], $DIRS['hero']['web'])
            : upload_image($_FILES['media'], $DIRS['hero']['abs'], $DIRS['hero']['web']);
        if (!$res['ok']) {
            redirect_back('hero', 'error', $res['error']);
        }
        $settings->addHeroSlide(
            $res['path'],
            trim($_POST['caption'] ?? '') ?: null,
            (int) ($_POST['sort_order'] ?? 0),
            [
                'media_type' => $mediaType,
            ]
        );
        redirect_back('hero', 'success', 'Slide added.');
    }

    // ---- HERO: update meta -----------------------------------------
    if ($action === 'update_hero') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $mediaType = in_array($_POST['media_type'] ?? 'image', ['image', 'video'], true) ? $_POST['media_type'] : 'image';
            $settings->updateHeroSlide($id, [
                'caption'    => trim($_POST['caption'] ?? '') ?: null,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active'  => isset($_POST['is_active']) ? 1 : 0,
                'media_type' => $mediaType,
            ]);
            if (!empty($_FILES['media']['name'])) {
                $oldSlide = $settings->getHeroSlide($id);
                $newType = str_starts_with((string) ($_FILES['media']['type'] ?? ''), 'video/') ? 'video' : 'image';
                $res = $newType === 'video'
                    ? upload_video($_FILES['media'], $DIRS['hero']['abs'], $DIRS['hero']['web'])
                    : upload_image($_FILES['media'], $DIRS['hero']['abs'], $DIRS['hero']['web']);
                if (!$res['ok']) {
                    redirect_back('hero', 'error', $res['error']);
                }
                $settings->replaceHeroSlideMedia($id, $res['path'], $newType);
                safe_unlink($oldSlide['image_path'] ?? null, $PUBLIC);
            }
        }
        redirect_back('hero', 'success', 'Slide updated.');
    }

    // ---- HERO: delete ----------------------------------------------
    if ($action === 'delete_hero') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $removedPath = $settings->deleteHeroSlide($id);
            safe_unlink($removedPath, $PUBLIC);
        }
        redirect_back('hero', 'success', 'Slide removed.');
    }

    // ---- PAGE HEADER: save -----------------------------------------
    if ($action === 'save_header') {
        $pageKey = trim($_POST['page_key'] ?? '');
        if ($pageKey === '' || !preg_match('/^[a-z0-9_-]+$/', $pageKey)) {
            redirect_back('headers', 'error', 'Invalid page key (use lowercase letters, numbers, - or _).');
        }
        $fields = [
            'title'    => trim($_POST['title'] ?? '') ?: null,
            'subtitle' => trim($_POST['subtitle'] ?? '') ?: null,
        ];
        if (!empty($_FILES['image']['name'])) {
            $res = upload_image($_FILES['image'], $DIRS['headers']['abs'], $DIRS['headers']['web']);
            if (!$res['ok']) {
                redirect_back('headers', 'error', $res['error']);
            }
            $existing = $settings->getPageHeader($pageKey);
            if ($existing) {
                safe_unlink($existing['image_path'], $PUBLIC);
            }
            $fields['image_path'] = $res['path'];
        }
        $settings->upsertPageHeader($pageKey, $fields);
        redirect_back('headers', 'success', 'Page banner saved.');
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
        redirect_back('contact', 'success', 'Contact & map settings saved.');
    }

    if ($action === 'save_schedule') {
        $settings->set('class_schedule_online', mb_strimwidth(trim((string) ($_POST['class_schedule_online'] ?? '')), 0, 240, ''));
        $settings->set('class_schedule_physical', mb_strimwidth(trim((string) ($_POST['class_schedule_physical'] ?? '')), 0, 240, ''));
        $settings->set('class_schedule_note', mb_strimwidth(trim((string) ($_POST['class_schedule_note'] ?? '')), 0, 500, ''));
        redirect_back('schedule', 'success', 'Class schedule saved.');
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
            $res = upload_image($_FILES['receipt_logo'], $DIRS['receipt']['abs'], $DIRS['receipt']['web'], ['png', 'jpg', 'jpeg', 'webp', 'svg']);
            if (!$res['ok']) {
                redirect_back('receipt', 'error', $res['error']);
            }
            safe_unlink($settings->get('sms_receipt_logo_path'), $PUBLIC);
            $settings->set('sms_receipt_logo_path', $res['path']);
        }
        redirect_back('receipt', 'success', 'Receipt settings saved.');
    }

    redirect_back($section, 'error', 'Unknown action.');
}

/* ====================================================================
   VIEW DATA
   ==================================================================== */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$siteName    = $settings->get('site_name', 'Ismano');
$logoAlt     = $settings->get('logo_alt', 'Ismano');
$logoUrl     = settings_asset_url($settings->get('logo_path'));
$heroSlides  = $settings->getHeroSlides(false);
$pageHeaders = $settings->getAllPageHeaders();

$titles = ['logo' => 'Branding', 'hero' => 'Homepage Hero', 'headers' => 'Page Banners', 'contact' => 'Contact & Map', 'schedule' => 'Class Schedule', 'receipt' => 'Receipts'];
$contactPhone = $settings->get('contact_phone', '+254 750 116 600');
$contactEmail = $settings->get('contact_email', 'admin@dazuhub.com');
$contactAddress = $settings->get('contact_address', 'Witeithie House, Room 529, Thika Town');
$contactHours = $settings->get('contact_hours', 'Monday - Saturday: 8:00 AM - 5:00 PM');
$contactMapEmbed = $settings->get('contact_map_embed', '');
$contactMapLink = $settings->get('contact_map_link', '');
$contactMapTitle = $settings->get('contact_map_title', 'Witeithie House, Thika');
$contactMapSubtitle = $settings->get('contact_map_subtitle', 'Room 529 (5th Floor)');
$contactMapBlurb = $settings->get('contact_map_blurb', 'Visit our Thika campus for physical labs, or contact admissions for Nairobi classes and online learning options.');
$classScheduleOnline = $settings->get('class_schedule_online', '8am, 12pm, 6pm');
$classSchedulePhysical = $settings->get('class_schedule_physical', '5am');
$classScheduleNote = $settings->get('class_schedule_note', '');
$receiptCompany = $settings->get('sms_receipt_company_name', $siteName);
$receiptPhone = $settings->get('sms_receipt_phone', $contactPhone);
$receiptLocation = $settings->get('sms_receipt_location', $contactAddress);
$receiptPobox = $settings->get('sms_receipt_pobox', '');
$receiptFooter = $settings->get('sms_receipt_footer', 'Thank you for your payment. Please retain this receipt for your records.');
$receiptLogoUrl = settings_asset_url($settings->get('sms_receipt_logo_path') ?: $settings->get('logo_path'));
$page_title = $titles[$section] . ' Settings';
$breadcrumbs = [
    ['label' => 'Settings', 'url' => url('admin/settings/index.php')],
    ['label' => $titles[$section], 'active' => true],
];

ob_start();
?>
<style>
.settings-edit { max-width: 980px; }
.settings-edit .tabs { display: flex; gap: 6px; margin-bottom: 24px; flex-wrap: wrap; }
.settings-edit .tab { padding: 9px 16px; border-radius: 9px; font-size: .86rem; font-weight: 600; color: #5b6472; background: #fff; border: 1px solid #e6e9f0; text-decoration: none; }
.settings-edit .tab:hover { border-color: var(--brand-primary, #0D9488); color: var(--brand-primary, #0D9488); }
.settings-edit .tab.active { background: var(--brand-primary, #0D9488); color: #fff; border-color: var(--brand-primary, #0D9488); }
.settings-edit .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 22px; font-size: .9rem; font-weight: 500; }
.settings-edit .flash.success { background: #e7f7ee; color: #11704a; border: 1px solid #b6e6cd; }
.settings-edit .flash.error { background: #fdecec; color: #a4282b; border: 1px solid #f3c0c1; }
.settings-edit .panel { background: #fff; border: 1px solid #e6e9f0; border-radius: 14px; padding: 24px; margin-bottom: 22px; box-shadow: 0 3px 14px rgba(20,35,25,.07); }
.settings-edit .panel h2 { font-size: 1.15rem; margin: 0 0 4px; font-weight: 700; }
.settings-edit .panel .hint { color: #8a93a3; font-size: .85rem; margin: 0 0 22px; }
.settings-edit label { display: block; font-size: .82rem; font-weight: 600; color: #3a4250; margin: 0 0 6px; }
.settings-edit input[type=text], .settings-edit input[type=number], .settings-edit input[type=url], .settings-edit textarea, .settings-edit select {
  width: 100%; padding: 10px 12px; border: 1px solid #d7dce6; border-radius: 9px;
  font-size: .9rem; font-family: inherit; color: #101319; background: #fff; outline: none;
}
.settings-edit input:focus, .settings-edit textarea:focus, .settings-edit select:focus { border-color: var(--brand-primary, #0D9488); box-shadow: 0 0 0 3px rgba(13,148,136,.12); }
.settings-edit .field { margin-bottom: 18px; }
.settings-edit .row { display: grid; grid-template-columns: 1fr 140px; gap: 14px; }
.settings-edit .file-drop { border: 1.5px dashed #c7cdda; border-radius: 11px; padding: 16px; background: #fafbfd; display: flex; align-items: center; gap: 14px; font-size: .85rem; color: #5b6472; }
.settings-edit .file-drop input { font-size: .82rem; }
.settings-edit .btn { display: inline-flex; align-items: center; gap: 8px; }
.settings-edit .btn-ghost { background: #fff; color: #3a4250; border: 1px solid #d7dce6; }
.settings-edit .btn-ghost:hover { border-color: var(--brand-primary, #0D9488); }
.settings-edit .logo-preview { height: 96px; border-radius: 11px; border: 1px solid #e6e9f0; background: #0b0d12; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 18px; }
.settings-edit .logo-preview img { max-height: 64px; max-width: 70%; object-fit: contain; }
.settings-edit .logo-preview .muted { color: #6b7686; font-size: .85rem; }
.settings-edit .slide { display: grid; grid-template-columns: 120px 1fr auto; gap: 16px; align-items: center; border: 1px solid #eceff4; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
.settings-edit .slide img { width: 120px; height: 74px; object-fit: cover; border-radius: 8px; }
.settings-edit .slide .meta { display: flex; flex-direction: column; gap: 8px; }
.settings-edit .slide .inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.settings-edit .slide .inline input[type=text] { flex: 1; min-width: 140px; }
.settings-edit .slide .inline input[type=number] { width: 80px; }
.settings-edit .chk { display: flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 500; color: #3a4250; }
.settings-edit .slide .actions { display: flex; flex-direction: column; gap: 8px; }
.settings-edit .empty { color: #8a93a3; font-size: .88rem; padding: 8px 0; }
.settings-edit .divider { height: 1px; background: #eceff4; margin: 24px 0; }
@media (max-width: 767.98px) {
  .settings-edit .row, .settings-edit .slide { display: block; }
  .settings-edit .slide img { width: 100%; height: 150px; margin-bottom: 12px; }
}
</style>

<div class="settings-edit">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h5 class="mb-1"><i class="fas fa-cog me-2"></i><?php echo htmlspecialchars($titles[$section]); ?></h5>
      <div class="text-muted small">Editing settings for <?php echo htmlspecialchars($siteName); ?>.</div>
    </div>
    <a href="<?php echo url('admin/settings/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i> All settings
    </a>
  </div>

  <div class="tabs">
    <a class="tab <?php echo $section === 'logo' ? 'active' : ''; ?>"    href="edit.php?section=logo"><i class="fa-solid fa-image"></i> Branding</a>
    <a class="tab <?php echo $section === 'hero' ? 'active' : ''; ?>"    href="edit.php?section=hero"><i class="fa-solid fa-images"></i> Hero</a>
    <a class="tab <?php echo $section === 'headers' ? 'active' : ''; ?>" href="edit.php?section=headers"><i class="fa-solid fa-heading"></i> Page banners</a>
    <a class="tab <?php echo $section === 'contact' ? 'active' : ''; ?>" href="edit.php?section=contact"><i class="fa-solid fa-map-location-dot"></i> Contact &amp; map</a>
    <a class="tab <?php echo $section === 'schedule' ? 'active' : ''; ?>" href="edit.php?section=schedule"><i class="fa-solid fa-calendar-days"></i> Class schedule</a>
    <a class="tab <?php echo $section === 'receipt' ? 'active' : ''; ?>" href="edit.php?section=receipt"><i class="fa-solid fa-receipt"></i> Receipts</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
      <?php echo htmlspecialchars($flash['msg']); ?>
    </div>
  <?php endif; ?>

  <?php /* ============================ LOGO ============================ */ ?>
  <?php if ($section === 'logo'): ?>
    <form class="panel" method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_logo">
      <h2>Branding</h2>
      <p class="hint">The logo here is used anywhere the site shows a logo. Recommended: a transparent PNG or WebP.</p>

      <div class="logo-preview">
        <?php if ($logoUrl): ?>
          <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($logoAlt); ?>">
        <?php else: ?>
          <span class="muted">No logo uploaded yet</span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Replace logo</label>
        <div class="file-drop">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-sky);"></i>
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        </div>
      </div>

      <div class="row">
        <div class="field" style="margin:0;">
          <label>Site name</label>
          <input type="text" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>">
        </div>
        <div class="field" style="margin:0;">
          <label>Logo alt text</label>
          <input type="text" name="logo_alt" value="<?php echo htmlspecialchars($logoAlt); ?>">
        </div>
      </div>

      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save branding</button>
    </form>
  <?php endif; ?>

  <?php /* ============================ HERO ============================ */ ?>
  <?php if ($section === 'hero'): ?>
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div>
          <h2>Homepage Hero Slides</h2>
          <p class="hint mb-1">Manage the hero slides appearing on the homepage opening section. The first active slide (Order 0) is your main opening hero image.</p>
        </div>
      </div>
      <div class="p-2 mb-3 rounded bg-light border text-muted small d-flex align-items-center gap-2">
        <i class="fa-solid fa-bolt text-success"></i>
        <span><strong>Speed Optimization:</strong> All uploaded photos are automatically downscaled and converted to ultra-lightweight WebP format to ensure rapid page loads.</span>
      </div>

      <?php if (empty($heroSlides)): ?>
        <p class="empty">No slides yet. The homepage will use its built-in fallback images until you add some.</p>
      <?php else: ?>
        <?php foreach ($heroSlides as $idx => $s): ?>
          <?php
            $slideExt = strtolower(pathinfo(parse_url((string) ($s['image_path'] ?? ''), PHP_URL_PATH) ?: (string) ($s['image_path'] ?? ''), PATHINFO_EXTENSION));
            $slideType = (($s['media_type'] ?? '') === 'video' || in_array($slideExt, ['mp4', 'webm', 'mov'], true)) ? 'video' : 'image';
            $isPrimary = ($idx === 0);
          ?>
          <div class="slide" style="<?php echo $isPrimary ? 'border: 2px solid #15A24A; background: #fcfdfc;' : ''; ?>">
            <div style="position:relative;">
              <?php if ($slideType === 'video'): ?>
                <video src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" muted playsinline style="width:120px;height:74px;object-fit:cover;border-radius:8px;"></video>
              <?php else: ?>
                <img src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" alt="" style="width:120px;height:74px;object-fit:cover;border-radius:8px;">
              <?php endif; ?>
              <?php if ($isPrimary): ?>
                <span class="badge bg-success" style="position:absolute;top:-8px;left:-6px;font-size:0.65rem;box-shadow:0 2px 4px rgba(0,0,0,0.15);">Main Hero</span>
              <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" class="meta">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="update_hero">
              <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
              <input type="hidden" name="media_type" value="<?php echo htmlspecialchars($slideType); ?>">

              <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="small fw-bold text-dark">
                  <?php echo $isPrimary ? '🌟 Primary Homepage Slide (Opens First)' : 'Slide ' . ($idx + 1) . ' (Carousel)'; ?>
                </span>
                <label class="chk mb-0"><input type="checkbox" name="is_active" <?php echo $s['is_active'] ? 'checked' : ''; ?>> Active</label>
              </div>

              <div class="inline">
                <input type="text" name="caption" value="<?php echo htmlspecialchars($s['caption'] ?? ''); ?>" placeholder="Alt text / Caption (e.g. Student working on laptop)">
                <input type="number" name="sort_order" value="<?php echo (int) $s['sort_order']; ?>" title="Order (0 = first)" style="width:80px;">
              </div>

              <div class="file-drop" style="padding:10px 14px;">
                <i class="fa-solid fa-cloud-arrow-up" style="color:var(--brand-primary, #15A24A);font-size:1.1rem;"></i>
                <div style="flex:1;">
                  <div class="small fw-semibold text-dark">Replace image/video:</div>
                  <input type="file" name="media" accept="image/*,video/mp4,video/webm,video/quicktime" style="width:100%;">
                </div>
              </div>

              <div class="d-flex align-items-center gap-2">
                <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-check"></i> Save Slide Changes</button>
              </div>
            </form>
            <div class="actions">
              <form method="POST" onsubmit="return confirm('Remove this slide?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_hero">
                <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                <button class="btn btn-danger btn-sm" type="submit" title="Delete slide"><i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form class="panel" method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_hero">
      <h2>Add another hero slide</h2>
      <p class="hint">Add a secondary slide to the homepage hero carousel. Upload an image or video.</p>

      <div class="field">
        <label>Media file</label>
        <div class="file-drop">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-primary, #15A24A);"></i>
          <input type="file" name="media" accept="image/*,video/mp4,video/webm,video/quicktime" required>
        </div>
      </div>
      <div class="row">
        <div class="field" style="margin:0;">
          <label>Caption / alt text <span class="text-muted small">(optional)</span></label>
          <input type="text" name="caption" placeholder="e.g. Students collaborating in campus lab">
        </div>
        <div class="field" style="margin:0;">
          <label>Sort Order</label>
          <input type="number" name="sort_order" value="<?php echo count($heroSlides); ?>">
        </div>
      </div>
      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add new slide</button>
    </form>
  <?php endif; ?>

  <?php /* ========================= PAGE HEADERS ========================= */ ?>
  <?php if ($section === 'headers'): ?>
    <?php foreach ($pageHeaders as $h): ?>
      <form class="panel" method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_header">
        <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($h['page_key']); ?>">
        <h2 style="text-transform:capitalize;"><?php echo htmlspecialchars($h['page_key']); ?> page</h2>
        <p class="hint">Shown as the banner at the top of the <?php echo htmlspecialchars($h['page_key']); ?> page.</p>

        <?php if (!empty($h['image_path'])): ?>
          <div class="logo-preview" style="height:120px;background:#0b0d12;">
            <img src="<?php echo htmlspecialchars(settings_asset_url($h['image_path'])); ?>" style="max-height:120px;max-width:100%;object-fit:cover;width:100%;border-radius:11px;" alt="">
          </div>
        <?php endif; ?>

        <div class="field">
          <label>Title</label>
          <input type="text" name="title" value="<?php echo htmlspecialchars($h['title'] ?? ''); ?>">
        </div>
        <div class="field">
          <label>Subtitle</label>
          <input type="text" name="subtitle" value="<?php echo htmlspecialchars($h['subtitle'] ?? ''); ?>">
        </div>
        <div class="field">
          <label><?php echo !empty($h['image_path']) ? 'Replace banner image' : 'Banner image'; ?></label>
          <div class="file-drop">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-sky);"></i>
            <input type="file" name="image" accept="image/*">
          </div>
        </div>
        <div class="divider"></div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save <?php echo htmlspecialchars($h['page_key']); ?> banner</button>
      </form>
    <?php endforeach; ?>

    <!-- Add a new page key -->
    <form class="panel" method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_header">
      <h2>Add a new page</h2>
      <p class="hint">Use a short key like <code>about</code> or <code>contact</code>. Your page references it via <code>$page_key</code>.</p>
      <div class="field">
        <label>Page key</label>
        <input type="text" name="page_key" placeholder="about" required pattern="[a-z0-9_-]+">
      </div>
      <div class="field">
        <label>Title</label>
        <input type="text" name="title">
      </div>
      <div class="field">
        <label>Subtitle</label>
        <input type="text" name="subtitle">
      </div>
      <div class="field">
        <label>Banner image (optional)</label>
        <div class="file-drop">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-sky);"></i>
          <input type="file" name="image" accept="image/*">
        </div>
      </div>
      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add page banner</button>
    </form>
  <?php endif; ?>

  <?php if ($section === 'contact'): ?>
    <form class="panel" method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_contact">
      <h2>Contact page &amp; campus map</h2>
      <p class="hint">These details appear on the public Contact page. Paste a Google Maps <strong>embed URL</strong> (iframe src) for the live map.</p>

      <div class="row">
        <div class="field" style="margin:0;">
          <label>Phone / WhatsApp (display)</label>
          <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($contactPhone); ?>">
        </div>
        <div class="field" style="margin:0;">
          <label>Email</label>
          <input type="text" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>">
        </div>
      </div>
      <div class="field">
        <label>Campus address</label>
        <input type="text" name="contact_address" value="<?php echo htmlspecialchars($contactAddress); ?>">
      </div>
      <div class="field">
        <label>Office hours</label>
        <input type="text" name="contact_hours" value="<?php echo htmlspecialchars($contactHours); ?>">
      </div>
      <div class="field">
        <label>Google Maps embed URL (iframe src)</label>
        <textarea name="contact_map_embed" rows="3" placeholder="https://www.google.com/maps/embed?pb=..."><?php echo htmlspecialchars($contactMapEmbed); ?></textarea>
      </div>
      <div class="field">
        <label>Google Maps link (Open in Maps button)</label>
        <input type="url" name="contact_map_link" value="<?php echo htmlspecialchars($contactMapLink); ?>" placeholder="https://maps.google.com/...">
      </div>
      <div class="row">
        <div class="field" style="margin:0;">
          <label>Map card title</label>
          <input type="text" name="contact_map_title" value="<?php echo htmlspecialchars($contactMapTitle); ?>">
        </div>
        <div class="field" style="margin:0;">
          <label>Map card subtitle</label>
          <input type="text" name="contact_map_subtitle" value="<?php echo htmlspecialchars($contactMapSubtitle); ?>">
        </div>
      </div>
      <div class="field">
        <label>Directions blurb</label>
        <textarea name="contact_map_blurb" rows="3"><?php echo htmlspecialchars($contactMapBlurb); ?></textarea>
      </div>
      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save contact settings</button>
    </form>
  <?php endif; ?>

  <?php if ($section === 'schedule'): ?>
    <form class="panel" method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_schedule">
      <h2>Class schedule</h2>
      <p class="hint">These official class times are shown on every paid course details page. Keep them short and easy to scan.</p>

      <div class="row">
        <div class="field" style="margin:0;">
          <label>Online classes</label>
          <input type="text" name="class_schedule_online" maxlength="240"
                 placeholder="e.g. 8am, 12pm, 6pm"
                 value="<?php echo htmlspecialchars($classScheduleOnline ?? ''); ?>">
        </div>
        <div class="field" style="margin:0;">
          <label>Physical classes</label>
          <input type="text" name="class_schedule_physical" maxlength="240"
                 placeholder="e.g. 5am"
                 value="<?php echo htmlspecialchars($classSchedulePhysical ?? ''); ?>">
        </div>
      </div>

      <div class="field">
        <label>Schedule note <span class="text-muted small">(optional)</span></label>
        <textarea name="class_schedule_note" rows="3" maxlength="500" placeholder="e.g. Students confirm their selected slot after admission."><?php echo htmlspecialchars($classScheduleNote ?? ''); ?></textarea>
      </div>

      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save class schedule</button>
    </form>
  <?php endif; ?>

  <?php if ($section === 'receipt'): ?>
    <form class="panel" method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_receipt">
      <h2>SmS receipt layout</h2>
      <p class="hint">Professional black &amp; white receipts for SmS student payments. Logo, address and footer appear on generated PDF receipts.</p>

      <div class="logo-preview" style="background:#fff;border:1px solid #111;">
        <?php if ($receiptLogoUrl): ?>
          <img src="<?php echo htmlspecialchars($receiptLogoUrl); ?>" alt="Receipt logo">
        <?php else: ?>
          <span class="muted">No receipt logo — site logo will be used if set</span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Receipt logo (optional)</label>
        <div class="file-drop">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-sky);"></i>
          <input type="file" name="receipt_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
        </div>
      </div>

      <div class="field">
        <label>Company / institute name</label>
        <input type="text" name="receipt_company_name" value="<?php echo htmlspecialchars($receiptCompany); ?>">
      </div>
      <div class="row">
        <div class="field" style="margin:0;">
          <label>Phone</label>
          <input type="text" name="receipt_phone" value="<?php echo htmlspecialchars($receiptPhone); ?>">
        </div>
        <div class="field" style="margin:0;">
          <label>P.O. Box</label>
          <input type="text" name="receipt_pobox" value="<?php echo htmlspecialchars($receiptPobox); ?>">
        </div>
      </div>
      <div class="field">
        <label>Location / address</label>
        <input type="text" name="receipt_location" value="<?php echo htmlspecialchars($receiptLocation); ?>">
      </div>
      <div class="field">
        <label>Receipt footer note</label>
        <textarea name="receipt_footer" rows="3"><?php echo htmlspecialchars($receiptFooter); ?></textarea>
      </div>
      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save receipt settings</button>
    </form>
  <?php endif; ?>

</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
