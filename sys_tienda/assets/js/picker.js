/* Buscador de productos con lista desplegable (traspasos, cotizaciones…) */
window.productPicker = function (input, drop, endpoint, onPick, sub) {
  let found = [];
  const hide = () => (drop.style.display = 'none');
  input.addEventListener('input', debounce(async () => {
    const v = input.value.trim(); if (!v) return hide();
    found = (await api(endpoint + (endpoint.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(v))).rows;
    drop.innerHTML = found.map((p) => `<div class="cl-item" data-id="${p.id}"><div><b class="small">${esc(p.nombre)}</b><div class="text-muted" style="font-size:.72rem">${esc(p.codigo)} · stock ${fmt(p.stock, 0)} ${esc(p.unidad)}</div></div>${sub ? `<b class="small">${sub(p)}</b>` : ''}</div>`).join('') || '<div class="empty py-3">Sin resultados</div>';
    drop.style.display = 'block';
  }));
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && found.length) { e.preventDefault(); onPick(found[0]); input.value = ''; hide(); } });
  drop.addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (d) { onPick(found.find((p) => p.id === +d.dataset.id)); input.value = ''; hide(); input.focus(); } });
  document.addEventListener('click', (e) => { if (!drop.contains(e.target) && e.target !== input) hide(); });
};
