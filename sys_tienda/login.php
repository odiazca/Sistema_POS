<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
verificar_esquema(false);
if (usuario()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = ''; $userVal = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userVal = trim($_POST['usuario'] ?? '');
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'La sesión expiró. Intenta nuevamente.';
    } elseif ($userVal === '' || ($_POST['password'] ?? '') === '') {
        $error = 'Ingresa tu usuario y contraseña.';
    } else {
        [$ok, $error] = intentar_login($userVal, $_POST['password']);
        if ($ok) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
        usleep(300000);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#5b5ef4"><link rel="manifest" href="<?= BASE_URL ?>/manifest.php"><link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/pwa/apple-touch-icon.png"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="<?= e(cfg('empresa_nombre', APP_NAME)) ?>"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Iniciar sesión · <?= e(APP_NAME) ?></title>
<link href="<?= BASE_URL ?>/assets/vendor/inter/inter.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--brand:#5b5ef4;--brand2:#8b5cf6;--text:#0f172a;--muted:#64748b;--line:#e2e8f0}
*{box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;margin:0;min-height:100vh;color:var(--text);background:#fff;-webkit-font-smoothing:antialiased}
.wrap{display:grid;grid-template-columns:1.1fr 1fr;min-height:100vh}

/* ---- Lado visual ---- */
.hero{position:relative;overflow:hidden;color:#fff;padding:3rem 3.5rem;display:flex;flex-direction:column;justify-content:space-between;
  background:radial-gradient(1200px 600px at 10% -10%,#7c7ff8 0%,transparent 55%),radial-gradient(900px 600px at 100% 110%,#a855f7 0%,transparent 55%),linear-gradient(135deg,#312e81 0%,#1e1b4b 60%,#0f172a 100%)}
.hero .orb{position:absolute;border-radius:50%;filter:blur(2px);opacity:.5;animation:float 9s ease-in-out infinite}
.hero .o1{width:340px;height:340px;right:-90px;top:12%;background:radial-gradient(circle at 30% 30%,rgba(255,255,255,.22),rgba(255,255,255,0) 70%)}
.hero .o2{width:200px;height:200px;left:-60px;bottom:18%;background:radial-gradient(circle at 30% 30%,rgba(20,184,166,.5),rgba(20,184,166,0) 70%);animation-delay:-3s}
@keyframes float{50%{transform:translateY(-26px) scale(1.04)}}
.hero:after{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:44px 44px;mask-image:radial-gradient(circle at 50% 40%,#000 0%,transparent 75%);pointer-events:none}
.brand{display:flex;align-items:center;gap:.8rem;position:relative;z-index:2}
.logo{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#fff,#c7d2fe);display:grid;place-items:center;color:var(--brand);font-size:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.25)}
.brand b{font-size:1.35rem;letter-spacing:-.02em}
.hero-body{position:relative;z-index:2;max-width:560px}
.hero h1{font-size:clamp(2rem,3.4vw,3.05rem);font-weight:800;letter-spacing:-.04em;line-height:1.08;margin:0 0 1rem}
.hero h1 span{background:linear-gradient(90deg,#a5b4fc,#67e8f9);-webkit-background-clip:text;background-clip:text;color:transparent}
.hero p.lead{color:#c7d2fe;font-size:1.02rem;max-width:480px}
.feats{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-top:2rem}
.feat{display:flex;gap:.75rem;align-items:center;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(8px);border-radius:14px;padding:.8rem .9rem;font-size:.84rem;font-weight:500}
.feat i{width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.14);display:grid;place-items:center;font-size:1rem;flex-shrink:0}

/* Tarjetas flotantes de demostración */
.float-card{position:absolute;z-index:3;background:rgba(255,255,255,.96);color:var(--text);border-radius:16px;padding:.9rem 1.1rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.45);animation:float 7s ease-in-out infinite}
.fc1{right:3rem;top:8.5rem;min-width:190px}
.fc2{right:6.5rem;bottom:6rem;min-width:210px;animation-delay:-2.5s}
.float-card small{color:var(--muted);font-size:.7rem;display:block}
.float-card b{font-size:1.35rem;letter-spacing:-.03em}
.up{color:#059669;font-size:.72rem;font-weight:700}
.mini-bars{display:flex;align-items:flex-end;gap:4px;height:34px;margin-top:.4rem}
.mini-bars i{flex:1;border-radius:4px;background:linear-gradient(180deg,#818cf8,#5b5ef4)}
.hero-foot{position:relative;z-index:2;color:#a5b4fc;font-size:.78rem}

/* ---- Formulario ---- */
.side{display:flex;align-items:center;justify-content:center;padding:2rem;background:#fafbff;position:relative}
.box{width:100%;max-width:410px;animation:up .6s ease both}
@keyframes up{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.box h2{font-size:1.75rem;font-weight:800;letter-spacing:-.035em;margin:0 0 .35rem}
.box .sub{color:var(--muted);margin-bottom:1.8rem}
.field{position:relative;margin-bottom:1.1rem}
.field i.l{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1.05rem;transition:.2s;pointer-events:none}
.field input{width:100%;height:52px;border:1.5px solid var(--line);border-radius:14px;background:#fff;padding:0 3rem 0 2.9rem;font-size:.95rem;color:var(--text);outline:none;transition:.2s}
.field input::placeholder{color:#a3aec2}
.field input:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(91,94,244,.13)}
.field input:focus ~ i.l{color:var(--brand)}
.field .eye{position:absolute;right:.5rem;top:50%;transform:translateY(-50%);border:0;background:none;color:#94a3b8;width:38px;height:38px;border-radius:10px;font-size:1.1rem}
.field .eye:hover{color:var(--brand);background:#f1f5f9}
label.lb{font-size:.8rem;font-weight:600;margin-bottom:.4rem;display:block;color:#334155}
.opts{display:flex;justify-content:space-between;align-items:center;margin:.2rem 0 1.4rem;font-size:.84rem}
.opts a{color:var(--brand);font-weight:600;text-decoration:none}
.form-check-input:checked{background-color:var(--brand);border-color:var(--brand)}
.btn-login{width:100%;height:52px;border:0;border-radius:14px;color:#fff;font-weight:700;font-size:.98rem;background:linear-gradient(135deg,var(--brand),var(--brand2));box-shadow:0 14px 28px -12px rgba(91,94,244,.9);transition:.2s;display:flex;align-items:center;justify-content:center;gap:.6rem}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 18px 32px -12px rgba(91,94,244,1)}
.btn-login:disabled{opacity:.8;transform:none}
.alert-x{background:#fff1f2;border:1px solid #fecdd3;color:#be123c;border-radius:12px;padding:.75rem 1rem;font-size:.85rem;display:flex;gap:.6rem;align-items:center;margin-bottom:1.1rem;animation:shake .4s}
@keyframes shake{25%{transform:translateX(-6px)}75%{transform:translateX(6px)}}
.demo{margin-top:1.6rem;padding:.85rem 1rem;border:1.5px dashed #cbd5e1;border-radius:14px;font-size:.8rem;color:var(--muted);display:flex;align-items:center;gap:.7rem;cursor:pointer;transition:.2s;background:#fff}
.demo:hover{border-color:var(--brand);color:var(--brand)}
.demo code{background:#eef0ff;color:var(--brand);padding:.1rem .4rem;border-radius:6px}
.foot{position:absolute;bottom:1.2rem;left:0;right:0;text-align:center;font-size:.76rem;color:#94a3b8}
.sec-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.72rem;color:#059669;background:#ecfdf5;padding:.25rem .65rem;border-radius:20px;font-weight:600;margin-bottom:1rem}
@media (max-width:991px){.wrap{grid-template-columns:1fr}.hero{display:none}.side{min-height:100vh}}
@media (max-width:991px){.mobile-brand{display:flex!important}}
.mobile-brand{display:none;align-items:center;gap:.7rem;margin-bottom:2rem}
.mobile-brand .logo{width:42px;height:42px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff}
</style>
</head>
<body>
<div class="wrap">
  <section class="hero">
    <span class="orb o1"></span><span class="orb o2"></span>
    <div class="brand"><div class="logo" style="overflow:hidden"><?php if ($lg = logo_url()): ?><img src="<?= e($lg) ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain;padding:4px"><?php else: ?><i class="bi bi-shop-window"></i><?php endif; ?></div><b><?= e(cfg('empresa_nombre', APP_NAME)) ?></b></div>

    <div class="float-card fc1"><small>Ventas de hoy</small><b><?= e(money(2845.6)) ?></b> <span class="up"><i class="bi bi-arrow-up-right"></i> 12.4%</span>
      <div class="mini-bars"><i style="height:35%"></i><i style="height:55%"></i><i style="height:42%"></i><i style="height:70%"></i><i style="height:58%"></i><i style="height:85%"></i><i style="height:100%"></i></div></div>
    <div class="float-card fc2"><div class="d-flex align-items-center gap-2"><span style="width:38px;height:38px;border-radius:11px;background:#ecfdf5;color:#059669;display:grid;place-items:center"><i class="bi bi-check2-circle fs-5"></i></span>
      <div><small>Venta registrada</small><b style="font-size:1rem">B001-000482</b></div></div></div>

    <div class="hero-body">
      <h1>Controla tu tienda <span>en tiempo real</span>, vende más rápido.</h1>
      <p class="lead">Punto de venta, inventario, caja, compras y reportes en un solo lugar. Diseñado para el día a día de tu negocio.</p>
      <div class="feats">
        <div class="feat"><i class="bi bi-lightning-charge-fill"></i>Ventas ágiles con lector de barras</div>
        <div class="feat"><i class="bi bi-box-seam-fill"></i>Inventario y alertas de stock</div>
        <div class="feat"><i class="bi bi-safe2-fill"></i>Control de caja y arqueo</div>
        <div class="feat"><i class="bi bi-bar-chart-line-fill"></i>Reportes y utilidades</div>
      </div>
    </div>
    <div class="hero-foot">© <?= date('Y') ?> <?= e(APP_NAME) ?> · Sistema de ventas v<?= APP_VERSION ?></div>
  </section>

  <section class="side">
    <div class="box">
      <div class="mobile-brand"><div class="logo" style="overflow:hidden"><?php if ($lg = logo_url()): ?><img src="<?= e($lg) ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain;padding:3px"><?php else: ?><i class="bi bi-shop-window"></i><?php endif; ?></div><b class="fs-4"><?= e(cfg('empresa_nombre', APP_NAME)) ?></b></div>
      <span class="sec-badge"><i class="bi bi-shield-check"></i> Conexión segura</span>
      <h2>Bienvenido de nuevo</h2>
      <p class="sub">Ingresa tus credenciales para acceder al sistema.</p>

      <?php if ($error): ?><div class="alert-x"><i class="bi bi-exclamation-octagon-fill"></i><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="on" id="loginForm" novalidate>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label class="lb" for="usuario">Usuario</label>
        <div class="field"><input type="text" id="usuario" name="usuario" placeholder="Tu nombre de usuario" value="<?= e($userVal) ?>" autocomplete="username" autofocus required><i class="bi bi-person l"></i></div>
        <label class="lb" for="password">Contraseña</label>
        <div class="field"><input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required><i class="bi bi-lock l"></i>
          <button type="button" class="eye" id="eye" tabindex="-1" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button></div>
        <div class="opts">
          <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="rec"><label class="form-check-label" for="rec">Recordar usuario</label></div>
          <a href="#" onclick="alert('Contacta al administrador para restablecer tu contraseña.');return false">¿Olvidaste tu contraseña?</a>
        </div>
        <button class="btn-login" id="btn" type="submit"><span>Ingresar al sistema</span><i class="bi bi-arrow-right"></i></button>
      </form>

      <div class="demo" id="demo" title="Autocompletar"><i class="bi bi-magic fs-5"></i>
        <div>Acceso de demostración<br><code>admin</code> / <code>admin123</code></div></div>
    </div>
    <div class="foot">Caps Lock <span id="caps" class="text-danger fw-semibold" style="display:none">activado</span><span id="capsOff">desactivado</span></div>
  </section>
</div>

<script>
const $ = (s) => document.querySelector(s), f = $('#loginForm');
try { const s = localStorage.getItem('st-user'); if (s && !$('#usuario').value) { $('#usuario').value = s; $('#rec').checked = true; $('#password').focus(); } } catch (e) {}
$('#eye').onclick = () => { const p = $('#password'), s = p.type === 'password'; p.type = s ? 'text' : 'password'; $('#eye i').className = 'bi bi-eye' + (s ? '-slash' : ''); };
$('#demo').onclick = () => { $('#usuario').value = 'admin'; $('#password').value = 'admin123'; $('#password').focus(); };
document.addEventListener('keyup', (e) => { if (!e.getModifierState) return; const on = e.getModifierState('CapsLock'); $('#caps').style.display = on ? '' : 'none'; $('#capsOff').style.display = on ? 'none' : ''; });
f.addEventListener('submit', (e) => {
  if (!$('#usuario').value.trim() || !$('#password').value) { e.preventDefault(); $('#usuario').focus(); return; }
  try { $('#rec').checked ? localStorage.setItem('st-user', $('#usuario').value) : localStorage.removeItem('st-user'); } catch (x) {}
  $('#btn').disabled = true; $('#btn').innerHTML = '<span class="spinner-border spinner-border-sm"></span><span>Verificando…</span>';
});
</script>
<script>window.APP=window.APP||{base:"<?= BASE_URL ?>"}</script><script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>
</body>
</html>
