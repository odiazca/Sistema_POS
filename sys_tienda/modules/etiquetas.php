<?php $extraJs[] = BASE_URL . '/assets/vendor/jsbarcode/JsBarcode.all.min.js'; $extraJs[] = BASE_URL . '/assets/js/picker.js?v=' . ASSET_V; $extraJs[] = BASE_URL . '/assets/js/etiquetas.js?v=' . ASSET_V; ?>
<script>window.ETQ_EMPRESA=<?= json_encode(cfg('empresa_nombre')) ?>;</script>
<div class="page-head"><div><h1>Etiquetas de código de barras</h1><p>Imprime etiquetas con nombre, precio y código de barras para tus productos</p></div><button class="btn-pos" id="btnPrint"><i class="bi bi-printer"></i> Imprimir etiquetas</button></div>
<div class="row g-3">
  <div class="col-xl-4 no-print"><div class="cardx">
    <div class="position-relative mb-3"><input class="form-control" id="pq" placeholder="Buscar producto por nombre o código…" autocomplete="off"><div class="pick-drop" id="pres"></div></div>
    <div id="list"></div>
    <hr><div class="row g-2">
      <div class="col-6"><label class="form-label small">Tamaño</label><select class="form-select form-select-sm" id="size"><option value="s">Pequeña (50×25 mm)</option><option value="m" selected>Mediana (60×35 mm)</option><option value="l">Grande (80×45 mm)</option></select></div>
      <div class="col-6"><label class="form-label small">Mostrar</label><select class="form-select form-select-sm" id="show"><option value="all" selected>Nombre + precio</option><option value="name">Solo nombre</option><option value="none">Solo código</option></select></div></div>
    <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Si el producto no tiene código de barras se usa su código interno. Códigos de 13 dígitos se imprimen como EAN-13; el resto como CODE-128.</div>
  </div></div>
  <div class="col-xl-8"><div class="cardx"><div class="cardx-head no-print"><div><h6>Vista previa</h6><small id="nEtq">0 etiquetas</small></div></div><div class="labels" id="sheet"></div></div></div>
</div>
