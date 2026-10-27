(function () {
  const $ = (s) => document.querySelector(s); let rows = []; const bajo = window.ALERTA === 'bajo';
  const st = (ic, c, l, v) => `<div class="col-md-4"><div class="cardx d-flex align-items-center gap-3"><div class="kpi-ic ${c}"><i class="bi ${ic}"></i></div><div><div class="muted small">${l}</div><div class="fs-4 fw-800 num">${v}</div></div></div></div>`;
  async function load() {
    const r = await api(`inventario.php?a=alertas&tipo=${window.ALERTA}&dias=${$('#dias')?.value || 30}`); rows = r.rows;
    if (bajo) {
      $('#stats').innerHTML = st('bi-exclamation-triangle', 'ic-amber', 'Productos con stock bajo', rows.length) + st('bi-x-octagon', 'ic-rose', 'Agotados', rows.filter((x) => x.stock <= 0).length) + st('bi-cash-coin', 'ic-indigo', 'Inversión sugerida', money(rows.reduce((a, x) => a + x.sugerido * x.precio_compra, 0)));
      $('#tb').innerHTML = rows.map((x) => `<tr><td><b>${esc(x.nombre)}</b><div class="small text-muted">${esc(x.codigo)}</div></td><td><span class="pill" style="background:${x.color}1f;color:${x.color}">${esc(x.categoria)}</span></td>
        <td class="r"><span class="pill ${x.stock <= 0 ? 'bad' : 'warn'}">${fmt(x.stock, 0)} ${esc(x.unidad)}</span></td><td class="r">${fmt(x.stock_minimo, 0)}</td><td class="r fw-bold">${fmt(x.sugerido, 0)}</td><td class="r num">${money(x.sugerido * x.precio_compra)}</td>
        <td class="r">${APP.rol !== 2 ? `<a class="btn-soft" href="${APP.base}/modulo.php?m=compras-nueva&p=${encodeURIComponent(x.codigo)}"><i class="bi bi-bag-plus"></i> Comprar</a>` : ''}</td></tr>`).join('') || '<tr><td colspan="7"><div class="empty"><i class="bi bi-check2-circle"></i>¡Todo el inventario está sobre el mínimo!</div></td></tr>';
    } else {
      const venc = rows.filter((x) => x.dias < 0);
      $('#stats').innerHTML = st('bi-hourglass-split', 'ic-amber', 'Por vencer en el período', rows.length) + st('bi-x-circle', 'ic-rose', 'Ya vencidos', venc.length) + st('bi-cash-coin', 'ic-indigo', 'Valor en riesgo (costo)', money(rows.reduce((a, x) => a + x.stock * x.precio_compra, 0)));
      $('#tb').innerHTML = rows.map((x) => `<tr><td><b>${esc(x.nombre)}</b><div class="small text-muted">${esc(x.codigo)}</div></td><td><span class="pill" style="background:${x.color}1f;color:${x.color}">${esc(x.categoria)}</span></td><td>${x.fecha_vencimiento.split('-').reverse().join('/')}</td>
        <td><span class="pill ${x.dias < 0 ? 'bad' : x.dias <= 10 ? 'bad' : 'warn'}">${x.dias < 0 ? 'Vencido hace ' + (-x.dias) + ' d' : x.dias === 0 ? 'Vence hoy' : 'En ' + x.dias + ' días'}</span></td><td class="r">${fmt(x.stock, 0)} ${esc(x.unidad)}</td><td class="r num">${money(x.stock * x.precio_compra)}</td></tr>`).join('') || '<tr><td colspan="6"><div class="empty"><i class="bi bi-check2-circle"></i>Sin productos por vencer en este período</div></td></tr>';
    }
  }
  $('#dias')?.addEventListener('change', load);
  $('#btnCsv').onclick = () => exportCSV(bajo ? 'stock_bajo' : 'por_vencer', ['Código', 'Producto', 'Stock', bajo ? 'Mínimo' : 'Vence'], rows.map((x) => [x.codigo, x.nombre, x.stock, bajo ? x.stock_minimo : x.fecha_vencimiento]));
  load();
})();
