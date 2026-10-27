/* CRM · Clientes 360° (directorio con segmento, salud y etiquetas) */
(function () {
  const $ = (s) => document.querySelector(s); const C = window.CRM;
  const url = new URLSearchParams(location.search);
  const st = { q: url.get('q') || '', seg: url.get('seg') || '', tag: '', orden: 'monto', page: 1 };
  $('#q').value = st.q;

  async function load() {
    const qs = new URLSearchParams({ a: 'clientes', q: st.q, seg: st.seg, tag: st.tag, orden: st.orden, page: st.page });
    const r = await api('crm.php?' + qs);
    const chip = (k, label, n, color) => `<button class="chip-f ${st.seg === k ? 'on' : ''}" data-seg="${k}">${color ? `<span class="dot" style="--c:${color}"></span>` : ''}${label}<em>${n}</em></button>`;
    $('#segs').innerHTML = chip('', 'Todos', r.todos) + Object.entries(C.SEG).filter(([k]) => r.segcount[k]).map(([k, s]) => chip(k, s[0], r.segcount[k], s[1])).join('');
    $('#tb').innerHTML = r.rows.map((c) => `<tr>
      <td><div class="d-flex align-items-center gap-2">${C.av(c.nombre)}<div><a class="crm-link" data-ficha="${c.id}">${esc(c.nombre)}</a><div class="small text-muted">${esc(c.documento || 'Sin documento')}${c.telefono ? ' · ' + esc(c.telefono) : ''}</div></div></div></td>
      <td>${C.segPill(c.seg)}</td><td>${C.ring(c.score)}</td><td class="r num">${c.n}</td><td class="r num">${money(c.monto)}${c.saldo_deuda > 0 ? `<div class="small text-danger">Debe ${money(c.saldo_deuda)}</div>` : ''}</td>
      <td>${c.dias === null ? '<span class="text-muted">Nunca</span>' : C.ago(c.dias)}<div class="small text-muted">${c.ultimo_contacto ? 'Contacto ' + C.fd(c.ultimo_contacto) : ''}</div></td>
      <td style="white-space:normal;min-width:130px"><div class="chips">${c.etiquetas.map(C.tagChip).join('') || '<span class="text-muted small">—</span>'}</div></td>
      <td class="r"><div class="d-flex gap-1 justify-content-end">${c.wa ? `<a class="ico-btn wa" target="_blank" rel="noopener" href="${C.waLink(c.wa)}" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>` : ''}<button class="btn-soft" data-ficha="${c.id}"><i class="bi bi-person-lines-fill"></i> Ficha</button></div></td></tr>`).join('')
      || `<tr><td colspan="8">${C.empty('bi-people', 'No se encontraron clientes con esos filtros.')}</td></tr>`;
    const per = 15; $('#info').textContent = r.total ? `Mostrando ${(r.page - 1) * per + 1}–${Math.min(r.page * per, r.total)} de ${r.total}` : '';
    const pg = []; for (let i = 1; i <= r.pages; i++) if (i === 1 || i === r.pages || Math.abs(i - r.page) <= 1) pg.push(i); else if (pg[pg.length - 1] !== '…') pg.push('…');
    $('#pg').innerHTML = r.pages > 1 ? pg.map((p) => (p === '…' ? '<span class="px-1 text-muted">…</span>' : `<button class="chip-f ${p === r.page ? 'on' : ''}" data-p="${p}">${p}</button>`)).join('') : '';
  }

  async function loadTags() {
    const e = await C.etiquetas(true); $('#tag').innerHTML = '<option value="">Todas las etiquetas</option>' + e.rows.map((t) => `<option value="${t.id}">${esc(t.nombre)} (${t.n})</option>`).join('');
    $('#tag').value = st.tag; return e;
  }

  $('#q').addEventListener('input', debounce((e) => { st.q = e.target.value; st.page = 1; load(); }));
  $('#tag').onchange = (e) => { st.tag = e.target.value; st.page = 1; load(); };
  $('#orden').onchange = (e) => { st.orden = e.target.value; st.page = 1; load(); };
  document.addEventListener('click', (e) => {
    const s = e.target.closest('[data-seg]'); if (s) { st.seg = s.dataset.seg; st.page = 1; load(); }
    const p = e.target.closest('[data-p]'); if (p) { st.page = +p.dataset.p; load(); }
  });
  document.addEventListener('crm:cambio', load);

  /* gestión de etiquetas (administrador) */
  if ($('#bTags')) {
    const m = new bootstrap.Modal($('#mdlTags'));
    const paint = async () => {
      const e = await loadTags();
      $('#tagList').innerHTML = e.rows.map((t) => `<div class="d-flex gap-2 align-items-center mb-2" data-id="${t.id}"><input type="color" class="form-control form-control-color" value="${esc(t.color)}"><input class="form-control" value="${esc(t.nombre)}" maxlength="40"><small class="text-muted" style="min-width:52px">${t.n} cli.</small>
        <button class="ico-btn" data-save title="Guardar"><i class="bi bi-check2"></i></button><button class="ico-btn danger" data-del title="Eliminar"><i class="bi bi-trash"></i></button></div>`).join('') || C.empty('bi-tags', 'Aún no hay etiquetas.');
    };
    $('#bTags').onclick = async () => { await paint(); m.show(); };
    $('#tagAdd').onclick = async () => { try { await api('crm.php', { a: 'etiqueta_guardar', nombre: $('#tagNewN').value, color: $('#tagNewC').value }); $('#tagNewN').value = ''; await paint(); } catch (x) { toast(x.message, 'danger'); } };
    $('#tagList').addEventListener('click', async (e) => {
      const row = e.target.closest('[data-id]'); if (!row) return; const id = +row.dataset.id;
      try {
        if (e.target.closest('[data-save]')) { await api('crm.php', { a: 'etiqueta_guardar', id, nombre: row.children[1].value, color: row.children[0].value }); toast('Etiqueta actualizada'); await paint(); load(); }
        if (e.target.closest('[data-del]') && (await confirmBox('Se quitará de todos los clientes que la tengan.', '¿Eliminar etiqueta?', 'Eliminar', true))) { await api('crm.php', { a: 'etiqueta_borrar', id }); await paint(); load(); }
      } catch (x) { toast(x.message, 'danger'); }
    });
  }
  loadTags().then(load);
})();
