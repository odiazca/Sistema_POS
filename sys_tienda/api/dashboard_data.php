<?php
require_once __DIR__ . '/../includes/helpers.php';
require_login(true);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = db();
$q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };
$one = fn($sql, $p = []) => $q($sql, $p)->fetchColumn();
$cfg = $pdo->query('SELECT clave,valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);

$ok = "v.estado='COMPLETADA'" . suc_where('v');
$SC = suc();
/** devoluciones (monto) y utilidad devuelta, para ventas netas */
$dvM = fn(string $c) => (float)$one("SELECT COALESCE(SUM(total),0) FROM devoluciones dv WHERE $c" . suc_where('dv'));
$dvU = fn(string $c) => (float)$one("SELECT COALESCE(SUM(dd.subtotal-dd.cantidad*dd.costo_unitario),0) FROM detalle_devoluciones dd JOIN devoluciones dv ON dv.id=dd.devolucion_id WHERE $c" . suc_where('dv'));
$rango = (int)($_GET['rango'] ?? 7); if (!in_array($rango, [7, 30, 60], true)) $rango = 7;

/* ---------- KPIs ---------- */
$ventasHoy   = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE()");
$ventasAyer  = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE()-INTERVAL 1 DAY");
$ventasHoy -= $dvM('DATE(dv.fecha)=CURDATE()'); $ventasAyer -= $dvM('DATE(dv.fecha)=CURDATE()-INTERVAL 1 DAY');
$ticketsHoy  = (int)$one("SELECT COUNT(*) FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE()");
$ticketsAyer = (int)$one("SELECT COUNT(*) FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE()-INTERVAL 1 DAY");
$utilSql = "SELECT COALESCE(SUM(d.subtotal - d.cantidad*d.costo_unitario),0) FROM detalle_ventas d JOIN ventas v ON v.id=d.venta_id WHERE $ok AND ";
$utilHoy  = (float)$one($utilSql . "DATE(v.fecha)=CURDATE()");
$utilAyer = (float)$one($utilSql . "DATE(v.fecha)=CURDATE()-INTERVAL 1 DAY");
$utilHoy -= $dvU('DATE(dv.fecha)=CURDATE()'); $utilAyer -= $dvU('DATE(dv.fecha)=CURDATE()-INTERVAL 1 DAY');
$mesAct   = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas v WHERE $ok AND DATE_FORMAT(fecha,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')") - $dvM("DATE_FORMAT(dv.fecha,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$mesPrev  = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas v WHERE $ok AND DATE_FORMAT(fecha,'%Y-%m')=DATE_FORMAT(CURDATE()-INTERVAL 1 MONTH,'%Y-%m')") - $dvM("DATE_FORMAT(dv.fecha,'%Y-%m')=DATE_FORMAT(CURDATE()-INTERVAL 1 MONTH,'%Y-%m')");
$pct = fn($a, $b) => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

// sparklines (últimos 14 días)
$spark = $q("SELECT DATE(fecha) d, SUM(total) t, COUNT(*) n FROM ventas v WHERE $ok AND fecha >= CURDATE()-INTERVAL 13 DAY GROUP BY d ORDER BY d")->fetchAll();
$sparkMap = array_column($spark, null, 'd');
$spV = $spT = [];
for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i days")); $spV[] = (float)($sparkMap[$d]['t'] ?? 0); $spT[] = (int)($sparkMap[$d]['n'] ?? 0); }

$kpis = [
    'ventas_hoy'   => ['valor' => $ventasHoy, 'var' => $pct($ventasHoy, $ventasAyer), 'spark' => $spV],
    'tickets_hoy'  => ['valor' => $ticketsHoy, 'var' => $pct($ticketsHoy, $ticketsAyer), 'spark' => $spT],
    'utilidad_hoy' => ['valor' => $utilHoy, 'var' => $pct($utilHoy, $utilAyer)],
    'ticket_prom'  => ['valor' => $ticketsHoy ? $ventasHoy / $ticketsHoy : 0],
    'mes'          => ['valor' => $mesAct, 'var' => $pct($mesAct, $mesPrev), 'anterior' => $mesPrev],
    'por_cobrar'   => ['valor' => (float)$one('SELECT COALESCE(SUM(saldo_deuda),0) FROM clientes'), 'clientes' => (int)$one('SELECT COUNT(*) FROM clientes WHERE saldo_deuda>0')],
    'meta'         => ['objetivo' => (float)($cfg['meta_diaria'] ?? 2500), 'actual' => $ventasHoy],
];

/* ---------- Tendencia de ventas / utilidad ---------- */
$rows = $q("SELECT DATE(v.fecha) d, SUM(v.total) t FROM ventas v WHERE $ok AND v.fecha >= CURDATE()-INTERVAL ".($rango-1)." DAY GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$urows = $q("SELECT DATE(v.fecha) d, SUM(d.subtotal-d.cantidad*d.costo_unitario) u FROM detalle_ventas d JOIN ventas v ON v.id=d.venta_id WHERE $ok AND v.fecha >= CURDATE()-INTERVAL ".($rango-1)." DAY GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($pdo->query("SELECT DATE(dv.fecha) d, SUM(dv.total) t FROM devoluciones dv WHERE dv.fecha >= CURDATE()-INTERVAL ".($rango-1)." DAY" . suc_where('dv') . " GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR) as $d => $t) if (isset($rows[$d])) $rows[$d] -= $t;
$tend = ['labels' => [], 'ventas' => [], 'utilidad' => []];
for ($i = $rango - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $tend['labels'][]   = date('d/m', strtotime($d));
    $tend['ventas'][]   = round((float)($rows[$d] ?? 0), 2);
    $tend['utilidad'][] = round((float)($urows[$d] ?? 0), 2);
}

/* ---------- Ventas por hora: hoy vs ayer ---------- */
$hoy = $q("SELECT HOUR(fecha) h, SUM(total) t FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE() GROUP BY h")->fetchAll(PDO::FETCH_KEY_PAIR);
$ayer = $q("SELECT HOUR(fecha) h, SUM(total) t FROM ventas v WHERE $ok AND DATE(fecha)=CURDATE()-INTERVAL 1 DAY GROUP BY h")->fetchAll(PDO::FETCH_KEY_PAIR);
$horas = ['labels' => [], 'hoy' => [], 'ayer' => []];
for ($h = 7; $h <= 21; $h++) { $horas['labels'][] = sprintf('%02d:00', $h); $horas['hoy'][] = $h <= (int)date('G') ? round((float)($hoy[$h] ?? 0), 2) : null; $horas['ayer'][] = round((float)($ayer[$h] ?? 0), 2); }

/* ---------- Categorías (30 días) ---------- */
$cat = $q("SELECT c.nombre, c.color, SUM(d.subtotal) t FROM detalle_ventas d JOIN ventas v ON v.id=d.venta_id JOIN productos p ON p.id=d.producto_id JOIN categorias c ON c.id=p.categoria_id WHERE $ok AND v.fecha >= CURDATE()-INTERVAL 29 DAY GROUP BY c.id ORDER BY t DESC")->fetchAll();

/* ---------- Métodos de pago (30 días) ---------- */
$pagos = $q("SELECT metodo_pago m, SUM(total) t, COUNT(*) n FROM ventas v WHERE $ok AND fecha >= CURDATE()-INTERVAL 29 DAY GROUP BY m ORDER BY t DESC")->fetchAll();

/* ---------- Mapa de calor día × hora (últimos 60 días) ---------- */
$hm = $q("SELECT WEEKDAY(fecha) w, HOUR(fecha) h, COUNT(*) n FROM ventas v WHERE $ok GROUP BY w,h")->fetchAll();

/* ---------- Top productos / alertas / recientes ---------- */
$top = $q("SELECT p.nombre, c.nombre cat, c.color, SUM(d.cantidad) cant, SUM(d.subtotal) total FROM detalle_ventas d JOIN ventas v ON v.id=d.venta_id JOIN productos p ON p.id=d.producto_id JOIN categorias c ON c.id=p.categoria_id WHERE $ok AND v.fecha >= CURDATE()-INTERVAL 29 DAY GROUP BY p.id ORDER BY total DESC LIMIT 6")->fetchAll();
$sc = stock_col();
$bajo = $q("SELECT p.nombre, $sc AS stock, p.stock_minimo, p.unidad FROM productos p WHERE p.estado=1 AND $sc<=p.stock_minimo ORDER BY ($sc/p.stock_minimo) ASC LIMIT 6")->fetchAll();
$bajoTotal = (int)$one("SELECT COUNT(*) FROM productos p WHERE p.estado=1 AND $sc<=p.stock_minimo");
$vence = $q("SELECT nombre, fecha_vencimiento, DATEDIFF(fecha_vencimiento,CURDATE()) dias FROM productos WHERE estado=1 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento<=CURDATE()+INTERVAL 30 DAY ORDER BY fecha_vencimiento LIMIT 4")->fetchAll();
$rec = $q("SELECT v.id, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, v.tipo_comprobante tipo, v.total, v.metodo_pago, v.estado, DATE_FORMAT(v.fecha,'%H:%i') hora, DATE(v.fecha)=CURDATE() es_hoy, DATE_FORMAT(v.fecha,'%d/%m') dia, COALESCE(c.nombre,'Cliente Varios') cliente FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE 1=1" . suc_where('v') . " ORDER BY v.fecha DESC, v.id DESC LIMIT 7")->fetchAll();

/* ---------- Estado de caja ---------- */
$caja = $q("SELECT c.*, CONCAT(u.nombres,' ',u.apellidos) cajero FROM cajas c JOIN usuarios u ON u.id=c.usuario_id WHERE c.estado='ABIERTA'" . suc_where('c') . " ORDER BY c.id DESC LIMIT 1")->fetch();
$cajaInfo = null;
if ($caja) {
    $efec = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas WHERE caja_id=? AND estado='COMPLETADA' AND metodo_pago='EFECTIVO'", [$caja['id']]);
    $dig  = (float)$one("SELECT COALESCE(SUM(total),0) FROM ventas WHERE caja_id=? AND estado='COMPLETADA' AND metodo_pago<>'EFECTIVO'", [$caja['id']]);
    $cajaInfo = ['cajero' => $caja['cajero'], 'apertura' => date('H:i', strtotime($caja['fecha_apertura'])), 'inicial' => (float)$caja['monto_apertura'],
                 'efectivo' => $efec, 'digital' => $dig, 'esperado' => (float)$caja['monto_apertura'] + $efec];
}

/* ---------- Comparativo por sucursal (hoy y mes) y cumpleaños ---------- */
$porSuc = $q("SELECT s.id, s.nombre,
    COALESCE((SELECT SUM(total) FROM ventas v WHERE v.sucursal_id=s.id AND v.estado='COMPLETADA' AND DATE(v.fecha)=CURDATE()),0)
      - COALESCE((SELECT SUM(total) FROM devoluciones dv WHERE dv.sucursal_id=s.id AND DATE(dv.fecha)=CURDATE()),0) hoy,
    (SELECT COUNT(*) FROM ventas v WHERE v.sucursal_id=s.id AND v.estado='COMPLETADA' AND DATE(v.fecha)=CURDATE()) tickets,
    COALESCE((SELECT SUM(total) FROM ventas v WHERE v.sucursal_id=s.id AND v.estado='COMPLETADA' AND DATE_FORMAT(v.fecha,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')),0) mes
    FROM sucursales s WHERE s.estado=1 ORDER BY s.id")->fetchAll();
$cumples = $q("SELECT id, nombre, telefono, DATE_FORMAT(fecha_nacimiento,'%d/%m') dia, (DATE_FORMAT(fecha_nacimiento,'%m-%d')=DATE_FORMAT(CURDATE(),'%m-%d')) hoy
    FROM clientes WHERE estado=1 AND id<>1 AND fecha_nacimiento IS NOT NULL
    AND DATE_FORMAT(fecha_nacimiento,'%m-%d') BETWEEN DATE_FORMAT(CURDATE(),'%m-%d') AND DATE_FORMAT(CURDATE()+INTERVAL 7 DAY,'%m-%d') ORDER BY DATE_FORMAT(fecha_nacimiento,'%m-%d') LIMIT 5")->fetchAll();
$cotPend = (int)$one("SELECT COUNT(*) FROM cotizaciones c WHERE c.estado='PENDIENTE' AND c.vigencia>=CURDATE()" . suc_where('c'));

echo json_encode([
    'sucursal' => suc_nombre(), 'por_sucursal' => can('sucursales') ? $porSuc : [], 'cumples' => $cumples, 'cot_pendientes' => $cotPend,
    'actualizado' => date('H:i:s'), 'kpis' => $kpis, 'tendencia' => $tend, 'horas' => $horas,
    'categorias' => $cat, 'pagos' => $pagos, 'heatmap' => $hm, 'top' => $top,
    'stock_bajo' => $bajo, 'stock_bajo_total' => $bajoTotal, 'por_vencer' => $vence,
    'recientes' => $rec, 'caja' => $cajaInfo,
    'resumen_inv' => ['productos' => (int)$one('SELECT COUNT(*) FROM productos WHERE estado=1'), 'valor' => (float)$one('SELECT COALESCE(SUM(' . stock_col() . '*p.precio_compra),0) FROM productos p WHERE p.estado=1')],
], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
