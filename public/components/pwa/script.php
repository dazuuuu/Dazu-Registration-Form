<?php // Loads the portal app script. Expects $pwaPortal. Notifications are wired up only for portals that have them.
require_once __DIR__ . '/portals.php';
$pwaInfo = pwa_portal($pwaPortal);
?>
<script src="<?php echo url('assets/js/portal-pwa.js'); ?>?v=2" defer
        data-portal="<?php echo htmlspecialchars($pwaPortal); ?>"
        data-sw="<?php echo url($pwaPortal . '/sw.js'); ?>"
        data-scope="<?php echo url($pwaPortal . '/'); ?>"
<?php if ($pwaInfo['alerts']): ?>
        data-api="<?php echo url($pwaPortal . '/notifications/api.php'); ?>"
        data-list="<?php echo url($pwaPortal . '/notifications/index.php'); ?>"
        data-csrf="<?php echo htmlspecialchars(csrf_token()); ?>"
<?php endif; ?>
        data-preview="<?php echo !empty($_SESSION['preview']) ? '1' : '0'; ?>"></script>
