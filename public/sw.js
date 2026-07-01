/* Dazu Hub Institute — service worker (PWA) */
const CACHE = 'dazu-hub-v2';
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

function isStaticAsset(url) {
  if (url.pathname.startsWith('/assets/')) return true;
  return /\.(css|js|png|jpe?g|webp|gif|svg|ico|woff2?|ttf)$/i.test(url.pathname);
}

self.addEventListener('fetch', (e) => {
  if (e.request.method !== 'GET') return;

  const url = new URL(e.request.url);
  if (url.origin !== self.location.origin) return;

  // Never intercept PHP pages or HTML navigation — avoids breaking dynamic routes.
  if (url.pathname.includes('.php') || e.request.mode === 'navigate') {
    return;
  }

  if (!isStaticAsset(url)) return;

  e.respondWith(
    caches.match(e.request).then((cached) => cached || fetch(e.request))
  );
});
