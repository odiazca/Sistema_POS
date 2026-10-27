/* CRM · Panel principal */
(function () {
  const $ = (s) => document.querySelector(s); let charts = [];
  const C = window.CRM;
  const card = (t, sub, body, extra = '') => `<div class="cardx"><div class="cardx-head"><div><h6>${t}</h6>${sub ? `<small>${sub}</small>` : ''}</div>${extra}</div>${body}</div>`;
  const wa = (r) => (r.wa ? `<a class="ico-btn wa" target="_blank" rel="noopener" href="${C.waLink(r.wa)}" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>` : '');

  function render(d) {
    const k = d.kpi, p = d.pipeline, t = d.tareas;
    const kp = `<div class="row g-3 mb-3">
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-people-fill', 'ic-indigo', 'Clientes activos', k.clientes, `+${k.nuevos_mes} este mes`)}</div>
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-arrow-repeat', 'ic-green', 'Tasa de recompra', k.recompra + '%', 'Compraron 2+ veces')}</div>
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-gem', 'ic-sky', 'Valor por cliente', money(k.ltv), 'Gasto histórico prom.')}</div>
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-receipt', 'ic-teal', 'Ticket promedio', money(k.ticket), `${k.activos_90} activos (90 d)`)}</div>
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-exclamation-triangle-fill', 'ic-amber', 'Clientes en riesgo', k.en_riesgo, `${money(k.valor_riesgo)} en juego`)}</div>
      <div class="col-xl-4 col-md-4 col-6">${C.stat('bi-bullseye', 'ic-rose', 'Pipeline ponderado', money(p.ponderado), `${p.abiertas} oportunidades`)}</div></div>`;

    const segs = d.segmentos.filter((s) => s.n > 0);
    const segCard = card('Salud de la cartera', 'Clientes por segmento RFM', `<div class="d-flex align-items-center gap-3 flex-wrap"><div style="width:150px;height:150px;position:relative;flex-shrink:0"><canvas id="chSeg"></canvas></div>
      <div class="flex-grow-1" style="min-width:170px">${segs.map((s) => `<a class="d-flex align-items-center gap-2 py-1 crm-link" href="${APP.base}/modulo.php?m=crm-clientes&seg=${s.key}"><span style="width:10px;height:10px;border-radius:3px;background:${s.color}"></span><span class="flex-grow-1 small">${s.nombre}</span><b class="small">${s.n}</b></a>`).join('')}</div></div>`,
      `<a class="btn-soft" href="${APP.base}/modulo.php?m=crm-segmentos">Ver segmentos</a>`);

    const riesgo = card('Necesitan atención', 'Buenos clientes que dejaron de venir', d.riesgo.length ? `<div class="mini-list">${d.riesgo.map((r) => `<div class="it">${C.av(r.nombre)}<div class="grow"><div class="t crm-link" data-ficha="${r.id}">${esc(r.nombre)}</div><div class="s">${r.dias} días sin comprar · ${money(r.monto)} histórico</div></div>${wa(r)}<button class="ico-btn" title="Crear tarea" data-tarea="${r.id}" data-n="${esc(r.nombre)}"><i class="bi bi-plus-lg"></i></button></div>`).join('')}</div>` : C.empty('bi-emoji-smile', 'Ningún cliente valioso en riesgo. ¡Buen trabajo!'),
      `<a class="btn-soft" href="${APP.base}/modulo.php?m=crm-campanas&nueva=1&seg=rfm:en_riesgo">Reactivar</a>`);

    const tareas = card('Mi agenda', `${t.vencidas} vencidas · ${t.hoy} para hoy`, t.proximas.length ? `<div class="mini-list">${t.proximas.map((x) => `<div class="it"><div class="ck task-ck" data-ck="${x.id}" style="width:22px;height:22px;border-radius:7px;border:2px solid var(--line);cursor:pointer;flex-shrink:0"></div><div class="grow"><div class="t"><span class="pri ${x.prioridad}"></span> ${esc(x.titulo)}</div><div class="s ${x.vencida ? 'text-danger fw-bold' : ''}"><i class="bi ${C.TAREA_IC[x.tipo]}"></i> ${C.venceTxt(x)}${x.cliente ? ' · ' + esc(x.cliente) : ''}</div></div></div>`).join('')}</div>` : C.empty('bi-check2-all', 'Sin tareas pendientes.'),
      `<a class="btn-soft" href="${APP.base}/modulo.php?m=crm-tareas">Ver todas</a>`);

    const pip = card('Pipeline de ventas', 'Oportunidades en curso', `<div class="row g-2 text-center"><div class="col-6"><div class="fx-k"><small>Abiertas</small><b>${p.abiertas}</b></div></div><div class="col-6"><div class="fx-k"><small>Valor total</small><b>${money(p.valor)}</b></div></div>
      <div class="col-6"><div class="fx-k"><small>Ponderado</small><b>${money(p.ponderado)}</b></div></div><div class="col-6"><div class="fx-k"><small>Ganado este mes</small><b class="text-success">${money(p.valor_ganado_mes)}</b></div></div></div>`, `<a class="btn-soft" href="${APP.base}/modulo.php?m=crm-pipeline">Abrir pipeline</a>`);

    const cum = card('Cumpleaños próximos', 'Próximos 14 días', d.cumples.length ? `<div class="mini-list">${d.cumples.map((r) => `<div class="it">${C.av(r.nombre)}<div class="grow"><div class="t crm-link" data-ficha="${r.id}">${esc(r.nombre)}</div><div class="s">${r.en_dias === 0 ? '<b class="text-success">¡Hoy!</b>' : 'en ' + r.en_dias + ' día(s)'}</div></div>${wa(r)}</div>`).join('')}</div>` : C.empty('bi-cake2', 'No hay cumpleaños cerca.'));

    const top = card('Mejores clientes', 'Por valor de compra', d.top.length ? `<div class="mini-list">${d.top.map((r, i) => `<div class="it"><b class="text-muted" style="width:16px">${i + 1}</b>${C.av(r.nombre)}<div class="grow"><div class="t crm-link" data-ficha="${r.id}">${esc(r.nombre)}</div><div class="s">${r.n} compras · ${C.ago(r.dias)}</div></div><b class="num">${money(r.monto)}</b></div>`).join('')}</div>` : C.empty('bi-trophy', 'Aún no hay compras de clientes registrados.'));

    const camps = card('Campañas recientes', `Ventas atribuidas este mes: <b>${money(k.atribuido_mes)}</b>`, d.campanas.length ? `<div class="table-responsive"><table class="tbl"><thead><tr><th>Campaña</th><th>Segmento</th><th class="r">Enviados</th><th class="r">Compraron</th><th class="r">Ventas</th></tr></thead><tbody>${d.campanas.map((c) => `<tr><td><b>${esc(c.nombre)}</b></td><td>${esc(c.segmento_nombre)}</td><td class="r">${c.enviados}/${c.total}</td><td class="r">${c.compraron} <small class="text-muted">(${c.conversion}%)</small></td><td class="r num">${money(c.monto)}</td></tr>`).join('')}</tbody></table></div>` : C.empty('bi-megaphone', 'Aún no has lanzado campañas.'),
      `<a class="btn-soft" href="${APP.base}/modulo.php?m=crm-campanas">Ver campañas</a>`);

    const nuevos = card('Clientes nuevos', 'Registrados por mes', '<div style="height:170px"><canvas id="chNuevos"></canvas></div>');

    $('#crmRoot').innerHTML = kp + `<div class="row g-3 mb-3"><div class="col-lg-4">${segCard}</div><div class="col-lg-4">${riesgo}</div><div class="col-lg-4">${tareas}</div></div>
      <div class="row g-3 mb-3"><div class="col-lg-4">${pip}</div><div class="col-lg-4">${cum}</div><div class="col-lg-4">${top}</div></div>
      <div class="row g-3"><div class="col-lg-8">${camps}</div><div class="col-lg-4">${nuevos}</div></div>`;

    charts.forEach((c) => c.destroy()); charts = [];
    const css = getComputedStyle(document.documentElement), txt = css.getPropertyValue('--muted').trim() || '#64748b', line = css.getPropertyValue('--line').trim() || '#e8ebf3';
    if (segs.length) charts.push(new Chart($('#chSeg'), { type: 'doughnut', data: { labels: segs.map((s) => s.nombre), datasets: [{ data: segs.map((s) => s.n), backgroundColor: segs.map((s) => s.color), borderWidth: 0 }] }, options: { cutout: '68%', plugins: { legend: { display: false } }, maintainAspectRatio: false } }));
    const mn = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    charts.push(new Chart($('#chNuevos'), { type: 'bar', data: { labels: Object.keys(d.meses).map((m) => mn[+m.slice(5) - 1]), datasets: [{ data: Object.values(d.meses), backgroundColor: '#5b5ef4', borderRadius: 7 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: txt } }, y: { beginAtZero: true, ticks: { color: txt, precision: 0 }, grid: { color: line } } } } }));
  }

  async function load() { try { render(await api('crm.php?a=dashboard')); } catch (e) { $('#crmRoot').innerHTML = C.empty('bi-exclamation-circle', esc(e.message)); } }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-tarea]'); if (b) C.tareaForm({ cliente: { id: +b.dataset.tarea, nombre: b.dataset.n }, onSave: load });
    const k = e.target.closest('[data-ck]'); if (k) { await api('crm.php', { a: 'tarea_toggle', id: k.dataset.ck }); toast('Tarea completada'); load(); }
  });
  $('#bTarea').onclick = () => C.tareaForm({ onSave: load });
  $('#bOpp').onclick = () => C.oppForm({ onSave: load });
  document.addEventListener('crm:cambio', load);
  window.addEventListener('themechange', load);
  load();
})();
