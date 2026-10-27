<?php
/** Registra ventas de HOY (ligadas a la caja abierta) y rellena días sin ventas desde la última venta. Solo administrador. */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
session_start();
require_login();
if ((int)(usuario()['rol_id'] ?? 0) !== 1) { http_response_code(403); die('Solo administrador.'); }
$pdo = db(); $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    set_time_limit(300);
    $sucs = $pdo->query("SELECT id FROM sucursales WHERE estado=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $prods = $pdo->query("SELECT id,precio_compra,precio_venta FROM productos WHERE estado=1 AND precio_venta>0")->fetchAll();
    $clis = $pdo->query("SELECT id FROM clientes WHERE estado=1 AND id>1")->fetchAll(PDO::FETCH_COLUMN);
    $usu = $pdo->query("SELECT id,sucursal_id FROM usuarios WHERE estado=1 ORDER BY id")->fetchAll();
    if (!$prods || !$sucs || !$usu) die('Faltan productos, sucursales o usuarios.');
    $uDe = []; foreach ($usu as $u) $uDe[(int)$u['sucursal_id']][] = (int)$u['id'];
    $hoy = date('Y-m-d'); $ult = $pdo->query("SELECT MAX(DATE(fecha)) FROM ventas")->fetchColumn() ?: date('Y-m-d', strtotime('-7 days'));
    $desde = min(date('Y-m-d', strtotime("$ult +1 day")), $hoy);
    // caja abierta por sucursal (si no existe, se abre una)
    $caja = [];
    foreach ($sucs as $s) {
        $c = $pdo->prepare("SELECT id FROM cajas WHERE sucursal_id=? AND estado='ABIERTA' ORDER BY id DESC LIMIT 1"); $c->execute([$s]); $id = $c->fetchColumn();
        if (!$id) { $pdo->prepare("INSERT INTO cajas (sucursal_id,usuario_id,fecha_apertura,monto_apertura,estado) VALUES (?,?,?,150,'ABIERTA')")->execute([$s, ($uDe[(int)$s][0] ?? $usu[0]['id']), "$hoy 07:30:00"]); $id = $pdo->lastInsertId(); }
        $caja[(int)$s] = (int)$id;
    }
    $metodos = ['EFECTIVO','EFECTIVO','EFECTIVO','EFECTIVO','YAPE','YAPE','PLIN','TARJETA','TARJETA','CREDITO'];
    $iv = $pdo->prepare('INSERT INTO ventas (sucursal_id,caja_id,usuario_id,cliente_id,tipo_comprobante,serie,numero,fecha,subtotal,igv,total,metodo_pago,monto_recibido,vuelto,estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $idt = $pdo->prepare('INSERT INTO detalle_ventas (venta_id,producto_id,cantidad,precio_unitario,costo_unitario,subtotal) VALUES (?,?,?,?,?,?)');
    $ser = $pdo->prepare('SELECT serie,correlativo FROM series_comprobantes WHERE sucursal_id=? AND tipo=?');
    $upd = $pdo->prepare('UPDATE series_comprobantes SET correlativo=? WHERE sucursal_id=? AND tipo=?');
    $corr = []; $nv = 0; $nh = 0; $tot = 0; $totHoy = 0; $hh = (int)date('G'); $now = time();
    $pdo->beginTransaction();
    for ($dt = strtotime($desde); $dt <= strtotime($hoy); $dt = strtotime(date('Y-m-d', $dt) . ' +1 day')) {
        $dia = date('Y-m-d', $dt); $esHoy = $dia === $hoy; $dow = (int)date('N', $dt);
        foreach ($sucs as $i => $suc) {
            $suc = (int)$suc; $us = $uDe[$suc] ?? [(int)$usu[0]['id']]; $f65 = $i === 0 ? 1 : 0.65;
            $n = $esHoy ? (int)((max(1, $hh - 6) * 3.2 + 4) * $f65) : (int)((($dow >= 5 ? 40 : 27) + rand(-5, 8)) * $f65);
            for ($k = 0; $k < $n; $k++) {
                $h = $esHoy ? rand(7, max(7, $hh)) : rand(7, 21);
                $f = $dia . sprintf(' %02d:%02d:%02d', $h, rand(0, 59), rand(0, 59));
                if ($esHoy && strtotime($f) > $now) $f = date('Y-m-d H:i:s', $now - rand(30, 3000));
                $tipo = rand(0, 9) < 6 ? 'TICKET' : (rand(0, 9) < 8 ? 'BOLETA' : 'FACTURA');
                if (!isset($corr[$suc][$tipo])) { $ser->execute([$suc, $tipo]); $r = $ser->fetch(); $corr[$suc][$tipo] = [$r['serie'] ?? 'H' . $suc, (int)($r['correlativo'] ?? 0)]; }
                $corr[$suc][$tipo][1]++;
                $its = []; $t = 0;
                for ($j = 0, $m = rand(1, 5); $j < $m; $j++) { $p = $prods[array_rand($prods)]; $q = rand(1, 3); $its[] = [$p['id'], $q, $p['precio_venta'], $p['precio_compra']]; $t += $q * $p['precio_venta']; }
                $met = $metodos[array_rand($metodos)]; $rec = $met === 'EFECTIVO' ? ceil($t / 10) * 10 : $t;
                $cli = (!$clis || rand(0, 3) === 0) ? 1 : $clis[array_rand($clis)];
                $est = (!$esHoy && rand(0, 99) < 2) ? 'ANULADA' : 'COMPLETADA';
                $iv->execute([$suc, $esHoy ? $caja[$suc] : null, $us[array_rand($us)], $cli, $tipo, $corr[$suc][$tipo][0], $corr[$suc][$tipo][1], $f, round($t / 1.18, 2), round($t - $t / 1.18, 2), round($t, 2), $met, $rec, $rec - $t, $est]);
                $vid = $pdo->lastInsertId();
                foreach ($its as $x) $idt->execute([$vid, $x[0], $x[1], $x[2], $x[3], $x[1] * $x[2]]);
                $nv++; if ($esHoy) { $nh++; $totHoy += $t; }
            }
        }
    }
    foreach ($corr as $suc => $ts) foreach ($ts as $tipo => $c) $upd->execute([$c[1], $suc, $tipo]);
    $pdo->commit();
    $msg = "Listo: $nv ventas registradas (hoy: $nh por S/ " . number_format($totHoy, 2) . ", desde $desde). Recarga el dashboard. Ya puedes borrar seed_hoy.php.";
}
?><!doctype html><meta charset="utf-8"><title>Ventas de hoy</title>
<body style="font-family:system-ui;max-width:520px;margin:60px auto;padding:0 16px">
<h2>Registrar ventas de hoy</h2>
<?php if ($msg) echo '<p style="background:#dcfce7;padding:12px;border-radius:8px">' . e($msg) . '</p>'; ?>
<p>Crea ventas de hoy ligadas a la caja abierta de cada sucursal (se reflejan en el dashboard y en caja) y rellena los días sin ventas desde la última venta registrada.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button style="padding:8px 20px">Generar ventas de hoy</button></form>
