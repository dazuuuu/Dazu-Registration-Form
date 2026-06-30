<?php
// public/admin/settings/index.php
// Settings overview: branding/logo, hero slides, and page headers.

require_once __DIR__ . '/../../../app/helpers/uploads.php';        // BASE_URL + helpers
require_once __DIR__ . '/../../../app/models/SettingModel.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';      // provides $pdo

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

$settings    = new SettingModel($pdo);
$logoUrl     = settings_asset_url($settings->get('logo_path'));
$siteName    = $settings->get('site_name', 'Prime Investment');
$heroSlides  = $settings->getHeroSlides(false);   // include inactive in admin
$pageHeaders = $settings->getAllPageHeaders();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Settings — Admin</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{
      --brand-sky:#00A1F3; --brand-royal:#0759F8; --brand-gold:#EBA94E;
      --brand-white:#FFFFFF; --brand-black:#000000;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0; background:#f5f7fb; color:#101319;
      font-family:var(--font-body, system-ui, -apple-system, sans-serif);
    }
    a{ text-decoration:none; }
    .admin-topbar{
      display:flex; align-items:center; gap:14px;
      background:var(--brand-black); color:#fff;
      padding:14px 28px;
    }
    .admin-topbar .dot{ width:12px;height:12px;border-radius:3px;background:var(--brand-sky); }
    .admin-topbar h1{ font-size:1rem; font-weight:600; margin:0; letter-spacing:.02em; }
    .admin-topbar .back{ margin-left:auto; color:rgba(255,255,255,.7); font-size:.85rem; }
    .admin-topbar .back:hover{ color:var(--brand-gold); }
    .wrap{ max-width:1040px; margin:0 auto; padding:32px 24px 64px; }
    .page-head{ margin-bottom:28px; }
    .page-head h2{ font-family:var(--font-display, Georgia, serif); font-size:1.9rem; margin:0 0 6px; }
    .page-head p{ color:#5b6472; margin:0; font-size:.92rem; }
    .flash{ padding:12px 16px; border-radius:10px; margin-bottom:22px; font-size:.9rem; font-weight:500; }
    .flash.success{ background:#e7f7ee; color:#11704a; border:1px solid #b6e6cd; }
    .flash.error{ background:#fdecec; color:#a4282b; border:1px solid #f3c0c1; }
    .cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; }
    .card{
      background:#fff; border:1px solid #e6e9f0; border-radius:16px;
      padding:24px; display:flex; flex-direction:column; gap:16px;
    }
    .card-top{ display:flex; align-items:center; gap:12px; }
    .card-icon{
      width:44px;height:44px;border-radius:11px; display:flex;align-items:center;justify-content:center;
      color:#fff; font-size:1.05rem; flex-shrink:0;
    }
    .ic-logo{ background:linear-gradient(135deg,var(--brand-royal),var(--brand-sky)); }
    .ic-hero{ background:linear-gradient(135deg,var(--brand-sky),#36c0ff); }
    .ic-head{ background:linear-gradient(135deg,var(--brand-gold),#f6c074); }
    .card-top h3{ margin:0; font-size:1.05rem; font-weight:700; }
    .card-top span{ display:block; font-size:.8rem; color:#8a93a3; font-weight:500; }
    .logo-preview{
      height:84px; border-radius:10px; border:1px dashed #d7dce6; background:#fafbfd;
      display:flex; align-items:center; justify-content:center; overflow:hidden;
    }
    .logo-preview img{ max-height:60px; max-width:80%; object-fit:contain; }
    .logo-preview .muted{ color:#9aa3b2; font-size:.85rem; }
    .thumbs{ display:flex; gap:8px; flex-wrap:wrap; }
    .thumbs img{ width:62px;height:42px;object-fit:cover;border-radius:7px;border:1px solid #e6e9f0; }
    .hdr-list{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px; }
    .hdr-list li{ display:flex; align-items:center; gap:10px; font-size:.88rem; }
    .hdr-list .key{ font-weight:600; text-transform:capitalize; }
    .hdr-list .ok{ color:#11704a; } .hdr-list .no{ color:#b9402f; }
    .hdr-list .pill{ margin-left:auto; font-size:.72rem; padding:2px 9px; border-radius:20px; background:#eef1f7; color:#5b6472; }
    .card .btn{
      margin-top:auto; align-self:flex-start;
      display:inline-flex; align-items:center; gap:8px;
      background:var(--brand-royal); color:#fff; border:none;
      padding:10px 18px; border-radius:9px; font-size:.86rem; font-weight:600; cursor:pointer;
      transition:background .15s ease, transform .15s ease;
    }
    .card .btn:hover{ background:#0546c8; transform:translateY(-1px); }
    .count{ font-family:var(--font-display, Georgia, serif); font-size:1.6rem; font-weight:800; }
  </style>
</head>
<body>

<div class="admin-topbar">
  <span class="dot"></span>
  <h1><?php echo htmlspecialchars($siteName); ?> · Settings</h1>
  <a class="back" href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
</div>

<div class="wrap">
  <div class="page-head">
    <h2>Site Settings</h2>
    <p>Manage your logo, homepage hero images, and the banner shown on each public page.</p>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
      <?php echo htmlspecialchars($flash['msg']); ?>
    </div>
  <?php endif; ?>

  <div class="cards">

    <!-- Branding / logo -->
    <div class="card">
      <div class="card-top">
        <div class="card-icon ic-logo"><i class="fa-solid fa-image"></i></div>
        <div><h3>Branding</h3><span>Logo &amp; site name</span></div>
      </div>
      <div class="logo-preview">
        <?php if ($logoUrl): ?>
          <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Current logo">
        <?php else: ?>
          <span class="muted">No logo uploaded yet</span>
        <?php endif; ?>
      </div>
      <a class="btn" href="edit.php?section=logo"><i class="fa-solid fa-pen"></i> Edit branding</a>
    </div>

    <!-- Hero slides -->
    <div class="card">
      <div class="card-top">
        <div class="card-icon ic-hero"><i class="fa-solid fa-images"></i></div>
        <div><h3>Homepage Hero</h3><span><?php echo count($heroSlides); ?> slide<?php echo count($heroSlides) === 1 ? '' : 's'; ?></span></div>
      </div>
      <?php if ($heroSlides): ?>
        <div class="thumbs">
          <?php foreach (array_slice($heroSlides, 0, 6) as $s): ?>
            <img src="<?php echo htmlspecialchars(settings_asset_url($s['image_path'])); ?>" alt="">
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color:#8a93a3;font-size:.86rem;margin:0;">No slides yet — the homepage will use its built-in fallback images.</p>
      <?php endif; ?>
      <a class="btn" href="edit.php?section=hero"><i class="fa-solid fa-sliders"></i> Manage slides</a>
    </div>

    <!-- Page headers -->
    <div class="card">
      <div class="card-top">
        <div class="card-icon ic-head"><i class="fa-solid fa-heading"></i></div>
        <div><h3>Page Banners</h3><span><?php echo count($pageHeaders); ?> page<?php echo count($pageHeaders) === 1 ? '' : 's'; ?></span></div>
      </div>
      <ul class="hdr-list">
        <?php foreach ($pageHeaders as $h): ?>
          <li>
            <span class="key"><?php echo htmlspecialchars($h['page_key']); ?></span>
            <?php if (!empty($h['image_path'])): ?>
              <span class="pill"><i class="fa-solid fa-check ok"></i> image set</span>
            <?php else: ?>
              <span class="pill"><i class="fa-solid fa-xmark no"></i> no image</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="btn" href="edit.php?section=headers"><i class="fa-solid fa-pen"></i> Edit banners</a>
    </div>

  </div>
</div>

</body>
</html>