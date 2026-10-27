/* SysTienda · Service Worker. No guarda datos de la sesión: solo archivos estáticos y una página sin conexión. */
const V = 'systienda-v1';
const BASE = new URL('./', self.location).pathname;   // carpeta donde vive el sistema
const OFFLINE = BASE + 'offline.html';

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(V).then((c) => c.addAll([OFFLINE, BASE + 'assets/pwa/icon-192.png'])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k.startsWith('systienda-') && k !== V).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || r.headers.has('range')) return;
  const p = u.pathname.slice(BASE.length);
  if (p.startsWith('api/') || p.startsWith('backups/')) return;

  // Páginas: siempre desde la red (datos al día); si no hay conexión, pantalla amigable
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(() => caches.match(OFFLINE)));
    return;
  }
  // Estáticos: vendor y archivos con versión → caché primero; el resto → caché y actualiza en segundo plano
  if (p.startsWith('assets/')) {
    const cacheFirst = p.startsWith('assets/vendor/') || p.startsWith('assets/pwa/') || u.search.includes('v=');
    e.respondWith(caches.open(V).then(async (c) => {
      const hit = await c.match(r);
      const net = fetch(r).then((res) => { if (res.ok) c.put(r, res.clone()); return res; }).catch(() => hit);
      return cacheFirst ? (hit || net) : (hit ? (net, hit) : net);
    }));
  }
});
