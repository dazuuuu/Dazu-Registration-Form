<?php
// public/templates/secretary/layout.php — the secretary portal frame: desktop sidebar + top bar, phone header + bottom bar, installable app (PWA).
// Pages call secretary_require(<switch>) FIRST (the server check); this file only draws. Pages set $page_title, $content and optionally $sp_active (menu key), $sp_subtitle, $sec.
// All class names start with sp- so they never clash with the Staff Calendar page (sc-).
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';   // csrf_token()
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$spDb = \Models\Connection::getInstance()->getConnection();
if (empty($sec) || !isset($sec['switches'])) {
    $sec = secretary_by_user($spDb, (int) ($_SESSION['user_id'] ?? 0)) ?: ['id' => 0, 'full_name' => '', 'status' => 'active'];
    $sec['switches'] = $sec['id'] ? secretary_switches($spDb, (int) $sec['id']) : [];
}
$spMenu = secretary_menu($sec['switches']);
$spName = (string) ($sec['full_name'] ?? '');
$spInit = secretary_initials($spName);
$spLeave = ($sec['status'] ?? '') === 'on_leave';
if (!isset($sp_active)) {
    $dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
    $sp_active = ['dashboard' => 'dashboard', 'blogs' => 'blogs', 'enquiries' => 'enquiries', 'tasks' => 'tasks', 'room' => 'room_visitors', 'staffcalendar' => 'staff_calendar', 'idcard' => 'id_card', 'notifications' => 'notifications'][$dir] ?? 'dashboard';
}
$spTitle = (string) ($page_title ?? 'Secretary');
$spBottom = array_values(array_filter($spMenu, fn($i) => $i[4] !== null));
$spIcon = static fn(string $f): string => url('assets/icons/' . $f);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo $h($spTitle); ?> — Dazu Secretary</title>
    <link rel="manifest" href="<?php echo $h(url('secretary/manifest.php')); ?>">
    <meta name="theme-color" content="#048C44">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Dazu Secretary">
    <link rel="apple-touch-icon" href="<?php echo $h($spIcon('secretary-180.png')); ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $h($spIcon('secretary-192.png')); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --g:#048C44; --gd:#037A3B; --gl:#E3F3EA; --ink:#1b1f1d; --mut:#6b7570; --bg:#f4f5f2; --line:#e4e8e3; --deep:#0f3d24; }
        * { box-sizing: border-box; }
        body.sp-body { margin:0; background:var(--bg); color:var(--ink); font-family:'Poppins',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
        a { text-decoration:none; }
        /* ---------- desktop: sidebar + content ---------- */
        .sp-side { position:fixed; inset:0 auto 0 0; width:260px; background:#fff; border-right:1px solid var(--line); display:flex; flex-direction:column; padding:18px 14px; z-index:30; overflow-y:auto; }
        .sp-logo { display:block; padding:2px 4px 12px; } .sp-logo img { height:46px; width:auto; display:block; }
        .sp-pill { display:inline-flex; align-items:center; gap:8px; background:var(--gl); color:var(--gd); font-size:.68rem; font-weight:800; letter-spacing:.12em; border-radius:99px; padding:8px 14px; margin:0 0 14px; }
        .sp-pill i { width:7px; height:7px; border-radius:50%; background:var(--g); display:inline-block; }
        .sp-nav { display:flex; flex-direction:column; gap:4px; }
        .sp-nav a { display:flex; align-items:center; gap:14px; min-height:46px; padding:0 14px; border-radius:14px; color:#2b332f; font-weight:500; font-size:.95rem; }
        .sp-nav a i { width:20px; text-align:center; color:#56605b; }
        .sp-nav a:hover { background:var(--gl); }
        .sp-nav a.on { background:var(--g); color:#fff; box-shadow:0 8px 18px rgba(4,140,68,.28); } .sp-nav a.on i { color:#fff; }
        .sp-nav .sp-grp { font-size:.68rem; font-weight:800; letter-spacing:.14em; color:#8b948f; margin:18px 14px 6px; }
        .sp-count { margin-left:auto; background:#d92d20; color:#fff; border-radius:99px; font-size:.68rem; font-weight:800; padding:1px 7px; }
        .sp-side-foot { margin-top:auto; padding-top:16px; display:grid; gap:8px; }
        .sp-wrap { margin-left:260px; min-height:100vh; }
        .sp-top { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:22px 32px 6px; }
        .sp-top h1 { font-size:1.35rem; font-weight:800; margin:0; } .sp-top p { margin:2px 0 0; color:var(--mut); font-size:.95rem; }
        .sp-top-r { display:flex; align-items:center; gap:12px; }
        .sp-bell { position:relative; width:48px; height:48px; border-radius:14px; background:#fff; display:flex; align-items:center; justify-content:center; color:#2b332f; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .sp-bell .sp-count { position:absolute; top:-5px; right:-5px; margin:0; }
        .sp-av { width:48px; height:48px; border-radius:50%; background:#2b2f2d; color:#fff; font-weight:800; display:flex; align-items:center; justify-content:center; letter-spacing:.02em; flex:0 0 auto; }
        .sp-who { display:flex; align-items:center; gap:10px; } .sp-who b { display:block; font-size:.9rem; line-height:1.2; }
        .sp-badge-leave { display:inline-block; background:#fff4d6; color:#8a6100; font-size:.68rem; font-weight:800; padding:2px 9px; border-radius:99px; margin-left:6px; vertical-align:middle; white-space:nowrap; }
        .sp-main { padding:14px 32px 56px; }
        .sp-mhead, .sp-bottom { display:none; }
        .sp-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 16px; border-radius:12px; border:1px solid var(--g); background:#fff; color:var(--gd); font-weight:700; font-size:.9rem; cursor:pointer; }
        .sp-btn:hover { background:var(--gl); } .sp-btn.solid { background:var(--g); color:#fff; } .sp-btn.solid:hover { background:var(--gd); }
        .sp-btn.danger { border-color:#d92d20; color:#b42318; } .sp-btn[hidden] { display:none; }
        /* ---------- the dashboard ---------- */
        .sp-hero { position:relative; overflow:hidden; border-radius:26px; background:linear-gradient(120deg,var(--deep),#0b2c1a); color:#fff; padding:34px 40px; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
        .sp-hero::after { content:''; position:absolute; right:0; top:0; bottom:0; width:190px; background:var(--g); clip-path:polygon(100% 0, 100% 100%, 0 100%); opacity:.95; }
        .sp-hero > * { position:relative; z-index:1; }
        .sp-hero h2 { font-size:1.9rem; font-weight:800; margin:0 0 6px; } .sp-hero p { margin:0; color:#cfe3d7; font-size:1.02rem; }
        .sp-stats { display:flex; gap:14px; flex-wrap:wrap; padding-right:60px; }
        .sp-stat { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.12); border-radius:16px; padding:14px 18px; min-width:150px; }
        .sp-stat small { display:block; font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; color:#cfe3d7; } .sp-stat b { display:block; font-size:1.8rem; line-height:1.15; margin-top:4px; }
        .sp-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:24px; }
        .sp-box { background:#fff; border-radius:24px; padding:22px 24px; min-width:0; }
        .sp-box-h { display:flex; align-items:center; justify-content:space-between; gap:10px; border-bottom:1px solid var(--line); padding-bottom:12px; margin-bottom:6px; }
        .sp-box-h h3 { font-size:1.1rem; font-weight:800; margin:0; } .sp-box-h span { color:var(--mut); font-size:.88rem; }
        .sp-row { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 0; border-bottom:1px solid var(--line); } .sp-row:last-child { border-bottom:0; }
        .sp-row b { display:block; font-weight:600; } .sp-row small { color:var(--mut); }
        .sp-chip { font-size:.78rem; font-weight:800; padding:5px 12px; border-radius:99px; background:#eceeea; color:#4a524e; white-space:nowrap; }
        .sp-chip.today, .sp-chip.pending { background:#fff4d6; color:#8a6100; } .sp-chip.done { background:var(--gl); color:var(--gd); }
        .sp-empty { text-align:center; padding:26px 8px; color:var(--mut); } .sp-empty i { display:block; font-size:1.6rem; color:#b9c7be; margin-bottom:8px; } .sp-empty b { display:block; color:#3b443f; }
        .sp-next h4 { font-size:1.35rem; font-weight:800; margin:14px 0 6px; } .sp-next p { margin:0; color:var(--mut); }
        .sp-note { margin-top:24px; border:1.5px dashed #cfd8d2; border-radius:20px; padding:20px 24px; color:#555e59; font-size:.92rem; }
        .sp-soon { background:#fff; border-radius:24px; padding:36px 24px; text-align:center; } .sp-soon i { font-size:2rem; color:var(--g); margin-bottom:10px; }
        /* ---------- phones: dark header, one column, bottom bar ---------- */
        @media (max-width: 991.98px) {
            .sp-side, .sp-top { display:none; }
            .sp-wrap { margin-left:0; padding-bottom:calc(76px + env(safe-area-inset-bottom)); }
            .sp-mhead { display:flex; align-items:center; justify-content:space-between; gap:10px; background:var(--deep); color:#fff;
                padding:calc(12px + env(safe-area-inset-top)) calc(16px + env(safe-area-inset-right)) 12px calc(16px + env(safe-area-inset-left)); position:sticky; top:0; z-index:20; }
            .sp-mhead .sp-chiplogo { background:#fff; border-radius:12px; padding:6px 10px; display:flex; } .sp-mhead .sp-chiplogo img { height:30px; width:auto; display:block; }
            .sp-mhead .sp-r { display:flex; align-items:center; gap:10px; } .sp-mhead .sp-av { background:var(--g); width:42px; height:42px; }
            .sp-mhead .sp-ibtn { width:42px; height:42px; border-radius:12px; background:rgba(255,255,255,.12); color:#fff; display:flex; align-items:center; justify-content:center; border:0; position:relative; }
            .sp-mhead .sp-ibtn .sp-count { position:absolute; top:-4px; right:-4px; margin:0; }
            .sp-main { padding:14px calc(14px + env(safe-area-inset-right)) 24px calc(14px + env(safe-area-inset-left)); }
            .sp-hero { border-radius:22px; padding:22px 20px; } .sp-hero::after { width:110px; } .sp-hero h2 { font-size:1.45rem; }
            .sp-stats { width:100%; padding-right:0; display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; } .sp-stat { min-width:0; padding:10px 12px; } .sp-stat b { font-size:1.4rem; }
            .sp-grid { grid-template-columns:1fr; gap:16px; margin-top:16px; } .sp-box { border-radius:20px; padding:18px; }
            .sp-note { font-size:.85rem; padding:16px; }
            .sp-bottom { display:grid; grid-auto-flow:column; grid-auto-columns:1fr; position:fixed; left:0; right:0; bottom:0; z-index:40; background:#fff; border-top:1px solid var(--line);
                padding:6px calc(6px + env(safe-area-inset-right)) calc(6px + env(safe-area-inset-bottom)) calc(6px + env(safe-area-inset-left)); box-shadow:0 -4px 16px rgba(0,0,0,.06); }
            .sp-bottom a { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; min-height:56px; border-radius:14px; color:#56605b; font-size:.7rem; font-weight:700; }
            .sp-bottom a i { font-size:1.15rem; } .sp-bottom a.on { color:var(--g); background:var(--gl); }
        }
        @media (display-mode: standalone) { .sp-side-foot [data-sp-install], .sp-mhead [data-sp-install] { display:none !important; } }
        @media (prefers-reduced-motion: reduce) { * { transition:none !important; } }
        .sp-nav a:focus-visible, .sp-btn:focus-visible, .sp-bottom a:focus-visible { outline:3px solid rgba(4,140,68,.45); outline-offset:2px; }
    </style>
</head>
<body class="sp-body">
<aside class="sp-side" aria-label="Menu">
    <a class="sp-logo" href="<?php echo $h(url('secretary/dashboard/index.php')); ?>"><img src="<?php echo $h(url('assets/images/logo/logo.png')); ?>" alt="Dazu Training Institute"></a>
    <div class="sp-pill"><i></i> SECRETARY PORTAL</div>
    <nav class="sp-nav">
        <?php $grp = 'main'; foreach ($spMenu as [$k, $label, $ico, $path, $bl, $g]):
            if ($g !== $grp): $grp = $g; ?><div class="sp-grp">ACCOUNT</div><?php endif; ?>
            <a href="<?php echo $h(url($path)); ?>" class="<?php echo $sp_active === $k ? 'on' : ''; ?>"<?php echo $sp_active === $k ? ' aria-current="page"' : ''; ?>><i class="fas <?php echo $h($ico); ?>"></i><span><?php echo $h($label); ?></span><?php if ($k === 'notifications'): ?><span class="sp-count d-none" data-notif-count>0</span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sp-side-foot">
        <button type="button" class="sp-btn solid" data-sp-install hidden><i class="fas fa-download"></i> Install app</button>
        <a class="sp-btn danger" href="<?php echo $h(url('auth/logout.php')); ?>"><i class="fas fa-right-from-bracket"></i> Sign out</a>
    </div>
</aside>

<div class="sp-wrap">
    <header class="sp-mhead">
        <a class="sp-chiplogo" href="<?php echo $h(url('secretary/dashboard/index.php')); ?>"><img src="<?php echo $h(url('assets/images/logo/logo.png')); ?>" alt="Dazu"></a>
        <div class="sp-r">
            <button type="button" class="sp-ibtn" data-sp-install hidden aria-label="Install app"><i class="fas fa-download"></i></button>
            <?php if ($spLeave): ?><span class="sp-badge-leave">On leave</span><?php endif; ?>
            <a class="sp-ibtn" href="<?php echo $h(url('secretary/notifications/index.php')); ?>" aria-label="Notifications"><i class="fas fa-bell"></i><span class="sp-count d-none" data-notif-count>0</span></a>
            <div class="sp-av" aria-label="<?php echo $h($spName); ?>"><?php echo $h($spInit); ?></div>
        </div>
    </header>
    <header class="sp-top">
        <div><h1><?php echo $h($spTitle); ?></h1><p><?php echo $h($sp_subtitle ?? 'Secretary portal'); ?></p></div>
        <div class="sp-top-r">
            <a class="sp-bell" href="<?php echo $h(url('secretary/notifications/index.php')); ?>" aria-label="Notifications"><i class="far fa-bell"></i><span class="sp-count d-none" data-notif-count>0</span></a>
            <div class="sp-who"><div class="sp-av"><?php echo $h($spInit); ?></div><div><b><?php echo $h($spName); ?><?php if ($spLeave): ?><span class="sp-badge-leave">On leave</span><?php endif; ?></b></div></div>
        </div>
    </header>
    <main class="sp-main">
        <?php
        require_once __DIR__ . '/../../../app/v1/models/Session.php';
        foreach (['success' => 'alert-success', 'error' => 'alert-danger', 'warning' => 'alert-warning'] as $fk => $fc) {
            if ($f = SessionModel::getFlash($fk)) echo '<div class="alert ' . $fc . '" role="status">' . $f . '</div>';
        }
        echo $content;
        ?>
    </main>
</div>

<?php if (count($spBottom) > 1): ?>
<nav class="sp-bottom" aria-label="Quick menu">
    <?php foreach ($spBottom as [$k, $label, $ico, $path, $bl]): ?>
        <a href="<?php echo $h(url($path)); ?>" class="<?php echo $sp_active === $k ? 'on' : ''; ?>"<?php echo $sp_active === $k ? ' aria-current="page"' : ''; ?>><i class="fas <?php echo $h($ico); ?>"></i><span><?php echo $h($bl); ?></span></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<script>
// "Install app": the button only shows when this browser says the app can be installed (and never once it is installed).
(function () {
  var ev = null, btns = document.querySelectorAll('[data-sp-install]');
  function show(on) { btns.forEach(function (b) { b.hidden = !on; }); }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; show(true); });
  window.addEventListener('appinstalled', function () { ev = null; show(false); });
  btns.forEach(function (b) { b.addEventListener('click', function () {
    if (!ev) return; var p = ev; ev = null; show(false); p.prompt(); p.userChoice.catch(function () {});
  }); });
})();
</script>
<script src="<?php echo $h(url('assets/js/portal-pwa.js')); ?>?v=2" defer
        data-portal="secretary"
        data-sw="<?php echo $h(url('secretary/sw.js')); ?>"
        data-scope="<?php echo $h(url('secretary/')); ?>"
        data-api="<?php echo $h(url('secretary/notifications/api.php')); ?>"
        data-list="<?php echo $h(url('secretary/notifications/index.php')); ?>"
        data-csrf="<?php echo $h(csrf_token()); ?>"
        data-preview="0"></script>
</body>
</html>
