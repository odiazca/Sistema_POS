(() => {
  const $ = (s) => document.querySelector(s);
  const SUG = ['Top 10 productos más vendidos este mes', 'Ventas por día de los últimos 30 días', 'Ventas por método de pago esta semana', 'Utilidad por categoría este mes', 'Clientes que más compran', 'Productos con stock bajo', 'Gastos por categoría este mes', 'Productos que vencen en los próximos 30 días', 'Ventas por cajero este mes'];
  const COLORS = ['#5b5ef4', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6', '#ec4899', '#84cc16', '#64748b'];
  let ST = null, last = null, chart = null, cur = null;
  const MONEY = /(total|importe|ingreso|venta|monto|utilidad|costo|precio|valor|deuda|gasto|margen|saldo|ticket|promedio)/i;
  const mdlCfg = new bootstrap.Modal($('#mdlCfg')), mdlEsq = new bootstrap.Modal($('#mdlEsq'));
  const isNum = (v) => typeof v === 'number';
  const isDate = (v) => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v);
  const label = (c) => c.replace(/_/g, ' ').replace(/^./, (x) => x.toUpperCase());
  const cell = (v, c) => v === null || v === undefined ? '<span class="text-muted">—</span>' : isNum(v) ? (MONEY.test(c) ? money(v) : fmt(v, Number.isInteger(v) ? 0 : 2)) : esc(String(v));

  $('#iaChips').innerHTML = SUG.slice(0, 6).map((s) => `<button type="button" class="ia-chip">${esc(s)}</button>`).join('');
  $('#iaChips').addEventListener('click', (e) => { const c = e.target.closest('.ia-chip'); if (c) { $('#iaQ').value = c.textContent; preguntar(true); } });
  $('#iaQ').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); preguntar(); } });
  $('#iaQ').addEventListener('input', (e) => { e.target.style.height = 'auto'; e.target.style.height = Math.min(120, e.target.scrollHeight) + 'px'; });
  $('#frmAsk').onsubmit = (e) => { e.preventDefault(); preguntar(); };

  async function estado() {
    ST = await api('ia.php?a=estado');
    $('#iaSetup').style.display = ST.listo ? 'none' : '';
    const lst = (arr, fav) => arr.map((x) => `<div class="it" data-id="${x.id}"><i class="bi ${fav ? 'bi-star-fill text-warning' : 'bi-arrow-return-right text-muted'}"></i><div class="tx">${esc(x.titulo || x.pregunta)}<small>${esc(x.pregunta)}</small></div><button data-fav="${x.id}" data-v="${fav ? 0 : 1}" title="${fav ? 'Quitar de favoritas' : 'Marcar como favorita'}"><i class="bi ${fav ? 'bi-star' : 'bi-star'}"></i></button><button data-del="${x.id}" title="Eliminar"><i class="bi bi-x-lg"></i></button></div>`).join('');
    $('#iaFav').innerHTML = lst(ST.favoritos, true) || '<div class="text-muted small py-2">Marca con la estrella tus consultas frecuentes para repetirlas con un clic (sin gastar IA).</div>';
    $('#iaHis').innerHTML = lst(ST.historial, false) || '<div class="text-muted small py-2">Aún no hay consultas.</div>';
  }
  document.addEventListener('click', async (e) => {
    const side = e.target.closest('.ia-side .it'); if (!side) return;
    const f = e.target.closest('[data-fav]'), d = e.target.closest('[data-del]');
    try {
      if (f) { await api('ia.php', { a: 'favorito', id: +f.dataset.fav, valor: +f.dataset.v }); estado(); if (cur && cur.id === +f.dataset.fav) { cur.favorito = +f.dataset.v; const b = $('#iaStar'); if (b) b.innerHTML = `<i class="bi bi-star${cur.favorito ? '-fill text-warning' : ''}"></i>`; } return; }
      if (d) { await api('ia.php', { a: 'borrar', id: +d.dataset.del }); estado(); return; }
      cargarGuardada(+side.dataset.id);
    } catch (x) { toast(x.message, 'danger'); }
  });

  function cargando(txt) { $('#iaOut').innerHTML = `<div class="cardx ia-res"><div class="ia-load"><div class="ia-dots"><span></span><span></span><span></span></div><div id="iaLt">${esc(txt)}</div></div></div>`; }
  async function preguntar(auto) {
    const q = $('#iaQ').value.trim(); if (q.length < 4) return toast('Escribe tu pregunta', 'warning');
    if (!ST.listo) { toast('Primero configura el proveedor de IA', 'warning'); return mdlCfg.show(); }
    $('#iaGo').disabled = true; cargando('Interpretando tu pregunta…');
    const t = setTimeout(() => { const e = $('#iaLt'); if (e) e.textContent = 'Consultando tus datos…'; }, 2500);
    try {
      const r = await api('ia.php', { a: 'preguntar', pregunta: q, previo: !auto && last ? { pregunta: last.pregunta, sql: last.sql } : null });
      if (r.mensaje) { $('#iaOut').innerHTML = `<div class="cardx ia-res"><div class="d-flex gap-3"><i class="bi bi-info-circle fs-4 text-primary"></i><div>${esc(r.mensaje)}</div></div></div>`; }
      else { r.pregunta = q; mostrar(r); resumir(r.id); }
      estado();
    } catch (x) { $('#iaOut').innerHTML = `<div class="cardx ia-res"><div class="d-flex gap-3"><i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i><div><b>No se pudo completar la consulta</b><div class="small text-muted mt-1">${esc(x.message)}</div></div></div></div>`; }
    clearTimeout(t); $('#iaGo').disabled = false;
  }
  async function cargarGuardada(id) {
    cargando('Ejecutando consulta guardada…');
    try { const r = await api('ia.php', { a: 'ejecutar', id }); $('#iaQ').value = r.pregunta; mostrar(r); resumir(r.id); estado(); }
    catch (x) { $('#iaOut').innerHTML = `<div class="cardx ia-res text-danger">${esc(x.message)}</div>`; }
  }

  function elegirGrafico(r) {
    const cols = r.columnas, f = r.filas; if (!f.length || cols.length < 2) return null;
    const g = r.grafico || {}; let xi = cols.indexOf(g.x), yi = cols.indexOf(g.y);
    if (xi < 0 || yi < 0 || !isNum(f[0][yi])) { xi = cols.findIndex((c, i) => !isNum(f[0][i])); yi = cols.findIndex((c, i) => i !== xi && isNum(f[0][i])); if (xi < 0) xi = 0; }
    if (yi < 0 || !isNum(f.find((x) => x[yi] !== null)?.[yi])) return null;
    let tipo = g.tipo && g.tipo !== 'none' ? g.tipo : (isDate(f[0][xi]) ? 'line' : (f.length <= 40 ? 'bar' : 'none'));
    if (g.tipo === 'none' && !isDate(f[0][xi]) && f.length > 1 && f.length <= 15) tipo = 'bar';
    if (tipo === 'none' || f.length < 2) return null;
    if (tipo === 'pie' && f.length > 8) tipo = 'bar';
    return { xi, yi, tipo };
  }
  function dibujar(r, cfg, tipo) {
    if (chart) { chart.destroy(); chart = null; }
    const cv = $('#iaCv'); if (!cv || !cfg) return;
    const lab = r.filas.map((x) => isDate(x[cfg.xi]) ? String(x[cfg.xi]).slice(0, 10) : String(x[cfg.xi] ?? '—')), val = r.filas.map((x) => x[cfg.yi]);
    const pie = tipo === 'pie', line = tipo === 'line', yn = r.columnas[cfg.yi];
    Chart.defaults.font.family = "'Inter',sans-serif"; Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--muted') || '#64748b';
    chart = new Chart(cv, { type: pie ? 'doughnut' : tipo, data: { labels: lab, datasets: [{ label: label(yn), data: val, backgroundColor: pie ? COLORS : (line ? 'rgba(91,94,244,.15)' : '#5b5ef4'), borderColor: pie ? '#fff' : '#5b5ef4', borderWidth: pie ? 2 : 2, borderRadius: line || pie ? 0 : 8, fill: line, tension: .3, pointRadius: line ? 3 : 0 }] },
      options: { responsive: true, maintainAspectRatio: false, indexAxis: !pie && !line && lab.length > 6 && lab.some((l) => l.length > 14) ? 'y' : 'x', plugins: { legend: { display: pie, position: 'right' }, tooltip: { callbacks: { label: (c) => ' ' + (MONEY.test(yn) ? money(c.parsed.y ?? c.parsed) : fmt(c.parsed.y ?? c.parsed, Number.isInteger(c.parsed.y ?? c.parsed) ? 0 : 2)) } } }, scales: pie ? {} : { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.2)' } } } } });
  }

  function mostrar(r) {
    last = r; cur = r; const cfg = elegirGrafico(r); let tipo = cfg?.tipo;
    const th = r.columnas.map((c, i) => `<th class="${r.filas.length && isNum(r.filas[0][i]) ? 'r' : ''}">${esc(label(c))}</th>`).join('');
    const tb = r.filas.map((f) => `<tr>${f.map((v, i) => `<td class="${isNum(v) ? 'r num' : ''}">${cell(v, r.columnas[i])}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${r.columnas.length}"><div class="empty"><i class="bi bi-inbox"></i>La consulta no devolvió resultados</div></td></tr>`;
    $('#iaOut').innerHTML = `<div class="cardx ia-res">
      <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap"><div><h5>${esc(r.titulo)}</h5><div class="ia-meta">${r.filas.length} fila(s)${r.truncado ? ' (límite de 500 alcanzado)' : ''} · ${r.ms} ms · «${esc(r.pregunta || '')}»</div></div>
        <div class="d-flex gap-2"><button class="btn-soft" id="iaStar" title="Favorita"><i class="bi bi-star${r.favorito ? '-fill text-warning' : ''}"></i></button><a class="btn-soft" href="${APP.base}/api/ia.php?a=csv&id=${r.id}"><i class="bi bi-download"></i> CSV</a><button class="btn-soft" id="iaSqlB"><i class="bi bi-code-slash"></i> SQL</button></div></div>
      <div id="iaSum"></div>
      ${cfg ? `<div class="d-flex gap-1 justify-content-end"><div class="seg" id="iaSeg"><button data-t="bar">Barras</button><button data-t="line">Líneas</button><button data-t="pie">Torta</button></div></div><div class="ia-chart"><canvas id="iaCv"></canvas></div>` : ''}
      <div class="ia-tbl"><table class="tbl"><thead><tr>${th}</tr></thead><tbody>${tb}</tbody></table></div>
      <pre class="ia-sql" id="iaSql" style="display:none">${esc(r.sql)}</pre></div>`;
    if (cfg) { dibujar(r, cfg, tipo); const seg = $('#iaSeg'); seg.querySelector(`[data-t=${tipo}]`)?.classList.add('on'); seg.onclick = (e) => { const b = e.target.closest('[data-t]'); if (!b) return; seg.querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b)); dibujar(r, cfg, b.dataset.t); }; }
    $('#iaSqlB').onclick = () => { const p = $('#iaSql'); p.style.display = p.style.display === 'none' ? '' : 'none'; };
    $('#iaStar').onclick = async () => { try { const v = cur.favorito ? 0 : 1; await api('ia.php', { a: 'favorito', id: cur.id, valor: v }); cur.favorito = v; $('#iaStar').innerHTML = `<i class="bi bi-star${v ? '-fill text-warning' : ''}"></i>`; estado(); if (v) toast('Guardada en favoritas'); } catch (x) { toast(x.message, 'danger'); } };
    document.querySelector('.ia-res')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  async function resumir(id) {
    if (!ST.cfg.resumen) return; const box = $('#iaSum'); if (!box) return;
    box.innerHTML = '<div class="ia-sum"><i class="bi bi-stars"></i><div class="ia-dots mt-1"><span></span><span></span><span></span></div></div>';
    try { const r = await api('ia.php', { a: 'resumir', id }); if (cur && cur.id === id && $('#iaSum')) $('#iaSum').innerHTML = `<div class="ia-sum"><i class="bi bi-stars"></i><div>${esc(r.resumen)}</div></div>`; }
    catch (x) { if ($('#iaSum')) $('#iaSum').innerHTML = ''; }
  }

  // ---- Configuración ----
  function cargarCfg() {
    const c = ST.cfg, P = ST.presets;
    $('#cProv').innerHTML = Object.entries(P).map(([k, p]) => `<option value="${k}">${esc(p.nombre)}</option>`).join(''); $('#cProv').value = c.proveedor;
    $('#cUrl').value = c.url; $('#cMod').value = c.modelo; $('#cKey').value = ''; $('#cRes').checked = !!c.resumen; $('#cErr').textContent = ''; ayuda();
  }
  function ayuda() { const p = ST.presets[$('#cProv').value]; $('#cAyuda').textContent = p.ayuda; $('#cKey').disabled = !p.clave; $('#cKeyH').textContent = !p.clave ? 'Este proveedor no necesita clave.' : (ST.cfg.clave_puesta && $('#cProv').value === ST.cfg.proveedor ? 'Hay una clave guardada. Déjalo vacío para conservarla.' : 'Pega aquí tu clave. Se guarda cifrada y no se vuelve a mostrar.'); }
  $('#cProv').onchange = () => { const p = ST.presets[$('#cProv').value]; if (p.url) { $('#cUrl').value = p.url; $('#cMod').value = p.modelo; } ayuda(); };
  $('#btnCfg').onclick = () => { cargarCfg(); mdlCfg.show(); };
  const datosCfg = () => ({ a: 'guardar_config', proveedor: $('#cProv').value, url: $('#cUrl').value, modelo: $('#cMod').value, clave: $('#cKey').value, resumen: $('#cRes').checked ? 1 : 0 });
  $('#frmCfg').onsubmit = async (e) => { e.preventDefault(); $('#cErr').textContent = ''; try { await api('ia.php', datosCfg()); await estado(); mdlCfg.hide(); toast('Configuración guardada'); } catch (x) { $('#cErr').textContent = x.message; } };
  $('#cTest').onclick = async () => { $('#cErr').textContent = ''; const b = $('#cTest'); b.disabled = true; b.innerHTML = '<i class="bi bi-hourglass-split"></i> Probando…';
    try { await api('ia.php', datosCfg()); await estado(); const r = await api('ia.php', { a: 'probar' }); $('#cErr').className = 'col-12 text-success small'; $('#cErr').textContent = '✔ Conexión correcta (respuesta: ' + r.respuesta + ')'; }
    catch (x) { $('#cErr').className = 'col-12 text-danger small'; $('#cErr').textContent = x.message; }
    b.disabled = false; b.innerHTML = '<i class="bi bi-plug"></i> Probar conexión'; };
  $('#btnEsq').onclick = async () => { mdlEsq.show(); $('#esqTxt').textContent = 'Cargando…'; $('#esqTxt').textContent = await (await fetch(APP.base + '/api/ia.php?a=esquema', { credentials: 'same-origin' })).text(); };

  $('#frmQuick').onsubmit = async (e) => { e.preventDefault(); const k = $('#qKey').value.trim(), b = $('#qGo'); $('#qErr').textContent = ''; if (k.length < 8) { $('#qErr').textContent = 'Pega la clave completa.'; return; }
    b.disabled = true; b.textContent = 'Probando…';
    try { await window.iaActivar(k); toast('IA activada', 'success'); await estado(); } catch (x) { $('#qErr').textContent = x.message; } b.disabled = false; b.textContent = 'Activar'; };
  estado();
})();
