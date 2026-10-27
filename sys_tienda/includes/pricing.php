<?php
/**
 * Motor de precios: mayorista + promociones. Misma lógica que assets/js/pos.js (calcLinea).
 * El servidor recalcula siempre: nunca confía en precios/descuentos enviados por el navegador.
 */

/** Promociones vigentes hoy para una sucursal */
function promos_vigentes(int $suc): array
{
    return q("SELECT id,nombre,tipo,alcance,producto_id,categoria_id,valor,lleva,paga FROM promociones
              WHERE estado=1 AND CURDATE() BETWEEN fecha_inicio AND fecha_fin AND (sucursal_id IS NULL OR sucursal_id=?)", [$suc])->fetchAll();
}

/** Calcula una línea: devuelve [precio_unitario, descuento, nombre_promo|null, subtotal] */
function calc_linea(array $p, float $cant, array $promos): array
{
    $precio = (float)$p['precio_venta'];
    if ((float)($p['cantidad_mayor'] ?? 0) > 0 && (float)($p['precio_mayor'] ?? 0) > 0 && $cant >= (float)$p['cantidad_mayor']) $precio = (float)$p['precio_mayor'];
    $base = round($cant * $precio, 2);
    $best = 0.0; $name = null;
    foreach ($promos as $pr) {
        $ok = $pr['alcance'] === 'TODO'
            || ($pr['alcance'] === 'PRODUCTO' && (int)$pr['producto_id'] === (int)$p['id'])
            || ($pr['alcance'] === 'CATEGORIA' && (int)$pr['categoria_id'] === (int)$p['categoria_id']);
        if (!$ok) continue;
        $d = 0.0;
        if ($pr['tipo'] === 'PORCENTAJE') $d = $base * (float)$pr['valor'] / 100;
        elseif ($pr['tipo'] === 'MONTO') $d = min($base, (float)$pr['valor'] * $cant);
        elseif ($pr['tipo'] === 'NXM' && (int)$pr['lleva'] > (int)$pr['paga'] && (int)$pr['lleva'] > 0) {
            $d = floor($cant / (int)$pr['lleva']) * ((int)$pr['lleva'] - (int)$pr['paga']) * $precio;
        }
        $d = round(min($d, $base), 2);
        if ($d > $best) { $best = $d; $name = $pr['nombre']; }
    }
    return [$precio, $best, $name, round($base - $best, 2)];
}

function es_cumple(?string $fn): bool { return $fn && date('m-d', strtotime($fn)) === date('m-d'); }
