/* CRM · Campañas por WhatsApp */
(function () {
  const $ = (s) => document.querySelector(s); const C = window.CRM; let D = null, curId = 0;
  const mNew = new bootstrap.Modal($('#mdlNew')), mDet = new bootstrap.Modal($('#mdlDet')), mPl = new bootstrap.Modal($('#mdlPl')), f = $('#frmNew');
  const url = new URLSearchParams(location.search);
  const pill = (e) => `<span class="pill ${e === 'ACTIVA' ? 'ok' : 'neutral'}">${e === 'ACTIVA' ? 'Activa' : 'Cerrada'}</span>`;

  async function load() {
    D = await api('crm.php?a=campanas'); const k = D.kpi;
    $('#kpis').innerHTML = `<div class="col-md-3 col-6">${C.stat('bi-megaphone', 'ic-indigo', 'Campañas', k.campanas)}</div><div class="col-md-3 col-6">${C.stat('bi-send-check', 'ic-sky', 'Mensajes enviados', k.enviados)}</div>
      <div class="col-md-3 col-6">${C.stat('bi-bag-check', 'ic-green', 'Clientes que compraron', k.compraron, 'En los 30 días siguientes')}</div><div class="col-md-3 col-6">${C.stat('bi-cash-coin', 'ic-amber', 'Ventas atribuidas', money(k.monto))}</div>`;
    $('#tb').innerHTML = D.rows.map((c) => { const pc = c.total ? Math.round((c.enviados + c.omitidos) / c.total * 100) : 0;
      return `<tr><td><b>${esc(c.nombre)}</b><div class="small text-muted">${C.fd(c.creado_en)}</div></td><td>${esc(c.segmento_nombre)}</td>
      <td style="min-width:130px"><div class="bar-s"><i style="width:${pc}%;background:var(--brand)"></i></div><small class="text-muted">${pc}% gestionado (${c.pendientes} pend.)</small></td>
      <td class="r num">${c.enviados}/${c.total}</td><td class="r num">${c.compraron} <small class="text-muted">(${c.conversion}%)</small></td><td class="r num"><b>${money(c.monto)}</b></td><td>${pill(c.estado)}</td>
      <td class="r"><button class="btn-soft" data-open="${c.id}"><i class="bi bi-send"></i> Gestionar</button></td></tr>`; }).join('') || `<tr><td colspan="8">${C.empty('bi-megaphone', 'Aún no has creado campañas. Empieza con tus clientes «En riesgo» o con los cumpleaños de la semana.')}</td></tr>`;
    f.segmento.innerHTML = '<option value="">Elige un segmento…</option>' + D.segmentos.map((s) => `<option value="${s.key}" ${s.n ? '' : 'disabled'}>${esc(s.nombre)} (${s.n})</option>`).join('');
    $('#plSel').innerHTML = '<option value="">Usar una plantilla…</option>' + D.plantillas.map((p) => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
  }

  /* ---- nueva campaña ---- */
  const prev = debounce(async () => {
    if (!f.segmento.value) { $('#prev').innerHTML = 'Elige un segmento y escribe el mensaje.'; return; }
    try {
      const r = await api('crm.php?a=campana_preview&segmento=' + encodeURIComponent(f.segmento.value) + '&mensaje=' + encodeURIComponent(f.mensaje.value));
      $('#prev').innerHTML = `<div class="d-flex flex-wrap gap-2 mb-2"><span class="pill ok">${r.elegibles} recibirán el mensaje</span>${r.sin_telefono ? `<span class="pill warn">${r.sin_telefono} sin teléfono válido</span>` : ''}${r.sin_permiso ? `<span class="pill neutral">${r.sin_permiso} no aceptan mensajes</span>` : ''}</div>`
        + (r.muestra.length && f.mensaje.value.trim() ? r.muestra.map((m) => `<div class="mb-2"><div class="small text-muted mb-1">Para ${esc(m.nombre)}</div><div class="msg-prev">${esc(m.msg).replace(/\n/g, '<br>')}</div></div>`).join('') : '');
    } catch (e) { $('#prev').textContent = e.message; }
  }, 350);
  f.segmento.onchange = prev; f.mensaje.oninput = prev;
  $('#plSel').onchange = () => { const p = D.plantillas.find((x) => x.id == $('#plSel').value); if (p) { f.mensaje.value = p.texto; if (!f.nombre.value) f.nombre.value = p.nombre; prev(); } };
  document.querySelectorAll('.var-chip').forEach((b) => (b.onclick = () => { const t = f.mensaje, s = t.selectionStart ?? t.value.length; t.setRangeText(b.dataset.v, s, t.selectionEnd ?? s, 'end'); t.focus(); prev(); }));
  const abrirNueva = (seg) => { f.reset(); if (seg) f.segmento.value = seg; $('#prev').textContent = 'Elige un segmento y escribe el mensaje.'; mNew.show(); if (seg) prev(); };
  $('#bNew').onclick = () => abrirNueva();
  f.onsubmit = async (e) => { e.preventDefault(); try { const r = await api('crm.php', { a: 'campana_crear', nombre: f.nombre.value, segmento: f.segmento.value, mensaje: f.mensaje.value }); mNew.hide(); toast(`Campaña creada con ${r.total} destinatarios`); await load(); abrir(r.id); } catch (x) { toast(x.message, 'danger'); } };

  /* ---- gestión de una campaña ---- */
  async function abrir(id) {
    curId = id; const r = await api('crm.php?a=campana_detalle&id=' + id); const s = r.stats, c = r.campana; const pend = r.rows.find((x) => x.estado === 'PENDIENTE' && x.wa);
    const st = { PENDIENTE: ['neutral', 'Pendiente'], ENVIADO: ['ok', 'Enviado'], OMITIDO: ['warn', 'Omitido'] };
    $('#det').innerHTML = `<div class="d-flex justify-content-between align-items-start mb-3 gap-3"><div><h5 class="fw-bold m-0">${esc(c.nombre)}</h5><div class="small text-muted">${esc(c.segmento_nombre)} · ${pill(c.estado)}</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="msg-prev mb-3">${esc(c.mensaje).replace(/\n/g, '<br>')}</div>
      <div class="row g-2 mb-3 text-center"><div class="col-3"><div class="fx-k"><small>Destinatarios</small><b>${s.total}</b></div></div><div class="col-3"><div class="fx-k"><small>Enviados</small><b>${s.enviados}</b></div></div>
        <div class="col-3"><div class="fx-k"><small>Compraron</small><b class="text-success">${s.compraron} · ${s.conversion}%</b></div></div><div class="col-3"><div class="fx-k"><small>Ventas</small><b>${money(s.monto)}</b></div></div></div>
      <div class="d-flex flex-wrap gap-2 mb-3">${pend ? `<button class="btn-pos" data-next="${pend.id}" style="height:38px"><i class="bi bi-whatsapp"></i> Enviar al siguiente (${s.pendientes})</button>` : '<span class="pill ok align-self-center">Todos gestionados</span>'}
        <button class="btn-soft ms-auto" data-toggle="${c.id}"><i class="bi bi-${c.estado === 'ACTIVA' ? 'lock' : 'unlock'}"></i> ${c.estado === 'ACTIVA' ? 'Cerrar campaña' : 'Reabrir'}</button><button class="btn-soft danger" data-delc="${c.id}"><i class="bi bi-trash"></i></button></div>
      <div class="table-responsive"><table class="tbl"><tbody>${r.rows.map((x) => `<tr><td>${C.av(x.nombre)}</td><td style="white-space:normal"><a class="crm-link" data-ficha="${x.cliente_id}">${esc(x.nombre)}</a><div class="small text-muted">${esc(x.telefono || '')}</div></td>
        <td><span class="pill ${st[x.estado][0]}">${st[x.estado][1]}</span>${x.enviado_en ? `<div class="small text-muted">${C.fdt(x.enviado_en)}</div>` : ''}</td>
        <td class="r"><div class="d-flex gap-1 justify-content-end">${x.wa ? `<button class="btn-soft" data-wa="${x.id}" data-url="${esc(C.waLink(x.wa, x.msg))}"><i class="bi bi-whatsapp text-success"></i> ${x.estado === 'ENVIADO' ? 'Reenviar' : 'Enviar'}</button>` : ''}
        ${x.estado === 'PENDIENTE' ? `<button class="ico-btn" data-skip="${x.id}" title="Omitir"><i class="bi bi-skip-forward"></i></button>` : x.estado === 'OMITIDO' ? `<button class="ico-btn" data-undo="${x.id}" title="Volver a pendiente"><i class="bi bi-arrow-counterclockwise"></i></button>` : ''}</div></td></tr>`).join('')}</tbody></table></div>`;
    mDet.show(); window._camp = r;
  }
  const marcar = async (id, estado) => { try { await api('crm.php', { a: 'destinatario_marcar', id, estado }); await abrir(curId); load(); C.changed(); } catch (x) { toast(x.message, 'danger'); } };
  $('#det').addEventListener('click', async (e) => {
    const wa = e.target.closest('[data-wa]'), nx = e.target.closest('[data-next]'), sk = e.target.closest('[data-skip]'), un = e.target.closest('[data-undo]'), tg = e.target.closest('[data-toggle]'), dl = e.target.closest('[data-delc]');
    if (wa) { window.open(wa.dataset.url, '_blank', 'noopener'); marcar(+wa.dataset.wa, 'ENVIADO'); }
    if (nx) { const x = window._camp.rows.find((r) => r.id == nx.dataset.next); window.open(C.waLink(x.wa, x.msg), '_blank', 'noopener'); marcar(x.id, 'ENVIADO'); }
    if (sk) marcar(+sk.dataset.skip, 'OMITIDO'); if (un) marcar(+un.dataset.undo, 'PENDIENTE');
    if (tg) { await api('crm.php', { a: 'campana_cerrar', id: tg.dataset.toggle }); abrir(curId); load(); }
    if (dl && (await confirmBox('Se eliminará la campaña y su historial de envíos.', '¿Eliminar campaña?', 'Eliminar', true))) { await api('crm.php', { a: 'campana_borrar', id: dl.dataset.delc }); mDet.hide(); toast('Campaña eliminada'); load(); }
  });
  $('#tb').addEventListener('click', (e) => { const b = e.target.closest('[data-open]'); if (b) abrir(+b.dataset.open); });

  /* ---- plantillas ---- */
  const CATS = { promo: 'Promoción', cumple: 'Cumpleaños', reactivacion: 'Reactivación', cobranza: 'Cobranza', general: 'General' };
  async function paintPl() {
    const r = await api('crm.php?a=plantillas'); const row = (p) => `<div class="cardx mb-2" style="height:auto" data-id="${p ? p.id : 0}"><div class="row g-2"><div class="col-7"><input class="form-control form-control-sm" data-f="nombre" maxlength="60" placeholder="Nombre de la plantilla" value="${esc(p?.nombre || '')}"></div>
      <div class="col-5"><select class="form-select form-select-sm" data-f="categoria">${Object.entries(CATS).map(([k, v]) => `<option value="${k}" ${p?.categoria === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
      <div class="col-12"><textarea class="form-control form-control-sm" data-f="texto" rows="3" maxlength="1000" placeholder="Mensaje con variables: {nombre} {puntos} {deuda} {dias} {empresa} {descuento}">${esc(p?.texto || '')}</textarea></div>
      <div class="col-12 d-flex gap-2 justify-content-end"><button class="btn-soft" data-save><i class="bi bi-check2"></i> ${p ? 'Guardar' : 'Agregar plantilla'}</button>${p ? '<button class="btn-soft danger" data-del><i class="bi bi-trash"></i></button>' : ''}</div></div></div>`;
    $('#plBody').innerHTML = `<div class="small text-muted mb-3">Variables disponibles: <code>{nombre}</code> <code>{puntos}</code> <code>{deuda}</code> <code>{dias}</code> (días sin comprar) <code>{empresa}</code> <code>{descuento}</code> (cumpleaños).</div>` + r.rows.map(row).join('') + '<div class="fw-bold small mt-3 mb-2">Nueva plantilla</div>' + row(null);
  }
  $('#bPl').onclick = async () => { await paintPl(); mPl.show(); };
  $('#plBody').addEventListener('click', async (e) => {
    const card = e.target.closest('[data-id]'); if (!card) return; const g = (k) => card.querySelector(`[data-f=${k}]`).value;
    try {
      if (e.target.closest('[data-save]')) { await api('crm.php', { a: 'plantilla_guardar', id: +card.dataset.id, nombre: g('nombre'), categoria: g('categoria'), texto: g('texto') }); toast('Plantilla guardada'); await paintPl(); load(); }
      if (e.target.closest('[data-del]') && (await confirmBox('Se eliminará la plantilla.', '¿Eliminar?', 'Eliminar', true))) { await api('crm.php', { a: 'plantilla_borrar', id: +card.dataset.id }); await paintPl(); load(); }
    } catch (x) { toast(x.message, 'danger'); }
  });

  load().then(() => { if (url.get('nueva')) abrirNueva(url.get('seg') || ''); });
})();
