<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 2], $a === 'pagar');
$pdo = db();

switch ($a) {
case 'list':
    $s = '%' . trim($_GET['q'] ?? '') . '%'; $solo = ($_GET['solo'] ?? '1') === '1';
    $rows = q('SELECT id,tipo_doc,documento,nombre,telefono,limite_credito,saldo_deuda FROM clientes WHERE id<>1 AND estado=1 AND (nombre LIKE ? OR documento LIKE ?)' . ($solo ? ' AND saldo_deuda>0' : ' AND (saldo_deuda>0 OR limite_credito>0)') . ' ORDER BY saldo_deuda DESC, nombre LIMIT 60', [$s, $s])->fetchAll();
    $t = q('SELECT COALESCE(SUM(saldo_deuda),0) d, COUNT(CASE WHEN saldo_deuda>0 THEN 1 END) n, COALESCE(SUM(limite_credito),0) l FROM clientes WHERE id<>1')->fetch();
    $cobrado = (float)q("SELECT COALESCE(SUM(monto),0) FROM pagos_clientes WHERE DATE_FORMAT(fecha,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
    json_out(['ok' => true, 'rows' => $rows, 'deuda' => $t['d'], 'deudores' => $t['n'], 'limite' => $t['l'], 'cobrado_mes' => $cobrado]);

case 'detalle':
    $id = (int)($_GET['id'] ?? 0);
    $c = q('SELECT * FROM clientes WHERE id=?', [$id])->fetch(); if (!$c) json_fail('Cliente no encontrado', 404);
    json_out(['ok' => true, 'cliente' => $c,
        'ventas' => q("SELECT CONCAT(serie,'-',LPAD(numero,6,'0')) nro, fecha, total, estado FROM ventas WHERE cliente_id=? AND metodo_pago='CREDITO' ORDER BY id DESC LIMIT 8", [$id])->fetchAll(),
        'pagos' => q('SELECT fecha, monto, metodo, nota FROM pagos_clientes WHERE cliente_id=? ORDER BY id DESC LIMIT 8', [$id])->fetchAll()]);

case 'pagar':
    $b = body(); $id = (int)($b['cliente_id'] ?? 0); $monto = round((float)($b['monto'] ?? 0), 2); $met = $b['metodo'] ?? 'EFECTIVO';
    if (!in_array($met, ['EFECTIVO', 'TARJETA', 'YAPE', 'PLIN', 'TRANSFERENCIA'], true)) json_fail('Método inválido.');
    if ($monto <= 0) json_fail('Ingresa un monto mayor a 0.');
    $caja = caja_abierta(); if ($met === 'EFECTIVO' && !$caja) json_fail('Abre tu caja para cobrar en efectivo.');
    $pdo->beginTransaction();
    try {
        $c = q('SELECT * FROM clientes WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$c) throw new RuntimeException('Cliente no encontrado.');
        if ($monto > $c['saldo_deuda']) throw new RuntimeException('El pago supera la deuda (' . money($c['saldo_deuda']) . ').');
        q('UPDATE clientes SET saldo_deuda=saldo_deuda-? WHERE id=?', [$monto, $id]);
        q('INSERT INTO pagos_clientes (cliente_id,caja_id,usuario_id,monto,metodo,nota) VALUES (?,?,?,?,?,?)', [$id, $caja['id'] ?? null, usuario()['id'], $monto, $met, mb_substr(trim($b['nota'] ?? ''), 0, 150) ?: null]);
        if ($met === 'EFECTIVO') q("INSERT INTO movimientos_caja (caja_id,tipo,concepto,monto) VALUES (?,'INGRESO',?,?)", [$caja['id'], 'Cobro de crédito · ' . $c['nombre'], $monto]);
        $pdo->commit(); json_out(['ok' => true, 'saldo' => round($c['saldo_deuda'] - $monto, 2)]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
}
json_fail('Acción no válida');
