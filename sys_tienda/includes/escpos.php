<?php
/**
 * Impresión térmica ESC/POS · SysTienda
 * Genera el ticket en comandos ESC/POS y lo envía a la impresora (red, compartida de Windows, COM/LPT o dispositivo USB).
 */
class Escpos
{
    private string $b = '';
    public int $cols;
    private int $cp;

    public function __construct(?int $ancho = null)
    {
        $ancho = $ancho ?? (int)cfg('print_ancho', '80');
        $this->cols = $ancho === 58 ? 32 : 48;
        $this->cp = (int)cfg('print_codepage', '19');   // 19 = CP858 (Epson y compatibles) · 16 = WPC1252 · 0 = CP437
        $this->b = "\x1B\x40" . "\x1B\x74" . chr($this->cp);
    }
    private function enc(string $s): string
    {
        $map = [19 => 'CP858', 16 => 'CP1252', 0 => 'CP437'];
        $out = @iconv('UTF-8', ($map[$this->cp] ?? 'CP858') . '//TRANSLIT//IGNORE', $s);
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
    }
    public function align(string $a): self { $this->b .= "\x1B\x61" . chr(['l' => 0, 'c' => 1, 'r' => 2][$a] ?? 0); return $this; }
    public function bold(bool $on): self { $this->b .= "\x1B\x45" . chr($on ? 1 : 0); return $this; }
    public function big(bool $on): self { $this->b .= "\x1D\x21" . chr($on ? 0x11 : 0); return $this; }
    /** Logo en monocromo (GS v 0). Requiere GD; si algo falla simplemente no se imprime. */
    public function logo(?string $path, int $maxPx = 256): self
    {
        if (!$path || !function_exists('imagecreatetruecolor') || !is_file($path)) return $this;
        $info = @getimagesize($path); if (!$info) return $this;
        $f = [IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$info[2]] ?? null;
        if (!$f || !function_exists($f) || !($im = @$f($path))) return $this;
        $maxPx = min($maxPx, $this->cols >= 48 ? 320 : 220);
        $r = min($maxPx / $info[0], 120 / $info[1], 1.0); $w = max(8, (int)round($info[0] * $r)); $h = max(1, (int)round($info[1] * $r));
        $w = (int)(ceil($w / 8) * 8);
        $c = imagecreatetruecolor($w, $h); imagefill($c, 0, 0, imagecolorallocate($c, 255, 255, 255)); imagecopyresampled($c, $im, 0, 0, 0, 0, (int)round($info[0] * $r), $h, $info[0], $info[1]);
        $bytes = intdiv($w, 8); $d = '';
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $bytes; $x++) {
            $v = 0; for ($k = 0; $k < 8; $k++) { $px = imagecolorat($c, $x * 8 + $k, $y); $lum = (($px >> 16 & 255) * .3 + ($px >> 8 & 255) * .59 + ($px & 255) * .11); if ($lum < 140) $v |= 0x80 >> $k; }
            $d .= chr($v);
        }
        $this->b .= "\x1B\x61\x01" . "\x1D\x76\x30\x00" . chr($bytes & 255) . chr($bytes >> 8) . chr($h & 255) . chr($h >> 8) . $d . "\n";
        return $this;
    }
    public function text(string $s): self { $this->b .= $this->enc($s); return $this; }
    public function ln(string $s = ''): self { $this->b .= $this->enc($s) . "\n"; return $this; }
    public function center(string $s): self { return $this->align('c')->ln($s)->align('l'); }
    public function sep(string $c = '-'): self { return $this->ln(str_repeat($c, $this->cols)); }
    /** Izquierda y derecha en la misma línea; recorta la izquierda si no cabe */
    public function lr(string $l, string $r, bool $big = false): self
    {
        $w = $big ? intdiv($this->cols, 2) : $this->cols;   // en tamaño doble caben la mitad de columnas
        $r = trim($r); $max = $w - mb_strlen($r) - 1;
        if (mb_strlen($l) > $max) $l = mb_substr($l, 0, $max);
        return $this->ln($l . str_repeat(' ', $w - mb_strlen($l) - mb_strlen($r)) . $r);
    }
    /** Texto largo en varias líneas */
    public function wrap(string $s, string $pre = ''): self
    {
        foreach (explode("\n", wordwrap($s, $this->cols - mb_strlen($pre), "\n", true)) as $i => $line) $this->ln(($i ? str_repeat(' ', mb_strlen($pre)) : $pre) . $line);
        return $this;
    }
    public function feed(int $n = 1): self { $this->b .= str_repeat("\n", $n); return $this; }
    public function cut(): self { $this->b .= "\n\n\n" . "\x1D\x56\x42\x03"; return $this; }
    /** Pulso para abrir el cajón monedero conectado a la impresora (pin 2) */
    public function cajon(): self { $this->b .= "\x1B\x70\x00\x32\xFA"; return $this; }
    public function bytes(): string { return $this->b; }
}

