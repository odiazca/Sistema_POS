<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/menu.php';
require_login();
require_once __DIR__ . '/backup.php';
backup_hook();
$u = usuario();
$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeKey  = $activeKey  ?? 'dashboard';
$activeSub  = $activeSub  ?? '';
$empresa = db()->query("SELECT valor FROM configuracion WHERE clave='empresa_nombre'")->fetchColumn() ?: APP_NAME;
$stockBajo = (int)db()->query('SELECT COUNT(*) FROM productos p WHERE p.estado=1 AND ' . stock_col() . '<=p.stock_minimo')->fetchColumn();
$sucActiva = suc(); $sucLista = sucursales_lista();
$tareasCrm = 0; if (can('crm-tareas')) { try { $tareasCrm = (int)q("SELECT COUNT(*) FROM crm_tareas WHERE estado='PENDIENTE' AND vence IS NOT NULL AND vence<=NOW() AND asignado_id=?", [$u['id']])->fetchColumn(); } catch (Throwable $e) {} }
?>
<!doctype html>
<html lang="es" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#5b5ef4"><link rel="manifest" href="<?= BASE_URL ?>/manifest.php"><link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/pwa/apple-touch-icon.png"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="<?= e(cfg('empresa_nombre', APP_NAME)) ?>"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?php if ($lg = logo_url()): ?><link rel="icon" href="<?= e($lg) ?>"><?php endif; ?>
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link href="<?= BASE_URL ?>/assets/vendor/inter/inter.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/vendor/tomselect/tom-select.bootstrap5.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= ASSET_V ?>" rel="stylesheet">
<?php foreach (($extraCss ?? []) as $c): ?><link href="<?= $c ?>" rel="stylesheet"><?php endforeach; ?>
<script>try{var t=localStorage.getItem('st-theme');if(t)document.documentElement.setAttribute('data-theme',t);if(localStorage.getItem('st-mini')==='1'&&innerWidth>991)document.documentElement.classList.add('pre-mini')}catch(e){}</script>
</head>
<body>
<script>if(document.documentElement.classList.contains('pre-mini'))document.body.classList.add('sb-mini')</script>

<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <?php if ($lg = logo_url()): ?><div class="sb-logo" style="background:#fff;padding:3px;overflow:hidden"><img src="<?= e($lg) ?>" alt="" style="max-width:100%;max-height:100%;object-fit:contain"></div><?php else: ?><div class="sb-logo"><i class="bi bi-shop-window"></i></div><?php endif; ?>
    <div><b><?= e($empresa) ?></b><small>Punto de venta</small></div>
  </div>
  <nav class="sb-scroll">
  <?php foreach (menu_visible() as $idx => $m): ?>
    <?php if (isset($m['label'])): ?><div class="sb-label"><?= e($m['label']) ?></div><?php continue; endif; ?>
    <?php if (empty($m['sub'])): ?>
      <a class="sb-link <?= $activeKey === $m['k'] ? 'active' : '' ?>" href="<?= BASE_URL . '/' . $m['u'] ?>" title="<?= e($m['t']) ?>">
        <i class="bi <?= $m['i'] ?> ic"></i><span><?= e($m['t']) ?></span>
        <?php if (!empty($m['badge'])): ?><em class="sb-badge <?= $m['badge_class'] ?? '' ?>" style="font-style:normal"><?= e($m['badge']) ?></em><?php endif; ?>
      </a>
    <?php else: $open = $activeKey === $m['k']; $badge = ($m['k'] === 'almacen' && $stockBajo) ? $stockBajo : null; ?>
      <a class="sb-link <?= $open ? 'active' : '' ?>" data-bs-toggle="collapse" href="#sm<?= $idx ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" title="<?= e($m['t']) ?>">
        <i class="bi <?= $m['i'] ?> ic"></i><span><?= e($m['t']) ?></span>
        <?php if ($badge): ?><em class="sb-badge" style="font-style:normal"><?= $badge ?></em><?php endif; ?>
        <i class="bi bi-chevron-right chev"></i>
      </a>
      <div class="collapse <?= $open ? 'show' : '' ?>" id="sm<?= $idx ?>"><div class="sb-sub">
        <?php foreach ($m['sub'] as $s): ?>
          <a href="<?= BASE_URL ?>/modulo.php?m=<?= $s[1] ?>" class="<?= $activeSub === $s[1] ? 'active' : '' ?>"><?= e($s[0]) ?></a>
        <?php endforeach; ?>
      </div></div>
    <?php endif; ?>
  <?php endforeach; ?>
  </nav>
  <div class="sb-user">
    <div class="avatar"><?= e($u['iniciales']) ?></div>
    <div style="min-width:0"><b><?= e($u['nombre']) ?></b><small><?= e($u['rol']) ?></small></div>
    <a href="<?= BASE_URL ?>/logout.php" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>
