<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'kardex');
api_guard([1, 3], $a === 'ajuste');

$pdo = db();

switch ($a) {
case 'productos':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT p.id,p.codigo,p.nombre,p.unidad,' . stock_col() . ' AS stock FROM productos p WHERE (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?) ORDER BY p.nombre LIMIT 10', [$s, $s, $s])->fetchAll()]);

case 'kardex':
    $w = ['1=1' . suc_where('m')]; $p = [];
    if (!empty($_GET['producto'])) { $w[] = 'm.producto_id=?'; $p[] = (int)$_GET['producto']; }
    if (!empty($_GET['tipo'])) { $w[] = 'm.tipo=?'; $p[] = $_GET['tipo']; }
    if (!empty($_GET['desde'])) { $w[] = 'DATE(m.fecha)>=?'; $p[] = $_GET['desde']; }
    if (!empty($_GET['hasta'])) { $w[] = 'DATE(m.fecha)<=?'; $p[] = $_GET['hasta']; }
    $from = 'FROM movimientos_inventario m JOIN productos p ON p.id=m.producto_id LEFT JOIN usuarios u ON u.id=m.usuario_id WHERE ' . implode(' AND ', $w);
    $per = 15; $page = max(1, (int)($_GET['page'] ?? 1)); $total = (int)q("SELECT COUNT(*) $from", $p)->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
    $rows = q("SELECT m.id,m.fecha,m.tipo,m.cantidad,m.stock_resultante,m.motivo,p.nombre,p.codigo,p.unidad,u.usuario,(SELECT nombre FROM sucursales WHERE id=m.sucursal_id) sucursal $from ORDER BY m.fecha DESC, m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p)->fetchAll();
    json_out(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per]);

case 'ajuste':
    $b = body(); $modo = $b['modo'] ?? ''; $cant = (float)($b['cantidad'] ?? -1); $motivo = trim($b['motivo'] ?? '');
    if (!in_array($modo, ['ENTRADA', 'SALIDA', 'CONTEO'], true)) json_fail('Tipo de ajuste inválido.');
    if ($cant < 0 || ($modo !== 'CONTEO' && $cant == 0)) json_fail('Ingresa una cantidad válida.');
    if ($motivo === '') json_fail('Indica el motivo del ajuste (merma, vencido, conteo, etc.).');
    $sc = suc_w();
    $pdo->beginTransaction();
    try {
        $p = q('SELECT * FROM productos WHERE id=? FOR UPDATE', [(int)($b['producto_id'] ?? 0)])->fetch();
        if (!$p) throw new RuntimeException('Producto no encontrado.');
        $st = stock_de((int)$p['id'], $sc, true);
        if ($modo === 'ENTRADA') { stock_mover((int)$p['id'], $sc, $cant); kardex((int)$p['id'], 'ENTRADA', $cant, "Ajuste: $motivo"); }
        elseif ($modo === 'SALIDA') {
            if ($cant > $st) throw new RuntimeException('La salida supera el stock de esta sucursal (' . $st . ').');
            stock_mover((int)$p['id'], $sc, -$cant); kardex((int)$p['id'], 'SALIDA', $cant, "Ajuste: $motivo");
        } else {
            $dif = round($cant - $st, 2); if ($dif == 0) throw new RuntimeException('El conteo coincide con el stock actual; no hay nada que ajustar.');
            stock_mover((int)$p['id'], $sc, $dif); kardex((int)$p['id'], 'AJUSTE', $dif, "Conteo físico: $motivo");
        }
        audit('AJUSTE_STOCK', 'producto', (int)$p['id'], "$modo $cant · {$p['nombre']} · $motivo");
        $pdo->commit(); json_out(['ok' => true]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }

case 'alertas':
    $tipo = $_GET['tipo'] ?? 'bajo';
    if ($tipo === 'bajo') { $sc = stock_col(); $rows = q("SELECT p.id,p.codigo,p.nombre,p.unidad,$sc AS stock,p.stock_minimo,p.precio_compra,c.nombre categoria,c.color,GREATEST(p.stock_minimo*2-$sc,0) sugerido FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.estado=1 AND $sc<=p.stock_minimo ORDER BY $sc/NULLIF(p.stock_minimo,0), p.nombre")->fetchAll(); }
    else { $d = max(1, min(365, (int)($_GET['dias'] ?? 30)));
        $sc = stock_col(); $rows = q("SELECT p.id,p.codigo,p.nombre,p.unidad,$sc AS stock,p.fecha_vencimiento,DATEDIFF(p.fecha_vencimiento,CURDATE()) dias,p.precio_compra,c.nombre categoria,c.color FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.estado=1 AND $sc>0 AND p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento<=CURDATE()+INTERVAL $d DAY ORDER BY p.fecha_vencimiento")->fetchAll(); }
    json_out(['ok' => true, 'rows' => $rows]);
}
json_fail('Acción no válida');
