<?php
$extraJs[] = BASE_URL . '/assets/js/picker.js?v=' . ASSET_V; $extraJs[] = BASE_URL . '/assets/js/pricing.js?v=' . ASSET_V; $extraJs[] = BASE_URL . '/assets/js/cotizaciones.js?v=' . ASSET_V;
?>
<script>window.COT_EMPRESA=<?= json_encode(cfg('empresa_nombre')) ?>;</script>
<div class="page-head"><div><h1>Cotizaciones</h1><p>Presupuestos para tus clientes con vigencia; conviértelos en venta con un clic</p></div>
  <button class="btn-pos" id="btnNew"><i class="bi bi-file-earmark-plus"></i> Nueva cotización</button></div>
<div class="cardx"><div class="toolbar"><div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="Cliente o N° de cotización…"></div>
  <select class="form-select w-auto" id="estado"><option value="">Estado: todos</option><option value="PENDIENTE">Vigentes</option><option value="VENCIDA">Vencidas</option><option value="ACEPTADA">Convertidas en venta</option><option value="ANULADA">Anuladas</option></select></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Vigencia</th><th>Estado</th><th class="r">Total</th><th></th></tr></thead><tbody id="tb"></tbody></table></div></div>

<div class="modal fade" id="mdlN" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <div class="d-flex justify-content-between mb-3"><h5 class="fw-bold m-0"><i class="bi bi-file-earmark-text me-2"></i>Nueva cotización</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="row g-3 mb-3">
    <div class="col-md-7 position-relative"><label class="form-label">Cliente</label><input class="form-control" id="cliq" placeholder="Buscar cliente… (vacío = Cliente Varios)" autocomplete="off"><div class="pick-drop" id="clires"></div><small class="text-muted" id="cliSel">Cliente Varios</small></div>
    <div class="col-md-3"><label class="form-label">Vigencia (días)</label><input type="number" min="1" max="90" class="form-control" id="dias" value="7"></div></div>
  <div class="position-relative mb-2"><input class="form-control" id="pq" placeholder="Buscar producto por nombre o código…" autocomplete="off"><div class="pick-drop" id="pres"></div></div>
  <div class="table-responsive" style="max-height:250px;overflow:auto"><table class="tbl"><thead><tr><th>Producto</th><th class="r" style="width:110px">Cant.</th><th class="r">P. unit.</th><th class="r">Subtotal</th><th></th></tr></thead><tbody id="lines"></tbody></table></div>
  <div class="d-flex justify-content-between align-items-center mt-3"><input class="form-control" style="max-width:62%" id="nota" maxlength="200" placeholder="Nota o condiciones (opcional)"><div class="fs-4 fw-bold num" id="total">S/ 0.00</div></div>
  <small class="text-muted">Incluye precio por mayor y promociones vigentes. Los precios pueden cambiar si la cotización vence.</small>
  <div class="d-flex gap-2 mt-3"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-grow-1 justify-content-center" id="btnSave"><i class="bi bi-check2-circle"></i> Guardar cotización</button></div>
</div></div></div></div>
<div class="modal fade" id="mdlD" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="dBody"></div></div></div></div>
