// Service Worker 
// Handles offline functionality, caching, and PWA support

const CACHE_NAME = 'family-medicare-clinic-v2.0';
const CACHE_VERSION = '2.0';

// Static assets to cache
const PRECACHE_ASSETS = [
  './',
  'index.php',
  'login.php',
  'assets/css/style.css',
  'ai-assistant/ai-assistant.css',
  'ai-assistant/ai-assistant.js',
  'assets/icons/icon-192x192.png',
  'assets/icons/icon-512x512.png',
  'assets/icons/apple-touch-icon.png',
  'assets/images/ai_logo.png',
  'assets/images/favicon.png',
  'manifest.json'
];

// Check if running on localhost for development purposes 
const isLocalhost = Boolean(
  self.location.hostname === 'localhost' ||
  self.location.hostname === '[::1]' ||
  self.location.hostname.match(/^127(?:\.(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}$/)
);

// CDN assets to cache
const CDN_ASSETS = [
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Never cache patterns
const NEVER_CACHE_PATTERNS = [
  /\.php(\?.*)?$/i,
  /ai-assistant-api\.php/i,
  /login\.php/i,
  /logout\.php/i,
  /config\//i,
  /database\//i,
];

function shouldNeverCache(url) {
  return NEVER_CACHE_PATTERNS.some(pattern => pattern.test(url.toString()));
}

function isNavigationRequest(request) {
  return request.mode === 'navigate';
}

// Install event
self.addEventListener('install', event => {
  console.log('[SW] Installing Service Worker v2.0...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        const localCachePromises = PRECACHE_ASSETS.map(asset => {
          return cache.add(asset).catch(err => {
            console.warn('[SW] Failed to cache local asset:', asset);
          });
        });

        const cdnCachePromises = CDN_ASSETS.map(url => {
          return fetch(url, { mode: 'cors' })
            .then(response => {
              if (response.ok) {
                return cache.put(url, response);
              }
            })
            .catch(err => {
              console.warn('[SW] Failed to cache CDN asset:', url);
            });
        });

        return Promise.all([...localCachePromises, ...cdnCachePromises]);
      })
      .then(() => {
        console.log('[SW] ✓ Service Worker installed successfully');
        return self.skipWaiting();
      })
  );
});

// Activate event
self.addEventListener('activate', event => {
  console.log('[SW] Activating Service Worker v2.0...');
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(name => !name.includes(CACHE_NAME))
            .map(name => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => {
        console.log('[SW] ✓ Service Worker activated');
        return self.clients.claim();
      })
  );
});

// Fetch event
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);

  // Never cache APIs and non-GET requests
  if (shouldNeverCache(url) || request.method !== 'GET' || url.protocol === 'chrome-extension:') {
    if (isNavigationRequest(request)) {
      event.respondWith(
        fetch(request)
          .catch(() => {
            return caches.match('login.php') || caches.match('./login.php') || new Response('Offline - Please check your connection', {
              status: 503,
              statusText: 'Service Unavailable',
              headers: new Headers({ 'Content-Type': 'text/plain' })
            });
          })
      );
    }
    return;
  }

  // Network-first for navigation requests
  if (isNavigationRequest(request)) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Only cache successful responses
          if (response && response.status === 200) {
            const cloned = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, cloned));
          }
          return response;
        })
        .catch(() => {
          return caches.match(request) || caches.match('login.php') || caches.match('./login.php');
        })
    );
    return;
  }

  // Cache-first for assets
  event.respondWith(
    caches.match(request)
      .then(cachedResponse => {
        if (cachedResponse) return cachedResponse;

        return fetch(request).then(networkResponse => {
          if (networkResponse && networkResponse.status === 200) {
            const cloned = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(request, cloned));
          }
          return networkResponse;
        });
      })
      .catch(() => {
        return new Response('Offline - Please check your connection', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({ 'Content-Type': 'text/plain' })
        });
      })
  );
});
