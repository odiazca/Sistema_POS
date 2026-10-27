/* Listas desplegables con búsqueda avanzada (Tom Select).
   Se aplica automáticamente a todo <select> con 6 o más opciones, también a los que se llenan después por JS.
   Excluir uno: data-nosearch · Forzar uno pequeño: data-search */
(function () {
  if (!window.TomSelect) return;
  const MIN = 6, norm = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const protoValue = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

  function elegible(sel) {
    if (sel.tomselect || sel.hasAttribute('data-nosearch') || sel.multiple || sel.closest('.ts-wrapper')) return false;
    return sel.hasAttribute('data-search') || sel.options.length >= MIN;
  }
  function mejorar(sel) {
    if (!elegible(sel)) return;
    const ph = sel.options[0] && sel.options[0].value === '' ? sel.options[0].textContent.trim() : 'Selecciona…';
    const ts = new TomSelect(sel, {
      allowEmptyOption: true, maxOptions: 1000, create: false, openOnFocus: true, selectOnTab: true,
      plugins: ['dropdown_input'], placeholder: ph, searchField: ['text'],
      score(search) { const s = norm(search); return (item) => { const t = norm(item.text || ''); const i = t.indexOf(s); return i < 0 ? 0 : 1 + (i === 0 ? 1 : 0) + 1 / (1 + t.length); }; },
      render: { no_results: () => '<div class="no-results">Sin resultados</div>' },
    });
    // Marca los cambios que hace la propia librería sobre el <select> para no reaccionar a ellos
    let busy = false; const uoi = ts.updateOriginalInput.bind(ts);
    ts.updateOriginalInput = function (...a) { busy = true; queueMicrotask(() => { busy = false; }); return uoi(...a); };
    // El control de búsqueda dentro del desplegable
    const inp = ts.dropdown_content && ts.dropdown.querySelector('.dropdown-input'); if (inp) inp.setAttribute('placeholder', 'Buscar…');
    // Asignaciones por código (sel.value = x) mantienen sincronizada la vista
    Object.defineProperty(sel, 'value', { configurable: true, get() { return protoValue.get.call(this); }, set(v) { protoValue.set.call(this, v); if (!busy) ts.setValue(protoValue.get.call(this), true); } });
    // Si JS reemplaza las opciones, se vuelve a leer
    new MutationObserver(() => { if (!busy) ts.sync(false); }).observe(sel, { childList: true });
    if (sel.disabled) ts.disable();
  }
  const barrer = (root) => (root.querySelectorAll ? root.querySelectorAll('select') : []).forEach(mejorar);
  barrer(document);
  // Selects que se crean luego o que reciben sus opciones después
  const pendientes = new WeakSet();
  const revisar = (sel) => { if (sel.tomselect || pendientes.has(sel)) return; pendientes.add(sel); setTimeout(() => { pendientes.delete(sel); mejorar(sel); }, 30); };
  new MutationObserver((muts) => {
    for (const m of muts) {
      m.addedNodes.forEach((n) => { if (n.nodeType !== 1) return; if (n.tagName === 'SELECT') revisar(n); else if (n.querySelectorAll) n.querySelectorAll('select').forEach(revisar); if (n.tagName === 'OPTION' && n.parentElement) revisar(n.parentElement); });
      if (m.target && m.target.tagName === 'SELECT') revisar(m.target);
    }
  }).observe(document.body, { childList: true, subtree: true });
})();
