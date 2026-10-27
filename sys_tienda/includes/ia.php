<?php
/**
 * Asistente de reportes con IA · SysTienda
 * La IA NUNCA toca las tablas reales: solo ve unas vistas de lectura (v_ia_*) y su SQL se valida, se ejecuta
 * en una transacción de solo lectura, con límite de tiempo y de filas.
 */
const IA_MAX_FILAS = 500;
const IA_PRESETS = [
    'gemini'     => ['nombre' => 'Google Gemini (gratis)', 'url' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'modelo' => 'gemini-3.5-flash-lite', 'clave' => true,  'ayuda' => 'Clave gratis en aistudio.google.com/apikey'],
    'groq'       => ['nombre' => 'Groq (gratis)',          'url' => 'https://api.groq.com/openai/v1',                            'modelo' => 'llama-3.3-70b-versatile', 'clave' => true,  'ayuda' => 'Clave gratis en console.groq.com/keys'],
    'openrouter' => ['nombre' => 'OpenRouter (modelos :free)', 'url' => 'https://openrouter.ai/api/v1',                         'modelo' => 'meta-llama/llama-3.3-70b-instruct:free', 'clave' => true, 'ayuda' => 'Clave gratis en openrouter.ai/keys'],
    'ollama'     => ['nombre' => 'Ollama (local, 100 % gratis y privado)', 'url' => 'http://localhost:11434/v1',                'modelo' => 'qwen2.5-coder:7b', 'clave' => false, 'ayuda' => 'Instala Ollama en tu PC y ejecuta: ollama pull qwen2.5-coder:7b'],
    'otro'       => ['nombre' => 'Otro (compatible con OpenAI)', 'url' => '', 'modelo' => '', 'clave' => true, 'ayuda' => 'Cualquier servicio con API /chat/completions (OpenAI, Claude vía gateway, LM Studio…)'],
];

