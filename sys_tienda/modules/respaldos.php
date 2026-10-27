<?php $extraJs[] = BASE_URL . '/assets/js/respaldos.js?v=' . ASSET_V; ?>
<style>
.bk-hero{background:linear-gradient(120deg,#052e2b,#047857 55%,#10b981);border-radius:18px;color:#fff;padding:24px 28px;margin-bottom:18px;position:relative;overflow:hidden}
.bk-hero:after{content:"";position:absolute;right:-50px;bottom:-70px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.07)}
.bk-hero h2{font-weight:800;font-size:1.3rem;margin:0}.bk-hero p{opacity:.85;margin:2px 0 14px;font-size:.88rem}
.bk-stats{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}.bk-stat{background:rgba(255,255,255,.13);border-radius:12px;padding:10px 16px;min-width:130px;backdrop-filter:blur(4px)}
.bk-stat b{display:block;font-size:1.25rem}.bk-stat span{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;opacity:.75}
.bk-hero .btn-w{background:#fff;color:#047857;border:0;border-radius:11px;font-weight:700;padding:9px 18px}.bk-hero .btn-g{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:11px;padding:9px 16px;text-decoration:none;font-weight:600;cursor:pointer}
.bk-item{display:flex;align-items:center;gap:14px;padding:12px 4px;border-bottom:1px solid var(--line,#e2e8f0)}.bk-item:last-child{border:0}
.bk-ic{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex:none}
.bk-ic.manual{background:#dbeafe;color:#1d4ed8}.bk-ic.auto{background:#d1fae5;color:#047857}.bk-ic.previo{background:#fef3c7;color:#b45309}.bk-ic.subido{background:#e2e8f0;color:#475569}
.bk-item .nm{font-weight:700;font-size:.92rem}.bk-item .mt{font-size:.78rem;color:#64748b}
.scope{border:2px solid var(--line,#e2e8f0);border-radius:14px;padding:14px 16px;cursor:pointer;display:flex;gap:12px;align-items:flex-start;margin-bottom:10px;transition:.15s}
.scope:hover{border-color:#94a3b8}.scope.on{border-color:#e11d48;background:#fff1f2}.scope input{margin-top:5px}
.scope .t{font-weight:700}.scope .d{font-size:.8rem;color:#64748b}
.danger-card{border:1px solid rgba(244,63,94,.35)!important}
.emp-sec.cardx,.bk-card.cardx,.danger-card.cardx,.col-lg-8>.cardx,.col-lg-4>.cardx{height:auto}
.col-lg-8>.cardx,.col-lg-4>.cardx{height:auto}
.sched-note{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:10px 14px;font-size:.8rem}
</style>
<div class="page-head"><div><h1>Copias de seguridad</h1><p>Respalda, restaura y restablece la información del sistema</p></div></div>

<div class="bk-hero"><h2><i class="bi bi-shield-check me-2"></i>Tu información protegida</h2><p id="hInfo">Cargando…</p>
  <div class="d-flex gap-2 flex-wrap"><button class="btn-w" id="bkNow"><i class="bi bi-plus-circle me-1"></i> Crear copia ahora</button>
   <label class="btn-g mb-0"><i class="bi bi-upload me-1"></i> Subir copia (.sql / .sql.gz)<input type="file" id="bkUp" accept=".sql,.gz" hidden></label>
   <a class="btn-g" href="<?= BASE_URL ?>/api/respaldos.php?a=directa"><i class="bi bi-download me-1"></i> Descargar sin guardar</a></div>
  <div class="bk-stats" id="hStats"></div></div>

<div class="row g-3">
 <div class="col-lg-8">
  <div class="cardx mb-3"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Copias guardadas</h6><span class="text-muted small" id="bkCount"></span></div><div id="bkList"></div></div>
  <div class="cardx danger-card"><h6 class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Restablecer el sistema</h6>
   <p class="text-muted small mb-3">Limpia datos para empezar a operar (por ejemplo, tras la demostración o al entregar el sistema a una empresa nueva). <b>Antes de borrar se guarda una copia automática</b>, así que siempre puedes volver atrás.</p>
   <button class="btn-pos" style="background:linear-gradient(135deg,#f43f5e,#e11d48)" id="btnRst"><i class="bi bi-arrow-repeat me-1"></i> Abrir asistente de restablecimiento</button></div>
 </div>
 <div class="col-lg-4">
  <div class="cardx mb-3"><h6 class="fw-bold mb-3"><i class="bi bi-calendar-check me-2 text-success"></i>Copia automática</h6>
   <form id="frmBk"><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="bkAuto" name="backup_auto"><label class="form-check-label" for="bkAuto">Activar copias automáticas</label></div>
    <label class="form-label">Frecuencia</label><select class="form-select mb-3" name="backup_horas" data-nosearch><option value="1">Cada hora</option><option value="3">Cada 3 horas</option><option value="6">Cada 6 horas</option><option value="12">Cada 12 horas</option><option value="24">Cada día</option><option value="48">Cada 2 días</option><option value="168">Cada semana</option></select>
    <label class="form-label">Conservar las últimas</label><input type="number" min="3" max="365" class="form-control mb-3" name="backup_conservar">
    <label class="form-label">Copiar también a otra carpeta <span class="text-muted">(opcional)</span></label><input class="form-control mb-3" name="backup_copia" placeholder="D:\Respaldos">
    <button class="btn-pos w-100"><i class="bi bi-check2"></i> Guardar programación</button></form>
   <div class="sched-note mt-3"><b>Sin usar el sistema:</b> programa en el Programador de tareas de Windows<br><code id="cronCmd" style="word-break:break-all"></code></div>
   <div class="text-muted small mt-2">Las copias manuales nunca se borran solas.</div></div>
 </div>
</div>

<div class="modal fade" id="mdlRs" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0" style="border-radius:20px">
 <form id="frmRs" novalidate><div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="modal-title fw-bold text-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar copia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body px-4"><p class="small text-muted">Se reemplazarán <b>todos los datos actuales</b> por los de <code id="rsFile"></code>. Antes se guarda automáticamente una copia del estado actual.</p>
  <label class="form-label">Tu contraseña de administrador</label><input type="password" class="form-control mb-3" name="password" autocomplete="current-password" required>
  <label class="form-label">Escribe RESTAURAR para confirmar</label><input class="form-control" name="confirmar" autocomplete="off" required><div class="text-danger small mt-2" id="rsErr"></div></div>
 <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos" id="rsGo" style="background:linear-gradient(135deg,#f43f5e,#e11d48)">Restaurar</button></div></form></div></div></div>

<div class="modal fade" id="mdlRst" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px">
 <form id="frmRst" novalidate><div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="modal-title fw-bold text-danger"><i class="bi bi-arrow-repeat me-2"></i>Restablecer el sistema</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body px-4"><label class="form-label fw-semibold">1. ¿Qué deseas borrar?</label>
  <label class="scope on"><input type="radio" name="alcance" value="movimientos" checked><div><div class="t">Solo movimientos</div><div class="d">Ventas, compras, cajas, gastos, cotizaciones, devoluciones, traspasos, puntos y auditoría. Se conservan productos, clientes, proveedores y usuarios; el stock queda en 0.</div></div></label>
  <label class="scope"><input type="radio" name="alcance" value="catalogo"><div><div class="t">Movimientos + catálogo</div><div class="d">Además borra productos (y sus fotos), categorías, marcas, proveedores, promociones y clientes. Se conservan usuarios, sucursales y datos de la empresa.</div></div></label>
  <label class="scope"><input type="radio" name="alcance" value="empresa_nueva"><div><div class="t">Empresa nueva <span class="pill bad ms-1">Total</span></div><div class="d">Deja el sistema como recién instalado: borra todo lo anterior, los demás usuarios y sucursales, y los datos y logo de la empresa. Se conserva tu usuario administrador, la moneda, la impresora y las copias.</div></div></label>
  <div class="alert alert-warning small py-2 mt-2"><i class="bi bi-shield-check me-1"></i>Se crea una copia de seguridad antes de borrar; podrás recuperarla desde la lista.</div>
  <div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">2. Tu contraseña de administrador</label><input type="password" class="form-control" name="password" autocomplete="current-password" required></div>
   <div class="col-md-6"><label class="form-label fw-semibold">3. Escribe <b id="rstWord">BORRAR</b></label><input class="form-control" name="confirmar" autocomplete="off" required></div></div>
  <div class="text-danger small mt-2" id="rstErr"></div></div>
 <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos" id="rstGo" style="background:linear-gradient(135deg,#f43f5e,#e11d48)"><i class="bi bi-trash3 me-1"></i> Restablecer</button></div></form></div></div></div>
