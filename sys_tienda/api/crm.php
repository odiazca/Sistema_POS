<?php
/** API del CRM · SysTienda */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/crm.php';

$a = $_GET['a'] ?? (body()['a'] ?? 'dashboard');
$escritura = ['perfil_guardar', 'nota_add', 'nota_del', 'etiqueta_guardar', 'etiqueta_borrar', 'etiqueta_toggle', 'tarea_guardar', 'tarea_toggle', 'tarea_borrar',
              'opp_guardar', 'opp_mover', 'opp_borrar', 'plantilla_guardar', 'plantilla_borrar', 'campana_crear', 'destinatario_marcar', 'campana_cerrar', 'campana_borrar'];
api_guard([1, 2], in_array($a, $escritura, true));
$uid = usuario()['id']; $admin = usuario()['rol_id'] === 1; $pdo = db();
// El cajero solo opera la ficha del cliente y sus propias tareas
$cajero_ok = ['clientes', 'buscar', 'usuarios', 'ficha', 'nota_add', 'nota_del', 'etiquetas', 'etiqueta_toggle', 'tareas', 'tarea_guardar', 'tarea_toggle', 'tarea_borrar'];
if (!$admin && !in_array($a, $cajero_ok, true)) json_fail('Esta sección es solo para administradores.', 403);

