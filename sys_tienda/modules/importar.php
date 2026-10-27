<?php $extraJs[] = BASE_URL . '/assets/js/importar.js?v=' . ASSET_V; ?>
<div class="page-head"><div><h1>Importar / exportar productos</h1><p>Carga tu catálogo desde Excel (CSV) o descárgalo para editarlo masivamente</p></div></div>
<div class="row g-3">
  <div class="col-xl-4"><div class="cardx h-100">
    <h6 class="fw-bold"><i class="bi bi-1-circle me-2"></i>Descarga la plantilla o tu catálogo</h6>
    <p class="text-muted small">Abre el CSV en Excel, edita o agrega filas y guárdalo como <b>CSV (delimitado por comas o punto y coma, UTF-8)</b>.</p>
    <div class="d-grid gap-2"><button class="btn-soft justify-content-center" id="btnTpl"><i class="bi bi-file-earmark-spreadsheet"></i> Descargar plantilla (con ejemplos)</button>
      <button class="btn-soft justify-content-center" id="btnExp"><i class="bi bi-download"></i> Exportar mis productos actuales</button></div>
    <hr><div class="small text-muted"><b>Columnas:</b> codigo*, codigo_barras, nombre*, categoria, marca, unidad (UND, KG, LT, PQT, BLS, BOT, LAT, CJA, DOC), precio_compra, precio_venta*, precio_mayor, cantidad_mayor, stock, stock_minimo, fecha_vencimiento (AAAA-MM-DD).<br><br>
      • Si el <b>código</b> ya existe, se <b>actualiza</b>; si no, se crea.<br>• Las categorías y marcas nuevas se crean solas.<br>• El <b>stock</b> se asigna a la sucursal activa: <b><?= e(suc_nombre()) ?></b>.</div>
  </div></div>
  <div class="col-xl-8"><div class="cardx h-100">
    <h6 class="fw-bold"><i class="bi bi-2-circle me-2"></i>Sube tu archivo CSV</h6>
    <label class="drop" id="drop"><i class="bi bi-cloud-arrow-up"></i><div><b>Arrastra el archivo aquí</b> o haz clic para elegirlo</div><input type="file" id="file" accept=".csv,text/csv" hidden></label>
    <div id="prev" style="display:none" class="mt-3">
      <div class="d-flex justify-content-between align-items-center mb-2"><div><b id="pCount"></b> <span class="text-muted small" id="pInfo"></span></div><button class="btn-pos" id="btnGo"><i class="bi bi-check2-circle"></i> Importar ahora</button></div>
      <div id="pErr" class="alert alert-warning small py-2" style="display:none;border-radius:12px"></div>
      <div class="table-responsive" style="max-height:340px;overflow:auto"><table class="tbl"><thead id="pHead"></thead><tbody id="pBody"></tbody></table></div>
    </div>
  </div></div>
</div>
