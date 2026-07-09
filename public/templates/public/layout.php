<?php
// public/templates/public/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/contact.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($page_title ?? 'Dazu Hub Tech Skills Institute'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Practical tech training in web development, cybersecurity, data analytics, and AI. Nairobi, Kenya.'); ?>">
<meta name="theme-color" content="#00A859">
<link rel="manifest" href="<?php echo url('manifest.php'); ?>">
<link rel="apple-touch-icon" href="<?php echo asset('icons/icon-192.png'); ?>">

<script>
(function(){
    try {
        var t = localStorage.getItem('dh-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    } catch(e){}
})();
</script>

<link rel="stylesheet" href="<?php echo asset('css/theme.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/mockup-nav-footer.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/public-pages.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/apply-modal.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
<?php include __DIR__ . '/../../components/public/navbar.php'; ?>

<main id="main-content">
    <?php echo $content ?? ''; ?>
</main>

<?php include __DIR__ . '/../../components/public/footer.php'; ?>

<?php if (empty($skip_apply_modal)): ?>
    <?php include __DIR__ . '/../../components/public/apply_modal_bootstrap.php'; ?>
<?php endif; ?>

<script>window.DH_APPLY_URL = <?php echo json_encode(url('apply/index.php')); ?>;</script>
<script src="<?php echo asset('js/apply-form.js'); ?>"></script>

<a href="<?php echo htmlspecialchars(whatsapp_chat_url('Hi Dazu Hub Institute, I have a question.')); ?>"
   class="dh-wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"
   title="WhatsApp <?php echo htmlspecialchars(site_whatsapp_display()); ?>">
    <i class="fab fa-whatsapp"></i>
</a>
<style>
.dh-wa-float {
    position: fixed; right: 20px; bottom: 20px; z-index: 999;
    width: 52px; height: 52px; border-radius: 50%;
    background: #25d366; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; text-decoration: none;
    box-shadow: 0 4px 16px rgba(37,211,102,.45);
    transition: transform .2s, box-shadow .2s;
}
.dh-wa-float:hover { transform: scale(1.06); color: #fff; box-shadow: 0 6px 22px rgba(37,211,102,.55); }
</style>

<script>
(function(){
    var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if(e.isIntersecting){
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.dh-reveal, .dh-reveal-left, .dh-reveal-right, .reveal')
            .forEach(function(el){ io.observe(el); });
})();
</script>

<?php if (isset($extra_js)) echo $extra_js; ?>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?php echo url('sw.js'); ?>').catch(function () {});
  });
}
</script>
</body>
</html>
