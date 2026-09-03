const STATIC_CACHE = 'rutinku-static-v3';
const STATIC_ASSETS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/vendor/bootstrap-5.3.3.min.css',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('rutinku-static-') && key !== STATIC_CACHE)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (request.mode === 'navigate') {
    // Authenticated HTML is always fetched from the server and never stored.
    event.respondWith(
      fetch(request, { cache: 'no-store' })
        .catch(() => caches.match('/offline.html'))
    );
    return;
  }

  if (url.origin === self.location.origin && STATIC_ASSETS.includes(url.pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request))
    );
  }
});
