<?php $extraJs[] = BASE_URL . '/assets/js/config.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Configuración</h1><p>Ventas, comprobantes, impresión y seguridad</p></div></div>
<div class="nav-tabs-x" id="tabs"><button class="on" data-t="emp"><i class="bi bi-stars me-1"></i>Ventas y fidelización</button><button data-t="ser"><i class="bi bi-receipt me-1"></i>Comprobantes</button><button data-t="imp"><i class="bi bi-printer me-1"></i>Impresión</button><button data-t="seg"><i class="bi bi-shield-lock me-1"></i>Seguridad</button></div>

<div id="t-emp" class="cardx" style="max-width:820px"><div class="alert alert-light border small"><i class="bi bi-building me-1"></i>Los datos de la empresa, logo, moneda e impuesto ahora están en el módulo <a href="<?= BASE_URL ?>/modulo.php?m=empresa" class="fw-semibold">Empresa</a>.</div><form id="frmEmp"><div class="row g-3">
  <div class="col-md-6"><label class="form-label">Meta de ventas diaria (<?= e(moneda()) ?>)</label><input type="number" step="1" class="form-control" name="meta_diaria"></div>
  <div class="col-12"><hr class="my-1"><div class="fw-bold small mb-1"><i class="bi bi-stars text-warning me-1"></i>Programa de fidelización</div></div>
  <div class="col-md-4"><label class="form-label">1 punto por cada (<?= e(moneda()) ?>)</label><input type="number" step="0.5" min="0.5" class="form-control" name="puntos_cada"></div>
  <div class="col-md-4"><label class="form-label">Valor de cada punto (<?= e(moneda()) ?>)</label><input type="number" step="0.01" min="0" class="form-control" name="puntos_valor"></div>
  <div class="col-md-4"><label class="form-label">Descuento de cumpleaños (%)</label><input type="number" step="0.5" min="0" max="100" class="form-control" name="cumple_descuento"></div>
  <div class="col-12"><button class="btn-pos"><i class="bi bi-check2"></i> Guardar cambios</button></div></div></form></div>

<div id="t-ser" class="cardx" style="max-width:720px;display:none"><p class="text-muted small">Cada sucursal tiene sus propias series y correlativos. El correlativo indica el <b>último número emitido</b>.</p>
  <form id="frmSer"><div id="serRows"></div><button class="btn-pos mt-3"><i class="bi bi-check2"></i> Guardar series</button></form></div>

<div id="t-imp" class="cardx" style="max-width:820px;display:none">
  <form id="frmImp"><div class="row g-3">
    <div class="col-12"><label class="form-label">¿Cómo imprimir los tickets?</label>
      <select class="form-select" name="print_modo" id="pMode" data-nosearch>
        <option value="navegador">Vista de impresión del navegador (cualquier impresora instalada en Windows)</option>
        <option value="red">Directo a impresora térmica de red (IP, puerto 9100)</option>
        <option value="local">Directo a impresora térmica local (compartida de Windows, COM/LPT o USB)</option></select></div>
    <div class="col-md-8" id="pDestBox"><label class="form-label" id="pDestLbl">Dirección de la impresora</label><input class="form-control" name="print_destino" id="pDest" placeholder="192.168.1.50:9100"><div class="form-text" id="pDestHelp"></div></div>
    <div class="col-md-2"><label class="form-label">Papel</label><select class="form-select" name="print_ancho" data-nosearch><option value="80">80 mm</option><option value="58">58 mm</option></select></div>
    <div class="col-md-2"><label class="form-label">Caracteres</label><select class="form-select" name="print_codepage" data-nosearch><option value="19">CP858</option><option value="16">WPC1252</option><option value="0">CP437</option></select></div>
    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="print_auto" id="pAuto"><label class="form-check-label" for="pAuto">Imprimir el ticket automáticamente al cobrar</label></div></div>
    <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="print_cajon" id="pCajon"><label class="form-check-label" for="pCajon">Abrir cajón monedero al cobrar en efectivo</label></div></div>
    <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn-pos"><i class="bi bi-check2"></i> Guardar</button><button type="button" class="btn-soft" id="btnPrueba"><i class="bi bi-printer"></i> Imprimir prueba</button><button type="button" class="btn-soft" id="btnCajonTest"><i class="bi bi-box-arrow-up"></i> Probar cajón</button></div>
    <div class="col-12"><div class="alert alert-light border small mb-0" id="pHelp"></div></div>
  </div></form></div>

<div id="t-seg" style="max-width:900px;display:none">
  <div class="cardx mb-3"><h6 class="fw-bold mb-1">Revisión de seguridad</h6><p class="text-muted small mb-3">Revisa estos puntos antes de usar el sistema desde internet.</p><div id="segList"></div></div>
  <div class="cardx"><h6 class="fw-bold mb-3">Sesiones</h6><form id="frmSeg" class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">Cerrar la sesión por inactividad (minutos)</label><input type="number" min="5" max="1440" class="form-control" name="sesion_minutos"></div>
    <div class="col-md-3"><button class="btn-pos"><i class="bi bi-check2"></i> Guardar</button></div></form>
    <ul class="small text-muted mt-3 mb-0"><li>Las contraseñas deben tener 8+ caracteres con letras y números; las de demostración se bloquean.</li><li>Un usuario nuevo, o con contraseña restablecida por el administrador, debe cambiarla al ingresar.</li><li>Tras 5 intentos fallidos la cuenta se bloquea 10 minutos; tras 20 desde un mismo equipo se bloquea ese equipo 15 minutos.</li></ul></div>
</div>

