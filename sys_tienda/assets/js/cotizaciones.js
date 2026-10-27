(function () {
  const $ = (s) => document.querySelector(s); let L = [], promos = [], cli = { id: 1, nombre: 'Cliente Varios' }, todas = false;
  const mN = new bootstrap.Modal($('#mdlN')), mD = new bootstrap.Modal($('#mdlD')); const fd = (d) => d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(0, 4);
  const est = (c) => c.estado === 'PENDIENTE' ? (c.vencida == 1 ? ['bad', 'Vencida'] : ['info', 'Vigente']) : c.estado === 'ACEPTADA' ? ['ok', 'Vendida'] : ['neutral', 'Anulada'];
  async function load() {
    const r = await api('cotizaciones.php?a=list&q=' + encodeURIComponent($('#q').value) + '&estado=' + $('#estado').value); todas = r.todas;
    $('#tb').innerHTML = r.rows.length ? r.rows.map((c) => { const [k, l] = est(c);
      return `<tr><td><b>COT-${String(c.id).padStart(5, '0')}</b></td><td>${fd(c.fecha)}</td><td>${esc(c.cliente)}</td><td>${esc(c.vendedor)}${todas && c.sucursal ? `<div class="small text-muted">${esc(c.sucursal)}</div>` : ''}</td><td>${fd(c.vigencia)}</td><td><span class="pill ${k}">${l}</span></td><td class="r fw-bold num">${money(c.total)}</td><td class="r"><button class="act" data-id="${c.id}"><i class="bi bi-eye"></i></button></td></tr>`; }).join('')
      : '<tr><td colspan="8"><div class="empty"><i class="bi bi-file-earmark-text"></i>Sin cotizaciones</div></td></tr>';
  }
  const calc = () => { L.forEach((l) => (l.c = calcLineaP(l, promos))); return L.reduce((a, l) => a + l.c.sub, 0); };
  function render() {
    const t = calc();
    $('#lines').innerHTML = L.length ? L.map((l, i) => `<tr><td style="white-space:normal"><b>${esc(l.nombre)}</b>${l.c.promo ? `<div class="small text-success"><i class="bi bi-percent"></i> ${esc(l.c.promo)}</div>` : ''}${l.c.precio !== +l.precio_venta ? '<div class="small text-primary">Precio por mayor</div>' : ''}</td>
      <td><input type="number" min="0.01" step="any" class="form-control form-control-sm text-end" data-i="${i}" value="${l.cant}"></td><td class="r">${fmt(l.c.sub / l.cant)}</td><td class="r fw-bold num">${fmt(l.c.sub)}</td><td class="r"><button class="act del" data-rm="${i}"><i class="bi bi-x-lg"></i></button></td></tr>`).join('')
      : '<tr><td colspan="5"><div class="empty py-3"><i class="bi bi-box-seam"></i>Busca y agrega productos</div></td></tr>';
    $('#total').textContent = money(t);
  }
  productPicker($('#pq'), $('#pres'), 'cotizaciones.php?a=productos', (p) => { const x = L.find((l) => l.id === p.id); if (x) x.cant++; else L.push({ ...p, cant: 1 }); render(); }, (p) => money(p.precio_venta));
  api('cotizaciones.php?a=productos&q=%25').then((r) => (promos = r.promos || [])).catch(() => {});
  $('#lines').addEventListener('change', (e) => { const t = e.target; if (t.dataset.i) { L[+t.dataset.i].cant = Math.max(0.01, parseFloat(t.value) || 1); render(); } });
  $('#lines').addEventListener('click', (e) => { const b = e.target.closest('[data-rm]'); if (b) { L.splice(+b.dataset.rm, 1); render(); } });
  // cliente
  let clis = [];
  $('#cliq').addEventListener('input', debounce(async () => { const v = $('#cliq').value.trim(); if (!v) { $('#clires').style.display = 'none'; return; }
    clis = (await api('cotizaciones.php?a=clientes&q=' + encodeURIComponent(v))).rows;
    $('#clires').innerHTML = clis.map((c) => `<div class="cl-item" data-id="${c.id}"><b class="small">${esc(c.nombre)}</b><span class="text-muted small">${esc(c.documento || '')}</span></div>`).join('') || '<div class="empty py-3">Sin resultados</div>'; $('#clires').style.display = 'block'; }));
  $('#clires').addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (!d) return; cli = clis.find((c) => c.id === +d.dataset.id); $('#cliSel').textContent = cli.nombre; $('#cliq').value = ''; $('#clires').style.display = 'none'; });
  $('#btnNew').onclick = () => { L = []; cli = { id: 1, nombre: 'Cliente Varios' }; $('#cliSel').textContent = cli.nombre; $('#nota').value = ''; $('#dias').value = 7; render(); mN.show(); };
  $('#btnSave').onclick = async () => {
    if (!L.length) return toast('Agrega productos', 'warning');
    try { const r = await api('cotizaciones.php', { a: 'save', cliente_id: cli.id, dias: $('#dias').value, nota: $('#nota').value, items: L.map((l) => ({ id: l.id, cant: l.cant })) });
      toast('Cotización guardada: ' + money(r.total)); mN.hide(); load(); detalle(r.id); } catch (e) { toast(e.message, 'danger'); }
  };
  async function detalle(id) {
    const { cot: c } = await api('cotizaciones.php?a=get&id=' + id), [k, l] = est({ ...c, vencida: c.estado === 'PENDIENTE' && c.vigencia < new Date().toISOString().slice(0, 10) ? 1 : 0 });
    const vig = c.estado === 'PENDIENTE' && c.vigencia >= new Date().toISOString().slice(0, 10);
    let tel = String(c.telefono || '').replace(/\D/g, ''); if (tel.length === 9) tel = '51' + tel;
    const msg = `Hola ${c.cliente.split(' ')[0]}, te enviamos la cotización COT-${String(c.id).padStart(5, '0')} de ${window.COT_EMPRESA} (válida hasta ${fd(c.vigencia)}):\n` + c.items.map((i) => `• ${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)} x ${i.nombre} — ${money(i.subtotal)}`).join('\n') + `\nTOTAL: ${money(c.total)}`;
    $('#dBody').innerHTML = `<div class="d-flex justify-content-between mb-2"><div><h5 class="fw-bold m-0">COT-${String(c.id).padStart(5, '0')}</h5><small class="text-muted">${esc(c.cliente)} · válida hasta ${fd(c.vigencia)}</small></div><span class="pill ${k}">${l}</span></div>
      <table class="tbl"><thead><tr><th>Producto</th><th class="r">Cant.</th><th class="r">P.U.</th><th class="r">Importe</th></tr></thead><tbody>${c.items.map((i) => `<tr><td style="white-space:normal">${esc(i.nombre)}</td><td class="r">${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)}</td><td class="r">${fmt(i.precio_unitario)}</td><td class="r">${fmt(i.subtotal)}</td></tr>`).join('')}</tbody></table>
      <div class="text-end fs-5 fw-bold mt-2">Total: ${money(c.total)}</div>${c.nota ? `<div class="small text-muted mt-1">${esc(c.nota)}</div>` : ''}
      <div class="d-flex gap-2 mt-3 flex-wrap"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cerrar</button><a class="btn-soft flex-fill justify-content-center" target="_blank" href="${APP.base}/print_cot.php?id=${c.id}"><i class="bi bi-printer"></i> Imprimir</a>
      <a class="btn flex-fill text-white" style="background:#25d366" target="_blank" href="https://wa.me/${tel}?text=${encodeURIComponent(msg)}"><i class="bi bi-whatsapp"></i></a></div>
      ${vig ? `<div class="d-flex gap-2 mt-2"><a class="btn-pos flex-fill justify-content-center" href="${APP.base}/modulo.php?m=pos&cot=${c.id}"><i class="bi bi-cart-check"></i> Convertir en venta</a><button class="btn-soft danger" id="anC"><i class="bi bi-x-octagon"></i></button></div>` : ''}`;
    $('#anC')?.addEventListener('click', async () => { mD.hide(); if (!await confirmBox('La cotización quedará anulada.', '¿Anular cotización?', 'Anular', true)) return; try { await api('cotizaciones.php', { a: 'anular', id: c.id }); toast('Cotización anulada'); load(); } catch (x) { toast(x.message, 'danger'); } });
    mD.show();
  }
  document.addEventListener('click', (e) => { const b = e.target.closest('#tb [data-id]'); if (b) detalle(b.dataset.id); });
  $('#q').addEventListener('input', debounce(load)); $('#estado').addEventListener('change', load); load();
})();
