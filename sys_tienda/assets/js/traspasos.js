(function () {
  const $ = (s) => document.querySelector(s); let L = []; const md = new bootstrap.Modal($('#mdlT')), mdD = new bootstrap.Modal($('#mdlD'));
  const fd = (d) => d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(2, 4) + ' ' + d.slice(11, 16);
  async function load() {
    const r = await api('traspasos.php?a=list');
    $('#tb').innerHTML = r.rows.length ? r.rows.map((t) => `<tr><td>#${t.id}</td><td>${fd(t.fecha)}</td><td><b>${esc(t.origen)}</b></td><td><i class="bi bi-arrow-right text-muted"></i></td><td><b>${esc(t.destino)}</b></td><td class="r">${t.items}</td><td class="r">${fmt(t.unidades, 0)}</td><td>${esc(t.usuario)}</td><td class="r"><button class="act" data-id="${t.id}"><i class="bi bi-eye"></i></button></td></tr>`).join('')
      : '<tr><td colspan="9"><div class="empty"><i class="bi bi-arrow-left-right"></i>Aún no hay traspasos</div></td></tr>';
  }
  function render() {
    $('#lines').innerHTML = L.length ? L.map((l, i) => `<tr><td style="white-space:normal"><b>${esc(l.nombre)}</b><div class="small text-muted">${esc(l.codigo)} · disponible ${fmt(l.stock, 0)} ${esc(l.unidad)}</div></td>
      <td><input type="number" min="0.01" max="${l.stock}" step="any" class="form-control form-control-sm text-end" data-i="${i}" value="${l.cant}"></td><td class="r"><button class="act del" data-rm="${i}"><i class="bi bi-x-lg"></i></button></td></tr>`).join('')
      : '<tr><td colspan="3"><div class="empty py-3"><i class="bi bi-box-seam"></i>Busca y agrega productos</div></td></tr>';
  }
  productPicker($('#pq'), $('#pres'), 'traspasos.php?a=productos', (p) => { const x = L.find((l) => l.id === p.id); if (x) x.cant = Math.min(x.cant + 1, p.stock); else L.push({ ...p, cant: 1 }); render(); }, (p) => 'Stock ' + fmt(p.stock, 0));
  $('#lines').addEventListener('change', (e) => { const t = e.target; if (t.dataset.i) { const l = L[+t.dataset.i]; l.cant = Math.min(Math.max(0, parseFloat(t.value) || 0), l.stock); render(); } });
  $('#lines').addEventListener('click', (e) => { const b = e.target.closest('[data-rm]'); if (b) { L.splice(+b.dataset.rm, 1); render(); } });
  $('#btnNew').onclick = async () => {
    try { const r = await api('traspasos.php?a=destinos'); if (!r.origen) return toast('Elige primero una sucursal de origen en el selector superior', 'warning');
      $('#dest').innerHTML = r.rows.map((s) => `<option value="${s.id}">${esc(s.nombre)}</option>`).join(''); if (!r.rows.length) return toast('Necesitas al menos otra sucursal activa', 'warning');
      L = []; $('#nota').value = ''; render(); md.show(); } catch (e) { toast(e.message, 'danger'); }
  };
  $('#btnSave').onclick = async () => {
    if (!L.length || L.some((l) => !(l.cant > 0))) return toast('Agrega productos con cantidad mayor a 0', 'warning');
    try { await api('traspasos.php', { a: 'save', destino_id: $('#dest').value, nota: $('#nota').value, items: L.map((l) => ({ id: l.id, cant: l.cant })) }); toast('Traspaso registrado: stock actualizado en ambas sucursales'); md.hide(); load(); } catch (e) { toast(e.message, 'danger'); }
  };
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('#tb [data-id]'); if (!b) return;
    const { traspaso: t } = await api('traspasos.php?a=get&id=' + b.dataset.id);
    $('#dBody').innerHTML = `<h5 class="fw-bold">Traspaso #${t.id}</h5><div class="small text-muted mb-3">${fd(t.fecha)} · ${esc(t.origen)} → ${esc(t.destino)}${t.nota ? ' · ' + esc(t.nota) : ''}</div>
      <table class="tbl"><thead><tr><th>Producto</th><th class="r">Cantidad</th></tr></thead><tbody>${t.items.map((i) => `<tr><td style="white-space:normal">${esc(i.nombre)}</td><td class="r">${fmt(i.cantidad, 0)} ${esc(i.unidad)}</td></tr>`).join('')}</tbody></table>
      <div class="d-flex gap-2 mt-3"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cerrar</button><button class="btn-soft flex-fill justify-content-center" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div>`;
    mdD.show();
  });
  load();
})();
