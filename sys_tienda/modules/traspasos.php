<?php $extraJs[] = BASE_URL . '/assets/js/picker.js?v=' . ASSET_V; $extraJs[] = BASE_URL . '/assets/js/traspasos.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Traspasos entre sucursales</h1><p>Mueve mercadería de <b><?= e(suc_nombre()) ?></b> a otra sucursal: descuenta aquí y suma allá, con kardex en ambas</p></div>
  <button class="btn-pos" id="btnNew"><i class="bi bi-arrow-left-right"></i> Nuevo traspaso</button></div>
<?php if (suc() === 0): ?><div class="alert alert-info border-0" style="border-radius:14px"><i class="bi bi-info-circle me-2"></i>Estás viendo <b>todas las sucursales</b>. Para registrar un traspaso elige la sucursal de origen en el selector superior.</div><?php endif; ?>
<div class="cardx"><div class="table-responsive"><table class="tbl"><thead><tr><th>#</th><th>Fecha</th><th>Origen</th><th></th><th>Destino</th><th class="r">Productos</th><th class="r">Unidades</th><th>Registró</th><th></th></tr></thead><tbody id="tb"></tbody></table></div></div>

<div class="modal fade" id="mdlT" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <div class="d-flex justify-content-between mb-3"><h5 class="fw-bold m-0"><i class="bi bi-arrow-left-right me-2"></i>Nuevo traspaso</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="row g-3 mb-3"><div class="col-md-5"><label class="form-label">Sale de</label><input class="form-control" disabled value="<?= e(suc_nombre()) ?>"></div>
    <div class="col-md-7"><label class="form-label">Destino *</label><select class="form-select" id="dest"></select></div></div>
  <div class="position-relative mb-2"><input class="form-control" id="pq" placeholder="Buscar producto por nombre o código…" autocomplete="off"><div class="pick-drop" id="pres"></div></div>
  <div class="table-responsive" style="max-height:260px;overflow:auto"><table class="tbl"><thead><tr><th>Producto</th><th class="r" style="width:120px">Cantidad</th><th></th></tr></thead><tbody id="lines"></tbody></table></div>
  <input class="form-control mt-3" id="nota" maxlength="150" placeholder="Nota (opcional): ej. reposición semanal">
  <div class="d-flex gap-2 mt-3"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-grow-1 justify-content-center" id="btnSave"><i class="bi bi-check2-circle"></i> Confirmar traspaso</button></div>
</div></div></div></div>
<div class="modal fade" id="mdlD" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="dBody"></div></div></div></div>
