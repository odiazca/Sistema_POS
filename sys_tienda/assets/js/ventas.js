(function () {
  const $ = (s) => document.querySelector(s); let page = 1, rows = [], admin = false; const md = new bootstrap.Modal($('#mdlV'));
  const qs = () => new URLSearchParams({ a: 'list', page, q: $('#q').value.trim(), desde: $('#desde').value, hasta: $('#hasta').value, estado: $('#estado').value, metodo: $('#metodo').value, tipo: $('#tipo').value });
  const fdate = (d) => d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(2, 4) + ' ' + d.slice(11, 16);
  async function load() {
    try {
      const r = await api('ventas.php?' + qs()); rows = r.rows; page = r.page; admin = r.admin; window.VENTAS_ALL = r.todas;
      $('#sTotal').textContent = money(r.sum.total); $('#sN').textContent = r.sum.n; $('#sAn').textContent = r.sum.anuladas;
      $('#tb').innerHTML = rows.length ? rows.map((v) => { const [c, l] = statusPill(v.estado);
        return `<tr><td><b>${esc(v.nro)}</b><div class="small text-muted">${v.tipo}</div></td><td>${fdate(v.fecha)}</td><td>${esc(v.cliente)}</td><td>${esc(v.cajero)}${v.sucursal && window.VENTAS_ALL ? `<div class="small text-muted">${esc(v.sucursal)}</div>` : ''}</td><td>${v.metodo_pago.charAt(0) + v.metodo_pago.slice(1).toLowerCase()}</td><td><span class="pill ${c}">${l}</span>${v.devuelto > 0 ? ' <span class="pill warn" title="Devoluciones">Dev. ' + fmt(v.devuelto) + '</span>' : ''}</td>
        <td class="r fw-bold num ${v.estado === 'ANULADA' ? 'text-decoration-line-through text-muted' : ''}">${money(v.total)}</td><td class="r"><button class="act" data-id="${v.id}" title="Ver detalle"><i class="bi bi-eye"></i></button></td></tr>`; }).join('')
        : '<tr><td colspan="8"><div class="empty"><i class="bi bi-receipt"></i>Sin ventas en este filtro</div></td></tr>';
      $('#pager').innerHTML = pagerHtml(r.page, r.pages, r.total, r.per);
    } catch (e) { toast(e.message, 'danger'); }
  }
  function wa(v) {
    let tel = String(v.telefono || '').replace(/\D/g, ''); if (tel.length === 9) tel = '51' + tel;
    const msg = `Hola ${v.cliente.split(' ')[0]}, adjuntamos el detalle de tu compra ${v.nro} (${fdate(v.fecha)}):\n` + v.items.map((i) => `• ${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)} x ${i.nombre} — ${money(i.subtotal)}`).join('\n') + `\nTOTAL: ${money(v.total)}\nGracias por tu preferencia.`;
    return `https://wa.me/${tel}?text=${encodeURIComponent(msg)}`;
  }
  async function detalle(id) {
    const { venta: v } = await api(`ventas.php?a=get&id=${id}`), [c, l] = statusPill(v.estado);
    $('#vBody').innerHTML = `<div class="d-flex justify-content-between align-items-start mb-3"><div><h5 class="fw-bold m-0">${esc(v.nro)}</h5><small class="text-muted">${esc(v.tipo_comprobante)} · ${fdate(v.fecha)}</small></div><span class="pill ${c}">${l}</span></div>
      <div class="small mb-3"><div><b>Cliente:</b> ${esc(v.cliente)} ${v.documento ? '(' + esc(v.documento) + ')' : ''}</div><div><b>Cajero:</b> ${esc(v.cajero)}</div><div><b>Pago:</b> ${esc(v.metodo_pago)}</div></div>
      <table class="tbl"><thead><tr><th>Producto</th><th class="r">Cant.</th><th class="r">P.U.</th><th class="r">Importe</th></tr></thead><tbody>${v.items.map((i) => `<tr><td style="white-space:normal">${esc(i.nombre)}${i.promo ? `<div class="small text-success"><i class="bi bi-percent"></i> ${esc(i.promo)} (−${fmt(i.descuento)})</div>` : ''}${i.devuelta > 0 ? `<div class="small text-warning">Devuelto: ${fmt(i.devuelta, 0)}</div>` : ''}</td><td class="r">${fmt(i.cantidad, i.cantidad % 1 ? 2 : 0)}</td><td class="r">${fmt(i.precio_unitario)}</td><td class="r">${fmt(i.subtotal)}</td></tr>`).join('')}</tbody></table>
      <div class="mt-3 ms-auto" style="max-width:220px"><div class="d-flex justify-content-between small text-muted"><span>Subtotal</span><span>${fmt(v.subtotal)}</span></div><div class="d-flex justify-content-between small text-muted"><span>${esc(APP.impuesto)}</span><span>${fmt(v.igv)}</span></div>
      ${v.descuento > 0 ? `<div class="d-flex justify-content-between small text-muted"><span>Descuentos</span><span>-${fmt(v.descuento)}</span></div>` : ''}${v.puntos_ganados ? `<div class="d-flex justify-content-between small text-muted"><span>Puntos ganados</span><span>+${v.puntos_ganados}</span></div>` : ''}<div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span>${money(v.total)}</span></div></div>
      <div class="d-flex gap-2 mt-4"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cerrar</button><a class="btn-soft flex-fill justify-content-center" target="_blank" data-ticket="${v.id}" href="${APP.base}/print.php?id=${v.id}"><i class="bi bi-printer"></i> Imprimir</a><a class="btn flex-fill text-white" style="background:#25d366" target="_blank" href="${wa(v)}"><i class="bi bi-whatsapp"></i></a>
      ${v.estado === 'COMPLETADA' ? `<a class="btn-soft flex-fill justify-content-center" href="${APP.base}/modulo.php?m=devoluciones&venta=${v.id}"><i class="bi bi-arrow-return-left"></i> Devolver</a>` : ''}
      ${admin && v.estado === 'COMPLETADA' ? `<button class="btn-soft danger flex-fill justify-content-center" id="btnAn"><i class="bi bi-x-octagon"></i> Anular</button>` : ''}</div>`;
    $('#btnAn')?.addEventListener('click', async () => {
      md.hide(); if (!await confirmBox('Se devolverá el stock y se revertirá el crédito si aplica.', `¿Anular ${v.nro}?`, 'Anular venta', true)) return;
      try { await api('ventas.php', { a: 'anular', id: v.id }); toast('Venta anulada'); load(); } catch (e) { toast(e.message, 'danger'); }
    });
    md.show();
  }
  document.addEventListener('click', (e) => { const b = e.target.closest('#tb [data-id]'), pg = e.target.closest('#pager [data-p]'); if (b) detalle(b.dataset.id); if (pg && !pg.disabled) { page = +pg.dataset.p; load(); } });
  $('#q').addEventListener('input', debounce(() => { page = 1; load(); }));
  ['desde', 'hasta', 'estado', 'metodo', 'tipo'].forEach((i) => $('#' + i).addEventListener('change', () => { page = 1; load(); }));
  $('#btnCsv').onclick = () => exportCSV('ventas', ['Comprobante', 'Fecha', 'Cliente', 'Cajero', 'Pago', 'Estado', 'Total'], rows.map((v) => [v.nro, v.fecha, v.cliente, v.cajero, v.metodo_pago, v.estado, v.total]));
  load();
})();
