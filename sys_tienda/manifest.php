<?php
/** Manifiesto PWA dinámico: toma el nombre de la empresa de la configuración */
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$nom = cfg('empresa_nombre', APP_NAME) ?: APP_NAME;
$b = BASE_URL;
$ic = fn($f, $s, $p = 'any') => ['src' => "$b/assets/pwa/$f", 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => $p];
echo json_encode([
    'id' => "$b/",
    'name' => $nom . ' · Punto de venta',
    'short_name' => mb_substr($nom, 0, 12),
    'description' => 'Sistema de punto de venta e inventario',
    'lang' => 'es',
    'dir' => 'ltr',
    'start_url' => "$b/dashboard.php?utm=pwa",
    'scope' => "$b/",
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation' => 'any',
    'background_color' => '#f4f6fb',
    'theme_color' => '#5b5ef4',
    'categories' => ['business', 'shopping'],
    'icons' => [$ic('icon-192.png', 192), $ic('icon-512.png', 512), $ic('maskable-512.png', 512, 'maskable')],
    'shortcuts' => [
        ['name' => 'Nueva venta', 'url' => "$b/modulo.php?m=pos", 'icons' => [$ic('icon-192.png', 192)]],
        ['name' => 'Caja', 'url' => "$b/modulo.php?m=caja", 'icons' => [$ic('icon-192.png', 192)]],
        ['name' => 'Ventas', 'url' => "$b/modulo.php?m=ventas", 'icons' => [$ic('icon-192.png', 192)]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
