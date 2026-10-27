(function () {
  const $ = (s) => document.querySelector(s); let page = 1, rows = [], init = false;
  const col = (a) => /ANUL|ELIMIN|RESET/.test(a) ? 'bad' : /LOGIN|CAMBIO/.test(a) ? 'neutral' : /VENTA|COMPRA|DEVOL|TRASP|COTIZ/.test(a) ? 'ok' : /CAJA|AJUSTE/.test(a) ? 'warn' : 'info';
  async function load() {
    const p = new URLSearchParams({ page, q: $('#q').value.trim(), accion: $('#accion').value, usuario: $('#usuario').value, desde: $('#desde').value, hasta: $('#hasta').value });
    try {
      const r = await api('auditoria.php?' + p); rows = r.rows; page = r.page;
      if (!init) { init = true; $('#accion').innerHTML += r.acciones.map((a) => `<option>${esc(a)}</option>`).join(''); $('#usuario').innerHTML += r.usuarios.map((a) => `<option>${esc(a)}</option>`).join(''); }
      $('#tb').innerHTML = rows.length ? rows.map((a) => `<tr><td class="text-nowrap">${a.fecha.slice(8, 10)}/${a.fecha.slice(5, 7)}/${a.fecha.slice(2, 4)} ${a.fecha.slice(11, 19)}</td><td><b>${esc(a.usuario || '—')}</b></td><td><span class="pill ${col(a.accion)}">${esc(a.accion.replace(/_/g, ' '))}</span></td><td style="white-space:normal">${esc(a.detalle || '')}</td><td>${esc(a.sucursal || '—')}</td><td class="text-muted small">${esc(a.ip || '')}</td></tr>`).join('')
        : '<tr><td colspan="6"><div class="empty"><i class="bi bi-shield-check"></i>Sin registros</div></td></tr>';
      $('#pager').innerHTML = pagerHtml(r.page, r.pages, r.total, r.per);
    } catch (e) { toast(e.message, 'danger'); }
  }
  document.addEventListener('click', (e) => { const pg = e.target.closest('#pager [data-p]'); if (pg && !pg.disabled) { page = +pg.dataset.p; load(); } });
  $('#q').addEventListener('input', debounce(() => { page = 1; load(); })); ['accion', 'usuario', 'desde', 'hasta'].forEach((i) => $('#' + i).addEventListener('change', () => { page = 1; load(); }));
  $('#btnCsv').onclick = () => exportCSV('auditoria', ['Fecha', 'Usuario', 'Acción', 'Detalle', 'Sucursal', 'IP'], rows.map((a) => [a.fecha, a.usuario, a.accion, a.detalle, a.sucursal, a.ip]));
  load();
})();
