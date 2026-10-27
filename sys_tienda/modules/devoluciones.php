<?php $extraJs[] = BASE_URL . '/assets/js/devoluciones.js?v=' . ASSET_V; ?>
<script>window.DEV_VENTA = <?= (int)($_GET['venta'] ?? 0) ?>;</script>
<div class="page-head"><div><h1>Devoluciones</h1><p>Devuelve productos de una venta: reintegra stock y reembolsa en efectivo, mismo medio o descontando crédito</p></div>
  <button class="btn-pos" id="btnNew"><i class="bi bi-arrow-return-left"></i> Nueva devolución</button></div>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="cardx"><div class="muted small">Devuelto este mes</div><div class="fs-3 fw-800 num" id="sMes">—</div></div></div></div>
<div class="cardx"><div class="table-responsive"><table class="tbl"><thead><tr><th>#</th><th>Fecha</th><th>Venta</th><th>Motivo</th><th>Reembolso</th><th>Stock</th><th>Registró</th><th class="r">Total</th><th></th></tr></thead><tbody id="tb"></tbody></table></div></div>

<div class="modal fade" id="mdlN" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <div class="d-flex justify-content-between mb-3"><h5 class="fw-bold m-0"><i class="bi bi-arrow-return-left me-2"></i>Nueva devolución</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div id="stepBuscar"><div class="position-relative mb-2"><input class="form-control form-control-lg" id="vq" placeholder="Número de comprobante o nombre del cliente… (ej. T001-000123)" autocomplete="off"></div><div id="vres" style="max-height:300px;overflow:auto"></div></div>
  <div id="stepItems" style="display:none">
    <div class="d-flex justify-content-between align-items-start mb-2"><div><b id="vNro"></b> <span class="text-muted small" id="vInfo"></span></div><button class="btn-soft" id="vBack"><i class="bi bi-arrow-left"></i> Otra venta</button></div>
    <div class="table-responsive"><table class="tbl"><thead><tr><th>Producto</th><th class="r">Vendido</th><th class="r">Ya devuelto</th><th class="r">P. neto</th><th class="r" style="width:130px">Cant. a devolver</th></tr></thead><tbody id="vItems"></tbody></table></div>
    <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Motivo *</label><input class="form-control" id="motivo" maxlength="200" placeholder="Ej. producto en mal estado, se equivocó de producto…"></div>
      <div class="col-md-3"><label class="form-label">Reembolso</label><select class="form-select" id="reemb"><option value="EFECTIVO">Efectivo (sale de caja)</option><option value="MISMO_MEDIO">Mismo medio de pago</option></select></div>
      <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="reint" checked><label class="form-check-label small" for="reint">Reintegrar al stock</label></div></div></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><div class="text-muted small" id="credNote"></div><div class="fs-4 fw-bold num">Reembolso: <span id="vTot">S/ 0.00</span></div></div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-grow-1 justify-content-center" id="btnSave"><i class="bi bi-check2-circle"></i> Registrar devolución</button></div>
  </div>
</div></div></div></div>
<div class="modal fade" id="mdlD" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="dBody"></div></div></div></div>
