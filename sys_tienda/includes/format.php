<?php
/**
 * Formato de importes y datos de empresa · SysTienda
 * Lee la configuración (moneda, decimales, separadores) una sola vez por petición.
 */
function fmt_cfg(): array
{
    static $c = null;
    if ($c !== null) return $c;
    $d = ['moneda' => 'S/', 'moneda_codigo' => 'PEN', 'moneda_decimales' => '2', 'sep_decimal' => '.', 'sep_miles' => ',', 'moneda_posicion' => 'antes'];
    try { foreach (db()->query("SELECT clave,valor FROM configuracion WHERE clave IN ('moneda','moneda_codigo','moneda_decimales','sep_decimal','sep_miles','moneda_posicion')")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $d[$k] = (string)$v; } catch (Throwable $e) {}
    $sm = $d['sep_miles']; if ($sm === 'espacio') $sm = ' '; if ($sm === 'ninguno') $sm = '';
    $dec = max(0, min(4, (int)$d['moneda_decimales'])); $sd = $d['sep_decimal'] === ',' ? ',' : '.';
    if ($sm === $sd) $sm = $sd === '.' ? ',' : '.';       // nunca el mismo símbolo para ambos
    return $c = ['sym' => $d['moneda'], 'code' => $d['moneda_codigo'], 'dec' => $dec, 'sd' => $sd, 'sm' => $sm, 'pos' => $d['moneda_posicion'] === 'despues' ? 'despues' : 'antes'];
}

/** Número con el formato configurado (sin símbolo de moneda). $d = decimales (por defecto los de la moneda) */
function nf($v, ?int $d = null): string
{
    $f = fmt_cfg();
    return number_format((float)$v, $d ?? $f['dec'], $f['sd'], $f['sm']);
}
function moneda(): string { return fmt_cfg()['sym']; }
/** Importe con símbolo según la configuración: «S/ 1,234.50» o «1.234,50 €» */
function money($v): string
{
    $f = fmt_cfg(); $n = nf($v);
    return $f['pos'] === 'despues' ? $n . ' ' . $f['sym'] : $f['sym'] . ' ' . $n;
}
/** Cantidad sin ceros sobrantes (2 → «2», 1.5 → «1.5») */
function nq($v): string
{
    $f = fmt_cfg(); $s = rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
    return str_replace('.', $f['sd'], $s === '' ? '0' : $s);
}

/** URL pública del logo de la empresa o null */
function logo_url(): ?string
{
    $f = cfg('empresa_logo', '');
    return $f !== '' && is_file(dirname(__DIR__) . '/assets/uploads/empresa/' . $f) ? BASE_URL . '/assets/uploads/empresa/' . $f . '?v=' . filemtime(dirname(__DIR__) . '/assets/uploads/empresa/' . $f) : null;
}
function logo_path(): ?string
{
    $f = cfg('empresa_logo', '');
    $p = dirname(__DIR__) . '/assets/uploads/empresa/' . $f;
    return $f !== '' && is_file($p) ? $p : null;
}

function impuesto(): string { return cfg('impuesto_nombre', 'IGV'); }
