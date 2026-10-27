<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 2], $a === 'anular');
$u = usuario(); $pdo = db();

switch ($a) {
case 'list':
    $w = ['1=1' . suc_where('v')]; $p = [];
    if ($u['rol_id'] != 1) { $w[] = 'v.usuario_id=?'; $p[] = $u['id']; }
    if (!empty($_GET['desde'])) { $w[] = 'DATE(v.fecha)>=?'; $p[] = $_GET['desde']; }
    if (!empty($_GET['hasta'])) { $w[] = 'DATE(v.fecha)<=?'; $p[] = $_GET['hasta']; }
    if (!empty($_GET['estado'])) { $w[] = 'v.estado=?'; $p[] = $_GET['estado']; }
    if (!empty($_GET['metodo'])) { $w[] = 'v.metodo_pago=?'; $p[] = $_GET['metodo']; }
    if (!empty($_GET['tipo'])) { $w[] = 'v.tipo_comprobante=?'; $p[] = $_GET['tipo']; }
    if (($s = trim($_GET['q'] ?? '')) !== '') { $w[] = "(CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) LIKE ? OR c.nombre LIKE ?)"; array_push($p, "%$s%", "%$s%"); }
    $W = implode(' AND ', $w);
    $from = "FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios us ON us.id=v.usuario_id WHERE $W";
    $per = 12; $page = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)q("SELECT COUNT(*) $from", $p)->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
    $rows = q("SELECT v.id, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, v.tipo_comprobante tipo, v.fecha, v.total, v.metodo_pago, v.estado, COALESCE(c.nombre,'Cliente Varios') cliente, CONCAT(us.nombres,' ',us.apellidos) cajero, v.sucursal_id, (SELECT nombre FROM sucursales WHERE id=v.sucursal_id) sucursal, COALESCE((SELECT SUM(total) FROM devoluciones dv WHERE dv.venta_id=v.id),0) devuelto
              $from ORDER BY v.fecha DESC, v.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p)->fetchAll();
    $sum = q("SELECT COALESCE(SUM(CASE WHEN v.estado='COMPLETADA' THEN v.total END),0) total, COUNT(CASE WHEN v.estado='COMPLETADA' THEN 1 END) n, COUNT(CASE WHEN v.estado='ANULADA' THEN 1 END) anuladas $from", $p)->fetch();
    json_out(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'sum' => $sum, 'admin' => $u['rol_id'] == 1, 'todas' => suc() === 0]);

case 'get':
    $id = (int)($_GET['id'] ?? 0);
    $v = q("SELECT v.*, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, COALESCE(c.nombre,'Cliente Varios') cliente, c.documento, CONCAT(us.nombres,' ',us.apellidos) cajero FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios us ON us.id=v.usuario_id WHERE v.id=?" . suc_where('v'), [$id])->fetch();
    if (!$v || ($u['rol_id'] != 1 && $v['usuario_id'] != $u['id'])) json_fail('Venta no encontrada', 404);
    $v['items'] = q('SELECT d.producto_id, d.cantidad, d.precio_unitario, d.descuento, d.promo, d.subtotal, p.nombre, p.unidad, COALESCE((SELECT SUM(dd.cantidad) FROM detalle_devoluciones dd JOIN devoluciones dv ON dv.id=dd.devolucion_id WHERE dv.venta_id=d.venta_id AND dd.producto_id=d.producto_id),0) devuelta FROM detalle_ventas d JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?', [$id])->fetchAll();
    $v['telefono'] = $v['cliente_id'] ? q('SELECT telefono FROM clientes WHERE id=?', [$v['cliente_id']])->fetchColumn() : null;
    json_out(['ok' => true, 'venta' => $v]);

case 'anular':
    if ($u['rol_id'] != 1) json_fail('Solo un administrador puede anular ventas.', 403);
    $id = (int)(body()['id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $v = q('SELECT * FROM ventas WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$v) throw new RuntimeException('Venta no encontrada.');
        if ($v['estado'] !== 'COMPLETADA') throw new RuntimeException('La venta ya está anulada.');
        $nro = $v['serie'] . '-' . str_pad((string)$v['numero'], 6, '0', STR_PAD_LEFT);
        if (q('SELECT COUNT(*) FROM devoluciones WHERE venta_id=?', [$id])->fetchColumn() > 0) throw new RuntimeException('La venta tiene devoluciones registradas; no se puede anular.');
        foreach (q('SELECT * FROM detalle_ventas WHERE venta_id=?', [$id])->fetchAll() as $d) {
            stock_mover((int)$d['producto_id'], (int)$v['sucursal_id'], (float)$d['cantidad']);
            kardex((int)$d['producto_id'], 'ENTRADA', (float)$d['cantidad'], "Anulación $nro", (int)$v['sucursal_id']);
        }
        if ($v['cliente_id'] && $v['cliente_id'] != 1 && ($v['puntos_ganados'] || $v['puntos_canjeados'])) {
            q('UPDATE clientes SET puntos = GREATEST(0, puntos - ? + ?) WHERE id=?', [$v['puntos_ganados'], $v['puntos_canjeados'], $v['cliente_id']]);
            q('INSERT INTO movimientos_puntos (cliente_id,venta_id,puntos,motivo) VALUES (?,?,?,?)', [$v['cliente_id'], $id, $v['puntos_canjeados'] - $v['puntos_ganados'], "Anulación $nro"]);
        }
        if ($v['cotizacion_id']) q("UPDATE cotizaciones SET estado='PENDIENTE', venta_id=NULL WHERE id=?", [$v['cotizacion_id']]);
        audit('ANULAR_VENTA', 'venta', $id, $nro);
        if ($v['metodo_pago'] === 'CREDITO') q('UPDATE clientes SET saldo_deuda = GREATEST(0, saldo_deuda - ?) WHERE id=?', [$v['total'], $v['cliente_id']]);
        q("UPDATE ventas SET estado='ANULADA' WHERE id=?", [$id]);
        $pdo->commit(); json_out(['ok' => true]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
}
json_fail('Acción no válida');
