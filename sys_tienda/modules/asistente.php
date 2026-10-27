<?php $extraJs[] = BASE_URL . '/assets/vendor/chartjs/chart.umd.min.js'; $extraJs[] = BASE_URL . '/assets/js/asistente.js?v=' . ASSET_V; ?>
<style>
.ia-hero{background:linear-gradient(120deg,#1e1b4b,#4338ca 55%,#7c3aed);border-radius:18px;color:#fff;padding:22px 26px;margin-bottom:16px;position:relative;overflow:hidden}
.ia-hero:after{content:"";position:absolute;right:-50px;top:-70px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.07)}
.ia-hero h2{font-size:1.25rem;font-weight:800;margin:0 0 4px}.ia-hero p{margin:0 0 14px;opacity:.85;font-size:.88rem}
.ia-ask{display:flex;gap:8px;background:#fff;border-radius:14px;padding:6px;box-shadow:0 10px 30px -12px rgba(0,0,0,.5);position:relative;z-index:1}
.ia-ask textarea{flex:1;border:0;outline:0;resize:none;padding:.65rem .8rem;font-size:.97rem;border-radius:10px;background:transparent;color:#0f172a;min-height:48px;max-height:120px}
.ia-ask button{border:0;border-radius:11px;padding:0 20px;font-weight:700;color:#fff;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;gap:8px;white-space:nowrap}
.ia-ask button:disabled{opacity:.6}
.ia-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;position:relative;z-index:1}
.ia-chip{border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#fff;border-radius:999px;padding:5px 12px;font-size:.78rem;cursor:pointer;transition:.15s}
.ia-chip:hover{background:rgba(255,255,255,.25)}
.ia-res{margin-bottom:16px}
.ia-res h5{font-weight:800;margin:0;font-size:1.05rem}.ia-meta{color:var(--muted);font-size:.78rem}
.ia-sum{background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #e0e7ff;border-radius:12px;padding:10px 14px;font-size:.88rem;color:#312e81;display:flex;gap:10px;align-items:flex-start;margin:12px 0}
[data-theme=dark] .ia-sum{background:#1e1b4b;border-color:#312e81;color:#c7d2fe}
.ia-chart{position:relative;height:300px;margin:8px 0 14px}
.ia-sql{background:#0f172a;color:#e2e8f0;border-radius:12px;padding:12px 14px;font-size:.78rem;white-space:pre-wrap;word-break:break-word;margin:10px 0 0}
.ia-tbl{max-height:420px;overflow:auto;border:1px solid var(--line);border-radius:12px}
.ia-tbl .tbl th{position:sticky;top:0;background:var(--card);z-index:1}
.ia-side .it{display:flex;gap:8px;align-items:center;padding:7px 4px;border-bottom:1px solid var(--line);cursor:pointer;border-radius:8px}
.ia-side .it:hover{background:var(--bg)}.ia-side .it:last-child{border:0}
.ia-side .it .tx{flex:1;min-width:0;font-size:.82rem;line-height:1.25}.ia-side .it .tx small{color:var(--muted);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ia-side .it button{border:0;background:none;color:var(--muted);padding:2px 4px}.ia-side .it button:hover{color:#e11d48}
.ia-load{display:flex;align-items:center;gap:12px;padding:22px;color:var(--muted)}
.ia-dots span{display:inline-block;width:8px;height:8px;border-radius:50%;background:#7c3aed;margin-right:4px;animation:iad 1s infinite ease-in-out}.ia-dots span:nth-child(2){animation-delay:.15s}.ia-dots span:nth-child(3){animation-delay:.3s}
@keyframes iad{0%,80%,100%{transform:scale(.5);opacity:.4}40%{transform:scale(1);opacity:1}}
.ia-setup .st{display:flex;gap:12px;margin-bottom:10px}.ia-setup .st b{width:26px;height:26px;border-radius:50%;background:#eef2ff;color:#4f46e5;display:grid;place-items:center;flex:none;font-size:.8rem}
.col-lg-8>.cardx,.col-lg-4>.cardx{height:auto}
@media(max-width:575.98px){.ia-ask{flex-direction:column}.ia-ask button{padding:12px;justify-content:center}.ia-hero{padding:16px}}
</style>
<div class="page-head"><div><h1>Asistente de reportes</h1><p>Pregunta en lenguaje natural y obtén tablas, gráficos y resúmenes de tu negocio</p></div>
  <div class="d-flex gap-2"><button class="btn-soft" id="btnEsq"><i class="bi bi-diagram-3"></i> Qué datos ve la IA</button><button class="btn-soft" id="btnCfg"><i class="bi bi-gear"></i> Configurar IA</button></div></div>

<div class="ia-hero"><h2><i class="bi bi-stars me-2"></i>¿Qué quieres saber de tu negocio?</h2><p>Escribe tu pregunta como se la harías a un analista. Ejemplo: «¿Cuáles fueron los 10 productos más vendidos este mes?»</p>
  <form class="ia-ask" id="frmAsk"><textarea id="iaQ" rows="1" maxlength="500" placeholder="Escribe tu pregunta aquí…" aria-label="Pregunta"></textarea><button id="iaGo"><i class="bi bi-send-fill"></i> Preguntar</button></form>
  <div class="ia-chips" id="iaChips"></div></div>

<div id="iaSetup" class="cardx ia-setup mb-3" style="display:none"><h6 class="fw-bold"><i class="bi bi-rocket-takeoff me-2 text-primary"></i>Activa el asistente en 2 minutos (gratis)</h6>
  <div class="st"><b>1</b><div>Entra a <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a> con tu cuenta de Google y crea una clave gratuita.</div></div>
  <div class="st"><b>2</b><div class="flex-grow-1">Pega la clave aquí y pulsa <b>Activar</b>:
    <form class="d-flex gap-2 mt-2 flex-wrap" id="frmQuick"><input type="password" class="form-control" id="qKey" style="max-width:340px" placeholder="Clave de Gemini" autocomplete="new-password"><button class="btn-pos" id="qGo">Activar</button></form><div class="small text-danger mt-1" id="qErr"></div></div></div>
  <div class="small text-muted mb-2">También puedes usar Groq, OpenRouter u Ollama (local, sin enviar datos a internet) desde la configuración avanzada.</div>
  <button class="btn-soft" onclick="document.getElementById('btnCfg').click()"><i class="bi bi-gear"></i> Configuración avanzada</button></div>

<div class="row g-3"><div class="col-lg-8"><div id="iaOut"><div class="cardx text-center text-muted py-5"><i class="bi bi-chat-square-text" style="font-size:2.4rem"></i><div class="mt-2">Aquí aparecerán tus resultados</div><div class="small">Puedes hacer preguntas de seguimiento, como «ahora por sucursal».</div></div></div></div>
  <div class="col-lg-4"><div class="cardx ia-side mb-3"><h6 class="fw-bold mb-2"><i class="bi bi-star-fill text-warning me-1"></i>Favoritas</h6><div id="iaFav"></div></div>
    <div class="cardx ia-side"><h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i>Recientes</h6><div id="iaHis"></div></div></div></div>

<div class="modal fade" id="mdlCfg" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0" style="border-radius:20px">
 <form id="frmCfg" novalidate><div class="modal-header border-0 pb-0 px-4 pt-4"><h5 class="modal-title fw-bold"><i class="bi bi-gear me-2"></i>Configurar el asistente de IA</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body px-4"><div class="row g-3">
  <div class="col-12"><label class="form-label">Proveedor</label><select class="form-select" name="proveedor" id="cProv" data-nosearch></select><div class="form-text" id="cAyuda"></div></div>
  <div class="col-md-7"><label class="form-label">URL de la API</label><input class="form-control" name="url" id="cUrl" autocomplete="off"></div>
  <div class="col-md-5"><label class="form-label">Modelo</label><input class="form-control" name="modelo" id="cMod" autocomplete="off"></div>
  <div class="col-12"><label class="form-label">Clave (API key)</label><input type="password" class="form-control" name="clave" id="cKey" autocomplete="new-password" placeholder=""><div class="form-text" id="cKeyH"></div></div>
  <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="resumen" id="cRes"><label class="form-check-label" for="cRes">Generar un resumen en lenguaje natural de cada resultado (usa una segunda consulta a la IA)</label></div></div>
  <div class="col-12"><div class="alert alert-light border small mb-0"><i class="bi bi-shield-lock me-1"></i><b>Privacidad y seguridad.</b> La IA solo ve la estructura de unas vistas de lectura (sin contraseñas ni usuarios) y su consulta se valida antes de ejecutarse: solo SELECT, en modo lectura, con límite de tiempo y de filas. Al proveedor se envía tu pregunta y, si activas el resumen, hasta 25 filas del resultado (pueden incluir nombres de productos o clientes). Con <b>Ollama</b> nada sale de tu PC. La clave se guarda cifrada.</div></div>
  <div class="col-12 text-danger small" id="cErr"></div></div></div>
 <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light me-auto" id="cTest"><i class="bi bi-plug"></i> Probar conexión</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button><button class="btn-pos" id="cSave"><i class="bi bi-check2"></i> Guardar</button></div></form></div></div></div>

<div class="modal fade" id="mdlEsq" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content border-0" style="border-radius:20px">
 <div class="modal-header border-0 px-4 pt-4"><h5 class="modal-title fw-bold"><i class="bi bi-diagram-3 me-2"></i>Datos que ve la IA</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
 <div class="modal-body px-4"><p class="small text-muted">Solo estas vistas de lectura. Nunca accede a las tablas reales, usuarios ni contraseñas.</p><pre class="ia-sql" id="esqTxt" style="margin:0"></pre></div></div></div></div>
