(function () {
  const $ = (s) => document.querySelector(s); let page = 1; const md = new bootstrap.Modal($('#mdlC'));
  async function load() {
    const p = new URLSearchParams({ a: 'list', page, q: $('#q').value, proveedor: $('#prov').value, desde: $('#desde').value, hasta: $('#hasta').value });
    try { const r = await api('compras.php?' + p); page = r.page; $('#sum').textContent = money(r.sum);
      $('#tb').innerHTML = r.rows.length ? r.rows.map((c) => { const [k, l] = statusPill(c.estado); return `<tr><td>#${c.id}</td><td>${c.fecha.split('-').reverse().join('/')}</td><td>${esc(c.comprobante || '—')}</td><td>${esc(c.proveedor)}</td><td class="r">${c.items}</td><td><span class="pill ${k}">${l}</span></td><td class="r fw-bold num">${money(c.total)}</td><td class="r"><button class="act" data-id="${c.id}"><i class="bi bi-eye"></i></button></td></tr>`; }).join('') : '<tr><td colspan="8"><div class="empty"><i class="bi bi-bag"></i>Sin compras registradas</div></td></tr>';
      $('#pager').innerHTML = pagerHtml(r.page, r.pages, r.total, r.per); } catch (e) { toast(e.message, 'danger'); }
  }
  document.addEventListener('click', async (e) => {
    const pg = e.target.closest('#pager [data-p]'); if (pg && !pg.disabled) { page = +pg.dataset.p; load(); }
    const b = e.target.closest('#tb [data-id]'); if (!b) return;
    const { compra: c } = await api('compras.php?a=get&id=' + b.dataset.id), [k, l] = statusPill(c.estado);
    $('#cBody').innerHTML = `<div class="d-flex justify-content-between mb-3"><div><h5 class="fw-bold m-0">Compra #${c.id}</h5><small class="text-muted">${esc(c.proveedor)} · ${esc(c.comprobante || 'sin comprobante')} · ${c.fecha.split('-').reverse().join('/')}</small></div><span class="pill ${k}">${l}</span></div>
      <table class="tbl"><thead><tr><th>Producto</th><th class="r">Cant.</th><th class="r">Costo</th><th class="r">Subtotal</th></tr></thead><tbody>${c.items.map((i) => `<tr><td style="white-space:normal">${esc(i.nombre)}</td><td class="r">${fmt(i.cantidad, 0)} ${esc(i.unidad)}</td><td class="r">${fmt(i.costo_unitario)}</td><td class="r">${fmt(i.subtotal)}</td></tr>`).join('')}</tbody></table>
      <div class="text-end fs-5 fw-bold mt-3">Total: ${money(c.total)}</div><div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cerrar</button>${APP.rol === 1 && c.estado === 'RECIBIDA' ? '<button class="btn-soft danger flex-fill justify-content-center" id="anC"><i class="bi bi-x-octagon"></i> Anular compra</button>' : ''}</div>`;
    $('#anC')?.addEventListener('click', async () => { md.hide(); if (!await confirmBox('Se descontará del stock lo ingresado por esta compra.', `¿Anular compra #${c.id}?`, 'Anular', true)) return;
      try { await api('compras.php', { a: 'anular', id: c.id }); toast('Compra anulada'); load(); } catch (x) { toast(x.message, 'danger'); } });
    md.show();
  });
  $('#q').addEventListener('input', debounce(() => { page = 1; load(); })); ['prov', 'desde', 'hasta'].forEach((i) => $('#' + i).addEventListener('change', () => { page = 1; load(); }));
  load();
})();
