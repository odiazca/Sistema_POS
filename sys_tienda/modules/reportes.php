<?php
$extraJs = [BASE_URL . '/assets/vendor/chartjs/chart.umd.min.js', BASE_URL . '/assets/js/reportes.js?v=' . ASSET_V];
$rep = ['rep-ventas' => 'ventas', 'rep-utilidad' => 'utilidad', 'rep-top' => 'top', 'rep-cajero' => 'cajero', 'rep-inventario' => 'inventario', 'rep-sucursales' => 'sucursales'][$slug];
?>
<div class="page-head"><div><h1 id="rTitle"><?= e($titulo) ?></h1><p>Analiza el rendimiento de tu negocio y exporta los datos</p></div>
  <div class="d-flex gap-2"><button class="btn-soft" id="btnCsv"><i class="bi bi-download"></i> Exportar CSV</button><button class="btn-soft" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div></div>
<div class="cardx mb-3" id="filtros"><div class="toolbar mb-0">
  <div class="seg" id="presets"><button data-p="hoy">Hoy</button><button data-p="7">7 días</button><button data-p="30">30 días</button><button data-p="mes" class="on">Este mes</button><button data-p="prev">Mes anterior</button></div>
  <input type="date" class="form-control w-auto" id="desde"><span class="text-muted">a</span><input type="date" class="form-control w-auto" id="hasta"></div></div>
<div class="row g-3 mb-3" id="sum"></div>
<div class="row g-3"><div class="col-xl-5"><div class="cardx"><div class="cardx-head"><div><h6>Gráfico</h6></div></div><div class="chart-box"><canvas id="ch"></canvas></div></div></div>
  <div class="col-xl-7"><div class="cardx"><div class="cardx-head"><div><h6>Detalle</h6><small id="nRows"></small></div></div><div class="table-responsive" style="max-height:420px;overflow:auto"><table class="tbl"><thead id="th"></thead><tbody id="tb"></tbody></table></div></div></div></div>
<script>window.REP = '<?= $rep ?>';</script>