/* ---------- utilidades ---------- */
function fh($v): ?string
{
    $v = trim((string)$v); if ($v === '') return null;
    $v = str_replace('T', ' ', $v);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v . ' 09:00:00';
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v)) return substr($v . ':00', 0, 19);
    json_fail('Fecha u hora no válida.');
}
function fd($v): ?string
{
    $v = trim((string)$v); if ($v === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) json_fail('Fecha no válida.');
    return $v;
}
function cli_ok(int $id): array
{
    $c = q('SELECT * FROM clientes WHERE id=? AND id<>1', [$id])->fetch();
    if (!$c) json_fail('Cliente no encontrado.', 404);
    return $c;
}
function crm_nota(int $cid, string $tipo, string $texto, ?int $uid): void
{
    q('INSERT INTO crm_notas (cliente_id,usuario_id,tipo,texto) VALUES (?,?,?,?)', [$cid, $uid, $tipo, mb_substr($texto, 0, 1000)]);
    if ($tipo !== 'SISTEMA') q('UPDATE clientes SET ultimo_contacto=NOW() WHERE id=?', [$cid]);
}
function camp_stats(int $id): array
{
    $e = q("SELECT COUNT(*) total, SUM(estado='ENVIADO') enviados, SUM(estado='OMITIDO') omitidos FROM crm_campana_destinatarios WHERE campana_id=?", [$id])->fetch();
    $c = q("SELECT COUNT(DISTINCT d.cliente_id) clientes, COALESCE(SUM(v.total),0) monto FROM crm_campana_destinatarios d
            JOIN ventas v ON v.cliente_id=d.cliente_id AND v.estado='COMPLETADA' AND v.fecha>=d.enviado_en AND v.fecha<=DATE_ADD(d.enviado_en, INTERVAL 30 DAY)
            WHERE d.campana_id=? AND d.estado='ENVIADO'", [$id])->fetch();
    $env = (int)$e['enviados'];
    return ['total' => (int)$e['total'], 'enviados' => $env, 'omitidos' => (int)$e['omitidos'], 'pendientes' => (int)$e['total'] - $env - (int)$e['omitidos'],
            'compraron' => (int)$c['clientes'], 'monto' => (float)$c['monto'], 'conversion' => $env ? round($c['clientes'] / $env * 100, 1) : 0];
}
function etiquetas_de(array $ids): array
{
    if (!$ids) return [];
    $in = implode(',', array_map('intval', $ids)); $m = [];
    foreach (db()->query("SELECT ce.cliente_id cid, e.id, e.nombre, e.color FROM crm_cliente_etiqueta ce JOIN crm_etiquetas e ON e.id=ce.etiqueta_id WHERE ce.cliente_id IN ($in) ORDER BY e.nombre")->fetchAll() as $t)
        $m[$t['cid']][] = ['id' => (int)$t['id'], 'nombre' => $t['nombre'], 'color' => $t['color']];
    return $m;
}
function mini(array $c): array
{
    return ['id' => (int)$c['id'], 'nombre' => $c['nombre'], 'telefono' => $c['telefono'], 'wa' => $c['acepta_marketing'] ? crm_telefono($c['telefono']) : null, 'documento' => $c['documento'], 'tipo_doc' => $c['tipo_doc'], 'seg' => $c['seg'], 'score' => $c['score'],
            'n' => $c['n'], 'monto' => $c['monto'], 'dias' => $c['dias'], 'ticket' => $c['ticket'], 'puntos' => (int)$c['puntos'], 'saldo_deuda' => (float)$c['saldo_deuda'],
            'ultimo_contacto' => $c['ultimo_contacto'], 'acepta_marketing' => (int)$c['acepta_marketing']];
}

switch ($a) {

/* ================= PANEL ================= */
case 'dashboard':
    $rf = crm_rfm(); $def = crm_segmentos_def();
    $seg = []; foreach ($def as $k => $d) $seg[$k] = ['key' => $k, 'nombre' => $d[0], 'color' => $d[1], 'icono' => $d[2], 'n' => 0, 'monto' => 0.0];
    $conCompra = $recompra = $act90 = 0; $sumM = 0.0; $sumN = 0;
    foreach ($rf as $c) {
        $seg[$c['seg']]['n']++; $seg[$c['seg']]['monto'] += $c['monto'];
        if ($c['n'] > 0) { $conCompra++; $sumM += $c['monto']; $sumN += $c['n']; if ($c['n'] >= 2) $recompra++; if ($c['dias'] <= 90) $act90++; }
    }
    $riesgo = array_values(array_filter($rf, fn($c) => in_array($c['seg'], ['no_perder', 'en_riesgo'], true)));
    usort($riesgo, fn($x, $y) => $y['monto'] <=> $x['monto']);
    $top = array_values(array_filter($rf, fn($c) => $c['n'] > 0)); usort($top, fn($x, $y) => $y['monto'] <=> $x['monto']);
    $cumples = []; foreach ($rf as $c) { $d = crm_dias_cumple($c['fecha_nacimiento']); if ($d !== null && $d <= 14) $cumples[] = mini($c) + ['en_dias' => $d]; }
    usort($cumples, fn($x, $y) => $x['en_dias'] <=> $y['en_dias']);
    // nuevos clientes por mes
    $meses = []; for ($i = 5; $i >= 0; $i--) $meses[date('Y-m', strtotime("first day of -$i month"))] = 0;
    foreach (q("SELECT DATE_FORMAT(creado_en,'%Y-%m') m, COUNT(*) n FROM clientes WHERE id<>1 AND creado_en >= ? GROUP BY m", [date('Y-m-01', strtotime('-5 month'))])->fetchAll() as $r) if (isset($meses[$r['m']])) $meses[$r['m']] = (int)$r['n'];
    $nuevosMes = (int)q("SELECT COUNT(*) FROM clientes WHERE id<>1 AND creado_en >= ?", [date('Y-m-01')])->fetchColumn();
    // tareas
    $tw = $admin ? '' : ' AND t.asignado_id=' . (int)$uid;
    $tk = q("SELECT SUM(t.vence IS NOT NULL AND t.vence<NOW()) vencidas, SUM(DATE(t.vence)=CURDATE()) hoy, COUNT(*) pend FROM crm_tareas t WHERE t.estado='PENDIENTE'$tw")->fetch();
    $prox = q("SELECT t.id,t.titulo,t.tipo,t.prioridad,t.vence,t.cliente_id,c.nombre cliente, (t.vence IS NOT NULL AND t.vence<NOW()) vencida FROM crm_tareas t LEFT JOIN clientes c ON c.id=t.cliente_id WHERE t.estado='PENDIENTE'$tw ORDER BY t.vence IS NULL, t.vence LIMIT 6")->fetchAll();
    // pipeline
    $pp = q("SELECT COUNT(*) n, COALESCE(SUM(o.valor),0) valor, COALESCE(SUM(o.valor*e.probabilidad/100),0) pond FROM crm_oportunidades o JOIN crm_etapas e ON e.id=o.etapa_id WHERE o.estado='ABIERTA'")->fetch();
    $gm = q("SELECT COUNT(*) n, COALESCE(SUM(valor),0) valor FROM crm_oportunidades WHERE estado='GANADA' AND cerrado_en>=?", [date('Y-m-01')])->fetch();
    // campañas
    $camps = q('SELECT id,nombre,segmento_nombre,creado_en FROM crm_campanas ORDER BY id DESC LIMIT 4')->fetchAll();
    foreach ($camps as &$cp) $cp += camp_stats((int)$cp['id']); unset($cp);
    $atrib = (float)q("SELECT COALESCE(SUM(v.total),0) FROM crm_campana_destinatarios d JOIN ventas v ON v.cliente_id=d.cliente_id AND v.estado='COMPLETADA' AND v.fecha>=d.enviado_en AND v.fecha<=DATE_ADD(d.enviado_en, INTERVAL 30 DAY) WHERE d.estado='ENVIADO' AND d.enviado_en>=?", [date('Y-m-01')])->fetchColumn();
    json_out(['ok' => true,
        'kpi' => ['clientes' => count($rf), 'nuevos_mes' => $nuevosMes, 'activos_90' => $act90, 'recompra' => $conCompra ? round($recompra / $conCompra * 100, 1) : 0,
                  'ltv' => $conCompra ? round($sumM / $conCompra, 2) : 0, 'ticket' => $sumN ? round($sumM / $sumN, 2) : 0, 'en_riesgo' => $seg['en_riesgo']['n'] + $seg['no_perder']['n'],
                  'valor_riesgo' => round($seg['en_riesgo']['monto'] + $seg['no_perder']['monto'], 2), 'atribuido_mes' => $atrib],
        'segmentos' => array_values($seg), 'riesgo' => array_map('mini', array_slice($riesgo, 0, 7)), 'top' => array_map('mini', array_slice($top, 0, 6)),
        'cumples' => array_slice($cumples, 0, 6), 'meses' => $meses,
        'tareas' => ['vencidas' => (int)$tk['vencidas'], 'hoy' => (int)$tk['hoy'], 'pendientes' => (int)$tk['pend'], 'proximas' => $prox],
        'pipeline' => ['abiertas' => (int)$pp['n'], 'valor' => (float)$pp['valor'], 'ponderado' => round((float)$pp['pond'], 2), 'ganadas_mes' => (int)$gm['n'], 'valor_ganado_mes' => (float)$gm['valor']],
        'campanas' => $camps]);

/* ================= CLIENTES 360 ================= */
case 'clientes':
    $rf = array_values(crm_rfm()); $f = $rf;
    $segcount = []; foreach ($rf as $c) $segcount[$c['seg']] = ($segcount[$c['seg']] ?? 0) + 1;
    if (($s = mb_strtolower(trim($_GET['q'] ?? ''))) !== '') $f = array_filter($f, fn($c) => str_contains(mb_strtolower($c['nombre'] . ' ' . $c['documento'] . ' ' . $c['telefono'] . ' ' . $c['email']), $s));
    if (($sg = $_GET['seg'] ?? '') !== '') $f = array_filter($f, fn($c) => $c['seg'] === $sg);
    $tm = [];
    foreach (q('SELECT cliente_id, etiqueta_id FROM crm_cliente_etiqueta')->fetchAll() as $r) $tm[$r['cliente_id']][$r['etiqueta_id']] = 1;
    if (($tg = (int)($_GET['tag'] ?? 0)) > 0) $f = array_filter($f, fn($c) => isset($tm[$c['id']][$tg]));
    $f = array_values($f);
    $ord = $_GET['orden'] ?? 'monto';
    usort($f, match ($ord) {
        'recientes' => fn($x, $y) => ($x['dias'] ?? 99999) <=> ($y['dias'] ?? 99999),
        'score'     => fn($x, $y) => $y['score'] <=> $x['score'],
        'nombre'    => fn($x, $y) => strcasecmp($x['nombre'], $y['nombre']),
        'deuda'     => fn($x, $y) => $y['saldo_deuda'] <=> $x['saldo_deuda'],
        'riesgo'    => fn($x, $y) => ($y['dias'] ?? 0) <=> ($x['dias'] ?? 0),
        default     => fn($x, $y) => $y['monto'] <=> $x['monto'],
    });
    $per = 15; $total = count($f); $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    $slice = array_slice($f, ($page - 1) * $per, $per); $et = etiquetas_de(array_column($slice, 'id'));
    $rows = array_map(fn($c) => mini($c) + ['etiquetas' => $et[$c['id']] ?? []], $slice);
    json_out(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'segcount' => $segcount, 'todos' => count($rf)]);

case 'buscar':
    $s = '%' . trim($_GET['q'] ?? '') . '%';
    json_out(['ok' => true, 'rows' => q('SELECT id,nombre,documento,telefono FROM clientes WHERE id<>1 AND estado=1 AND (nombre LIKE ? OR documento LIKE ? OR telefono LIKE ?) ORDER BY nombre LIMIT 8', [$s, $s, $s])->fetchAll()]);

case 'usuarios':
    json_out(['ok' => true, 'rows' => q("SELECT id, CONCAT(nombres,' ',apellidos) nombre FROM usuarios WHERE estado=1 ORDER BY nombres")->fetchAll(), 'yo' => $uid]);

case 'ficha':
    $id = (int)($_GET['id'] ?? 0); $c = cli_ok($id);
    $rf = crm_rfm()[$id] ?? null;
    if (!$rf) { $rf = $c + ['n' => 0, 'monto' => 0, 'ultima' => null, 'primera' => null, 'dias' => null, 'ticket' => 0, 'r' => 0, 'f' => 0, 'm' => 0, 'score' => 0, 'seg' => 'prospectos']; }
    $def = crm_segmentos_def(); $sd = $def[$rf['seg']];
    $top = q("SELECT p.nombre, SUM(dv.cantidad) cant, SUM(dv.subtotal) monto FROM detalle_ventas dv JOIN ventas v ON v.id=dv.venta_id JOIN productos p ON p.id=dv.producto_id WHERE v.cliente_id=? AND v.estado='COMPLETADA' GROUP BY p.id,p.nombre ORDER BY monto DESC LIMIT 5", [$id])->fetchAll();
    $cats = q("SELECT ca.nombre, ca.color, SUM(dv.subtotal) monto FROM detalle_ventas dv JOIN ventas v ON v.id=dv.venta_id JOIN productos p ON p.id=dv.producto_id JOIN categorias ca ON ca.id=p.categoria_id WHERE v.cliente_id=? AND v.estado='COMPLETADA' GROUP BY ca.id,ca.nombre,ca.color ORDER BY monto DESC LIMIT 5", [$id])->fetchAll();
    $pago = q("SELECT metodo_pago m, COUNT(*) n FROM ventas WHERE cliente_id=? AND estado='COMPLETADA' GROUP BY metodo_pago ORDER BY n DESC", [$id])->fetchAll();
    $mm = []; for ($i = 5; $i >= 0; $i--) $mm[date('Y-m', strtotime("first day of -$i month"))] = 0.0;
    foreach (q("SELECT DATE_FORMAT(fecha,'%Y-%m') m, SUM(total) t FROM ventas WHERE cliente_id=? AND estado='COMPLETADA' AND fecha>=? GROUP BY m", [$id, date('Y-m-01', strtotime('-5 month'))])->fetchAll() as $r) if (isset($mm[$r['m']])) $mm[$r['m']] = (float)$r['t'];
    $intervalo = ($rf['n'] >= 2 && $rf['primera'] && $rf['ultima']) ? round((strtotime($rf['ultima']) - strtotime($rf['primera'])) / 86400 / ($rf['n'] - 1), 1) : null;
    $proxima = ($intervalo && $rf['ultima']) ? date('Y-m-d', strtotime($rf['ultima']) + (int)round($intervalo * 86400)) : null;
    $dc = crm_dias_cumple($c['fecha_nacimiento']);
    // línea de tiempo
    $tl = [];
    foreach (q("SELECT v.id, v.fecha, CONCAT(v.serie,'-',LPAD(v.numero,6,'0')) nro, v.total, v.metodo_pago, v.estado, (SELECT COUNT(*) FROM detalle_ventas WHERE venta_id=v.id) items FROM ventas v WHERE v.cliente_id=? ORDER BY v.id DESC LIMIT 40", [$id])->fetchAll() as $r)
        $tl[] = ['t' => 'venta', 'f' => $r['fecha'], 'titulo' => 'Compra ' . $r['nro'], 'detalle' => $r['items'] . ' ítem(s) · ' . ucfirst(strtolower($r['metodo_pago'])) . ($r['estado'] === 'ANULADA' ? ' · ANULADA' : ''), 'monto' => (float)$r['total'], 'anulada' => $r['estado'] === 'ANULADA'];
    foreach (q('SELECT fecha,monto,metodo,nota FROM pagos_clientes WHERE cliente_id=? ORDER BY id DESC LIMIT 20', [$id])->fetchAll() as $r)
        $tl[] = ['t' => 'pago', 'f' => $r['fecha'], 'titulo' => 'Pago de deuda', 'detalle' => ucfirst(strtolower($r['metodo'])) . ($r['nota'] ? ' · ' . $r['nota'] : ''), 'monto' => (float)$r['monto']];
    foreach (q('SELECT id,fecha,total,estado FROM cotizaciones WHERE cliente_id=? ORDER BY id DESC LIMIT 15', [$id])->fetchAll() as $r)
        $tl[] = ['t' => 'cotizacion', 'f' => $r['fecha'] . ' 00:00:00', 'titulo' => 'Cotización N° ' . $r['id'], 'detalle' => ucfirst(strtolower($r['estado'])), 'monto' => (float)$r['total']];
    foreach (q("SELECT d.fecha,d.total,d.motivo FROM devoluciones d JOIN ventas v ON v.id=d.venta_id WHERE v.cliente_id=? ORDER BY d.id DESC LIMIT 15", [$id])->fetchAll() as $r)
        $tl[] = ['t' => 'devolucion', 'f' => $r['fecha'], 'titulo' => 'Devolución', 'detalle' => $r['motivo'], 'monto' => -(float)$r['total']];
    foreach (q("SELECT n.id,n.creado_en,n.tipo,n.texto,n.usuario_id,CONCAT(u.nombres,' ',u.apellidos) autor FROM crm_notas n LEFT JOIN usuarios u ON u.id=n.usuario_id WHERE n.cliente_id=? ORDER BY n.id DESC LIMIT 60", [$id])->fetchAll() as $r)
        $tl[] = ['t' => 'nota', 'sub' => $r['tipo'], 'f' => $r['creado_en'], 'titulo' => $r['tipo'] === 'SISTEMA' ? 'Sistema' : ucfirst(strtolower($r['tipo'])), 'detalle' => $r['texto'], 'autor' => $r['autor'], 'nota_id' => (int)$r['id'], 'puede_borrar' => $admin || (int)$r['usuario_id'] === $uid];
    usort($tl, fn($x, $y) => strcmp($y['f'], $x['f'])); $tl = array_slice($tl, 0, 70);
    $tareas = q("SELECT t.*, CONCAT(u.nombres,' ',u.apellidos) asignado, (t.vence IS NOT NULL AND t.vence<NOW()) vencida FROM crm_tareas t LEFT JOIN usuarios u ON u.id=t.asignado_id WHERE t.cliente_id=? AND t.estado='PENDIENTE' ORDER BY t.vence IS NULL, t.vence", [$id])->fetchAll();
    $opps = q("SELECT o.id,o.titulo,o.valor,o.estado,o.cierre_estimado,e.nombre etapa,e.color,e.probabilidad FROM crm_oportunidades o JOIN crm_etapas e ON e.id=o.etapa_id WHERE o.cliente_id=? ORDER BY o.estado='ABIERTA' DESC, o.id DESC LIMIT 8", [$id])->fetchAll();
    // próximas mejores acciones
    $sug = [];
    if ($rf['saldo_deuda'] > 0) $sug[] = ['bi-cash-coin', 'Cobrar saldo pendiente de ' . money($rf['saldo_deuda'])];
    if ($dc !== null && $dc <= 7) $sug[] = ['bi-cake2', $dc === 0 ? '¡Hoy es su cumpleaños! Felicítalo' : "Cumpleaños en $dc día(s): prepara un saludo"];
    if (in_array($rf['seg'], ['no_perder', 'en_riesgo', 'dormidos'], true) && $rf['dias'] !== null) $sug[] = ['bi-arrow-repeat', "Lleva {$rf['dias']} días sin comprar: reactívalo"];
    if ($proxima && $proxima < date('Y-m-d', strtotime('+3 days')) && $rf['dias'] !== null && $rf['dias'] >= max($intervalo, 3)) $sug[] = ['bi-clock-history', 'Ya le toca volver según su ritmo de compra'];
    if (!$sug) $sug[] = [$sd[2], $sd[4]];
    $resp = $c['responsable_id'] ? q("SELECT CONCAT(nombres,' ',apellidos) FROM usuarios WHERE id=?", [$c['responsable_id']])->fetchColumn() : null;
    $et = etiquetas_de([$id]);
    json_out(['ok' => true,
        'cliente' => ['id' => $id, 'nombre' => $c['nombre'], 'tipo_doc' => $c['tipo_doc'], 'documento' => $c['documento'], 'telefono' => $c['telefono'], 'email' => $c['email'], 'direccion' => $c['direccion'],
                      'fecha_nacimiento' => $c['fecha_nacimiento'], 'creado_en' => $c['creado_en'], 'puntos' => (int)$c['puntos'], 'limite_credito' => (float)$c['limite_credito'], 'saldo_deuda' => (float)$c['saldo_deuda'],
                      'origen' => $c['origen'], 'acepta_marketing' => (int)$c['acepta_marketing'], 'responsable_id' => $c['responsable_id'] ? (int)$c['responsable_id'] : null, 'responsable' => $resp,
                      'ultimo_contacto' => $c['ultimo_contacto'], 'wa' => crm_telefono($c['telefono']), 'cumple_en' => $dc],
        'metricas' => ['n' => $rf['n'], 'monto' => $rf['monto'], 'ticket' => $rf['ticket'], 'dias' => $rf['dias'], 'primera' => $rf['primera'], 'ultima' => $rf['ultima'], 'intervalo' => $intervalo, 'proxima' => $proxima,
                       'r' => $rf['r'], 'f' => $rf['f'], 'm' => $rf['m'], 'score' => $rf['score']],
        'segmento' => ['key' => $rf['seg'], 'nombre' => $sd[0], 'color' => $sd[1], 'icono' => $sd[2], 'desc' => $sd[3], 'accion' => $sd[4]],
        'top' => $top, 'categorias' => $cats, 'pagos' => $pago, 'meses' => $mm, 'timeline' => $tl, 'tareas' => $tareas, 'oportunidades' => $opps, 'sugerencias' => $sug,
        'etiquetas' => $et[$id] ?? [], 'plantillas' => q('SELECT id,nombre,categoria,texto FROM crm_plantillas WHERE estado=1 ORDER BY id')->fetchAll(),
        'empresa' => cfg('empresa_nombre', APP_NAME)]);

case 'perfil_guardar':
    $b = body(); $id = (int)($b['id'] ?? 0); cli_ok($id);
    $origen = trim((string)($b['origen'] ?? '')); if ($origen !== '' && !in_array($origen, CRM_ORIGENES, true)) json_fail('Origen no válido.');
    $resp = (int)($b['responsable_id'] ?? 0);
    if ($resp && !q('SELECT 1 FROM usuarios WHERE id=? AND estado=1', [$resp])->fetchColumn()) json_fail('Responsable no válido.');
    q('UPDATE clientes SET origen=?, acepta_marketing=?, responsable_id=? WHERE id=?', [$origen ?: null, empty($b['acepta_marketing']) ? 0 : 1, $resp ?: null, $id]);
    audit('EDITAR', 'crm_perfil', $id, 'Perfil CRM del cliente');
    json_out(['ok' => true]);

/* ================= NOTAS / ETIQUETAS ================= */
case 'nota_add':
    $b = body(); $id = (int)($b['cliente_id'] ?? 0); cli_ok($id);
    $tipo = $b['tipo'] ?? 'NOTA'; if (!in_array($tipo, ['NOTA', 'LLAMADA', 'VISITA', 'WHATSAPP', 'EMAIL', 'REUNION'], true)) json_fail('Tipo de nota no válido.');
    $tx = trim((string)($b['texto'] ?? '')); if ($tx === '') json_fail('Escribe el contenido de la nota.');
    crm_nota($id, $tipo, $tx, $uid); json_out(['ok' => true]);

case 'nota_del':
    $id = (int)(body()['id'] ?? 0); $n = q('SELECT * FROM crm_notas WHERE id=?', [$id])->fetch(); if (!$n) json_fail('Nota no encontrada.', 404);
    if (!$admin && (int)$n['usuario_id'] !== $uid) json_fail('Solo puedes borrar tus propias notas.', 403);
    q('DELETE FROM crm_notas WHERE id=?', [$id]); json_out(['ok' => true]);

case 'etiquetas':
    json_out(['ok' => true, 'rows' => q('SELECT e.id,e.nombre,e.color,(SELECT COUNT(*) FROM crm_cliente_etiqueta ce WHERE ce.etiqueta_id=e.id) n FROM crm_etiquetas e ORDER BY e.nombre')->fetchAll(), 'origenes' => CRM_ORIGENES]);

case 'etiqueta_guardar':
    if (!$admin) json_fail('Solo el administrador gestiona etiquetas.', 403);
    $b = body(); $id = (int)($b['id'] ?? 0); $n = trim((string)($b['nombre'] ?? '')); $col = (string)($b['color'] ?? '#5b5ef4');
    if ($n === '' || mb_strlen($n) > 40) json_fail('Nombre de etiqueta no válido (máx. 40).');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $col)) $col = '#5b5ef4';
    try { $id ? q('UPDATE crm_etiquetas SET nombre=?, color=? WHERE id=?', [$n, $col, $id]) : q('INSERT INTO crm_etiquetas (nombre,color) VALUES (?,?)', [$n, $col]); }
    catch (PDOException $e) { json_fail('Ya existe una etiqueta con ese nombre.'); }
    json_out(['ok' => true]);

