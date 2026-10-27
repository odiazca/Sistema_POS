<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Dashboard'; $activeKey = 'dashboard';
$extraJs = [BASE_URL . '/assets/vendor/chartjs/chart.umd.min.js', BASE_URL . '/assets/js/dashboard.js?v=' . APP_VERSION];
require __DIR__ . '/includes/header.php';
$hora = (int)date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p>Resumen en tiempo real · <b><?= e(suc_nombre()) ?></b></p></div>
  <div class="d-flex align-items-center gap-2">
    <span class="live"><i></i> En vivo · <span id="lastUpdate">--:--:--</span></span>
    <button class="icon-btn" id="btnRefresh" title="Actualizar"><i class="bi bi-arrow-clockwise"></i></button>
  </div>
</div>

<!-- Bienvenida + meta + caja -->
<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <div class="hero fade-up h-100 d-flex flex-wrap align-items-center justify-content-between gap-4">
      <div class="goal" style="max-width:520px">
        <h2><?= $saludo ?>, <?= e(explode(' ', $u['nombre'])[0]) ?> 👋</h2>
        <p>Hoy llevas <b id="heroVentas">S/ 0.00</b> en <b id="heroTickets">0</b> ventas. <span id="heroMsg">Cargando tu meta del día…</span></p>
        <div class="d-flex flex-wrap gap-2">
          <?php if (can('pos')): ?><a href="<?= BASE_URL ?>/modulo.php?m=pos" class="btn-w"><i class="bi bi-cart-plus-fill"></i> Abrir punto de venta</a><?php endif; ?>
          <?php if (can('rep-ventas')): ?><a href="<?= BASE_URL ?>/modulo.php?m=rep-ventas" class="btn-g"><i class="bi bi-file-earmark-bar-graph"></i> Ver reportes</a><?php endif; ?>
        </div>
      </div>
      <div class="goal d-flex align-items-center gap-3">
        <div class="goal-ring">
          <svg width="128" height="128" viewBox="0 0 128 128"><circle cx="64" cy="64" r="54" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="12"/>
            <circle id="ringArc" cx="64" cy="64" r="54" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-dasharray="339.3" stroke-dashoffset="339.3" style="transition:stroke-dashoffset 1.4s cubic-bezier(.2,.8,.2,1)"/></svg>
          <div class="v"><b id="ringPct">0%</b><small>de la meta</small></div>
        </div>
        <div><small style="opacity:.8">Meta diaria</small><div class="fw-bold fs-5" id="metaTxt">S/ 0</div><small style="opacity:.8" id="metaFalta"></small></div>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="cardx caja fade-up" id="cajaCard">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div><small class="text-uppercase" style="letter-spacing:.1em;color:#94a3b8;font-size:.68rem">Caja actual</small><div class="big num" id="cajaEsperado">S/ 0.00</div><small style="color:#94a3b8">Efectivo esperado en caja</small></div>
        <span class="pill ok" id="cajaEstado"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Abierta</span>
      </div>
      <div class="row g-2 mt-2">
        <div class="col-4"><div class="mini"><small>Apertura</small><b class="num" id="cajaIni">—</b></div></div>
        <div class="col-4"><div class="mini"><small>Efectivo</small><b class="num" id="cajaEfe">—</b></div></div>
        <div class="col-4"><div class="mini"><small>Digital</small><b class="num" id="cajaDig">—</b></div></div>
      </div>
      <div class="mt-3 small" style="color:#94a3b8"><i class="bi bi-person-badge me-1"></i><span id="cajaUser">—</span> · desde <span id="cajaHora">—</span></div>
    </div>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="kpi-g g-violet fade-up">
    <span class="trend flat" id="tVentas">—</span>
    <div class="val num" id="kVentas"><span class="sk">S/ 0,000</span></div>
    <div class="ttl">Ventas de hoy</div><div class="sub">Ingresos del día en esta sucursal</div>
    <a class="more" href="<?= BASE_URL ?>/modulo.php?m=ventas">Ver ventas <i class="bi bi-arrow-right"></i></a>
    <i class="bi bi-cash-stack bg-ic"></i></div></div>
  <div class="col-6 col-xl-3"><div class="kpi-g g-cyan fade-up" style="animation-delay:0.05s">
    <span class="trend flat" id="tTickets">—</span>
    <div class="val num" id="kTickets"><span class="sk">000</span></div>
    <div class="ttl">Transacciones</div><div class="sub">Comprobantes emitidos hoy</div>
    <a class="more" href="<?= BASE_URL ?>/modulo.php?m=ventas">Ver detalle <i class="bi bi-arrow-right"></i></a>
    <i class="bi bi-receipt bg-ic"></i></div></div>
  <div class="col-6 col-xl-3"><div class="kpi-g g-green fade-up" style="animation-delay:0.1s">
    <span class="trend flat" id="tUtil">—</span>
    <div class="val num" id="kUtil"><span class="sk">S/ 0,000</span></div>
    <div class="ttl">Utilidad bruta de hoy</div><div class="sub">Ticket promedio: <b class="num" id="kProm">—</b></div>
    <a class="more" href="<?= BASE_URL ?>/modulo.php?m=rep-utilidad">Ver utilidades <i class="bi bi-arrow-right"></i></a>
    <i class="bi bi-graph-up-arrow bg-ic"></i></div></div>
  <div class="col-6 col-xl-3"><div class="kpi-g g-orange fade-up" style="animation-delay:0.15s">
    <span class="trend flat" id="tMes">—</span>
    <div class="val num" id="kMes"><span class="sk">S/ 00,000</span></div>
    <div class="ttl">Ventas del mes</div><div class="sub">Mes anterior: <b class="num" id="kMesPrev">—</b></div>
    <a class="more" href="<?= BASE_URL ?>/modulo.php?m=rep-ventas">Ver reportes <i class="bi bi-arrow-right"></i></a>
    <i class="bi bi-calendar2-week bg-ic"></i></div></div>
