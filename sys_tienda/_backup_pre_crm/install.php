<?php
/**
 * Instalador · crea la BD sys_tienda, tablas, usuario admin y datos de demostración.
 * Ejecutar una sola vez:  http://localhost/sys_tienda/install.php
 * (Por seguridad, elimina este archivo cuando termines.)
 */
require_once __DIR__ . '/config/config.php';
// En producción, una vez instalado el sistema queda bloqueado: nadie puede reinstalar ni borrar datos desde internet.
$lockFile = __DIR__ . '/config/installed.lock';
if (APP_ENV === 'produccion' && is_file($lockFile)) {
    http_response_code(403);
    die('<h3 style="font-family:sans-serif">Instalación bloqueada</h3><p style="font-family:sans-serif">El sistema ya está instalado. Para reinstalar (esto BORRA todos los datos) elimina el archivo <code>config/installed.lock</code> desde el servidor.</p>');
}
set_time_limit(120);
$log = [];
try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    // 1) Esquema
    $sql = file_get_contents(__DIR__ . '/database/sys_tienda.sql');
    $pdo->exec($sql);
    $pdo->exec('USE ' . DB_NAME);
    $pdo->exec("SET time_zone = '-05:00'");
    $log[] = 'Base de datos y tablas creadas.';

    // 2) Usuarios (sucursal 1 = Central, 2 = Norte)
    $ins = $pdo->prepare('INSERT INTO usuarios (rol_id,sucursal_id,nombres,apellidos,usuario,email,password,cambiar_clave) VALUES (?,?,?,?,?,?,?,1)');
    $ins->execute([1, 1, 'Victor', 'Administrador', 'admin', 'admin@tienda.com', password_hash('admin123', PASSWORD_DEFAULT)]);
    $ins->execute([2, 1, 'María', 'Quispe', 'cajero', 'caja@tienda.com', password_hash('cajero123', PASSWORD_DEFAULT)]);
    $ins->execute([2, 2, 'Luis', 'Rojas', 'luis', 'luis@tienda.com', password_hash('cajero123', PASSWORD_DEFAULT)]);
    $log[] = 'Usuarios de demostración: admin/admin123 · cajero/cajero123 (Central) · luis/cajero123 (Norte). Al ingresar, el sistema pedirá cambiar estas contraseñas.';

    // 3) Categorías y productos
    $cats = [
        ['Abarrotes','bi-basket3','#f59e0b'], ['Bebidas','bi-cup-straw','#06b6d4'], ['Lácteos','bi-droplet-half','#3b82f6'],
        ['Snacks y Golosinas','bi-cookie','#ec4899'], ['Limpieza','bi-stars','#10b981'], ['Cuidado Personal','bi-heart-pulse','#8b5cf6'],
        ['Licores','bi-cup-hot','#ef4444'], ['Panadería','bi-egg-fried','#d97706'],
    ];
    $ic = $pdo->prepare('INSERT INTO categorias (nombre,icono,color) VALUES (?,?,?)');
    foreach ($cats as $c) $ic->execute($c);
    foreach (['Alicorp','Gloria','Coca-Cola','Backus','Laive','Nestlé','Costeño','Primor','Bolívar','Dove','Field','San Fernando'] as $m)
        $pdo->prepare('INSERT INTO marcas (nombre) VALUES (?)')->execute([$m]);

    // [cat, nombre, unidad, compra, venta, stock, minimo]
    $prods = [
        [1,'Arroz Costeño Extra 5kg','BLS',19.50,23.90,48,10],[1,'Azúcar Rubia Cartavio 1kg','KG',3.40,4.50,70,15],
        [1,'Aceite Primor Premium 1L','BOT',8.20,10.90,36,8],[1,'Fideo Don Vittorio Spaghetti 500g','PQT',2.30,3.20,90,20],
        [1,'Atún Florida Filete 170g','LAT',4.10,5.50,60,12],[1,'Menestra Lentejas 500g','PQT',3.00,4.20,3,10],
        [1,'Sal Emsal Yodada 1kg','BLS',1.00,1.60,55,10],[1,'Harina Blanca Flor 1kg','KG',3.20,4.30,40,10],
        [2,'Coca-Cola 1.5L','BOT',5.20,7.00,80,20],[2,'Inca Kola 1.5L','BOT',5.20,7.00,75,20],
        [2,'Agua San Luis 625ml','BOT',0.90,1.50,120,30],[2,'Cielo Agua sin gas 2.5L','BOT',2.40,3.50,40,10],
        [2,'Sporade Naranja 500ml','BOT',1.80,2.80,4,12],[2,'Gatorade Limón 500ml','BOT',2.30,3.50,45,12],
        [2,'Frugos del Valle Durazno 1L','CJA',3.00,4.20,35,10],
        [3,'Leche Gloria Evaporada 400g','LAT',3.40,4.50,100,24],[3,'Yogurt Gloria Fresa 1L','BOT',5.30,7.00,28,8],
        [3,'Queso Edam Laive 250g','PQT',9.50,12.50,15,5],[3,'Mantequilla Laive 200g','PQT',6.00,8.00,2,6],
        [3,'Leche Gloria Pura Vida 1L','CJA',3.80,4.90,60,15],
        [4,'Papas Lays Clásicas 150g','BLS',3.60,5.00,50,12],[4,'Galleta Soda Field 6un','PQT',1.40,2.00,85,20],
        [4,'Chocolate Sublime 30g','UND',1.00,1.60,110,30],[4,'Chizitos Pequeño','BLS',0.70,1.00,140,30],
        [4,'Casino Fresa Galletas','UND',0.60,1.00,6,25],[4,'Chicle Trident Menta','UND',0.90,1.50,60,15],
        [5,'Detergente Bolívar 800g','BLS',9.00,12.50,25,8],[5,'Lejía Clorox 1L','BOT',3.20,4.80,32,10],
        [5,'Lavavajilla Sapolio 750g','UND',4.60,6.50,30,8],[5,'Papel Higiénico Elite x4','PQT',4.80,6.90,44,12],
        [5,'Jabón Bolívar Barra','UND',1.60,2.50,2,15],
        [6,'Shampoo Head&Shoulders 375ml','BOT',14.00,19.50,18,5],[6,'Jabón Dove 90g','UND',3.20,4.80,50,12],
        [6,'Pasta Dental Colgate 75ml','UND',4.50,6.50,38,10],[6,'Desodorante Rexona 50g','UND',6.50,9.50,22,6],
        [7,'Cerveza Pilsen Callao 620ml','BOT',4.60,6.50,96,24],[7,'Cerveza Cusqueña Dorada 330ml','BOT',3.80,5.50,70,18],
        [7,'Pisco Quebranta Tabernero 750ml','BOT',32.00,44.00,12,4],[7,'Ron Cartavio Black 750ml','BOT',26.00,36.00,9,3],
        [8,'Pan Francés (bolsa x10)','BLS',2.50,4.00,40,10],[8,'Pan de Molde Bimbo Grande','BLS',7.50,10.50,14,5],
        [8,'Keke Marmoleado Mini','UND',1.80,3.00,20,8],
    ];
    $ip = $pdo->prepare('INSERT INTO productos (categoria_id,marca_id,codigo,codigo_barras,nombre,unidad,precio_compra,precio_venta,precio_mayor,cantidad_mayor,stock,stock_minimo,fecha_vencimiento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $iss = $pdo->prepare('INSERT INTO stock_sucursal (producto_id,sucursal_id,stock) VALUES (?,?,?)');
    $ikx = $pdo->prepare("INSERT INTO movimientos_inventario (sucursal_id,producto_id,tipo,cantidad,stock_resultante,motivo,usuario_id,fecha) VALUES (?,?,'ENTRADA',?,?,'Stock inicial',1,NOW()-INTERVAL 61 DAY)");
    foreach ($prods as $i => $p) {
        $venc = (rand(0, 9) < 2) ? date('Y-m-d', strtotime('+' . rand(5, 25) . ' days')) : date('Y-m-d', strtotime('+' . rand(120, 400) . ' days'));
        $s1 = $p[5]; $s2 = (int)round($p[5] * 0.6);
        // precio mayorista de ejemplo en bebidas y abarrotes
        $mayor = in_array($p[0], [1, 2]) ? round($p[4] * 0.92, 2) : null; $cmay = $mayor ? 6 : 0;
        $ip->execute([$p[0], rand(1, 12), 'P' . str_pad($i + 1, 4, '0', STR_PAD_LEFT), '775' . rand(1000000000, 9999999999), $p[1], $p[2], $p[3], $p[4], $mayor, $cmay, $s1 + $s2, $p[6], $venc]);
        $pid = (int)$pdo->lastInsertId();
        foreach ([[1, $s1], [2, $s2]] as [$sid, $st]) { $iss->execute([$pid, $sid, $st]); $ikx->execute([$sid, $pid, $st, $st]); }
    }
    $log[] = count($prods) . ' productos en ' . count($cats) . ' categorías, con stock en 2 sucursales.';

    // 4) Clientes y proveedores
    $cl = ['Cliente Varios','Rosa Mendoza Huamán','Carlos Pérez Díaz','Bodega El Sol SAC','Juana Torres Ríos','Restaurante Sabor Criollo EIRL','Miguel Soto Vega','Ana Castillo Lara','Pedro Gutiérrez Paz','Lucía Fernández Ayala'];
    $icl = $pdo->prepare('INSERT INTO clientes (tipo_doc,documento,nombre,telefono,saldo_deuda,puntos,fecha_nacimiento) VALUES (?,?,?,?,?,?,?)');
    foreach ($cl as $i => $n) $icl->execute([$i == 3 || $i == 5 ? 'RUC' : 'DNI', $i ? (string)($i == 3 || $i == 5 ? rand(20000000000, 20999999999) : rand(10000000, 79999999)) : null, $n, $i ? '9' . rand(10000000, 99999999) : null,
        in_array($i, [2, 4, 8]) ? rand(20, 180) : 0, $i ? rand(0, 120) : 0, in_array($i, [1, 7]) ? date('Y-m-d', strtotime('-' . rand(25, 45) . ' years')) : null]);
    // un cliente cumple años hoy (demostración) y otro en pocos días
    $pdo->exec("UPDATE clientes SET fecha_nacimiento = DATE_SUB(CURDATE(), INTERVAL 30 YEAR) WHERE id=2");
    $pdo->exec("UPDATE clientes SET fecha_nacimiento = DATE_SUB(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 28 YEAR) WHERE id=8");
    foreach ([['20100070970','Distribuidora Alicorp SAC'],['20100128056','Gloria S.A.'],['20100113610','Backus y Johnston'],['20501234567','Mayorista Santa Rosa']] as $pv)
        $pdo->prepare('INSERT INTO proveedores (ruc,razon_social,contacto,telefono) VALUES (?,?,?,?)')->execute([$pv[0], $pv[1], 'Ventas', '01' . rand(2000000, 7999999)]);

    // Promociones de ejemplo
    $pr = $pdo->prepare('INSERT INTO promociones (nombre,tipo,alcance,producto_id,categoria_id,valor,lleva,paga,fecha_inicio,fecha_fin) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $pr->execute(['2x1 en Chizitos', 'NXM', 'PRODUCTO', 24, null, 0, 2, 1, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+30 days'))]);
    $pr->execute(['10% en Lácteos', 'PORCENTAJE', 'CATEGORIA', null, 3, 10, 0, 0, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+30 days'))]);

    // Compras de ejemplo (sucursal central)
    $pc = $pdo->prepare('INSERT INTO compras (sucursal_id,proveedor_id,usuario_id,comprobante,fecha,total) VALUES (1,?,?,?,?,?)');
    $pd = $pdo->prepare('INSERT INTO detalle_compras (compra_id,producto_id,cantidad,costo_unitario,subtotal) VALUES (?,?,?,?,?)');
    $allP = $pdo->query('SELECT id,precio_compra FROM productos')->fetchAll();
    foreach ([[1,'F001-' . rand(1000,9999),12],[2,'F002-' . rand(1000,9999),6],[3,'F003-' . rand(1000,9999),2]] as $cc) {
        $tot = 0; $lines = [];
        foreach (array_rand($allP, 4) as $ix) { $pp = $allP[$ix]; $q = rand(10, 40); $lines[] = [$pp['id'], $q, $pp['precio_compra']]; $tot += $q * $pp['precio_compra']; }
        $pc->execute([$cc[0], 1, $cc[1], date('Y-m-d', strtotime('-' . $cc[2] . ' days')), $tot]); $cid = $pdo->lastInsertId();
        foreach ($lines as $l) $pd->execute([$cid, $l[0], $l[1], $l[2], $l[1] * $l[2]]);
    }

    // 5) Cajas abiertas hoy (una por sucursal) + ventas últimos 60 días
    $pdo->exec("INSERT INTO cajas (sucursal_id,usuario_id,fecha_apertura,monto_apertura,estado) VALUES (1,2, CONCAT(CURDATE(),' 07:30:00'), 200, 'ABIERTA'), (2,3, CONCAT(CURDATE(),' 07:45:00'), 150, 'ABIERTA')");
    $cajaDe = [1 => (int)$pdo->query('SELECT id FROM cajas WHERE sucursal_id=1')->fetchColumn(), 2 => (int)$pdo->query('SELECT id FROM cajas WHERE sucursal_id=2')->fetchColumn()];
    $usuDe = [1 => 2, 2 => 3];

    $stockProds = $pdo->query('SELECT id,precio_compra,precio_venta FROM productos')->fetchAll();
    $metodos = ['EFECTIVO','EFECTIVO','EFECTIVO','EFECTIVO','YAPE','YAPE','PLIN','TARJETA','TARJETA','CREDITO'];
    $iv = $pdo->prepare('INSERT INTO ventas (sucursal_id,caja_id,usuario_id,cliente_id,tipo_comprobante,serie,numero,fecha,subtotal,igv,total,metodo_pago,monto_recibido,vuelto,estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $id = $pdo->prepare('INSERT INTO detalle_ventas (venta_id,producto_id,cantidad,precio_unitario,costo_unitario,subtotal) VALUES (?,?,?,?,?,?)');
    $corr = [1 => ['TICKET' => 0, 'BOLETA' => 0, 'FACTURA' => 0], 2 => ['TICKET' => 0, 'BOLETA' => 0, 'FACTURA' => 0]];
    $serieDe = [1 => ['TICKET' => 'T001', 'BOLETA' => 'B001', 'FACTURA' => 'F001'], 2 => ['TICKET' => 'T002', 'BOLETA' => 'B002', 'FACTURA' => 'F002']];
    $pdo->beginTransaction();
    for ($d = 59; $d >= 0; $d--) {
        $dia = strtotime("-$d days"); $dow = (int)date('N', $dia);
        foreach ([1, 2] as $suc) {
            $base = (($dow >= 5 ? 42 : 28) + ($d < 30 ? 8 : 0) + rand(-5, 8)) * ($suc == 1 ? 1 : 0.65);
            if ($d == 0) $base = (int)((date('G') * 3.1 + 6) * ($suc == 1 ? 1 : 0.65));
            for ($k = 0; $k < (int)$base; $k++) {
                $hora = ($d == 0) ? rand(7, max(7, (int)date('G'))) : rand(7, 21);
                $f = date('Y-m-d', $dia) . sprintf(' %02d:%02d:%02d', $hora, rand(0, 59), rand(0, 59));
                if ($d == 0 && strtotime($f) > time()) $f = date('Y-m-d H:i:s', time() - rand(60, 3600));
                $tipo = rand(0, 9) < 6 ? 'TICKET' : (rand(0, 9) < 8 ? 'BOLETA' : 'FACTURA');
                $n = ++$corr[$suc][$tipo]; $items = []; $tot = 0;
                for ($j = 0, $m = rand(1, 5); $j < $m; $j++) { $p = $stockProds[array_rand($stockProds)]; $q = rand(1, 3); $items[] = [$p['id'], $q, $p['precio_venta'], $p['precio_compra']]; $tot += $q * $p['precio_venta']; }
                $met = $metodos[array_rand($metodos)]; $recib = $met === 'EFECTIVO' ? ceil($tot / 10) * 10 : $tot;
                $anul = rand(0, 99) < 2 ? 'ANULADA' : 'COMPLETADA';
                $iv->execute([$suc, $d == 0 ? $cajaDe[$suc] : null, $usuDe[$suc], rand(0, 3) ? 1 : rand(2, 10), $tipo, $serieDe[$suc][$tipo], $n, $f,
                    round($tot / 1.18, 2), round($tot - $tot / 1.18, 2), round($tot, 2), $met, $recib, $recib - $tot, $anul]);
                $vid = $pdo->lastInsertId();
                foreach ($items as $it) $id->execute([$vid, $it[0], $it[1], $it[2], $it[3], $it[1] * $it[2]]);
            }
        }
        if (rand(0, 4) == 0) {
            $g = [['Servicio de luz', 'Servicios'], ['Bolsas y empaques', 'Insumos'], ['Pasaje de reparto', 'Transporte'], ['Reposición de gas', 'Insumos']][rand(0, 3)];
            $pdo->prepare('INSERT INTO gastos (sucursal_id,concepto,categoria,monto,fecha) VALUES (?,?,?,?,?)')->execute([rand(1, 2), $g[0], $g[1], rand(15, 90), date('Y-m-d', $dia)]);
        }
    }
    foreach ($corr as $suc => $tipos) foreach ($tipos as $t => $c) $pdo->prepare('UPDATE series_comprobantes SET correlativo=? WHERE tipo=? AND sucursal_id=?')->execute([$c, $t, $suc]);
    $pdo->commit();
    $pdo->exec("INSERT INTO auditoria (usuario_id,usuario,sucursal_id,accion,detalle) VALUES (1,'admin',1,'INSTALACION','Sistema instalado con datos de demostración')");
    $log[] = 'Ventas de demostración generadas (60 días, 2 sucursales).';
    @file_put_contents($lockFile, 'Instalado: ' . date('Y-m-d H:i:s') . "\n");
    $ok = true;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $ok = false; $log[] = 'ERROR: ' . $e->getMessage();
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Instalación · SysTienda</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-5" style="max-width:640px">
<div class="card shadow-sm border-0"><div class="card-body p-4">
<h4 class="mb-3"><?= $ok ? '✅ Instalación completada' : '❌ Error en la instalación' ?></h4>
<ul class="list-group mb-4"><?php foreach ($log as $l): ?><li class="list-group-item"><?= htmlspecialchars($l) ?></li><?php endforeach; ?></ul>
<?php if ($ok): ?><a class="btn btn-primary" href="login.php">Ir al login</a>
<span class="text-muted small ms-2">Elimina <code>install.php</code> en producción.</span><?php endif; ?>
</div></div></div></body></html>