<div class="sb-backdrop" onclick="document.body.classList.remove('sb-open')"></div>

<div class="main">
  <header class="topbar">
    <button class="icon-btn" id="btnToggle" aria-label="Menú"><i class="bi bi-list fs-5"></i></button>
    <div class="search">
      <i class="bi bi-search"></i>
      <input type="text" placeholder="Buscar producto, cliente o comprobante…" id="globalSearch" autocomplete="off">
      <div id="searchDrop" class="shadow" style="display:none"></div>
      <kbd>Ctrl K</kbd>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($u['rol_id'] === ROL_ADMIN && count($sucLista) > 1): ?>
      <select class="form-select form-select-sm suc-select" id="sucSelect" title="Sucursal activa">
        <option value="0" <?= $sucActiva === 0 ? 'selected' : '' ?>>Todas las sucursales</option>
        <?php foreach ($sucLista as $sl): ?><option value="<?= $sl['id'] ?>" <?= $sucActiva === (int)$sl['id'] ? 'selected' : '' ?>><?= e($sl['nombre']) ?></option><?php endforeach; ?>
      </select>
      <?php else: ?>
      <span class="suc-chip" title="Sucursal"><i class="bi bi-shop"></i><?= e(suc_nombre()) ?></span>
      <?php endif; ?>
      <div class="top-clock"><b id="clock">--:--</b><small id="dateTxt"></small></div>
      <a class="btn-pos" href="<?= BASE_URL ?>/modulo.php?m=pos"><i class="bi bi-lightning-charge-fill"></i><span>Nueva venta</span></a>
      <button class="icon-btn" id="btnTheme" title="Cambiar tema"><i class="bi bi-moon-stars"></i></button>
      <div class="dropdown">
        <button class="icon-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i><?php if ($stockBajo || $tareasCrm): ?><span class="dot"></span><?php endif; ?></button>
        <div class="dropdown-menu dropdown-menu-end p-2 shadow border-0" style="min-width:290px;border-radius:14px">
          <div class="px-2 py-1 fw-bold small">Notificaciones</div>
          <?php if ($stockBajo): ?>
          <a class="dropdown-item rounded-3 d-flex gap-2 align-items-start py-2" href="<?= BASE_URL ?>/modulo.php?m=stock-bajo">
            <span class="kpi-ic ic-rose" style="width:34px;height:34px;font-size:1rem"><i class="bi bi-exclamation-triangle-fill"></i></span>
            <span><b class="small d-block"><?= $stockBajo ?> productos con stock bajo</b><small class="text-muted">Revisa tu inventario</small></span></a>
          <?php endif; ?>
          <?php if ($tareasCrm): ?>
          <a class="dropdown-item rounded-3 d-flex gap-2 align-items-start py-2" href="<?= BASE_URL ?>/modulo.php?m=crm-tareas">
            <span class="kpi-ic ic-amber" style="width:34px;height:34px;font-size:1rem"><i class="bi bi-alarm-fill"></i></span>
            <span><b class="small d-block"><?= $tareasCrm ?> tarea(s) de CRM por atender</b><small class="text-muted">Seguimiento a clientes</small></span></a>
          <?php endif; ?>
          <a class="dropdown-item rounded-3 d-flex gap-2 align-items-start py-2" href="#">
            <span class="kpi-ic ic-green" style="width:34px;height:34px;font-size:1rem"><i class="bi bi-check2-circle"></i></span>
            <span><b class="small d-block">Sistema funcionando</b><small class="text-muted">Todo en orden</small></span></a>
        </div>
      </div>
      <div class="dropdown">
        <button class="avatar border-0" data-bs-toggle="dropdown" style="width:40px;height:40px"><?= e($u['iniciales']) ?></button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="border-radius:14px;min-width:210px">
          <li class="px-2 py-1"><b class="d-block"><?= e($u['nombre']) ?></b><small class="text-muted"><?= e($u['rol']) ?> · @<?= e($u['usuario']) ?></small></li>
          <li><hr class="dropdown-divider"></li>
          
          <li data-pwa-install hidden><a class="dropdown-item rounded-2" href="#"><i class="bi bi-phone me-2"></i>Instalar aplicación</a></li>
          <li><a class="dropdown-item rounded-2" href="#" data-bs-toggle="modal" data-bs-target="#mdlPass"><i class="bi bi-key me-2"></i>Cambiar contraseña</a></li>
          <li><a class="dropdown-item rounded-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="content">
