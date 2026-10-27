/* SysTienda · comportamiento global */
(function () {
  const $ = (s) => document.querySelector(s);
  const body = document.body;

  // Reloj
  const dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  function tick() {
    const d = new Date();
    const c = $('#clock'), t = $('#dateTxt');
    if (c) c.textContent = d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: false });
    if (t) t.textContent = `${dias[d.getDay()]}, ${d.getDate()} ${meses[d.getMonth()]} ${d.getFullYear()}`;
  }
  tick(); setInterval(tick, 15000);

  // Menú lateral (mini en escritorio / offcanvas en móvil)
  $('#btnToggle')?.addEventListener('click', () => {
    if (innerWidth > 991) {
      body.classList.toggle('sb-mini');
      try { localStorage.setItem('st-mini', body.classList.contains('sb-mini') ? '1' : '0'); } catch (e) {}
      setTimeout(() => window.dispatchEvent(new Event('resize')), 300);
    } else body.classList.toggle('sb-open');
  });

  // Tema claro / oscuro
  const root = document.documentElement, btn = $('#btnTheme');
  const paintIcon = () => { if (btn) btn.innerHTML = `<i class="bi bi-${root.dataset.theme === 'dark' ? 'sun' : 'moon-stars'}"></i>`; };
  paintIcon();
  btn?.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('st-theme', root.dataset.theme); } catch (e) {}
    paintIcon();
    window.dispatchEvent(new CustomEvent('themechange'));
  });

  // Atajos: Ctrl+K busca, F2 nueva venta
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); $('#globalSearch')?.focus(); }
    if (e.key === 'F2') { e.preventDefault(); location.href = APP.base + '/modulo.php?m=pos'; }
  });

  // Utilidades globales
  // Formato configurable (Empresa → Moneda y formatos): decimales, separador decimal y de miles, símbolo y posición
  window.fmt = (n, d) => {
    const F = APP.fmt; d = d === undefined ? F.dec : d;
    const [i, f] = Math.abs(Number(n || 0)).toFixed(d).split('.'), neg = Number(n || 0) < 0 && (+i > 0 || (f && +f > 0));
    return (neg ? '-' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, F.sm) + (d > 0 ? F.sd + f : '');
  };
  window.money = (n) => APP.fmt.pos === 'despues' ? fmt(n) + ' ' + APP.moneda : APP.moneda + ' ' + fmt(n);
})();

/* ---------- Utilidades compartidas ---------- */
window.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
window.debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

window.toast = (msg, type = 'success') => {
  const ic = { success: 'check-circle-fill', danger: 'x-octagon-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' }[type];
  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${type} border-0 shadow`; el.style.borderRadius = '12px';
  el.innerHTML = `<div class="d-flex"><div class="toast-body"><i class="bi bi-${ic} me-2"></i>${esc(msg)}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  document.getElementById('toasts').appendChild(el);
  const t = new bootstrap.Toast(el, { delay: 3500 }); t.show(); el.addEventListener('hidden.bs.toast', () => el.remove());
};

/** fetch JSON con CSRF. Lanza Error con el mensaje del servidor */
window.api = async (url, data = null, method = null) => {
  const opt = { credentials: 'same-origin', headers: { 'X-CSRF-Token': APP.csrf } };
  if (data !== null) { opt.method = method || 'POST'; opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data); }
  const r = await fetch(url.startsWith('http') || url.startsWith('/') ? url : `${APP.base}/api/${url}`, opt);
  if (r.status === 401) { location.href = APP.base + '/login.php'; throw new Error('Sesión expirada'); }
  let j; try { j = await r.json(); } catch (e) { throw new Error('Respuesta inválida del servidor'); }
  if (!r.ok || j.ok === false) { const er = new Error(j.error || 'Error inesperado'); er.data = j; throw er; }
  return j;
};

window.confirmBox = (msg, title = '¿Confirmar acción?', okText = 'Aceptar', danger = false) => new Promise((res) => {
  const m = document.getElementById('mdlConfirm'), inst = bootstrap.Modal.getOrCreateInstance(m);
  document.getElementById('cfTitle').textContent = title; document.getElementById('cfMsg').textContent = msg;
  const ok = document.getElementById('cfOk'); ok.textContent = okText; ok.style.background = danger ? 'linear-gradient(135deg,#f43f5e,#e11d48)' : '';
  let done = false; const fin = (v) => { if (!done) { done = true; res(v); } };
  ok.onclick = () => { fin(true); inst.hide(); }; m.addEventListener('hidden.bs.modal', () => fin(false), { once: true }); inst.show();
});

window.statusPill = (e) => ({ COMPLETADA: ['ok', 'Completada'], RECIBIDA: ['ok', 'Recibida'], ANULADA: ['bad', 'Anulada'], PENDIENTE: ['warn', 'Pendiente'] }[e] || ['neutral', e]);
window.pagerHtml = (page, pages, total, per) => {
  if (!total) return '';
  const from = (page - 1) * per + 1, to = Math.min(total, page * per); let b = '';
  const set = new Set([1, pages, page - 1, page, page + 1].filter((x) => x >= 1 && x <= pages)); let last = 0;
  [...set].sort((a, b) => a - b).forEach((p) => { if (p - last > 1) b += '<button disabled>…</button>'; b += `<button data-p="${p}" class="${p === page ? 'on' : ''}">${p}</button>`; last = p; });
  return `<div>Mostrando <b>${from}–${to}</b> de <b>${total}</b></div><div class="pg"><button data-p="${page - 1}" ${page <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>${b}<button data-p="${page + 1}" ${page >= pages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button></div>`;
};
window.exportCSV = (name, headers, rows) => {
  const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
  const csv = '﻿' + [headers, ...rows].map((r) => r.map(q).join(',')).join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = name + '.csv'; a.click();
};

