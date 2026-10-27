(function () {
  const $ = (s) => document.querySelector(s); let page = 1, rows = [], prod = 0, aProd = null, modo = 'CONTEO'; const mA = new bootstrap.Modal($('#mdlA'));
  const T = { ENTRADA: ['ok', '▲ Entrada'], SALIDA: ['bad', '▼ Salida'], AJUSTE: ['warn', '⇄ Ajuste'] };
  async function load() {
    const p = new URLSearchParams({ a: 'kardex', page, producto: prod || '', tipo: $('#tipo').value, desde: $('#desde').value, hasta: $('#hasta').value });
    try { const r = await api('inventario.php?' + p); rows = r.rows; page = r.page;
      $('#tb').innerHTML = rows.length ? rows.map((m) => `<tr><td>${m.fecha.slice(8, 10)}/${m.fecha.slice(5, 7)} ${m.fecha.slice(11, 16)}</td><td><b>${esc(m.nombre)}</b><div class="small text-muted">${esc(m.codigo)}</div></td><td><span class="pill ${T[m.tipo][0]}">${T[m.tipo][1]}</span></td>
        <td class="r fw-bold num">${m.tipo === 'SALIDA' ? '−' : m.cantidad > 0 && m.tipo === 'AJUSTE' ? '+' : m.tipo === 'ENTRADA' ? '+' : ''}${fmt(Math.abs(m.cantidad), 2).replace(/\.00$/, '')}</td><td class="r num">${fmt(m.stock_resultante, 2).replace(/\.00$/, '')} ${esc(m.unidad)}</td><td>${esc(m.motivo || '')}</td><td class="text-muted">${esc(m.usuario || '')}</td></tr>`).join('')
        : '<tr><td colspan="7"><div class="empty"><i class="bi bi-journal-text"></i>Sin movimientos</div></td></tr>';
      $('#pager').innerHTML = pagerHtml(r.page, r.pages, r.total, r.per); } catch (e) { toast(e.message, 'danger'); }
  }
  function finder(inp, box, cb) {
    let f = []; inp.addEventListener('input', debounce(async () => { const v = inp.value.trim(); if (!v) { box.style.display = 'none'; return; }
      f = (await api('inventario.php?a=productos&q=' + encodeURIComponent(v))).rows;
      box.innerHTML = f.map((p) => `<div class="cl-item" data-id="${p.id}"><div><b class="small">${esc(p.nombre)}</b><div class="text-muted" style="font-size:.72rem">${esc(p.codigo)} · stock ${fmt(p.stock, 0)} ${esc(p.unidad)}</div></div></div>`).join('') || '<div class="empty py-2">Sin resultados</div>'; box.style.display = 'block'; }));
    box.addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (d) { cb(f.find((p) => p.id === +d.dataset.id)); box.style.display = 'none'; } });
  }
  finder($('#pq'), $('#pres'), (p) => { prod = p.id; $('#pq').value = ''; $('#pchip').style.display = ''; $('#pchip').innerHTML = `${esc(p.nombre)} <a href="#" id="clr" class="ms-1">✕</a>`; page = 1; load(); });
  document.addEventListener('click', (e) => { if (e.target.id === 'clr') { e.preventDefault(); prod = 0; $('#pchip').style.display = 'none'; load(); } const pg = e.target.closest('#pager [data-p]'); if (pg && !pg.disabled) { page = +pg.dataset.p; load(); } });
  finder($('#aq'), $('#ares'), (p) => { aProd = p; $('#aq').value = p.nombre; $('#aInfo').style.display = ''; $('#aInfo').innerHTML = `<small>Stock actual en sistema</small><b>${fmt(p.stock, 2).replace(/\.00$/, '')} ${esc(p.unidad)}</b>`; $('#aCant').focus(); });
  $('#aModo').addEventListener('click', (e) => { const b = e.target.closest('button'); if (!b) return; modo = b.dataset.m; document.querySelectorAll('#aModo button').forEach((x) => x.classList.toggle('on', x === b)); $('#aLbl').textContent = { CONTEO: 'Stock real contado', ENTRADA: 'Cantidad a ingresar', SALIDA: 'Cantidad a retirar' }[modo]; });
  $('#btnAj').onclick = () => { $('#frmA').reset(); aProd = null; $('#aInfo').style.display = 'none'; mA.show(); setTimeout(() => $('#aq').focus(), 400); };
  $('#frmA').onsubmit = async (e) => { e.preventDefault(); if (!aProd) return toast('Selecciona un producto', 'warning');
    try { await api('inventario.php', { a: 'ajuste', producto_id: aProd.id, modo, cantidad: $('#aCant').value, motivo: $('#aMot').value }); toast('Ajuste aplicado'); mA.hide(); load(); } catch (x) { toast(x.message, 'danger'); } };
  ['tipo', 'desde', 'hasta'].forEach((i) => $('#' + i).addEventListener('change', () => { page = 1; load(); }));
  $('#btnCsv').onclick = () => exportCSV('kardex', ['Fecha', 'Código', 'Producto', 'Tipo', 'Cantidad', 'Stock', 'Motivo'], rows.map((m) => [m.fecha, m.codigo, m.nombre, m.tipo, m.cantidad, m.stock_resultante, m.motivo]));
  load();
})();
