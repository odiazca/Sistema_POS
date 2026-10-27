<?php
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
api_guard([1, 3], $a === 'save');
$pdo = db(); $u = usuario();

switch ($a) {
case 'productos':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT p.id,p.codigo,p.nombre,p.unidad,' . stock_col() . ' AS stock FROM productos p WHERE p.estado=1 AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?) ORDER BY p.nombre LIMIT 10', [$s, $s, $s])->fetchAll()]);

case 'destinos':
    json_out(['ok' => true, 'origen' => suc(), 'rows' => q('SELECT id,nombre FROM sucursales WHERE estado=1 AND id<>? ORDER BY id', [suc()])->fetchAll()]);

case 'list':
    $sc = suc(); $w = $sc ? "WHERE (t.origen_id=$sc OR t.destino_id=$sc)" : '';
    $rows = q("SELECT t.id,t.fecha,t.nota,so.nombre origen,sd.nombre destino,t.origen_id,t.destino_id,CONCAT(us.nombres,' ',us.apellidos) usuario,
        (SELECT COUNT(*) FROM detalle_traspasos d WHERE d.traspaso_id=t.id) items,(SELECT COALESCE(SUM(cantidad),0) FROM detalle_traspasos d WHERE d.traspaso_id=t.id) unidades
        FROM traspasos t JOIN sucursales so ON so.id=t.origen_id JOIN sucursales sd ON sd.id=t.destino_id JOIN usuarios us ON us.id=t.usuario_id $w ORDER BY t.id DESC LIMIT 60")->fetchAll();
    json_out(['ok' => true, 'rows' => $rows, 'suc' => $sc]);

case 'get':
    $t = q('SELECT t.*,so.nombre origen,sd.nombre destino FROM traspasos t JOIN sucursales so ON so.id=t.origen_id JOIN sucursales sd ON sd.id=t.destino_id WHERE t.id=?', [(int)($_GET['id'] ?? 0)])->fetch();
    if (!$t) json_fail('Traspaso no encontrado', 404);
    $t['items'] = q('SELECT d.cantidad,p.nombre,p.codigo,p.unidad FROM detalle_traspasos d JOIN productos p ON p.id=d.producto_id WHERE d.traspaso_id=?', [$t['id']])->fetchAll();
    json_out(['ok' => true, 'traspaso' => $t]);

case 'save':
    $b = body(); $items = $b['items'] ?? []; $origen = suc_w(); $dest = (int)($b['destino_id'] ?? 0);
    if (!$items) json_fail('Agrega al menos un producto.');
    if ($dest === $origen || !q('SELECT 1 FROM sucursales WHERE id=? AND estado=1', [$dest])->fetch()) json_fail('Selecciona una sucursal de destino distinta a la actual.');
    $pdo->beginTransaction();
    try {
        $agr = []; foreach ($items as $it) { $k = (int)($it['id'] ?? 0); $agr[$k] = ($agr[$k] ?? 0) + round((float)($it['cant'] ?? 0), 2); }
        q('INSERT INTO traspasos (origen_id,destino_id,usuario_id,nota) VALUES (?,?,?,?)', [$origen, $dest, $u['id'], mb_substr(trim($b['nota'] ?? ''), 0, 150) ?: null]);
        $tid = (int)$pdo->lastInsertId();
        $so = (string)q('SELECT nombre FROM sucursales WHERE id=?', [$origen])->fetchColumn(); $sd = (string)q('SELECT nombre FROM sucursales WHERE id=?', [$dest])->fetchColumn();
        foreach ($agr as $pid => $cant) {
            if ($cant <= 0) throw new RuntimeException('Revisa las cantidades.');
            $p = q('SELECT id,nombre FROM productos WHERE id=? FOR UPDATE', [$pid])->fetch(); if (!$p) throw new RuntimeException('Producto no válido.');
            $st = stock_de($pid, $origen, true);
            if ($cant > $st) throw new RuntimeException("Stock insuficiente de «{$p['nombre']}» en $so (disponible: $st).");
            q('INSERT INTO detalle_traspasos (traspaso_id,producto_id,cantidad) VALUES (?,?,?)', [$tid, $pid, $cant]);
            stock_mover($pid, $origen, -$cant); kardex($pid, 'SALIDA', $cant, "Traspaso #$tid hacia $sd", $origen);
            stock_mover($pid, $dest, $cant);    kardex($pid, 'ENTRADA', $cant, "Traspaso #$tid desde $so", $dest);
        }
        audit('TRASPASO', 'traspaso', $tid, "$so → $sd · " . count($agr) . ' producto(s)');
        $pdo->commit(); json_out(['ok' => true, 'id' => $tid]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
}
json_fail('Acción no válida');
