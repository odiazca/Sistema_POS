<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$forzar = !empty($_SESSION['forzar_clave']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#5b5ef4"><link rel="manifest" href="<?= BASE_URL ?>/manifest.php"><link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/pwa/apple-touch-icon.png"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="<?= e(cfg('empresa_nombre', APP_NAME)) ?>"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Cambiar contraseña · <?= e(APP_NAME) ?></title>
<link href="<?= BASE_URL ?>/assets/vendor/inter/inter.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<style>body{font-family:'Inter',system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;background:radial-gradient(900px 500px at 10% -10%,#7c7ff8 0%,transparent 55%),linear-gradient(135deg,#312e81,#0f172a);padding:1rem}
.box{background:#fff;border-radius:22px;padding:2.2rem;width:100%;max-width:440px;box-shadow:0 30px 80px -20px rgba(0,0,0,.5)}
.btn-main{background:linear-gradient(135deg,#5b5ef4,#8b5cf6);color:#fff;border:0;border-radius:12px;padding:.75rem;font-weight:600;width:100%}
.form-control{border-radius:12px;padding:.7rem .9rem}</style></head><body>
<div class="box"><div class="d-flex align-items-center gap-3 mb-3"><div style="width:52px;height:52px;border-radius:14px;background:#eef0ff;color:#5b5ef4;display:grid;place-items:center;font-size:1.5rem"><i class="bi bi-shield-lock"></i></div>
<div><h5 class="fw-bold mb-0">Elige una contraseña nueva</h5><div class="text-muted small">Hola, <?= e(explode(' ', usuario()['nombre'])[0]) ?></div></div></div>
<?php if ($forzar): ?><div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i>Tu contraseña actual es temporal o de demostración. Por seguridad debes cambiarla para continuar.</div><?php endif; ?>
<form id="f" novalidate>
  <label class="form-label small fw-semibold">Contraseña actual</label><input type="password" class="form-control mb-3" name="actual" autocomplete="current-password" required>
  <label class="form-label small fw-semibold">Contraseña nueva</label><input type="password" class="form-control" name="nueva" autocomplete="new-password" required>
  <div class="form-text mb-3">Mínimo 8 caracteres, con letras y números. Evita claves comunes.</div>
  <label class="form-label small fw-semibold">Repite la contraseña nueva</label><input type="password" class="form-control mb-3" name="nueva2" autocomplete="new-password" required>
  <div class="text-danger small mb-2" id="err"></div>
  <button class="btn-main" id="btn"><i class="bi bi-check2"></i> Guardar y continuar</button>
</form><div class="text-center mt-3"><a href="<?= BASE_URL ?>/logout.php" class="small text-muted">Cerrar sesión</a></div></div>
<script>
document.getElementById('f').addEventListener('submit', async (e) => {
  e.preventDefault(); const d = Object.fromEntries(new FormData(e.target)), err = document.getElementById('err'); err.textContent = '';
  if (d.nueva !== d.nueva2) { err.textContent = 'Las contraseñas nuevas no coinciden.'; return; }
  document.getElementById('btn').disabled = true;
  try {
    const r = await fetch('<?= BASE_URL ?>/api/perfil.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' }, body: JSON.stringify(d) }), j = await r.json();
    if (!j.ok) throw new Error(j.error || 'No se pudo cambiar la contraseña'); location.href = '<?= BASE_URL ?>/dashboard.php';
  } catch (x) { err.textContent = x.message; document.getElementById('btn').disabled = false; }
});
</script><script>window.APP=window.APP||{base:"<?= BASE_URL ?>"}</script><script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>
</body></html>
