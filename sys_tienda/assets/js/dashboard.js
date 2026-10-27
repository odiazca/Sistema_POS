/* SysTienda · Dashboard dinámico */
(function () {
  const $ = (id) => document.getElementById(id);
  const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  const PAL = { brand: '#5b5ef4', violet: '#8b5cf6', teal: '#14b8a6', amber: '#f59e0b', rose: '#f43f5e', sky: '#0ea5e9', green: '#10b981' };
  const MET = { EFECTIVO: ['#10b981', 'bi-cash-coin'], YAPE: ['#7c3aed', 'bi-phone'], PLIN: ['#06b6d4', 'bi-phone-vibrate'], TARJETA: ['#5b5ef4', 'bi-credit-card-2-front'], TRANSFERENCIA: ['#0ea5e9', 'bi-bank'], CREDITO: ['#f59e0b', 'bi-journal-text'] };
  let rango = 7, DATA = null, charts = {}, timer;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const hexA = (hex, a) => { const n = parseInt(hex.slice(1), 16); return `rgba(${n >> 16},${(n >> 8) & 255},${n & 255},${a})`; };

  // ---------- Animación de contadores ----------
  function count(el, to, fmtFn, dur = 900) {
    const from = parseFloat(el.dataset.v || 0); el.dataset.v = to;
    const t0 = performance.now();
    (function step(t) {
      const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = fmtFn(from + (to - from) * e);
      if (p < 1) requestAnimationFrame(step);
    })(t0);
  }
  function trend(el, v) {
    if (v === null || v === undefined) { el.className = 'trend flat'; el.innerHTML = 'sin datos'; return; }
    const cls = v > 0 ? 'up' : v < 0 ? 'down' : 'flat', ic = v > 0 ? 'bi-arrow-up-right' : v < 0 ? 'bi-arrow-down-right' : 'bi-dash';
    el.className = 'trend ' + cls; el.innerHTML = `<i class="bi ${ic}"></i>${Math.abs(v)}%`;
  }

  // ---------- Config global de Chart.js ----------
  function setupChart() {
    Chart.defaults.font.family = "'Inter',sans-serif"; Chart.defaults.font.size = 11;
    Chart.defaults.color = css('--muted'); Chart.defaults.borderColor = css('--line');
    Chart.defaults.plugins.legend.display = false;
    Object.assign(Chart.defaults.plugins.tooltip, { backgroundColor: '#0f172a', padding: 12, cornerRadius: 10, titleFont: { weight: '700' }, boxPadding: 5, usePointStyle: true });
  }
  const mk = (id, cfg) => { charts[id]?.destroy(); charts[id] = new Chart($(id), cfg); return charts[id]; };

  function spark(id, data, color) {
    mk(id, { type: 'line', data: { labels: data.map((_, i) => i), datasets: [{ data, borderColor: color, borderWidth: 2, tension: .4, pointRadius: 0, fill: true,
      backgroundColor: (c) => { const g = c.chart.ctx.createLinearGradient(0, 0, 0, 50); g.addColorStop(0, hexA(color, .28)); g.addColorStop(1, hexA(color, 0)); return g; } }] },
      options: { responsive: true, maintainAspectRatio: false, animation: { duration: 900 }, plugins: { tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } }, layout: { padding: 0 } } });
  }

  // ---------- Render ----------
  function render(d) {
    DATA = d; const k = d.kpis;
    $('lastUpdate').textContent = d.actualizado;

    // KPIs
    count($('kVentas'), k.ventas_hoy.valor, money); trend($('tVentas'), k.ventas_hoy.var);
    count($('kTickets'), k.tickets_hoy.valor, (v) => Math.round(v)); trend($('tTickets'), k.tickets_hoy.var);
    count($('kUtil'), k.utilidad_hoy.valor, money); trend($('tUtil'), k.utilidad_hoy.var);
    $('kProm').textContent = money(k.ticket_prom.valor);
    count($('kMes'), k.mes.valor, money); trend($('tMes'), k.mes.var);
    $('kMesPrev').textContent = money(k.mes.anterior);

    // Hero / meta
    $('heroVentas').textContent = money(k.ventas_hoy.valor); $('heroTickets').textContent = k.tickets_hoy.valor;
    const pct = k.meta.objetivo ? k.meta.actual / k.meta.objetivo : 0, falta = Math.max(0, k.meta.objetivo - k.meta.actual);
    $('ringArc').style.strokeDashoffset = 339.3 * (1 - Math.min(1, pct));
    count($('ringPct'), Math.round(pct * 100), (v) => Math.round(v) + '%');
    $('metaTxt').textContent = money(k.meta.objetivo);
    $('metaFalta').textContent = falta ? `Faltan ${money(falta)}` : '¡Meta superada! 🎉';
    $('heroMsg').textContent = pct >= 1 ? '¡Superaste la meta del día, excelente trabajo!' : pct >= .6 ? 'Vas muy bien, ya estás cerca de la meta.' : 'Aún hay tiempo para alcanzar la meta del día.';

    // Caja
    const c = d.caja;
    if (c) {
      $('cajaEsperado').textContent = money(c.esperado); $('cajaIni').textContent = money(c.inicial); $('cajaEfe').textContent = money(c.efectivo);
      $('cajaDig').textContent = money(c.digital); $('cajaUser').textContent = c.cajero; $('cajaHora').textContent = c.apertura;
      $('cajaEstado').className = 'pill ok'; $('cajaEstado').innerHTML = '<i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Abierta';
    } else {
      $('cajaEsperado').textContent = '—'; $('cajaEstado').className = 'pill bad'; $('cajaEstado').textContent = 'Cerrada';
      ['cajaIni', 'cajaEfe', 'cajaDig', 'cajaUser', 'cajaHora'].forEach((i) => $(i).textContent = '—');
    }

    drawTend(d); drawHoras(d); drawCat(d); drawPagos(d); drawHeat(d.heatmap);
    drawLists(d);
    if ($('porSuc')) { const mx = Math.max(1, ...d.por_sucursal.map((x) => x.mes)); $('porSuc').innerHTML = d.por_sucursal.map((x) => `<div class="mb-3"><div class="d-flex justify-content-between small"><b><i class="bi bi-shop me-1"></i>${esc(x.nombre)}</b><span><b class="num">${money(x.hoy)}</b> hoy · ${x.tickets} ventas</span></div>
      <div class="progress mt-1" style="height:9px;border-radius:9px"><div class="progress-bar" style="width:${(x.mes / mx * 100).toFixed(0)}%;background:linear-gradient(90deg,#5b5ef4,#8b5cf6)"></div></div><div class="small text-muted mt-1">Mes: <b class="num">${money(x.mes)}</b></div></div>`).join('') || '<div class="empty">Sin datos</div>'; }
    $('cumples').innerHTML = d.cumples.length ? d.cumples.map((c) => `<div class="d-flex justify-content-between align-items-center py-1"><div><b class="small">${esc(c.nombre)}</b><div class="small text-muted">${c.hoy == 1 ? '<span class="text-danger fw-semibold">¡Hoy!</span>' : esc(c.dia)}</div></div>${c.telefono ? `<a class="act" target="_blank" title="Felicitar por WhatsApp" href="https://wa.me/51${String(c.telefono).replace(/\D/g, '').slice(-9)}?text=${encodeURIComponent('¡Feliz cumpleaños, ' + c.nombre.split(' ')[0] + '! 🎂 De parte de todo el equipo, te regalamos un descuento especial en tu próxima compra.')}"><i class="bi bi-whatsapp text-success"></i></a>` : ''}</div>`).join('') : '<div class="empty py-3"><i class="bi bi-gift"></i>Sin cumpleaños cercanos</div>';
    if ($('cotPend')) $('cotPend').textContent = d.cot_pendientes;
    $('invProd').textContent = d.resumen_inv.productos; $('invVal').textContent = money(d.resumen_inv.valor);
  }

  function drawTend(d) {
    const t = d.tendencia, ctx = $('chTend').getContext('2d');
    const g1 = ctx.createLinearGradient(0, 0, 0, 300); g1.addColorStop(0, hexA(PAL.brand, .30)); g1.addColorStop(1, hexA(PAL.brand, 0));
    mk('chTend', { type: 'line', data: { labels: t.labels, datasets: [
      { label: 'Ventas', data: t.ventas, borderColor: PAL.brand, backgroundColor: g1, fill: true, tension: .38, borderWidth: 2.5, pointRadius: t.labels.length > 30 ? 0 : 3, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderWidth: 2 },
      { label: 'Utilidad', data: t.utilidad, borderColor: PAL.teal, borderDash: [6, 5], fill: false, tension: .38, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5 }] },
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, align: 'end', labels: { usePointStyle: true, boxWidth: 8, padding: 18 } }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${money(c.parsed.y)}` } } },
        scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 12 } }, y: { beginAtZero: true, border: { display: false }, ticks: { callback: (v) => APP.moneda + (v >= 1000 ? (v / 1000) + 'k' : v) } } } } });
  }

  function drawHoras(d) {
    const h = d.horas;
    mk('chHoras', { type: 'bar', data: { labels: h.labels, datasets: [
      { label: 'Hoy', data: h.hoy, backgroundColor: PAL.brand, borderRadius: 6, borderSkipped: false, barPercentage: .7, categoryPercentage: .8 },
      { label: 'Ayer', data: h.ayer, backgroundColor: hexA(PAL.brand, .18), borderRadius: 6, borderSkipped: false, barPercentage: .7, categoryPercentage: .8 }] },
      options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, align: 'end', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${money(c.parsed.y)}` } } },
        scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, callback(v) { return this.getLabelForValue(v).slice(0, 2); } } }, y: { beginAtZero: true, border: { display: false }, ticks: { maxTicksLimit: 5 } } } } });
  }

  function drawCat(d) {
    const cats = d.categorias, tot = cats.reduce((a, b) => a + b.t, 0) || 1;
    mk('chCat', { type: 'doughnut', data: { labels: cats.map((c) => c.nombre), datasets: [{ data: cats.map((c) => c.t), backgroundColor: cats.map((c) => c.color), borderColor: css('--card'), borderWidth: 3, hoverOffset: 8 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { tooltip: { callbacks: { label: (c) => ` ${money(c.parsed)} (${(c.parsed / tot * 100).toFixed(1)}%)` } } } },
      plugins: [{ id: 'center', afterDraw(ch) { const { ctx, chartArea: a } = ch, x = (a.left + a.right) / 2, y = (a.top + a.bottom) / 2; ctx.save(); ctx.textAlign = 'center';
        ctx.fillStyle = css('--muted'); ctx.font = '500 11px Inter'; ctx.fillText('Total 30 días', x, y - 8); ctx.fillStyle = css('--text'); ctx.font = '800 19px Inter'; ctx.fillText(money(tot).replace(/\.\d+$/, ''), x, y + 14); ctx.restore(); } }] });
    $('legCat').innerHTML = cats.slice(0, 5).map((c) => `<li><span class="sw" style="background:${c.color}"></span>${esc(c.nombre)}<span class="amt num">${money(c.t).replace(/\.\d+$/, '')}</span><span class="pc">${(c.t / tot * 100).toFixed(0)}%</span></li>`).join('');
  }

  function drawPagos(d) {
    const tot = d.pagos.reduce((a, b) => a + b.t, 0) || 1;
    $('pagos').innerHTML = d.pagos.map((p) => { const [col, ic] = MET[p.m] || ['#64748b', 'bi-cash']; const pc = p.t / tot * 100;
      return `<div class="mb-3"><div class="d-flex align-items-center gap-2 mb-1"><span class="kpi-ic" style="width:30px;height:30px;font-size:.9rem;background:${hexA(col, .14)};color:${col}"><i class="bi ${ic}"></i></span>
        <span class="fw-semibold small">${p.m.charAt(0) + p.m.slice(1).toLowerCase()}</span><span class="ms-auto small fw-bold num">${pc.toFixed(0)}%</span></div>
        <div class="bar-s"><i style="width:0;background:${col}" data-w="${pc}"></i></div><div class="small text-muted mt-1">${money(p.t)} · ${p.n} ventas</div></div>`; }).join('');
    requestAnimationFrame(() => document.querySelectorAll('#pagos .bar-s i').forEach((i) => i.style.width = i.dataset.w + '%'));
  }

  function drawHeat(rows) {
    const dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'], map = {}; let max = 1;
    rows.forEach((r) => { map[r.w + '-' + r.h] = r.n; max = Math.max(max, r.n); });
    let h = '<div></div>'; for (let x = 7; x <= 21; x++) h += `<div class="hl">${x}</div>`;
    dias.forEach((dn, w) => { h += `<div class="dl">${dn}</div>`; for (let x = 7; x <= 21; x++) { const n = map[w + '-' + x] || 0, a = n ? .12 + .88 * (n / max) : 0;
      h += `<div class="c" style="${n ? 'background:' + hexA(PAL.brand, a) : ''}" title="${dn} ${x}:00 · ${n} ventas"></div>`; } });
    $('heat').innerHTML = h;
    $('heatLeg').innerHTML = [.1, .3, .5, .75, 1].map((a) => `<i style="width:16px;height:12px;border-radius:3px;background:${hexA(PAL.brand, a)}"></i>`).join('');
  }

  function drawLists(d) {
    const max = Math.max(...d.top.map((t) => t.total), 1);
    $('top').innerHTML = d.top.map((t, i) => `<div class="row-item"><div class="rank r${i + 1}">${i + 1}</div><div class="flex-grow-1" style="min-width:0">
      <div class="t text-truncate">${esc(t.nombre)}</div><div class="bar-s"><i style="width:0;background:${t.color}" data-w="${t.total / max * 100}"></i></div></div>
      <div class="text-end"><div class="fw-bold num small">${money(t.total)}</div><div class="s">${fmt(t.cant, 0)} und</div></div></div>`).join('');
    requestAnimationFrame(() => document.querySelectorAll('#top .bar-s i').forEach((i) => i.style.width = i.dataset.w + '%'));

    $('bajoTot').textContent = d.stock_bajo_total;
    $('bajo').innerHTML = d.stock_bajo.length ? d.stock_bajo.map((p) => { const pc = Math.min(100, p.stock / p.stock_minimo * 100), crit = p.stock <= p.stock_minimo * .4;
      return `<div class="row-item"><div class="flex-grow-1" style="min-width:0"><div class="t text-truncate">${esc(p.nombre)}</div>
        <div class="bar-s"><i style="width:${pc}%;background:${crit ? PAL.rose : PAL.amber}"></i></div></div>
        <span class="pill ${crit ? 'bad' : 'warn'}">${fmt(p.stock, 0)} / ${fmt(p.stock_minimo, 0)}</span></div>`; }).join('') : '<div class="text-muted small py-3">Todo el stock está en orden ✅</div>';
    $('vence').innerHTML = d.por_vencer.length ? `<div class="small fw-bold mt-3 mb-1"><i class="bi bi-hourglass-split text-warning me-1"></i>Por vencer (30 días)</div>` +
      d.por_vencer.map((p) => `<div class="d-flex justify-content-between small py-1"><span class="text-truncate me-2">${esc(p.nombre)}</span><span class="pill ${p.dias <= 10 ? 'bad' : 'warn'}">${p.dias} d</span></div>`).join('') : '';

    const est = { COMPLETADA: ['ok', 'Completada'], ANULADA: ['bad', 'Anulada'], PENDIENTE: ['warn', 'Pendiente'] };
    $('recientes').innerHTML = d.recientes.map((r) => { const [c, l] = est[r.estado]; const m = MET[r.metodo_pago] || ['#64748b', 'bi-cash'];
      return `<tr><td><div class="d-flex align-items-center gap-2"><span class="kpi-ic ic-indigo" style="width:34px;height:34px;font-size:.95rem"><i class="bi bi-receipt"></i></span>
        <div><b>${esc(r.nro)}</b><div class="s small text-muted">${esc(r.tipo)}</div></div></div></td><td>${esc(r.cliente)}</td>
        <td>${r.es_hoy ? r.hora : r.dia + ' ' + r.hora}</td><td><i class="bi ${m[1]} me-1" style="color:${m[0]}"></i>${r.metodo_pago.charAt(0) + r.metodo_pago.slice(1).toLowerCase()}</td>
        <td><span class="pill ${c}">${l}</span></td><td class="r fw-bold num">${money(r.total)}</td></tr>`; }).join('');
  }

  // ---------- Carga ----------
  async function load() {
    $('btnRefresh')?.classList.add('disabled');
    try {
      const r = await fetch(`${APP.base}/api/dashboard_data.php?rango=${rango}`, { credentials: 'same-origin' });
      if (r.status === 401) return location.href = APP.base + '/login.php';
      render(await r.json());
    } catch (e) { console.error(e); }
    $('btnRefresh')?.classList.remove('disabled');
  }

  document.querySelectorAll('#segRango button').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('#segRango button').forEach((x) => x.classList.remove('on')); b.classList.add('on'); rango = +b.dataset.r; load();
  }));
  $('btnRefresh')?.addEventListener('click', load);
  window.addEventListener('themechange', () => { setupChart(); if (DATA) render(DATA); });

  setupChart(); load();
  timer = setInterval(load, 60000);   // actualización automática cada minuto
})();
