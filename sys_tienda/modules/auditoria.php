<?php $extraJs[] = BASE_URL . '/assets/js/auditoria.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Auditoría del sistema</h1><p>Bitácora de acciones: quién hizo qué, cuándo y desde dónde</p></div><button class="btn-soft" id="btnCsv"><i class="bi bi-download"></i> Exportar CSV</button></div>
<div class="cardx"><div class="toolbar">
  <div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="Buscar en el detalle…"></div>
  <select class="form-select w-auto" id="accion"><option value="">Acción: todas</option></select>
  <select class="form-select w-auto" id="usuario"><option value="">Usuario: todos</option></select>
  <input type="date" class="form-control w-auto" id="desde"><input type="date" class="form-control w-auto" id="hasta"></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>Sucursal</th><th>IP</th></tr></thead><tbody id="tb"></tbody></table></div><div class="pager" id="pager"></div></div>
