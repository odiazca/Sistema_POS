<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 2], $a === 'save');
$pdo = db(); $u = usuario();

/** Datos de una venta con lo ya devuelto por producto y el valor neto unitario (descuentos incluidos) */
function venta_devolvible(int $id): ?array
{
    $v = q("SELECT v.*, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, COALESCE(c.nombre,'Cliente Varios') cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE v.id=?" . suc_where('v'), [$id])->fetch();
    if (!$v) return null;
    $items = q('SELECT d.producto_id, d.cantidad, d.costo_unitario, d.subtotal, p.nombre, p.unidad,
        COALESCE((SELECT SUM(dd.cantidad) FROM detalle_devoluciones dd JOIN devoluciones dv ON dv.id=dd.devolucion_id WHERE dv.venta_id=d.venta_id AND dd.producto_id=d.producto_id),0) devuelta
        FROM detalle_ventas d JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?', [$id])->fetchAll();
    $sumSub = array_sum(array_column($items, 'subtotal')); $factor = $sumSub > 0 ? $v['total'] / $sumSub : 1;   // reparte descuentos globales (manual/cumpleaños/puntos)
    foreach ($items as &$i) { $i['unit_neto'] = round($i['subtotal'] / $i['cantidad'] * $factor, 4); $i['disponible'] = round($i['cantidad'] - $i['devuelta'], 2); }
    $v['items'] = $items;
    return $v;
}

switch ($a) {
case 'buscar':
    $s = trim($_GET['q'] ?? '');
    $rows = q("SELECT v.id, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, v.fecha, v.total, v.metodo_pago, COALESCE(c.nombre,'Cliente Varios') cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id
        WHERE v.estado='COMPLETADA'" . suc_where('v') . ($s !== '' ? " AND (CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) LIKE ? OR c.nombre LIKE ?)" : '') . ' ORDER BY v.id DESC LIMIT 8', $s !== '' ? ["%$s%", "%$s%"] : [])->fetchAll();
    json_out(['ok' => true, 'rows' => $rows]);

case 'venta':
    $v = venta_devolvible((int)($_GET['id'] ?? 0));
    if (!$v || $v['estado'] !== 'COMPLETADA') json_fail('Venta no encontrada o anulada.', 404);
    json_out(['ok' => true, 'venta' => $v]);

case 'list':
    $rows = q("SELECT dv.id, dv.fecha, dv.total, dv.motivo, dv.reembolso, dv.reintegra_stock, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, CONCAT(us.nombres,' ',us.apellidos) usuario,
        (SELECT nombre FROM sucursales WHERE id=dv.sucursal_id) sucursal, (SELECT COUNT(*) FROM detalle_devoluciones d WHERE d.devolucion_id=dv.id) items
        FROM devoluciones dv JOIN ventas v ON v.id=dv.venta_id JOIN usuarios us ON us.id=dv.usuario_id WHERE 1=1" . suc_where('dv') . ' ORDER BY dv.id DESC LIMIT 80')->fetchAll();
    $sum = (float)q('SELECT COALESCE(SUM(total),0) FROM devoluciones dv WHERE MONTH(dv.fecha)=MONTH(CURDATE()) AND YEAR(dv.fecha)=YEAR(CURDATE())' . suc_where('dv'))->fetchColumn();
    json_out(['ok' => true, 'rows' => $rows, 'mes' => $sum, 'todas' => suc() === 0]);

case 'get':
    $d = q("SELECT dv.*, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro FROM devoluciones dv JOIN ventas v ON v.id=dv.venta_id WHERE dv.id=?" . suc_where('dv'), [(int)($_GET['id'] ?? 0)])->fetch();
    if (!$d) json_fail('Devolución no encontrada', 404);
    $d['items'] = q('SELECT dd.*, p.nombre, p.unidad FROM detalle_devoluciones dd JOIN productos p ON p.id=dd.producto_id WHERE dd.devolucion_id=?', [$d['id']])->fetchAll();
    json_out(['ok' => true, 'dev' => $d]);

case 'save':
    $b = body(); $sc = suc_w(); $vid = (int)($b['venta_id'] ?? 0); $motivo = trim($b['motivo'] ?? '');
    $reint = !empty($b['reintegra_stock']) ? 1 : 0; $reemb = $b['reembolso'] ?? 'EFECTIVO';
    if ($motivo === '') json_fail('Indica el motivo de la devolución.');
    if (!in_array($reemb, ['EFECTIVO', 'MISMO_MEDIO', 'CREDITO'], true)) json_fail('Forma de reembolso inválida.');
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM ventas WHERE id=? FOR UPDATE', [$vid]);
        $v = venta_devolvible($vid);
        if (!$v || $v['estado'] !== 'COMPLETADA') throw new RuntimeException('La venta no existe, está anulada o pertenece a otra sucursal.');
        if ((int)$v['sucursal_id'] !== $sc) throw new RuntimeException('La venta pertenece a otra sucursal.');
        $map = []; foreach ($v['items'] as $i) $map[$i['producto_id']] = $i;
        $lines = []; $tot = 0;
        foreach ($b['items'] ?? [] as $it) {
            $pid = (int)($it['producto_id'] ?? 0); $cant = round((float)($it['cant'] ?? 0), 2); if ($cant <= 0) continue;
            if (!isset($map[$pid])) throw new RuntimeException('Un producto no pertenece a esta venta.');
            if ($cant > $map[$pid]['disponible'] + 0.001) throw new RuntimeException("Solo puedes devolver hasta {$map[$pid]['disponible']} de «{$map[$pid]['nombre']}».");
            $sub = round($cant * $map[$pid]['unit_neto'], 2); $tot += $sub; $lines[] = [$pid, $cant, $map[$pid], $sub];
        }
        if (!$lines) throw new RuntimeException('Indica la cantidad a devolver de al menos un producto.');
        $tot = round($tot, 2);
        // forma de reembolso coherente con el medio de pago original
        if ($v['metodo_pago'] === 'CREDITO') $reemb = 'CREDITO';
        elseif ($reemb === 'CREDITO') throw new RuntimeException('La venta no fue al crédito: elige efectivo o mismo medio de pago.');
        elseif ($reemb === 'MISMO_MEDIO' && $v['metodo_pago'] === 'EFECTIVO') $reemb = 'EFECTIVO';

        q('INSERT INTO devoluciones (sucursal_id,venta_id,usuario_id,total,motivo,reintegra_stock,reembolso) VALUES (?,?,?,?,?,?,?)', [$sc, $vid, $u['id'], $tot, mb_substr($motivo, 0, 200), $reint, $reemb]);
        $did = (int)$pdo->lastInsertId();
        foreach ($lines as [$pid, $cant, $i, $sub]) {
            q('INSERT INTO detalle_devoluciones (devolucion_id,producto_id,cantidad,precio_unitario,costo_unitario,subtotal) VALUES (?,?,?,?,?,?)', [$did, $pid, $cant, round($i['unit_neto'], 2), $i['costo_unitario'], $sub]);
            if ($reint) { stock_mover($pid, $sc, $cant); kardex($pid, 'ENTRADA', $cant, "Devolución {$v['nro']}", $sc); }
        }
        // dinero
        if ($reemb === 'EFECTIVO') {
            $caja = caja_abierta(); if (!$caja || (int)$caja['sucursal_id'] !== $sc) throw new RuntimeException('Abre tu caja para devolver efectivo.');
            $r = resumen_caja($caja); if ($tot > $r['esperado']) throw new RuntimeException('No hay suficiente efectivo en caja (' . money($r['esperado']) . ') para devolver ' . money($tot) . '.');
            q("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto) VALUES (?,'EGRESO',?,?)", [$caja['id'], "Devolución {$v['nro']}", $tot]);
        } elseif ($reemb === 'CREDITO' && $v['cliente_id']) {
            q('UPDATE clientes SET saldo_deuda = GREATEST(0, saldo_deuda - ?) WHERE id=?', [$tot, $v['cliente_id']]);
        }
        // puntos proporcionales
        if ($v['cliente_id'] && $v['cliente_id'] != 1 && $v['puntos_ganados'] > 0 && $v['total'] > 0) {
            $rev = (int)floor($v['puntos_ganados'] * $tot / $v['total']);
            if ($rev > 0) {
                q('UPDATE clientes SET puntos = GREATEST(0, puntos - ?) WHERE id=?', [$rev, $v['cliente_id']]);
                q('INSERT INTO movimientos_puntos (cliente_id,venta_id,puntos,motivo) VALUES (?,?,?,?)', [$v['cliente_id'], $vid, -$rev, "Devolución {$v['nro']}"]);
            }
        }
        audit('DEVOLUCION', 'devolucion', $did, "{$v['nro']} · " . money($tot) . " · $reemb");
        $pdo->commit(); json_out(['ok' => true, 'id' => $did, 'total' => $tot, 'reembolso' => $reemb]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
}
json_fail('Acción no válida');
