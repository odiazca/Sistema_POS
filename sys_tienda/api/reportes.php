<?php
require_once __DIR__ . '/../includes/helpers.php';
api_guard([1, 3]);
$r = $_GET['r'] ?? 'ventas';
$map = ['ventas' => 'rep-ventas', 'utilidad' => 'rep-utilidad', 'top' => 'rep-top', 'cajero' => 'rep-cajero', 'inventario' => 'rep-inventario', 'sucursales' => 'rep-sucursales'];
if (!isset($map[$r]) || !can($map[$r])) json_fail('Reporte no disponible para tu rol.', 403);
$d = $_GET['desde'] ?? date('Y-m-01'); $h = $_GET['hasta'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $h)) json_fail('Fechas inválidas.');
$ok = "v.estado='COMPLETADA' AND DATE(v.fecha) BETWEEN ? AND ?" . suc_where('v'); $P = [$d, $h];
$S = fn($l, $v, $t = 'money') => ['l' => $l, 'v' => $v, 't' => $t];

switch ($r) {
case 'ventas':
    $rows = q("SELECT DATE(v.fecha) fecha, COUNT(*) tickets, SUM(v.subtotal) subtotal, SUM(v.igv) igv, SUM(v.descuento) descuento, SUM(v.total) total, AVG(v.total) promedio FROM ventas v WHERE $ok GROUP BY DATE(v.fecha) ORDER BY fecha", $P)->fetchAll();
    $dev = q('SELECT DATE(dv.fecha) d, SUM(dv.total) t FROM devoluciones dv WHERE DATE(dv.fecha) BETWEEN ? AND ?' . suc_where('dv') . ' GROUP BY d', $P)->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($rows as &$x) { $x['devoluciones'] = (float)($dev[$x['fecha']] ?? 0); $x['neto'] = round($x['total'] - $x['devoluciones'], 2); } unset($x);
    $tot = array_sum(array_column($rows, 'neto')); $tk = array_sum(array_column($rows, 'tickets'));
    $best = $rows ? array_reduce($rows, fn($c, $x) => !$c || $x['total'] > $c['total'] ? $x : $c) : null;
    json_out(['ok' => true, 'title' => 'Ventas por período', 'summary' => [$S('Ventas netas', $tot), $S('Comprobantes', $tk, 'num'), $S('Ticket promedio', $tk ? $tot / $tk : 0), $S('Mejor día', $best ? date('d/m', strtotime($best['fecha'])) . ' · ' . money($best['total']) : '—', 'text')],
        'cols' => [['fecha', 'Fecha', 'date'], ['tickets', 'Comprob.', 'num'], ['subtotal', 'Subtotal', 'money'], ['igv', 'IGV', 'money'], ['descuento', 'Descuento', 'money'], ['total', 'Total', 'money'], ['devoluciones', 'Devol.', 'money'], ['neto', 'Neto', 'money'], ['promedio', 'Ticket prom.', 'money']], 'rows' => $rows,
        'chart' => ['type' => 'line', 'labels' => array_map(fn($x) => date('d/m', strtotime($x['fecha'])), $rows), 'datasets' => [['label' => 'Ventas netas', 'data' => array_map(fn($x) => round($x['neto'], 2), $rows)]]]]);

case 'utilidad':
    $rows = q("SELECT p.nombre producto, c.nombre categoria, SUM(dv.cantidad) cant, SUM(dv.subtotal) ventas, SUM(dv.cantidad*dv.costo_unitario) costo, SUM(dv.subtotal-dv.cantidad*dv.costo_unitario) utilidad,
        ROUND(SUM(dv.subtotal-dv.cantidad*dv.costo_unitario)/NULLIF(SUM(dv.subtotal),0)*100,1) margen FROM detalle_ventas dv JOIN ventas v ON v.id=dv.venta_id JOIN productos p ON p.id=dv.producto_id JOIN categorias c ON c.id=p.categoria_id WHERE $ok GROUP BY p.id ORDER BY utilidad DESC", $P)->fetchAll();
    $ven = array_sum(array_column($rows, 'ventas')); $cos = array_sum(array_column($rows, 'costo'));
    $dvM = (float)q('SELECT COALESCE(SUM(dv.total),0) FROM devoluciones dv WHERE DATE(dv.fecha) BETWEEN ? AND ?' . suc_where('dv'), $P)->fetchColumn();
    $dvC = (float)q('SELECT COALESCE(SUM(dd.cantidad*dd.costo_unitario),0) FROM detalle_devoluciones dd JOIN devoluciones dv ON dv.id=dd.devolucion_id WHERE DATE(dv.fecha) BETWEEN ? AND ?' . suc_where('dv'), $P)->fetchColumn();
    $ven -= $dvM; $cos -= $dvC; $ut = $ven - $cos;
    $gas = (float)q('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE estado=1 AND fecha BETWEEN ? AND ?' . suc_where(), $P)->fetchColumn();
    $top = array_slice($rows, 0, 10);
    json_out(['ok' => true, 'title' => 'Utilidades por producto', 'summary' => [$S('Ventas netas (con IGV)', $ven), $S('Costo de ventas', $cos), $S('Utilidad bruta', $ut), $S('Margen bruto', $ven ? round($ut / $ven * 100, 1) : 0, 'pct'), $S('Gastos del período', $gas), $S('Utilidad neta (aprox.)', $ut - $gas)],
        'cols' => [['producto', 'Producto', 'text'], ['categoria', 'Categoría', 'text'], ['cant', 'Cant.', 'num'], ['ventas', 'Ventas', 'money'], ['costo', 'Costo', 'money'], ['utilidad', 'Utilidad', 'money'], ['margen', 'Margen', 'pct']], 'rows' => $rows,
        'chart' => ['type' => 'bar', 'labels' => array_column($top, 'producto'), 'datasets' => [['label' => 'Utilidad', 'data' => array_map(fn($x) => round($x['utilidad'], 2), $top)]]]]);

case 'top':
    $rows = q("SELECT p.nombre producto, c.nombre categoria, SUM(dv.cantidad) cant, SUM(dv.subtotal) ventas, COUNT(DISTINCT v.id) tickets FROM detalle_ventas dv JOIN ventas v ON v.id=dv.venta_id JOIN productos p ON p.id=dv.producto_id JOIN categorias c ON c.id=p.categoria_id WHERE $ok GROUP BY p.id ORDER BY ventas DESC LIMIT 30", $P)->fetchAll();
    $top = array_slice($rows, 0, 10);
    json_out(['ok' => true, 'title' => 'Productos más vendidos', 'summary' => [$S('Productos distintos', count($rows), 'num'), $S('Unidades vendidas', array_sum(array_column($rows, 'cant')), 'num'), $S('Ventas del top 30', array_sum(array_column($rows, 'ventas')))],
        'cols' => [['producto', 'Producto', 'text'], ['categoria', 'Categoría', 'text'], ['cant', 'Unidades', 'num'], ['tickets', 'En ventas', 'num'], ['ventas', 'Ventas', 'money']], 'rows' => $rows,
        'chart' => ['type' => 'bar', 'labels' => array_column($top, 'producto'), 'datasets' => [['label' => 'Ventas', 'data' => array_map(fn($x) => round($x['ventas'], 2), $top)]]]]);

case 'sucursales':
    $rows = q("SELECT s.nombre sucursal,
        COUNT(CASE WHEN v.estado='COMPLETADA' THEN 1 END) tickets,
        COALESCE(SUM(CASE WHEN v.estado='COMPLETADA' THEN v.total END),0) ventas,
        COALESCE((SELECT SUM(dv.total) FROM devoluciones dv WHERE dv.sucursal_id=s.id AND DATE(dv.fecha) BETWEEN ? AND ?),0) devoluciones,
        COALESCE((SELECT SUM(g.monto) FROM gastos g WHERE g.estado=1 AND g.sucursal_id=s.id AND g.fecha BETWEEN ? AND ?),0) gastos,
        COALESCE(SUM(CASE WHEN v.estado='COMPLETADA' THEN (SELECT SUM(d.subtotal-d.cantidad*d.costo_unitario) FROM detalle_ventas d WHERE d.venta_id=v.id) END),0) utilidad
        FROM sucursales s LEFT JOIN ventas v ON v.sucursal_id=s.id AND DATE(v.fecha) BETWEEN ? AND ? WHERE s.estado=1 GROUP BY s.id ORDER BY ventas DESC", [$d, $h, $d, $h, $d, $h])->fetchAll();
    foreach ($rows as &$x) { $x['neto'] = round($x['ventas'] - $x['devoluciones'], 2); } unset($x);
    json_out(['ok' => true, 'title' => 'Consolidado por sucursal', 'summary' => [$S('Ventas netas', array_sum(array_column($rows, 'neto'))), $S('Comprobantes', array_sum(array_column($rows, 'tickets')), 'num'), $S('Utilidad bruta', array_sum(array_column($rows, 'utilidad'))), $S('Gastos', array_sum(array_column($rows, 'gastos')))],
        'cols' => [['sucursal', 'Sucursal', 'text'], ['tickets', 'Comprob.', 'num'], ['ventas', 'Ventas', 'money'], ['devoluciones', 'Devol.', 'money'], ['neto', 'Neto', 'money'], ['utilidad', 'Utilidad bruta', 'money'], ['gastos', 'Gastos', 'money']], 'rows' => $rows,
        'chart' => ['type' => 'bar', 'labels' => array_column($rows, 'sucursal'), 'datasets' => [['label' => 'Ventas netas', 'data' => array_map(fn($x) => round($x['neto'], 2), $rows)]]]]);

case 'cajero':
    $rows = q("SELECT CONCAT(u.nombres,' ',u.apellidos) cajero, COUNT(CASE WHEN v.estado='COMPLETADA' THEN 1 END) tickets, COALESCE(SUM(CASE WHEN v.estado='COMPLETADA' THEN v.total END),0) total,
        COALESCE(AVG(CASE WHEN v.estado='COMPLETADA' THEN v.total END),0) promedio, COUNT(CASE WHEN v.estado='ANULADA' THEN 1 END) anuladas FROM ventas v JOIN usuarios u ON u.id=v.usuario_id WHERE DATE(v.fecha) BETWEEN ? AND ?" . suc_where('v') . " GROUP BY u.id ORDER BY total DESC", $P)->fetchAll();
    json_out(['ok' => true, 'title' => 'Ventas por cajero', 'summary' => [$S('Ventas totales', array_sum(array_column($rows, 'total'))), $S('Comprobantes', array_sum(array_column($rows, 'tickets')), 'num'), $S('Anulaciones', array_sum(array_column($rows, 'anuladas')), 'num')],
        'cols' => [['cajero', 'Cajero', 'text'], ['tickets', 'Comprob.', 'num'], ['total', 'Total vendido', 'money'], ['promedio', 'Ticket prom.', 'money'], ['anuladas', 'Anuladas', 'num']], 'rows' => $rows,
        'chart' => ['type' => 'doughnut', 'labels' => array_column($rows, 'cajero'), 'datasets' => [['label' => 'Ventas', 'data' => array_map(fn($x) => round($x['total'], 2), $rows)]]]]);

case 'inventario':
    $sk = stock_col();
    $rows = q("SELECT p.nombre producto, c.nombre categoria, $sk stock, p.unidad, p.precio_compra costo, p.precio_venta precio, $sk*p.precio_compra valor_costo, $sk*p.precio_venta valor_venta, $sk*(p.precio_venta-p.precio_compra) utilidad FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE p.estado=1 ORDER BY valor_costo DESC")->fetchAll();
    $cat = []; foreach ($rows as $x) $cat[$x['categoria']] = ($cat[$x['categoria']] ?? 0) + $x['valor_costo'];
    arsort($cat);
    json_out(['ok' => true, 'title' => 'Valorización de inventario', 'nodates' => true, 'summary' => [$S('Productos', count($rows), 'num'), $S('Valor a costo', array_sum(array_column($rows, 'valor_costo'))), $S('Valor a precio de venta', array_sum(array_column($rows, 'valor_venta'))), $S('Utilidad potencial', array_sum(array_column($rows, 'utilidad')))],
        'cols' => [['producto', 'Producto', 'text'], ['categoria', 'Categoría', 'text'], ['stock', 'Stock', 'num'], ['costo', 'Costo', 'money'], ['precio', 'P. venta', 'money'], ['valor_costo', 'Valor costo', 'money'], ['valor_venta', 'Valor venta', 'money'], ['utilidad', 'Utilidad pot.', 'money']], 'rows' => $rows,
        'chart' => ['type' => 'doughnut', 'labels' => array_keys($cat), 'datasets' => [['label' => 'Valor a costo', 'data' => array_map(fn($x) => round($x, 2), array_values($cat))]]]]);
}