/* ---------- Vistas de solo lectura (la «capa de inteligencia») ---------- */
function ia_vistas(): array
{
    $dv = "FROM detalle_ventas dv JOIN ventas v ON v.id=dv.venta_id JOIN productos p ON p.id=dv.producto_id LEFT JOIN categorias cat ON cat.id=p.categoria_id LEFT JOIN marcas m ON m.id=p.marca_id LEFT JOIN sucursales s ON s.id=v.sucursal_id LEFT JOIN usuarios u ON u.id=v.usuario_id";
    return [
    'v_ia_ventas' => ["Una fila por venta (comprobante). Columna estado: COMPLETADA, ANULADA o PENDIENTE; las ventas válidas son COMPLETADA. devuelto = monto devuelto de esa venta.",
      "SELECT v.id AS venta_id, v.fecha, DATE(v.fecha) AS dia, s.nombre AS sucursal, CONCAT(u.nombres,' ',u.apellidos) AS cajero, COALESCE(c.nombre,'Cliente Varios') AS cliente, v.tipo_comprobante, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) AS comprobante, v.subtotal, v.descuento, v.igv AS impuesto, v.total, v.metodo_pago, v.estado, COALESCE((SELECT SUM(d.total) FROM devoluciones d WHERE d.venta_id=v.id),0) AS devuelto FROM ventas v LEFT JOIN sucursales s ON s.id=v.sucursal_id LEFT JOIN usuarios u ON u.id=v.usuario_id LEFT JOIN clientes c ON c.id=v.cliente_id"],
    'v_ia_detalle_ventas' => ["Una fila por producto vendido en cada venta. Úsala para productos/categorías/marcas más vendidos y para utilidades. importe = ingreso de la línea; utilidad = importe - cantidad*costo_unitario. Filtra estado_venta='COMPLETADA'.",
      "SELECT dv.venta_id, v.fecha, DATE(v.fecha) AS dia, s.nombre AS sucursal, p.id AS producto_id, p.codigo, p.nombre AS producto, COALESCE(cat.nombre,'Sin categoría') AS categoria, COALESCE(m.nombre,'Sin marca') AS marca, dv.cantidad, dv.precio_unitario, dv.costo_unitario, dv.subtotal AS importe, (dv.subtotal - dv.cantidad*dv.costo_unitario) AS utilidad, v.estado AS estado_venta, v.metodo_pago, CONCAT(u.nombres,' ',u.apellidos) AS cajero $dv"],
    'v_ia_productos' => ["Catálogo de productos con su stock total actual. activo=1 son los productos activos. stock_bajo=1 si stock <= stock_minimo.",
      "SELECT p.id AS producto_id, p.codigo, p.codigo_barras, p.nombre AS producto, COALESCE(cat.nombre,'Sin categoría') AS categoria, COALESCE(m.nombre,'Sin marca') AS marca, p.unidad, p.precio_compra, p.precio_venta, (p.precio_venta-p.precio_compra) AS margen_unitario, p.stock, p.stock_minimo, (p.stock<=p.stock_minimo) AS stock_bajo, p.fecha_vencimiento, (p.stock*p.precio_compra) AS valor_costo, (p.stock*p.precio_venta) AS valor_venta, p.estado AS activo, p.creado_en FROM productos p LEFT JOIN categorias cat ON cat.id=p.categoria_id LEFT JOIN marcas m ON m.id=p.marca_id"],
    'v_ia_stock_sucursal' => ["Stock de cada producto en cada sucursal.",
      "SELECT ss.producto_id, p.nombre AS producto, COALESCE(cat.nombre,'Sin categoría') AS categoria, s.nombre AS sucursal, ss.stock, p.stock_minimo FROM stock_sucursal ss JOIN productos p ON p.id=ss.producto_id LEFT JOIN categorias cat ON cat.id=p.categoria_id JOIN sucursales s ON s.id=ss.sucursal_id"],
    'v_ia_clientes' => ["Clientes con su historial de compras (solo ventas COMPLETADAS). saldo_deuda = crédito pendiente de pago. El cliente genérico se llama 'Cliente Varios'.",
      "SELECT c.id AS cliente_id, c.nombre, c.tipo_doc, c.documento, c.telefono, c.puntos, c.limite_credito, c.saldo_deuda, c.estado AS activo, c.creado_en, (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='COMPLETADA') AS num_compras, (SELECT COALESCE(SUM(v.total),0) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='COMPLETADA') AS total_comprado, (SELECT MAX(v.fecha) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='COMPLETADA') AS ultima_compra FROM clientes c"],
    'v_ia_compras' => ["Compras a proveedores (cabecera). estado: RECIBIDA, PENDIENTE o ANULADA.",
      "SELECT co.id AS compra_id, co.fecha, DATE(co.fecha) AS dia, s.nombre AS sucursal, pr.razon_social AS proveedor, co.comprobante, co.total, co.estado FROM compras co LEFT JOIN sucursales s ON s.id=co.sucursal_id LEFT JOIN proveedores pr ON pr.id=co.proveedor_id"],
    'v_ia_detalle_compras' => ["Productos comprados a proveedores (detalle).",
      "SELECT dc.compra_id, co.fecha, DATE(co.fecha) AS dia, pr.razon_social AS proveedor, p.nombre AS producto, dc.cantidad, dc.costo_unitario, dc.subtotal AS importe, co.estado FROM detalle_compras dc JOIN compras co ON co.id=dc.compra_id JOIN productos p ON p.id=dc.producto_id LEFT JOIN proveedores pr ON pr.id=co.proveedor_id"],
    'v_ia_gastos' => ["Gastos operativos. activo=1 son los gastos vigentes (activo=0 están desactivados y no cuentan).",
      "SELECT g.id AS gasto_id, g.fecha, s.nombre AS sucursal, g.concepto, g.categoria, g.monto, g.estado AS activo FROM gastos g LEFT JOIN sucursales s ON s.id=g.sucursal_id"],
    'v_ia_devoluciones' => ["Devoluciones de ventas.",
      "SELECT d.id AS devolucion_id, d.fecha, DATE(d.fecha) AS dia, s.nombre AS sucursal, d.venta_id, d.total, d.motivo, d.reembolso FROM devoluciones d LEFT JOIN sucursales s ON s.id=d.sucursal_id"],
    'v_ia_cajas' => ["Aperturas y cierres de caja por cajero. estado: ABIERTA o CERRADA.",
      "SELECT ca.id AS caja_id, ca.fecha_apertura, ca.fecha_cierre, s.nombre AS sucursal, CONCAT(u.nombres,' ',u.apellidos) AS cajero, ca.monto_apertura, ca.monto_cierre, ca.estado FROM cajas ca LEFT JOIN sucursales s ON s.id=ca.sucursal_id LEFT JOIN usuarios u ON u.id=ca.usuario_id"],
    'v_ia_movimientos_inventario' => ["Kardex: entradas, salidas y ajustes de inventario (tipo ENTRADA, SALIDA o AJUSTE).",
      "SELECT mi.fecha, s.nombre AS sucursal, p.nombre AS producto, mi.tipo, mi.cantidad, mi.stock_resultante, mi.motivo FROM movimientos_inventario mi JOIN productos p ON p.id=mi.producto_id LEFT JOIN sucursales s ON s.id=mi.sucursal_id"],
    ];
}

