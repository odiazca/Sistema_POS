<?php
$extraJs[] = BASE_URL . '/assets/js/compras.js?v=' . ASSET_V;
$provs = db()->query('SELECT id, razon_social FROM proveedores ORDER BY razon_social')->fetchAll();
?>
<div class="page-head"><div><h1>Historial de compras</h1><p>Mercadería recibida de tus proveedores</p></div><a class="btn-pos" href="<?= BASE_URL ?>/modulo.php?m=compras-nueva"><i class="bi bi-bag-plus"></i> Registrar compra</a></div>
<div class="cardx">
  <div class="toolbar"><div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="Comprobante o proveedor…"></div>
    <select class="form-select w-auto" id="prov"><option value="">Proveedor: todos</option><?php foreach ($provs as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['razon_social']) ?></option><?php endforeach; ?></select>
    <input type="date" class="form-control w-auto" id="desde"><input type="date" class="form-control w-auto" id="hasta">
    <div class="ms-auto stat-mini"><small>Total comprado</small><b id="sum">—</b></div></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>#</th><th>Fecha</th><th>Comprobante</th><th>Proveedor</th><th class="r">Ítems</th><th>Estado</th><th class="r">Total</th><th></th></tr></thead><tbody id="tb"></tbody></table></div><div class="pager" id="pager"></div></div>
<div class="modal fade" id="mdlC" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="cBody"></div></div></div></div>
