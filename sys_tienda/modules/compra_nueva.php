<?php
$extraJs[] = BASE_URL . '/assets/js/compra_nueva.js?v=' . ASSET_V;
$provs = db()->query('SELECT id, razon_social FROM proveedores WHERE estado=1 ORDER BY razon_social')->fetchAll();
?>
<div class="page-head"><div><h1>Registrar compra</h1><p>Ingresa mercadería: actualiza stock, costo y kardex automáticamente</p></div>
  <a class="btn-soft" href="<?= BASE_URL ?>/modulo.php?m=compras"><i class="bi bi-clock-history"></i> Historial</a></div>
<div class="row g-3">
  <div class="col-xl-8"><div class="cardx">
    <div class="position-relative mb-3"><input class="form-control form-control-lg" id="pq" placeholder="Buscar producto por nombre, código o código de barras…" autocomplete="off" style="border-radius:13px;font-size:.95rem">
      <div id="pres" class="shadow" style="display:none;position:absolute;left:0;right:0;top:100%;margin-top:4px;background:var(--card);border:1px solid var(--line);border-radius:13px;z-index:30;max-height:300px;overflow:auto;padding:.3rem"></div></div>
    <div class="table-responsive"><table class="tbl"><thead><tr><th>Producto</th><th class="r" style="width:120px">Cantidad</th><th class="r" style="width:140px">Costo unit. (<?= e(moneda()) ?>)</th><th class="r">Subtotal</th><th></th></tr></thead><tbody id="lines"></tbody></table></div>
  </div></div>
  <div class="col-xl-4"><div class="cardx">
    <label class="form-label">Proveedor *</label><select class="form-select mb-3" id="prov"><option value="">— Selecciona —</option><?php foreach ($provs as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['razon_social']) ?></option><?php endforeach; ?></select>
    <div class="row g-2 mb-3"><div class="col-7"><label class="form-label">N° comprobante</label><input class="form-control" id="comp" placeholder="F001-0001"></div><div class="col-5"><label class="form-label">Fecha</label><input type="date" class="form-control" id="fecha" value="<?= date('Y-m-d') ?>"></div></div>
    <div class="stat-mini mb-3"><div class="d-flex justify-content-between"><small>Ítems</small><b id="nItems">0</b></div><div class="d-flex justify-content-between align-items-end mt-1"><small>Total compra</small><b class="fs-3 num" id="total" style="color:var(--brand)">S/ 0.00</b></div></div>
    <button class="btn-cobrar" id="btnSave"><i class="bi bi-check2-circle"></i> Registrar compra</button>
  </div></div>
</div>
