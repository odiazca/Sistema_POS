<?php $extraJs[] = BASE_URL . '/assets/js/ventas.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Historial de ventas</h1><p>Consulta, reimprime y anula comprobantes</p></div>
  <div class="d-flex gap-2"><button class="btn-soft" id="btnCsv"><i class="bi bi-download"></i> Exportar CSV</button><a class="btn-pos" href="<?= BASE_URL ?>/modulo.php?m=pos"><i class="bi bi-cart-plus"></i> Nueva venta</a></div></div>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="cardx"><div class="muted small">Ventas del filtro</div><div class="fs-3 fw-800 num" id="sTotal">—</div></div></div>
  <div class="col-md-4"><div class="cardx"><div class="muted small">Comprobantes emitidos</div><div class="fs-3 fw-800 num" id="sN">—</div></div></div>
  <div class="col-md-4"><div class="cardx"><div class="muted small">Anulados</div><div class="fs-3 fw-800 num" id="sAn">—</div></div></div>
</div>
<div class="cardx">
  <div class="toolbar">
    <div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="N° comprobante o cliente…" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <input type="date" class="form-control w-auto" id="desde" value="<?= date('Y-m-d', strtotime('-7 days')) ?>"><input type="date" class="form-control w-auto" id="hasta" value="<?= date('Y-m-d') ?>">
    <select class="form-select w-auto" id="estado"><option value="">Estado: todos</option><option>COMPLETADA</option><option>ANULADA</option></select>
    <select class="form-select w-auto" id="metodo"><option value="">Pago: todos</option><?php foreach (['EFECTIVO', 'YAPE', 'PLIN', 'TARJETA', 'TRANSFERENCIA', 'CREDITO'] as $m): ?><option><?= $m ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" id="tipo"><option value="">Tipo: todos</option><option>TICKET</option><option>BOLETA</option><option>FACTURA</option></select>
  </div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Comprobante</th><th>Fecha</th><th>Cliente</th><th>Cajero</th><th>Pago</th><th>Estado</th><th class="r">Total</th><th class="r"></th></tr></thead><tbody id="tb"></tbody></table></div>
  <div class="pager" id="pager"></div>
</div>
<div class="modal fade" id="mdlV" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="vBody"></div></div></div></div>
