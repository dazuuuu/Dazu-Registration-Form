<?php // Installable-app tags for a portal layout <head>. Expects $pwaPortal ('tutor', 'students', 'admin', 'employee').
require_once __DIR__ . '/portals.php';
$pwaInfo = pwa_portal($pwaPortal);
?>
<link rel="manifest" href="<?php echo url($pwaPortal . '/manifest.php'); ?>">
<meta name="theme-color" content="#44A024">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($pwaInfo['name']); ?>">
<?php $pwaIcon = in_array($pwaPortal, ['students', 'tutor'], true) ? $pwaPortal : 'favicon'; ?>
<link rel="apple-touch-icon" href="<?php echo url('assets/icons/' . $pwaIcon . '-180.png'); ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo url('assets/icons/' . $pwaIcon . '-192.png'); ?>">
<?php require __DIR__ . '/styles.php'; ?>
