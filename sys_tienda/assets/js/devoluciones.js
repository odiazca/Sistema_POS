(function () {
  const $ = (s) => document.querySelector(s); let V = null, todas = false; const mN = new bootstrap.Modal($('#mdlN')), mD = new bootstrap.Modal($('#mdlD'));
  const fd = (d) => d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(2, 4) + ' ' + d.slice(11, 16);
  const RB = { EFECTIVO: 'Efectivo', MISMO_MEDIO: 'Mismo medio', CREDITO: 'Crédito' };
  async function load() {
    const r = await api('devoluciones.php?a=list'); todas = r.todas; $('#sMes').textContent = money(r.mes);
    $('#tb').innerHTML = r.rows.length ? r.rows.map((d) => `<tr><td>#${d.id}</td><td>${fd(d.fecha)}</td><td><b>${esc(d.nro)}</b>${todas && d.sucursal ? `<div class="small text-muted">${esc(d.sucursal)}</div>` : ''}</td><td style="white-space:normal;max-width:240px">${esc(d.motivo)}</td><td><span class="pill info">${RB[d.reembolso]}</span></td><td>${d.reintegra_stock == 1 ? '<span class="pill ok">Reintegrado</span>' : '<span class="pill neutral">Merma</span>'}</td><td>${esc(d.usuario)}</td><td class="r fw-bold num">${money(d.total)}</td><td class="r"><button class="act" data-id="${d.id}"><i class="bi bi-eye"></i></button></td></tr>`).join('')
      : '<tr><td colspan="9"><div class="empty"><i class="bi bi-arrow-return-left"></i>Sin devoluciones registradas</div></td></tr>';
  }
  async function buscar() {
    const r = await api('devoluciones.php?a=buscar&q=' + encodeURIComponent($('#vq').value.trim()));
    $('#vres').innerHTML = r.rows.map((v) => `<div class="cl-item" data-id="${v.id}"><div><b class="small">${esc(v.nro)}</b><div class="text-muted" style="font-size:.72rem">${fd(v.fecha)} · ${esc(v.cliente)} · ${esc(v.metodo_pago)}</div></div><b class="num">${money(v.total)}</b></div>`).join('') || '<div class="empty py-3">Sin ventas encontradas</div>';
  }
  async function elegir(id) {
    try { const r = await api('devoluciones.php?a=venta&id=' + id); V = r.venta; } catch (e) { return toast(e.message, 'danger'); }
    $('#stepBuscar').style.display = 'none'; $('#stepItems').style.display = 'block';
    $('#vNro').textContent = V.nro; $('#vInfo').textContent = `· ${V.cliente} · ${V.metodo_pago} · ${money(V.total)}`; $('#motivo').value = ''; $('#reint').checked = true;
    const cred = V.metodo_pago === 'CREDITO'; $('#reemb').disabled = cred; if (cred) $('#reemb').innerHTML = '<option value="CREDITO">Descontar de la deuda</option>'; else $('#reemb').innerHTML = '<option value="EFECTIVO">Efectivo (sale de caja)</option>' + (V.metodo_pago !== 'EFECTIVO' ? '<option value="MISMO_MEDIO">Mismo medio de pago (' + esc(V.metodo_pago) + ')</option>' : '');
    $('#credNote').textContent = cred ? 'Venta al crédito: el monto se descuenta de la deuda del cliente.' : '';
    $('#vItems').innerHTML = V.items.map((i, n) => `<tr><td style="white-space:normal">${esc(i.nombre)}</td><td class="r">${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)}</td><td class="r">${fmt(i.devuelta, 0)}</td><td class="r">${fmt(i.unit_neto)}</td>
      <td>${i.disponible > 0 ? `<input type="number" min="0" max="${i.disponible}" step="any" value="${i.disponible}" title="Cantidad de unidades (máx. ${i.disponible})" class="form-control form-control-sm text-end" data-n="${n}">` : '<span class="text-muted small">—</span>'}</td></tr>`).join(''); tot();
  }
  function tot() { let t = 0; document.querySelectorAll('#vItems [data-n]').forEach((el) => { const i = V.items[+el.dataset.n]; const c = Math.min(Math.max(0, parseFloat(el.value) || 0), i.disponible); t += c * i.unit_neto; }); $('#vTot').textContent = money(t); }
  $('#vItems').addEventListener('input', (e) => { const el = e.target; if (el.dataset.n !== undefined) { const m = V.items[+el.dataset.n].disponible; if (parseFloat(el.value) > m) { el.value = m; toast('Máximo a devolver: ' + m + ' unidad(es)', 'warning'); } } tot(); });
  $('#vq').addEventListener('input', debounce(buscar));
  $('#vres').addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (d) elegir(+d.dataset.id); });
  $('#vBack').onclick = () => { $('#stepItems').style.display = 'none'; $('#stepBuscar').style.display = 'block'; };
  $('#btnNew').onclick = () => { $('#stepItems').style.display = 'none'; $('#stepBuscar').style.display = 'block'; $('#vq').value = ''; buscar(); mN.show(); };
  $('#btnSave').onclick = async () => {
    const items = [...document.querySelectorAll('#vItems [data-n]')].map((el) => ({ producto_id: V.items[+el.dataset.n].producto_id, cant: parseFloat(el.value) || 0 })).filter((x) => x.cant > 0);
    if (!items.length) return toast('Indica la cantidad a devolver', 'warning'); if (!$('#motivo').value.trim()) return toast('Indica el motivo', 'warning');
    try { const r = await api('devoluciones.php', { a: 'save', venta_id: V.id, items, motivo: $('#motivo').value, reintegra_stock: $('#reint').checked ? 1 : 0, reembolso: $('#reemb').value }); toast(`Devolución registrada: ${money(r.total)}`); mN.hide(); load(); } catch (e) { toast(e.message, 'danger'); }
  };
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('#tb [data-id]'); if (!b) return; const { dev: d } = await api('devoluciones.php?a=get&id=' + b.dataset.id);
    $('#dBody').innerHTML = `<h5 class="fw-bold">Devolución #${d.id} · ${esc(d.nro)}</h5><div class="small text-muted mb-3">${fd(d.fecha)} · ${esc(d.motivo)} · ${RB[d.reembolso]} · ${d.reintegra_stock == 1 ? 'Stock reintegrado' : 'Sin reintegro (merma)'}</div>
      <table class="tbl"><thead><tr><th>Producto</th><th class="r">Cant.</th><th class="r">Importe</th></tr></thead><tbody>${d.items.map((i) => `<tr><td style="white-space:normal">${esc(i.nombre)}</td><td class="r">${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)}</td><td class="r">${fmt(i.subtotal)}</td></tr>`).join('')}</tbody></table>
      <div class="text-end fs-5 fw-bold mt-2">Total: ${money(d.total)}</div><button class="btn btn-light w-100 mt-3" data-bs-dismiss="modal">Cerrar</button>`; mD.show();
  });
  load(); if (window.DEV_VENTA) { buscar(); mN.show(); elegir(window.DEV_VENTA); }
})();