function ia_instalar(bool $force = false): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS ia_consultas (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, pregunta VARCHAR(500) NOT NULL, titulo VARCHAR(160) NULL, sql_texto TEXT NOT NULL, grafico VARCHAR(255) NULL, favorito TINYINT(1) NOT NULL DEFAULT 0, veces INT NOT NULL DEFAULT 1, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP, ultimo_uso DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX (usuario_id, favorito)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ok = !$force;
    if ($ok) { try { foreach (array_keys(ia_vistas()) as $v) $pdo->query("SELECT 1 FROM `$v` LIMIT 1")->fetchAll(); } catch (Throwable $e) { $ok = false; } }
    if ($ok) return;
    foreach (ia_vistas() as $v => [, $sql]) $pdo->exec("CREATE OR REPLACE VIEW `$v` AS $sql");
}

function ia_esquema_texto(): string
{
    $t = '';
    foreach (ia_vistas() as $v => [$desc]) {
        $cols = [];
        foreach (db()->query("SHOW COLUMNS FROM `$v`")->fetchAll() as $c) $cols[] = $c['Field'] . ' ' . strtolower(preg_replace('/\(.*/', '', $c['Type']));
        $t .= "$v: $desc\n  columnas: " . implode(', ', $cols) . "\n";
    }
    return $t;
}

