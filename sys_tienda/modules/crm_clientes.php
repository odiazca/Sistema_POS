<?php
$extraJs[] = BASE_URL . '/assets/js/crm_common.js?v=' . ASSET_V;
$extraJs[] = BASE_URL . '/assets/js/crm_clientes.js?v=' . ASSET_V;
?>
<div class="page-head"><div><h1>Clientes 360°</h1><p>Toda la información de cada cliente: compras, interacciones, tareas y su segmento</p></div>
  <div class="d-flex flex-wrap gap-2"><?php if (usuario()['rol_id'] === 1): ?><button class="btn-soft" id="bTags"><i class="bi bi-tags"></i> Etiquetas</button><?php endif; ?>
  <a class="btn-pos" href="<?= BASE_URL ?>/modulo.php?m=clientes"><i class="bi bi-person-plus-fill"></i><span>Registrar cliente</span></a></div></div>
<div class="cardx">
  <div class="toolbar"><div class="search-in"><i class="bi bi-search"></i><input class="form-control" id="q" placeholder="Buscar por nombre, documento, teléfono o correo…"></div>
    <select class="form-select" id="tag" style="max-width:190px"><option value="">Todas las etiquetas</option></select>
    <select class="form-select" id="orden" style="max-width:200px"><option value="monto">Mayor valor</option><option value="recientes">Compra más reciente</option><option value="riesgo">Más tiempo sin comprar</option><option value="score">Mejor puntaje</option><option value="deuda">Mayor deuda</option><option value="nombre">Nombre A-Z</option></select></div>
  <div class="chips mb-3" id="segs"></div>
  <div class="table-responsive"><table class="tbl"><thead><tr><th>Cliente</th><th>Segmento</th><th>Salud</th><th class="r">Compras</th><th class="r">Total</th><th>Última compra</th><th>Etiquetas</th><th></th></tr></thead><tbody id="tb"></tbody></table></div>
  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"><small class="text-muted" id="info"></small><div class="d-flex gap-1" id="pg"></div></div></div>
<?php if (usuario()['rol_id'] === 1): ?>
<div class="modal fade" id="mdlTags" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Etiquetas de clientes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div id="tagList"></div><div class="d-flex gap-2 mt-3"><input type="color" class="form-control form-control-color" id="tagNewC" value="#5b5ef4"><input class="form-control" id="tagNewN" maxlength="40" placeholder="Nueva etiqueta (ej. Cliente VIP)"><button class="btn-pos" id="tagAdd">Agregar</button></div></div></div></div></div>
<?php endif; ?>