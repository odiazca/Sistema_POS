/* PWA: service worker, instalación y aviso de conexión */
(() => {
  const base = (window.APP && APP.base) || document.documentElement.dataset.base || '';
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1' || location.hostname.endsWith('.test'))) {
    addEventListener('load', () => navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(() => {}));
  }
  // Instalar
  let ev = null;
  const items = () => document.querySelectorAll('[data-pwa-install]');
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); ev = e; items().forEach((i) => i.hidden = false); });
  addEventListener('appinstalled', () => { ev = null; items().forEach((i) => i.hidden = true); if (window.toast) toast('Aplicación instalada'); });
  document.addEventListener('DOMContentLoaded', () => {
    if (!standalone && ios) items().forEach((i) => i.hidden = false);
    document.addEventListener('click', async (e) => {
      if (!e.target.closest('[data-pwa-install]')) return;
      e.preventDefault();
      if (ev) { ev.prompt(); await ev.userChoice; ev = null; items().forEach((i) => i.hidden = true); }
      else if (window.confirmBox) confirmBox('En Safari toca el botón Compartir y elige «Añadir a pantalla de inicio».', 'Instalar en iPhone / iPad', 'Entendido');
    });
  });
  // Aviso sin conexión
  const bar = document.createElement('div'); bar.className = 'net-bar'; bar.innerHTML = '<i class="bi bi-wifi-off me-2"></i>Sin conexión · las ventas no se guardarán hasta reconectar'; document.addEventListener('DOMContentLoaded', () => document.body.appendChild(bar));
  const upd = () => document.body && document.body.classList.toggle('is-offline', !navigator.onLine);
  addEventListener('online', upd); addEventListener('offline', upd); document.addEventListener('DOMContentLoaded', upd);
})();
