/* Motor CRUD genérico */
(function () {
  const C = window.CRUD, $ = (s) => document.querySelector(s);
  let page = 1, rows = [], editing = 0;
  const modal = new bootstrap.Modal($('#mdlForm'));

  const R = {
    text: (v) => esc(v ?? '—'), num: (v) => fmt(v, 0), money: (v) => `<span class="num">${money(v)}</span>`,
    date: (v) => v ? v.split('-').reverse().join('/') : '—', datetime: (v) => v ? v.slice(8, 10) + '/' + v.slice(5, 7) + ' ' + v.slice(11, 16) : '<span class="text-muted">Nunca</span>',
    estado: (v) => v == 1 ? '<span class="pill ok">Activo</span>' : '<span class="pill neutral">Inactivo</span>',
    color: (v) => `<span class="swatch" style="background:${esc(v)}"></span> <small class="text-muted">${esc(v)}</small>`,
    tag: (v) => `<span class="pill info">${esc(v)}</span>`,
    doc: (v, r) => v ? `<small class="text-muted">${esc(r.tipo_doc)}</small> ${esc(v)}` : '—',
    debt: (v) => v > 0 ? `<span class="pill bad">${money(v)}</span>` : '<span class="text-muted">—</span>',
    icon: (v, r) => `<span class="kpi-ic" style="width:34px;height:34px;font-size:1rem;display:inline-grid;vertical-align:middle;margin-right:.6rem;background:${esc(r.color)}22;color:${esc(r.color)}"><i class="bi ${esc(r.icono)}"></i></span><b>${esc(v)}</b>`,
    cat: (v, r) => `<span class="pill" style="background:${esc(r.cat_color)}1f;color:${esc(r.cat_color)}">${esc(v)}</span>`,
    user: (v, r) => `<div class="d-flex align-items-center gap-2"><div class="avatar" style="width:34px;height:34px;font-size:.72rem">${esc((r.nombres[0] + r.apellidos[0]).toUpperCase())}</div><div><b>${esc(r.nombres + ' ' + r.apellidos)}</b><div class="small text-muted">${esc(r.email || '')}</div></div></div>`,
    prod: (v, r) => `<div class="d-flex align-items-center gap-2">${r.imagen ? `<img src="${APP.base}/assets/uploads/productos/${esc(r.imagen)}" class="p-thumb" alt="">` : `<span class="p-thumb ph"><i class="bi bi-image"></i></span>`}<div><div><b>${esc(v)}</b></div><div class="small text-muted">${esc(r.marca || 'Sin marca')} · ${esc(r.unidad)}${r.fecha_vencimiento ? ' · vence ' + R.date(r.fecha_vencimiento) : ''}</div></div></div>`,
    stock: (v, r) => { const cls = v <= 0 ? 'bad' : v <= r.stock_minimo ? 'warn' : 'ok'; return `<span class="pill ${cls}">${fmt(v, v % 1 ? 2 : 0)} ${esc(r.unidad || '')}</span>`; },
  };

  function head() {
    $('#tbl thead').innerHTML = '<tr>' + C.cols.map((c) => `<th class="${['money', 'num'].includes(c[2]) ? 'r' : ''}">${c[1]}</th>`).join('') + '<th class="r">Acciones</th></tr>';
  }
  function params() {
    const p = new URLSearchParams({ e: C.entity, a: 'list', page, q: $('#q').value.trim() });
    document.querySelectorAll('[data-filter]').forEach((s) => p.set('f_' + s.dataset.filter, s.value));
    if ($('#fEstado')) p.set('estado', $('#fEstado').value);
    if ($('#fDesde')) { p.set('desde', $('#fDesde').value); p.set('hasta', $('#fHasta').value); }
    return p;
  }
  async function load() {
    $('#tbl tbody').style.opacity = .5;
    try {
      const r = await api('crud.php?' + params()); rows = r.rows; page = r.page;
      $('#tbl tbody').innerHTML = rows.length ? rows.map((row) => '<tr>' + C.cols.map((c) => `<td class="${['money', 'num'].includes(c[2]) ? 'r' : ''}">${(R[c[2]] || R.text)(row[c[0]], row)}</td>`).join('') +
        `<td class="r" style="white-space:nowrap"><button class="act" title="Ver detalle" data-a="view" data-id="${row.id}"><i class="bi bi-eye"></i></button>${C.write ? (C.protected.includes(row.id) ? ' <span class="text-muted small">Sistema</span>' :
          ` <button class="act" title="Editar" data-a="edit" data-id="${row.id}"><i class="bi bi-pencil"></i></button>${C.hasEstado ? ` <button class="act" title="Activar/Desactivar" data-a="tog" data-id="${row.id}"><i class="bi bi-toggle-${row.estado == 1 ? 'on' : 'off'}"></i></button>` : ''}`) : ''}</td></tr>`).join('')
        : `<tr><td colspan="${C.cols.length + 1}"><div class="empty"><i class="bi bi-inbox"></i>No se encontraron registros</div></td></tr>`;
      $('#pager').innerHTML = pagerHtml(r.page, r.pages, r.total, r.per);
      if ($('#sumVal')) $('#sumVal').textContent = money(r.sum);
    } catch (e) { toast(e.message, 'danger'); }
    $('#tbl tbody').style.opacity = 1;
  }

  function input(f, v) {
    const id = 'f_' + f.name, val = v ?? '', req = f.req ? 'required' : '';
    if (f.type === 'switch') return `<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="${id}" ${val == 1 ? 'checked' : ''}><label class="form-check-label" for="${id}">${esc(f.label)}</label></div>`;
    let h = `<label class="form-label" for="${id}">${esc(f.label)}${f.req ? ' <span class="text-danger">*</span>' : ''}</label>`;
    if (f.type === 'select') h += `<select class="form-select" id="${id}" ${req}>${f.req ? '' : '<option value="">— Ninguno —</option>'}${Object.entries(f.options).map(([k, l]) => `<option value="${esc(k)}" ${String(k) === String(val) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
    else if (f.type === 'textarea') h += `<textarea class="form-control" id="${id}" rows="2">${esc(val)}</textarea>`;
    else if (f.type === 'color') h += `<input type="color" class="form-control form-control-color w-100" id="${id}" value="${esc(val || '#5b5ef4')}">`;
    else if (f.type === 'money' || f.type === 'number') h += `<input type="number" step="${f.type === 'money' ? '0.01' : 'any'}" min="0" class="form-control" id="${id}" value="${esc(val)}" ${req}>`;
    else h += `<input type="${f.type === 'password' ? 'password' : f.type === 'email' ? 'email' : f.type === 'date' ? 'date' : 'text'}" class="form-control" id="${id}" value="${esc(f.type === 'password' ? '' : val)}" ${f.type === 'password' && editing ? 'placeholder="Dejar vacío para no cambiarla"' : ''} ${req} autocomplete="off">`;
    return h;
  }
  function photoBox(id, img) {
    return `<div class="col-12"><div class="photo-box"><div class="photo-prev" id="phPrev">${img ? `<img src="${APP.base}/assets/uploads/productos/${esc(img)}" alt="">` : '<i class="bi bi-image"></i>'}</div>
      <div class="flex-grow-1"><div class="fw-semibold small">Foto del producto</div><div class="text-muted" style="font-size:.74rem">JPG, PNG o WEBP · máx. 1 MB</div>
      ${id ? `<div class="d-flex gap-2 mt-2"><label class="btn-soft mb-0" style="cursor:pointer"><i class="bi bi-upload"></i> Subir foto<input type="file" id="phFile" accept="image/jpeg,image/png,image/webp" hidden></label><button type="button" class="btn-soft danger" id="phDel" ${img ? '' : 'hidden'}><i class="bi bi-trash"></i></button></div>` : '<div class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Guarda el producto y luego edítalo para subir su foto.</div>'}</div></div></div>`;
  }
  async function sendPhoto(fd) {
    const r = await fetch(`${APP.base}/api/producto_img.php`, { method: 'POST', headers: { 'X-CSRF-Token': APP.csrf }, body: fd }), j = await r.json();
    if (!j.ok) throw new Error(j.error || 'No se pudo procesar la imagen'); return j;
  }
  document.addEventListener('change', async (ev) => {
    if (ev.target.id !== 'phFile' || !ev.target.files[0]) return;
    const fd = new FormData(); fd.append('id', editing); fd.append('foto', ev.target.files[0]);
    try { const j = await sendPhoto(fd); $('#phPrev').innerHTML = `<img src="${APP.base}/assets/uploads/productos/${j.imagen}?t=${Date.now()}" alt="">`; $('#phDel').hidden = false; toast('Foto actualizada'); load(); } catch (e) { toast(e.message, 'danger'); }
    ev.target.value = '';
  });
  document.addEventListener('click', async (ev) => {
    if (!ev.target.closest('#phDel')) return; const fd = new FormData(); fd.append('id', editing); fd.append('a', 'quitar');
    try { await sendPhoto(fd); $('#phPrev').innerHTML = '<i class="bi bi-image"></i>'; $('#phDel').hidden = true; toast('Foto eliminada'); load(); } catch (e) { toast(e.message, 'danger'); }
  });
  async function openForm(id) {
    editing = id; $('#frmErr').textContent = '';
    try {
      const r = await api(`crud.php?e=${C.entity}&a=get&id=${id}`);
      $('#mdlTitle').textContent = (id ? 'Editar ' : C.nuevo + ' ') + C.singular.toLowerCase();
      $('#fields').innerHTML = C.fields.filter((f) => !(f.flag === 'only_new' && id)).map((f) => `<div class="col-md-${f.w}">${input(f, r.row[f.name])}</div>`).join('');
      if (C.entity === 'productos') $('#fields').insertAdjacentHTML('afterbegin', photoBox(id, r.row.imagen));
      modal.show(); setTimeout(() => $('#fields input,#fields select')?.focus(), 400);
    } catch (e) { toast(e.message, 'danger'); }
  }

  /* ---- Ver detalle ---- */
  document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="mdlView" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content border-0" style="border-radius:20px">
    <div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="modal-title fw-bold" id="vTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body px-4" id="vBody"></div>
    <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button><button class="btn-pos" id="vEdit" hidden><i class="bi bi-pencil"></i> Editar</button></div></div></div></div>`);
  const vModal = new bootstrap.Modal($('#mdlView'));
  const EXTRA = { // datos adicionales por módulo (campo, etiqueta, tipo)
    productos: [['stock', 'Stock en la sucursal', 'stock'], ['stock_total', 'Stock total (todas las sucursales)', 'num']],
    clientes: [['puntos', 'Puntos acumulados', 'num'], ['saldo_deuda', 'Deuda pendiente', 'debt']],
    categorias: [['productos', 'Productos en la categoría', 'num']], marcas: [['productos', 'Productos de la marca', 'num']],
    gastos: [['sucursal', 'Sucursal', 'text']], usuarios: [['ultimo_acceso', 'Último acceso', 'datetime']],
    sucursales: [['usuarios', 'Usuarios asignados', 'num']], promociones: [['sucursal', 'Sucursal', 'text']],
  };
  function fval(f, v, r) {
    if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
    if (f.type === 'switch') return R.estado(v);
    if (f.type === 'select' && f.options) { const o = f.options[v]; return esc(o !== undefined ? o : v); }
    if (f.type === 'money') return `<b>${money(v)}</b>`;
    if (f.type === 'number') return fmt(v, v % 1 ? 2 : 0);
    if (f.type === 'date') return R.date(v);
    if (f.type === 'color') return R.color(v);
    if (f.type === 'password') return '••••••';
    return esc(String(v));
  }
  async function openView(id) {
    try {
      const [g, l] = await Promise.all([api(`crud.php?e=${C.entity}&a=get&id=${id}`), Promise.resolve(rows.find((x) => x.id == id) || {})]);
      const r = { ...l, ...g.row }, items = [];
      C.fields.forEach((f) => { if (f.flag === 'only_new' || f.type === 'password' || f.type === 'switch') return; items.push([f.label, fval(f, r[f.name], r), ['textarea'].includes(f.type) || f.w >= 12]); });
      (EXTRA[C.entity] || []).forEach(([k, lb, t]) => { if (r[k] === undefined || (t !== 'stock' && C.fields.some((f) => f.name === k))) return; const h = t === 'stock' ? R.stock(+r[k], r) : t === 'debt' ? (r[k] > 0 ? R.debt(+r[k]) : '<span class="text-muted">Sin deuda</span>') : t === 'datetime' ? R.datetime(r[k]) : t === 'num' ? fmt(r[k], r[k] % 1 ? 2 : 0) : esc(r[k] ?? '—'); items.push([lb, h, false]); });
      const title = r.concepto ?? r.nombre ?? r.razon_social ?? r.nombres ?? C.singular;
      const nom = C.entity === 'usuarios' ? `${r.nombres} ${r.apellidos}` : title;
      $('#vTitle').innerHTML = `${esc(nom)} ${r.estado !== undefined ? R.estado(r.estado) : ''}`;
      const img = r.imagen ? `<div class="text-center mb-3"><img src="${APP.base}/assets/uploads/productos/${esc(r.imagen)}" alt="" style="max-height:150px;border-radius:14px;object-fit:cover"></div>` : '';
      $('#vBody').innerHTML = img + '<div class="row g-3">' + items.map(([lb, h, full]) => `<div class="col-${full ? 12 : 6} col-md-${full ? 12 : 4}"><div class="small text-muted text-uppercase" style="font-size:.68rem;letter-spacing:.04em">${esc(lb)}</div><div class="fw-semibold">${h}</div></div>`).join('') + '</div>';
      const eb = $('#vEdit'); eb.hidden = !(C.write && !C.protected.includes(+id)); eb.onclick = () => { vModal.hide(); setTimeout(() => openForm(+id), 250); };
      vModal.show();
    } catch (e) { toast(e.message, 'danger'); }
  }
  $('#frm').addEventListener('submit', async (ev) => {
    ev.preventDefault(); const data = {};
    C.fields.forEach((f) => { const el = $('#f_' + f.name); if (el) data[f.name] = f.type === 'switch' ? (el.checked ? 1 : 0) : el.value; });
    $('#btnSave').disabled = true;
    try { const r = await api('crud.php', { e: C.entity, a: 'save', id: editing, data }); toast(r.msg); modal.hide(); load(); }
    catch (e) { $('#frmErr').textContent = e.message; }
    $('#btnSave').disabled = false;
  });

  document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-a]'), pg = ev.target.closest('#pager [data-p]');
    if (pg && !pg.disabled) { page = +pg.dataset.p; load(); return; }
    if (!b) return; const id = +b.dataset.id;
    if (b.dataset.a === 'view') openView(id);
    if (b.dataset.a === 'edit') openForm(id);
    if (b.dataset.a === 'tog') { try { await api('crud.php', { e: C.entity, a: 'toggle', id }); toast('Estado actualizado'); load(); } catch (e) { toast(e.message, 'danger'); } }
  });
  $('#btnNew')?.addEventListener('click', () => openForm(0));
  $('#q').addEventListener('input', debounce(() => { page = 1; load(); }));
  document.querySelectorAll('[data-filter],#fEstado,#fDesde,#fHasta').forEach((el) => el?.addEventListener('change', () => { page = 1; load(); }));
  $('#btnCsv').addEventListener('click', () => exportCSV(C.entity, C.cols.map((c) => c[1]), rows.map((r) => C.cols.map((c) => r[c[0]]))));
  head(); load();
})();
