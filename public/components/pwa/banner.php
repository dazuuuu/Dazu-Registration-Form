<?php
// Install banner for a portal dashboard. Expects $pwaPortal. Driven by assets/js/portal-pwa.js (hidden until it decides).
require_once __DIR__ . '/portals.php';
$pwaInfo = pwa_portal($pwaPortal);
?>
<div class="pwa-banner d-none" data-pwa-banner>
    <div class="d-flex align-items-start gap-3 mb-2">
        <img src="<?php echo url('assets/icons/icon-192.png'); ?>" alt="" style="width:44px;height:44px;border-radius:11px">
        <div class="flex-grow-1">
            <h5>Install the <?php echo htmlspecialchars($pwaInfo['name']); ?> app</h5>
            <div class="small text-muted">Put the portal on your phone or computer and <?php echo htmlspecialchars($pwaInfo['pitch']); ?>.</div>
        </div>
        <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none" data-pwa-later>Later</button>
    </div>

    <div class="pwa-step" data-pwa-step="install" data-step-no="1">
        <span class="pwa-step-icon">1</span>
        <div class="pwa-step-body">
            <strong data-step-text>Install the app on this device</strong>
            <div class="small text-muted d-none mt-1" data-pwa-install-help></div>
        </div>
        <button type="button" class="btn btn-sm btn-success" data-pwa-install><i class="fas fa-download me-1"></i>Install app</button>
    </div>

    <div class="pwa-step" data-pwa-step="resources" data-step-no="2">
        <span class="pwa-step-icon">2</span>
        <div class="pwa-step-body">
            <strong data-step-text>Save the app's resources on this device</strong>
            <div class="small text-muted">Its styles, fonts and icons, so it opens quickly even on a weak connection. Your data always stays online.</div>
            <div class="pwa-progress d-none"><div data-pwa-progress></div></div>
            <div class="small text-muted" data-pwa-progress-label></div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-success" data-pwa-save><i class="fas fa-cloud-download-alt me-1"></i>Save now</button>
    </div>

    <?php if ($pwaInfo['alerts']): ?>
    <div class="pwa-step" data-pwa-step="alerts" data-step-no="3">
        <span class="pwa-step-icon">3</span>
        <div class="pwa-step-body">
            <strong data-step-text>Turn on alerts</strong>
            <div class="small text-muted">Get a notification on this device the moment something new arrives.</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-success" data-pwa-enable-push><i class="fas fa-bell me-1"></i>Turn on alerts</button>
    </div>
    <?php endif; ?>
</div>
