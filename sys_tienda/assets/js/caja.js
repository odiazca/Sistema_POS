(function () {
  const $ = (s) => document.querySelector(s); let ST = null;
  const mMov = new bootstrap.Modal($('#mdlMov')), mCer = new bootstrap.Modal($('#mdlCerrar')); let movTipo = 'INGRESO';
  const MET = { EFECTIVO: ['#10b981', 'bi-cash-coin'], YAPE: ['#7c3aed', 'bi-phone'], PLIN: ['#06b6d4', 'bi-phone-vibrate'], TARJETA: ['#5b5ef4', 'bi-credit-card-2-front'], TRANSFERENCIA: ['#0ea5e9', 'bi-bank'], CREDITO: ['#f59e0b', 'bi-journal-text'] };
  const kp = (ic, cls, l, v, s = '') => `<div class="col-6 col-xl-2"><div class="cardx kpi"><div class="kpi-ic ${cls} mb-2"><i class="bi ${ic}"></i></div><div class="lbl">${l}</div><div class="val num" style="font-size:1.35rem">${v}</div><div class="foot">${s}</div></div></div>`;
  const fd = (d) => d ? d.slice(8, 10) + '/' + d.slice(5, 7) + ' ' + d.slice(11, 16) : '—';

  async function load() {
    const r = await api('caja.php?a=estado'); ST = r;
    $('#closed').style.display = r.caja ? 'none' : ''; $('#open').style.display = r.caja ? '' : 'none';
    $('#acts').innerHTML = r.caja ? `<button class="btn-soft" data-mov="INGRESO"><i class="bi bi-plus-circle"></i> Ingreso</button><button class="btn-soft" data-mov="EGRESO"><i class="bi bi-dash-circle"></i> Egreso</button>${Imprimir.directa() && APP.print.cajon ? '<button class="btn-soft" id="btnCajon"><i class="bi bi-box-arrow-up"></i> Abrir cajón</button>' : ''}<button class="btn-pos" id="btnCerrar" style="background:linear-gradient(135deg,#f43f5e,#e11d48)"><i class="bi bi-lock"></i> Cerrar caja</button>` : '';
    if (r.caja) {
      const s = r.resumen;
      $('#kpis').innerHTML = kp('bi-unlock', 'ic-sky', 'Apertura', money(r.caja.monto_apertura), 'Desde ' + fd(r.caja.fecha_apertura)) + kp('bi-cash-stack', 'ic-indigo', 'Ventas totales', money(s.ventas_total), s.ventas_n + ' ventas' + (s.devoluciones > 0 ? ' · devuelto ' + money(s.devoluciones) : '')) + kp('bi-cash-coin', 'ic-green', 'Ventas en efectivo', money(s.efectivo))
        + kp('bi-arrow-down-circle', 'ic-teal', 'Ingresos', money(s.ingresos)) + kp('bi-arrow-up-circle', 'ic-rose', 'Egresos', money(s.egresos)) + kp('bi-calculator', 'ic-amber', 'Esperado en caja', money(s.esperado), 'Apertura + efectivo + mov.');
      const tot = s.ventas_total || 1;
      $('#metodos').innerHTML = Object.entries(s.por_metodo).map(([m, t]) => { const [c, i] = MET[m] || ['#64748b', 'bi-cash']; return `<div class="mb-3"><div class="d-flex align-items-center gap-2 mb-1"><span class="kpi-ic" style="width:30px;height:30px;font-size:.9rem;background:${c}22;color:${c}"><i class="bi ${i}"></i></span><span class="small fw-semibold">${m.charAt(0) + m.slice(1).toLowerCase()}</span><b class="ms-auto num small">${money(t)}</b></div><div class="bar-s"><i style="width:${t / tot * 100}%;background:${c}"></i></div></div>`; }).join('') || '<div class="empty py-3">Aún no hay ventas en este turno</div>';
      $('#turno').textContent = 'Turno de ' + (r.caja.fecha_apertura || '').slice(11, 16) + ' en adelante';
      $('#movs').innerHTML = r.movimientos.length ? r.movimientos.map((m) => `<tr><td>${m.fecha.slice(11, 16)}</td><td><span class="pill ${m.tipo === 'INGRESO' ? 'ok' : 'bad'}">${m.tipo === 'INGRESO' ? 'Ingreso' : 'Egreso'}</span></td><td>${esc(m.concepto)}</td><td class="r fw-bold num">${m.tipo === 'INGRESO' ? '+' : '−'} ${money(m.monto)}</td></tr>`).join('') : '<tr><td colspan="4"><div class="empty py-3">Sin movimientos manuales</div></td></tr>';
    }
    const h = await api('caja.php?a=historial');
    $('#hist').innerHTML = h.rows.map((c) => { const dif = c.estado === 'CERRADA' ? c.monto_cierre - c.esperado : null;
      return `<tr><td>#${c.id}</td><td>${esc(c.cajero)}</td><td>${fd(c.fecha_apertura)}</td><td>${fd(c.fecha_cierre)}</td><td class="r num">${money(c.ventas)}</td><td class="r num">${money(c.esperado)}</td><td class="r num">${c.monto_cierre !== null ? money(c.monto_cierre) : '—'}</td>
      <td class="r">${dif === null ? '—' : `<span class="pill ${Math.abs(dif) < .01 ? 'ok' : dif < 0 ? 'bad' : 'warn'}">${dif > 0 ? '+' : ''}${fmt(dif)}</span>`}</td><td><span class="pill ${c.estado === 'ABIERTA' ? 'ok' : 'neutral'}">${c.estado === 'ABIERTA' ? 'Abierta' : 'Cerrada'}</span></td><td class="r"><a class="act" target="_blank" title="Reporte de caja" href="${APP.base}/print_caja.php?id=${c.id}"><i class="bi bi-printer"></i></a></td></tr>`; }).join('') || '<tr><td colspan="10"><div class="empty">Sin historial</div></td></tr>';
  }
  $('#btnAbrir').onclick = async () => { try { await api('caja.php', { a: 'abrir', monto: $('#apertura').value }); toast('Caja abierta'); load(); } catch (e) { toast(e.message, 'danger'); } };
  document.addEventListener('click', (e) => {
    const m = e.target.closest('[data-mov]'); if (m) { movTipo = m.dataset.mov; $('#movTitle').textContent = movTipo === 'INGRESO' ? 'Registrar ingreso de efectivo' : 'Registrar egreso de efectivo'; $('#frmMov').reset(); mMov.show(); }
    if (e.target.closest('#btnCajon')) Imprimir.cajon();
    if (e.target.closest('#btnCerrar')) { $('#cEsp').textContent = money(ST.resumen.esperado); $('#cContado').value = ''; $('#cDif').textContent = ''; mCer.show(); }
  });
  $('#frmMov').onsubmit = async (e) => { e.preventDefault(); try { await api('caja.php', { a: 'movimiento', tipo: movTipo, concepto: $('#movCon').value, monto: $('#movMonto').value }); toast('Movimiento registrado'); mMov.hide(); load(); } catch (x) { toast(x.message, 'danger'); } };
  $('#cContado').oninput = () => { const d = (+$('#cContado').value) - ST.resumen.esperado; $('#cDif').innerHTML = $('#cContado').value === '' ? '' : Math.abs(d) < .01 ? '<span class="text-success">✔ La caja cuadra perfectamente</span>' : `<span class="${d < 0 ? 'text-danger' : 'text-warning'}">${d < 0 ? 'Faltante' : 'Sobrante'}: ${money(Math.abs(d))}</span>`; };
  $('#frmCerrar').onsubmit = async (e) => { e.preventDefault();     try { const r = await api('caja.php', { a: 'cerrar', contado: $('#cContado').value, obs: $('#cObs').value }); mCer.hide(); toast(Math.abs(r.diferencia) < .01 ? 'Caja cerrada sin diferencias' : `Caja cerrada. Diferencia: ${money(r.diferencia)}`, Math.abs(r.diferencia) < .01 ? 'success' : 'warning'); Imprimir.cierre(r.id, `${APP.base}/print_caja.php?id=${r.id}&auto=1`); load(); } catch (x) { toast(x.message, 'danger'); } };
  load();
})();
