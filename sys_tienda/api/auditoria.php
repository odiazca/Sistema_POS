<?php
require_once __DIR__ . '/../includes/helpers.php';
api_guard([ROL_ADMIN]);
$w = ['1=1']; $p = [];
if (!empty($_GET['desde'])) { $w[] = 'DATE(a.fecha)>=?'; $p[] = $_GET['desde']; }
if (!empty($_GET['hasta'])) { $w[] = 'DATE(a.fecha)<=?'; $p[] = $_GET['hasta']; }
if (!empty($_GET['accion'])) { $w[] = 'a.accion=?'; $p[] = $_GET['accion']; }
if (!empty($_GET['usuario'])) { $w[] = 'a.usuario=?'; $p[] = $_GET['usuario']; }
if (($s = trim($_GET['q'] ?? '')) !== '') { $w[] = '(a.detalle LIKE ? OR a.usuario LIKE ? OR a.accion LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
$from = 'FROM auditoria a LEFT JOIN sucursales s ON s.id=a.sucursal_id WHERE ' . implode(' AND ', $w);
$per = 20; $page = max(1, (int)($_GET['page'] ?? 1)); $total = (int)q("SELECT COUNT(*) $from", $p)->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$rows = q("SELECT a.id,a.fecha,a.usuario,a.accion,a.entidad,a.entidad_id,a.detalle,a.ip,s.nombre sucursal $from ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p)->fetchAll();
json_out(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per,
    'acciones' => q('SELECT DISTINCT accion FROM auditoria ORDER BY accion')->fetchAll(PDO::FETCH_COLUMN), 'usuarios' => q('SELECT DISTINCT usuario FROM auditoria WHERE usuario IS NOT NULL ORDER BY usuario')->fetchAll(PDO::FETCH_COLUMN)]);
