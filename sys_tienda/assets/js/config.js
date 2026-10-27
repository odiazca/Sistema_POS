(function () {
  const $ = (s) => document.querySelector(s);
  document.querySelectorAll('#tabs button').forEach((b) => b.onclick = () => { document.querySelectorAll('#tabs button').forEach((x) => x.classList.toggle('on', x === b)); ['emp', 'ser', 'imp', 'seg'].forEach((t) => $('#t-' + t).style.display = t === b.dataset.t ? '' : 'none'); if (b.dataset.t === 'bak') loadBk(); if (b.dataset.t === 'seg') loadSeg(); });
  async function load() {
    const r = await api('config.php?a=get');
    cargarImp(r.cfg);
    Object.entries(r.cfg).forEach(([k, v]) => { const el = document.querySelector(`#frmEmp [name="${k}"]`); if (el) el.value = v; });
    let last = null; $('#serRows').innerHTML = r.series.map((s) => { const h = s.sucursal !== last ? `<div class="fw-bold small mt-3 mb-1"><i class="bi bi-shop me-1"></i>${esc(s.sucursal)}</div>` : ''; last = s.sucursal; return h + `<div class="row g-2 align-items-end mb-2" data-id="${s.id}"><div class="col-4"><label class="form-label">Comprobante</label><input class="form-control" value="${s.tipo}" disabled></div><div class="col-4"><label class="form-label">Serie</label><input class="form-control" data-k="serie" value="${esc(s.serie)}" maxlength="4"></div><div class="col-4"><label class="form-label">Último correlativo</label><input type="number" min="0" class="form-control" data-k="correlativo" value="${s.correlativo}"></div></div>`; }).join('');
  }
  $('#frmEmp').onsubmit = async (e) => { e.preventDefault(); try { await api('config.php', { a: 'save', cfg: Object.fromEntries(new FormData(e.target)) }); toast('Configuración guardada'); } catch (x) { toast(x.message, 'danger'); } };
  $('#frmSer').onsubmit = async (e) => { e.preventDefault(); const series = [...document.querySelectorAll('#serRows [data-id]')].map((r) => ({ id: r.dataset.id, serie: r.querySelector('[data-k=serie]').value, correlativo: r.querySelector('[data-k=correlativo]').value }));
    try { await api('config.php', { a: 'series', series }); toast('Series actualizadas'); } catch (x) { toast(x.message, 'danger'); } };

  // ---------- Impresión ----------
  const HELP = {
    navegador: 'Se abre la vista del ticket y el navegador la imprime. Para que salga <b>sin preguntar</b> cada vez, abre Chrome/Edge con la opción <code>--kiosk-printing</code> (clic derecho al acceso directo → Propiedades → Destino: añade <code> --kiosk-printing</code> al final) y deja tu ticketera como impresora predeterminada de Windows. El cajón se abre solo si la impresora lo hace al imprimir.',
    red: 'Para impresoras con conexión de red/WiFi (Epson TM-T20 LAN, Xprinter, etc.). Escribe su IP fija, por ejemplo <code>192.168.1.50</code> (puerto 9100 por defecto). El cajón se conecta a la impresora (cable RJ11) y se abre con un pulso.',
    local: 'Para impresoras USB. En Windows: Panel de control → Dispositivos e impresoras → clic derecho en la ticketera → Propiedades → Compartir (por ejemplo con el nombre <code>TICKET</code>) y aquí escribe <code>\\\\localhost\\TICKET</code>. También sirve un puerto como <code>COM3</code>. En Linux: <code>/dev/usb/lp0</code>.',
  };
  const PLACE = { red: '192.168.1.50:9100', local: '\\\\localhost\\TICKET' };
  function modo() { const m = $('#pMode').value; $('#pDestBox').style.display = m === 'navegador' ? 'none' : ''; $('#pDest').placeholder = PLACE[m] || ''; $('#pDestLbl').textContent = m === 'red' ? 'IP de la impresora (y puerto)' : 'Impresora compartida, puerto o dispositivo'; $('#pHelp').innerHTML = '<i class="bi bi-info-circle me-1"></i>' + HELP[m]; document.querySelectorAll('#btnPrueba,#btnCajonTest').forEach((x) => x.style.display = m === 'navegador' ? 'none' : ''); }
  $('#pMode').addEventListener('change', modo);
  function cargarImp(c) {
    const f = $('#frmImp'); f.print_modo.value = c.print_modo || 'navegador'; f.print_destino.value = c.print_destino || ''; f.print_ancho.value = c.print_ancho || '80'; f.print_codepage.value = c.print_codepage || '19';
    f.print_auto.checked = String(c.print_auto) === '1'; f.print_cajon.checked = String(c.print_cajon) === '1'; f.print_modo.tomselect?.sync(false); modo();
  }
  $('#frmImp').onsubmit = async (e) => { e.preventDefault(); const f = e.target;
    try { await api('config.php', { a: 'print_save', cfg: { print_modo: f.print_modo.value, print_destino: f.print_destino.value, print_ancho: f.print_ancho.value, print_codepage: f.print_codepage.value, print_auto: f.print_auto.checked ? 1 : 0, print_cajon: f.print_cajon.checked ? 1 : 0 } }); toast('Impresión guardada. Recarga las pantallas abiertas para aplicarla.'); } catch (x) { toast(x.message, 'danger'); } };
  async function pruebaImp(a, ok) { try { await api('imprimir.php', { a }); toast(ok, 'success'); } catch (x) { toast(x.message, 'danger'); } }
  $('#btnPrueba').onclick = () => pruebaImp('prueba', 'Prueba enviada a la impresora');
  $('#btnCajonTest').onclick = () => pruebaImp('cajon', 'Pulso enviado al cajón');

  // ---------- Seguridad ----------
  async function loadSeg() {
    const r = await api('config.php?a=seguridad'), IC = { ok: ['check-circle-fill', 'text-success'], warn: ['exclamation-triangle-fill', 'text-warning'], bad: ['x-octagon-fill', 'text-danger'] };
    $('#segList').innerHTML = r.checks.map((c) => `<div class="d-flex gap-3 py-2 border-bottom"><i class="bi bi-${IC[c.estado][0]} ${IC[c.estado][1]} fs-5"></i><div><div class="fw-semibold">${esc(c.titulo)}</div><div class="small text-muted">${esc(c.detalle)}</div></div></div>`).join('');
    $('#frmSeg').sesion_minutos.value = r.sesion_minutos;
  }
  $('#frmSeg').onsubmit = async (e) => { e.preventDefault(); try { await api('config.php', { a: 'seg_guardar', sesion_minutos: e.target.sesion_minutos.value }); toast('Guardado'); } catch (x) { toast(x.message, 'danger'); } };

  load();
})();
