/**
 * service-worker.js — CLAUTMET Intranet
 * - Cache estática (assets) con stale-while-revalidate
 * - Push notifications + click handler
 * - Network-first para HTML/JSON
 */

const VERSION = 'clautmet-v1';
const STATIC_CACHE = `${VERSION}-static`;
const RUNTIME_CACHE = `${VERSION}-runtime`;

const CORE_ASSETS = [
  '/',
  '/index.html',
  '/login.html',
  '/assets/css/build.css',
  '/assets/fonts/inter/InterVariable.woff2',
  '/assets/img/logo/logo-light.png',
  '/assets/img/logo/logo-dark.png',
  '/manifest.json',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => cache.addAll(CORE_ASSETS).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => !k.startsWith(VERSION)).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // No cachear API
  if (url.pathname.startsWith('/api/')) return;

  // HTML / JSON: network first
  if (req.mode === 'navigate' || req.headers.get('accept')?.includes('text/html')) {
    e.respondWith(
      fetch(req).then(res => {
        const copy = res.clone();
        caches.open(RUNTIME_CACHE).then(c => c.put(req, copy));
        return res;
      }).catch(() => caches.match(req).then(r => r || caches.match('/index.html')))
    );
    return;
  }

  // Assets: cache first con revalidación
  e.respondWith(
    caches.match(req).then(cached => {
      const network = fetch(req).then(res => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(RUNTIME_CACHE).then(c => c.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});

/* ===== Push notifications ===== */
self.addEventListener('push', e => {
  const data = (() => {
    try { return e.data?.json() || {}; }
    catch { return { title: 'CLAUTMET', body: e.data?.text() || '' }; }
  })();

  const title = data.title || 'Clúster Automotriz Metropolitano';
  const options = {
    body: data.body || '',
    icon: '/assets/img/logo/logo-light.png',
    badge: '/assets/img/logo/logo-light.png',
    data: { url: data.url || '/app/notificaciones.html', ...data },
    tag: data.tag || 'clautmet-default',
    renotify: !!data.renotify,
  };
  e.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = e.notification.data?.url || '/app/dashboard.html';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const c of list) {
        if (c.url.includes(url) && 'focus' in c) return c.focus();
      }
      return self.clients.openWindow(url);
    })
  );
});
