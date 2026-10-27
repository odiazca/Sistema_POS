<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$v = q("SELECT v.*, COALESCE(c.nombre,'Cliente Varios') cliente, c.documento, c.tipo_doc, CONCAT(u.nombres,' ',u.apellidos) cajero FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=v.usuario_id WHERE v.id=?", [$id])->fetch();
if (!$v) { http_response_code(404); die('Venta no encontrada'); }
$items = q('SELECT d.*, p.nombre, p.unidad FROM detalle_ventas d JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?', [$id])->fetchAll();
$tit = ['TICKET' => 'TICKET DE VENTA', 'BOLETA' => 'BOLETA DE VENTA', 'FACTURA' => 'FACTURA'][$v['tipo_comprobante']];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title><?= e($v['serie'] . '-' . $v['numero']) ?></title>
<?php $w58 = cfg('print_ancho', '80') === '58'; ?>
<style>
@page{size:<?= $w58 ? '58' : '80' ?>mm auto;margin:0}
body{font:<?= $w58 ? '11' : '12' ?>px/1.35 'Courier New',monospace;width:<?= $w58 ? '54' : '76' ?>mm;margin:0 auto;padding:4mm 2mm;color:#000}
.c{text-align:center}.r{text-align:right}hr{border:0;border-top:1px dashed #000;margin:6px 0}
table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:1px 0}
h1{font-size:15px;margin:0}.b{font-weight:bold}
.noprint{text-align:center;margin-bottom:8px}@media print{.noprint{display:none}}
</style></head><body<?= isset($_GET['auto']) ? ' onload="window.print()" onafterprint="setTimeout(function(){window.close()},300)"' : '' ?>>
<div class="noprint"><button onclick="window.print()">Imprimir</button></div>
<?php $sx = q('SELECT nombre,direccion,telefono FROM sucursales WHERE id=?', [$v['sucursal_id']])->fetch(); ?>
<div class="c"><?php if (cfg('ticket_logo', '1') === '1' && ($lg = logo_url())): ?><img src="<?= e($lg) ?>" alt="" style="max-width:55%;max-height:70px;margin-bottom:3px"><br><?php endif; ?><h1><?= e(cfg('empresa_nombre')) ?></h1>RUC: <?= e(cfg('empresa_ruc')) ?><br><?= e($sx['direccion'] ?: cfg('empresa_direccion')) ?><br>Tel: <?= e($sx['telefono'] ?: cfg('empresa_telefono')) ?><br><b><?= e($sx['nombre']) ?></b></div>
<hr><div class="c b"><?= $tit ?><br><?= e($v['serie'] . '-' . str_pad((string)$v['numero'], 6, '0', STR_PAD_LEFT)) ?></div><hr>
Fecha: <?= date('d/m/Y H:i', strtotime($v['fecha'])) ?><br>Cajero: <?= e($v['cajero']) ?><br>Cliente: <?= e($v['cliente']) ?><?= $v['documento'] ? '<br>' . e($v['tipo_doc']) . ': ' . e($v['documento']) : '' ?>
<hr><table><tr class="b"><td>Cant</td><td>Descripción</td><td class="r">Importe</td></tr>
<?php foreach ($items as $i): ?><tr><td><?= nq($i['cantidad']) ?></td><td><?= e($i['nombre']) ?><br><small>x <?= nf($i['precio_unitario']) ?><?= $i['descuento'] > 0 ? ' · ' . e($i['promo'] ?: 'Dscto.') . ' -' . nf($i['descuento']) : '' ?></small></td><td class="r"><?= nf($i['subtotal']) ?></td></tr><?php endforeach; ?></table><hr>
<table><tr><td>Op. gravada</td><td class="r"><?= nf($v['subtotal']) ?></td></tr><tr><td><?= e(impuesto()) ?> (<?= e(cfg('igv', '18')) ?>%)</td><td class="r"><?= nf($v['igv']) ?></td></tr>
<?php if ($v['descuento'] > 0): ?><tr><td>Descuentos / ahorro</td><td class="r">-<?= nf($v['descuento']) ?></td></tr><?php endif; ?>
<tr class="b"><td>TOTAL <?= e(moneda()) ?></td><td class="r"><?= nf($v['total']) ?></td></tr>
<tr><td>Pago: <?= e($v['metodo_pago']) ?></td><td></td></tr>
<?php if ($v['monto_recibido'] !== null): ?><tr><td>Recibido</td><td class="r"><?= nf($v['monto_recibido']) ?></td></tr><tr><td>Vuelto</td><td class="r"><?= nf($v['vuelto']) ?></td></tr><?php endif; ?></table>
<?php if ($v['puntos_ganados'] > 0): ?><hr><div class="c">Ganaste <b><?= (int)$v['puntos_ganados'] ?></b> puntos con esta compra</div><?php endif; ?>
<?php if ($v['estado'] === 'ANULADA'): ?><hr><div class="c b">*** COMPROBANTE ANULADO ***</div><?php endif; ?>
<hr><div class="c"><?= e(cfg('ticket_mensaje')) ?></div>
</body></html>