/* ---------- Configuración y clave cifrada ---------- */
function ia_secret(): string
{
    $f = dirname(__DIR__) . '/config/secret.key';
    if (!is_file($f)) @file_put_contents($f, base64_encode(random_bytes(32)));
    return base64_decode((string)@file_get_contents($f)) ?: str_repeat('x', 32);
}
function ia_cifrar(string $s): string
{
    if ($s === '') return '';
    if (!function_exists('openssl_encrypt')) return 'p:' . base64_encode($s);
    $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($s, 'aes-256-gcm', ia_secret(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'g:' . base64_encode($iv . $tag . $c);
}
function ia_descifrar(string $s): string
{
    if ($s === '') return '';
    if (str_starts_with($s, 'p:')) return (string)base64_decode(substr($s, 2));
    if (!str_starts_with($s, 'g:') || !function_exists('openssl_decrypt')) return '';
    $b = base64_decode(substr($s, 2)); if (strlen($b) < 29) return '';
    return (string)openssl_decrypt(substr($b, 28), 'aes-256-gcm', ia_secret(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
}
function ia_cfg(): array
{
    $p = cfg_fresh('ia_proveedor', 'gemini'); if (!isset(IA_PRESETS[$p])) $p = 'gemini';
    return ['proveedor' => $p, 'url' => cfg_fresh('ia_url', IA_PRESETS[$p]['url']), 'modelo' => cfg_fresh('ia_modelo', IA_PRESETS[$p]['modelo']),
            'clave' => ia_descifrar(cfg_fresh('ia_clave', '')), 'resumen' => cfg_fresh('ia_resumen', '1') === '1'];
}

/* ---------- Llamada al modelo (API compatible con OpenAI) ---------- */
function ia_llamar(array $mensajes, int $maxTokens = 900): string
{
    $c = ia_cfg();
    if ($c['url'] === '' || $c['modelo'] === '') throw new RuntimeException('Configura el proveedor de IA (URL y modelo).');
    if (IA_PRESETS[$c['proveedor']]['clave'] && $c['clave'] === '') throw new RuntimeException('Falta la clave (API key) del proveedor de IA. Configúrala con el botón «Configurar IA».');
    $url = rtrim($c['url'], '/') . '/chat/completions';
    if (!preg_match('#^https?://#i', $url)) throw new RuntimeException('La URL del proveedor debe empezar por http:// o https://');
    $payload = json_encode(['model' => $c['modelo'], 'messages' => $mensajes, 'temperature' => 0, 'max_tokens' => $maxTokens], JSON_UNESCAPED_UNICODE);
    $h = ['Content-Type: application/json', 'Accept: application/json']; if ($c['clave'] !== '') $h[] = 'Authorization: Bearer ' . $c['clave'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $h), 'content' => $payload, 'timeout' => 60, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx); $err = $res === false ? 'sin respuesta' : ''; $code = 0;
        foreach ($http_response_header ?? [] as $l) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) $code = (int)$m[1];
    }
    if ($res === false || $res === null) throw new RuntimeException('No se pudo conectar con el proveedor de IA (' . $err . '). Revisa tu internet y la URL.');
    $j = json_decode((string)$res, true);
    if ($code === 401 || $code === 403) throw new RuntimeException('El proveedor rechazó la clave (API key). Revisa que sea correcta.');
    if ($code === 429) throw new RuntimeException('Se alcanzó el límite del plan gratuito. Espera un minuto e inténtalo de nuevo.');
    if ($code === 404) throw new RuntimeException('Modelo o URL no encontrados (' . $c['modelo'] . '). Google renueva sus modelos: prueba con gemini-3.5-flash-lite o gemini-3.5-flash.');
    if ($code >= 400 || !is_array($j)) { $m = is_array($j) ? ($j['error']['message'] ?? ($j[0]['error']['message'] ?? '')) : ''; throw new RuntimeException('Error del proveedor de IA (' . $code . ')' . ($m ? ': ' . mb_substr($m, 0, 200) : '.')); }
    $txt = $j['choices'][0]['message']['content'] ?? '';
    if (is_array($txt)) $txt = implode('', array_map(fn($p) => $p['text'] ?? '', $txt));
    if (trim((string)$txt) === '') throw new RuntimeException('La IA no devolvió respuesta. Intenta reformular la pregunta.');
    return (string)$txt;
}

function ia_extraer_json(string $t): ?array
{
    $t = trim($t);
    if (preg_match('/```(?:json)?\s*(.*?)```/is', $t, $m)) $t = trim($m[1]);
    $j = json_decode($t, true); if (is_array($j)) return $j;
    $a = strpos($t, '{'); $b = strrpos($t, '}');
    if ($a !== false && $b > $a) { $j = json_decode(substr($t, $a, $b - $a + 1), true); if (is_array($j)) return $j; }
    return null;
}

/* ---------- Validación del SQL (defensa en profundidad) ---------- */
function ia_validar_sql(string $sql): string
{
    $sql = trim($sql); $sql = rtrim($sql, "; \t\r\n");
    if ($sql === '' || strlen($sql) > 4000) throw new InvalidArgumentException('La consulta está vacía o es demasiado larga.');
    // Quita literales de texto para analizar solo la estructura
    $s = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", ' 0 ', $sql);
    $s = preg_replace('/"(?:[^"\\\\]|\\\\.|"")*"/s', ' 0 ', $s);
    if (strpbrk($s, "'\"") !== false) throw new InvalidArgumentException('Comillas sin cerrar en la consulta.');
    if (strpos($s, ';') !== false) throw new InvalidArgumentException('Solo se permite una consulta.');
    if (preg_match('/--|\/\*|\*\/|#|@/', $s)) throw new InvalidArgumentException('No se permiten comentarios ni variables.');
    $l = strtolower(str_replace('`', ' ', $s));
    if (!preg_match('/^\s*(select|with)\b/', $l)) throw new InvalidArgumentException('Solo se permiten consultas SELECT.');
    $prohib = 'insert|update|delete|drop|alter|create|truncate|grant|revoke|call|execute|prepare|handler|load|outfile|dumpfile|into|lock|unlock|set|rename|use|show|describe|explain|optimize|analyze|sleep|benchmark|load_file|get_lock|release_lock|information_schema|performance_schema|mysql|sys|user|current_user|session_user|system_user|database|schema|version|connection_id|row_count|found_rows|last_insert_id|uuid|procedure|kill|shutdown|flush|reset|purge|install|uninstall|import|binlog|xa|savepoint|commit|rollback|begin|start';
    if (preg_match('/\b(' . $prohib . ')\b/', $l, $m)) throw new InvalidArgumentException('Palabra no permitida en la consulta: ' . strtoupper($m[1]));
    if (preg_match('/for\s+update|lock\s+in\s+share/', $l)) throw new InvalidArgumentException('Bloqueos no permitidos.');
    // Ninguna tabla real: solo vistas v_ia_*
    static $reales = null;
    if ($reales === null) { $reales = []; foreach (db()->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $r) if (strpos($r[0], 'v_ia_') !== 0) $reales[strtolower($r[0])] = true; $reales[strtolower(DB_NAME)] = true; }
    if (preg_match_all('/(?<![\w.])(?:(as)\s+)?([a-z_][a-z0-9_]*)\b/', $l, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $t) if (isset($reales[$t[2]]) && $t[1] === '') throw new InvalidArgumentException('Solo puedes consultar las vistas v_ia_* (no la tabla «' . $t[2] . '»). Si es un alias, usa otro nombre.');
    }
    if (preg_match('/\b[a-z_][a-z0-9_]*\s*\.\s*[a-z_][a-z0-9_]*\s*\./', $l)) throw new InvalidArgumentException('No se permiten nombres con base de datos.');
    return $sql;
}

/** Ejecuta en solo lectura con límite de tiempo y de filas. Devuelve [columnas, filas, truncado, ms] */
function ia_ejecutar(string $sql): array
{
    $sql = ia_validar_sql($sql); $pdo = db(); $t0 = microtime(true);
    foreach (["SET SESSION MAX_EXECUTION_TIME=8000", "SET SESSION max_statement_time=8"] as $x) { try { $pdo->exec($x); } catch (Throwable $e) {} }
    $buf = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    try {
        $pdo->exec('START TRANSACTION READ ONLY');
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $st = $pdo->query($sql);
        $n = $st->columnCount(); $cols = $tipos = [];
        for ($i = 0; $i < $n; $i++) { $m = $st->getColumnMeta($i); $cols[] = $m['name'] ?? ('col' . $i); $tipos[] = strtoupper((string)($m['native_type'] ?? '')); }
        $filas = []; $trunc = false;
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            if (count($filas) >= IA_MAX_FILAS) { $trunc = true; break; }
            foreach ($r as $i => $v) if ($v !== null && is_string($v) && in_array($tipos[$i], ['NEWDECIMAL', 'DECIMAL', 'LONG', 'LONGLONG', 'SHORT', 'TINY', 'INT24', 'FLOAT', 'DOUBLE'], true) && is_numeric($v)) $r[$i] = $v + 0;
            $filas[] = $r;
        }
        $st->closeCursor();
    } catch (PDOException $e) {
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buf); try { $pdo->exec('ROLLBACK'); } catch (Throwable $x) {}
        throw new RuntimeException('Error SQL: ' . preg_replace('/^SQLSTATE\[[^\]]*\]:?\s*/', '', $e->getMessage()));
    }
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buf); try { $pdo->exec('ROLLBACK'); } catch (Throwable $x) {}
    return ['columnas' => $cols, 'filas' => $filas, 'truncado' => $trunc, 'ms' => (int)round((microtime(true) - $t0) * 1000), 'sql' => $sql];
}


