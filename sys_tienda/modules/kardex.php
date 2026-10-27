<?php $extraJs[] = BASE_URL . '/assets/js/kardex.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Kardex y ajustes</h1><p>Historial de movimientos de inventario y correcciones de stock</p></div>
  <div class="d-flex gap-2"><button class="btn-soft" id="btnCsv"><i class="bi bi-download"></i> CSV</button><button class="btn-pos" id="btnAj"><i class="bi bi-sliders"></i> Nuevo ajuste</button></div></div>
<div class="cardx">
  <div class="toolbar">
    <div class="search-in position-relative"><i class="bi bi-search"></i><input class="form-control" id="pq" placeholder="Filtrar por producto…" autocomplete="off"><div id="pres" class="shadow" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:var(--card);border:1px solid var(--line);border-radius:13px;z-index:30;padding:.3rem"></div></div>
    <span class="pill info" id="pchip" style="display:none"></span>
    <select class="form-select w-auto" id="tipo"><option value="">Tipo: todos</option><option value="ENTRADA">Entradas</option><option value="SALIDA">Salidas</option><option value="AJUSTE">Ajustes</option></select>
    <input type="date" class="form-control w-auto" id="desde"><input type="date" class="form-control w-auto" id="hasta"></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th class="r">Cantidad</th><th class="r">Stock resultante</th><th>Motivo / Documento</th><th>Usuario</th></tr></thead><tbody id="tb"></tbody></table></div><div class="pager" id="pager"></div></div>

<div class="modal fade" id="mdlA" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><form id="frmA"><div class="modal-body p-4">
  <h5 class="fw-bold mb-3"><i class="bi bi-sliders me-2"></i>Ajuste de inventario</h5>
  <label class="form-label">Producto</label><div class="position-relative mb-3"><input class="form-control" id="aq" placeholder="Buscar producto…" autocomplete="off"><div id="ares" class="shadow" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:var(--card);border:1px solid var(--line);border-radius:13px;z-index:30;padding:.3rem"></div></div>
  <div class="stat-mini mb-3" id="aInfo" style="display:none"></div>
  <div class="seg w-100 mb-3" id="aModo"><button type="button" data-m="CONTEO" class="on flex-fill">Conteo físico</button><button type="button" data-m="ENTRADA" class="flex-fill">Entrada</button><button type="button" data-m="SALIDA" class="flex-fill">Salida / merma</button></div>
  <label class="form-label" id="aLbl">Stock real contado</label><input type="number" min="0" step="any" class="form-control mb-3" id="aCant" required>
  <label class="form-label">Motivo *</label><input class="form-control mb-3" id="aMot" maxlength="140" placeholder="Ej. Producto vencido, rotura, conteo mensual" required>
  <div class="d-flex gap-2"><button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center">Aplicar ajuste</button></div></div></form></div></div></div>
