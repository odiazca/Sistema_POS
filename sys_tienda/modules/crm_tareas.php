<?php
$extraJs[] = BASE_URL . '/assets/js/crm_common.js?v=' . ASSET_V;
$extraJs[] = BASE_URL . '/assets/js/crm_tareas.js?v=' . ASSET_V;
?>
<div class="page-head"><div><h1>Tareas y seguimiento</h1><p>Llamadas, visitas y cobranzas pendientes con tus clientes</p></div><button class="btn-pos" id="bNew"><i class="bi bi-plus-lg"></i><span>Nueva tarea</span></button></div>
<div class="row g-3 mb-3" id="kpis"></div>
<div class="cardx"><div class="toolbar"><div class="chips" id="fest"><button class="chip-f on" data-v="PENDIENTE">Pendientes</button><button class="chip-f" data-v="HECHA">Completadas</button><button class="chip-f" data-v="todas">Todas</button></div>
  <?php if (usuario()['rol_id'] === 1): ?><select class="form-select ms-auto" id="quien" style="max-width:220px"><option value="">Todo el equipo</option><option value="mias">Solo mis tareas</option></select><?php endif; ?></div>
  <div id="lista"></div></div>