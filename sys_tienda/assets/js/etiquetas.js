(function () {
  const $ = (s) => document.querySelector(s); let L = [];
  const key = 'st-etq'; try { L = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { L = []; }
  const save = () => { try { localStorage.setItem(key, JSON.stringify(L)); } catch (e) {} };
  productPicker($('#pq'), $('#pres'), 'importar.php?a=buscar', (p) => { const x = L.find((l) => l.id === p.id); if (x) x.n++; else L.push({ id: p.id, codigo: p.codigo, barras: p.codigo_barras, nombre: p.nombre, precio: +p.precio_venta, n: 1 }); render(); }, (p) => money(p.precio_venta));
  function render() {
    save();
    $('#list').innerHTML = L.length ? L.map((l, i) => `<div class="d-flex align-items-center gap-2 mb-2"><div class="flex-grow-1" style="min-width:0"><div class="small fw-semibold text-truncate">${esc(l.nombre)}</div><div class="text-muted" style="font-size:.7rem">${esc(l.barras || l.codigo)}</div></div>
      <input type="number" min="1" max="200" class="form-control form-control-sm text-end" style="width:70px" value="${l.n}" data-i="${i}"><button class="act del" data-rm="${i}"><i class="bi bi-x-lg"></i></button></div>`).join('') : '<div class="empty py-3"><i class="bi bi-upc"></i>Agrega productos y la cantidad de etiquetas de cada uno</div>';
    const size = $('#size').value, show = $('#show').value; let n = 0, html = '';
    L.forEach((l) => { for (let k = 0; k < l.n; k++) { n++; html += `<div class="label ${size}"><svg class="bc" data-v="${esc(l.barras || l.codigo)}"></svg>${show !== 'none' ? `<div class="ln">${esc(l.nombre)}</div>` : ''}${show === 'all' ? `<div class="lp">${money(l.precio)}</div>` : ''}</div>`; } });
    $('#sheet').innerHTML = html || '<div class="empty"><i class="bi bi-upc-scan"></i>Aquí verás tus etiquetas</div>'; $('#nEtq').textContent = n + ' etiqueta(s)';
    document.querySelectorAll('svg.bc').forEach((el) => { const v = el.dataset.v; try { JsBarcode(el, v, { format: /^\d{13}$/.test(v) ? 'EAN13' : 'CODE128', height: size === 's' ? 28 : 36, width: size === 'l' ? 2 : 1.4, fontSize: 11, margin: 0, displayValue: true }); } catch (e) { el.outerHTML = '<div class="text-danger small">Código inválido</div>'; } });
  }
  $('#list').addEventListener('change', (e) => { const i = e.target.dataset.i; if (i !== undefined) { L[+i].n = Math.min(200, Math.max(1, parseInt(e.target.value) || 1)); render(); } });
  $('#list').addEventListener('click', (e) => { const b = e.target.closest('[data-rm]'); if (b) { L.splice(+b.dataset.rm, 1); render(); } });
  $('#size').addEventListener('change', render); $('#show').addEventListener('change', render);
  $('#btnPrint').onclick = () => { if (!L.length) return toast('Agrega productos para imprimir', 'warning'); document.body.classList.add('etq-print'); window.print(); };
  window.addEventListener('afterprint', () => document.body.classList.remove('etq-print'));
  render();
})();
