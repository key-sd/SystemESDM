const CACHE_NAME = 'elim-sdm-v1';
const STATIC_ASSETS = [
  'css/app.css',
  'css/login.css',
  'js/main.js',
  'img/logoClaro.png',
  'img/logoOscuro.png',
  'img/icono-192.png',
  'img/icono-512.png'
];

// Instalar el Service Worker y almacenar en caché los recursos estáticos básicos
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Usar paths relativos al Service Worker
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activar y limpiar cachés antiguas
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar peticiones
self.addEventListener('fetch', (e) => {
  // Ignorar métodos que no sean GET y URLs externas (como Google Fonts o Remixicon CDN por simplicidad)
  if (e.request.method !== 'GET' || !e.request.url.startsWith(self.location.origin)) {
    return;
  }

  const url = new URL(e.request.url);
  const isStatic = STATIC_ASSETS.some(asset => url.pathname.endsWith(asset));

  if (isStatic) {
    // Estrategia: Stale-While-Revalidate para archivos estáticos locales
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        const fetchPromise = fetch(e.request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(e.request, responseToCache);
            });
          }
          return networkResponse;
        });
        return cachedResponse || fetchPromise;
      })
    );
  } else {
    // Estrategia: Network-First para páginas PHP dinámicas y el resto de peticiones
    e.respondWith(
      fetch(e.request).catch(() => {
        return caches.match(e.request);
      })
    );
  }
});
