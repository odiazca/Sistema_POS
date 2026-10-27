<?php
require_once __DIR__ . '/../includes/helpers.php';
api_guard();
$t = trim($_GET['q'] ?? ''); $l = '%' . $t . '%';
$r = ['productos' => [], 'clientes' => [], 'ventas' => []];
if (strlen($t) >= 2) {
    $r['productos'] = q('SELECT codigo,nombre,precio_venta FROM productos WHERE estado=1 AND (nombre LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ?) LIMIT 5', [$l, $l, $l])->fetchAll();
    if (can('clientes')) $r['clientes'] = q('SELECT nombre,documento FROM clientes WHERE estado=1 AND (nombre LIKE ? OR documento LIKE ?) LIMIT 4', [$l, $l])->fetchAll();
    if (can('ventas')) $r['ventas'] = q("SELECT CONCAT(serie,'-',LPAD(numero,6,'0')) nro,total FROM ventas v WHERE CONCAT(serie,'-',LPAD(numero,6,'0')) LIKE ?" . suc_where('v') . " ORDER BY id DESC LIMIT 4", [$l])->fetchAll();
}
json_out($r);
