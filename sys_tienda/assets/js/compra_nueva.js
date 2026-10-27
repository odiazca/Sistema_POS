(function () {
  const $ = (s) => document.querySelector(s); let L = [];
  function render() {
    $('#lines').innerHTML = L.length ? L.map((l, i) => `<tr><td style="white-space:normal"><b>${esc(l.nombre)}</b><div class="small text-muted">${esc(l.codigo)} · Stock actual: ${fmt(l.stock, 0)} ${esc(l.unidad)}</div></td>
      <td><input type="number" min="0.01" step="any" class="form-control form-control-sm text-end" data-i="${i}" data-f="cant" value="${l.cant}"></td>
      <td><input type="number" min="0" step="0.01" class="form-control form-control-sm text-end" data-i="${i}" data-f="costo" value="${l.costo}"></td>
      <td class="r fw-bold num">${money(l.cant * l.costo)}</td><td class="r"><button class="act del" data-rm="${i}"><i class="bi bi-x-lg"></i></button></td></tr>`).join('')
      : '<tr><td colspan="5"><div class="empty"><i class="bi bi-box-arrow-in-down"></i>Busca y agrega productos a la compra</div></td></tr>';
    $('#nItems').textContent = L.length; $('#total').textContent = money(L.reduce((a, l) => a + l.cant * l.costo, 0));
  }
  function add(p) { const x = L.find((l) => l.id === p.id); if (x) x.cant++; else L.push({ id: p.id, codigo: p.codigo, nombre: p.nombre, unidad: p.unidad, stock: p.stock, cant: 1, costo: +p.precio_compra }); render(); }
  $('#lines').addEventListener('change', (e) => { const t = e.target; if (t.dataset.f) { L[+t.dataset.i][t.dataset.f] = Math.max(0, parseFloat(t.value) || 0); render(); } });
  $('#lines').addEventListener('click', (e) => { const b = e.target.closest('[data-rm]'); if (b) { L.splice(+b.dataset.rm, 1); render(); } });
  let found = [];
  $('#pq').addEventListener('input', debounce(async () => {
    const v = $('#pq').value.trim(); if (!v) { $('#pres').style.display = 'none'; return; }
    found = (await api('compras.php?a=productos&q=' + encodeURIComponent(v))).rows;
    $('#pres').innerHTML = found.map((p) => `<div class="cl-item" data-id="${p.id}"><div><b class="small">${esc(p.nombre)}</b><div class="text-muted" style="font-size:.72rem">${esc(p.codigo)} · stock ${fmt(p.stock, 0)}</div></div><b class="small">${money(p.precio_compra)}</b></div>`).join('') || '<div class="empty py-3">Sin resultados</div>';
    $('#pres').style.display = 'block';
  }));
  $('#pq').addEventListener('keydown', (e) => { if (e.key === 'Enter' && found.length) { e.preventDefault(); add(found[0]); $('#pq').value = ''; $('#pres').style.display = 'none'; } });
  $('#pres').addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (d) { add(found.find((p) => p.id === +d.dataset.id)); $('#pq').value = ''; $('#pres').style.display = 'none'; $('#pq').focus(); } });
  $('#btnSave').onclick = async () => {
    if (!$('#prov').value) return toast('Selecciona un proveedor', 'warning'); if (!L.length) return toast('Agrega productos', 'warning');
    try { const r = await api('compras.php', { a: 'save', proveedor_id: $('#prov').value, comprobante: $('#comp').value, fecha: $('#fecha').value, items: L.map((l) => ({ id: l.id, cant: l.cant, costo: l.costo })) });
      toast(`Compra registrada por ${money(r.total)}. Stock actualizado.`); L = []; $('#comp').value = ''; render(); } catch (e) { toast(e.message, 'danger'); }
  };
  const pre = new URLSearchParams(location.search).get('p');
  if (pre) api('compras.php?a=productos&q=' + encodeURIComponent(pre)).then((r) => { const p = r.rows.find((x) => x.codigo === pre) || r.rows[0]; if (p) add(p); });
  render();
})();
