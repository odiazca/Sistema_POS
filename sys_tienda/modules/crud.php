<?php
require_once __DIR__ . '/../includes/crud_config.php';
$ents = crud_entities(); $c = $ents[$slug]; $cfg = crud_public($slug, $c);
$extraJs[] = BASE_URL . '/assets/js/crud.js?v=' . ASSET_V;
?>
<div class="page-head">
  <div><h1><?= e($c['title']) ?></h1><p><?= e($c['sub']) ?></p></div>
  <div class="d-flex gap-2">
    <button class="btn-soft" id="btnCsv"><i class="bi bi-download"></i> Exportar CSV</button>
    <?php if ($cfg['write']): ?><button class="btn-pos" id="btnNew"><i class="bi bi-plus-lg"></i> <?= e($cfg['nuevo']) ?> <?= e(mb_strtolower($c['singular'])) ?></button><?php endif; ?>
  </div>
</div>
<div class="cardx">
  <div class="toolbar">
    <div class="search-in"><i class="bi bi-search"></i><input type="text" class="form-control" id="q" placeholder="Buscar…" value="<?= e($_GET['q'] ?? '') ?>"></div>
    <?php foreach ($cfg['filters'] as $f): ?>
      <select class="form-select w-auto" data-filter="<?= e($f['key']) ?>"><option value=""><?= e($f['label']) ?>: todas</option>
        <?php foreach ($f['options'] as $v => $l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
    <?php endforeach; ?>
    <?php if ($cfg['hasEstado']): ?><select class="form-select w-auto" id="fEstado"><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select><?php endif; ?>
    <?php if ($cfg['dates']): ?><input type="date" class="form-control w-auto" id="fDesde" title="Desde"><input type="date" class="form-control w-auto" id="fHasta" title="Hasta"><?php endif; ?>
    <?php if ($cfg['sum']): ?><div class="ms-auto stat-mini"><small><?= e($cfg['sum']) ?></small><b id="sumVal">—</b></div><?php endif; ?>
  </div>
  <div class="table-responsive"><table class="tbl" id="tbl"><thead></thead><tbody></tbody></table></div>
  <div class="pager" id="pager"></div>
</div>

<div class="modal fade" id="mdlForm" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px">
  <form id="frm" novalidate><div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="modal-title fw-bold" id="mdlTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body px-4"><div class="row g-3" id="fields"></div><div class="text-danger small mt-3" id="frmErr"></div></div>
  <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos" id="btnSave"><i class="bi bi-check2"></i> Guardar</button></div></form>
</div></div></div>
<script>window.CRUD = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE) ?>;</script>
