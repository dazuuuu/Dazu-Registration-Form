<style>
    .notif-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px;
                  border-radius: 50%; color: #475569; border: 0; background: transparent; }
    .notif-bell:hover { background: #f1f5f9; color: #0D9488; }
    .notif-badge { position: absolute; top: 2px; right: 0; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
                   background: #dc2626; color: #fff; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center;
                   box-shadow: 0 0 0 2px #fff; }
    .notif-menu { width: min(360px, calc(100vw - 24px)); max-height: 70vh; overflow-y: auto; padding: 0; }
    .pwa-banner { background: #fff; border: 1px solid #99f6e4; border-left: 4px solid #0D9488; border-radius: 12px; padding: 16px 18px;
                  margin-bottom: 20px; box-shadow: 0 2px 10px rgba(13,148,136,.08); color: #0f172a; }
    .pwa-banner h5 { font-size: 1.05rem; margin: 0 0 2px; font-weight: 700; }
    .pwa-step { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-top: 1px dashed #e2e8f0; flex-wrap: wrap; }
    .pwa-step-icon { width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: #334155; font-weight: 700; font-size: 13px;
                     display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .pwa-step-done .pwa-step-icon { background: #0D9488; color: #fff; }
    .pwa-step-done [data-step-text] { color: #0f766e; }
    .pwa-step-body { flex: 1; min-width: 200px; font-size: .9rem; }
    .pwa-step .btn { white-space: nowrap; }
    .pwa-progress { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; margin-top: 6px; }
    .pwa-progress > div { height: 100%; width: 0; background: #0D9488; transition: width .2s; }
</style>
