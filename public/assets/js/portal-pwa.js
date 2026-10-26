/* Dazu portal app: install banner, saved resources, notifications and the unread badge (all portals).
 * Configured by the <script> tag's data- attributes:
 *   data-sw, data-scope            the portal's service worker and its scope (required)
 *   data-portal                    short portal name, used for this device's saved state
 *   data-api, data-list, data-csrf notifications (tutor + student portals only; leave data-api empty elsewhere)
 *   data-preview="1"               portal preview: no alerts are turned on
 *
 *  Install banner ([data-pwa-banner]) — three steps, each ticked when done:
 *    1. Install the app        one-tap install where the browser offers it, otherwise device-specific instructions
 *    2. Save app resources     every stylesheet, script, font and icon this portal loaded is saved on the device by the
 *                              service worker (with a progress bar) — works even when the browser can't install apps
 *    3. Turn on alerts         push notifications (portals with notifications only)
 *  "Later" hides the banner for 7 days; it disappears for good once every step is done.
 */
(function () {
  'use strict';
  var me = document.currentScript;
  if (!me) return;
  var cfg = {
    api: me.dataset.api || '', sw: me.dataset.sw, scope: me.dataset.scope, csrf: me.dataset.csrf || '',
    list: me.dataset.list || '', preview: me.dataset.preview === '1', portal: me.dataset.portal || 'portal'
  };
  var hasAlerts = !!cfg.api;
  var POLL_MS = 30000;
  var K = {
    seen: 'dazu-notif-seen:' + cfg.api,
    later: 'dazu-pwa-later:' + cfg.portal,
    saved: 'dazu-pwa-saved:' + cfg.portal
  };
  var baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  var swReg = null;
  var installEvent = null;
  var saving = false;
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  var ua = navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isAndroid = /android/i.test(ua);

  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (_) { return null; } }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function $all(sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); }
  function $(sel) { return document.querySelector(sel); }

  /* ======================= Notifications (tutor + student) ======================= */
  function api(params) {
    return fetch(cfg.api + '?' + new URLSearchParams(params), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); });
  }
  function post(body) {
    body.csrf_token = cfg.csrf;
    return fetch(cfg.api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  function showCount(n) {
    n = Math.max(0, n | 0);
    $all('[data-notif-count]').forEach(function (el) {
      el.textContent = n > 99 ? '99+' : String(n);
      el.classList.toggle('d-none', n === 0);
    });
    document.title = (n ? '(' + n + ') ' : '') + baseTitle;
    if (navigator.setAppBadge) (n ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(function () {});
    if (swReg && swReg.active) swReg.active.postMessage({ type: 'badge', count: n });
  }

  function alertNew(latestId) {
    var seen = parseInt(store(K.seen) || '0', 10);
    if (!seen) { store(K.seen, String(latestId)); return; }        // first visit on this device: don't replay history
    if (latestId <= seen) return;
    store(K.seen, String(latestId));
    if (!swReg || !('Notification' in window) || Notification.permission !== 'granted') return;
    api({ action: 'list', limit: 5 }).then(function (d) {
      d.items.filter(function (it) { return it.id > seen && !it.read; }).reverse().forEach(function (it) {
        swReg.showNotification(it.title, {
          body: it.body, icon: it.icon || undefined, tag: 'dazu-n' + it.id,
          data: { id: it.id }, badge: new URL('../assets/icons/icon-192.png', swReg.scope).href
        });
      });
    }).catch(function () {});
  }

  function poll() {
    if (!hasAlerts) return;
    api({ action: 'count' }).then(function (d) {
      showCount(d.unread);
      if (d.latest_id) alertNew(d.latest_id);
    }).catch(function () {});
  }

  function fillMenu(menu) {
    menu.innerHTML = '<div class="px-3 py-2 small text-muted">Loading…</div>';
    api({ action: 'list', limit: 8 }).then(function (d) {
      showCount(d.unread);
      var html = '<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"><strong>Notifications</strong>' +
        (d.unread ? '<button type="button" class="btn btn-link btn-sm p-0" data-notif-readall>Mark all read</button>' : '') + '</div>';
      if (!d.items.length) html += '<div class="px-3 py-4 text-center small text-muted">No notifications yet</div>';
      d.items.forEach(function (it) {
        html += '<a class="dropdown-item d-flex gap-2 py-2 text-wrap' + (it.read ? '' : ' bg-light') + '" href="' + cfg.list + '?open=' + it.id + '">' +
          (it.icon ? '<img src="' + esc(it.icon) + '" alt="" class="rounded-circle flex-shrink-0" style="width:32px;height:32px;object-fit:cover">'
                   : '<span class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px"><i class="fas fa-bell small"></i></span>') +
          '<span class="small" style="min-width:0"><span class="d-block' + (it.read ? '' : ' fw-semibold') + '">' + esc(it.title) + '</span>' +
          (it.body ? '<span class="d-block text-muted text-truncate" style="max-width:240px">' + esc(it.body) + '</span>' : '') +
          '<span class="d-block text-muted" style="font-size:.72rem">' + esc(it.ago) + '</span></span></a>';
      });
      html += '<div class="border-top text-center"><a class="dropdown-item small text-success py-2" href="' + cfg.list + '">See all notifications</a></div>';
      menu.innerHTML = html;
    }).catch(function () { menu.innerHTML = '<div class="px-3 py-2 small text-danger">Could not load notifications.</div>'; });
  }

  function urlB64ToUint8(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4);
    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function pushSupported() { return hasAlerts && !cfg.preview && swReg && 'PushManager' in window && 'Notification' in window; }

  function enablePush(ask) {
    if (!pushSupported()) {
      if (ask) alert(isIOS && !isStandalone
        ? 'On iPhone/iPad, first install the app (step 1), open it from your home screen, then turn on alerts.'
        : 'This browser does not support notifications.');
      return Promise.resolve(false);
    }
    var perm = ask ? Notification.requestPermission() : Promise.resolve(Notification.permission);
    return Promise.resolve(perm).then(function (p) {
      if (p !== 'granted') {
        if (ask && p === 'denied') alert('Notifications are blocked. Allow them for this site in your browser settings, then try again.');
        refresh();
        return false;
      }
      return api({ action: 'vapid' }).then(function (v) {
        if (!v.ok || !v.key) return false;
        return swReg.pushManager.getSubscription().then(function (sub) {
          return sub || swReg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8(v.key) });
        }).then(function (sub) {
          return post({ action: 'subscribe', subscription: sub.toJSON() });
        }).then(function () {
          refresh();
          if (ask) swReg.showNotification('Alerts are on', { body: 'You will be notified here about new activity.', tag: 'dazu-welcome', icon: new URL('../assets/icons/icon-192.png', swReg.scope).href });
          return true;
        });
      });
    }).catch(function (err) { if (ask) alert('Could not turn on alerts: ' + err); return false; });
  }

  /* ======================= Install ======================= */
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); installEvent = e; refresh(); });
  window.addEventListener('appinstalled', function () {
    installEvent = null; isStandalone = true;
    store('dazu-pwa-installed:' + cfg.portal, '1');
    saveResources(false);       // the new app gets its resources straight away
    refresh();
  });

  function installHelp() {
    if (!window.isSecureContext) return 'Installing needs a secure (https://) connection. Open the portal with https:// and try again.';
    if (isIOS) return 'In Safari: tap the Share button, then "Add to Home Screen". Open the app from your home screen afterwards.';
    if (isAndroid) return 'In Chrome: tap the ⋮ menu, then "Install app" (or "Add to Home screen").';
    return 'In Chrome or Edge: click the install icon at the right of the address bar, or open the ⋮ menu and choose "Install".';
  }

  function install() {
    if (installEvent) {
      installEvent.prompt();
      installEvent.userChoice.then(function (c) {
        if (c && c.outcome === 'accepted') store('dazu-pwa-installed:' + cfg.portal, '1');
      }).finally(function () { installEvent = null; refresh(); });
    } else {
      var box = $('[data-pwa-install-help]');
      if (box) { box.textContent = installHelp(); box.classList.remove('d-none'); }
      else alert(installHelp());
    }
  }

  function installed() { return isStandalone || store('dazu-pwa-installed:' + cfg.portal) === '1'; }

  /* ======================= Save app resources ======================= */
  // Everything this page actually loaded that the app needs to look right: stylesheets, scripts, fonts, icons.
  function resourceList() {
    var urls = [];
    var add = function (u) {
      if (!u) return;
      try {
        var x = new URL(u, location.href);
        if (!/^https?:$/.test(x.protocol) || /\.php$/i.test(x.pathname) || x.pathname.indexOf('/uploads/') !== -1) return;
        if (!/\.(css|js|woff2?|ttf|otf|eot|png|jpe?g|webp|gif|svg|ico|html)$/i.test(x.pathname) && !/^\/npm\/[^/]+$/.test(x.pathname)) return;
        x.hash = '';
        urls.push(x.href);
      } catch (_) {}
    };
    $all('link[rel=stylesheet], link[rel=icon], link[rel=apple-touch-icon]').forEach(function (l) { add(l.href); });
    $all('script[src]').forEach(function (s) { add(s.src); });
    if (window.performance && performance.getEntriesByType) {
      performance.getEntriesByType('resource').forEach(function (r) {
        if (['link', 'script', 'css', 'img', 'other'].indexOf(r.initiatorType) !== -1) add(r.name);
      });
    }
    var root = new URL('../', new URL(cfg.scope, location.href)).href;   // site root (one level above the portal)
    ['assets/icons/icon-192.png', 'assets/icons/icon-512.png', 'assets/portal-offline.html'].forEach(function (p) { add(root + p); });
    return urls.filter(function (u, i, a) { return a.indexOf(u) === i; });
  }

  function saveResources(showProgress) {
    if (saving || !swReg || !swReg.active) return;
    saving = true;
    var urls = resourceList();
    if (showProgress) setProgress(0, urls.length, false);
    if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});
    swReg.active.postMessage({ type: 'dazu-precache', urls: urls });
  }

  function setProgress(done, total, finished, failed) {
    var bar = $('[data-pwa-progress]'), label = $('[data-pwa-progress-label]');
    if (!bar) return;
    bar.parentNode.classList.toggle('d-none', finished);
    var pct = total ? Math.round(done / total * 100) : 0;
    bar.style.width = pct + '%';
    if (label) label.textContent = finished ? '' : 'Saving ' + done + ' of ' + total + ' files…';
  }

  /* ======================= Banner ======================= */
  function stepState(name, state, text) {
    var el = $('[data-pwa-step="' + name + '"]');
    if (!el) return;
    el.classList.toggle('pwa-step-done', state === 'done');
    el.classList.toggle('d-none', state === 'hidden');
    var icon = el.querySelector('.pwa-step-icon');
    if (icon) icon.innerHTML = state === 'done' ? '<i class="fas fa-check"></i>' : el.getAttribute('data-step-no');
    var t = el.querySelector('[data-step-text]');
    if (t && text) t.textContent = text;
    var btn = el.querySelector('button');
    if (btn) btn.classList.toggle('d-none', state === 'done' || state === 'waiting');
  }

  function refresh() {
    // 1. install
    var installDone = installed();
    stepState('install', installDone ? 'done' : 'todo', installDone ? 'App installed on this device' : null);

    // 2. resources
    var saved = store(K.saved);
    stepState('resources', saved ? 'done' : (saving ? 'waiting' : 'todo'),
      saved ? 'App resources saved on this device (' + saved + ' files)' : null);

    // 3. alerts
    var alertsDone = 'Notification' in window && Notification.permission === 'granted';
    var alertsBlocked = 'Notification' in window && Notification.permission === 'denied';
    stepState('alerts', !hasAlerts || cfg.preview ? 'hidden' : (alertsDone ? 'done' : 'todo'),
      alertsDone ? 'Alerts are on' : (alertsBlocked ? 'Alerts are blocked — allow notifications for this site in your browser settings' : null));

    var allDone = installDone && !!saved && (!hasAlerts || cfg.preview || alertsDone);
    var later = parseInt(store(K.later) || '0', 10);
    var banner = $('[data-pwa-banner]');
    if (banner) banner.classList.toggle('d-none', allDone || Date.now() < later);

    // install/alerts shortcuts elsewhere in the menus
    $all('[data-pwa-install]').forEach(function (b) { b.classList.toggle('d-none', installDone); });
    $all('[data-pwa-enable-push]').forEach(function (b) { b.classList.toggle('d-none', !hasAlerts || cfg.preview || alertsDone || alertsBlocked); });
  }

  document.addEventListener('click', function (e) {
    var bell = e.target.closest('[data-notif-toggle]');
    if (bell && hasAlerts) {
      var menu = document.querySelector(bell.getAttribute('data-notif-toggle'));
      if (menu) fillMenu(menu);
    }
    if (e.target.closest('[data-notif-readall]')) {
      e.preventDefault(); e.stopPropagation();
      post({ action: 'read_all' }).then(function (d) { showCount(d.unread || 0); $all('[data-notif-menu]').forEach(fillMenu); });
    }
    if (e.target.closest('[data-pwa-install]')) { e.preventDefault(); install(); }
    if (e.target.closest('[data-pwa-save]')) { e.preventDefault(); store(K.saved, null); saveResources(true); refresh(); }
    if (e.target.closest('[data-pwa-enable-push]')) { e.preventDefault(); enablePush(true); }
    if (e.target.closest('[data-pwa-later]')) { e.preventDefault(); store(K.later, String(Date.now() + 7 * 864e5)); refresh(); }
  });

  /* ======================= Start ======================= */
  if ('serviceWorker' in navigator && cfg.sw) {
    navigator.serviceWorker.addEventListener('message', function (e) {
      var d = e.data || {};
      if (d.type === 'dazu-notification') {
        if (d.id) store(K.seen, String(Math.max(d.id, parseInt(store(K.seen) || '0', 10))));
        poll();
      }
      if (d.type === 'dazu-precache-progress') {
        setProgress(d.done, d.total, d.finished, d.failed);
        if (d.finished) {
          saving = false;
          if (d.done > 0) store(K.saved, String(d.done));
          refresh();
        }
      }
    });
    navigator.serviceWorker.register(cfg.sw, { scope: cfg.scope }).then(function () {
      return navigator.serviceWorker.ready;
    }).then(function (reg) {
      swReg = reg;
      if (hasAlerts && 'Notification' in window && Notification.permission === 'granted') enablePush(false); // keep this device subscribed
      // Keep the saved resources complete: quietly add anything new this portal uses (already-saved files are skipped).
      if (installed() || store(K.saved)) saveResources(false);
      refresh();
    }).catch(function () { refresh(); });
  }
  refresh();
  poll();
  if (hasAlerts) {
    setInterval(function () { if (!document.hidden) poll(); }, POLL_MS);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  }
})();
