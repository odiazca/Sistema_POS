/* CRM · Pipeline Kanban */
(function () {
  const $ = (s) => document.querySelector(s); const C = window.CRM; let D = null, drag = null;
  const mot = new bootstrap.Modal($('#mdlMotivo'));
  const cierre = (o) => { if (!o.cierre_estimado) return ''; const d = o.dias_cierre; const c = d < 0 ? 'text-danger' : d <= 3 ? 'text-warning' : ''; return `<span class="${c}"><i class="bi bi-calendar-event"></i> ${C.fd(o.cierre_estimado).slice(0, 5)}</span>`; };

  async function load() {
    D = await api('crm.php?a=pipeline'); const k = D.kpi;
    $('#kpis').innerHTML = `<div class="col-md-3 col-6">${C.stat('bi-bullseye', 'ic-indigo', 'Oportunidades abiertas', k.abiertas)}</div><div class="col-md-3 col-6">${C.stat('bi-cash-stack', 'ic-sky', 'Valor del pipeline', money(k.valor))}</div>
      <div class="col-md-3 col-6">${C.stat('bi-graph-up-arrow', 'ic-green', 'Ponderado por probabilidad', money(k.ponderado), 'Ingreso esperado')}</div><div class="col-md-3 col-6">${C.stat('bi-trophy', 'ic-amber', 'Tasa de éxito', k.tasa_exito + '%', k.ciclo !== null ? `Ciclo medio ${k.ciclo} días` : 'Aún sin ventas cerradas')}</div>`;
    const colHtml = (e) => {
      const ops = D.oportunidades.filter((o) => o.etapa_id == e.id).sort((a, b) => a.orden - b.orden); const tot = ops.reduce((s, o) => s + +o.valor, 0);
      return `<div class="kb-col" data-e="${e.id}" data-tipo="${e.tipo}"><div class="hd" style="--c:${e.color}"><div class="d-flex justify-content-between align-items-center"><b>${esc(e.nombre)} <span class="text-muted fw-normal">· ${ops.length}</span></b>${e.tipo === 'ABIERTA' ? `<button class="ico-btn" style="width:26px;height:26px" data-add="${e.id}" title="Agregar"><i class="bi bi-plus-lg"></i></button>` : ''}</div>
        <small>${money(tot)}${e.tipo === 'ABIERTA' ? ` · prob. ${e.probabilidad}%` : e.tipo === 'GANADA' ? ' · últimos 30 días' : ' · últimos 30 días'}</small></div>
        <div class="kb-list">${ops.map((o) => `<div class="kb-card ${o.estado === 'GANADA' ? 'won' : o.estado === 'PERDIDA' ? 'lost' : ''}" draggable="true" data-o="${o.id}"><div class="ti">${esc(o.titulo)}</div><div class="cl"><i class="bi bi-person"></i> ${esc(o.cliente)}</div>
          <div class="ft"><span class="val num">${money(o.valor)}</span><span>${cierre(o)}</span></div>
          <div class="ft mt-1"><span>${o.responsable ? esc(o.responsable.split(' ')[0]) : ''}</span><span>${o.estado === 'ABIERTA' ? o.dias_etapa + ' d en etapa' : o.estado === 'PERDIDA' ? esc(o.motivo_perdida || '') : ''}</span></div></div>`).join('')}</div></div>`;
    };
    $('#kb').innerHTML = `<div class="kb">${D.etapas.filter((e) => e.tipo === 'ABIERTA').map(colHtml).join('')}</div><div class="kb-end">${D.etapas.filter((e) => e.tipo !== 'ABIERTA').map(colHtml).join('')}</div>`;
  }

  async function mover(oid, col, motivo) {
    const ids = [...col.querySelectorAll('.kb-card')].map((c) => +c.dataset.o);
    try { await api('crm.php', { a: 'opp_mover', id: oid, etapa_id: col.dataset.e, orden_ids: ids, motivo }); C.changed(); } catch (x) { toast(x.message, 'danger'); }
    load();
  }

  const kb = $('#kb');
  kb.addEventListener('dragstart', (e) => { const c = e.target.closest('.kb-card'); if (!c) return; drag = c; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.o); });
  kb.addEventListener('dragend', () => { drag?.classList.remove('drag'); document.querySelectorAll('.kb-col.over').forEach((c) => c.classList.remove('over')); drag = null; });
  kb.addEventListener('dragover', (e) => {
    const col = e.target.closest('.kb-col'); if (!col || !drag) return; e.preventDefault();
    document.querySelectorAll('.kb-col.over').forEach((c) => c !== col && c.classList.remove('over')); col.classList.add('over');
    const list = col.querySelector('.kb-list'), after = [...list.querySelectorAll('.kb-card:not(.drag)')].find((c) => e.clientY < c.getBoundingClientRect().top + c.offsetHeight / 2);
    after ? list.insertBefore(drag, after) : list.appendChild(drag);
  });
  kb.addEventListener('drop', async (e) => {
    const col = e.target.closest('.kb-col'); if (!col || !drag) return; e.preventDefault(); const oid = +drag.dataset.o; const o = D.oportunidades.find((x) => x.id == oid);
    if (o.etapa_id == col.dataset.e) return mover(oid, col);              // solo reordena
    if (col.dataset.tipo === 'PERDIDA') {
      $('#motOk').onclick = () => { const t = $('#motTxt').value.trim(); mot.hide(); mover(oid, col, $('#motSel').value + (t ? ' · ' + t : '')); $('#motTxt').value = ''; };
      $('#mdlMotivo').addEventListener('hidden.bs.modal', () => load(), { once: true }); mot.show(); return;
    }
    mover(oid, col);
  });
  kb.addEventListener('click', (e) => {
    const a = e.target.closest('[data-add]'); if (a) return C.oppForm({ etapa_id: a.dataset.add, etapas: D.etapas, onSave: load });
    const c = e.target.closest('.kb-card'); if (c) { const o = D.oportunidades.find((x) => x.id == c.dataset.o); C.oppForm({ opp: o, etapas: D.etapas, onSave: load }); }
  });
  $('#bNew').onclick = () => C.oppForm({ etapas: D?.etapas, onSave: load });
  document.addEventListener('crm:cambio', () => { if (!drag) load(); });
  load();
})();
