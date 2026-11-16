/* Dazu Secretary app: service worker for /secretary/ (a NEW file; no existing service worker is edited, and none covers this portal).
 *
 * What it may keep on the phone: the offline page and the app's own look (the Dazu icons, logo, styles, scripts, fonts), nothing else.
 * It NEVER keeps a page, a data answer, an upload or anything private: every page and every .php address goes to the network,
 * and when there is no network the visitor gets the offline page. It also shows push notifications and the app-icon badge. */
const SHELL = 'dazu-secretary-shell-v1';
const OFFLINE = new URL('offline.html', self.location).href;
const ICON = new URL('../assets/icons/secretary-192.png', self.location).href;
const LOGO = new URL('../assets/images/logo/logo.png', self.location).href;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(SHELL).then((c) => c.addAll([OFFLINE, ICON, LOGO])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys()
    .then((keys) => Promise.all(keys.filter((k) => k.startsWith('dazu-secretary-') && k !== SHELL).map((k) => caches.delete(k))))
    .then(() => self.clients.claim()));
});

// The app shell: the site's own look (styles, scripts, icons, fonts under /assets/) and the two versioned CDN libraries.
// Never a page, a .php address, an upload, or any other site's data.
function isShell(url) {
  if (url.pathname.includes('/uploads/') || /\.php$/i.test(url.pathname)) return false;
  if (url.origin === self.location.origin) return /\/assets\/.+\.(css|js|png|jpe?g|webp|gif|svg|ico|woff2?|ttf)$/i.test(url.pathname);
  return /^(cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)$/.test(url.hostname) && /(bootstrap@5\.1\.3|font-awesome\/6\.0\.0)/.test(url.pathname);
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                                   // forms and the API are never touched
  if (req.mode === 'navigate') {                                      // pages: always live; the offline page only when there is no network
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }
  const url = new URL(req.url);
  if (!isShell(url)) return;                                          // everything else goes straight to the network
  e.respondWith(
    fetch(req).then((res) => {                                        // newest first; the saved copy only when offline
      if (res && (res.ok || res.type === 'opaque')) { const copy = res.clone(); caches.open(SHELL).then((c) => c.put(req, copy)).catch(() => {}); }
      return res;
    }).catch(() => caches.match(req, { ignoreVary: true }))
  );
});

function setBadge(n) {
  if (!self.navigator || !self.navigator.setAppBadge) return Promise.resolve();
  return (n > 0 ? self.navigator.setAppBadge(n) : self.navigator.clearAppBadge()).catch(() => {});
}

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(Promise.all([
    self.registration.showNotification(d.title || 'Dazu Secretary', {
      body: d.body || '', icon: d.icon || ICON, badge: ICON, tag: 'dazu-n' + (d.id || Date.now()),
      data: { url: d.url || self.registration.scope, id: d.id || 0 }, vibrate: [120, 60, 120],
    }),
    typeof d.badge_count === 'number' ? setBadge(d.badge_count) : Promise.resolve(),
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((l) => l.forEach((c) => c.postMessage({ type: 'dazu-notification', id: d.id || 0, unread: d.badge_count }))),
  ]));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const data = e.notification.data || {};
  const target = data.id ? new URL('notifications/index.php?open=' + data.id, self.registration.scope).href : (data.url || self.registration.scope);
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) if (c.url.startsWith(self.registration.scope) && 'focus' in c) return c.focus().then((w) => (w && 'navigate' in w ? w.navigate(target) : null));
    return self.clients.openWindow(target);
  }));
});

self.addEventListener('message', (e) => {
  if (e.data && e.data.type === 'badge') e.waitUntil(setBadge(e.data.count || 0));
});
