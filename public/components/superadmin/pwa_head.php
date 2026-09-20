<?php // PWA + mobile meta for every superadmin page (dashboard pages and the login page). ?>
<link rel="manifest" href="<?php echo url('superadmin/manifest.php'); ?>">
<meta name="theme-color" content="#1b1c30">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="DazuHub Admin">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="<?php echo asset('superadmin/icon.svg'); ?>">
<link rel="apple-touch-icon" href="<?php echo asset('superadmin/icon-180.png'); ?>">
<script>window.SA_SW = <?php echo json_encode(['sw' => url('superadmin/sw.js'), 'scope' => url('superadmin/')], JSON_UNESCAPED_SLASHES); ?>;</script>
