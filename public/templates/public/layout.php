<?php
// public/templates/public/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($page_title ?? 'Dazu Hub Institute'); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Tech training institute offering Python, PHP, Node.js, Frontend and AI courses in Nairobi, Kenya.'); ?>">
<meta name="theme-color" content="#2E7D32">
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>
<?php include __DIR__ . '/../../components/public/navbar.php'; ?>

<main id="main-content">
    <?php echo $content ?? ''; ?>
</main>

<?php include __DIR__ . '/../../components/public/footer.php'; ?>

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
