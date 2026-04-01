const CACHE_NAME = 'prt-v1';

// Assets statiques à mettre en cache lors de l'installation
const STATIC_ASSETS = [
  '/',
  '/index.html',
  '/track.html',
  '/assets/css/prt.css',
  '/assets/js/api.js',
  '/assets/js/app.js',
];

// Routes API à ne jamais mettre en cache
const API_PREFIX = '/api/';

// ── Installation : mise en cache des assets statiques ──────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// ── Activation : suppression des anciens caches ────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// ── Fetch : stratégie réseau d'abord pour l'API, cache d'abord pour le reste ─
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Toujours aller sur le réseau pour l'API
  if (url.pathname.startsWith(API_PREFIX)) {
    event.respondWith(fetch(request));
    return;
  }

  // Stratégie "Stale While Revalidate" pour les assets statiques
  event.respondWith(
    caches.match(request).then(cached => {
      const networkFetch = fetch(request).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
        }
        return response;
      }).catch(() => cached);

      return cached || networkFetch;
    })
  );
});
