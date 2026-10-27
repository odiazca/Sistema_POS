<?php
$extraJs[] = BASE_URL . '/assets/vendor/chartjs/chart.umd.min.js';
$extraJs[] = BASE_URL . '/assets/js/crm_common.js?v=' . ASSET_V;
$extraJs[] = BASE_URL . '/assets/js/crm_panel.js?v=' . ASSET_V;
?>
<div class="page-head"><div><h1>CRM</h1><p>Salud de tu cartera de clientes, oportunidades y acciones del día</p></div>
  <div class="d-flex flex-wrap gap-2"><button class="btn-soft" id="bTarea"><i class="bi bi-check2-square"></i> Nueva tarea</button><button class="btn-soft" id="bOpp"><i class="bi bi-bullseye"></i> Nueva oportunidad</button>
  <a class="btn-pos" href="<?= BASE_URL ?>/modulo.php?m=crm-campanas&nueva=1"><i class="bi bi-megaphone-fill"></i><span>Nueva campaña</span></a></div></div>
<div id="crmRoot"><div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Cargando…</div></div>