</div>

<!-- Tendencia + categorías -->
<div class="row g-3 mb-3">
  <div class="col-xl-8"><div class="cardx">
    <div class="cardx-head"><div><h6>Ventas y utilidad</h6><small>Evolución diaria del período</small></div>
      <div class="seg" id="segRango"><button data-r="7" class="on">7 días</button><button data-r="30">30 días</button><button data-r="60">60 días</button></div></div>
    <div class="chart-box"><canvas id="chTend"></canvas></div></div></div>
  <div class="col-xl-4"><div class="cardx">
    <div class="cardx-head"><div><h6>Ventas por categoría</h6><small>Últimos 30 días</small></div></div>
    <div class="chart-box sm" style="height:190px"><canvas id="chCat"></canvas></div>
    <ul class="legend-list mt-2" id="legCat"></ul></div></div>
</div>

<!-- Horas + heatmap + pagos -->
<div class="row g-3 mb-3">
  <div class="col-xl-5"><div class="cardx">
    <div class="cardx-head"><div><h6>Ventas por hora</h6><small>Hoy vs. ayer</small></div></div>
    <div class="chart-box sm"><canvas id="chHoras"></canvas></div></div></div>
  <div class="col-xl-4"><div class="cardx">
    <div class="cardx-head"><div><h6>Horas pico</h6><small>Transacciones por día y hora (60 días)</small></div></div>
    <div class="heat" id="heat"></div>
    <div class="d-flex align-items-center gap-2 mt-3 small text-muted">Menos <span style="display:inline-flex;gap:3px" id="heatLeg"></span> Más</div></div></div>
  <div class="col-xl-3"><div class="cardx">
    <div class="cardx-head"><div><h6>Métodos de pago</h6><small>Últimos 30 días</small></div></div>
    <div id="pagos"></div></div></div>
</div>

