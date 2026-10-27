<?php
$extraJs[] = BASE_URL . '/assets/js/crm_common.js?v=' . ASSET_V;
$extraJs[] = BASE_URL . '/assets/js/crm_pipeline.js?v=' . ASSET_V;
?>
<div class="page-head"><div><h1>Pipeline de ventas</h1><p>Pedidos por mayor, clientes por convertir y negocios en curso. Arrastra las tarjetas entre etapas.</p></div>
  <button class="btn-pos" id="bNew"><i class="bi bi-plus-lg"></i><span>Nueva oportunidad</span></button></div>
<div class="row g-3 mb-3" id="kpis"></div><div class="kb-wrap" id="kb"></div>
<div class="modal fade" id="mdlMotivo" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <h6 class="fw-bold mb-1">¿Por qué se perdió?</h6><p class="small text-muted">Registrar el motivo te ayuda a mejorar tu oferta.</p>
  <select class="form-select mb-2" id="motSel"><option>Precio más alto que la competencia</option><option>Compró en otro lugar</option><option>No respondió</option><option>Sin stock / no cumplimos</option><option>Ya no lo necesita</option></select>
  <input class="form-control mb-3" id="motTxt" maxlength="120" placeholder="Detalle (opcional)"><div class="d-flex gap-2"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center" id="motOk">Confirmar</button></div></div></div></div></div>