<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';
$a = $_GET['a'] ?? (body()['a'] ?? '');
api_guard([1, 2], $a === 'venta');
$pdo = db();

switch ($a) {
case 'productos':
    $s = trim($_GET['q'] ?? ''); $cat = (int)($_GET['cat'] ?? 0); $p = []; $w = ['p.estado=1']; $sc = suc();
    if ($s !== '') { $w[] = '(p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%"); }
    if ($cat) { $w[] = 'p.categoria_id=?'; $p[] = $cat; }
    $rows = q('SELECT p.id,p.codigo,p.codigo_barras,p.nombre,p.unidad,p.precio_venta,p.precio_mayor,p.cantidad_mayor,p.categoria_id,p.imagen,' . stock_col() . ' AS stock,p.stock_minimo,c.color,c.icono FROM productos p JOIN categorias c ON c.id=p.categoria_id WHERE ' . implode(' AND ', $w)
        . " ORDER BY (p.codigo_barras=? OR p.codigo=?) DESC, p.nombre LIMIT 60", [...$p, $s, $s])->fetchAll();
    json_out(['ok' => true, 'rows' => $rows, 'promos' => promos_vigentes($sc)]);

case 'categorias':
    json_out(['ok' => true, 'rows' => q('SELECT id,nombre,icono,color FROM categorias WHERE estado=1 ORDER BY nombre')->fetchAll()]);

case 'clientes':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    $rows = q('SELECT id,tipo_doc,documento,nombre,telefono,saldo_deuda,limite_credito,puntos,fecha_nacimiento FROM clientes WHERE estado=1 AND (nombre LIKE ? OR documento LIKE ?) ORDER BY id=1 DESC, nombre LIMIT 8', [$s, $s])->fetchAll();
    foreach ($rows as &$r) { $r['cumple'] = es_cumple($r['fecha_nacimiento']); unset($r['fecha_nacimiento']); }
    json_out(['ok' => true, 'rows' => $rows, 'fid' => ['cada' => (float)cfg('puntos_cada', '10'), 'valor' => (float)cfg('puntos_valor', '0.10'), 'cumple' => (float)cfg('cumple_descuento', '5')]]);

case 'cotizacion': // precarga de una cotización en el POS
    $c = q('SELECT * FROM cotizaciones WHERE id=? AND estado=? AND sucursal_id=?', [(int)($_GET['id'] ?? 0), 'PENDIENTE', suc_w()])->fetch();
    if (!$c) json_fail('Cotización no disponible (¿ya fue convertida o es de otra sucursal?).');
    if ($c['vigencia'] < date('Y-m-d')) json_fail('La cotización venció el ' . date('d/m/Y', strtotime($c['vigencia'])) . '. Genera una nueva.');
    $it = q('SELECT p.id,p.codigo,p.nombre,p.unidad,p.precio_venta,p.precio_mayor,p.cantidad_mayor,p.categoria_id,' . stock_col() . ' AS stock,d.cantidad cant FROM detalle_cotizaciones d JOIN productos p ON p.id=d.producto_id WHERE d.cotizacion_id=?', [$c['id']])->fetchAll();
    $cli = $c['cliente_id'] ? q('SELECT id,tipo_doc,documento,nombre,telefono,saldo_deuda,limite_credito,puntos,fecha_nacimiento FROM clientes WHERE id=?', [$c['cliente_id']])->fetch() : null;
    if ($cli) { $cli['cumple'] = es_cumple($cli['fecha_nacimiento']); unset($cli['fecha_nacimiento']); }
    json_out(['ok' => true, 'items' => $it, 'cliente' => $cli, 'cot' => (int)$c['id']]);

case 'venta':
    $b = body(); $u = usuario(); $sc = suc_w();
    $caja = caja_abierta(); if (!$caja) json_fail('Abre tu caja antes de vender.');
    if ((int)$caja['sucursal_id'] !== $sc) json_fail('Tu caja abierta pertenece a otra sucursal.');
    $items = $b['items'] ?? []; if (!$items || !is_array($items)) json_fail('El carrito está vacío.');
    $tipo = in_array($b['tipo'] ?? '', ['TICKET', 'BOLETA', 'FACTURA'], true) ? $b['tipo'] : 'TICKET';
    $metodo = in_array($b['metodo'] ?? '', ['EFECTIVO', 'TARJETA', 'YAPE', 'PLIN', 'TRANSFERENCIA', 'CREDITO'], true) ? $b['metodo'] : 'EFECTIVO';
    $clienteId = (int)($b['cliente_id'] ?? 1) ?: 1;
    $canje = max(0, (int)($b['canje_puntos'] ?? 0)); $cotId = (int)($b['cotizacion_id'] ?? 0);
    $pdo->beginTransaction();
    try {
        $cli = q('SELECT * FROM clientes WHERE id=? AND estado=1 FOR UPDATE', [$clienteId])->fetch();
        if (!$cli) throw new RuntimeException('Cliente no válido.');
        $promos = promos_vigentes($sc);
        $lineas = []; $bruto = 0; $dPromo = 0; $agr = [];
        foreach ($items as $it) { $k = (int)($it['id'] ?? 0); $agr[$k] = ['id' => $k, 'cant' => ($agr[$k]['cant'] ?? 0) + (float)($it['cant'] ?? 0)]; }
        foreach ($agr as $it) {
            $cant = round((float)$it['cant'], 2); if ($cant <= 0) throw new RuntimeException('Cantidad inválida en el carrito.');
            $p = q('SELECT * FROM productos WHERE id=? AND estado=1 FOR UPDATE', [$it['id']])->fetch();
            if (!$p) throw new RuntimeException('Un producto del carrito ya no está disponible.');
            $disp = stock_de((int)$p['id'], $sc, true);
            if ($disp < $cant) throw new RuntimeException("Stock insuficiente de «{$p['nombre']}» en esta sucursal (disponible: " . $disp . ').');
            [$precio, $dl, $promoN, $sub] = calc_linea($p, $cant, $promos);
            $bruto += round($cant * $precio, 2); $dPromo += $dl;
            $lineas[] = [$p, $cant, $precio, $dl, $promoN, $sub];
        }
        $neto = round($bruto - $dPromo, 2);
        $manual = round(max(0, (float)($b['descuento'] ?? 0)), 2);
        if ($manual > $neto) throw new RuntimeException('El descuento no puede superar el total.');
        $neto -= $manual;
        // cumpleaños
        $dCumple = 0;
        if ($clienteId !== 1 && es_cumple($cli['fecha_nacimiento'])) $dCumple = round($neto * (float)cfg('cumple_descuento', '5') / 100, 2);
        $neto -= $dCumple;
        // canje de puntos
        $dPuntos = 0;
        if ($canje > 0) {
            if ($clienteId === 1) throw new RuntimeException('Para canjear puntos selecciona un cliente registrado.');
            if ($canje > (int)$cli['puntos']) throw new RuntimeException('El cliente solo tiene ' . (int)$cli['puntos'] . ' puntos.');
            $dPuntos = round($canje * (float)cfg('puntos_valor', '0.10'), 2);
            if ($dPuntos > $neto) { $canje = (int)floor($neto / (float)cfg('puntos_valor', '0.10')); $dPuntos = round($canje * (float)cfg('puntos_valor', '0.10'), 2); }
            $neto -= $dPuntos;
        }
        $total = round($neto, 2);
        if ($total <= 0) throw new RuntimeException('El total debe ser mayor a 0.');
        $descTotal = round($dPromo + $manual + $dCumple + $dPuntos, 2);
        $igvPct = (float)cfg('igv', '18'); $subtotal = round($total / (1 + $igvPct / 100), 2); $igv = round($total - $subtotal, 2);

        if ($tipo === 'FACTURA' && !($cli['tipo_doc'] === 'RUC' && strlen((string)$cli['documento']) === 11)) throw new RuntimeException('Para factura el cliente debe tener RUC válido.');
        if ($tipo === 'BOLETA' && $total >= 700 && $clienteId === 1) throw new RuntimeException('Boletas desde ' . money(700) . ' requieren identificar al cliente (DNI).');
        $recibido = null; $vuelto = null;
        if ($metodo === 'EFECTIVO') {
            $recibido = round((float)($b['recibido'] ?? 0), 2);
            if ($recibido < $total) throw new RuntimeException('El monto recibido es menor al total.');
            $vuelto = round($recibido - $total, 2);
        }
        if ($metodo === 'CREDITO') {
            if ($clienteId === 1) throw new RuntimeException('La venta al crédito requiere un cliente registrado.');
            if ($cli['limite_credito'] <= 0) throw new RuntimeException('Este cliente no tiene línea de crédito.');
            if ($cli['saldo_deuda'] + $total > $cli['limite_credito']) throw new RuntimeException('Supera el límite de crédito (disponible: ' . money($cli['limite_credito'] - $cli['saldo_deuda']) . ').');
        }
        $serie = q('SELECT * FROM series_comprobantes WHERE tipo=? AND sucursal_id=? FOR UPDATE', [$tipo, $sc])->fetch();
        if (!$serie) throw new RuntimeException('Esta sucursal no tiene serie configurada para ' . $tipo . '.');
        $numero = $serie['correlativo'] + 1;
        q('UPDATE series_comprobantes SET correlativo=? WHERE id=?', [$numero, $serie['id']]);
        $ganados = $clienteId !== 1 ? (int)floor($total / max(0.01, (float)cfg('puntos_cada', '10'))) : 0;
        q('INSERT INTO ventas (sucursal_id,caja_id,usuario_id,cliente_id,tipo_comprobante,serie,numero,fecha,subtotal,descuento,igv,total,puntos_ganados,puntos_canjeados,cotizacion_id,metodo_pago,monto_recibido,vuelto,estado) VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?,?,?,?,?,?)',
          [$sc, $caja['id'], $u['id'], $clienteId, $tipo, $serie['serie'], $numero, $subtotal, $descTotal, $igv, $total, $ganados, $canje, $cotId ?: null, $metodo, $recibido, $vuelto, 'COMPLETADA']);
        $vid = (int)$pdo->lastInsertId(); $nro = $serie['serie'] . '-' . str_pad((string)$numero, 6, '0', STR_PAD_LEFT);
        foreach ($lineas as [$p, $cant, $precio, $dl, $promoN, $sub]) {
            q('INSERT INTO detalle_ventas (venta_id,producto_id,cantidad,precio_unitario,costo_unitario,descuento,promo,subtotal) VALUES (?,?,?,?,?,?,?,?)', [$vid, $p['id'], $cant, $precio, $p['precio_compra'], $dl, $promoN, $sub]);
            stock_mover((int)$p['id'], $sc, -$cant);
            kardex((int)$p['id'], 'SALIDA', $cant, "Venta $nro", $sc);
        }
        if ($metodo === 'CREDITO') q('UPDATE clientes SET saldo_deuda = saldo_deuda + ? WHERE id=?', [$total, $clienteId]);
        if ($clienteId !== 1 && ($ganados || $canje)) {
            q('UPDATE clientes SET puntos = puntos + ? - ? WHERE id=?', [$ganados, $canje, $clienteId]);
            if ($ganados) q('INSERT INTO movimientos_puntos (cliente_id,venta_id,puntos,motivo) VALUES (?,?,?,?)', [$clienteId, $vid, $ganados, "Compra $nro"]);
            if ($canje) q('INSERT INTO movimientos_puntos (cliente_id,venta_id,puntos,motivo) VALUES (?,?,?,?)', [$clienteId, $vid, -$canje, "Canje en $nro"]);
        }
        if ($cotId) q("UPDATE cotizaciones SET estado='ACEPTADA', venta_id=? WHERE id=? AND estado='PENDIENTE'", [$vid, $cotId]);
        audit('VENTA', 'venta', $vid, "$nro · " . money($total) . " · $metodo");
        $pdo->commit();
        json_out(['ok' => true, 'id' => $vid, 'nro' => $nro, 'total' => $total, 'vuelto' => $vuelto, 'puntos' => $ganados,
                  'telefono' => $cli['telefono'], 'cliente' => $cli['nombre']]);
    } catch (RuntimeException $e) { $pdo->rollBack(); json_fail($e->getMessage()); }
      catch (Throwable $e) { $pdo->rollBack(); json_fail('No se pudo registrar la venta: ' . $e->getMessage(), 500); }
}
json_fail('Acción no válida');