/** Envía bytes a la impresora configurada. Lanza RuntimeException con un mensaje entendible si falla. */
function printer_send(string $bytes): void
{
    $modo = cfg('print_modo', 'navegador'); $dest = trim(cfg('print_destino', ''));
    if ($modo === 'navegador') throw new RuntimeException('La impresión directa está desactivada (modo navegador).');
    if ($dest === '') throw new RuntimeException('Falta configurar la impresora en Configuración → Impresión.');

    if ($modo === 'red') {
        [$host, $port] = array_pad(explode(':', $dest, 2), 2, '9100');
        $s = @stream_socket_client('tcp://' . $host . ':' . ((int)$port ?: 9100), $en, $es, 3);
        if (!$s) throw new RuntimeException("No se pudo conectar con la impresora en $host:" . ((int)$port ?: 9100) . ' (' . ($es ?: 'sin respuesta') . '). Revisa que esté encendida y en la misma red.');
        stream_set_timeout($s, 3);
        $ok = fwrite($s, $bytes); fclose($s);
        if ($ok === false) throw new RuntimeException('La impresora no aceptó los datos.');
        return;
    }
    // modo 'local': impresora compartida de Windows (\\PC\NOMBRE), puerto COM/LPT o dispositivo (/dev/usb/lp0)
    $h = @fopen($dest, 'wb');
    if ($h) { $ok = fwrite($h, $bytes); fclose($h); if ($ok !== false) return; }
    if (DIRECTORY_SEPARATOR === '\\' && str_starts_with($dest, '\\\\')) {   // alternativa en Windows: copy /b
        $tmp = tempnam(sys_get_temp_dir(), 'tk'); file_put_contents($tmp, $bytes);
        exec('copy /b "' . $tmp . '" "' . $dest . '" 2>&1', $out, $rc); @unlink($tmp);
        if ($rc === 0) return;
        throw new RuntimeException('Windows no pudo enviar a «' . $dest . '»: ' . trim(implode(' ', $out)) . '. Verifica que la impresora esté compartida con ese nombre.');
    }
    throw new RuntimeException('No se pudo abrir «' . $dest . '». Verifica el nombre/puerto de la impresora y sus permisos.');
}

function _n($v, ?int $d = null): string { return nf($v, $d); }

