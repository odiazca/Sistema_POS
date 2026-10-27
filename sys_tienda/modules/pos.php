<?php
$extraJs[] = BASE_URL . '/assets/js/pos.js?v=' . ASSET_V;
$igv = cfg('igv', '18');
?>
<script>window.POS_IGV=<?= (float)$igv ?>;window.POS_EMPRESA=<?= json_encode(cfg('empresa_nombre')) ?>;window.POS_COT=<?= (int)($_GET['cot'] ?? 0) ?>;</script>
<div class="pos" id="pos">
  <!-- Catálogo -->
  <section class="cardx pos-left">
    <div class="d-flex gap-2 mb-3">
      <div class="search-in position-relative flex-grow-1">
        <i class="bi bi-upc-scan" style="position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:var(--muted)"></i>
        <input type="text" id="pSearch" class="form-control form-control-lg" style="padding-left:2.6rem;border-radius:13px;font-size:.95rem" placeholder="Escanea código de barras o busca por nombre…  (F3)" autocomplete="off" autofocus>
      </div>
      <button class="btn-soft" id="btnHold" title="Ventas en espera"><i class="bi bi-pause-circle"></i> En espera <span class="pill info" id="holdCount">0</span></button>
    </div>
    <div class="chips" id="chips"></div>
    <div class="prod-grid" id="grid"></div>
  </section>

  <!-- Carrito -->
  <button type="button" class="cart-bar" id="cartBar"><span><i class="bi bi-cart3 me-2"></i><b id="cbCount">0</b> productos</span><span class="cb-tot" id="cbTotal">—</span><span class="cb-go">Ver venta <i class="bi bi-chevron-up"></i></span></button>
  <aside class="cardx pos-right">
    <button type="button" class="cart-back" id="cartBack"><i class="bi bi-chevron-down"></i> Seguir agregando productos</button>
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="m-0 fw-bold"><i class="bi bi-cart3 me-2"></i>Venta actual <span class="pill neutral" id="cartCount">0</span></h6>
      <button class="btn-soft danger" id="btnClear" title="Vaciar (F4)"><i class="bi bi-trash3"></i></button>
    </div>
    <div class="cli-box" id="cliBox"><div class="avatar" style="width:34px;height:34px;font-size:.75rem"><i class="bi bi-person"></i></div>
      <div class="flex-grow-1" style="min-width:0"><div class="fw-semibold small text-truncate" id="cliName">Cliente Varios</div><div class="text-muted" style="font-size:.72rem" id="cliDoc">Venta al público</div></div>
      <i class="bi bi-chevron-expand text-muted"></i></div>
    <div class="seg w-100 my-2" id="segTipo"><button data-t="TICKET" class="on flex-fill">Ticket</button><button data-t="BOLETA" class="flex-fill">Boleta</button><button data-t="FACTURA" class="flex-fill">Factura</button></div>

    <div class="cart" id="cart"></div>

    <div class="totals">
      <div id="extraRows" class="small"></div>
      <div id="ptsRow" class="d-flex justify-content-between align-items-center small mb-1" style="display:none!important">
        <span><i class="bi bi-stars text-warning"></i> Puntos <b id="ptsDisp">0</b> <span class="text-muted">(1 pt = <?= e(moneda()) ?> <?= e(cfg('puntos_valor','0.10')) ?>)</span></span>
        <span class="d-flex align-items-center gap-1">Canjear <input type="number" min="0" step="1" id="canje" class="form-control form-control-sm text-end" style="width:70px;padding:.15rem .4rem" value="0"><button class="btn-soft" id="canjeMax" style="padding:.1rem .45rem;font-size:.7rem">Máx</button></span></div>
      <div class="d-flex justify-content-between align-items-center small text-muted"><span>Sub. <b id="tSub" class="num">0.00</b> · <?= e(impuesto()) ?> <?= e($igv) ?>% <b id="tIgv" class="num">0.00</b></span>
        <span class="d-flex align-items-center gap-1">Desc. <?= e(moneda()) ?><input type="number" min="0" step="0.10" id="desc" class="form-control form-control-sm text-end" style="width:74px;padding:.15rem .4rem" value="0"></span></div>
      <div class="d-flex justify-content-between align-items-center"><span class="fw-bold">TOTAL</span><span class="total-big num" id="tTotal">S/ 0.00</span></div>
    </div>

    <div class="pay-grid" id="payGrid">
      <button data-m="EFECTIVO" class="on"><i class="bi bi-cash-coin"></i>Efectivo</button>
      <button data-m="YAPE"><i class="bi bi-phone"></i>Yape</button>
      <button data-m="PLIN"><i class="bi bi-phone-vibrate"></i>Plin</button>
      <button data-m="TARJETA"><i class="bi bi-credit-card-2-front"></i>Tarjeta</button>
      <button data-m="TRANSFERENCIA"><i class="bi bi-bank"></i>Transf.</button>
      <button data-m="CREDITO"><i class="bi bi-journal-text"></i>Crédito</button>
    </div>
    <div id="cashBox" class="mb-2">
      <div class="d-flex gap-2 align-items-center">
        <div class="flex-grow-1"><input type="number" step="0.10" min="0" id="recibido" class="form-control" placeholder="Monto recibido"></div>
        <div class="text-end" style="min-width:110px"><small class="text-muted d-block" style="font-size:.7rem">Vuelto</small><b class="num" id="vuelto">S/ 0.00</b></div>
      </div>
      <div class="d-flex gap-1 mt-2 flex-wrap" id="quick"></div>
    </div>
    <button class="btn-cobrar" id="btnCobrar"><i class="bi bi-check2-circle"></i> Cobrar <kbd>F9</kbd></button>
  </aside>

  <!-- Caja cerrada -->
  <div class="pos-lock" id="lock" style="display:none"><div class="cardx text-center" style="max-width:380px">
    <div class="kpi-ic ic-amber mx-auto mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-safe2"></i></div>
    <h5 class="fw-bold">Abre tu caja para vender</h5><p class="text-muted small">Ingresa el efectivo inicial con el que empiezas el turno.</p>
    <input type="number" step="0.10" min="0" class="form-control form-control-lg text-center mb-3" id="apertura" placeholder="Monto inicial (<?= e(moneda()) ?>)" value="100">
    <button class="btn-pos w-100 justify-content-center" id="btnAbrir"><i class="bi bi-unlock"></i> Abrir caja</button></div></div>
