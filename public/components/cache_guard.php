<?php
// Cache guard for admin / superadmin pages.
// Shows a small banner offering to clear caches when (a) the site was updated since this browser last cleared,
// or (b) the browser holds a lot of cached data for this site. "Clear cache" calls admin/cache/clear.php
// (PHP code cache + server cache files + Clear-Site-Data), then deletes Cache Storage and service workers here.
// window.dazuClearCache() can also be called from any button (e.g. Settings → Clear cache).
if (!function_exists('csrf_token')) {
    require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
}
$cacheVersion = '';
try {
    require_once __DIR__ . '/../../app/v1/models/SettingModel.php';
    $cacheVersion = (string) (new SettingModel($pdo ?? \Models\Connection::getInstance()->getConnection()))->get('cache_version', '');
} catch (Throwable $e) {
}
// Site version = admin's last "clear cache" + modification times of core files (changes on every upload)
$siteVersion = substr(md5($cacheVersion . '|' . implode('|', array_map(static fn($f) => @filemtime($f) ?: 0, [
    APP_V1_ROOT . '/config/paths.php', APP_V1_ROOT . '/bootstrap.php', PUBLIC_ROOT . '/.htaccess', PUBLIC_ROOT . '/sw.js',
    __FILE__, PUBLIC_ROOT . '/templates/admin/layout.php',
]))), 0, 12);
?>
<style>
#dzCache{position:fixed;right:16px;bottom:16px;z-index:2000;max-width:360px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.15);padding:14px 16px;font-size:13px;line-height:1.45;color:#1f2937;display:none}
#dzCache.show{display:block;animation:dzIn .25s ease}
@keyframes dzIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
#dzCache b{display:block;font-size:14px;margin-bottom:2px}
#dzCache .dz-row{display:flex;gap:8px;margin-top:10px;justify-content:flex-end}
#dzCache button{border:0;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;cursor:pointer}
#dzCache .dz-go{background:#16a34a;color:#fff}
#dzCache .dz-go:disabled{opacity:.6;cursor:wait}
#dzCache .dz-later{background:#f3f4f6;color:#374151}
@media (max-width:480px){#dzCache{left:12px;right:12px;max-width:none}}
</style>
<div id="dzCache" role="status" aria-live="polite">
  <b id="dzCacheTitle">Clear old cached files?</b>
  <span id="dzCacheText"></span>
  <div class="dz-row">
    <button type="button" class="dz-later" id="dzCacheLater">Later</button>
    <button type="button" class="dz-go" id="dzCacheGo">Clear cache</button>
  </div>
</div>
<script>
(function () {
  const VERSION = <?php echo json_encode($siteVersion); ?>;
  const ENDPOINT = <?php echo json_encode(url('admin/cache/clear.php')); ?>;
  const TOKEN = <?php echo json_encode(csrf_token()); ?>;
  const LIMIT_MB = 40;           // ask when this site's cached data in the browser is larger than this
  const box = document.getElementById('dzCache'), text = document.getElementById('dzCacheText');
  const go = document.getElementById('dzCacheGo'), later = document.getElementById('dzCacheLater');
  const store = {
    get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} },
  };
  const mb = b => (b / 1048576).toFixed(b > 10485760 ? 0 : 1) + ' MB';

  async function clearBrowserSide() {
    try { if (window.caches) for (const k of await caches.keys()) await caches.delete(k); } catch (e) {}
    try {
      if (navigator.serviceWorker) for (const r of await navigator.serviceWorker.getRegistrations()) await r.unregister();
    } catch (e) {}
  }

  window.dazuClearCache = async function (btn) {
    if (btn) { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.innerHTML = 'Clearing…'; }
    let msg = '';
    try {
      const res = await fetch(ENDPOINT, {method: 'POST', body: new URLSearchParams({csrf_token: TOKEN}), credentials: 'same-origin', cache: 'no-store'});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Could not clear the server cache.');
      msg = 'Cleared: ' + data.cleared.join(', ') + '.';
    } catch (e) {
      msg = (e.message || 'Server cache could not be cleared') + ' Browser cache was still cleared.';
    }
    await clearBrowserSide();
    store.set('dazu_cache_version', VERSION);
    store.set('dazu_cache_snooze', '');
    text.textContent = msg + ' Reloading…';
    box.classList.add('show');
    setTimeout(() => location.reload(), 900);
  };

  go.addEventListener('click', () => window.dazuClearCache(go));
  later.addEventListener('click', () => { box.classList.remove('show'); store.set('dazu_cache_snooze', String(Date.now())); });

  async function check() {
    const snooze = +store.get('dazu_cache_snooze') || 0;
    if (Date.now() - snooze < 6 * 3600 * 1000) return;     // "Later" hides it for 6 hours
    const seen = store.get('dazu_cache_version');
    if (seen === null) { store.set('dazu_cache_version', VERSION); }  // first visit: nothing old to clear
    let usage = 0;
    try { if (navigator.storage && navigator.storage.estimate) usage = (await navigator.storage.estimate()).usage || 0; } catch (e) {}
    if (seen !== null && seen !== VERSION) {
      text.textContent = 'The system was updated since this browser last cleared its cache. Clearing now prevents old pages, styles or errors from showing.';
    } else if (usage > LIMIT_MB * 1048576) {
      text.textContent = 'This browser is holding ' + mb(usage) + ' of cached data for the site. Clearing it frees space and makes sure you see the latest pages.';
    } else {
      return;
    }
    box.classList.add('show');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', check); else check();
})();
</script>
