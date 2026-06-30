<?php
// public/admin/settings/edit.php
// Handles uploads + DB writes for: logo, hero slides, page headers.
// Sections via ?section=logo|hero|headers . Uses Post-Redirect-Get.

require_once __DIR__ . '/../../../app/helpers/uploads.php';        // BASE_URL + helpers
require_once __DIR__ . '/../../../app/models/SettingModel.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';      // provides $pdo

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Admin guard ------------------------------------------------------
if (empty($_SESSION['logged_in']) || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$settings = new SettingModel($pdo);

// Filesystem root of /public  and the upload destinations.
$PUBLIC = realpath(__DIR__ . '/../..');
$DIRS = [
    'logo'    => ['abs' => $PUBLIC . '/assets/images/branding', 'web' => 'assets/images/branding'],
    'hero'    => ['abs' => $PUBLIC . '/uploads/hero',           'web' => 'uploads/hero'],
    'headers' => ['abs' => $PUBLIC . '/uploads/headers',        'web' => 'uploads/headers'],
];

$section = $_GET['section'] ?? 'logo';
if (!in_array($section, ['logo', 'hero', 'headers'], true)) {
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
                ['png', 'jpg', 'jpeg', 'webp']
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
        if (empty($_FILES['image']['name'])) {
            redirect_back('hero', 'error', 'Please choose an image to add.');
        }
        $res = upload_image($_FILES['image'], $DIRS['hero']['abs'], $DIRS['hero']['web']);
        if (!$res['ok']) {
            redirect_back('hero', 'error', $res['error']);
        }
        $settings->addHeroSlide(
            $res['path'],
            trim($_POST['caption'] ?? '') ?: null,
            (int) ($_POST['sort_order'] ?? 0)
        );
        redirect_back('hero', 'success', 'Slide added.');
    }

    // ---- HERO: update meta -----------------------------------------
    if ($action === 'update_hero') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $settings->updateHeroSlide($id, [
                'caption'    => trim($_POST['caption'] ?? '') ?: null,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active'  => isset($_POST['is_active']) ? 1 : 0,
            ]);
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

$titles = ['logo' => 'Branding', 'hero' => 'Homepage Hero', 'headers' => 'Page Banners'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $titles[$section]; ?> — Settings</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{ --brand-sky:#00A1F3; --brand-royal:#0759F8; --brand-gold:#EBA94E; --brand-black:#000; }
    *{ box-sizing:border-box; }
    body{ margin:0; background:#f5f7fb; color:#101319; font-family:var(--font-body, system-ui, sans-serif); }
    a{ text-decoration:none; color:inherit; }
    .admin-topbar{ display:flex; align-items:center; gap:14px; background:var(--brand-black); color:#fff; padding:14px 28px; }
    .admin-topbar .dot{ width:12px;height:12px;border-radius:3px;background:var(--brand-sky); }
    .admin-topbar h1{ font-size:1rem; font-weight:600; margin:0; }
    .admin-topbar .back{ margin-left:auto; color:rgba(255,255,255,.7); font-size:.85rem; }
    .admin-topbar .back:hover{ color:var(--brand-gold); }
    .wrap{ max-width:880px; margin:0 auto; padding:28px 24px 64px; }
    .tabs{ display:flex; gap:6px; margin-bottom:24px; flex-wrap:wrap; }
    .tab{ padding:9px 16px; border-radius:9px; font-size:.86rem; font-weight:600; color:#5b6472; background:#fff; border:1px solid #e6e9f0; }
    .tab:hover{ border-color:var(--brand-sky); color:var(--brand-royal); }
    .tab.active{ background:var(--brand-royal); color:#fff; border-color:var(--brand-royal); }
    .flash{ padding:12px 16px; border-radius:10px; margin-bottom:22px; font-size:.9rem; font-weight:500; }
    .flash.success{ background:#e7f7ee; color:#11704a; border:1px solid #b6e6cd; }
    .flash.error{ background:#fdecec; color:#a4282b; border:1px solid #f3c0c1; }
    .panel{ background:#fff; border:1px solid #e6e9f0; border-radius:16px; padding:26px; margin-bottom:22px; }
    .panel h2{ font-family:var(--font-display, Georgia, serif); font-size:1.3rem; margin:0 0 4px; }
    .panel .hint{ color:#8a93a3; font-size:.85rem; margin:0 0 22px; }
    label{ display:block; font-size:.82rem; font-weight:600; color:#3a4250; margin:0 0 6px; }
    input[type=text], input[type=number], textarea, select{
      width:100%; padding:10px 12px; border:1px solid #d7dce6; border-radius:9px;
      font-size:.9rem; font-family:inherit; color:#101319; background:#fff; outline:none;
    }
    input:focus, textarea:focus, select:focus{ border-color:var(--brand-royal); box-shadow:0 0 0 3px rgba(7,89,248,.12); }
    .field{ margin-bottom:18px; }
    .row{ display:grid; grid-template-columns:1fr 140px; gap:14px; }
    .file-drop{
      border:1.5px dashed #c7cdda; border-radius:11px; padding:16px; background:#fafbfd;
      display:flex; align-items:center; gap:14px; font-size:.85rem; color:#5b6472;
    }
    .file-drop input{ font-size:.82rem; }
    .btn{ display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer;
      padding:11px 20px; border-radius:9px; font-size:.88rem; font-weight:600; transition:all .15s ease; }
    .btn-primary{ background:var(--brand-royal); color:#fff; } .btn-primary:hover{ background:#0546c8; }
    .btn-ghost{ background:#fff; color:#3a4250; border:1px solid #d7dce6; } .btn-ghost:hover{ border-color:var(--brand-sky); }
    .btn-danger{ background:#fff; color:#b9402f; border:1px solid #f0c5bf; } .btn-danger:hover{ background:#fdecec; }
    .logo-preview{ height:96px; border-radius:11px; border:1px solid #e6e9f0; background:#0b0d12;
      display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:18px; }
    .logo-preview img{ max-height:64px; max-width:70%; object-fit:contain; }
    .logo-preview .muted{ color:#6b7686; font-size:.85rem; }
    .slide{ display:grid; grid-template-columns:120px 1fr auto; gap:16px; align-items:center;
      border:1px solid #eceff4; border-radius:12px; padding:14px; margin-bottom:12px; }
    .slide img{ width:120px; height:74px; object-fit:cover; border-radius:8px; }
    .slide .meta{ display:flex; flex-direction:column; gap:8px; }
    .slide .inline{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .slide .inline input[type=text]{ flex:1; min-width:140px; }
    .slide .inline input[type=number]{ width:80px; }
    .chk{ display:flex; align-items:center; gap:6px; font-size:.82rem; font-weight:500; color:#3a4250; }
    .slide .actions{ display:flex; flex-direction:column; gap:8px; }
    .empty{ color:#8a93a3; font-size:.88rem; padding:8px 0; }
    .divider{ height:1px; background:#eceff4; margin:24px 0; }
  </style>
</head>
<body>

<div class="admin-topbar">
  <span class="dot"></span>
  <h1><?php echo htmlspecialchars($siteName); ?> · Settings</h1>
  <a class="back" href="index.php"><i class="fa-solid fa-arrow-left"></i> All settings</a>
</div>

<div class="wrap">

  <div class="tabs">
    <a class="tab <?php echo $section === 'logo' ? 'active' : ''; ?>"    href="edit.php?section=logo"><i class="fa-solid fa-image"></i> Branding</a>
    <a class="tab <?php echo $section === 'hero' ? 'active' : ''; ?>"    href="edit.php?section=hero"><i class="fa-solid fa-images"></i> Hero</a>
    <a class="tab <?php echo $section === 'headers' ? 'active' : ''; ?>" href="edit.php?section=headers"><i class="fa-solid fa-heading"></i> Page banners</a>
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
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
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
    <form class="panel" method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_hero">
      <h2>Add a hero slide</h2>
      <p class="hint">These images appear in the homepage hero slideshow. Landscape images (1920×1080) look best.</p>

      <div class="field">
        <label>Image</label>
        <div class="file-drop">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--brand-sky);"></i>
          <input type="file" name="image" accept="image/*" required>
        </div>
      </div>
      <div class="row">
        <div class="field" style="margin:0;">
          <label>Caption (optional)</label>
          <input type="text" name="caption" placeholder="e.g. Creative workspace">
        </div>
        <div class="field" style="margin:0;">
          <label>Order</label>
          <input type="number" name="sort_order" value="0">
        </div>
      </div>
      <div class="divider"></div>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Add slide</button>
    </form>

    <div class="panel">
      <h2>Current slides</h2>
      <p class="hint">Uncheck “Active” to hide a slide without deleting it.</p>
      <?php if (empty($heroSlides)): ?>
        <p class="empty">No slides yet. The homepage will use its built-in fallback images until you add some.</p>
      <?php else: ?>
        <?php foreach ($heroSlides as $s): ?>
          <div class="slide">
            <img src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" alt="">
            <form method="POST" class="meta">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="update_hero">
              <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
              <div class="inline">
                <input type="text" name="caption" value="<?php echo htmlspecialchars($s['caption'] ?? ''); ?>" placeholder="Caption">
                <input type="number" name="sort_order" value="<?php echo (int) $s['sort_order']; ?>" title="Order">
                <label class="chk"><input type="checkbox" name="is_active" <?php echo $s['is_active'] ? 'checked' : ''; ?>> Active</label>
              </div>
              <div>
                <button class="btn btn-ghost" type="submit"><i class="fa-solid fa-rotate"></i> Update</button>
              </div>
            </form>
            <div class="actions">
              <form method="POST" onsubmit="return confirm('Remove this slide?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_hero">
                <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                <button class="btn btn-danger" type="submit"><i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
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

</div>
</body>
</html>