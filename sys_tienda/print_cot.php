<?php
require_once __DIR__ . '/includes/helpers.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$c = q("SELECT c.*, COALESCE(cl.nombre,'Cliente Varios') cliente, cl.documento, cl.tipo_doc, cl.telefono, CONCAT(u.nombres,' ',u.apellidos) vendedor FROM cotizaciones c LEFT JOIN clientes cl ON cl.id=c.cliente_id JOIN usuarios u ON u.id=c.usuario_id WHERE c.id=?" . suc_where('c'), [$id])->fetch();
if (!$c) { http_response_code(404); die('Cotización no encontrada'); }
$items = q('SELECT d.*, p.nombre, p.unidad, p.codigo FROM detalle_cotizaciones d JOIN productos p ON p.id=d.producto_id WHERE d.cotizacion_id=?', [$id])->fetchAll();
$sx = q('SELECT nombre,direccion,telefono FROM sucursales WHERE id=?', [$c['sucursal_id']])->fetch();
$igv = (float)cfg('igv', '18'); $sub = $c['total'] / (1 + $igv / 100);
$nro = 'COT-' . str_pad((string)$c['id'], 5, '0', STR_PAD_LEFT);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><title><?= e($nro) ?></title>
<style>
@page{size:A4;margin:14mm}
body{font:13px/1.45 'Segoe UI',Arial,sans-serif;color:#0f172a;margin:0}
.wrap{max-width:780px;margin:0 auto;padding:10px}
.top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #5b5ef4;padding-bottom:12px;margin-bottom:16px}
h1{margin:0;font-size:22px;color:#5b5ef4}.muted{color:#64748b}
.box{background:#f4f6fd;border-radius:10px;padding:10px 14px;margin-bottom:14px}
table{width:100%;border-collapse:collapse}th{background:#5b5ef4;color:#fff;text-align:left;padding:7px 9px;font-size:12px}td{padding:7px 9px;border-bottom:1px solid #e8ebf3}
.r{text-align:right}.tot{margin-left:auto;width:250px;margin-top:12px}.tot div{display:flex;justify-content:space-between;padding:3px 0}.tot .g{font-size:17px;font-weight:700;border-top:2px solid #0f172a;margin-top:4px;padding-top:6px}
.noprint{text-align:center;margin:10px}@media print{.noprint{display:none}}
.foot{margin-top:26px;font-size:11.5px;color:#64748b;border-top:1px dashed #cbd5e1;padding-top:10px}
</style></head><body<?= isset($_GET['auto']) ? ' onload="window.print()"' : '' ?>>
<div class="noprint"><button onclick="window.print()">Imprimir / Guardar PDF</button></div>
<div class="wrap">
<div class="top"><div><?php if ($lg = logo_url()): ?><img src="<?= e($lg) ?>" alt="" style="max-height:60px;max-width:180px;margin-bottom:6px"><?php endif; ?><h1><?= e(cfg('empresa_nombre')) ?></h1><div class="muted">RUC <?= e(cfg('empresa_ruc')) ?><br><?= e($sx['direccion'] ?: cfg('empresa_direccion')) ?> · Tel. <?= e($sx['telefono'] ?: cfg('empresa_telefono')) ?><br><?= e($sx['nombre']) ?></div></div>
<div class="r"><b style="font-size:18px">COTIZACIÓN</b><br><b><?= e($nro) ?></b><br><span class="muted">Fecha: <?= date('d/m/Y', strtotime($c['fecha'])) ?><br>Válida hasta: <b><?= date('d/m/Y', strtotime($c['vigencia'])) ?></b></span></div></div>
<div class="box"><b>Cliente:</b> <?= e($c['cliente']) ?><?= $c['documento'] ? ' · ' . e($c['tipo_doc']) . ' ' . e($c['documento']) : '' ?><br><b>Atendido por:</b> <?= e($c['vendedor']) ?></div>
<table><thead><tr><th>#</th><th>Descripción</th><th class="r">Cant.</th><th class="r">P. unit.</th><th class="r">Importe</th></tr></thead><tbody>
<?php foreach ($items as $n => $i): ?><tr><td><?= $n + 1 ?></td><td><?= e($i['nombre']) ?> <span class="muted">(<?= e($i['unidad']) ?>)</span></td><td class="r"><?= rtrim(rtrim(nf($i['cantidad']), '0'), '.') ?></td><td class="r"><?= nf($i['precio_unitario']) ?></td><td class="r"><?= nf($i['subtotal']) ?></td></tr><?php endforeach; ?></tbody></table>
<div class="tot"><div><span>Subtotal</span><span><?= nf($sub) ?></span></div><div><span><?= e(impuesto()) ?> (<?= e($igv) ?>%)</span><span><?= nf($c['total'] - $sub) ?></span></div><div class="g"><span>TOTAL <?= e(moneda()) ?></span><span><?= nf($c['total']) ?></span></div></div>
<?php if ($c['nota']): ?><div class="foot"><b>Condiciones:</b> <?= e($c['nota']) ?></div><?php endif; ?>
<div class="foot">Precios incluyen <?= e(impuesto()) ?>. Sujetos a disponibilidad de stock. Cotización válida hasta el <?= date('d/m/Y', strtotime($c['vigencia'])) ?>. ¡Gracias por su preferencia!</div>
</div></body></html>
