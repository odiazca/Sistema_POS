/* Punto de Venta */
(function () {
  const $ = (s) => document.querySelector(s);
  const IGV = (window.POS_IGV || 18) / 100;
  const CLI0 = { id: 1, nombre: 'Cliente Varios', documento: '', tipo_doc: '', saldo_deuda: 0, limite_credito: 0, puntos: 0, cumple: false, telefono: '' };
  const S = { cart: [], cli: { ...CLI0 }, tipo: 'TICKET', metodo: 'EFECTIVO', cat: 0, busy: false, promos: [], fid: { cada: 10, valor: 0.1, cumple: 5 }, canje: 0, cot: 0, last: null };
  const r2 = (x) => Math.round((x + Number.EPSILON) * 100) / 100;
  const mCli = new bootstrap.Modal($('#mdlCli')), mHold = new bootstrap.Modal($('#mdlHold')), mOk = new bootstrap.Modal($('#mdlOk'));
  let lastId = 0, prods = [];

  // ---------- Motor de precios (espejo de includes/pricing.php) ----------
  function calcLinea(i) {
    let precio = +i.precio_venta;
    if (+i.cantidad_mayor > 0 && +i.precio_mayor > 0 && i.cant >= +i.cantidad_mayor) precio = +i.precio_mayor;
    const base = r2(i.cant * precio); let best = 0, name = null;
    for (const pr of S.promos) {
      const ok = pr.alcance === 'TODO' || (pr.alcance === 'PRODUCTO' && +pr.producto_id === +i.id) || (pr.alcance === 'CATEGORIA' && +pr.categoria_id === +i.categoria_id);
      if (!ok) continue; let d = 0;
      if (pr.tipo === 'PORCENTAJE') d = base * pr.valor / 100;
      else if (pr.tipo === 'MONTO') d = Math.min(base, pr.valor * i.cant);
      else if (pr.tipo === 'NXM' && pr.lleva > pr.paga && pr.lleva > 0) d = Math.floor(i.cant / pr.lleva) * (pr.lleva - pr.paga) * precio;
      d = r2(Math.min(d, base)); if (d > best) { best = d; name = pr.nombre; }
    }
    return { precio, base, desc: best, promo: name, sub: r2(base - best), mayor: precio !== +i.precio_venta && precio === +i.precio_mayor };
  }
  function calc() {
    let bruto = 0, dPromo = 0;
    S.cart.forEach((i) => { i.c = calcLinea(i); bruto += i.c.base; dPromo += i.c.desc; });
    let neto = r2(bruto - dPromo); const manual = Math.min(r2(+$('#desc').value || 0), neto); neto = r2(neto - manual);
    const dCumple = S.cli.id !== 1 && S.cli.cumple ? r2(neto * S.fid.cumple / 100) : 0; neto = r2(neto - dCumple);
    let canje = S.cli.id !== 1 ? Math.min(Math.max(0, Math.floor(S.canje)), S.cli.puntos || 0) : 0, dPuntos = r2(canje * S.fid.valor);
    if (dPuntos > neto) { canje = Math.floor(neto / S.fid.valor); dPuntos = r2(canje * S.fid.valor); }
    neto = r2(neto - dPuntos);
    return { bruto, dPromo, manual, dCumple, dPuntos, canje, total: Math.max(0, neto) };
  }

  // ---------- Caja ----------
  async function checkCaja() {
    const r = await api('caja.php?a=estado');
    $('#lock').style.display = r.caja ? 'none' : 'grid';
    if (!r.caja) setTimeout(() => $('#apertura').select(), 200);
  }
  $('#btnAbrir').onclick = async () => {
    try { await api('caja.php', { a: 'abrir', monto: $('#apertura').value }); toast('Caja abierta'); checkCaja(); $('#pSearch').focus(); } catch (e) { toast(e.message, 'danger'); }
  };

  // ---------- Catálogo ----------
  async function loadCats() {
    const r = await api('pos.php?a=categorias');
    $('#chips').innerHTML = `<button class="chip on" data-c="0"><i class="bi bi-grid"></i>Todos</button>` + r.rows.map((c) => `<button class="chip" data-c="${c.id}"><i class="bi ${esc(c.icono)}"></i>${esc(c.nombre)}</button>`).join('');
  }
  async function loadProds() {
    const r = await api(`pos.php?a=productos&q=${encodeURIComponent($('#pSearch').value.trim())}&cat=${S.cat}`); prods = r.rows; S.promos = r.promos || [];
    $('#grid').innerHTML = prods.length ? prods.map((p) => {
      const enCarrito = (S.cart.find((x) => x.id === p.id) || {}).cant || 0, disp = p.stock - enCarrito;
      return `<button class="pcard ${disp <= 0 ? 'out' : ''}" data-id="${p.id}">
        <div class="pi" style="background:${p.color}22;color:${p.color}">${p.imagen ? `<img src="${APP.base}/assets/uploads/productos/${esc(p.imagen)}" alt="">` : `<i class="bi ${esc(p.icono)}"></i>`}</div>
        <div class="pn">${esc(p.nombre)}</div><div class="pp">${money(p.precio_venta)}</div>${S.promos.some((q) => q.alcance === 'TODO' || (q.alcance === 'PRODUCTO' && +q.producto_id === +p.id) || (q.alcance === 'CATEGORIA' && +q.categoria_id === +p.categoria_id)) ? '<span class="promo-tag"><i class="bi bi-percent"></i>Promo</span>' : ''}${+p.cantidad_mayor > 0 && +p.precio_mayor > 0 ? `<div class="pm">Desde ${fmt(p.cantidad_mayor, 0)}: ${money(p.precio_mayor)}</div>` : ''}
        <div class="ps ${p.stock <= p.stock_minimo ? 'low' : ''}">${disp <= 0 ? 'Sin stock' : 'Stock: ' + fmt(disp, disp % 1 ? 2 : 0) + ' ' + esc(p.unidad)}</div></button>`;
    }).join('') : '<div class="empty" style="grid-column:1/-1"><i class="bi bi-search"></i>Sin productos para esta búsqueda</div>';
  }
  $('#chips').addEventListener('click', (e) => { const b = e.target.closest('.chip'); if (!b) return; document.querySelectorAll('.chip').forEach((x) => x.classList.remove('on')); b.classList.add('on'); S.cat = +b.dataset.c; loadProds(); });
  $('#grid').addEventListener('click', (e) => { const b = e.target.closest('.pcard'); if (!b) return; const p = prods.find((x) => x.id === +b.dataset.id); b.classList.add('pop'); setTimeout(() => b.classList.remove('pop'), 250); add(p); });
  $('#pSearch').addEventListener('input', debounce(loadProds, 250));
  $('#pSearch').addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return; e.preventDefault();
    const v = e.target.value.trim(); if (!v) return;
    const r = await api(`pos.php?a=productos&q=${encodeURIComponent(v)}&cat=0`);
    const exact = r.rows.find((p) => p.codigo_barras === v || p.codigo.toLowerCase() === v.toLowerCase()) || (r.rows.length === 1 ? r.rows[0] : null);
    if (exact) { add(exact); e.target.value = ''; loadProds(); } else toast('Producto no encontrado', 'warning');
  });

  // ---------- Carrito ----------
  function add(p, n = 1) {
    let it = S.cart.find((x) => x.id === p.id);
    const nuevo = (it ? it.cant : 0) + n;
    if (nuevo > p.stock) return toast(`Stock máximo disponible: ${fmt(p.stock, p.stock % 1 ? 2 : 0)}`, 'warning');
    if (it) it.cant = nuevo; else S.cart.push({ id: p.id, nombre: p.nombre, unidad: p.unidad, precio_venta: +p.precio_venta, precio_mayor: +p.precio_mayor || 0, cantidad_mayor: +p.cantidad_mayor || 0, categoria_id: p.categoria_id, cant: n, stock: +p.stock });
    render(); const c = $('#cart'); c.scrollTop = c.scrollHeight;
  }
  const total = () => calc().total;

  function render() {
    const T = calc(), t = T.total, sub = t / (1 + IGV);
    $('#cartCount').textContent = S.cart.length;
    $('#cart').innerHTML = S.cart.length ? S.cart.map((i) => `<div class="citem" data-id="${i.id}">
      <div><div class="n">${esc(i.nombre)}</div><div class="u">${money(i.c.precio)} / ${esc(i.unidad)}${i.c.mayor ? ' <span class="pill info">Mayorista</span>' : ''}${i.c.promo ? ` <span class="pill ok"><i class="bi bi-percent"></i> ${esc(i.c.promo)}</span>` : ''}</div></div>
      <div class="text-end fw-bold num">${i.c.desc ? `<s class="text-muted fw-normal" style="font-size:.72rem">${money(i.c.base)}</s><br>` : ''}${money(i.c.sub)}</div>
      <div class="qty"><button data-q="-">−</button><input type="number" step="any" min="0" value="${i.cant}" data-in><button data-q="+">+</button></div>
      <div class="text-end"><button class="act del" data-rm title="Quitar"><i class="bi bi-x-lg"></i></button></div></div>`).join('')
      : '<div class="cart-empty"><div><i class="bi bi-basket3"></i>Agrega productos para comenzar</div></div>';
    const row = (l, v, c) => `<div class="d-flex justify-content-between ${c || 'text-success'}"><span>${l}</span><b class="num">− ${money(v)}</b></div>`;
    $('#extraRows').innerHTML = (T.dPromo ? row('<i class="bi bi-percent"></i> Promociones', T.dPromo) : '') + (T.dCumple ? row('<i class="bi bi-gift"></i> Cumpleaños ' + S.fid.cumple + '%', T.dCumple) : '') + (T.dPuntos ? row('<i class="bi bi-stars"></i> Puntos canjeados (' + T.canje + ')', T.dPuntos) : '');
    const pr = $('#ptsRow'), tiene = S.cli.id !== 1 && S.cli.puntos > 0;
    pr.style.setProperty('display', tiene ? 'flex' : 'none', 'important'); $('#ptsDisp').textContent = S.cli.puntos || 0;
    $('#tSub').textContent = fmt(sub); $('#tIgv').textContent = fmt(t - sub); $('#tTotal').textContent = money(t);
    quick(t); vuelto(); $('#btnCobrar').disabled = !S.cart.length || S.busy;
  }
  $('#cart').addEventListener('click', (e) => {
    const row = e.target.closest('.citem'); if (!row) return; const it = S.cart.find((x) => x.id === +row.dataset.id);
    if (e.target.closest('[data-rm]')) S.cart = S.cart.filter((x) => x !== it);
    const q = e.target.closest('[data-q]');
    if (q) { const n = it.cant + (q.dataset.q === '+' ? 1 : -1); if (n <= 0) S.cart = S.cart.filter((x) => x !== it); else if (n > it.stock) return toast('Sin más stock disponible', 'warning'); else it.cant = n; }
    render(); loadProds();
  });
  $('#cart').addEventListener('change', (e) => {
    if (!e.target.matches('[data-in]')) return; const it = S.cart.find((x) => x.id === +e.target.closest('.citem').dataset.id); let n = parseFloat(e.target.value);
    if (!(n > 0)) S.cart = S.cart.filter((x) => x !== it); else if (n > it.stock) { toast(`Stock máximo: ${it.stock}`, 'warning'); it.cant = it.stock; } else it.cant = Math.round(n * 100) / 100;
    render(); loadProds();
  });
  $('#desc').addEventListener('input', render);
  $('#canje').addEventListener('input', () => { S.canje = Math.max(0, parseInt($('#canje').value) || 0); render(); });
  $('#canjeMax').onclick = () => { const T0 = calc(); S.canje = S.cli.puntos; render(); const T = calc(); S.canje = T.canje; $('#canje').value = T.canje; render(); };
  $('#btnClear').onclick = async () => { if (S.cart.length && await confirmBox('Se quitarán todos los productos.', '¿Vaciar la venta?', 'Vaciar', true)) reset(); };
  function reset() { S.cart = []; S.cot = 0; $('#desc').value = 0; $('#recibido').value = ''; setCli({ ...CLI0 }); setTipo('TICKET'); setMetodo('EFECTIVO'); render(); loadProds(); $('#pSearch').focus(); }

  // ---------- Pago ----------
  function quick(t) {
    const base = Math.ceil(t / 10) * 10, opts = [...new Set([t, base, base + 10, 50, 100, 200].filter((v) => v >= t && v > 0))].slice(0, 5);
    $('#quick').innerHTML = t > 0 ? opts.map((v, i) => `<button class="qbtn" data-v="${v}">${i === 0 ? 'Exacto' : fmt(v, 0)}</button>`).join('') : '';
  }
  $('#quick').addEventListener('click', (e) => { const b = e.target.closest('[data-v]'); if (b) { $('#recibido').value = (+b.dataset.v).toFixed(2); vuelto(); } });
  function vuelto() { const v = (+$('#recibido').value || 0) - total(); $('#vuelto').textContent = money(v > 0 ? v : 0); $('#vuelto').style.color = v < 0 && $('#recibido').value ? 'var(--rose)' : ''; }
  $('#recibido').addEventListener('input', vuelto);
  function setMetodo(m) { S.metodo = m; document.querySelectorAll('#payGrid button').forEach((b) => b.classList.toggle('on', b.dataset.m === m)); $('#cashBox').style.display = m === 'EFECTIVO' ? '' : 'none'; if (m === 'EFECTIVO') $('#recibido').focus(); }
  $('#payGrid').addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) setMetodo(b.dataset.m); });
  function setTipo(t) { S.tipo = t; document.querySelectorAll('#segTipo button').forEach((b) => b.classList.toggle('on', b.dataset.t === t)); }
  $('#segTipo').addEventListener('click', (e) => { const b = e.target.closest('button'); if (b) setTipo(b.dataset.t); });

  async function cobrar() {
    if (!S.cart.length || S.busy) return;
    const t = total(); if (S.metodo === 'EFECTIVO' && !(+$('#recibido').value >= t)) { $('#recibido').focus(); return toast('Ingresa el monto recibido (≥ total)', 'warning'); }
    S.busy = true; $('#btnCobrar').disabled = true; $('#btnCobrar').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando…';
    try {
      const T = calc(), snap = { items: S.cart.map((i) => ({ n: i.nombre, q: i.cant, s: i.c.sub })), cli: S.cli };
      const r = await api('pos.php', { a: 'venta', items: S.cart.map((i) => ({ id: i.id, cant: i.cant })), cliente_id: S.cli.id, tipo: S.tipo, metodo: S.metodo, recibido: +$('#recibido').value || 0, descuento: +$('#desc').value || 0, canje_puntos: T.canje, cotizacion_id: S.cot });
      S.last = { ...snap, nro: r.nro, total: r.total, tel: r.telefono, pts: r.puntos }; lastId = r.id;
      $('#okPts').innerHTML = r.puntos ? `<i class="bi bi-stars text-warning"></i> El cliente ganó <b>${r.puntos}</b> puntos` : ''; $('#okWa').style.display = S.cli.id === 1 ? 'none' : ''; $('#okNro').textContent = r.nro; $('#okTotal').textContent = money(r.total);
      $('#okVuelto').textContent = r.vuelto !== null ? 'Vuelto: ' + money(r.vuelto) : 'Pago: ' + S.metodo.toLowerCase();
      mOk.show(); setTimeout(() => $('#okNew').focus(), 300);
      // Ticket automático / apertura del cajón según la configuración de impresión
      if (Imprimir.directa()) { if (APP.print.auto) Imprimir.ticket(lastId); else if (S.metodo === 'EFECTIVO') Imprimir.cajon(true); }
    } catch (e) { toast(e.message, 'danger'); }
    S.busy = false; $('#btnCobrar').innerHTML = '<i class="bi bi-check2-circle"></i> Cobrar <kbd>F9</kbd>'; render();
  }
  $('#btnCobrar').onclick = cobrar;
  $('#okPrint').onclick = () => Imprimir.ticket(lastId, true);
  $('#okWa').onclick = () => {
    const L = S.last; if (!L) return;
    const lines = L.items.map((i) => `• ${i.q} x ${i.n} — ${money(i.s)}`).join('%0A');
    const msg = encodeURIComponent(`Hola ${L.cli.nombre.split(' ')[0]} 👋, gracias por tu compra en ${window.POS_EMPRESA || 'nuestra tienda'}.\nComprobante ${L.nro}\n`) + lines + encodeURIComponent(`\nTOTAL: ${money(L.total)}${L.pts ? `\nGanaste ${L.pts} puntos ⭐` : ''}\n¡Te esperamos pronto!`);
    let tel = String(L.tel || '').replace(/\D/g, ''); if (tel.length === 9) tel = '51' + tel;
    window.open(`https://wa.me/${tel}?text=${msg}`, '_blank');
  };
  $('#okNew').onclick = () => { mOk.hide(); reset(); };

  // ---------- Clientes ----------
  function setCli(c) { S.cli = { ...CLI0, ...c }; S.canje = 0; $('#canje').value = 0; c = S.cli; $('#cliName').textContent = c.nombre; $('#cliDoc').innerHTML = c.id === 1 ? 'Venta al público' : esc(`${c.tipo_doc || ''} ${c.documento || ''}`.trim() || 'Sin documento') + (c.puntos ? ` · <span class="text-warning">⭐ ${c.puntos} pts</span>` : '') + (c.cumple ? ' · <span class="text-danger">🎂 ¡Cumpleaños!</span>' : ''); render(); }
  async function loadCli() {
    const r = await api('pos.php?a=clientes&q=' + encodeURIComponent($('#cliQ').value));
    S.fid = r.fid || S.fid;
    $('#cliList').innerHTML = r.rows.map((c) => `<div class="cl-item" data-c='${esc(JSON.stringify(c))}'><div><b class="small">${esc(c.nombre)}</b><div class="text-muted" style="font-size:.72rem">${esc(c.tipo_doc)} ${esc(c.documento || '')}</div></div><span>${c.cumple ? '<span class="pill warn">🎂</span> ' : ''}${c.puntos ? `<span class="pill info">⭐ ${c.puntos}</span> ` : ''}${c.saldo_deuda > 0 ? `<span class="pill bad">Debe ${money(c.saldo_deuda)}</span>` : ''}</span></div>`).join('') || '<div class="empty py-3">Sin resultados</div>';
  }
  $('#cliBox').onclick = () => { $('#cliQ').value = ''; loadCli(); mCli.show(); setTimeout(() => $('#cliQ').focus(), 400); };
  $('#cliQ').addEventListener('input', debounce(loadCli));
  $('#cliList').addEventListener('click', (e) => { const d = e.target.closest('.cl-item'); if (d) { setCli(JSON.parse(d.dataset.c)); mCli.hide(); if (S.tipo === 'FACTURA' && S.cli.tipo_doc !== 'RUC') toast('Para factura se requiere un cliente con RUC', 'info'); } });
  $('#ncSave').onclick = async () => {
    try {
      const r = await api('crud.php', { e: 'clientes', a: 'save', id: 0, data: { tipo_doc: $('#ncTipo').value, documento: $('#ncDoc').value, nombre: $('#ncNombre').value, limite_credito: 0, estado: 1 } });
      setCli({ id: r.id, nombre: $('#ncNombre').value, documento: $('#ncDoc').value, tipo_doc: $('#ncTipo').value }); ['ncDoc', 'ncNombre'].forEach((i) => $('#' + i).value = ''); mCli.hide(); toast('Cliente registrado');
    } catch (e) { toast(e.message, 'danger'); }
  };

  // ---------- Ventas en espera ----------
  const getHold = () => { try { return JSON.parse(localStorage.getItem('st-hold') || '[]'); } catch (e) { return []; } };
  const setHold = (a) => { try { localStorage.setItem('st-hold', JSON.stringify(a)); } catch (e) {} countHold(); };
  const countHold = () => $('#holdCount').textContent = getHold().length;
  $('#btnHold').onclick = () => {
    const list = getHold();
    if (S.cart.length) { list.push({ t: Date.now(), cart: S.cart, cli: S.cli, tipo: S.tipo, desc: $('#desc').value }); setHold(list); toast('Venta puesta en espera'); reset(); return; }
    $('#holdList').innerHTML = list.length ? list.map((h, i) => `<div class="cl-item"><div><b class="small">${h.cart.length} producto(s) · ${money(h.cart.reduce((a, x) => a + (x.precio_venta || x.precio) * x.cant, 0))}</b><div class="text-muted" style="font-size:.72rem">${esc(h.cli.nombre)} · ${new Date(h.t).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}</div></div>
      <div><button class="btn-soft" data-res="${i}">Retomar</button> <button class="act del" data-del="${i}"><i class="bi bi-trash"></i></button></div></div>`).join('') : '<div class="empty py-3"><i class="bi bi-pause-circle"></i>No hay ventas en espera.<br><small>Agrega productos y presiona «En espera» para guardar una venta.</small></div>';
    mHold.show();
  };
  $('#holdList').addEventListener('click', (e) => {
    const r = e.target.closest('[data-res]'), d = e.target.closest('[data-del]'), list = getHold();
    if (r) { if (S.cart.length) return toast('Primero termina o pon en espera la venta actual', 'warning'); const h = list.splice(+r.dataset.res, 1)[0]; setHold(list); S.cart = h.cart; $('#desc').value = h.desc; setCli(h.cli); setTipo(h.tipo); render(); mHold.hide(); loadProds(); }
    if (d) { list.splice(+d.dataset.del, 1); setHold(list); $('#btnHold').click(); }
  });

  // ---------- Atajos ----------
  document.addEventListener('keydown', (e) => {
    if (e.key === 'F3') { e.preventDefault(); $('#pSearch').focus(); $('#pSearch').select(); }
    if (e.key === 'F4') { e.preventDefault(); $('#btnClear').click(); }
    if (e.key === 'F9') { e.preventDefault(); cobrar(); }
    if (e.key === 'Enter' && $('#mdlOk').classList.contains('show')) { e.preventDefault(); $('#okNew').click(); }
  });
  // F2 en el POS no debe recargar la página
  window.addEventListener('keydown', (e) => { if (e.key === 'F2') e.stopImmediatePropagation(); }, true);

  // Cotización → venta: precarga del carrito (?cot=ID)
  async function loadCot() {
    if (!window.POS_COT) return;
    try {
      const r = await api('pos.php?a=cotizacion&id=' + window.POS_COT);
      r.items.forEach((p) => { const n = +p.cant; if (n <= +p.stock) add(p, n); else { toast(`Stock insuficiente de «${p.nombre}» (disp. ${p.stock})`, 'warning'); if (+p.stock > 0) add(p, +p.stock); } });
      if (r.cliente) setCli(r.cliente); S.cot = r.cot; toast('Cotización cargada: revisa y cobra', 'info');
    } catch (e) { toast(e.message, 'danger'); }
  }
  countHold(); loadCats(); loadProds().then(loadCot); render(); checkCaja();
})();