case 'etiqueta_borrar':
    if (!$admin) json_fail('Solo el administrador gestiona etiquetas.', 403);
    q('DELETE FROM crm_etiquetas WHERE id=?', [(int)(body()['id'] ?? 0)]); json_out(['ok' => true]);

case 'etiqueta_toggle':
    $b = body(); $cid = (int)($b['cliente_id'] ?? 0); $eid = (int)($b['etiqueta_id'] ?? 0); cli_ok($cid);
    if (!q('SELECT 1 FROM crm_etiquetas WHERE id=?', [$eid])->fetchColumn()) json_fail('Etiqueta no encontrada.', 404);
    if (q('SELECT 1 FROM crm_cliente_etiqueta WHERE cliente_id=? AND etiqueta_id=?', [$cid, $eid])->fetchColumn()) { q('DELETE FROM crm_cliente_etiqueta WHERE cliente_id=? AND etiqueta_id=?', [$cid, $eid]); json_out(['ok' => true, 'on' => false]); }
    q('INSERT INTO crm_cliente_etiqueta (cliente_id,etiqueta_id) VALUES (?,?)', [$cid, $eid]); json_out(['ok' => true, 'on' => true]);

/* ================= TAREAS ================= */
case 'tareas':
    $w = []; $p = [];
    $est = $_GET['estado'] ?? 'PENDIENTE'; if (in_array($est, ['PENDIENTE', 'HECHA'], true)) { $w[] = 't.estado=?'; $p[] = $est; }
    if (!$admin) { $w[] = '(t.asignado_id=? OR t.creado_por=?)'; $p[] = $uid; $p[] = $uid; }
    elseif (($_GET['quien'] ?? '') === 'mias') { $w[] = 't.asignado_id=?'; $p[] = $uid; }
    elseif ((int)($_GET['asignado'] ?? 0) > 0) { $w[] = 't.asignado_id=?'; $p[] = (int)$_GET['asignado']; }
    if ((int)($_GET['cliente_id'] ?? 0) > 0) { $w[] = 't.cliente_id=?'; $p[] = (int)$_GET['cliente_id']; }
    $ws = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $rows = q("SELECT t.*, c.nombre cliente, c.telefono, CONCAT(u.nombres,' ',u.apellidos) asignado, (t.estado='PENDIENTE' AND t.vence IS NOT NULL AND t.vence<NOW()) vencida
               FROM crm_tareas t LEFT JOIN clientes c ON c.id=t.cliente_id LEFT JOIN usuarios u ON u.id=t.asignado_id$ws
               ORDER BY t.estado='HECHA', t.vence IS NULL, t.vence, FIELD(t.prioridad,'ALTA','MEDIA','BAJA') LIMIT 300", $p)->fetchAll();
    $own = $admin ? '' : ' AND (asignado_id=' . (int)$uid . ' OR creado_por=' . (int)$uid . ')';
    $k = q("SELECT SUM(estado='PENDIENTE') pend, SUM(estado='PENDIENTE' AND vence IS NOT NULL AND vence<NOW()) venc, SUM(estado='PENDIENTE' AND DATE(vence)=CURDATE()) hoy, SUM(estado='HECHA' AND completada_en>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) hechas FROM crm_tareas WHERE 1=1$own")->fetch();
    json_out(['ok' => true, 'rows' => $rows, 'kpi' => ['pendientes' => (int)$k['pend'], 'vencidas' => (int)$k['venc'], 'hoy' => (int)$k['hoy'], 'hechas_semana' => (int)$k['hechas']]]);

case 'tarea_guardar':
    $b = body(); $id = (int)($b['id'] ?? 0);
    $tit = trim((string)($b['titulo'] ?? '')); if ($tit === '') json_fail('Escribe qué hay que hacer.');
    $tipo = $b['tipo'] ?? 'SEGUIMIENTO'; if (!in_array($tipo, ['LLAMAR', 'WHATSAPP', 'VISITA', 'EMAIL', 'COBRAR', 'SEGUIMIENTO', 'OTRO'], true)) $tipo = 'SEGUIMIENTO';
    $pri = $b['prioridad'] ?? 'MEDIA'; if (!in_array($pri, ['BAJA', 'MEDIA', 'ALTA'], true)) $pri = 'MEDIA';
    $cid = (int)($b['cliente_id'] ?? 0) ?: null; if ($cid) cli_ok($cid);
    $oid = (int)($b['oportunidad_id'] ?? 0) ?: null;
    $asig = (int)($b['asignado_id'] ?? 0) ?: $uid; if (!$admin) $asig = $uid;
    $v = fh($b['vence'] ?? '');
    if ($id) {
        $t = q('SELECT * FROM crm_tareas WHERE id=?', [$id])->fetch(); if (!$t) json_fail('Tarea no encontrada.', 404);
        if (!$admin && (int)$t['asignado_id'] !== $uid && (int)$t['creado_por'] !== $uid) json_fail('No puedes editar esta tarea.', 403);
        q('UPDATE crm_tareas SET cliente_id=?, oportunidad_id=?, asignado_id=?, titulo=?, tipo=?, prioridad=?, vence=? WHERE id=?', [$cid, $oid, $asig, mb_substr($tit, 0, 160), $tipo, $pri, $v, $id]);
    } else {
        q('INSERT INTO crm_tareas (cliente_id,oportunidad_id,asignado_id,creado_por,titulo,tipo,prioridad,vence) VALUES (?,?,?,?,?,?,?,?)', [$cid, $oid, $asig, $uid, mb_substr($tit, 0, 160), $tipo, $pri, $v]);
        $id = (int)$pdo->lastInsertId();
    }
    json_out(['ok' => true, 'id' => $id]);

case 'tarea_toggle':
    $id = (int)(body()['id'] ?? 0); $t = q('SELECT * FROM crm_tareas WHERE id=?', [$id])->fetch(); if (!$t) json_fail('Tarea no encontrada.', 404);
    if (!$admin && (int)$t['asignado_id'] !== $uid && (int)$t['creado_por'] !== $uid) json_fail('No puedes modificar esta tarea.', 403);
    if ($t['estado'] === 'PENDIENTE') {
        q("UPDATE crm_tareas SET estado='HECHA', completada_en=NOW() WHERE id=?", [$id]);
        if ($t['cliente_id']) crm_nota((int)$t['cliente_id'], 'SISTEMA', 'Tarea completada: ' . $t['titulo'], $uid);
    } else q("UPDATE crm_tareas SET estado='PENDIENTE', completada_en=NULL WHERE id=?", [$id]);
    json_out(['ok' => true]);

case 'tarea_borrar':
    $id = (int)(body()['id'] ?? 0); $t = q('SELECT * FROM crm_tareas WHERE id=?', [$id])->fetch(); if (!$t) json_fail('Tarea no encontrada.', 404);
    if (!$admin && (int)$t['creado_por'] !== $uid) json_fail('Solo puedes borrar tareas creadas por ti.', 403);
    q('DELETE FROM crm_tareas WHERE id=?', [$id]); json_out(['ok' => true]);

/* ================= PIPELINE ================= */
case 'pipeline':
    $et = q('SELECT * FROM crm_etapas ORDER BY orden')->fetchAll();
    $ops = q("SELECT o.*, c.nombre cliente, c.telefono, CONCAT(u.nombres,' ',u.apellidos) responsable, DATEDIFF(NOW(), o.actualizado_en) dias_etapa, DATEDIFF(o.cierre_estimado, CURDATE()) dias_cierre
              FROM crm_oportunidades o JOIN clientes c ON c.id=o.cliente_id LEFT JOIN usuarios u ON u.id=o.responsable_id
              WHERE o.estado='ABIERTA' OR o.cerrado_en >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY o.orden, o.id")->fetchAll();
    $g = q("SELECT SUM(estado='GANADA') g, SUM(estado='PERDIDA') p, AVG(CASE WHEN estado='GANADA' THEN DATEDIFF(cerrado_en, creado_en) END) ciclo FROM crm_oportunidades")->fetch();
    $ab = q("SELECT COUNT(*) n, COALESCE(SUM(o.valor),0) v, COALESCE(SUM(o.valor*e.probabilidad/100),0) p FROM crm_oportunidades o JOIN crm_etapas e ON e.id=o.etapa_id WHERE o.estado='ABIERTA'")->fetch();
    $cerr = (int)$g['g'] + (int)$g['p'];
    json_out(['ok' => true, 'etapas' => $et, 'oportunidades' => $ops, 'origenes' => CRM_ORIGENES,
        'kpi' => ['abiertas' => (int)$ab['n'], 'valor' => (float)$ab['v'], 'ponderado' => round((float)$ab['p'], 2), 'tasa_exito' => $cerr ? round($g['g'] / $cerr * 100, 1) : 0, 'ciclo' => $g['ciclo'] !== null ? round((float)$g['ciclo'], 1) : null]]);

case 'opp_guardar':
    $b = body(); $id = (int)($b['id'] ?? 0); $cid = (int)($b['cliente_id'] ?? 0); cli_ok($cid);
    $tit = trim((string)($b['titulo'] ?? '')); if ($tit === '') json_fail('Escribe un título para la oportunidad.');
    $val = round((float)($b['valor'] ?? 0), 2); if ($val < 0) json_fail('El valor no puede ser negativo.');
    $eid = (int)($b['etapa_id'] ?? 0); $e = q('SELECT * FROM crm_etapas WHERE id=?', [$eid])->fetch(); if (!$e) json_fail('Etapa no válida.');
    $resp = (int)($b['responsable_id'] ?? 0) ?: $uid; $org = trim((string)($b['origen'] ?? '')) ?: null; $ce = fd($b['cierre_estimado'] ?? '');
    if ($id) {
        $o = q('SELECT * FROM crm_oportunidades WHERE id=?', [$id])->fetch(); if (!$o) json_fail('Oportunidad no encontrada.', 404);
        q('UPDATE crm_oportunidades SET cliente_id=?, titulo=?, valor=?, responsable_id=?, origen=?, cierre_estimado=? WHERE id=?', [$cid, mb_substr($tit, 0, 160), $val, $resp, $org, $ce, $id]);
    } else {
        $ord = (int)q('SELECT COALESCE(MAX(orden),0)+1 FROM crm_oportunidades WHERE etapa_id=?', [$eid])->fetchColumn();
        q('INSERT INTO crm_oportunidades (cliente_id,titulo,valor,etapa_id,responsable_id,origen,cierre_estimado,estado,orden,cerrado_en) VALUES (?,?,?,?,?,?,?,?,?,?)',
          [$cid, mb_substr($tit, 0, 160), $val, $eid, $resp, $org, $ce, $e['tipo'], $ord, $e['tipo'] === 'ABIERTA' ? null : date('Y-m-d H:i:s')]);
        $id = (int)$pdo->lastInsertId();
        crm_nota($cid, 'SISTEMA', "Oportunidad creada: «$tit» (" . money($val) . ') en etapa ' . $e['nombre'], $uid);
    }
    audit('CREAR', 'crm_oportunidad', $id, $tit); json_out(['ok' => true, 'id' => $id]);

case 'opp_mover':
    $b = body(); $id = (int)($b['id'] ?? 0); $o = q('SELECT * FROM crm_oportunidades WHERE id=?', [$id])->fetch(); if (!$o) json_fail('Oportunidad no encontrada.', 404);
    $e = q('SELECT * FROM crm_etapas WHERE id=?', [(int)($b['etapa_id'] ?? 0)])->fetch(); if (!$e) json_fail('Etapa no válida.');
    $motivo = trim((string)($b['motivo'] ?? ''));
    if ($e['tipo'] === 'PERDIDA' && $motivo === '') json_fail('Indica el motivo por el que se perdió.');
    $pdo->beginTransaction();
    try {
        $cambio = (int)$o['etapa_id'] !== (int)$e['id'];
        if ($cambio) {
            q('UPDATE crm_oportunidades SET etapa_id=?, estado=?, motivo_perdida=?, cerrado_en=? WHERE id=?',
              [$e['id'], $e['tipo'], $e['tipo'] === 'PERDIDA' ? mb_substr($motivo, 0, 160) : null, $e['tipo'] === 'ABIERTA' ? null : date('Y-m-d H:i:s'), $id]);
            crm_nota((int)$o['cliente_id'], 'SISTEMA', "Oportunidad «{$o['titulo']}» → {$e['nombre']}" . ($e['tipo'] === 'PERDIDA' ? " (motivo: $motivo)" : ''), $uid);
        }
        $ids = array_values(array_filter(array_map('intval', (array)($b['orden_ids'] ?? []))));
        foreach ($ids as $i => $oid) q('UPDATE crm_oportunidades SET orden=? WHERE id=? AND etapa_id=?', [$i + 1, $oid, $e['id']]);
        $pdo->commit();
    } catch (Throwable $ex) { $pdo->rollBack(); throw $ex; }
    json_out(['ok' => true]);

case 'opp_borrar':
    q('DELETE FROM crm_oportunidades WHERE id=?', [(int)(body()['id'] ?? 0)]); json_out(['ok' => true]);

/* ================= SEGMENTOS ================= */
case 'segmentos':
    $rf = crm_rfm(); $def = crm_segmentos_def(); $seg = []; $mat = array_fill(1, 5, array_fill(1, 5, 0)); $tot = 0; $totM = 0.0;
    foreach ($def as $k => $d) $seg[$k] = ['key' => $k, 'nombre' => $d[0], 'color' => $d[1], 'icono' => $d[2], 'desc' => $d[3], 'accion' => $d[4], 'n' => 0, 'monto' => 0.0, 'dias' => 0, 'tickets' => 0.0, 'cd' => 0];
    foreach ($rf as $c) {
        $s = &$seg[$c['seg']]; $s['n']++; $s['monto'] += $c['monto']; $tot++; $totM += $c['monto'];
        if ($c['n'] > 0) { $s['dias'] += $c['dias']; $s['tickets'] += $c['ticket']; $s['cd']++; $mat[$c['r']][$c['f']]++; }
        unset($s);
    }
    foreach ($seg as &$s) { $s['dias_prom'] = $s['cd'] ? round($s['dias'] / $s['cd']) : null; $s['ticket_prom'] = $s['cd'] ? round($s['tickets'] / $s['cd'], 2) : 0; $s['pct'] = $tot ? round($s['n'] / $tot * 100, 1) : 0; $s['pct_monto'] = $totM ? round($s['monto'] / $totM * 100, 1) : 0; unset($s['dias'], $s['tickets'], $s['cd']); } unset($s);
    json_out(['ok' => true, 'segmentos' => array_values($seg), 'matriz' => $mat, 'total' => $tot, 'monto' => round($totM, 2), 'umbrales' => crm_umbrales()]);

/* ================= CAMPAÑAS ================= */
case 'plantillas':
    json_out(['ok' => true, 'rows' => q('SELECT * FROM crm_plantillas ORDER BY categoria, nombre')->fetchAll()]);

case 'plantilla_guardar':
    $b = body(); $id = (int)($b['id'] ?? 0); $n = trim((string)($b['nombre'] ?? '')); $tx = trim((string)($b['texto'] ?? '')); $cat = (string)($b['categoria'] ?? 'general');
    if ($n === '' || $tx === '') json_fail('Nombre y mensaje son obligatorios.');
    if (!in_array($cat, ['promo', 'cumple', 'reactivacion', 'cobranza', 'general'], true)) $cat = 'general';
    $id ? q('UPDATE crm_plantillas SET nombre=?, categoria=?, texto=?, estado=? WHERE id=?', [mb_substr($n, 0, 60), $cat, mb_substr($tx, 0, 1000), empty($b['estado']) && isset($b['estado']) ? 0 : 1, $id])
        : q('INSERT INTO crm_plantillas (nombre,categoria,texto) VALUES (?,?,?)', [mb_substr($n, 0, 60), $cat, mb_substr($tx, 0, 1000)]);
    json_out(['ok' => true]);

case 'plantilla_borrar':
    q('DELETE FROM crm_plantillas WHERE id=?', [(int)(body()['id'] ?? 0)]); json_out(['ok' => true]);

case 'campanas':
    $rows = q('SELECT id,nombre,segmento,segmento_nombre,mensaje,estado,total,creado_en FROM crm_campanas ORDER BY id DESC LIMIT 60')->fetchAll();
    foreach ($rows as &$r) $r += camp_stats((int)$r['id']); unset($r);
    $def = crm_segmentos_def(); $opts = [];
    foreach ($def as $k => $d) $opts[] = ['key' => "rfm:$k", 'nombre' => 'Segmento · ' . $d[0], 'n' => count(array_filter(crm_rfm(), fn($c) => $c['seg'] === $k))];
    foreach (['cumple' => 'Cumpleaños próximos (7 días)', 'deuda' => 'Clientes con deuda', 'puntos' => 'Con 100+ puntos acumulados', 'todos' => 'Todos los clientes'] as $k => $n) $opts[] = ['key' => $k, 'nombre' => $n, 'n' => count(crm_segmento_clientes($k)[0])];
    foreach (q('SELECT id,nombre FROM crm_etiquetas ORDER BY nombre')->fetchAll() as $t) $opts[] = ['key' => 'tag:' . $t['id'], 'nombre' => 'Etiqueta · ' . $t['nombre'], 'n' => count(crm_segmento_clientes('tag:' . $t['id'])[0])];
    json_out(['ok' => true, 'rows' => $rows, 'segmentos' => $opts, 'plantillas' => q('SELECT id,nombre,categoria,texto FROM crm_plantillas WHERE estado=1 ORDER BY categoria,nombre')->fetchAll(),
              'kpi' => ['campanas' => count($rows), 'enviados' => array_sum(array_column($rows, 'enviados')), 'compraron' => array_sum(array_column($rows, 'compraron')), 'monto' => array_sum(array_column($rows, 'monto'))]]);

case 'campana_preview':
    [$lista, $nombre] = crm_segmento_clientes((string)($_GET['segmento'] ?? '')); if (!$nombre) json_fail('Segmento no válido.');
    $msg = (string)($_GET['mensaje'] ?? ''); $ok = array_values(array_filter($lista, fn($c) => $c['acepta_marketing'] && crm_telefono($c['telefono'])));
    json_out(['ok' => true, 'nombre' => $nombre, 'total' => count($lista), 'elegibles' => count($ok), 'sin_telefono' => count(array_filter($lista, fn($c) => !crm_telefono($c['telefono']))),
              'sin_permiso' => count(array_filter($lista, fn($c) => !$c['acepta_marketing'])),
              'muestra' => array_map(fn($c) => ['nombre' => $c['nombre'], 'msg' => crm_render($msg, $c)], array_slice($ok, 0, 3))]);

case 'campana_crear':
    $b = body(); $n = trim((string)($b['nombre'] ?? '')); $msg = trim((string)($b['mensaje'] ?? ''));
    if ($n === '' || $msg === '') json_fail('Nombre y mensaje son obligatorios.');
    [$lista, $sn] = crm_segmento_clientes((string)($b['segmento'] ?? '')); if (!$sn) json_fail('Segmento no válido.');
    $ok = array_values(array_filter($lista, fn($c) => $c['acepta_marketing'] && crm_telefono($c['telefono'])));
    if (!$ok) json_fail('Ningún cliente de este segmento tiene teléfono válido y acepta mensajes.');
    $pdo->beginTransaction();
    try {
        q('INSERT INTO crm_campanas (nombre,segmento,segmento_nombre,mensaje,total,creado_por) VALUES (?,?,?,?,?,?)', [mb_substr($n, 0, 120), $b['segmento'], $sn, mb_substr($msg, 0, 1000), count($ok), $uid]);
        $cid = (int)$pdo->lastInsertId(); $ins = $pdo->prepare('INSERT IGNORE INTO crm_campana_destinatarios (campana_id,cliente_id) VALUES (?,?)');
        foreach ($ok as $c) $ins->execute([$cid, $c['id']]);
        $pdo->commit();
    } catch (Throwable $ex) { $pdo->rollBack(); throw $ex; }
    audit('CREAR', 'crm_campana', $cid, "$n · $sn · " . count($ok) . ' destinatarios'); json_out(['ok' => true, 'id' => $cid, 'total' => count($ok)]);

case 'campana_detalle':
    $id = (int)($_GET['id'] ?? 0); $c = q('SELECT * FROM crm_campanas WHERE id=?', [$id])->fetch(); if (!$c) json_fail('Campaña no encontrada.', 404);
    $rf = crm_rfm(); $rows = [];
    foreach (q("SELECT d.id,d.cliente_id,d.estado,d.enviado_en,cl.nombre,cl.telefono,cl.puntos,cl.saldo_deuda FROM crm_campana_destinatarios d JOIN clientes cl ON cl.id=d.cliente_id WHERE d.campana_id=? ORDER BY FIELD(d.estado,'PENDIENTE','ENVIADO','OMITIDO'), cl.nombre", [$id])->fetchAll() as $d) {
        $ctx = $rf[$d['cliente_id']] ?? $d;
        $rows[] = ['id' => (int)$d['id'], 'cliente_id' => (int)$d['cliente_id'], 'nombre' => $d['nombre'], 'telefono' => $d['telefono'], 'estado' => $d['estado'], 'enviado_en' => $d['enviado_en'],
                   'wa' => crm_telefono($d['telefono']), 'msg' => crm_render($c['mensaje'], $ctx)];
    }
    json_out(['ok' => true, 'campana' => $c, 'stats' => camp_stats($id), 'rows' => $rows]);

case 'destinatario_marcar':
    $b = body(); $id = (int)($b['id'] ?? 0); $est = $b['estado'] ?? ''; if (!in_array($est, ['ENVIADO', 'OMITIDO', 'PENDIENTE'], true)) json_fail('Estado no válido.');
    $d = q('SELECT d.*, c.nombre camp FROM crm_campana_destinatarios d JOIN crm_campanas c ON c.id=d.campana_id WHERE d.id=?', [$id])->fetch(); if (!$d) json_fail('Destinatario no encontrado.', 404);
    q('UPDATE crm_campana_destinatarios SET estado=?, enviado_en=? WHERE id=?', [$est, $est === 'ENVIADO' ? date('Y-m-d H:i:s') : null, $id]);
    if ($est === 'ENVIADO' && $d['estado'] !== 'ENVIADO') crm_nota((int)$d['cliente_id'], 'WHATSAPP', 'Campaña «' . $d['camp'] . '»: mensaje enviado', $uid);
    json_out(['ok' => true, 'stats' => camp_stats((int)$d['campana_id'])]);

case 'campana_cerrar':
    q("UPDATE crm_campanas SET estado = IF(estado='ACTIVA','CERRADA','ACTIVA') WHERE id=?", [(int)(body()['id'] ?? 0)]); json_out(['ok' => true]);

case 'campana_borrar':
    q('DELETE FROM crm_campanas WHERE id=?', [(int)(body()['id'] ?? 0)]); json_out(['ok' => true]);
}
json_fail('Acción no válida');