/** Reglas de negocio compartidas por el Asistente de reportes y el Chat */
function ia_reglas(): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    return "REGLAS:\n- Genera un único SELECT (o WITH ... SELECT) usando SOLO las vistas v_ia_*. Sin punto y coma, sin comentarios, sin variables.\n"
    . "- Hoy es " . $dias[(int)date('w')] . " " . date('Y-m-d') . " (usa CURDATE()/NOW() para fechas relativas). La semana empieza el lunes. «Este mes» = desde el día 1 del mes actual hasta hoy; «mes pasado» = mes calendario anterior.\n"
    . "- Ventas válidas: estado='COMPLETADA' (en v_ia_detalle_ventas: estado_venta='COMPLETADA'). Ventas netas = total - devuelto.\n"
    . "- «Más vendidos» se mide por SUM(cantidad) salvo que pidan por monto. Productos activos: activo=1.\n"
    . "- Para buscar por nombre usa LIKE '%texto%' en minúsculas sobre LOWER(columna), sin tildes en el texto buscado si dudas.\n"
    . "- Moneda: " . cfg('moneda', 'S/') . ". Redondea importes con ROUND(x,2). Pon alias de columnas legibles en español (sin espacios: usa guion bajo), por ejemplo producto, unidades_vendidas, total_ventas.\n"
    . "- Si piden un ranking sin cantidad, usa LIMIT 10; en cualquier caso nunca más de " . IA_MAX_FILAS . " filas. Ordena de forma útil.\n"
    . "- Si la pregunta no se puede responder con estas vistas, no inventes.";
}

