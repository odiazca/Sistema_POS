<?php $extraJs[] = BASE_URL . '/assets/js/caja.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Caja</h1><p>Apertura, movimientos de efectivo, arqueo y cierre de turno</p></div><div class="d-flex gap-2" id="acts"></div></div>
<div id="closed" style="display:none"><div class="cardx text-center mx-auto" style="max-width:420px">
  <div class="kpi-ic ic-amber mx-auto mb-3" style="width:68px;height:68px;font-size:2rem"><i class="bi bi-safe2"></i></div>
  <h5 class="fw-bold">No tienes una caja abierta</h5><p class="text-muted small">Abre tu caja con el efectivo inicial para comenzar a vender.</p>
  <input type="number" step="0.10" min="0" class="form-control form-control-lg text-center mb-3" id="apertura" value="100"><button class="btn-pos w-100 justify-content-center" id="btnAbrir"><i class="bi bi-unlock"></i> Abrir caja</button></div></div>
<div id="open" style="display:none">
  <div class="row g-3 mb-3" id="kpis"></div>
  <div class="row g-3 mb-3">
    <div class="col-xl-4"><div class="cardx"><div class="cardx-head"><div><h6>Ventas por método</h6><small id="turno"></small></div></div><div id="metodos"></div></div></div>
    <div class="col-xl-8"><div class="cardx"><div class="cardx-head"><div><h6>Ingresos y egresos de efectivo</h6><small>Movimientos manuales de este turno</small></div></div>
      <div class="table-responsive"><table class="tbl"><thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th class="r">Monto</th></tr></thead><tbody id="movs"></tbody></table></div></div></div>
  </div>
</div>
<div class="cardx"><div class="cardx-head"><div><h6>Historial de cajas</h6><small>Últimos turnos cerrados y abiertos</small></div></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>#</th><th>Cajero</th><th>Apertura</th><th>Cierre</th><th class="r">Ventas</th><th class="r">Esperado</th><th class="r">Contado</th><th class="r">Diferencia</th><th>Estado</th><th></th></tr></thead><tbody id="hist"></tbody></table></div></div>

<div class="modal fade" id="mdlMov" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0" style="border-radius:20px"><form id="frmMov"><div class="modal-body p-4">
  <h6 class="fw-bold mb-3" id="movTitle"></h6><label class="form-label">Concepto</label><input class="form-control mb-3" id="movCon" required maxlength="150" placeholder="Ej. Pago a proveedor de pan">
  <label class="form-label">Monto (<?= e(moneda()) ?>)</label><input type="number" step="0.10" min="0.1" class="form-control mb-3" id="movMonto" required>
  <div class="d-flex gap-2"><button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center">Registrar</button></div></div></form></div></div></div>
<div class="modal fade" id="mdlCerrar" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><form id="frmCerrar"><div class="modal-body p-4">
  <h5 class="fw-bold mb-3"><i class="bi bi-lock me-2"></i>Cerrar caja y arqueo</h5>
  <div class="stat-mini mb-3 d-flex justify-content-between align-items-center"><div><small>Efectivo esperado en caja</small><b class="fs-4 num" id="cEsp">—</b></div><i class="bi bi-calculator fs-2 text-muted"></i></div>
  <label class="form-label">Efectivo contado (<?= e(moneda()) ?>)</label><input type="number" step="0.10" min="0" class="form-control form-control-lg mb-2" id="cContado" required>
  <div id="cDif" class="small fw-semibold mb-3"></div><label class="form-label">Observación (opcional)</label><input class="form-control mb-3" id="cObs" maxlength="200">
  <div class="d-flex gap-2"><button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center" style="background:linear-gradient(135deg,#f43f5e,#e11d48)">Cerrar caja</button></div></div></form></div></div></div>
