<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/escpos.php';
api_guard([1, 2], true);
$b = body(); $a = $b['a'] ?? '';
if (cfg('print_modo', 'navegador') === 'navegador') json_fail('La impresión directa no está activada. Se usará la vista de impresión del navegador.', 409);
try {
    switch ($a) {
    case 'ticket':
        $id = (int)($b['id'] ?? 0); $sc = suc();
        $v = q('SELECT metodo_pago, sucursal_id FROM ventas WHERE id=?', [$id])->fetch();
        if (!$v || ($sc && (int)$v['sucursal_id'] !== $sc)) json_fail('Venta no encontrada.', 404);
        // El cajón solo se abre en la venta recién cobrada en efectivo (no al reimprimir)
        $abrir = cfg('print_cajon', '0') === '1' && empty($b['reimp']) && $v['metodo_pago'] === 'EFECTIVO';
        printer_send(escpos_ticket($id, $abrir)); json_out(['ok' => true]);
    case 'cajon':
        if (cfg('print_cajon', '0') !== '1') json_fail('El cajón monedero no está activado en Configuración → Impresión.');
        printer_send((new Escpos())->cajon()->bytes()); audit('CAJON', 'caja', null, 'Apertura manual del cajón'); json_out(['ok' => true]);
    case 'cierre':
        $c = q('SELECT * FROM cajas WHERE id=?', [(int)($b['id'] ?? 0)])->fetch();
        if (!$c || (usuario()['rol_id'] !== 1 && (int)$c['usuario_id'] !== usuario()['id'])) json_fail('Caja no encontrada.', 404);
        printer_send(escpos_cierre($c)); json_out(['ok' => true]);
    case 'prueba':
        $p = new Escpos();
        $p->align('c')->bold(true)->big(true)->ln('PRUEBA OK')->big(false)->bold(false)->ln(cfg('empresa_nombre'))->ln(date('d/m/Y H:i'))->align('l')->sep();
        $p->ln('Ancho: ' . $p->cols . ' columnas')->ln('Tildes: áéíóú ñ Ñ ¿? ¡! S/ 12.50')->lr('Izquierda', 'Derecha')->sep();
        $p->center('Si lees esto bien, todo está listo.')->cut();
        printer_send($p->bytes());
        if (cfg('print_cajon', '0') === '1') printer_send((new Escpos())->cajon()->bytes());
        json_out(['ok' => true]);
    }
} catch (RuntimeException $e) { json_fail($e->getMessage(), 502); }
json_fail('Acción no válida');
