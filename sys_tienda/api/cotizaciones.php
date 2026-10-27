<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 2], in_array($a, ['save', 'anular'], true));
$pdo = db(); $u = usuario();

switch ($a) {
case 'productos':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT p.id,p.codigo,p.nombre,p.unidad,p.precio_venta,p.precio_mayor,p.cantidad_mayor,p.categoria_id,' . stock_col() . ' AS stock FROM productos p WHERE p.estado=1 AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?) ORDER BY p.nombre LIMIT 10', [$s, $s, $s])->fetchAll(),
        'promos' => promos_vigentes(suc() ?: 1)]);

case 'list':
    $w = ['1=1' . suc_where('c')]; $p = [];
    if (!empty($_GET['estado'])) {
        if ($_GET['estado'] === 'VENCIDA') $w[] = "c.estado='PENDIENTE' AND c.vigencia<CURDATE()";
        elseif ($_GET['estado'] === 'PENDIENTE') $w[] = "c.estado='PENDIENTE' AND c.vigencia>=CURDATE()";
        else { $w[] = 'c.estado=?'; $p[] = $_GET['estado']; }
    }
    if (($s = trim($_GET['q'] ?? '')) !== '') { $w[] = '(cl.nombre LIKE ? OR c.id=?)'; array_push($p, "%$s%", (int)$s); }
    $rows = q("SELECT c.id,c.fecha,c.vigencia,c.total,c.estado,c.venta_id,COALESCE(cl.nombre,'Cliente Varios') cliente,CONCAT(us.nombres,' ',us.apellidos) vendedor,
        (c.estado='PENDIENTE' AND c.vigencia<CURDATE()) vencida,(SELECT nombre FROM sucursales WHERE id=c.sucursal_id) sucursal
        FROM cotizaciones c LEFT JOIN clientes cl ON cl.id=c.cliente_id JOIN usuarios us ON us.id=c.usuario_id WHERE " . implode(' AND ', $w) . ' ORDER BY c.id DESC LIMIT 80', $p)->fetchAll();
    json_out(['ok' => true, 'rows' => $rows, 'todas' => suc() === 0]);

case 'get':
    $c = q("SELECT c.*,COALESCE(cl.nombre,'Cliente Varios') cliente,cl.documento,cl.telefono FROM cotizaciones c LEFT JOIN clientes cl ON cl.id=c.cliente_id WHERE c.id=?" . suc_where('c'), [(int)($_GET['id'] ?? 0)])->fetch();
    if (!$c) json_fail('Cotización no encontrada', 404);
    $c['items'] = q('SELECT d.*,p.nombre,p.unidad FROM detalle_cotizaciones d JOIN productos p ON p.id=d.producto_id WHERE d.cotizacion_id=?', [$c['id']])->fetchAll();
    json_out(['ok' => true, 'cot' => $c]);

case 'clientes':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT id,nombre,documento FROM clientes WHERE estado=1 AND (nombre LIKE ? OR documento LIKE ?) ORDER BY id=1 DESC, nombre LIMIT 8', [$s, $s])->fetchAll()]);

case 'save':
    $b = body(); $items = $b['items'] ?? []; $sc = suc_w();
    if (!$items) json_fail('Agrega al menos un producto.');
    $dias = max(1, min(90, (int)($b['dias'] ?? 7))); $cli = (int)($b['cliente_id'] ?? 1) ?: 1;
    if (!q('SELECT 1 FROM clientes WHERE id=? AND estado=1', [$cli])->fetch()) json_fail('Cliente no válido.');
    $pdo->beginTransaction();
    try {
        $promos = promos_vigentes($sc); $lines = []; $tot = 0; $agr = [];
        foreach ($items as $it) { $k = (int)($it['id'] ?? 0); $agr[$k] = ($agr[$k] ?? 0) + round((float)($it['cant'] ?? 0), 2); }
        foreach ($agr as $pid => $cant) {
            if ($cant <= 0) throw new RuntimeException('Revisa las cantidades.');
            $p = q('SELECT * FROM productos WHERE id=? AND estado=1', [$pid])->fetch(); if (!$p) throw new RuntimeException('Producto no válido.');
            [$precio, $d, , $sub] = calc_linea($p, $cant, $promos);
            $lines[] = [$pid, $cant, $sub / $cant, $sub]; $tot += $sub;
        }
        q('INSERT INTO cotizaciones (sucursal_id,usuario_id,cliente_id,fecha,vigencia,total,nota) VALUES (?,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL ? DAY),?,?)', [$sc, $u['id'], $cli, $dias, round($tot, 2), mb_substr(trim($b['nota'] ?? ''), 0, 200) ?: null]);
        $id = (int)$pdo->lastInsertId();
        foreach ($lines as [$pid, $cant, $pu, $sub]) q('INSERT INTO detalle_cotizaciones (cotizacion_id,producto_id,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)', [$id, $pid, $cant, round($pu, 2), $sub]);
        audit('COTIZACION', 'cotizacion', $id, 'Total ' . money($tot));
        $pdo->commit(); json_out(['ok' => true, 'id' => $id, 'total' => round($tot, 2)]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }

case 'anular':
    $id = (int)(body()['id'] ?? 0);
    $n = q("UPDATE cotizaciones SET estado='ANULADA' WHERE id=? AND estado='PENDIENTE'" . suc_where(), [$id])->rowCount();
    if (!$n) json_fail('Solo se pueden anular cotizaciones pendientes.');
    audit('ANULAR_COTIZACION', 'cotizacion', $id); json_out(['ok' => true]);
}
json_fail('Acción no válida');
