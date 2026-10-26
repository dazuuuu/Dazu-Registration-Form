/* Dazu portal app service worker (shared by every portal: /tutor/, /students/, /admin/, /superadmin/, /employee/ sw.js).
 * - Saves the app's resources on the device when the page asks (stylesheets, scripts, fonts, icons) and serves them from
 *   there, so the app opens with its full look even on a weak connection.
 * - Shows push notifications (with the sender's picture), keeps the app-icon badge in step with the unread count and
 *   opens the right page when a notification is tapped.
 * - Never stores portal pages or data: pages always come from the network, with an offline page when there is none. */
const OFFLINE_URL = new URL('../assets/portal-offline.html', self.location).href;
const BADGE_ICON = new URL('../assets/icons/icon-192.png', self.location).href;
const CACHE = 'dazu-portal-v1';
const RES_CACHE = 'dazu-portal-res-v1';   // resources saved by the install banner (shared by all portals)

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE_URL, BADGE_ICON])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('dazu-portal-') && k !== CACHE && k !== RES_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Code files from a versioned CDN path (bootstrap@5.1.3, font-awesome/6.0.0) never change, so the saved copy is used.
function isImmutable(url) {
  return url.origin !== self.location.origin && /(@\d|\/\d+\.\d+\.\d+\/)/.test(url.pathname);
}
function isStatic(url) {
  return /\.(css|js|woff2?|ttf|otf|eot|png|jpe?g|webp|gif|svg|ico)$/i.test(url.pathname) || /^\/npm\/[^/]+$/.test(url.pathname);
}
function save(req, res) {
  if (res && (res.ok || res.type === 'opaque')) {
    const copy = res.clone();
    caches.open(RES_CACHE).then((c) => c.put(req, copy)).catch(() => {});
  }
  return res;
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }
  const url = new URL(req.url);
  if (!isStatic(url) || url.pathname.includes('/uploads/')) return;   // data, API calls and user uploads: always live
  const font = /\.(woff2?|ttf|otf|eot)$/i.test(url.pathname);
  const image = /\.(png|jpe?g|webp|gif|svg|ico)$/i.test(url.pathname);
  if (font || image || isImmutable(url)) {
    // Saved copy first; fetch (and save) only if this device doesn't have it yet.
    e.respondWith(caches.match(req, { ignoreVary: true }).then((hit) => hit || fetch(req).then((r) => save(req, r))));
  } else {
    // The site's own CSS/JS and unversioned CDN files: newest version first, the saved copy when offline.
    e.respondWith(fetch(req).then((r) => save(req, r)).catch(() => caches.match(req, { ignoreVary: true })));
  }
});

/* "Save app resources" from the install banner: store every URL the portal needs, reporting progress to the page. */
async function saveResources(urls, client) {
  const cache = await caches.open(RES_CACHE);
  const list = Array.from(new Set(urls)).filter((u) => /^https?:/.test(u));
  let done = 0, failed = 0, i = 0;
  const report = (finished) => client && client.postMessage({ type: 'dazu-precache-progress', done, failed, total: list.length, finished });
  // A stylesheet's own fonts (e.g. Font Awesome's icon fonts) are saved too, so icons still show offline.
  function addFontsFrom(cssUrl, res) {
    if (!/\.css(\?|$)/i.test(cssUrl) || !res || res.type === 'opaque') return;
    pendingCss++;
    res.text().then((css) => {
      const re = /url\(\s*['"]?([^'")]+\.(?:woff2?|ttf|otf|eot)(?:\?[^'")]*)?)['"]?\s*\)/gi;
      let m;
      while ((m = re.exec(css))) {
        const f = new URL(m[1], cssUrl).href.replace(/#.*$/, '');
        if (list.indexOf(f) === -1) list.push(f);
      }
    }).catch(() => {}).finally(() => { pendingCss--; });
  }
  let pendingCss = 0;
  async function worker() {
    while (i < list.length) {
      const u = list[i++];
      try {
        if (!(await cache.match(u, { ignoreVary: true }))) {
          const crossOrigin = new URL(u).origin !== self.location.origin;
          let res = await fetch(u, { mode: crossOrigin ? 'cors' : 'same-origin', credentials: crossOrigin ? 'omit' : 'same-origin' })
            .catch(() => (crossOrigin ? fetch(u, { mode: 'no-cors' }) : Promise.reject()));
          if (!res || !(res.ok || res.type === 'opaque')) throw new Error('fetch failed');
          await cache.put(u, res.clone());
          addFontsFrom(u, res);
        } else if (/\.css(\?|$)/i.test(u)) {
          addFontsFrom(u, await cache.match(u, { ignoreVary: true }));
        }
        done++;
      } catch (_) {
        failed++;
      }
      report(false);
    }
  }
  // Run until every file is saved, including the fonts the stylesheets pointed to.
  do {
    await Promise.all([worker(), worker(), worker(), worker()]);
    while (pendingCss > 0) await new Promise((r) => setTimeout(r, 50));
  } while (i < list.length);
  report(true);
}

function setBadge(count) {
  if (!self.navigator || !self.navigator.setAppBadge) return Promise.resolve();
  return (count > 0 ? self.navigator.setAppBadge(count) : self.navigator.clearAppBadge()).catch(() => {});
}

function tellPages(msg) {
  return self.clients.matchAll({ type: 'window', includeUncontrolled: true })
    .then((list) => list.forEach((c) => c.postMessage(msg)));
}

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { body: e.data ? e.data.text() : '' }; }
  const title = d.title || 'Dazu Training Institute';
  e.waitUntil(Promise.all([
    self.registration.showNotification(title, {
      body: d.body || '',
      icon: d.icon || BADGE_ICON,
      badge: BADGE_ICON,
      image: d.image || undefined,
      tag: 'dazu-n' + (d.id || Date.now()),   // the page uses the same tag, so one notification never shows twice
      data: { url: d.url || self.registration.scope, id: d.id || 0 },
      vibrate: [120, 60, 120],
    }),
    typeof d.badge_count === 'number' ? setBadge(d.badge_count) : Promise.resolve(),
    tellPages({ type: 'dazu-notification', id: d.id || 0, unread: d.badge_count }),
  ]));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const data = e.notification.data || {};
  // Opening through the notifications page marks it read before following its link.
  const target = data.id ? new URL('notifications/index.php?open=' + data.id, self.registration.scope).href : (data.url || self.registration.scope);
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if (c.url.startsWith(self.registration.scope) && 'focus' in c) {
        return c.focus().then((w) => (w && 'navigate' in w ? w.navigate(target) : null));
      }
    }
    return self.clients.openWindow(target);
  }));
});

self.addEventListener('message', (e) => {
  if (!e.data) return;
  if (e.data.type === 'badge') e.waitUntil(setBadge(e.data.count || 0));
  if (e.data.type === 'dazu-precache') e.waitUntil(saveResources(e.data.urls || [], e.source));
});