/* ---------- Pregunta en lenguaje natural → SQL ---------- */
function ia_prompt_sistema(): string
{
    return "Eres el analista de datos de una tienda/bodega con punto de venta. Conviertes preguntas en español en UNA consulta SQL (MySQL/MariaDB) de solo lectura.\n\n"
    . "VISTAS DISPONIBLES (no existe nada más):\n" . ia_esquema_texto() . "\n"
    . ia_reglas() . " Si no se puede responder, devuelve sql null y explica en «mensaje».\n\n"
    . "Responde SOLO con un objeto JSON, sin texto adicional ni markdown:\n"
    . '{"titulo":"título corto del reporte","sql":"SELECT ...","grafico":{"tipo":"bar|line|pie|none","x":"alias_columna_etiquetas","y":"alias_columna_numerica"},"mensaje":null}' . "\n"
    . "grafico.tipo: line para series por fecha, bar para rankings/comparaciones, pie para participación (máx. 8 categorías), none si no tiene sentido graficar.";
}

function ia_preguntar(string $pregunta, ?array $previo = null): array
{
    $m = [['role' => 'system', 'content' => ia_prompt_sistema()]];
    if ($previo && !empty($previo['pregunta']) && !empty($previo['sql'])) {
        $m[] = ['role' => 'user', 'content' => mb_substr((string)$previo['pregunta'], 0, 300)];
        $m[] = ['role' => 'assistant', 'content' => json_encode(['titulo' => '', 'sql' => mb_substr((string)$previo['sql'], 0, 1500), 'grafico' => ['tipo' => 'none'], 'mensaje' => null], JSON_UNESCAPED_UNICODE)];
    }
    $m[] = ['role' => 'user', 'content' => $pregunta];
    $ultimoError = '';
    for ($intento = 0; $intento < 2; $intento++) {
        $raw = ia_llamar($m);
        $j = ia_extraer_json($raw);
        if (!$j) { $m[] = ['role' => 'assistant', 'content' => mb_substr($raw, 0, 800)]; $m[] = ['role' => 'user', 'content' => 'Tu respuesta no era JSON válido. Responde SOLO el objeto JSON indicado.']; $ultimoError = 'La IA respondió en un formato inesperado.'; continue; }
        if (empty($j['sql'])) return ['mensaje' => (string)($j['mensaje'] ?: 'No puedo responder esa pregunta con los datos disponibles. Prueba con ventas, productos, clientes, compras, gastos o inventario.')];
        try {
            $r = ia_ejecutar((string)$j['sql']);
            $g = is_array($j['grafico'] ?? null) ? $j['grafico'] : [];
            return $r + ['titulo' => mb_substr(trim((string)($j['titulo'] ?? '')) ?: $pregunta, 0, 160), 'grafico' => ['tipo' => in_array($g['tipo'] ?? '', ['bar', 'line', 'pie', 'none'], true) ? $g['tipo'] : 'none', 'x' => (string)($g['x'] ?? ''), 'y' => (string)($g['y'] ?? '')]];
        } catch (Throwable $e) {
            $ultimoError = $e->getMessage();
            $m[] = ['role' => 'assistant', 'content' => $raw];
            $m[] = ['role' => 'user', 'content' => 'Esa consulta falló: ' . $ultimoError . '. Corrígela usando solo las vistas y columnas listadas y responde de nuevo SOLO el JSON.'];
        }
    }
    throw new RuntimeException('No pude generar una consulta válida para esa pregunta. Reformúlala con más detalle. (' . $ultimoError . ')');
}

