(() => {
  const $ = (s) => document.querySelector(s);
  const fsize = (b) => b > 1073741824 ? (b / 1073741824).toFixed(1) + ' GB' : b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  const TIPO = { manual: ['manual', 'Manual', 'bi-hand-index-thumb'], auto: ['auto', 'Automática', 'bi-robot'], previo: ['previo', 'Previa a restaurar/borrar', 'bi-shield-exclamation'], subido: ['subido', 'Subida', 'bi-cloud-upload'] };
  const mRs = new bootstrap.Modal($('#mdlRs')), mRst = new bootstrap.Modal($('#mdlRst')); let rsName = '';

  async function load() {
    const r = await api('respaldos.php?a=list'), c = r.cfg;
    $('#hInfo').textContent = c.backup_ultimo ? 'Última copia: ' + c.backup_ultimo : 'Todavía no hay copias. Crea la primera ahora.';
    const s = r.resumen;
    $('#hStats').innerHTML = [[r.lista.length, 'Copias guardadas'], [fsize(r.total_bytes), 'Espacio usado'], [fsize(r.db_kb * 1024), 'Base de datos'], [s.ventas, 'Ventas'], [s.productos, 'Productos']].map(([v, l]) => `<div class="bk-stat"><b>${esc(String(v))}</b><span>${l}</span></div>`).join('');
    $('#bkCount').textContent = r.lista.length + ' archivo(s)' + (r.libre ? ' · ' + fsize(r.libre) + ' libres' : '');
    $('#bkList').innerHTML = r.lista.map((b) => { const t = TIPO[b.tipo] || TIPO.subido; return `<div class="bk-item"><div class="bk-ic ${t[0]}"><i class="bi ${t[2]}"></i></div>
      <div class="flex-grow-1"><div class="nm">${esc(b.fecha)}</div><div class="mt">${t[1]} · ${fsize(b.bytes)} · ${esc(b.nombre)}</div></div>
      <div style="white-space:nowrap"><a class="act" title="Descargar" href="${APP.base}/api/respaldos.php?a=descargar&n=${encodeURIComponent(b.nombre)}"><i class="bi bi-download"></i></a>
      <button class="act" title="Restaurar" data-rs="${esc(b.nombre)}"><i class="bi bi-arrow-counterclockwise"></i></button><button class="act" title="Eliminar archivo" data-bd="${esc(b.nombre)}"><i class="bi bi-trash3"></i></button></div></div>`; }).join('')
      || '<div class="empty"><i class="bi bi-database-x"></i>Aún no hay copias de seguridad</div>';
    const f = $('#frmBk'); f.backup_auto.checked = String(c.backup_auto) === '1'; f.backup_horas.value = c.backup_horas; f.backup_horas.tomselect?.sync(false); f.backup_conservar.value = c.backup_conservar; f.backup_copia.value = c.backup_copia || '';
    $('#cronCmd').textContent = 'php "' + r.dir.replace(/backups$/, '') + 'backup_cron.php"';
  }
  $('#bkNow').onclick = async () => { const b = $('#bkNow'); b.disabled = true; try { await api('respaldos.php', { a: 'crear' }); toast('Copia creada'); load(); } catch (x) { toast(x.message, 'danger'); } b.disabled = false; };
  $('#frmBk').onsubmit = async (e) => { e.preventDefault(); const f = e.target; try { await api('respaldos.php', { a: 'guardar', cfg: { backup_auto: f.backup_auto.checked ? 1 : 0, backup_horas: f.backup_horas.value, backup_conservar: f.backup_conservar.value, backup_copia: f.backup_copia.value } }); toast('Programación guardada'); load(); } catch (x) { toast(x.message, 'danger'); } };
  $('#bkUp').onchange = async (e) => {
    const fl = e.target.files[0]; if (!fl) return; const fd = new FormData(); fd.append('archivo', fl); fd.append('a', 'subir');
    try { const r = await fetch(`${APP.base}/api/respaldos.php`, { method: 'POST', headers: { 'X-CSRF-Token': APP.csrf }, body: fd }), j = await r.json(); if (!j.ok) throw new Error(j.error); toast('Archivo subido. Ya puedes restaurarlo desde la lista.'); load(); } catch (x) { toast(x.message, 'danger'); } e.target.value = '';
  };
  $('#bkList').addEventListener('click', async (e) => {
    const d = e.target.closest('[data-bd]'), r = e.target.closest('[data-rs]');
    if (d && await confirmBox('Se elimina solo este archivo de copia de seguridad.', '¿Eliminar copia?', 'Eliminar', true)) { try { await api('respaldos.php', { a: 'borrar', n: d.dataset.bd }); toast('Copia eliminada'); load(); } catch (x) { toast(x.message, 'danger'); } }
    if (r) { rsName = r.dataset.rs; $('#rsFile').textContent = rsName; $('#frmRs').reset(); $('#rsErr').textContent = ''; mRs.show(); }
  });
  $('#frmRs').onsubmit = async (e) => {
    e.preventDefault(); const f = e.target; $('#rsErr').textContent = ''; $('#rsGo').disabled = true;
    try { await api('respaldos.php', { a: 'restaurar', n: rsName, password: f.password.value, confirmar: f.confirmar.value }); mRs.hide(); toast('Copia restaurada. Vuelve a iniciar sesión.', 'success'); setTimeout(() => location.href = APP.base + '/logout.php', 1800); }
    catch (x) { $('#rsErr').textContent = x.message; } $('#rsGo').disabled = false;
  };

  // ---- Asistente de restablecimiento ----
  const WORD = { movimientos: 'BORRAR', catalogo: 'BORRAR', empresa_nueva: 'EMPRESA NUEVA' };
  $('#btnRst').onclick = () => { $('#frmRst').reset(); $('#rstErr').textContent = ''; sync(); mRst.show(); };
  function sync() { const v = $('#frmRst').alcance.value; $('#rstWord').textContent = WORD[v]; document.querySelectorAll('#frmRst .scope').forEach((l) => l.classList.toggle('on', l.querySelector('input').checked)); }
  $('#frmRst').addEventListener('change', sync);
  $('#frmRst').onsubmit = async (e) => {
    e.preventDefault(); const f = e.target; $('#rstErr').textContent = ''; $('#rstGo').disabled = true;
    try {
      const r = await api('respaldos.php', { a: 'resetear', alcance: f.alcance.value, password: f.password.value, confirmar: f.confirmar.value });
      mRst.hide(); toast('Sistema restablecido. Copia previa: ' + r.previo, 'success'); setTimeout(() => location.reload(), 1600);
    } catch (x) { $('#rstErr').textContent = x.message; } $('#rstGo').disabled = false;
  };
  load();
})();
