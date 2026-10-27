<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'estado');
api_guard([1, 2], $a !== 'estado' && $a !== 'historial');
$u = usuario(); $b = body();

switch ($a) {
case 'estado':
    $c = caja_abierta();
    json_out(['ok' => true, 'caja' => $c, 'resumen' => $c ? resumen_caja($c) : null,
              'movimientos' => $c ? q('SELECT * FROM movimientos_caja WHERE caja_id=? ORDER BY id DESC LIMIT 50', [$c['id']])->fetchAll() : []]);

case 'abrir':
    if (caja_abierta()) json_fail('Ya tienes una caja abierta.');
    $m = (float)($b['monto'] ?? -1); if ($m < 0) json_fail('Ingresa el monto inicial (puede ser 0).');
    $sc = suc_w();
    q("INSERT INTO cajas (sucursal_id,usuario_id,fecha_apertura,monto_apertura) VALUES (?,?,NOW(),?)", [$sc, $u['id'], $m]);
    audit('ABRIR_CAJA', 'caja', (int)db()->lastInsertId(), 'Apertura ' . money($m));
    json_out(['ok' => true]);

case 'movimiento':
    $c = caja_abierta(); if (!$c) json_fail('No hay caja abierta.');
    $t = $b['tipo'] ?? ''; $monto = (float)($b['monto'] ?? 0); $con = trim($b['concepto'] ?? '');
    if (!in_array($t, ['INGRESO', 'EGRESO'], true) || $monto <= 0 || $con === '') json_fail('Completa tipo, concepto y un monto mayor a 0.');
    $r = resumen_caja($c);
    if ($t === 'EGRESO' && $monto > $r['esperado']) json_fail('El egreso supera el efectivo disponible en caja (' . money($r['esperado']) . ').');
    q('INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto) VALUES (?,?,?,?)', [$c['id'], $t, $con, $monto]);
    audit('MOV_CAJA', 'caja', (int)$c['id'], "$t $con " . money($monto));
    json_out(['ok' => true]);

case 'cerrar':
    $c = caja_abierta(); if (!$c) json_fail('No hay caja abierta.');
    $contado = (float)($b['contado'] ?? -1); if ($contado < 0) json_fail('Ingresa el efectivo contado.');
    $r = resumen_caja($c);
    q("UPDATE cajas SET estado='CERRADA', fecha_cierre=NOW(), monto_cierre=?, observacion=? WHERE id=?", [$contado, mb_substr(trim($b['obs'] ?? ''), 0, 200), $c['id']]);
    audit('CERRAR_CAJA', 'caja', (int)$c['id'], 'Contado ' . money($contado) . ' · esperado ' . money($r['esperado']));
    json_out(['ok' => true, 'id' => (int)$c['id'], 'esperado' => $r['esperado'], 'diferencia' => round($contado - $r['esperado'], 2)]);

case 'historial':
    $rows = q("SELECT c.*, CONCAT(u.nombres,' ',u.apellidos) cajero, (SELECT nombre FROM sucursales WHERE id=c.sucursal_id) sucursal,
        (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.caja_id=c.id AND v.estado='COMPLETADA') ventas,
        (SELECT COALESCE(SUM(d.total),0) FROM devoluciones d WHERE d.usuario_id=c.usuario_id AND d.sucursal_id=c.sucursal_id AND d.fecha>=c.fecha_apertura AND (c.fecha_cierre IS NULL OR d.fecha<=c.fecha_cierre)) devuelto,
        (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.caja_id=c.id AND v.estado='COMPLETADA' AND metodo_pago='EFECTIVO') efectivo,
        (SELECT COALESCE(SUM(CASE tipo WHEN 'INGRESO' THEN monto ELSE -monto END),0) FROM movimientos_caja m WHERE m.caja_id=c.id) neto_mov
        FROM cajas c JOIN usuarios u ON u.id=c.usuario_id WHERE 1=1 " . suc_where('c') . ($u['rol_id'] == 1 ? '' : ' AND c.usuario_id=' . (int)$u['id']) . " ORDER BY c.id DESC LIMIT 15")->fetchAll();
    foreach ($rows as &$r) { $r['ventas'] = round($r['ventas'] - $r['devuelto'], 2); } unset($r);
    foreach ($rows as &$r) $r['esperado'] = round($r['monto_apertura'] + $r['efectivo'] + $r['neto_mov'], 2);
    json_out(['ok' => true, 'rows' => $rows]);
}
json_fail('Acción no válida');
