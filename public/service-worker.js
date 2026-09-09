const STATIC_CACHE = 'rutinku-static-v20';
const WORKER_HOSTNAME = self.location.hostname;
const IS_LOCAL_DEVELOPMENT = WORKER_HOSTNAME === 'localhost'
  || WORKER_HOSTNAME === '127.0.0.1'
  || WORKER_HOSTNAME === '[::1]'
  || /^10\./.test(WORKER_HOSTNAME)
  || /^192\.168\./.test(WORKER_HOSTNAME)
  || /^172\.(1[6-9]|2\d|3[01])\./.test(WORKER_HOSTNAME);
const STATIC_ASSETS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/vendor/bootstrap-5.3.3.min.css',
  '/assets/css/app.css',
  '/assets/css/buttons.css',
  '/assets/vendor/fontawesome/css/fontawesome.min.css',
  '/assets/vendor/fontawesome/css/solid.min.css',
  '/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2',
  '/assets/vendor/fontawesome/webfonts/fa-solid-900.ttf',
  '/assets/js/app.js',
  '/assets/js/child-today.js',
  '/assets/js/task-form.js',
  '/assets/js/reward-form.js',
  '/assets/css/task-form.css',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/apple-touch-icon.png'
];

self.addEventListener('install', (event) => {
  if (IS_LOCAL_DEVELOPMENT) {
    event.waitUntil(self.skipWaiting());
    return;
  }

  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  if (IS_LOCAL_DEVELOPMENT) {
    event.waitUntil(
      caches.keys()
        .then((keys) => Promise.all(keys.filter((key) => key.startsWith('rutinku-')).map((key) => caches.delete(key))))
        .then(() => self.registration.unregister())
    );
    return;
  }

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
  // Never replace local development navigation with the cached offline page.
  if (IS_LOCAL_DEVELOPMENT) {
    return;
  }

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
