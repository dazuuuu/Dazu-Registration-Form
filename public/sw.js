/* Dazu Hub Institute — service worker (PWA) */
/*
 * v3: code assets (css/js) are network-first, media is cache-first.
 *
 * v2 served EVERY /assets/** and every .css/.js from the cache first, with no
 * expiry and no revalidation. Once a file was cached the browser kept serving it
 * forever — a hard refresh could not help, because the SW answered the request
 * before it ever reached the network. Deploying new JS or CSS had no effect on
 * anyone who had already visited the site.
 */
const CACHE = 'dazu-hub-v3';
const ASSETS = [
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

/* Code: must reflect the deployed version. */
function isCodeAsset(url) {
  return /\.(css|js)$/i.test(url.pathname);
}

/* Media: safe to serve from cache. */
function isMediaAsset(url) {
  return /\.(png|jpe?g|webp|gif|svg|ico|woff2?|ttf)$/i.test(url.pathname);
}

/* Network-first: use the network, fall back to cache only when offline. */
function networkFirst(request) {
  return fetch(request)
    .then((response) => {
      if (response && response.ok) {
        const copy = response.clone();
        caches.open(CACHE).then((c) => c.put(request, copy)).catch(() => {});
      }
      return response;
    })
    .catch(() => caches.match(request).then((cached) => cached || Promise.reject(new Error('offline'))));
}

/* Cache-first: serve the cached copy, otherwise fetch and store it. */
function cacheFirst(request) {
  return caches.match(request).then((cached) => {
    if (cached) return cached;
    return fetch(request).then((response) => {
      if (response && response.ok) {
        const copy = response.clone();
        caches.open(CACHE).then((c) => c.put(request, copy)).catch(() => {});
      }
      return response;
    });
  });
}

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;

  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin) return;

  // Never intercept PHP pages or HTML navigation — avoids breaking dynamic routes.
  if (url.pathname.includes('.php') || e.request.mode === 'navigate') {
    return;
  }

  if (isCodeAsset(url)) {
    e.respondWith(networkFirst(e.request));
    return;
  }

  if (isMediaAsset(url)) {
    e.respondWith(cacheFirst(e.request));
  }
});