/* CRM · Segmentos RFM */
(function () {
  const $ = (s) => document.querySelector(s); const C = window.CRM;
  async function load() {
    const d = await api('crm.php?a=segmentos'); const u = d.umbrales;
    const max = Math.max(...Object.values(d.matriz).flatMap((r) => Object.values(r)), 1);
    const m = (r, f) => { const n = (d.matriz[r] || {})[f] || 0; const a = n / max; return `<div class="c ${a > .55 ? 'hi' : ''}" style="--a:${a.toFixed(2)}" title="Recencia ${r} · Frecuencia ${f}: ${n} cliente(s)">${n || ''}</div>`; };
    let grid = '<div class="hd"></div>' + [1, 2, 3, 4, 5].map((f) => `<div class="hd">F${f}</div>`).join('');
    for (let r = 5; r >= 1; r--) grid += `<div class="hd">R${r}</div>` + [1, 2, 3, 4, 5].map((f) => m(r, f)).join('');
    $('#crmRoot').innerHTML = `<div class="row g-3 mb-3">
      <div class="col-md-4">${C.stat('bi-people-fill', 'ic-indigo', 'Clientes analizados', d.total)}</div>
      <div class="col-md-4">${C.stat('bi-cash-stack', 'ic-green', 'Ventas históricas de estos clientes', money(d.monto))}</div>
      <div class="col-md-4">${C.stat('bi-person-plus', 'ic-sky', 'Aún sin compras', (d.segmentos.find((s) => s.key === 'prospectos') || {}).n || 0, 'Oportunidad de primera compra')}</div></div>
    <div class="row g-3 mb-3">${d.segmentos.filter((s) => s.n > 0 || ['campeones', 'en_riesgo', 'no_perder'].includes(s.key)).map((s) => `<div class="col-xl-4 col-md-6"><div class="cardx seg-card" style="--c:${s.color}">
      <div class="d-flex align-items-center gap-3"><div class="ic"><i class="bi ${s.icono}"></i></div><div class="flex-grow-1"><b>${s.nombre}</b><div class="small text-muted">${esc(s.desc)}</div></div><div class="big num">${s.n}</div></div>
      <dl><dt>% de clientes</dt><dd>${s.pct}%</dd><dt>% del ingreso</dt><dd>${s.pct_monto}%</dd><dt>Ventas históricas</dt><dd>${money(s.monto)}</dd><dt>Ticket promedio</dt><dd>${money(s.ticket_prom)}</dd><dt>Última compra (prom.)</dt><dd>${s.dias_prom === null ? '—' : 'hace ' + s.dias_prom + ' d'}</dd></dl>
      <div class="small"><i class="bi bi-lightbulb text-warning"></i> ${esc(s.accion)}</div>
      <div class="d-flex gap-2 mt-auto"><a class="btn-soft flex-fill justify-content-center" href="${APP.base}/modulo.php?m=crm-clientes&seg=${s.key}"><i class="bi bi-people"></i> Ver clientes</a>
      ${s.n ? `<a class="btn-soft flex-fill justify-content-center" href="${APP.base}/modulo.php?m=crm-campanas&nueva=1&seg=rfm:${s.key}"><i class="bi bi-megaphone"></i> Campaña</a>` : ''}</div></div></div>`).join('')}</div>
    <div class="row g-3"><div class="col-lg-6"><div class="cardx"><div class="cardx-head"><div><h6>Mapa de calor Recencia × Frecuencia</h6><small>Cada celda muestra cuántos clientes cumplen esa combinación</small></div></div><div class="rfm-m">${grid}</div></div></div>
      <div class="col-lg-6"><div class="cardx"><div class="cardx-head"><div><h6>¿Cómo se calcula?</h6><small>Puntajes de 1 a 5 para cada cliente</small></div></div>
        <div class="small"><p class="mb-2"><b>R · Recencia</b> (qué tan reciente compró): 5 si compró en los últimos ${u[0]} días, 4 hasta ${u[1]}, 3 hasta ${u[2]}, 2 hasta ${u[3]} y 1 si pasó más tiempo.</p>
        <p class="mb-2"><b>F · Frecuencia</b> (cuántas veces compró): 5 con 12 o más compras, 4 con 6+, 3 con 3+, 2 con 2 y 1 con una sola.</p>
        <p class="mb-2"><b>M · Valor</b> (cuánto gastó, neto de devoluciones): se compara contra el resto de tus clientes en quintiles.</p>
        <p class="mb-0 text-muted">El <b>puntaje de salud</b> (0-100) resume R + F + M. Se recalcula automáticamente con cada venta, sin que tengas que hacer nada. Los umbrales de recencia se pueden ajustar en la configuración <code>crm_rec_dias</code>.</p></div></div></div></div>`;
  }
  document.addEventListener('crm:cambio', load); load();
})();
