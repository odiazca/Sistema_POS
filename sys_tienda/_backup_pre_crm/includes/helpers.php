<?php
require_once __DIR__ . '/auth.php';

const ROL_ADMIN = 1, ROL_CAJERO = 2, ROL_ALMACEN = 3;

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(json_norm($data), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** Convierte cadenas numéricas (DECIMAL de MySQL) a número, salvo campos de texto como documento o teléfono */
function json_norm($v, string $key = '')
{
    static $texto = ['documento', 'telefono', 'codigo', 'codigo_barras', 'ruc', 'comprobante', 'usuario', 'nombre', 'nombres', 'apellidos', 'razon_social',
                     'contacto', 'direccion', 'nro', 'serie', 'cliente', 'producto', 'email', 'concepto', 'motivo', 'nota', 'observacion', 'valor_txt', 'label', 'fecha', 'hora', 'dia', 'etiqueta'];
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = json_norm($x, (string)$k); return $v; }
    if (is_string($v) && is_numeric($v) && !in_array($key, $texto, true) && !preg_match('/^0\d/', $v) && strlen($v) < 16) return $v + 0;
    return $v;
}
function json_fail(string $msg, int $code = 400): void { json_out(['ok' => false, 'error' => $msg], $code); }

/** Entrada JSON (body) o $_POST */
function body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $j = $raw ? json_decode($raw, true) : null;
        $b = is_array($j) ? $j : $_POST;
    }
    return $b;
}

/** Endpoint de API: exige login, rol y (en escritura) token CSRF */
function api_guard(array $roles = [], bool $write = false): void
{
    require_login(true);
    if ($roles && !in_array(usuario()['rol_id'], $roles, true)) json_fail('No tienes permiso para esta acción.', 403);
    if ($write) {
        $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (body()['csrf'] ?? null);
        if (!csrf_check($t)) json_fail('Sesión expirada. Recarga la página.', 419);
    }
}

