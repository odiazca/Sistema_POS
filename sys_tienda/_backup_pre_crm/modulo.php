<?php
/** Enrutador de módulos: modulo.php?m=<slug> */
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/includes/menu.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['m'] ?? '');

$crud = ['productos', 'categorias', 'marcas', 'proveedores', 'clientes', 'gastos', 'usuarios', 'sucursales', 'promociones'];
$files = [
    'pos' => 'pos', 'ventas' => 'ventas', 'caja' => 'caja', 'compras-nueva' => 'compra_nueva', 'compras' => 'compras',
    'kardex' => 'kardex', 'stock-bajo' => 'alertas', 'vencimientos' => 'alertas', 'creditos' => 'creditos', 'config' => 'config', 'empresa' => 'empresa', 'asistente' => 'asistente', 'chat' => 'chat', 'respaldos' => 'respaldos',
    'rep-ventas' => 'reportes', 'rep-utilidad' => 'reportes', 'rep-top' => 'reportes', 'rep-cajero' => 'reportes', 'rep-inventario' => 'reportes', 'rep-sucursales' => 'reportes',
    'traspasos' => 'traspasos', 'cotizaciones' => 'cotizaciones', 'devoluciones' => 'devoluciones', 'auditoria' => 'auditoria', 'etiquetas' => 'etiquetas', 'importar' => 'importar',
];

$file = in_array($slug, $crud, true) ? 'crud' : ($files[$slug] ?? null);
if (!$file) { http_response_code(404); }
if ($file && !can($slug)) { http_response_code(403); $file = 'denegado'; }

$titulo = modulo_titulo($slug);
$activeKey = $slug; $activeSub = $slug;
foreach (menu_items() as $m) foreach ($m['sub'] ?? [] as $s) if ($s[1] === $slug) $activeKey = $m['k'];
$pageTitle = $titulo; $extraJs = []; $extraCss = [];

require __DIR__ . '/includes/header.php';
if (!$file): ?>
  <div class="cardx soon"><div><div class="ico"><i class="bi bi-compass"></i></div><h4 class="fw-bold">Página no encontrada</h4>
  <a href="<?= BASE_URL ?>/dashboard.php" class="btn-pos mt-3"><i class="bi bi-arrow-left"></i> Volver al dashboard</a></div></div>
<?php elseif ($file === 'denegado'): ?>
  <div class="cardx soon"><div><div class="ico" style="color:var(--rose);background:rgba(244,63,94,.12)"><i class="bi bi-shield-lock"></i></div><h4 class="fw-bold">Acceso restringido</h4>
  <p class="text-muted">Tu rol (<?= e(usuario()['rol']) ?>) no tiene permiso para este módulo.</p>
  <a href="<?= BASE_URL ?>/dashboard.php" class="btn-pos mt-2"><i class="bi bi-arrow-left"></i> Volver</a></div></div>
<?php else: require __DIR__ . "/modules/$file.php"; endif;
require __DIR__ . '/includes/footer.php';
