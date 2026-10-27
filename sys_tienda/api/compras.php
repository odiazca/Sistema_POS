<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 3], in_array($a, ['save', 'anular'], true));
$pdo = db(); $u = usuario();

switch ($a) {
case 'productos':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT p.id,p.codigo,p.nombre,p.unidad,p.precio_compra,' . stock_col() . ' AS stock FROM productos p WHERE p.estado=1 AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?) ORDER BY p.nombre LIMIT 10', [$s, $s, $s])->fetchAll()]);

case 'list':
    $w = ['1=1' . suc_where('c')]; $p = [];
    if (!empty($_GET['desde'])) { $w[] = 'c.fecha>=?'; $p[] = $_GET['desde']; }
    if (!empty($_GET['hasta'])) { $w[] = 'c.fecha<=?'; $p[] = $_GET['hasta']; }
    if (!empty($_GET['proveedor'])) { $w[] = 'c.proveedor_id=?'; $p[] = (int)$_GET['proveedor']; }
    if (($s = trim($_GET['q'] ?? '')) !== '') { $w[] = '(c.comprobante LIKE ? OR pr.razon_social LIKE ?)'; array_push($p, "%$s%", "%$s%"); }
    $from = 'FROM compras c JOIN proveedores pr ON pr.id=c.proveedor_id JOIN usuarios us ON us.id=c.usuario_id WHERE ' . implode(' AND ', $w);
    $per = 12; $page = max(1, (int)($_GET['page'] ?? 1)); $total = (int)q("SELECT COUNT(*) $from", $p)->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
    $rows = q("SELECT c.id,c.comprobante,c.fecha,c.total,c.estado,pr.razon_social proveedor,(SELECT nombre FROM sucursales WHERE id=c.sucursal_id) sucursal,CONCAT(us.nombres,' ',us.apellidos) usuario,(SELECT COUNT(*) FROM detalle_compras d WHERE d.compra_id=c.id) items $from ORDER BY c.fecha DESC,c.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p)->fetchAll();
    $sum = (float)q("SELECT COALESCE(SUM(CASE WHEN c.estado='RECIBIDA' THEN c.total END),0) $from", $p)->fetchColumn();
    json_out(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'sum' => $sum]);

case 'get':
    $c = q('SELECT c.*, pr.razon_social proveedor FROM compras c JOIN proveedores pr ON pr.id=c.proveedor_id WHERE c.id=?', [(int)($_GET['id'] ?? 0)])->fetch();
    if (!$c) json_fail('Compra no encontrada', 404);
    $c['items'] = q('SELECT d.*, p.nombre, p.unidad FROM detalle_compras d JOIN productos p ON p.id=d.producto_id WHERE d.compra_id=?', [$c['id']])->fetchAll();
    json_out(['ok' => true, 'compra' => $c]);

case 'save':
    $b = body(); $items = $b['items'] ?? []; $sc = suc_w();
    if (!$items) json_fail('Agrega al menos un producto.');
    if (!q('SELECT 1 FROM proveedores WHERE id=? AND estado=1', [(int)($b['proveedor_id'] ?? 0)])->fetch()) json_fail('Selecciona un proveedor válido.');
    $fecha = $b['fecha'] ?? date('Y-m-d'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) json_fail('Fecha inválida.');
    $pdo->beginTransaction();
    try {
        $tot = 0; $lines = [];
        foreach ($items as $it) {
            $cant = (float)($it['cant'] ?? 0); $costo = (float)($it['costo'] ?? 0);
            if ($cant <= 0 || $costo < 0) throw new RuntimeException('Revisa las cantidades y costos.');
            if (!q('SELECT 1 FROM productos WHERE id=?', [(int)$it['id']])->fetch()) throw new RuntimeException('Producto no válido.');
            $sub = round($cant * $costo, 2); $tot += $sub; $lines[] = [(int)$it['id'], $cant, $costo, $sub];
        }
        q('INSERT INTO compras (sucursal_id,proveedor_id,usuario_id,comprobante,fecha,total) VALUES (?,?,?,?,?,?)', [$sc, (int)$b['proveedor_id'], $u['id'], mb_substr(trim($b['comprobante'] ?? ''), 0, 40) ?: null, $fecha, round($tot, 2)]);
        $cid = (int)$pdo->lastInsertId();
        foreach ($lines as [$pid, $cant, $costo, $sub]) {
            q('INSERT INTO detalle_compras (compra_id,producto_id,cantidad,costo_unitario,subtotal) VALUES (?,?,?,?,?)', [$cid, $pid, $cant, $costo, $sub]);
            q('UPDATE productos SET precio_compra = ? WHERE id=?', [$costo, $pid]);
            stock_mover($pid, $sc, $cant);
            kardex($pid, 'ENTRADA', $cant, "Compra #$cid", $sc);
        }
        audit('COMPRA', 'compra', $cid, 'Total ' . money($tot));
        $pdo->commit(); json_out(['ok' => true, 'id' => $cid, 'total' => round($tot, 2)]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }

case 'anular':
    if ($u['rol_id'] != 1) json_fail('Solo un administrador puede anular compras.', 403);
    $id = (int)(body()['id'] ?? 0); $pdo->beginTransaction();
    try {
        $c = q('SELECT * FROM compras WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$c || $c['estado'] !== 'RECIBIDA') throw new RuntimeException('La compra no existe o ya está anulada.');
        foreach (q('SELECT d.*, p.nombre FROM detalle_compras d JOIN productos p ON p.id=d.producto_id WHERE d.compra_id=?', [$id])->fetchAll() as $d) {
            $st = stock_de((int)$d['producto_id'], (int)$c['sucursal_id'], true);
            if ($st < $d['cantidad']) throw new RuntimeException("No se puede anular: ya se vendió parte de «{$d['nombre']}» (stock actual $st).");
            stock_mover((int)$d['producto_id'], (int)$c['sucursal_id'], -(float)$d['cantidad']);
            kardex((int)$d['producto_id'], 'SALIDA', (float)$d['cantidad'], "Anulación compra #$id", (int)$c['sucursal_id']);
        }
        audit('ANULAR_COMPRA', 'compra', $id);
        q("UPDATE compras SET estado='ANULADA' WHERE id=?", [$id]); $pdo->commit(); json_out(['ok' => true]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
}
json_fail('Acción no válida');