function ia_resumir(string $pregunta, array $cols, array $filas): string
{
    $muestra = array_slice($filas, 0, 25);
    $txt = ia_llamar([
        ['role' => 'system', 'content' => 'Eres un analista de una tienda. Responde en español, en 1 o 2 frases claras, mencionando las cifras más relevantes. No uses listas, ni markdown, ni inventes datos que no estén en la tabla. Moneda: ' . cfg('moneda', 'S/') . '.'],
        ['role' => 'user', 'content' => "Pregunta: $pregunta\nColumnas: " . implode(', ', $cols) . "\nFilas (" . count($filas) . " en total, se muestran " . count($muestra) . "):\n" . json_encode($muestra, JSON_UNESCAPED_UNICODE)],
    ], 250);
    return trim(strip_tags($txt));
}


/* ---------- Chat conversacional ---------- */
const IA_GUIA = "Punto de Venta (F2): cobrar ventas; antes hay que abrir caja en Caja. Ventas: historial, imprimir y anular. Cotizaciones y Devoluciones. Caja: abrir/cerrar caja, ingresos y egresos. Productos (listado, categorías, marcas, importar/exportar Excel, etiquetas de código de barras). Almacén: Kardex y ajustes de stock, Traspasos entre sucursales, Stock bajo, Por vencer. Compras: Registrar compra, Historial, Proveedores. Clientes: directorio y Créditos y cobranzas. Promociones. Gastos. Reportes: ventas, utilidades, más vendidos, por cajero, inventario. Administración: Sucursales, Usuarios, Empresa (datos, logo, moneda), Copias de seguridad (crear, restaurar, restablecer), Configuración (comprobantes, impresión, seguridad), Auditoría, Asistente IA y Chat IA.";

function ia_prompt_chat(): string
{
    return "Eres el chatbot de «" . cfg('empresa_nombre', 'la tienda') . "», un sistema de punto de venta. Conversas en español con el administrador, con tono cercano y breve.\n"
    . "Puedes: (1) consultar datos del negocio (productos, stock, ventas, clientes, proveedores, compras, gastos, cajas, devoluciones); (2) explicar cómo usar el sistema; (3) saludar o aclarar. NO puedes registrar, modificar ni borrar nada: si te lo piden, explica en qué módulo se hace.\n\n"
    . "VISTAS DISPONIBLES (no existe nada más):\n" . ia_esquema_texto() . "\n" . ia_reglas() . "\n"
    . "- Para datos de un producto, cliente o proveedor concretos devuelve pocas columnas útiles y LIMIT 20.\n"
    . "- Si el mensaje es una continuación («y en la sucursal Norte?», «y el mes pasado?»), reutiliza y adapta la consulta anterior.\n\n"
    . "GUÍA DEL SISTEMA: " . IA_GUIA . "\n\n"
    . "Responde SOLO con un objeto JSON, sin texto adicional ni markdown, en una de dos formas:\n"
    . '{"accion":"consulta","sql":"SELECT ..."}  → cuando necesites datos.' . "\n"
    . '{"accion":"respuesta","texto":"..."}  → cuando no necesites datos (saludo, ayuda de uso, aclaración, o si algo no es posible).';
}

