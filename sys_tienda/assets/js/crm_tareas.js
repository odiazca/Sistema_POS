/* CRM · Tareas y seguimiento */
(function () {
  const $ = (s) => document.querySelector(s); const C = window.CRM; let est = 'PENDIENTE', rows = [];
  async function load() {
    const r = await api(`crm.php?a=tareas&estado=${est}&quien=${$('#quien')?.value || ''}`); rows = r.rows; const k = r.kpi;
    $('#kpis').innerHTML = `<div class="col-md-3 col-6">${C.stat('bi-list-check', 'ic-indigo', 'Pendientes', k.pendientes)}</div><div class="col-md-3 col-6">${C.stat('bi-alarm', 'ic-rose', 'Vencidas', k.vencidas)}</div>
      <div class="col-md-3 col-6">${C.stat('bi-calendar-day', 'ic-amber', 'Para hoy', k.hoy)}</div><div class="col-md-3 col-6">${C.stat('bi-check2-circle', 'ic-green', 'Hechas esta semana', k.hechas_semana)}</div>`;
    const hoy = new Date().toISOString().slice(0, 10), g = { 'Vencidas': [], 'Hoy': [], 'Próximas': [], 'Sin fecha': [], 'Completadas': [] };
    rows.forEach((t) => { if (t.estado === 'HECHA') g['Completadas'].push(t); else if (!t.vence) g['Sin fecha'].push(t); else if (t.vencida) g['Vencidas'].push(t); else if (t.vence.slice(0, 10) === hoy) g['Hoy'].push(t); else g['Próximas'].push(t); });
    const row = (t) => `<div class="task-row ${t.estado === 'HECHA' ? 'done' : ''}"><div class="ck" data-ck="${t.id}">${t.estado === 'HECHA' ? '<i class="bi bi-check2"></i>' : ''}</div>
      <div class="flex-grow-1" style="min-width:0"><div class="tt"><span class="pri ${t.prioridad}" title="Prioridad ${t.prioridad.toLowerCase()}"></span> ${esc(t.titulo)}</div>
      <div class="ts ${t.vencida ? 'text-danger fw-bold' : ''}"><i class="bi ${C.TAREA_IC[t.tipo]}"></i> ${C.TAREA_NOM[t.tipo]} · ${C.venceTxt(t)}${t.cliente ? ` · <a class="crm-link" data-ficha="${t.cliente_id}">${esc(t.cliente)}</a>` : ''}${t.asignado ? ' · ' + esc(t.asignado) : ''}</div></div>
      <div class="d-flex gap-1">${t.cliente && t.telefono ? `<a class="ico-btn" href="tel:${esc(t.telefono)}" title="Llamar"><i class="bi bi-telephone"></i></a>` : ''}<button class="ico-btn" data-ed="${t.id}" title="Editar"><i class="bi bi-pencil"></i></button><button class="ico-btn danger" data-del="${t.id}" title="Eliminar"><i class="bi bi-trash"></i></button></div></div>`;
    $('#lista').innerHTML = Object.entries(g).filter(([, v]) => v.length).map(([k2, v]) => `<div class="task-grp ${k2 === 'Vencidas' ? 'text-danger' : ''}">${k2} · ${v.length}</div>${v.map(row).join('')}`).join('') || C.empty('bi-check2-all', est === 'PENDIENTE' ? '¡Todo al día! No tienes tareas pendientes.' : 'No hay tareas para mostrar.');
  }
  $('#fest').onclick = (e) => { const b = e.target.closest('[data-v]'); if (!b) return; est = b.dataset.v; document.querySelectorAll('#fest .chip-f').forEach((x) => x.classList.toggle('on', x === b)); load(); };
  $('#quien')?.addEventListener('change', load);
  $('#bNew').onclick = () => C.tareaForm({ onSave: load });
  $('#lista').addEventListener('click', async (e) => {
    const ck = e.target.closest('[data-ck]'), ed = e.target.closest('[data-ed]'), del = e.target.closest('[data-del]');
    try {
      if (ck) { await api('crm.php', { a: 'tarea_toggle', id: ck.dataset.ck }); C.changed(); load(); }
      if (ed) C.tareaForm({ tarea: rows.find((t) => t.id == ed.dataset.ed), onSave: load });
      if (del && (await confirmBox('Se eliminará esta tarea.', '¿Eliminar tarea?', 'Eliminar', true))) { await api('crm.php', { a: 'tarea_borrar', id: del.dataset.del }); toast('Tarea eliminada'); load(); }
    } catch (x) { toast(x.message, 'danger'); }
  });
  document.addEventListener('crm:cambio', load);
  load();
})();
