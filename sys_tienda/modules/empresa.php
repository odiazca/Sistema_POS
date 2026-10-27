<?php $extraJs[] = BASE_URL . '/assets/js/empresa.js?v=' . ASSET_V; ?>
<style>
.emp-hero{background:linear-gradient(120deg,#0f2a5e,#1d4ed8 60%,#2563eb);border-radius:18px;color:#fff;padding:22px 26px;display:flex;align-items:center;gap:20px;margin-bottom:18px;position:relative;overflow:hidden}
.emp-hero:after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.07)}
.emp-hero .lg{width:84px;height:84px;border-radius:18px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;flex:none;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.emp-hero .lg img{max-width:100%;max-height:100%;object-fit:contain}.emp-hero .lg i{font-size:2.2rem;color:#94a3b8}
.emp-hero h2{font-size:1.35rem;margin:0;font-weight:800}.emp-hero p{margin:2px 0 0;opacity:.85;font-size:.88rem}
.emp-sec{margin-bottom:18px}.emp-sec h6{font-weight:800;margin:0 0 2px;display:flex;align-items:center;gap:8px}
.emp-sec h6 i{width:30px;height:30px;border-radius:9px;background:#eff6ff;color:#2563eb;display:inline-flex;align-items:center;justify-content:center}
.emp-sec .sub{color:#64748b;font-size:.82rem;margin-bottom:14px}
.logo-drop{border:2px dashed var(--line,#e2e8f0);border-radius:14px;padding:18px;text-align:center;cursor:pointer;background:#f8fafc;transition:.15s}
.logo-drop:hover,.logo-drop.drag{border-color:#2563eb;background:#eff6ff}
.logo-drop img{max-height:110px;max-width:100%;margin-bottom:8px}.logo-drop i.big{font-size:2.4rem;color:#94a3b8}
.fmt-prev{background:#0f172a;color:#e2e8f0;border-radius:14px;padding:16px 18px}
.fmt-prev .k{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;opacity:.6}.fmt-prev .v{font-size:1.5rem;font-weight:800;font-variant-numeric:tabular-nums}
.fmt-prev .row>div{margin-bottom:8px}
.emp-sec.cardx{height:auto}
.sticky-save{position:sticky;bottom:0;background:linear-gradient(transparent,var(--bg,#f1f5f9) 30%);padding:18px 0 4px;z-index:5}
</style>
<div class="page-head"><div><h1>Empresa</h1><p>Datos del negocio, logo, moneda, formatos numéricos y preferencias generales</p></div></div>

<div class="emp-hero"><div class="lg" id="hLogo"><i class="bi bi-shop"></i></div><div><h2 id="hNom">Tu empresa</h2><p id="hSub">Completa los datos que aparecerán en tickets, comprobantes y reportes</p></div></div>

<form id="frmEmp" novalidate>
<div class="row g-3">
 <div class="col-lg-8">
  <div class="cardx emp-sec"><h6><i class="bi bi-building"></i>Datos de la empresa</h6><div class="sub">Se imprimen en el encabezado de tickets, cotizaciones y cierres de caja.</div>
   <div class="row g-3">
    <div class="col-md-7"><label class="form-label">Nombre comercial *</label><input class="form-control" name="empresa_nombre" maxlength="80" required></div>
    <div class="col-md-5"><label class="form-label">RUC / NIT / RFC</label><input class="form-control" name="empresa_ruc" maxlength="20"></div>
    <div class="col-md-7"><label class="form-label">Razón social</label><input class="form-control" name="empresa_razon_social" maxlength="120"></div>
    <div class="col-md-5"><label class="form-label">Eslogan</label><input class="form-control" name="empresa_eslogan" maxlength="80"></div>
    <div class="col-md-8"><label class="form-label">Dirección</label><input class="form-control" name="empresa_direccion" maxlength="150"></div>
    <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="empresa_telefono" maxlength="30"></div>
    <div class="col-md-6"><label class="form-label">Correo electrónico</label><input type="email" class="form-control" name="empresa_email" maxlength="100"></div>
    <div class="col-md-6"><label class="form-label">Página web</label><input class="form-control" name="empresa_web" maxlength="100" placeholder="www.mitienda.com"></div>
   </div></div>

  <div class="cardx emp-sec"><h6><i class="bi bi-currency-exchange"></i>Moneda y formato de números</h6><div class="sub">Define cómo se muestran los importes en todo el sistema. Los cálculos internos siempre usan 2 decimales; esta opción solo cambia la presentación.</div>
   <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Símbolo *</label><input class="form-control" name="moneda" maxlength="5" placeholder="S/"></div>
    <div class="col-md-3"><label class="form-label">Código ISO *</label><input class="form-control text-uppercase" name="moneda_codigo" maxlength="3" placeholder="PEN"></div>
    <div class="col-md-3"><label class="form-label">Decimales</label><select class="form-select" name="moneda_decimales" data-nosearch><option>0</option><option>1</option><option>2</option><option>3</option></select></div>
    <div class="col-md-3"><label class="form-label">Símbolo</label><select class="form-select" name="moneda_posicion" data-nosearch><option value="antes">Antes (S/ 10)</option><option value="despues">Después (10 S/)</option></select></div>
    <div class="col-md-6"><label class="form-label">Separador decimal</label><select class="form-select" name="sep_decimal" data-nosearch><option value=".">Punto ( . )</option><option value=",">Coma ( , )</option></select></div>
    <div class="col-md-6"><label class="form-label">Separador de miles</label><select class="form-select" name="sep_miles" data-nosearch><option value=",">Coma ( , )</option><option value=".">Punto ( . )</option><option value="espacio">Espacio</option><option value="ninguno">Ninguno</option></select></div>
    <div class="col-12"><div class="fmt-prev"><div class="row"><div class="col-6 col-md-3"><div class="k">Importe</div><div class="v" id="pv1"></div></div><div class="col-6 col-md-3"><div class="k">Importe grande</div><div class="v" id="pv2"></div></div><div class="col-6 col-md-3"><div class="k">Importe menor</div><div class="v" id="pv3"></div></div><div class="col-6 col-md-3"><div class="k">Con impuesto</div><div class="v" id="pv4"></div></div></div></div></div>
   </div></div>

  <div class="cardx emp-sec"><h6><i class="bi bi-percent"></i>Impuestos y preferencias</h6><div class="sub">Impuesto aplicado a las ventas y valores por defecto del sistema.</div>
   <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Impuesto (%)</label><input type="number" step="0.01" min="0" max="30" class="form-control" name="igv"></div>
    <div class="col-md-3"><label class="form-label">Nombre del impuesto</label><input class="form-control" name="impuesto_nombre" maxlength="10" placeholder="IGV"></div>
    <div class="col-md-6"><label class="form-label">Zona horaria</label><select class="form-select" name="zona_horaria" id="zona"></select></div>
    <div class="col-md-4"><label class="form-label">Filas por página en listados</label><select class="form-select" name="filas_pagina" data-nosearch><option>10</option><option>12</option><option>15</option><option>20</option><option>25</option><option>50</option></select></div>
    <div class="col-md-4"><label class="form-label">Alerta de vencimiento (días)</label><input type="number" min="7" max="180" class="form-control" name="vencimiento_dias"></div>
    <div class="col-md-4"><label class="form-label">Stock mínimo por defecto</label><input type="number" min="0" step="1" class="form-control" name="stock_minimo_defecto"></div>
   </div></div>

  <div class="cardx emp-sec"><h6><i class="bi bi-receipt"></i>Ticket</h6><div class="sub">Mensaje y logo en los comprobantes impresos.</div>
   <div class="row g-3"><div class="col-12"><label class="form-label">Mensaje al pie del ticket</label><input class="form-control" name="ticket_mensaje" maxlength="120" placeholder="¡Gracias por su compra!"></div>
   <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="ticket_logo" id="tLogo"><label class="form-check-label" for="tLogo">Imprimir el logo en el ticket (si hay logo cargado)</label></div></div></div></div>
 </div>

 <div class="col-lg-4">
  <div class="cardx emp-sec" style="position:sticky;top:12px"><h6><i class="bi bi-image"></i>Logo</h6><div class="sub">JPG, PNG o WEBP · máx. 2 MB. Se usa en el menú, el login y los comprobantes.</div>
   <div class="logo-drop" id="drop"><div id="dropIn"><i class="bi bi-cloud-arrow-up big"></i><div class="fw-semibold">Arrastra tu logo aquí</div><div class="small text-muted">o haz clic para elegir</div></div></div>
   <input type="file" id="logoFile" accept="image/png,image/jpeg,image/webp" hidden>
   <button type="button" class="btn-soft w-100 mt-2" id="btnQuitar" style="display:none"><i class="bi bi-trash"></i> Quitar logo</button>
  </div>
 </div>
</div>
<div class="sticky-save"><button class="btn-pos" id="btnSave"><i class="bi bi-check2"></i> Guardar cambios</button></div>
</form>
