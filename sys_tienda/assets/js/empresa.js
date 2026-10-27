(() => {
  const $ = (s) => document.querySelector(s), F = $('#frmEmp');
  let logo = null;
  const val = (n) => F.elements[n];

  function renderLogo(url) {
    logo = url;
    $('#hLogo').innerHTML = url ? `<img src="${url}" alt="">` : '<i class="bi bi-shop"></i>';
    $('#dropIn').innerHTML = url ? `<img src="${url}" alt=""><div class="small text-muted">Clic o arrastra para cambiar</div>` : '<i class="bi bi-cloud-arrow-up big"></i><div class="fw-semibold">Arrastra tu logo aquí</div><div class="small text-muted">o haz clic para elegir</div>';
    $('#btnQuitar').style.display = url ? '' : 'none';
  }
  function preview() {
    const dec = +val('moneda_decimales').value, sd = val('sep_decimal').value;
    let sm = val('sep_miles').value; sm = sm === 'espacio' ? ' ' : sm === 'ninguno' ? '' : sm;
    const sym = val('moneda').value || '', after = val('moneda_posicion').value === 'despues';
    const f = (n) => { const [i, d] = n.toFixed(dec).split('.'); return i.replace(/\B(?=(\d{3})+(?!\d))/g, sm) + (dec > 0 ? sd + d : ''); };
    const m = (n) => after ? f(n) + ' ' + sym : sym + ' ' + f(n);
    const t = +val('igv').value || 0;
    $('#pv1').textContent = m(1234.5); $('#pv2').textContent = m(1234567.891); $('#pv3').textContent = m(9.9); $('#pv4').textContent = m(100 * (1 + t / 100));
    const bad = (sm === sd && sm !== '');
    $('#pv1').style.color = bad ? '#f87171' : '';
  }
  function head() { $('#hNom').textContent = val('empresa_nombre').value || 'Tu empresa'; $('#hSub').textContent = [val('empresa_ruc').value && 'RUC ' + val('empresa_ruc').value, val('empresa_direccion').value].filter(Boolean).join(' · ') || 'Completa los datos que aparecerán en tickets, comprobantes y reportes'; }

  async function load() {
    const r = await api('empresa.php?a=get');
    $('#zona').innerHTML = r.zonas.map((z) => `<option>${z}</option>`).join('');
    for (const [k, v] of Object.entries(r.cfg)) {
      const el = val(k); if (!el) continue;
      if (el.type === 'checkbox') el.checked = String(v) === '1'; else el.value = v;
    }
    renderLogo(r.logo); preview(); head();
  }
  F.addEventListener('input', () => { preview(); head(); });
  F.addEventListener('change', () => { preview(); head(); });

  F.addEventListener('submit', async (e) => {
    e.preventDefault();
    const cfg = {}; [...F.elements].forEach((el) => { if (!el.name) return; cfg[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value; });
    const b = $('#btnSave'); b.disabled = true;
    try { await api('empresa.php', { a: 'save', cfg }); toast('Datos de la empresa guardados. Se aplican al recargar la página.'); setTimeout(() => location.reload(), 900); }
    catch (x) { toast(x.message || 'Error al guardar', 'danger'); b.disabled = false; }
  });

  async function subir(file) {
    if (!file) return;
    const fd = new FormData(); fd.append('a', 'logo'); fd.append('logo', file);
    try {
      const res = await fetch(APP.base + '/api/empresa.php?a=logo', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': APP.csrf } });
      const j = await res.json(); if (!j.ok) throw new Error(j.error || j.message || 'No se pudo subir');
      renderLogo(j.logo); toast('Logo actualizado');
    } catch (x) { toast(x.message, 'danger'); }
  }
  const drop = $('#drop'), fi = $('#logoFile');
  drop.addEventListener('click', () => fi.click());
  fi.addEventListener('change', () => { subir(fi.files[0]); fi.value = ''; });
  ['dragover', 'dragenter'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('drag'); }));
  ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('drag'); }));
  drop.addEventListener('drop', (e) => subir(e.dataTransfer.files[0]));
  $('#btnQuitar').addEventListener('click', async () => {
    if (!(await confirmBox('¿Quitar el logo de la empresa?'))) return;
    await api('empresa.php', { a: 'logo_quitar' }); renderLogo(null); toast('Logo eliminado');
  });
  load();
})();
