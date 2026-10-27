(function () {
  const $ = (s) => document.querySelector(s); const mP = new bootstrap.Modal($('#mdlP')); let cur = null;
  const st = (ic, c, l, v) => `<div class="col-md-3 col-6"><div class="cardx d-flex align-items-center gap-3"><div class="kpi-ic ${c}"><i class="bi ${ic}"></i></div><div><div class="muted small">${l}</div><div class="fs-4 fw-800 num">${v}</div></div></div></div>`;
  async function load() {
    const r = await api(`creditos.php?a=list&q=${encodeURIComponent($('#q').value)}&solo=${$('#solo').checked ? 1 : 0}`);
    $('#stats').innerHTML = st('bi-journal-text', 'ic-rose', 'Por cobrar', money(r.deuda)) + st('bi-people', 'ic-amber', 'Clientes deudores', r.deudores) + st('bi-cash-coin', 'ic-green', 'Cobrado este mes', money(r.cobrado_mes)) + st('bi-credit-card', 'ic-indigo', 'Líneas otorgadas', money(r.limite));
    $('#tb').innerHTML = r.rows.map((c) => { const pc = c.limite_credito > 0 ? Math.min(100, c.saldo_deuda / c.limite_credito * 100) : 0;
      return `<tr><td><b>${esc(c.nombre)}</b></td><td>${esc(c.tipo_doc)} ${esc(c.documento || '')}</td><td>${esc(c.telefono || '—')}</td><td class="r num">${money(c.limite_credito)}</td><td class="r">${c.saldo_deuda > 0 ? `<span class="pill bad">${money(c.saldo_deuda)}</span>` : '<span class="text-muted">Al día</span>'}</td>
      <td><div class="bar-s"><i style="width:${pc}%;background:${pc > 80 ? '#f43f5e' : pc > 50 ? '#f59e0b' : '#10b981'}"></i></div><small class="text-muted">${pc.toFixed(0)}% usado</small></td><td class="r"><button class="btn-soft" data-id="${c.id}"><i class="bi bi-cash-coin"></i> Detalle / Pagar</button></td></tr>`; }).join('') || '<tr><td colspan="7"><div class="empty"><i class="bi bi-emoji-smile"></i>No hay clientes con deuda</div></td></tr>';
  }
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('#tb [data-id]'); if (!b) return;
    const r = await api('creditos.php?a=detalle&id=' + b.dataset.id); cur = r.cliente; const c = r.cliente, f = (d) => d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(2, 4);
    $('#pBody').innerHTML = `<div class="d-flex justify-content-between mb-3"><div><h5 class="fw-bold m-0">${esc(c.nombre)}</h5><small class="text-muted">${esc(c.tipo_doc)} ${esc(c.documento || '')}</small></div><div class="text-end"><small class="text-muted d-block">Deuda actual</small><b class="fs-4 text-danger num">${money(c.saldo_deuda)}</b></div></div>
      <div class="row g-3 mb-3"><div class="col-6"><div class="fw-semibold small mb-1">Últimas ventas a crédito</div>${r.ventas.map((v) => `<div class="d-flex justify-content-between small py-1 border-bottom"><span>${esc(v.nro)} <span class="text-muted">${f(v.fecha)}</span></span><b class="${v.estado === 'ANULADA' ? 'text-decoration-line-through text-muted' : ''}">${fmt(v.total)}</b></div>`).join('') || '<div class="small text-muted">Sin ventas a crédito</div>'}</div>
      <div class="col-6"><div class="fw-semibold small mb-1">Últimos pagos</div>${r.pagos.map((p) => `<div class="d-flex justify-content-between small py-1 border-bottom"><span>${f(p.fecha)} <span class="text-muted">${esc(p.metodo.toLowerCase())}</span></span><b class="text-success">+${fmt(p.monto)}</b></div>`).join('') || '<div class="small text-muted">Sin pagos</div>'}</div></div>
      ${c.saldo_deuda > 0 ? `<hr><div class="row g-2"><div class="col-5"><label class="form-label">Monto a pagar</label><input type="number" step="0.10" min="0.1" max="${c.saldo_deuda}" class="form-control" id="pMonto" value="${c.saldo_deuda}" required></div>
      <div class="col-7"><label class="form-label">Método</label><select class="form-select" id="pMet"><option value="EFECTIVO">Efectivo (a caja)</option><option>YAPE</option><option>PLIN</option><option>TARJETA</option><option>TRANSFERENCIA</option></select></div>
      <div class="col-12"><input class="form-control" id="pNota" placeholder="Nota (opcional)" maxlength="150"></div></div>` : ''}
      <div class="d-flex gap-2 mt-3"><button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cerrar</button>${c.saldo_deuda > 0 ? '<button class="btn-pos flex-fill justify-content-center"><i class="bi bi-check2"></i> Registrar pago</button>' : ''}</div>`;
    mP.show();
  });
  $('#frmP').onsubmit = async (e) => { e.preventDefault(); try { const r = await api('creditos.php', { a: 'pagar', cliente_id: cur.id, monto: $('#pMonto').value, metodo: $('#pMet').value, nota: $('#pNota').value }); toast(r.saldo > 0 ? `Pago registrado. Saldo: ${money(r.saldo)}` : 'Deuda cancelada por completo'); mP.hide(); load(); } catch (x) { toast(x.message, 'danger'); } };
  $('#q').addEventListener('input', debounce(load)); $('#solo').addEventListener('change', load); load();
})();