// Cambiar contraseña
document.getElementById('frmPass')?.addEventListener('submit', async (e) => {
  e.preventDefault(); const f = Object.fromEntries(new FormData(e.target));
  if (f.nueva !== f.nueva2) return toast('Las contraseñas nuevas no coinciden', 'warning');
  try { await api('perfil.php', f); toast('Contraseña actualizada'); bootstrap.Modal.getInstance(document.getElementById('mdlPass')).hide(); e.target.reset(); } catch (x) { toast(x.message, 'danger'); }
});

// Búsqueda global
(function () {
  const inp = document.getElementById('globalSearch'), drop = document.getElementById('searchDrop'); if (!inp) return;
  const go = debounce(async () => {
    const v = inp.value.trim(); if (v.length < 2) { drop.style.display = 'none'; return; }
    try {
      const r = await api('search.php?q=' + encodeURIComponent(v)); let h = '';
      const sec = (t, arr, fn) => { if (arr.length) h += `<div class="sg">${t}</div>` + arr.map(fn).join(''); };
      sec('Productos', r.productos, (p) => `<a href="${APP.base}/modulo.php?m=productos&q=${encodeURIComponent(p.codigo)}"><span>${esc(p.nombre)}</span><b>${money(p.precio_venta)}</b></a>`);
      sec('Clientes', r.clientes, (c) => `<a href="${APP.base}/modulo.php?m=clientes&q=${encodeURIComponent(c.nombre)}"><span>${esc(c.nombre)}</span><small class="text-muted">${esc(c.documento || '')}</small></a>`);
      sec('Ventas', r.ventas, (c) => `<a href="${APP.base}/modulo.php?m=ventas&q=${encodeURIComponent(c.nro)}"><span>${esc(c.nro)}</span><b>${money(c.total)}</b></a>`);
      drop.innerHTML = h || '<div class="empty p-3">Sin resultados</div>'; drop.style.display = 'block';
    } catch (e) {}
  }, 250);
  inp.addEventListener('input', go); inp.addEventListener('blur', () => setTimeout(() => drop.style.display = 'none', 200));
})();

/* Selector de sucursal (administrador) */
document.getElementById('sucSelect')?.addEventListener('change', async e => {
  try { await api('sucursal.php', { id: +e.target.value }); location.reload(); } catch (x) { toast(x.message, 'danger'); }
});

/* ---- Impresión: térmica directa (ESC/POS) o vista del navegador ---- */
window.Imprimir = {
  vista(id) { window.open(`${APP.base}/print.php?id=${id}&auto=1`, '_blank', 'width=380,height=640'); },
  directa() { return APP.print && APP.print.modo !== 'navegador'; },
  async ticket(id, reimp = false) {
    if (!this.directa()) return this.vista(id);
    try { await api('imprimir.php', { a: 'ticket', id, reimp: reimp ? 1 : 0 }); toast('Ticket enviado a la impresora', 'success'); }
    catch (e) { toast(e.message + ' Se abre la vista de impresión.', 'warning'); this.vista(id); }
  },
  async cierre(id, fallbackUrl) {
    if (!this.directa()) { if (fallbackUrl) window.open(fallbackUrl, '_blank'); return; }
    try { await api('imprimir.php', { a: 'cierre', id }); toast('Cierre enviado a la impresora', 'success'); }
    catch (e) { toast(e.message, 'warning'); if (fallbackUrl) window.open(fallbackUrl, '_blank'); }
  },
  async cajon(silencioso = false) {
    if (!this.directa() || !APP.print.cajon) { if (!silencioso) toast('Activa la impresión directa y el cajón en Configuración → Impresión.', 'warning'); return; }
    try { await api('imprimir.php', { a: 'cajon' }); if (!silencioso) toast('Cajón abierto', 'success'); } catch (e) { toast(e.message, 'danger'); }
  },
};
document.addEventListener('click', (e) => {
  const a = e.target.closest('[data-ticket]'); if (!a || !Imprimir.directa()) return;
  e.preventDefault(); Imprimir.ticket(+a.dataset.ticket, true);
});

/* Móvil: toda tabla queda dentro de un contenedor con desplazamiento horizontal */
(() => {
  const wrap = (root) => root.querySelectorAll('table.tbl').forEach((t) => {
    const p = t.parentElement; if (!p || p.closest('.table-responsive') || p.classList.contains('tbl-scroll')) return;
    const w = document.createElement('div'); w.className = 'table-responsive tbl-scroll'; p.insertBefore(w, t); w.appendChild(t);
  });
  document.addEventListener('DOMContentLoaded', () => { wrap(document); new MutationObserver((ms) => ms.forEach((m) => m.addedNodes.forEach((n) => { if (n.nodeType === 1) { if (n.matches?.('table.tbl')) wrap(n.parentElement || document); else wrap(n); } }))).observe(document.body, { childList: true, subtree: true }); });
})();
