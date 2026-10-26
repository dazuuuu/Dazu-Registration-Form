<?php
// public/templates/public/standalone.php — minimal layout for shareable / embeddable pages
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
$logoPath = url('assets/images/logo/logo.png');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($page_title ?? 'Dazu Training Institute'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_description ?? ''); ?>">
<?php
require_once __DIR__ . '/../../../app/v1/helpers/seo.php';
if (empty($og_image)) {
    $og_image = seo_public_preview_image($page_key ?? null);
}
echo seo_head_tags(
    (string) ($page_title ?? 'Dazu Training Institute'),
    (string) ($page_description ?? ''),
    $og_image ?? null
);
?>
<meta name="theme-color" content="#2E7D32">
<link rel="manifest" href="<?php echo url('manifest.php'); ?>">
<link rel="apple-touch-icon" href="<?php echo asset('icons/icon-192.png'); ?>">
<script>
(function(){ try { var t=localStorage.getItem('dh-theme'); if(t==='dark') document.documentElement.setAttribute('data-theme','dark'); } catch(e){} })();
</script>
<link rel="stylesheet" href="<?php echo asset('css/theme.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="dh-standalone">

<header class="dh-standalone-header">
    <div class="dh-container dh-standalone-header-inner">
        <a href="<?php echo url('/'); ?>" class="dh-brand" aria-label="Dazu Training Institute">
            <img src="<?php echo $logoPath; ?>" alt="Dazu Training Institute" class="dh-brand-logo" height="40"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
            <span class="dh-brand-name" style="display:none;">Dazu <strong>Training Institute</strong></span>
        </a>
        <div class="dh-standalone-actions">
            <button class="dh-theme-btn" id="dhThemeToggle" aria-label="Toggle theme">
                <i class="fas fa-moon dh-icon-moon"></i>
                <i class="fas fa-sun dh-icon-sun"></i>
            </button>
            <a href="<?php echo url('courses/index.php'); ?>" class="dh-nav-link">All courses</a>
        </div>
    </div>
</header>

<main id="main-content" class="dh-standalone-main">
    <?php echo $content ?? ''; ?>
</main>

<footer class="dh-standalone-footer">
    <div class="dh-container">
        <small>&copy; <?php echo date('Y'); ?> Dazu Training Institute · <a href="<?php echo url('/'); ?>">dazuhub.ac.ke</a></small>
    </div>
</footer>

<script>
(function(){
    var btn = document.getElementById('dhThemeToggle');
    if(btn) btn.addEventListener('click', function(){
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.documentElement.setAttribute('data-theme', dark ? 'light' : 'dark');
        try { localStorage.setItem('dh-theme', dark ? 'light' : 'dark'); } catch(e){}
    });
    var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('visible'); io.unobserve(e.target); }});
    }, { threshold: 0.1 });
    document.querySelectorAll('.dh-reveal').forEach(function(el){ io.observe(el); });
})();
</script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
