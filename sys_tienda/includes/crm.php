<?php
/**
 * CRM · SysTienda
 * Esquema (auto-migración), segmentación RFM, cumpleaños y plantillas de mensajes.
 * Se carga bajo demanda desde api/crm.php, los módulos CRM y verificar_esquema().
 */

const CRM_ORIGENES = ['Tienda', 'Referido', 'WhatsApp', 'Redes sociales', 'Volante / publicidad', 'Otro'];

/** Crea tablas/columnas del CRM si no existen y siembra los datos base. Idempotente. */
function crm_migrar(PDO $pdo): void
{
    // Columnas nuevas en clientes
    $have = $pdo->query('SHOW COLUMNS FROM clientes')->fetchAll(PDO::FETCH_COLUMN);
    $add = [
        'origen'           => "ALTER TABLE clientes ADD COLUMN origen VARCHAR(30) NULL",
        'acepta_marketing' => "ALTER TABLE clientes ADD COLUMN acepta_marketing TINYINT(1) NOT NULL DEFAULT 1",
        'responsable_id'   => "ALTER TABLE clientes ADD COLUMN responsable_id INT UNSIGNED NULL",
        'ultimo_contacto'  => "ALTER TABLE clientes ADD COLUMN ultimo_contacto DATETIME NULL",
    ];
    foreach ($add as $col => $sql) if (!in_array($col, $have, true)) $pdo->exec($sql);

    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_etiquetas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(40) NOT NULL UNIQUE, color VARCHAR(9) NOT NULL DEFAULT '#5b5ef4'
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_cliente_etiqueta (
        cliente_id INT UNSIGNED NOT NULL, etiqueta_id INT UNSIGNED NOT NULL, PRIMARY KEY (cliente_id, etiqueta_id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (etiqueta_id) REFERENCES crm_etiquetas(id) ON DELETE CASCADE
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_notas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cliente_id INT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NULL,
        tipo ENUM('NOTA','LLAMADA','VISITA','WHATSAPP','EMAIL','REUNION','SISTEMA') NOT NULL DEFAULT 'NOTA',
        texto VARCHAR(1000) NOT NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (cliente_id, creado_en), FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_etapas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(40) NOT NULL, orden TINYINT NOT NULL DEFAULT 0,
        probabilidad TINYINT NOT NULL DEFAULT 0, color VARCHAR(9) NOT NULL DEFAULT '#5b5ef4',
        tipo ENUM('ABIERTA','GANADA','PERDIDA') NOT NULL DEFAULT 'ABIERTA'
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_oportunidades (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cliente_id INT UNSIGNED NOT NULL, titulo VARCHAR(160) NOT NULL,
        valor DECIMAL(12,2) NOT NULL DEFAULT 0, etapa_id INT UNSIGNED NOT NULL, responsable_id INT UNSIGNED NULL,
        origen VARCHAR(30) NULL, cierre_estimado DATE NULL, estado ENUM('ABIERTA','GANADA','PERDIDA') NOT NULL DEFAULT 'ABIERTA',
        motivo_perdida VARCHAR(160) NULL, orden INT NOT NULL DEFAULT 0, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, cerrado_en DATETIME NULL,
        INDEX (etapa_id, orden), INDEX (estado),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (etapa_id) REFERENCES crm_etapas(id)
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_tareas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cliente_id INT UNSIGNED NULL, oportunidad_id INT UNSIGNED NULL,
        asignado_id INT UNSIGNED NULL, creado_por INT UNSIGNED NULL, titulo VARCHAR(160) NOT NULL,
        tipo ENUM('LLAMAR','WHATSAPP','VISITA','EMAIL','COBRAR','SEGUIMIENTO','OTRO') NOT NULL DEFAULT 'SEGUIMIENTO',
        prioridad ENUM('BAJA','MEDIA','ALTA') NOT NULL DEFAULT 'MEDIA', vence DATETIME NULL,
        estado ENUM('PENDIENTE','HECHA') NOT NULL DEFAULT 'PENDIENTE', completada_en DATETIME NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (estado, vence), INDEX (asignado_id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades(id) ON DELETE SET NULL
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_plantillas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(60) NOT NULL, categoria VARCHAR(20) NOT NULL DEFAULT 'general',
        texto VARCHAR(1000) NOT NULL, estado TINYINT(1) NOT NULL DEFAULT 1
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_campanas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL, segmento VARCHAR(60) NOT NULL, segmento_nombre VARCHAR(80) NOT NULL,
        mensaje VARCHAR(1000) NOT NULL, estado ENUM('ACTIVA','CERRADA') NOT NULL DEFAULT 'ACTIVA', total INT NOT NULL DEFAULT 0,
        creado_por INT UNSIGNED NULL, creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $t");
    $pdo->exec("CREATE TABLE IF NOT EXISTS crm_campana_destinatarios (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, campana_id INT UNSIGNED NOT NULL, cliente_id INT UNSIGNED NOT NULL,
        estado ENUM('PENDIENTE','ENVIADO','OMITIDO') NOT NULL DEFAULT 'PENDIENTE', enviado_en DATETIME NULL,
        UNIQUE (campana_id, cliente_id),
        FOREIGN KEY (campana_id) REFERENCES crm_campanas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) $t");

    // Datos base
    if (!(int)$pdo->query('SELECT COUNT(*) FROM crm_etapas')->fetchColumn()) {
        $i = $pdo->prepare('INSERT INTO crm_etapas (nombre,orden,probabilidad,color,tipo) VALUES (?,?,?,?,?)');
        foreach ([['Prospecto', 1, 10, '#94a3b8', 'ABIERTA'], ['Contactado', 2, 25, '#0ea5e9', 'ABIERTA'], ['Propuesta', 3, 50, '#8b5cf6', 'ABIERTA'],
                  ['Negociación', 4, 75, '#f59e0b', 'ABIERTA'], ['Ganado', 5, 100, '#10b981', 'GANADA'], ['Perdido', 6, 0, '#f43f5e', 'PERDIDA']] as $r) $i->execute($r);
    }
    if (!(int)$pdo->query('SELECT COUNT(*) FROM crm_etiquetas')->fetchColumn()) {
        $i = $pdo->prepare('INSERT INTO crm_etiquetas (nombre,color) VALUES (?,?)');
        foreach ([['VIP', '#f59e0b'], ['Mayorista', '#5b5ef4'], ['Frecuente', '#10b981'], ['Moroso', '#f43f5e'], ['Negocio / Restaurante', '#14b8a6'], ['Referido', '#8b5cf6']] as $r) $i->execute($r);
    }
    if (!(int)$pdo->query('SELECT COUNT(*) FROM crm_plantillas')->fetchColumn()) {
        $i = $pdo->prepare('INSERT INTO crm_plantillas (nombre,categoria,texto) VALUES (?,?,?)');
        foreach ([
            ['Promoción de la semana', 'promo', '¡Hola {nombre}! 👋 En {empresa} tenemos ofertas especiales esta semana. Pasa por la tienda y aprovecha. ¡Te esperamos!'],
            ['Feliz cumpleaños', 'cumple', '¡Feliz cumpleaños, {nombre}! 🎂 En {empresa} queremos celebrarlo contigo: tienes {descuento}% de descuento en tu compra durante tu día. ¡Te esperamos!'],
            ['Te extrañamos (reactivación)', 'reactivacion', 'Hola {nombre}, hace {dias} días que no te vemos por {empresa} y te extrañamos. Tienes {puntos} puntos esperándote. ¡Vuelve pronto!'],
            ['Recordatorio de pago', 'cobranza', 'Hola {nombre}, te recordamos amablemente que tienes un saldo pendiente de {deuda} en {empresa}. Puedes pagar en tienda o por Yape/Plin. ¡Gracias!'],
            ['Gracias por tu compra', 'general', 'Gracias por tu preferencia, {nombre}. En {empresa} valoramos tu confianza. Tienes {puntos} puntos acumulados para canjear.'],
        ] as $r) $i->execute($r);
    }
}

/** Definición de segmentos RFM: clave => [nombre, color, icono, descripción, acción sugerida] */
function crm_segmentos_def(): array
{
    return [
        'campeones'    => ['Campeones',  '#10b981', 'bi-trophy-fill',          'Compran seguido, recientemente y gastan más.', 'Premia su lealtad y pídeles referidos.'],
        'leales'       => ['Leales',     '#5b5ef4', 'bi-heart-fill',           'Compran con regularidad y buen monto.', 'Ofrece fidelización, puntos y ofertas por volumen.'],
        'prometedores' => ['Prometedores', '#0ea5e9', 'bi-graph-up-arrow',     'Compraron hace poco pero aún gastan poco.', 'Sube su ticket con combos y venta cruzada.'],
        'nuevos'       => ['Nuevos',     '#14b8a6', 'bi-stars',                'Primera compra en los últimos 30 días.', 'Agradece y motiva una segunda compra rápida.'],
        'ocasionales'  => ['Ocasionales', '#8b5cf6', 'bi-cart',                'Compran de vez en cuando, ticket bajo.', 'Invítalos con promociones de temporada.'],
        'en_riesgo'    => ['En riesgo',  '#f59e0b', 'bi-exclamation-triangle-fill', 'Eran buenos clientes y llevan tiempo sin volver.', 'Reactívalos con un mensaje y una oferta personal.'],
        'no_perder'    => ['No perder',  '#f43f5e', 'bi-fire',                 'Clientes de alto valor que se están yendo.', 'Contacto personal inmediato (llamada o visita).'],
        'dormidos'     => ['Dormidos',   '#64748b', 'bi-moon-stars-fill',      'Mucho tiempo sin comprar y poco valor.', 'Campaña de reactivación de bajo costo.'],
        'prospectos'   => ['Prospectos', '#94a3b8', 'bi-person-plus',          'Registrados que aún no han comprado.', 'Invítalos a su primera compra con un incentivo.'],
    ];
}

/** Umbrales de recencia (días) para puntajes 5..2; configurable en configuracion.crm_rec_dias */
function crm_umbrales(): array
{
    $v = array_map('intval', array_filter(explode(',', cfg('crm_rec_dias', '7,15,30,60')), 'strlen'));
    return count($v) === 4 ? $v : [7, 15, 30, 60];
}

/** Ordena por monto y asigna puntaje 1-5 por percentil (empates = rango medio) */
function crm_quintiles(array $vals): array
{
    $n = count($vals); if (!$n) return [];
    asort($vals); $out = []; $keys = array_keys($vals); $i = 0;
    while ($i < $n) {
        $j = $i; while ($j + 1 < $n && $vals[$keys[$j + 1]] == $vals[$keys[$i]]) $j++;
        $pos = ($i + $j) / 2; $s = 1 + min(4, (int)floor(5 * ($pos + 0.5) / $n));
        for ($k = $i; $k <= $j; $k++) $out[$keys[$k]] = $s;
        $i = $j + 1;
    }
    return $out;
}

/**
 * Base de clientes con métricas y segmento RFM. Devuelve [id => fila].
 * Cada fila: datos del cliente + n (compras), monto (neto de devoluciones), ultima, primera, dias, ticket, r, f, m, score, seg
 */
function crm_rfm(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $rows = q("SELECT c.*, COALESCE(v.n,0) n, COALESCE(v.monto,0) - COALESCE(d.dev,0) monto, v.ultima, v.primera
        FROM clientes c
        LEFT JOIN (SELECT cliente_id, COUNT(*) n, SUM(total) monto, MAX(fecha) ultima, MIN(fecha) primera FROM ventas WHERE estado='COMPLETADA' GROUP BY cliente_id) v ON v.cliente_id=c.id
        LEFT JOIN (SELECT v2.cliente_id, SUM(dv.total) dev FROM devoluciones dv JOIN ventas v2 ON v2.id=dv.venta_id WHERE v2.estado='COMPLETADA' GROUP BY v2.cliente_id) d ON d.cliente_id=c.id
        WHERE c.id<>1 AND c.estado=1")->fetchAll();
    [$u5, $u4, $u3, $u2] = crm_umbrales();
    $mont = []; foreach ($rows as $r) if ($r['n'] > 0) $mont[$r['id']] = (float)$r['monto'];
    $mq = crm_quintiles($mont);
    $hoy = new DateTime('today'); $out = [];
    foreach ($rows as $r) {
        $n = (int)$r['n']; $r['n'] = $n; $r['monto'] = round(max(0, (float)$r['monto']), 2);
        $r['dias'] = $r['ultima'] ? (int)$hoy->diff(new DateTime(substr($r['ultima'], 0, 10)))->days : null;
        $r['ticket'] = $n ? round($r['monto'] / $n, 2) : 0;
        if ($n === 0) { $r += ['r' => 0, 'f' => 0, 'm' => 0, 'score' => 0, 'seg' => 'prospectos']; $out[$r['id']] = $r; continue; }
        $d = $r['dias'];
        $R = $d <= $u5 ? 5 : ($d <= $u4 ? 4 : ($d <= $u3 ? 3 : ($d <= $u2 ? 2 : 1)));
        $F = $n >= 12 ? 5 : ($n >= 6 ? 4 : ($n >= 3 ? 3 : ($n == 2 ? 2 : 1)));
        $M = $mq[$r['id']] ?? 1; $FM = ($F + $M) / 2;
        if ($n === 1 && $d <= 30) $seg = 'nuevos';
        elseif ($R <= 2) $seg = $FM >= 4 ? 'no_perder' : ($FM >= 3 ? 'en_riesgo' : 'dormidos');
        elseif ($R >= 4 && $FM >= 4) $seg = 'campeones';
        elseif ($FM >= 3) $seg = 'leales';
        elseif ($R >= 4) $seg = 'prometedores';
        else $seg = 'ocasionales';
        $r += ['r' => $R, 'f' => $F, 'm' => $M, 'score' => (int)round(($R + $F + $M) / 15 * 100), 'seg' => $seg];
        $out[$r['id']] = $r;
    }
    return $cache = $out;
}

/** Días que faltan para el próximo cumpleaños (0 = hoy) */
function crm_dias_cumple(?string $fecha): ?int
{
    if (!$fecha) return null;
    $d = DateTime::createFromFormat('Y-m-d', substr($fecha, 0, 10)); if (!$d) return null;
    $hoy = new DateTime('today'); $m = (int)$d->format('n'); $dd = (int)$d->format('j'); $y = (int)$hoy->format('Y');
    for ($k = 0; $k < 2; $k++, $y++) {
        $day = ($m === 2 && $dd === 29 && !checkdate(2, 29, $y)) ? 28 : $dd;
        $n = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $day));
        if ($n >= $hoy) return (int)$hoy->diff($n)->days;
    }
    return null;
}

/** Quita acentos y deja solo dígitos del teléfono + código de país. null si no es válido */
function crm_telefono(?string $t): ?string
{
    $d = preg_replace('/\D+/', '', (string)$t); if ($d === '') return null;
    $cc = preg_replace('/\D+/', '', cfg('crm_codigo_pais', '51')) ?: '51';
    if (strlen($d) === 9 && $d[0] === '9') return $cc . $d;           // celular local
    if (strlen($d) >= 10 && strlen($d) <= 15) return $d;               // ya incluye código de país
    return null;
}

/** Reemplaza {variables} de la plantilla con los datos del cliente (fila de crm_rfm o de clientes) */
function crm_render(string $txt, array $c): string
{
    $partes = preg_split('/\s+/', trim((string)($c['nombre'] ?? '')));
    $primer = mb_convert_case(mb_strtolower($partes[0] ?? ''), MB_CASE_TITLE, 'UTF-8');
    $dias = $c['dias'] ?? null;
    return strtr($txt, [
        '{nombre}' => $primer, '{nombre_completo}' => (string)($c['nombre'] ?? ''), '{puntos}' => (string)(int)($c['puntos'] ?? 0),
        '{deuda}' => money($c['saldo_deuda'] ?? 0), '{dias}' => $dias === null ? '—' : (string)$dias,
        '{empresa}' => cfg('empresa_nombre', APP_NAME), '{descuento}' => rtrim(rtrim(number_format((float)cfg('cumple_descuento', '5'), 2, '.', ''), '0'), '.'),
    ]);
}

/**
 * Resuelve un segmento de campaña a una lista de clientes.
 * Claves: rfm:<segmento> · cumple (próximos 7 días) · deuda · puntos (≥100) · tag:<id> · todos
 */
function crm_segmento_clientes(string $clave): array
{
    $all = crm_rfm(); $def = crm_segmentos_def(); $out = []; $nombre = '';
    if (str_starts_with($clave, 'rfm:')) {
        $k = substr($clave, 4); if (!isset($def[$k])) return [[], ''];
        $nombre = 'Segmento ' . $def[$k][0];
        foreach ($all as $c) if ($c['seg'] === $k) $out[] = $c;
    } elseif ($clave === 'cumple') {
        $nombre = 'Cumpleaños próximos (7 días)';
        foreach ($all as $c) { $d = crm_dias_cumple($c['fecha_nacimiento']); if ($d !== null && $d <= 7) $out[] = $c; }
    } elseif ($clave === 'deuda') {
        $nombre = 'Clientes con deuda';
        foreach ($all as $c) if ($c['saldo_deuda'] > 0) $out[] = $c;
    } elseif ($clave === 'puntos') {
        $nombre = 'Con 100+ puntos acumulados';
        foreach ($all as $c) if ($c['puntos'] >= 100) $out[] = $c;
    } elseif (str_starts_with($clave, 'tag:')) {
        $tid = (int)substr($clave, 4); $tn = q('SELECT nombre FROM crm_etiquetas WHERE id=?', [$tid])->fetchColumn(); if (!$tn) return [[], ''];
        $nombre = 'Etiqueta ' . $tn; $ids = array_flip(q('SELECT cliente_id FROM crm_cliente_etiqueta WHERE etiqueta_id=?', [$tid])->fetchAll(PDO::FETCH_COLUMN));
        foreach ($all as $c) if (isset($ids[$c['id']])) $out[] = $c;
    } elseif ($clave === 'todos') {
        $nombre = 'Todos los clientes'; $out = array_values($all);
    } else return [[], ''];
    return [$out, $nombre];
}
