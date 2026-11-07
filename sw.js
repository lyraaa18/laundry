const CACHE_NAME = 'laundry-pwa-v5';

// File statis yang aman di-cache (TANPA PHP)
const STATIC_CACHE = [
  './offline.html',
  './assets/css/main.css',
  './assets/css/components.css',
  './assets/css/dashboard.css',
  './assets/js/html5-qrcode.min.js',
  './assets/logo.png',
  './assets/logo-192.png',
  './assets/logo-512.png',
  './assets/logo 1.png',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install: cache semua aset statis
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate: hapus cache lama
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch: strategi Network-First untuk PHP, Cache-First untuk aset statis
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Jangan cache request POST/non-GET
  if (event.request.method !== 'GET') return;

  // PHP files: Network-First, kalau gagal tampilkan offline.html
  if (url.pathname.endsWith('.php') || url.pathname === '/') {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match('./offline.html').then(response => {
            return response || new Response('Anda sedang offline dan halaman tidak tersedia.', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({ 'Content-Type': 'text/html' })
            });
          });
        })
    );
    return;
  }

  // Aset statis: Cache-First, fallback ke network
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request).then(networkResponse => {
        // Simpan ke cache untuk berikutnya
        if (networkResponse.ok) {
          const clone = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return networkResponse;
      });
    }).catch(() => caches.match('./offline.html'))
  );
});
