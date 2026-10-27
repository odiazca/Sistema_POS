<?php $extraJs[] = BASE_URL . '/assets/js/creditos.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Créditos y cobranzas</h1><p>Clientes con deuda pendiente y registro de pagos</p></div></div>
<div class="row g-3 mb-3" id="stats"></div>
<div class="cardx"><div class="toolbar"><div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="Buscar cliente…"></div>
  <div class="form-check form-switch ms-2"><input class="form-check-input" type="checkbox" id="solo" checked><label class="form-check-label small" for="solo">Solo con deuda</label></div></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Cliente</th><th>Documento</th><th>Teléfono</th><th class="r">Línea de crédito</th><th class="r">Deuda</th><th style="width:170px">Uso</th><th></th></tr></thead><tbody id="tb"></tbody></table></div></div>
<div class="modal fade" id="mdlP" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><form id="frmP"><div class="modal-body p-4" id="pBody"></div></form></div></div></div>