/** Ticket de venta en ESC/POS */
function escpos_ticket(int $id, bool $abrirCajon): string
{
    $v = q("SELECT v.*, COALESCE(c.nombre,'Cliente Varios') cliente, c.documento, c.tipo_doc, CONCAT(u.nombres,' ',u.apellidos) cajero FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=v.usuario_id WHERE v.id=?", [$id])->fetch();
    if (!$v) throw new RuntimeException('Venta no encontrada.');
    $items = q('SELECT d.*, p.nombre FROM detalle_ventas d JOIN productos p ON p.id=d.producto_id WHERE d.venta_id=?', [$id])->fetchAll();
    $sx = q('SELECT nombre,direccion,telefono FROM sucursales WHERE id=?', [$v['sucursal_id']])->fetch();
    $tit = ['TICKET' => 'TICKET DE VENTA', 'BOLETA' => 'BOLETA DE VENTA', 'FACTURA' => 'FACTURA'][$v['tipo_comprobante']] ?? 'COMPROBANTE';
    $p = new Escpos();
    if ($abrirCajon) $p->cajon();
    if (cfg('ticket_logo', '1') === '1') $p->logo(logo_path());
    $nom = cfg('empresa_nombre'); $p->align('c')->bold(true); if (mb_strlen($nom) <= intdiv($p->cols, 2)) $p->big(true)->ln($nom)->big(false); else $p->ln($nom); $p->bold(false);
    $p->ln('RUC: ' . cfg('empresa_ruc'))->ln($sx['direccion'] ?: cfg('empresa_direccion'))->ln('Tel: ' . ($sx['telefono'] ?: cfg('empresa_telefono')))->bold(true)->ln($sx['nombre'])->bold(false)->align('l');
    $p->sep()->align('c')->bold(true)->ln($tit)->ln($v['serie'] . '-' . str_pad((string)$v['numero'], 6, '0', STR_PAD_LEFT))->bold(false)->align('l')->sep();
    $p->ln('Fecha: ' . date('d/m/Y H:i', strtotime($v['fecha'])))->ln('Cajero: ' . $v['cajero'])->wrap('Cliente: ' . $v['cliente']);
    if ($v['documento']) $p->ln($v['tipo_doc'] . ': ' . $v['documento']);
    $p->sep()->bold(true)->lr('CANT DESCRIPCION', 'IMPORTE')->bold(false);
    foreach ($items as $i) {
        $q = rtrim(rtrim(nf($i['cantidad']), '0'), '.');
        $p->wrap($q . ' ' . $i['nombre']);
        $det = '   x ' . _n($i['precio_unitario']) . ($i['descuento'] > 0 ? ' ' . ($i['promo'] ?: 'Dscto') . ' -' . _n($i['descuento']) : '');
        $p->lr($det, _n($i['subtotal']));
    }
    $p->sep()->lr('Op. gravada', _n($v['subtotal']))->lr(impuesto() . ' (' . cfg('igv', '18') . '%)', _n($v['igv']));
    if ($v['descuento'] > 0) $p->lr('Descuentos / ahorro', '-' . _n($v['descuento']));
    $p->bold(true)->big(true)->lr('TOTAL ' . moneda(), _n($v['total']), true)->big(false)->bold(false);
    $p->ln('Pago: ' . $v['metodo_pago']);
    if ($v['monto_recibido'] !== null) $p->lr('Recibido', _n($v['monto_recibido']))->lr('Vuelto', _n($v['vuelto']));
    if ($v['puntos_ganados'] > 0) $p->sep()->center('Ganaste ' . (int)$v['puntos_ganados'] . ' puntos con esta compra');
    if ($v['estado'] === 'ANULADA') $p->sep()->center('*** COMPROBANTE ANULADO ***');
    $p->sep()->align('c')->wrap(cfg('ticket_mensaje'))->align('l')->cut();
    return $p->bytes();
}

/** Resumen de cierre de caja en ESC/POS */
function escpos_cierre(array $c): string
{
    $r = resumen_caja($c);
    $sx = q('SELECT nombre FROM sucursales WHERE id=?', [$c['sucursal_id']])->fetchColumn();
    $u = q("SELECT CONCAT(nombres,' ',apellidos) FROM usuarios WHERE id=?", [$c['usuario_id']])->fetchColumn();
    $p = new Escpos();
    $p->align('c')->bold(true)->ln(cfg('empresa_nombre'))->ln('CIERRE DE CAJA')->bold(false)->ln($sx)->align('l')->sep();
    $p->ln('Cajero: ' . $u)->ln('Apertura: ' . date('d/m/Y H:i', strtotime($c['fecha_apertura'])));
    if ($c['fecha_cierre']) $p->ln('Cierre:   ' . date('d/m/Y H:i', strtotime($c['fecha_cierre'])));
    $p->sep()->lr('Monto de apertura', _n($c['monto_apertura']));
    foreach ($r['por_metodo'] as $m => $t) $p->lr('Ventas ' . ucfirst(strtolower($m)), _n($t));
    if ($r['devoluciones'] > 0) $p->lr('(Devoluciones del turno)', '-' . _n($r['devoluciones']));
    $p->bold(true)->lr('Ventas netas', _n($r['ventas_total']))->bold(false)->lr('Ingresos de efectivo', _n($r['ingresos']))->lr('Egresos de efectivo', _n($r['egresos']))->sep();
    $p->bold(true)->lr('Efectivo esperado', _n($r['esperado']))->bold(false);
    if ($c['monto_cierre'] !== null) { $dif = (float)$c['monto_cierre'] - $r['esperado']; $p->lr('Efectivo contado', _n($c['monto_cierre']))->bold(true)->lr($dif < -0.005 ? 'FALTANTE' : ($dif > 0.005 ? 'SOBRANTE' : 'DIFERENCIA'), _n($dif))->bold(false); }
    $p->sep()->feed(3)->center('______________________')->center('Firma del cajero')->cut();
    return $p->bytes();
}
