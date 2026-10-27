(function () {
  const $ = (s) => document.querySelector(s);
  const COLS = ['codigo', 'codigo_barras', 'nombre', 'categoria', 'marca', 'unidad', 'precio_compra', 'precio_venta', 'precio_mayor', 'cantidad_mayor', 'stock', 'stock_minimo', 'fecha_vencimiento'];
  let ROWS = [];
  const csvCell = (v) => { v = v == null ? '' : String(v); return /[",;\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
  function download(name, rows) { const blob = new Blob(['﻿' + rows.map((r) => r.map(csvCell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000); }
  $('#btnTpl').onclick = () => download('plantilla_productos.csv', [COLS, ['P9001', '7751234567890', 'Arroz Extra 5kg', 'Abarrotes', 'Costeño', 'BLS', 19.5, 23.9, 22.5, 6, 40, 10, ''], ['P9002', '', 'Leche Evaporada 400g', 'Lácteos', 'Gloria', 'LAT', 3.4, 4.5, '', '', 100, 24, '2027-03-31']]);
  $('#btnExp').onclick = async () => { try { const r = await api('importar.php?a=export'); download('productos_' + new Date().toISOString().slice(0, 10) + '.csv', [COLS, ...r.rows.map((x) => COLS.map((c) => x[c]))]); toast('Catálogo exportado (stock de ' + r.sucursal + ')'); } catch (e) { toast(e.message, 'danger'); } };

  // Parser CSV (comillas, coma o punto y coma)
  function parse(text) {
    text = text.replace(/^﻿/, ''); const first = text.split(/\r?\n/, 1)[0], delim = (first.match(/;/g) || []).length > (first.match(/,/g) || []).length ? ';' : ',';
    const out = []; let row = [], cell = '', q = false;
    for (let i = 0; i < text.length; i++) { const c = text[i];
      if (q) { if (c === '"') { if (text[i + 1] === '"') { cell += '"'; i++; } else q = false; } else cell += c; }
      else if (c === '"') q = true; else if (c === delim) { row.push(cell); cell = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && text[i + 1] === '\n') i++; row.push(cell); cell = ''; if (row.some((x) => x.trim() !== '')) out.push(row); row = []; }
      else cell += c; }
    row.push(cell); if (row.some((x) => x.trim() !== '')) out.push(row); return out;
  }
  const norm = (h) => h.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z_]/g, '').replace(/^codigo_?de_?barras$/, 'codigo_barras');
  async function handle(file) {
    if (!file) return; const t = await file.text(), data = parse(t); if (data.length < 2) return toast('El archivo está vacío', 'warning');
    const head = data[0].map(norm), miss = ['codigo', 'nombre', 'precio_venta'].filter((c) => !head.includes(c));
    if (miss.length) return toast('Faltan columnas obligatorias: ' + miss.join(', '), 'danger');
    ROWS = data.slice(1).map((r) => { const o = {}; head.forEach((h, i) => { if (COLS.includes(h)) o[h] = (r[i] ?? '').trim(); }); return o; });
    $('#prev').style.display = 'block'; $('#pErr').style.display = 'none';
    $('#pCount').textContent = ROWS.length + ' filas'; $('#pInfo').textContent = '· ' + file.name;
    $('#pHead').innerHTML = '<tr><th>#</th>' + COLS.filter((c) => head.includes(c)).map((c) => `<th>${c}</th>`).join('') + '</tr>';
    $('#pBody').innerHTML = ROWS.slice(0, 50).map((r, n) => `<tr><td class="text-muted">${n + 2}</td>${COLS.filter((c) => head.includes(c)).map((c) => `<td>${esc(r[c] ?? '')}</td>`).join('')}</tr>`).join('') + (ROWS.length > 50 ? `<tr><td colspan="20" class="text-muted small">… y ${ROWS.length - 50} filas más</td></tr>` : '');
  }
  $('#file').addEventListener('change', (e) => handle(e.target.files[0]));
  const drop = $('#drop'); ['dragover', 'dragenter'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('on'); })); ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('on'); }));
  drop.addEventListener('drop', (e) => handle(e.dataTransfer.files[0]));
  $('#btnGo').onclick = async () => {
    if (!ROWS.length) return; $('#btnGo').disabled = true; $('#pErr').style.display = 'none';
    try { const r = await api('importar.php', { a: 'importar', rows: ROWS }); toast(`Importación lista: ${r.nuevos} nuevos y ${r.actualizados} actualizados`); ROWS = []; $('#prev').style.display = 'none'; $('#file').value = ''; }
    catch (e) { const el = $('#pErr'); el.style.display = 'block'; el.innerHTML = '<b>' + esc(e.message) + '</b>' + (e.data?.errores ? '<ul class="mb-0 mt-1">' + e.data.errores.map((x) => `<li>${esc(x)}</li>`).join('') + '</ul>' + (e.data.total_errores > e.data.errores.length ? `<div>… y ${e.data.total_errores - e.data.errores.length} más</div>` : '') : ''); }
    $('#btnGo').disabled = false;
  };
})();
