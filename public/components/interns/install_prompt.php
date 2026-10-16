<?php
// First-time install screen for the intern app on phones (design: Dazu_Intern_PWA_Install_Prompt).
// Shown by assets/js/intern-app.js under 768px when the app is not installed yet; "Not now" hides it for 14 days.
// The Install button is wired by assets/js/portal-pwa.js ([data-pwa-install]).
?>
<div class="ipi" id="ipInstall" hidden role="dialog" aria-modal="true" aria-labelledby="ipiT">
    <div class="ipi-in">
        <div class="ipi-logo"><img src="<?php echo htmlspecialchars(brand_url('logo')); ?>" alt="Dazu Training Institute"></div>
        <h2 id="ipiT">Dazu Intern Portal</h2>
        <p>Install the app on your phone. Clock in, see tasks and get alerts, even on slow internet.</p>
        <div class="ipi-card">
            <div class="d-flex align-items-center gap-3 mb-3">
                <img src="<?php echo url('assets/icons/icon-192.png'); ?>" alt="" style="width:52px;height:52px;border-radius:14px;border:1px solid #eceee9">
                <div class="text-start"><div class="fw-semibold">Add Dazu Intern to Home screen</div><div class="ip-muted"><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? ''); ?></div></div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn flex-fill" style="background:#efeeea;font-weight:600" data-ipi-later>Not now</button>
                <button type="button" class="btn btn-success flex-fill" style="font-weight:600" data-pwa-install data-ipi-install>Install</button>
            </div>
            <div class="small text-muted mt-2 d-none" data-pwa-install-help></div>
        </div>
        <div class="ip-muted mt-3">Works offline · Push alerts for tasks and deadlines</div>
    </div>
</div>
<style>
.ipi{position:fixed;inset:0;z-index:2100;background:linear-gradient(180deg,#fff 55%,#eef7e8);display:flex;align-items:center;justify-content:center;padding:24px;text-align:center}
.ipi[hidden]{display:none}
.ipi-in{max-width:420px;width:100%}
.ipi-logo{width:150px;height:150px;border-radius:34px;background:#fff;box-shadow:0 14px 40px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 22px}
.ipi-logo img{width:118px;object-fit:contain}
.ipi h2{font-size:1.55rem;font-weight:700}
.ipi p{color:#5f6460;margin-bottom:20px}
.ipi-card{background:#fff;border-radius:18px;box-shadow:0 8px 26px rgba(0,0,0,.08);padding:18px}
</style>
