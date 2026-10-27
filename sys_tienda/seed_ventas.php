<?php
/** Agrega ventas históricas de demostración ANTES de la venta más antigua existente. Solo administrador. Borra este archivo al terminar. */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
session_start();
require_login();
if ((int)(usuario()['rol_id'] ?? 0) !== 1) { http_response_code(403); die('Solo administrador.'); }
$pdo = db(); $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? '')) {
    set_time_limit(300);
    $dias = max(1, min(180, (int)($_POST['dias'] ?? 60)));
    $ini = $pdo->query("SELECT MIN(DATE(fecha)) FROM ventas")->fetchColumn() ?: date('Y-m-d');
    $sucs = $pdo->query("SELECT id FROM sucursales WHERE estado=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $prods = $pdo->query("SELECT id,precio_compra,precio_venta FROM productos WHERE estado=1 AND precio_venta>0")->fetchAll();
    $clis = $pdo->query("SELECT id FROM clientes WHERE estado=1 AND id>1")->fetchAll(PDO::FETCH_COLUMN);
    $usu = $pdo->query("SELECT id,sucursal_id FROM usuarios WHERE estado=1 ORDER BY id")->fetchAll();
    if (!$prods || !$sucs || !$usu) die('Faltan productos, sucursales o usuarios.');
    $uDe = []; foreach ($usu as $u) $uDe[(int)$u['sucursal_id']][] = (int)$u['id'];
    $metodos = ['EFECTIVO','EFECTIVO','EFECTIVO','EFECTIVO','YAPE','YAPE','PLIN','TARJETA','TARJETA','CREDITO'];
    $iv = $pdo->prepare('INSERT INTO ventas (sucursal_id,caja_id,usuario_id,cliente_id,tipo_comprobante,serie,numero,fecha,subtotal,igv,total,metodo_pago,monto_recibido,vuelto,estado) VALUES (?,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $idt = $pdo->prepare('INSERT INTO detalle_ventas (venta_id,producto_id,cantidad,precio_unitario,costo_unitario,subtotal) VALUES (?,?,?,?,?,?)');
    $ser = $pdo->prepare('SELECT serie,correlativo FROM series_comprobantes WHERE sucursal_id=? AND tipo=?');
    $upd = $pdo->prepare('UPDATE series_comprobantes SET correlativo=? WHERE sucursal_id=? AND tipo=?');
    $corr = []; $nv = 0; $tot = 0;
    $pdo->beginTransaction();
    for ($d = $dias; $d >= 1; $d--) {
        $dia = strtotime("$ini -$d days"); $dow = (int)date('N', $dia);
        foreach ($sucs as $i => $suc) {
            $suc = (int)$suc; $us = $uDe[$suc] ?? [$usu[0]['id']];
            $base = (($dow >= 5 ? 40 : 27) + rand(-5, 8)) * ($i === 0 ? 1 : 0.65);
            for ($k = 0; $k < (int)$base; $k++) {
                $f = date('Y-m-d', $dia) . sprintf(' %02d:%02d:%02d', rand(7, 21), rand(0, 59), rand(0, 59));
                $tipo = rand(0, 9) < 6 ? 'TICKET' : (rand(0, 9) < 8 ? 'BOLETA' : 'FACTURA');
                if (!isset($corr[$suc][$tipo])) { $ser->execute([$suc, $tipo]); $r = $ser->fetch(); $corr[$suc][$tipo] = [$r['serie'] ?? 'H' . $suc, (int)($r['correlativo'] ?? 0)]; }
                $corr[$suc][$tipo][1]++;
                $its = []; $t = 0;
                for ($j = 0, $m = rand(1, 5); $j < $m; $j++) { $p = $prods[array_rand($prods)]; $q = rand(1, 3); $its[] = [$p['id'], $q, $p['precio_venta'], $p['precio_compra']]; $t += $q * $p['precio_venta']; }
                $met = $metodos[array_rand($metodos)]; $rec = $met === 'EFECTIVO' ? ceil($t / 10) * 10 : $t;
                $cli = (!$clis || rand(0, 3) === 0) ? 1 : $clis[array_rand($clis)];
                $est = rand(0, 99) < 2 ? 'ANULADA' : 'COMPLETADA';
                $iv->execute([$suc, $us[array_rand($us)], $cli, $tipo, $corr[$suc][$tipo][0], $corr[$suc][$tipo][1], $f, round($t / 1.18, 2), round($t - $t / 1.18, 2), round($t, 2), $met, $rec, $rec - $t, $est]);
                $vid = $pdo->lastInsertId();
                foreach ($its as $x) $idt->execute([$vid, $x[0], $x[1], $x[2], $x[3], $x[1] * $x[2]]);
                $nv++; if ($est === 'COMPLETADA') $tot += $t;
            }
        }
    }
    foreach ($corr as $suc => $ts) foreach ($ts as $tipo => $c) $upd->execute([$c[1], $suc, $tipo]);
    $pdo->commit();
    $msg = "Listo: se agregaron $nv ventas de $dias días anteriores al $ini (total S/ " . number_format($tot, 2) . "). Ya puedes borrar seed_ventas.php.";
}
?><!doctype html><meta charset="utf-8"><title>Cargar ventas</title>
<body style="font-family:system-ui;max-width:520px;margin:60px auto;padding:0 16px">
<h2>Cargar más ventas de demostración</h2>
<?php if ($msg) echo '<p style="background:#dcfce7;padding:12px;border-radius:8px">' . e($msg) . '</p>'; ?>
<p>Agrega ventas en días anteriores a la venta más antigua registrada. No modifica el stock actual.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
Días a agregar: <input type="number" name="dias" value="60" min="1" max="180" style="width:80px"> <button style="padding:6px 16px">Generar</button></form>