<!-- Top, alertas, acciones -->
<div class="row g-3 mb-3">
  <div class="col-xl-4"><div class="cardx">
    <div class="cardx-head"><div><h6>Más vendidos</h6><small>Top 6 · últimos 30 días</small></div><a class="link-more" href="<?= BASE_URL ?>/modulo.php?m=rep-top">Ver todo</a></div>
    <div id="top"></div></div></div>
  <div class="col-xl-4"><div class="cardx">
    <div class="cardx-head"><div><h6>Alertas de inventario</h6><small><span id="bajoTot">0</span> productos bajo el mínimo</small></div><a class="link-more" href="<?= BASE_URL ?>/modulo.php?m=stock-bajo">Gestionar</a></div>
    <div id="bajo"></div><div id="vence"></div></div></div>
  <div class="col-xl-4">
    <div class="cardx mb-3" style="height:auto">
      <div class="cardx-head"><div><h6>Accesos rápidos</h6></div></div>
      <div class="row g-2">
        <?php if (can('pos')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=pos"><i class="ic-indigo bi bi-cart-plus"></i>Nueva venta</a></div><?php endif; ?>
        <?php if (can('productos')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=productos"><i class="ic-teal bi bi-box-seam"></i>Producto</a></div><?php endif; ?>
        <?php if (can('clientes')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=clientes"><i class="ic-amber bi bi-person-plus"></i>Cliente</a></div><?php endif; ?>
        <?php if (can('compras-nueva')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=compras-nueva"><i class="ic-sky bi bi-bag-plus"></i>Compra</a></div><?php endif; ?>
        <?php if (can('gastos')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=gastos"><i class="ic-rose bi bi-wallet2"></i>Gasto</a></div><?php endif; ?>
        <?php if (can('caja')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=caja"><i class="ic-green bi bi-safe2"></i>Caja</a></div><?php endif; ?>
        <?php if (can('cotizaciones')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=cotizaciones"><i class="ic-indigo bi bi-file-earmark-text"></i>Cotizar</a></div><?php endif; ?>
        <?php if (can('devoluciones')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=devoluciones"><i class="ic-amber bi bi-arrow-return-left"></i>Devolución</a></div><?php endif; ?>
        <?php if (can('traspasos')): ?>        <div class="col-4"><a class="qa" href="<?= BASE_URL ?>/modulo.php?m=traspasos"><i class="ic-teal bi bi-arrow-left-right"></i>Traspaso</a></div><?php endif; ?>
      </div>
    </div>
    <div class="cardx" style="height:auto">
      <div class="row text-center g-0">
        <div class="col-6 border-end" style="border-color:var(--line)!important"><div class="muted small">Productos activos</div><div class="fs-4 fw-bold num" id="invProd">—</div></div>
        <div class="col-6"><div class="muted small">Valor del inventario</div><div class="fs-4 fw-bold num" id="invVal">—</div></div>
      </div>
    </div>
  </div>
</div>

<!-- Sucursales y cumpleaños -->
<div class="row g-3 mb-3">
  <?php if (can('sucursales')): ?><div class="col-xl-8"><div class="cardx"><div class="cardx-head"><div><h6>Comparativo por sucursal</h6><small>Ventas netas de hoy y del mes</small></div><?php if (can('rep-sucursales')): ?><a class="link-more" href="<?= BASE_URL ?>/modulo.php?m=rep-sucursales">Consolidado</a><?php endif; ?></div><div id="porSuc"></div></div></div><?php endif; ?>
  <div class="col-xl-<?= can('sucursales') ? '4' : '12' ?>"><div class="cardx"><div class="cardx-head"><div><h6>🎂 Cumpleaños de clientes</h6><small>Hoy y próximos 7 días · reciben descuento en el POS</small></div></div><div id="cumples"></div>
    <?php if (can('cotizaciones')): ?><div class="mt-2 small"><a class="link-more" href="<?= BASE_URL ?>/modulo.php?m=cotizaciones"><i class="bi bi-file-earmark-text"></i> <span id="cotPend">0</span> cotizaciones vigentes</a></div><?php endif; ?></div></div>
</div>

<!-- Ventas recientes -->
<div class="cardx mb-2">
  <div class="cardx-head"><div><h6>Últimas ventas</h6><small>Actividad más reciente del punto de venta</small></div><a class="link-more" href="<?= BASE_URL ?>/modulo.php?m=ventas">Ver historial</a></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Comprobante</th><th>Cliente</th><th>Hora</th><th>Pago</th><th>Estado</th><th class="r">Total</th></tr></thead>
  <tbody id="recientes"><tr><td colspan="6" class="text-center text-muted py-4">Cargando…</td></tr></tbody></table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
