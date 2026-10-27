<?php
$extraJs[] = BASE_URL . '/assets/js/crm_common.js?v=' . ASSET_V;
$extraJs[] = BASE_URL . '/assets/js/crm_campanas.js?v=' . ASSET_V;
?>
<div class="page-head"><div><h1>Campañas</h1><p>Mensajes personalizados por WhatsApp a tus segmentos, con medición de resultados</p></div>
  <div class="d-flex gap-2"><button class="btn-soft" id="bPl"><i class="bi bi-chat-square-text"></i> Plantillas</button><button class="btn-pos" id="bNew"><i class="bi bi-megaphone-fill"></i><span>Nueva campaña</span></button></div></div>
<div class="row g-3 mb-3" id="kpis"></div>
<div class="cardx"><div class="table-responsive"><table class="tbl"><thead><tr><th>Campaña</th><th>Segmento</th><th>Avance</th><th class="r">Enviados</th><th class="r">Compraron</th><th class="r">Ventas atribuidas</th><th>Estado</th><th></th></tr></thead><tbody id="tb"></tbody></table></div></div>
<div class="modal fade" id="mdlNew" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px"><form id="frmNew">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Nueva campaña</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Nombre de la campaña</label><input class="form-control" name="nombre" required maxlength="120" placeholder="Ej.: Reactivación de octubre"></div>
    <div class="col-md-6"><label class="form-label">¿A quién se envía?</label><select class="form-select" name="segmento" required></select></div>
    <div class="col-12"><div class="d-flex justify-content-between align-items-end"><label class="form-label mb-1">Mensaje</label><select class="form-select form-select-sm" id="plSel" style="max-width:240px"><option value="">Usar una plantilla…</option></select></div>
      <textarea class="form-control" name="mensaje" rows="4" required maxlength="1000" placeholder="Escribe tu mensaje. Usa variables para personalizarlo."></textarea>
      <div class="chips mt-2"><span class="small text-muted">Insertar:</span><?php foreach (['{nombre}', '{puntos}', '{deuda}', '{dias}', '{empresa}', '{descuento}'] as $v): ?><button type="button" class="var-chip" data-v="<?= $v ?>"><?= $v ?></button><?php endforeach; ?></div></div>
    <div class="col-12"><div class="cardx" style="height:auto;background:var(--bg)"><div class="fw-bold small mb-2">Vista previa</div><div id="prev" class="small text-muted">Elige un segmento y escribe el mensaje.</div></div></div></div></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos">Crear campaña</button></div></form></div></div></div>
<div class="modal fade" id="mdlDet" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-body p-4" id="det"></div></div></div></div>
<div class="modal fade" id="mdlPl" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content border-0" style="border-radius:20px"><div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Plantillas de mensajes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="plBody"></div></div></div></div>