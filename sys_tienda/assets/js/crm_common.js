/* CRM · utilidades compartidas, ficha 360°, formularios de tareas y oportunidades */
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const IS_ADMIN = APP.rol === 1;
  const SEG = {
    campeones: ['Campeones', '#10b981', 'bi-trophy-fill'], leales: ['Leales', '#5b5ef4', 'bi-heart-fill'], prometedores: ['Prometedores', '#0ea5e9', 'bi-graph-up-arrow'],
    nuevos: ['Nuevos', '#14b8a6', 'bi-stars'], ocasionales: ['Ocasionales', '#8b5cf6', 'bi-cart'], en_riesgo: ['En riesgo', '#f59e0b', 'bi-exclamation-triangle-fill'],
    no_perder: ['No perder', '#f43f5e', 'bi-fire'], dormidos: ['Dormidos', '#64748b', 'bi-moon-stars-fill'], prospectos: ['Prospectos', '#94a3b8', 'bi-person-plus'],
  };
  const TL = {
    venta: ['bi-receipt', '#10b981'], pago: ['bi-cash-coin', '#14b8a6'], cotizacion: ['bi-file-earmark-text', '#0ea5e9'], devolucion: ['bi-arrow-return-left', '#f43f5e'],
    NOTA: ['bi-sticky', '#5b5ef4'], LLAMADA: ['bi-telephone', '#0ea5e9'], VISITA: ['bi-geo-alt', '#f59e0b'], WHATSAPP: ['bi-whatsapp', '#22c55e'], EMAIL: ['bi-envelope', '#8b5cf6'], REUNION: ['bi-people', '#14b8a6'], SISTEMA: ['bi-gear', '#94a3b8'],
  };
  const TAREA_IC = { LLAMAR: 'bi-telephone', WHATSAPP: 'bi-whatsapp', VISITA: 'bi-geo-alt', EMAIL: 'bi-envelope', COBRAR: 'bi-cash-coin', SEGUIMIENTO: 'bi-arrow-repeat', OTRO: 'bi-check2-square' };
  const TAREA_NOM = { LLAMAR: 'Llamar', WHATSAPP: 'WhatsApp', VISITA: 'Visita', EMAIL: 'Correo', COBRAR: 'Cobrar', SEGUIMIENTO: 'Seguimiento', OTRO: 'Otro' };

  const fd = (s) => (s ? `${s.slice(8, 10)}/${s.slice(5, 7)}/${s.slice(0, 4)}` : '—');
  const fdt = (s) => (s ? `${s.slice(8, 10)}/${s.slice(5, 7)} ${s.slice(11, 16)}` : '—');
  const ago = (d) => (d === null || d === undefined ? 'Nunca' : d === 0 ? 'Hoy' : d === 1 ? 'Ayer' : `hace ${d} d`);
  const colorOf = (t) => { let h = 0; for (const c of String(t)) h = (h * 31 + c.charCodeAt(0)) % 360; return `hsl(${h} 60% 50%)`; };
  const ini = (n) => String(n || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
  const av = (n, big) => `<div class="crm-av ${big ? 'lg' : ''}" style="--c:${colorOf(n)}">${esc(ini(n))}</div>`;
  const segPill = (k) => { const s = SEG[k] || SEG.prospectos; return `<span class="seg-pill" style="--c:${s[1]}"><i class="bi ${s[2]}"></i>${s[0]}</span>`; };
  const ring = (sc, big) => { const c = sc >= 75 ? '#10b981' : sc >= 50 ? '#5b5ef4' : sc >= 30 ? '#f59e0b' : '#f43f5e'; return `<div class="score-ring ${big ? 'lg' : ''}" style="--p:${sc};--c:${sc ? c : '#94a3b8'}" title="Puntaje de salud del cliente"><b>${sc || '–'}</b></div>`; };
  const tagChip = (t) => `<span class="tag-chip" style="--c:${esc(t.color)}">${esc(t.nombre)}</span>`;
  const waLink = (num, msg) => `https://wa.me/${num}${msg ? '?text=' + encodeURIComponent(msg) : ''}`;
  const empty = (ic, t) => `<div class="crm-empty"><i class="bi ${ic}"></i>${t}</div>`;
  const stat = (ic, c, l, v, s) => `<div class="cardx crm-stat"><div class="kpi-ic ${c}"><i class="bi ${ic}"></i></div><div><div class="l">${l}</div><div class="v num">${v}</div>${s ? `<div class="s">${s}</div>` : ''}</div></div>`;
  const toLocalInput = (s) => (s ? s.slice(0, 16).replace(' ', 'T') : '');
  const venceTxt = (t) => { if (!t.vence) return 'Sin fecha'; const hoy = new Date().toISOString().slice(0, 10); const d = t.vence.slice(0, 10); return (d === hoy ? 'Hoy ' : d === new Date(Date.now() + 864e5).toISOString().slice(0, 10) ? 'Mañana ' : fd(d) + ' ') + t.vence.slice(11, 16); };

  /* ---------- caché de catálogos ---------- */
  let _users, _etq;
  const usuarios = () => (_users ||= api('crm.php?a=usuarios'));
  const etiquetas = (force) => { if (force) _etq = null; return (_etq ||= api('crm.php?a=etiquetas')); };

  /* ---------- selector de cliente ---------- */
  function picker(el, sel) {
    el.innerHTML = '<div class="picker"><input class="form-control" placeholder="Buscar cliente por nombre, documento o teléfono…" autocomplete="off"><div class="drop" hidden></div></div>';
    const inp = $('input', el), drop = $('.drop', el); el._sel = sel || null; if (sel) inp.value = sel.nombre;
    const search = debounce(async () => {
      const q = inp.value.trim(); if (q.length < 2) { drop.hidden = true; return; }
      const r = await api('crm.php?a=buscar&q=' + encodeURIComponent(q));
      drop.innerHTML = r.rows.map((c) => `<button type="button" data-id="${c.id}" data-n="${esc(c.nombre)}">${av(c.nombre)}<span><b class="d-block">${esc(c.nombre)}</b><small class="text-muted">${esc(c.documento || '')} ${esc(c.telefono || '')}</small></span></button>`).join('') || '<div class="p-3 small text-muted">Sin resultados</div>';
      drop.hidden = false;
    }, 250);
    inp.addEventListener('input', () => { el._sel = null; search(); });
    drop.addEventListener('click', (e) => { const b = e.target.closest('button'); if (!b) return; el._sel = { id: +b.dataset.id, nombre: b.dataset.n }; inp.value = b.dataset.n; drop.hidden = true; });
    document.addEventListener('click', (e) => { if (!el.contains(e.target)) drop.hidden = true; });
  }

  /* ---------- modales (se inyectan una vez) ---------- */
  function inject() {
    if ($('#crmMdlTarea')) return;
    document.body.insertAdjacentHTML('beforeend', `
<div class="modal fade" id="crmMdlTarea" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><form id="crmFrmTarea">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="crmTareaTit">Nueva tarea</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">¿Qué hay que hacer?</label><input class="form-control" name="titulo" maxlength="160" required placeholder="Ej.: Llamar para confirmar el pedido"></div>
    <div class="col-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo">${Object.entries(TAREA_NOM).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}</select></div>
    <div class="col-6"><label class="form-label">Prioridad</label><select class="form-select" name="prioridad"><option value="ALTA">Alta</option><option value="MEDIA" selected>Media</option><option value="BAJA">Baja</option></select></div>
    <div class="col-6"><label class="form-label">Vence</label><input type="datetime-local" class="form-control" name="vence"></div>
    <div class="col-6" id="crmTareaAsig"><label class="form-label">Asignada a</label><select class="form-select" name="asignado_id"></select></div>
    <div class="col-12"><label class="form-label">Cliente</label><div id="crmTareaCli"></div></div>
  </div></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos">Guardar tarea</button></div></form></div></div></div>
<div class="modal fade" id="crmMdlOpp" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><form id="crmFrmOpp">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold" id="crmOppTit">Nueva oportunidad</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="160" required placeholder="Ej.: Abastecimiento mensual de abarrotes"></div>
    <div class="col-12"><label class="form-label">Cliente</label><div id="crmOppCli"></div></div>
    <div class="col-6"><label class="form-label">Valor estimado (${esc(APP.moneda)})</label><input type="number" step="0.01" min="0" class="form-control" name="valor" value="0"></div>
    <div class="col-6"><label class="form-label">Etapa</label><select class="form-select" name="etapa_id"></select></div>
    <div class="col-6"><label class="form-label">Cierre estimado</label><input type="date" class="form-control" name="cierre_estimado"></div>
    <div class="col-6"><label class="form-label">Origen</label><select class="form-select" name="origen"><option value="">—</option></select></div>
    <div class="col-12"><label class="form-label">Responsable</label><select class="form-select" name="responsable_id"></select></div>
  </div></div>
  <div class="modal-footer border-0 justify-content-between"><button type="button" class="btn btn-light text-danger" id="crmOppDel" hidden><i class="bi bi-trash"></i> Eliminar</button><div class="d-flex gap-2 ms-auto"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos">Guardar</button></div></div></form></div></div></div>
<div class="offcanvas offcanvas-end crm-oc" id="crmFicha" tabindex="-1"><div class="offcanvas-header"><h6 class="offcanvas-title fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Cliente 360°</h6><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body" id="crmFichaBody"></div></div>`);
  }

  async function tareaForm({ cliente = null, tarea = null, onSave } = {}) {
    inject(); const el = $('#crmMdlTarea'), f = $('#crmFrmTarea'), m = bootstrap.Modal.getOrCreateInstance(el);
    const us = await usuarios(); f.reset();
    f.asignado_id.innerHTML = us.rows.map((u) => `<option value="${u.id}">${esc(u.nombre)}</option>`).join(''); $('#crmTareaAsig').hidden = !IS_ADMIN;
    f.asignado_id.value = tarea?.asignado_id || us.yo; $('#crmTareaTit').textContent = tarea ? 'Editar tarea' : 'Nueva tarea';
    if (tarea) { f.titulo.value = tarea.titulo; f.tipo.value = tarea.tipo; f.prioridad.value = tarea.prioridad; f.vence.value = toLocalInput(tarea.vence); }
    else { const d = new Date(Date.now() + 864e5); d.setHours(10, 0, 0, 0); f.vence.value = new Date(d - d.getTimezoneOffset() * 6e4).toISOString().slice(0, 16); }
    const c = tarea?.cliente_id ? { id: tarea.cliente_id, nombre: tarea.cliente } : cliente; picker($('#crmTareaCli'), c);
    f.onsubmit = async (e) => {
      e.preventDefault(); const cl = $('#crmTareaCli')._sel;
      try { await api('crm.php', { a: 'tarea_guardar', id: tarea?.id || 0, titulo: f.titulo.value, tipo: f.tipo.value, prioridad: f.prioridad.value, vence: f.vence.value, asignado_id: f.asignado_id.value, cliente_id: cl?.id || 0, oportunidad_id: tarea?.oportunidad_id || 0 });
        m.hide(); toast('Tarea guardada'); changed(); onSave && onSave(); } catch (x) { toast(x.message, 'danger'); }
    };
    m.show();
  }

  async function oppForm({ cliente = null, opp = null, etapa_id = null, etapas = null, onSave } = {}) {
    inject(); const el = $('#crmMdlOpp'), f = $('#crmFrmOpp'), m = bootstrap.Modal.getOrCreateInstance(el);
    const [us, et, pl] = await Promise.all([usuarios(), etapas ? { etapas } : api('crm.php?a=pipeline'), etiquetas()]); f.reset();
    f.etapa_id.innerHTML = et.etapas.map((e) => `<option value="${e.id}">${esc(e.nombre)}</option>`).join('');
    f.responsable_id.innerHTML = us.rows.map((u) => `<option value="${u.id}">${esc(u.nombre)}</option>`).join('');
    f.origen.innerHTML = '<option value="">—</option>' + pl.origenes.map((o) => `<option>${esc(o)}</option>`).join('');
    f.responsable_id.value = opp?.responsable_id || us.yo; f.etapa_id.value = opp?.etapa_id || etapa_id || et.etapas[0].id; f.etapa_id.disabled = !!opp;
    $('#crmOppTit').textContent = opp ? 'Editar oportunidad' : 'Nueva oportunidad'; $('#crmOppDel').hidden = !opp;
    if (opp) { f.titulo.value = opp.titulo; f.valor.value = opp.valor; f.cierre_estimado.value = opp.cierre_estimado || ''; f.origen.value = opp.origen || ''; }
    picker($('#crmOppCli'), opp ? { id: opp.cliente_id, nombre: opp.cliente } : cliente);
    f.onsubmit = async (e) => {
      e.preventDefault(); const cl = $('#crmOppCli')._sel; if (!cl) return toast('Selecciona un cliente de la lista.', 'warning');
      try { await api('crm.php', { a: 'opp_guardar', id: opp?.id || 0, cliente_id: cl.id, titulo: f.titulo.value, valor: f.valor.value, etapa_id: f.etapa_id.value, responsable_id: f.responsable_id.value, origen: f.origen.value, cierre_estimado: f.cierre_estimado.value });
        m.hide(); toast('Oportunidad guardada'); changed(); onSave && onSave(); } catch (x) { toast(x.message, 'danger'); }
    };
    $('#crmOppDel').onclick = async () => { if (!(await confirmBox('Se eliminará la oportunidad.', '¿Eliminar?', 'Eliminar', true))) return; await api('crm.php', { a: 'opp_borrar', id: opp.id }); m.hide(); toast('Oportunidad eliminada'); changed(); onSave && onSave(); };
    m.show();
  }

  const changed = () => document.dispatchEvent(new CustomEvent('crm:cambio'));

  /* ---------- Ficha 360° ---------- */
  const F = { id: 0, d: null, tab: 'act' };
  function fichaHtml() {
    const d = F.d, c = d.cliente, m = d.metricas, s = d.segmento;
    const k = (l, v) => `<div class="fx-k"><small>${l}</small><b class="num">${v}</b></div>`;
    const bar = (l, v) => `<div class="rfm-bar"><span>${l}</span><i><u style="width:${v * 20}%"></u></i><b>${v || '–'}</b></div>`;
    const tabs = [['act', 'Actividad'], ['tar', `Tareas (${d.tareas.length})`], ...(IS_ADMIN ? [['opp', `Oportunidades (${d.oportunidades.filter((o) => o.estado === 'ABIERTA').length})`]] : []), ['ins', 'Insights'], ['per', 'Perfil']];
    return `
    <div class="d-flex gap-3 align-items-center mb-3">${av(c.nombre, true)}
      <div class="flex-grow-1" style="min-width:0"><h5 class="fw-bold m-0 text-truncate">${esc(c.nombre)}</h5>
        <div class="text-muted small">${esc(c.tipo_doc)} ${esc(c.documento || '—')} · ${esc(c.telefono || 'Sin teléfono')}</div>
        <div class="chips mt-1"><span class="seg-pill" style="--c:${s.color}"><i class="bi ${s.icono}"></i>${esc(s.nombre)}</span>${d.etiquetas.map(tagChip).join('')}</div></div>
      ${ring(m.score, true)}</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
      ${c.wa && c.acepta_marketing ? `<a class="btn-soft" id="fxWa" href="${waLink(c.wa)}" target="_blank" rel="noopener"><i class="bi bi-whatsapp text-success"></i> WhatsApp</a>` : ''}
      ${c.telefono ? `<a class="btn-soft" href="tel:${esc(c.telefono)}"><i class="bi bi-telephone"></i> Llamar</a>` : ''}
      <button class="btn-soft" id="fxTarea"><i class="bi bi-plus-lg"></i> Tarea</button>
      ${IS_ADMIN ? '<button class="btn-soft" id="fxOpp"><i class="bi bi-bullseye"></i> Oportunidad</button>' : ''}
      <a class="btn-soft" href="${APP.base}/modulo.php?m=pos"><i class="bi bi-cart-check"></i> Vender</a></div>
    <div class="fx-grid">${k('Compras', m.n)}${k('Total gastado', money(m.monto))}${k('Ticket promedio', money(m.ticket))}${k('Última compra', m.dias === null ? '—' : ago(m.dias))}
      ${k('Compra cada', m.intervalo ? (m.intervalo < 1 ? 'Casi a diario' : `${m.intervalo} días`) : '—')}${k('Próxima estimada', m.proxima ? fd(m.proxima) : '—')}${k('Puntos', c.puntos)}${k(c.saldo_deuda > 0 ? 'Deuda' : 'Crédito', c.saldo_deuda > 0 ? `<span class="text-danger">${money(c.saldo_deuda)}</span>` : money(c.limite_credito))}</div>
    <div class="row g-3 mt-0">
      <div class="col-md-5"><div class="cardx" style="height:auto;padding:.9rem 1rem"><div class="fw-bold small mb-1">Análisis RFM</div>${bar('Recencia', m.r)}${bar('Frecuencia', m.f)}${bar('Valor', m.m)}<div class="small text-muted mt-2">${esc(s.desc)}</div></div></div>
      <div class="col-md-7"><div class="nba h-100"><div class="fw-bold small text-uppercase text-muted mb-1" style="font-size:.68rem;letter-spacing:.06em">Siguiente mejor acción</div>${d.sugerencias.map((x) => `<div><i class="bi ${x[0]}"></i><span>${esc(x[1])}</span></div>`).join('')}${d.sugerencias.some((x) => x[1] === s.accion) ? '' : `<div class="small text-muted mt-2"><i class="bi bi-lightbulb"></i> ${esc(s.accion)}</div>`}</div></div></div>
    <div class="fx-tabs">${tabs.map((t) => `<button data-tab="${t[0]}" class="${F.tab === t[0] ? 'on' : ''}">${t[1]}</button>`).join('')}</div>
    <div id="fxTab"></div>`;
  }

  function renderRender(txt) {
    const c = F.d.cliente, m = F.d.metricas, p = (c.nombre || '').trim().split(/\s+/)[0] || '';
    return txt.replaceAll('{nombre}', p.charAt(0).toUpperCase() + p.slice(1).toLowerCase()).replaceAll('{nombre_completo}', c.nombre).replaceAll('{puntos}', c.puntos).replaceAll('{deuda}', money(c.saldo_deuda))
      .replaceAll('{dias}', m.dias ?? '—').replaceAll('{empresa}', F.d.empresa).replaceAll('{descuento}', '5');
  }

  function tabAct() {
    const d = F.d, c = d.cliente;
    const compose = `<div class="compose"><div class="d-flex gap-2 mb-2"><select class="form-select form-select-sm" id="nTipo" style="max-width:150px"><option value="NOTA">Nota</option><option value="LLAMADA">Llamada</option><option value="VISITA">Visita</option><option value="WHATSAPP">WhatsApp</option><option value="EMAIL">Correo</option><option value="REUNION">Reunión</option></select>
      <input class="form-control form-control-sm" id="nTxt" maxlength="1000" placeholder="Registra una interacción con el cliente…"><button class="btn-pos" style="height:34px;padding:0 .9rem;font-size:.82rem" id="nAdd">Guardar</button></div>
      ${c.wa && c.acepta_marketing ? `<details><summary class="small fw-semibold text-success" style="cursor:pointer"><i class="bi bi-whatsapp"></i> Enviar mensaje por WhatsApp</summary><div class="mt-2"><select class="form-select form-select-sm mb-2" id="wPl"><option value="">Elige una plantilla…</option>${d.plantillas.map((p) => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('')}</select>
        <textarea class="form-control form-control-sm" id="wTxt" rows="3" placeholder="Escribe el mensaje…"></textarea><button class="btn-soft mt-2" id="wSend"><i class="bi bi-send"></i> Abrir WhatsApp y registrar</button></div></details>` : ''}</div>`;
    const ev = d.timeline.map((e) => {
      const ic = TL[e.t === 'nota' ? e.sub : e.t] || TL.NOTA; const monto = e.monto !== undefined ? `<span class="m ${e.monto < 0 ? 'text-danger' : ''} ${e.anulada ? 'text-decoration-line-through text-muted' : ''}">${money(e.monto)}</span>` : '';
      return `<div class="ev"><div class="dt" style="--c:${ic[1]}"><i class="bi ${ic[0]}"></i></div><div class="h"><div>${esc(e.titulo)} ${monto}</div><span>${fdt(e.f)}${e.puede_borrar ? ` <a href="#" class="text-danger ms-1" data-del="${e.nota_id}" title="Eliminar"><i class="bi bi-x-lg"></i></a>` : ''}</span></div>
        <div class="b">${esc(e.detalle || '')}${e.autor ? ` <i class="text-muted">— ${esc(e.autor)}</i>` : ''}</div></div>`;
    }).join('');
    $('#fxTab').innerHTML = compose + (ev ? `<div class="tl">${ev}</div>` : empty('bi-clock-history', 'Aún no hay actividad con este cliente.'));
    $('#nAdd').onclick = async () => { const t = $('#nTxt').value.trim(); if (!t) return; try { await api('crm.php', { a: 'nota_add', cliente_id: F.id, tipo: $('#nTipo').value, texto: t }); toast('Interacción registrada'); await reload(); changed(); } catch (x) { toast(x.message, 'danger'); } };
    $('#nTxt').onkeydown = (e) => { if (e.key === 'Enter') $('#nAdd').click(); };
    document.querySelectorAll('#fxTab [data-del]').forEach((a) => (a.onclick = async (e) => { e.preventDefault(); if (!(await confirmBox('Se eliminará esta anotación.', '¿Eliminar?', 'Eliminar', true))) return; await api('crm.php', { a: 'nota_del', id: a.dataset.del }); await reload(); }));
    if ($('#wPl')) {
      $('#wPl').onchange = () => { const p = d.plantillas.find((x) => x.id == $('#wPl').value); if (p) $('#wTxt').value = renderRender(p.texto); };
      $('#wSend').onclick = async () => { const t = $('#wTxt').value.trim(); if (!t) return toast('Escribe o elige un mensaje.', 'warning'); window.open(waLink(c.wa, t), '_blank', 'noopener');
        await api('crm.php', { a: 'nota_add', cliente_id: F.id, tipo: 'WHATSAPP', texto: 'Mensaje enviado: ' + t.slice(0, 300) }); await reload(); changed(); };
    }
  }

  function tabTar() {
    const d = F.d;
    $('#fxTab').innerHTML = `<div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-bold small">Tareas pendientes</div><button class="btn-soft" id="tNew"><i class="bi bi-plus-lg"></i> Nueva</button></div>` +
      (d.tareas.length ? d.tareas.map((t) => `<div class="task-row"><div class="ck" data-ck="${t.id}"></div><div class="flex-grow-1"><div class="tt"><span class="pri ${t.prioridad}"></span> ${esc(t.titulo)}</div>
        <div class="ts ${t.vencida ? 'text-danger fw-bold' : ''}"><i class="bi ${TAREA_IC[t.tipo]}"></i> ${TAREA_NOM[t.tipo]} · ${venceTxt(t)}${t.asignado ? ' · ' + esc(t.asignado) : ''}</div></div><button class="ico-btn" data-ed="${t.id}"><i class="bi bi-pencil"></i></button></div>`).join('') : empty('bi-check2-all', 'No hay tareas pendientes.'));
    $('#tNew').onclick = () => tareaForm({ cliente: { id: F.id, nombre: d.cliente.nombre }, onSave: reload });
    document.querySelectorAll('#fxTab [data-ck]').forEach((e) => (e.onclick = async () => { await api('crm.php', { a: 'tarea_toggle', id: e.dataset.ck }); toast('Tarea completada'); await reload(); changed(); }));
    document.querySelectorAll('#fxTab [data-ed]').forEach((e) => (e.onclick = () => tareaForm({ tarea: d.tareas.find((t) => t.id == e.dataset.ed), cliente: { id: F.id, nombre: d.cliente.nombre }, onSave: reload })));
  }

  function tabOpp() {
    const d = F.d;
    $('#fxTab').innerHTML = `<div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-bold small">Oportunidades de venta</div><button class="btn-soft" id="oNew"><i class="bi bi-plus-lg"></i> Nueva</button></div>` +
      (d.oportunidades.length ? d.oportunidades.map((o) => `<div class="task-row"><div class="flex-grow-1"><div class="tt">${esc(o.titulo)}</div><div class="ts"><span class="seg-pill" style="--c:${o.color}">${esc(o.etapa)}</span> · cierre ${o.cierre_estimado ? fd(o.cierre_estimado) : 'sin fecha'}</div></div><b class="num">${money(o.valor)}</b></div>`).join('') : empty('bi-bullseye', 'Sin oportunidades registradas.'));
    $('#oNew').onclick = () => oppForm({ cliente: { id: F.id, nombre: d.cliente.nombre }, onSave: reload });
  }

  function tabIns() {
    const d = F.d, mx = Math.max(...Object.values(d.meses), 1), tm = Math.max(...d.top.map((x) => x.monto), 1), cm = Math.max(...d.categorias.map((x) => x.monto), 1);
    const mn = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $('#fxTab').innerHTML = `<div class="cardx mb-3" style="height:auto"><div class="fw-bold small mb-2">Compras de los últimos 6 meses</div><div class="bars6">${Object.entries(d.meses).map(([m, v]) => `<div title="${money(v)}"><i style="height:${Math.max(3, v / mx * 70)}px"></i>${mn[+m.slice(5) - 1]}<br><b class="text-body">${v ? fmt(v, 0) : '–'}</b></div>`).join('')}</div></div>
      <div class="row g-3"><div class="col-md-6"><div class="cardx" style="height:auto"><div class="fw-bold small mb-1">Productos favoritos</div>${d.top.map((x) => `<div class="hbar"><span class="n">${esc(x.nombre)}</span><span class="bar"><i style="width:${x.monto / tm * 100}%"></i></span><span class="v">${money(x.monto)}</span></div>`).join('') || empty('bi-box', 'Sin compras')}</div></div>
      <div class="col-md-6"><div class="cardx" style="height:auto"><div class="fw-bold small mb-1">Categorías que más compra</div>${d.categorias.map((x) => `<div class="hbar" style="--c:${esc(x.color)}"><span class="n">${esc(x.nombre)}</span><span class="bar"><i style="width:${x.monto / cm * 100}%"></i></span><span class="v">${money(x.monto)}</span></div>`).join('') || empty('bi-tags', 'Sin compras')}</div></div></div>
      <div class="cardx mt-3" style="height:auto"><div class="fw-bold small mb-2">Medios de pago preferidos</div><div class="chips">${d.pagos.map((p) => `<span class="chip-f" style="cursor:default">${esc(p.m.charAt(0) + p.m.slice(1).toLowerCase())} <em>${p.n}</em></span>`).join('') || '<span class="text-muted small">—</span>'}</div></div>`;
  }

  async function tabPer() {
    const d = F.d, c = d.cliente, [us, et] = await Promise.all([usuarios(), etiquetas()]); const ro = !IS_ADMIN ? 'disabled' : '';
    $('#fxTab').innerHTML = `<div class="cardx mb-3" style="height:auto"><div class="fw-bold small mb-2">Etiquetas</div><div class="chips">${et.rows.map((t) => `<button type="button" class="tag-chip btn-t ${d.etiquetas.some((x) => x.id === t.id) ? '' : 'off'}" style="--c:${esc(t.color)}" data-tag="${t.id}">${esc(t.nombre)}</button>`).join('')}</div></div>
      <div class="cardx" style="height:auto"><div class="row g-3">
        <div class="col-6"><label class="form-label">Origen del cliente</label><select class="form-select" id="pOri" ${ro}><option value="">—</option>${et.origenes.map((o) => `<option ${c.origen === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></div>
        <div class="col-6"><label class="form-label">Responsable</label><select class="form-select" id="pRes" ${ro}><option value="">Sin asignar</option>${us.rows.map((u) => `<option value="${u.id}" ${c.responsable_id === u.id ? 'selected' : ''}>${esc(u.nombre)}</option>`).join('')}</select></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="pMk" ${c.acepta_marketing ? 'checked' : ''} ${ro}><label class="form-check-label small" for="pMk">Acepta recibir mensajes y promociones por WhatsApp</label></div></div>
        ${IS_ADMIN ? '<div class="col-12 text-end"><button class="btn-pos" id="pSave">Guardar perfil</button></div>' : ''}</div></div>
      <div class="cardx mt-3" style="height:auto"><div class="row g-2 small">
        <div class="col-6"><span class="text-muted">Correo</span><br><b>${esc(c.email || '—')}</b></div><div class="col-6"><span class="text-muted">Dirección</span><br><b>${esc(c.direccion || '—')}</b></div>
        <div class="col-6"><span class="text-muted">Cumpleaños</span><br><b>${c.fecha_nacimiento ? fd(c.fecha_nacimiento) + (c.cumple_en !== null ? ` · en ${c.cumple_en} día(s)` : '') : '—'}</b></div><div class="col-6"><span class="text-muted">Cliente desde</span><br><b>${fd(c.creado_en)}</b></div>
        <div class="col-6"><span class="text-muted">Último contacto</span><br><b>${c.ultimo_contacto ? fdt(c.ultimo_contacto) : 'Sin registro'}</b></div><div class="col-6"><span class="text-muted">Responsable</span><br><b>${esc(c.responsable || '—')}</b></div></div></div>`;
    document.querySelectorAll('#fxTab [data-tag]').forEach((b) => (b.onclick = async () => { try { const r = await api('crm.php', { a: 'etiqueta_toggle', cliente_id: F.id, etiqueta_id: b.dataset.tag }); b.classList.toggle('off', !r.on); await reload(true); changed(); } catch (x) { toast(x.message, 'danger'); } }));
    if (IS_ADMIN) $('#pSave').onclick = async () => { try { await api('crm.php', { a: 'perfil_guardar', id: F.id, origen: $('#pOri').value, responsable_id: $('#pRes').value, acepta_marketing: $('#pMk').checked ? 1 : 0 }); toast('Perfil actualizado'); await reload(); changed(); } catch (x) { toast(x.message, 'danger'); } };
  }

  function paintTab() { ({ act: tabAct, tar: tabTar, opp: tabOpp, ins: tabIns, per: tabPer })[F.tab](); document.querySelectorAll('.fx-tabs button').forEach((b) => b.classList.toggle('on', b.dataset.tab === F.tab)); }

  function paint(keepTab) {
    const body = $('#crmFichaBody'); body.innerHTML = fichaHtml();
    body.querySelectorAll('.fx-tabs button').forEach((b) => (b.onclick = () => { F.tab = b.dataset.tab; paintTab(); }));
    $('#fxTarea').onclick = () => tareaForm({ cliente: { id: F.id, nombre: F.d.cliente.nombre }, onSave: reload });
    if ($('#fxOpp')) $('#fxOpp').onclick = () => oppForm({ cliente: { id: F.id, nombre: F.d.cliente.nombre }, onSave: reload });
    if ($('#fxWa')) $('#fxWa').onclick = () => api('crm.php', { a: 'nota_add', cliente_id: F.id, tipo: 'WHATSAPP', texto: 'Contacto por WhatsApp' }).then(() => setTimeout(reload, 600));
    paintTab();
  }
  async function reload() { if (!F.id) return; const keep = $('#fxTab') && document.activeElement && document.activeElement.closest && document.activeElement.closest('#fxTab'); F.d = await api('crm.php?a=ficha&id=' + F.id); paint(keep); }
  async function openFicha(id, tab) {
    inject(); F.id = +id; F.tab = tab || 'act'; const oc = bootstrap.Offcanvas.getOrCreateInstance($('#crmFicha'));
    $('#crmFichaBody').innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Cargando ficha…</div>'; oc.show();
    try { F.d = await api('crm.php?a=ficha&id=' + F.id); paint(); } catch (e) { $('#crmFichaBody').innerHTML = empty('bi-exclamation-circle', esc(e.message)); }
  }
  document.addEventListener('click', (e) => { const t = e.target.closest('[data-ficha]'); if (t) { e.preventDefault(); openFicha(t.dataset.ficha); } });

  window.CRM = { SEG, TAREA_IC, TAREA_NOM, fd, fdt, ago, av, segPill, ring, tagChip, waLink, empty, stat, venceTxt, usuarios, etiquetas, tareaForm, oppForm, openFicha, changed, colorOf };
})();