function cfg(string $k, string $d = ''): string
{
    static $c = null;
    if ($c === null) $c = db()->query('SELECT clave,valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $c[$k] ?? $d;
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }

/** Sucursal activa. 0 = todas (solo administrador, vistas de lectura) */
function suc(): int
{
    $u = usuario();
    if (!$u) return 0;
    if ($u['rol_id'] !== ROL_ADMIN) return (int)$u['sucursal_id'];
    return (int)($_SESSION['suc'] ?? $u['sucursal_id']);
}
/** Sucursal para operaciones de escritura (exige una sucursal concreta) */
function suc_w(): int
{
    $s = suc();
    if ($s <= 0) json_fail('Selecciona una sucursal concreta (arriba a la derecha) para realizar esta operación.');
    return $s;
}
function sucursales_lista(): array { return db()->query('SELECT id,nombre FROM sucursales WHERE estado=1 ORDER BY id')->fetchAll(); }
function suc_nombre(): string
{
    if (suc() === 0) return 'Todas las sucursales';
    return (string)q('SELECT nombre FROM sucursales WHERE id=?', [suc()])->fetchColumn();
}
/** Fragmento SQL " AND alias.sucursal_id = N" según sucursal activa (vacío si 0 = todas) */
function suc_where(string $alias = ''): string
{
    $s = suc();
    return $s > 0 ? ' AND ' . ($alias ? $alias . '.' : '') . 'sucursal_id = ' . $s : '';
}
/** Expresión SQL de stock del producto p en la sucursal activa (o total si 0) */
function stock_col(string $p = 'p'): string
{
    $s = suc();
    return $s > 0 ? "COALESCE((SELECT ss.stock FROM stock_sucursal ss WHERE ss.producto_id=$p.id AND ss.sucursal_id=$s),0)" : "$p.stock";
}
/** Stock de un producto en una sucursal */
function stock_de(int $prod, int $suc, bool $lock = false): float
{
    return (float)q('SELECT stock FROM stock_sucursal WHERE producto_id=? AND sucursal_id=?' . ($lock ? ' FOR UPDATE' : ''), [$prod, $suc])->fetchColumn();
}
/** Mueve stock (delta +/-) en una sucursal y mantiene el total consolidado de productos.stock */
function stock_mover(int $prod, int $suc, float $delta): void
{
    q('INSERT INTO stock_sucursal (producto_id,sucursal_id,stock) VALUES (?,?,?) ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)', [$prod, $suc, $delta]);
    q('UPDATE productos SET stock = (SELECT COALESCE(SUM(stock),0) FROM stock_sucursal WHERE producto_id=?) WHERE id=?', [$prod, $prod]);
}

/** Caja abierta del usuario actual (en su sucursal) */
function caja_abierta(): ?array
{
    $r = q("SELECT * FROM cajas WHERE usuario_id=? AND estado='ABIERTA' ORDER BY id DESC LIMIT 1", [usuario()['id']])->fetch();
    return $r ?: null;
}

/** Registra movimiento de kardex con el stock resultante de la sucursal (llamar tras stock_mover) */
function kardex(int $prod, string $tipo, float $cant, string $motivo, ?int $suc = null): void
{
    $suc = $suc ?: suc_w();
    q('INSERT INTO movimientos_inventario (sucursal_id,producto_id,tipo,cantidad,stock_resultante,motivo,usuario_id) VALUES (?,?,?,?,?,?,?)',
      [$suc, $prod, $tipo, $cant, stock_de($prod, $suc), $motivo, usuario()['id']]);
}

/** Bitácora de acciones de usuario */
function audit(string $accion, string $entidad = '', ?int $id = null, string $detalle = ''): void
{
    try {
        $u = usuario();
        q('INSERT INTO auditoria (usuario_id,usuario,sucursal_id,accion,entidad,entidad_id,detalle,ip) VALUES (?,?,?,?,?,?,?,?)',
          [$u['id'] ?? null, $u['usuario'] ?? '-', suc() ?: null, $accion, $entidad ?: null, $id, mb_substr($detalle, 0, 250), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}

/** Resumen de una caja (ventas por método, ingresos, egresos, esperado) */
function resumen_caja(array $c): array
{
    $id = $c['id'];
    $m = q("SELECT metodo_pago m, COALESCE(SUM(total),0) t, COUNT(*) n FROM ventas WHERE caja_id=? AND estado='COMPLETADA' GROUP BY metodo_pago", [$id])->fetchAll();
    $por = []; $efe = 0; $tot = 0; $n = 0;
    foreach ($m as $r) { $por[$r['m']] = (float)$r['t']; $tot += $r['t']; $n += $r['n']; if ($r['m'] === 'EFECTIVO') $efe = (float)$r['t']; }
    $mov = q("SELECT tipo, COALESCE(SUM(monto),0) FROM movimientos_caja WHERE caja_id=? GROUP BY tipo", [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
    // devoluciones hechas por el cajero durante este turno: restan de las ventas (por el medio de pago original)
    $dev = q("SELECT v.metodo_pago m, COALESCE(SUM(d.total),0) t FROM devoluciones d JOIN ventas v ON v.id=d.venta_id
              WHERE d.usuario_id=? AND d.sucursal_id=? AND d.fecha>=? AND (? IS NULL OR d.fecha<=?) GROUP BY v.metodo_pago",
             [$c['usuario_id'], $c['sucursal_id'], $c['fecha_apertura'], $c['fecha_cierre'] ?? null, $c['fecha_cierre'] ?? null])->fetchAll(PDO::FETCH_KEY_PAIR);
    $devTot = 0.0;
    foreach ($dev as $mm => $t) { $t = (float)$t; $devTot += $t; $por[$mm] = round(($por[$mm] ?? 0) - $t, 2); if ($por[$mm] <= 0) unset($por[$mm]); }
    $tot -= $devTot;
    $ing = (float)($mov['INGRESO'] ?? 0); $egr = (float)($mov['EGRESO'] ?? 0);
    return ['por_metodo' => $por, 'ventas_total' => $tot, 'devoluciones' => $devTot, 'ventas_n' => $n, 'efectivo' => $efe, 'ingresos' => $ing, 'egresos' => $egr,
            'esperado' => (float)$c['monto_apertura'] + $efe + $ing - $egr];
}

/** Roles con acceso a cada slug. null = todos */
function slug_roles(): array
{
    return [
        'dashboard' => null, 'pos' => [1, 2], 'ventas' => [1, 2], 'caja' => [1, 2],
        'productos' => null, 'categorias' => [1, 3], 'marcas' => [1, 3],
        'kardex' => [1, 3], 'stock-bajo' => [1, 3], 'vencimientos' => [1, 3],
        'compras-nueva' => [1, 3], 'compras' => [1, 3], 'proveedores' => [1, 3],
        'clientes' => [1, 2], 'creditos' => [1, 2], 'gastos' => [1],
        'rep-ventas' => [1], 'rep-utilidad' => [1], 'rep-top' => [1], 'rep-cajero' => [1], 'rep-inventario' => [1, 3], 'rep-sucursales' => [1],
        'usuarios' => [1], 'config' => [1], 'empresa' => [1], 'respaldos' => [1], 'asistente' => [1], 'chat' => [1],
        'traspasos' => [1, 3], 'sucursales' => [1], 'cotizaciones' => [1, 2], 'devoluciones' => [1, 2],
        'promociones' => [1], 'auditoria' => [1], 'etiquetas' => [1, 3], 'importar' => [1, 3],
    ];
}
function can(string $slug): bool
{
    $m = slug_roles();
    if (!array_key_exists($slug, $m)) return false;
    return $m[$slug] === null || in_array(usuario()['rol_id'] ?? 0, $m[$slug], true);
}
