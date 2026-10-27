<?php
/** Reporte imprimible de cierre / arqueo de caja */
require_once __DIR__ . '/includes/helpers.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$c = q("SELECT c.*, CONCAT(u.nombres,' ',u.apellidos) cajero, s.nombre sucursal, s.direccion FROM cajas c JOIN usuarios u ON u.id=c.usuario_id JOIN sucursales s ON s.id=c.sucursal_id WHERE c.id=?", [$id])->fetch();
if (!$c || (usuario()['rol_id'] !== ROL_ADMIN && ((int)$c['usuario_id'] !== usuario()['id']))) { http_response_code(404); die('Caja no encontrada'); }
$r = resumen_caja($c);
$movs = q('SELECT * FROM movimientos_caja WHERE caja_id=? ORDER BY id', [$id])->fetchAll();
$dev = (float)q("SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=? AND tipo='EGRESO' AND concepto LIKE 'Devolución%'", [$id])->fetchColumn();
$anul = q("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE caja_id=? AND estado='ANULADA'", [$id])->fetch();
$tipos = q("SELECT tipo_comprobante t, COUNT(*) n, SUM(total) m FROM ventas WHERE caja_id=? AND estado='COMPLETADA' GROUP BY tipo_comprobante", [$id])->fetchAll();
$cerrada = $c['estado'] === 'CERRADA'; $dif = $cerrada ? round($c['monto_cierre'] - $r['esperado'], 2) : null;
$nf = fn($v) => nf($v);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Reporte de caja #<?= $id ?></title>
<style>
@page{size:A4;margin:14mm}body{font:13px/1.45 'Segoe UI',Arial,sans-serif;color:#0f172a;margin:0}.wrap{max-width:760px;margin:0 auto;padding:10px}
.top{display:flex;justify-content:space-between;border-bottom:3px solid #5b5ef4;padding-bottom:10px;margin-bottom:14px}h1{margin:0;font-size:20px;color:#5b5ef4}h3{margin:16px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#475569}
.muted{color:#64748b}table{width:100%;border-collapse:collapse}td,th{padding:6px 8px;border-bottom:1px solid #e8ebf3;text-align:left}th{background:#f1f3fb;font-size:12px}.r{text-align:right}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.big td{font-size:15px}.tot td{font-weight:700;border-top:2px solid #0f172a}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12px}.ok{background:#d1fae5;color:#047857}.bad{background:#ffe4e6;color:#be123c}.warn{background:#fef3c7;color:#b45309}
.sign{display:flex;gap:40px;margin-top:60px}.sign div{flex:1;border-top:1px solid #0f172a;text-align:center;padding-top:4px;font-size:12px}
.noprint{text-align:center;margin:10px}@media print{.noprint{display:none}}
</style></head><body<?= isset($_GET['auto']) ? ' onload="window.print()"' : '' ?>>
<div class="noprint"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
<div class="wrap">
<div class="top"><div><h1>Reporte de caja #<?= $id ?></h1><div class="muted"><?= e(cfg('empresa_nombre')) ?> · <?= e($c['sucursal']) ?></div></div>
<div style="text-align:right"><span class="badge <?= $cerrada ? 'ok' : 'warn' ?>"><?= $cerrada ? 'CAJA CERRADA' : 'CAJA ABIERTA (parcial)' ?></span><div class="muted">Impreso: <?= date('d/m/Y H:i') ?></div></div></div>
<table><tr><td><b>Cajero:</b> <?= e($c['cajero']) ?></td><td><b>Apertura:</b> <?= date('d/m/Y H:i', strtotime($c['fecha_apertura'])) ?></td><td><b>Cierre:</b> <?= $c['fecha_cierre'] ? date('d/m/Y H:i', strtotime($c['fecha_cierre'])) : '—' ?></td></tr></table>
<div class="grid"><div><h3>Ventas por método de pago</h3><table>
<?php foreach ($r['por_metodo'] as $m => $t): ?><tr><td><?= e(ucfirst(strtolower($m))) ?></td><td class="r"><?= $nf($t) ?></td></tr><?php endforeach; ?>
<?php if ($r['devoluciones'] > 0): ?><tr><td>Devoluciones del turno (ya restadas)</td><td class="r">−<?= $nf($r['devoluciones']) ?></td></tr><?php endif; ?>
<tr class="tot"><td>Ventas netas (<?= (int)$r['ventas_n'] ?> comprob.)</td><td class="r"><?= $nf($r['ventas_total']) ?></td></tr></table>
<h3>Comprobantes</h3><table><?php foreach ($tipos as $t): ?><tr><td><?= e(ucfirst(strtolower($t['t']))) ?>s</td><td class="r"><?= (int)$t['n'] ?></td><td class="r"><?= $nf($t['m']) ?></td></tr><?php endforeach; ?>
<?php if ($anul['n']): ?><tr><td>Anuladas</td><td class="r"><?= (int)$anul['n'] ?></td><td class="r"><?= $nf($anul['t']) ?></td></tr><?php endif; ?></table></div>
<div><h3>Arqueo de efectivo</h3><table class="big">
<tr><td>Monto de apertura</td><td class="r"><?= $nf($c['monto_apertura']) ?></td></tr><tr><td>+ Ventas en efectivo</td><td class="r"><?= $nf($r['efectivo']) ?></td></tr>
<tr><td>+ Otros ingresos</td><td class="r"><?= $nf($r['ingresos']) ?></td></tr><tr><td>− Egresos<?= $dev > 0 ? ' (incl. devoluciones ' . $nf($dev) . ')' : '' ?></td><td class="r"><?= $nf($r['egresos']) ?></td></tr>
<tr class="tot"><td>Efectivo esperado</td><td class="r"><?= $nf($r['esperado']) ?></td></tr>
<?php if ($cerrada): ?><tr><td>Efectivo contado</td><td class="r"><?= $nf($c['monto_cierre']) ?></td></tr><tr class="tot"><td>Diferencia</td><td class="r"><span class="badge <?= abs($dif) < .01 ? 'ok' : ($dif < 0 ? 'bad' : 'warn') ?>"><?= ($dif > 0 ? '+' : '') . $nf($dif) ?> <?= abs($dif) < .01 ? '· Cuadra' : ($dif < 0 ? '· Faltante' : '· Sobrante') ?></span></td></tr><?php endif; ?></table></div></div>
<?php if ($movs): ?><h3>Movimientos de efectivo</h3><table><thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th class="r">Monto</th></tr></thead><tbody>
<?php foreach ($movs as $m): ?><tr><td><?= date('H:i', strtotime($m['fecha'])) ?></td><td><?= e($m['tipo']) ?></td><td><?= e($m['concepto']) ?></td><td class="r"><?= $m['tipo'] === 'EGRESO' ? '−' : '+' ?><?= $nf($m['monto']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if ($c['observacion']): ?><h3>Observación</h3><p><?= e($c['observacion']) ?></p><?php endif; ?>
<div class="sign"><div>Cajero(a)<br><?= e($c['cajero']) ?></div><div>Supervisor / Administrador</div></div>
</div></body></html>
