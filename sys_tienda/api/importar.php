<?php
/** Importación/exportación de productos y búsqueda para etiquetas */
require_once __DIR__ . '/../includes/helpers.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'buscar');
api_guard([1, 3], $a === 'importar');
$pdo = db();

switch ($a) {
case 'buscar':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT p.id,p.codigo,p.codigo_barras,p.nombre,p.unidad,p.precio_venta,' . stock_col() . ' AS stock FROM productos p WHERE p.estado=1 AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.codigo_barras LIKE ?) ORDER BY p.nombre LIMIT 12', [$s, $s, $s])->fetchAll()]);

case 'export':
    $rows = q('SELECT p.codigo,p.codigo_barras,p.nombre,c.nombre categoria,m.nombre marca,p.unidad,p.precio_compra,p.precio_venta,p.precio_mayor,p.cantidad_mayor,' . stock_col() . ' AS stock,p.stock_minimo,p.fecha_vencimiento
        FROM productos p JOIN categorias c ON c.id=p.categoria_id LEFT JOIN marcas m ON m.id=p.marca_id ORDER BY p.nombre')->fetchAll();
    json_out(['ok' => true, 'rows' => $rows, 'sucursal' => suc_nombre()]);

case 'importar':
    $in = body()['rows'] ?? []; if (!is_array($in) || !$in) json_fail('El archivo no contiene filas.');
    if (count($in) > 2000) json_fail('Máximo 2000 filas por importación.');
    $unidades = ['UND', 'KG', 'LT', 'PQT', 'BLS', 'BOT', 'LAT', 'CJA', 'DOC'];
    $num = fn($v) => ($v === null || $v === '') ? null : (is_numeric(str_replace(',', '.', (string)$v)) ? (float)str_replace(',', '.', (string)$v) : false);
    $errs = []; $clean = []; $seen = []; $hayStock = false;
    foreach ($in as $n => $r) {
        $ln = $n + 2; $e = [];
        $cod = trim((string)($r['codigo'] ?? '')); $nom = trim((string)($r['nombre'] ?? ''));
        if ($cod === '') $e[] = 'falta código'; elseif (isset($seen[mb_strtolower($cod)])) $e[] = "código repetido ($cod)"; $seen[mb_strtolower($cod)] = 1;
        if ($nom === '') $e[] = 'falta nombre';
        $pc = $num($r['precio_compra'] ?? 0) ?? 0; $pv = $num($r['precio_venta'] ?? null); $pm = $num($r['precio_mayor'] ?? null); $cm = $num($r['cantidad_mayor'] ?? 0) ?? 0;
        $st = $num($r['stock'] ?? null); $sm = $num($r['stock_minimo'] ?? null);
        foreach (['precio_compra' => $pc, 'precio_venta' => $pv, 'precio_mayor' => $pm, 'cantidad_mayor' => $cm, 'stock' => $st, 'stock_minimo' => $sm] as $k => $v) if ($v === false || (is_float($v) && $v < 0)) $e[] = "$k inválido";
        if ($pv === null) $e[] = 'falta precio_venta'; elseif ($pv !== false && $pc !== false && $pv < $pc) $e[] = 'precio de venta menor al costo';
        $un = strtoupper(trim((string)($r['unidad'] ?? 'UND'))) ?: 'UND'; if (!in_array($un, $unidades, true)) $e[] = "unidad no válida ($un)";
        $fv = trim((string)($r['fecha_vencimiento'] ?? '')); if ($fv !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fv)) $e[] = 'fecha_vencimiento debe ser AAAA-MM-DD';
        if ($st !== null && $st !== false) $hayStock = true;
        if ($e) { $errs[] = "Fila $ln: " . implode(', ', $e); continue; }
        $clean[] = ['codigo' => $cod, 'barras' => trim((string)($r['codigo_barras'] ?? '')) ?: null, 'nombre' => mb_substr($nom, 0, 150), 'cat' => trim((string)($r['categoria'] ?? '')) ?: 'General', 'marca' => trim((string)($r['marca'] ?? '')) ?: null,
            'has' => array_keys($r), 'unidad' => $un, 'pc' => $pc, 'pv' => $pv, 'pm' => $pm, 'cm' => $cm, 'st' => $st, 'sm' => $sm, 'fv' => $fv ?: null];
    }
    if ($errs) json_out(['ok' => false, 'error' => 'Corrige estos errores y vuelve a intentar (no se importó nada):', 'errores' => array_slice($errs, 0, 25), 'total_errores' => count($errs)], 422);
    $sc = $hayStock ? suc_w() : 0;
    $pdo->beginTransaction();
    try {
        $catId = function (string $n) { $id = q('SELECT id FROM categorias WHERE LOWER(nombre)=LOWER(?)', [$n])->fetchColumn(); if ($id) return (int)$id; q('INSERT INTO categorias (nombre) VALUES (?)', [mb_substr($n, 0, 80)]); return (int)db()->lastInsertId(); };
        $marId = function (?string $n) { if (!$n) return null; $id = q('SELECT id FROM marcas WHERE LOWER(nombre)=LOWER(?)', [$n])->fetchColumn(); if ($id) return (int)$id; q('INSERT INTO marcas (nombre) VALUES (?)', [mb_substr($n, 0, 80)]); return (int)db()->lastInsertId(); };
        $nuevos = 0; $act = 0;
        foreach ($clean as $r) {
            $cat = $catId($r['cat']); $mar = $marId($r['marca']);
            $ex = q('SELECT id FROM productos WHERE codigo=? FOR UPDATE', [$r['codigo']])->fetchColumn();
            if ($ex) {
                // solo se actualizan las columnas presentes en el archivo
                $h = fn($k) => in_array($k, $r['has'], true);
                $set = ['nombre=?' => $r['nombre'], 'precio_venta=?' => $r['pv']];
                if ($h('categoria')) $set['categoria_id=?'] = $cat;
                if ($h('codigo_barras')) $set['codigo_barras=?'] = $r['barras'];
                if ($h('marca')) $set['marca_id=?'] = $mar;
                if ($h('unidad')) $set['unidad=?'] = $r['unidad'];
                if ($h('precio_compra')) $set['precio_compra=?'] = $r['pc'];
                if ($h('precio_mayor')) $set['precio_mayor=?'] = $r['pm'];
                if ($h('cantidad_mayor')) $set['cantidad_mayor=?'] = $r['cm'];
                if ($h('stock_minimo') && $r['sm'] !== null) $set['stock_minimo=?'] = $r['sm'];
                if ($h('fecha_vencimiento')) $set['fecha_vencimiento=?'] = $r['fv'];
                q('UPDATE productos SET ' . implode(',', array_keys($set)) . ' WHERE id=?', [...array_values($set), $ex]); $pid = (int)$ex; $act++;
            } else {
                q('INSERT INTO productos (codigo,codigo_barras,nombre,categoria_id,marca_id,unidad,precio_compra,precio_venta,precio_mayor,cantidad_mayor,stock,stock_minimo,fecha_vencimiento) VALUES (?,?,?,?,?,?,?,?,?,?,0,?,?)',
                  [$r['codigo'], $r['barras'], $r['nombre'], $cat, $mar, $r['unidad'], $r['pc'], $r['pv'], $r['pm'], $r['cm'], $r['sm'] ?? 5, $r['fv']]); $pid = (int)$pdo->lastInsertId(); $nuevos++;
            }
            if ($r['st'] !== null) {
                $dif = round($r['st'] - ($ex ? stock_de($pid, $sc, true) : 0), 2);
                if ($dif != 0) { stock_mover($pid, $sc, $dif); kardex($pid, $ex ? 'AJUSTE' : 'ENTRADA', $dif, 'Importación de productos', $sc); }
            }
        }
        audit('IMPORTAR', 'producto', null, "$nuevos nuevos, $act actualizados");
        $pdo->commit(); json_out(['ok' => true, 'nuevos' => $nuevos, 'actualizados' => $act]);
    } catch (PDOException $e) { $pdo->rollBack(); json_fail('Error al importar: ' . $e->getMessage() . ' (¿código de producto duplicado?)', 500); }
}
json_fail('Acción no válida');