</div>

<!-- Modal clientes -->
<div class="modal fade" id="mdlCli" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <div class="d-flex justify-content-between mb-3"><h6 class="fw-bold m-0">Seleccionar cliente</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <input type="text" class="form-control mb-2" id="cliQ" placeholder="Buscar por nombre o documento…" autocomplete="off">
  <div id="cliList" style="max-height:260px;overflow:auto"></div>
  <hr><div class="fw-semibold small mb-2">Nuevo cliente rápido</div>
  <div class="row g-2"><div class="col-4"><select class="form-select form-select-sm" id="ncTipo"><option>DNI</option><option>RUC</option><option>CE</option></select></div><div class="col-8"><input class="form-control form-control-sm" id="ncDoc" placeholder="N° documento"></div>
  <div class="col-12"><input class="form-control form-control-sm" id="ncNombre" placeholder="Nombre / Razón social"></div><div class="col-12"><button class="btn-soft w-100 justify-content-center" id="ncSave"><i class="bi bi-person-plus"></i> Guardar y usar</button></div></div>
</div></div></div></div>

<!-- Modal espera -->
<div class="modal fade" id="mdlHold" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4">
  <div class="d-flex justify-content-between mb-3"><h6 class="fw-bold m-0">Ventas en espera</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div id="holdList"></div></div></div></div></div>

<!-- Modal éxito -->
<div class="modal fade" id="mdlOk" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 text-center" style="border-radius:22px"><div class="modal-body p-4">
  <div class="ok-ring mx-auto mb-3"><i class="bi bi-check-lg"></i></div><h5 class="fw-bold mb-0">¡Venta registrada!</h5><div class="text-muted small mb-2" id="okNro"></div>
  <div class="fs-3 fw-bold num" id="okTotal"></div><div class="small text-muted mb-1" id="okVuelto"></div><div class="small mb-3" id="okPts"></div>
  <div class="d-grid gap-2"><button class="btn-pos justify-content-center" id="okPrint"><i class="bi bi-printer"></i> Imprimir ticket</button><button class="btn justify-content-center text-white" style="background:#25d366;font-weight:600" id="okWa"><i class="bi bi-whatsapp me-1"></i> Enviar por WhatsApp</button><button class="btn btn-light" id="okNew">Nueva venta <kbd>Enter</kbd></button></div></div></div></div></div>
<script>
(() => { const pos = document.getElementById('pos'), $ = (i) => document.getElementById(i);
  const sync = () => { const a = $('cartCount').textContent, b = $('tTotal').textContent; if ($('cbCount').textContent !== a) $('cbCount').textContent = a; if ($('cbTotal').textContent !== b) $('cbTotal').textContent = b; };
  ['cartCount', 'tTotal'].forEach((id) => new MutationObserver(sync).observe($(id), { subtree: true, childList: true, characterData: true })); sync();
  $('cartBar').onclick = () => { pos.classList.add('cart-open'); document.body.classList.add('cart-lock'); };
  $('cartBack').onclick = () => { pos.classList.remove('cart-open'); document.body.classList.remove('cart-lock'); };
})();
</script>