/** Un turno de chat. $hist = [['q'=>..., 'sql'=>?, 'a'=>...], ...]. Devuelve ['texto'=>..., 'tabla'=>?, 'sql'=>?] */
function ia_chat(string $msg, array $hist): array
{
    $m = [['role' => 'system', 'content' => ia_prompt_chat()]];
    foreach (array_slice($hist, -6) as $h) {
        $m[] = ['role' => 'user', 'content' => (string)$h['q']];
        $m[] = ['role' => 'assistant', 'content' => json_encode(!empty($h['sql']) ? ['accion' => 'consulta', 'sql' => $h['sql']] : ['accion' => 'respuesta', 'texto' => mb_substr((string)$h['a'], 0, 300)], JSON_UNESCAPED_UNICODE)];
    }
    $m[] = ['role' => 'user', 'content' => $msg];
    $err = '';
    for ($i = 0; $i < 2; $i++) {
        $raw = ia_llamar($m, 700); $j = ia_extraer_json($raw);
        if (!$j) { $t = trim(strip_tags($raw)); if ($t !== '' && $t[0] !== '{') return ['texto' => $t]; $m[] = ['role' => 'assistant', 'content' => mb_substr($raw, 0, 500)]; $m[] = ['role' => 'user', 'content' => 'Responde SOLO con el objeto JSON indicado.']; continue; }
        if (($j['accion'] ?? '') !== 'consulta' || empty($j['sql'])) return ['texto' => trim((string)($j['texto'] ?? $j['respuesta'] ?? '')) ?: 'No entendí bien. ¿Puedes darme más detalle?'];
        try { $r = ia_ejecutar((string)$j['sql']); }
        catch (Throwable $e) { $err = $e->getMessage(); $m[] = ['role' => 'assistant', 'content' => $raw]; $m[] = ['role' => 'user', 'content' => 'Esa consulta falló: ' . $err . '. Corrígela usando solo las vistas y columnas listadas y responde SOLO el JSON.']; continue; }
        $muestra = array_slice($r['filas'], 0, 25);
        $texto = ia_llamar([
            ['role' => 'system', 'content' => 'Eres el chatbot de una tienda. Responde en español con tono cercano y breve (máximo 4 frases o una lista corta con guiones). Usa las cifras EXACTAS de los datos, sin inventar nada. Importes con el símbolo ' . cfg('moneda', 'S/') . ' (ejemplo de formato: ' . money(1234.5) . '). Si no hay filas, dilo claramente. No menciones SQL, vistas ni columnas técnicas.'],
            ['role' => 'user', 'content' => "Pregunta: $msg\nColumnas: " . implode(', ', $r['columnas']) . "\nFilas (" . count($r['filas']) . " en total" . ($r['truncado'] ? ', hay más' : '') . ", se muestran " . count($muestra) . "):\n" . json_encode($muestra, JSON_UNESCAPED_UNICODE)],
        ], 450);
        return ['texto' => trim(strip_tags($texto)), 'sql' => $r['sql'], 'tabla' => ['columnas' => $r['columnas'], 'filas' => array_slice($r['filas'], 0, 10), 'total' => count($r['filas'])]];
    }
    throw new RuntimeException('No pude consultar eso con los datos disponibles. Prueba reformulando la pregunta. (' . $err . ')');
}
