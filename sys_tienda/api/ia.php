<?php
/** Asistente de reportes con IA (solo administrador) */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';
require_once __DIR__ . '/../includes/ia.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'estado');
api_guard([1], !in_array($a, ['estado', 'csv', 'esquema'], true));
$uid = (int)usuario()['id'];
try { ia_instalar(); } catch (Throwable $e) { json_fail('No se pudieron preparar las vistas del asistente: ' . $e->getMessage(), 500); }

/** Respuesta con tipos nativos (sin la conversión numérica automática de json_out) */
function ia_out(array $d): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d + ['ok' => true], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function ia_consulta(int $id, int $uid): array
{
    $c = q('SELECT * FROM ia_consultas WHERE id=? AND usuario_id=?', [$id, $uid])->fetch();
    if (!$c) json_fail('Consulta no encontrada.', 404);
    return $c;
}

switch ($a) {
case 'estado':
    $c = ia_cfg();
    $fav = q('SELECT id,pregunta,titulo FROM ia_consultas WHERE usuario_id=? AND favorito=1 ORDER BY ultimo_uso DESC', [$uid])->fetchAll();
    $his = q('SELECT id,pregunta,titulo,favorito FROM ia_consultas WHERE usuario_id=? AND favorito=0 ORDER BY ultimo_uso DESC LIMIT 15', [$uid])->fetchAll();
    ia_out(['cfg' => ['proveedor' => $c['proveedor'], 'url' => $c['url'], 'modelo' => $c['modelo'], 'clave_puesta' => $c['clave'] !== '', 'resumen' => $c['resumen']],
            'listo' => $c['url'] !== '' && $c['modelo'] !== '' && (!IA_PRESETS[$c['proveedor']]['clave'] || $c['clave'] !== ''),
            'presets' => IA_PRESETS, 'favoritos' => $fav, 'historial' => $his]);

case 'guardar_config':
    $b = body(); $p = (string)($b['proveedor'] ?? '');
    if (!isset(IA_PRESETS[$p])) json_fail('Proveedor no válido.');
    $url = trim((string)($b['url'] ?? '')); $modelo = trim((string)($b['modelo'] ?? ''));
    if (!preg_match('#^https?://[^\s]+$#i', $url)) json_fail('La URL debe empezar por http:// o https://');
    if ($modelo === '' || mb_strlen($modelo) > 100) json_fail('Indica el nombre del modelo.');
    backup_set('ia_proveedor', $p); backup_set('ia_url', $url); backup_set('ia_modelo', $modelo);
    backup_set('ia_resumen', !empty($b['resumen']) ? '1' : '0');
    $k = trim((string)($b['clave'] ?? ''));
    if ($k === '__borrar__') backup_set('ia_clave', ''); elseif ($k !== '') { if (mb_strlen($k) < 8 || mb_strlen($k) > 200 || preg_match('/\s/', $k)) json_fail('La clave no parece válida.'); backup_set('ia_clave', ia_cifrar($k)); }
    audit('CONFIG', 'ia', null, 'Asistente IA: ' . $p . ' / ' . $modelo); ia_out([]);

case 'probar':
    try { $t = ia_llamar([['role' => 'user', 'content' => 'Responde solo con la palabra OK.']], 20); } catch (Throwable $e) { json_fail($e->getMessage()); }
    ia_out(['respuesta' => mb_substr(trim($t), 0, 60)]);

case 'preguntar':
    $b = body(); $pregunta = trim((string)($b['pregunta'] ?? ''));
    if (mb_strlen($pregunta) < 4) json_fail('Escribe tu pregunta (mínimo 4 caracteres).');
    if (mb_strlen($pregunta) > 500) json_fail('La pregunta es demasiado larga (máx. 500 caracteres).');
    $now = time(); $_SESSION['ia_hist'] = array_values(array_filter($_SESSION['ia_hist'] ?? [], fn($t) => $t > $now - 600));
    if (count($_SESSION['ia_hist']) >= 30) json_fail('Demasiadas consultas seguidas. Espera unos minutos.', 429);
    $_SESSION['ia_hist'][] = $now;
    $previo = is_array($b['previo'] ?? null) ? $b['previo'] : null;
    try { $r = ia_preguntar($pregunta, $previo); } catch (Throwable $e) { json_fail($e->getMessage()); }
    if (isset($r['mensaje'])) ia_out(['mensaje' => $r['mensaje']]);
    q('INSERT INTO ia_consultas (usuario_id,pregunta,titulo,sql_texto,grafico) VALUES (?,?,?,?,?)', [$uid, $pregunta, $r['titulo'], $r['sql'], json_encode($r['grafico'])]);
    $id = (int)db()->lastInsertId();
    q('DELETE FROM ia_consultas WHERE usuario_id=? AND favorito=0 AND id NOT IN (SELECT id FROM (SELECT id FROM ia_consultas WHERE usuario_id=? AND favorito=0 ORDER BY id DESC LIMIT 60) x)', [$uid, $uid]);
    audit('IA', 'consulta', $id, mb_substr($pregunta, 0, 200));
    ia_out(['id' => $id] + $r);

case 'ejecutar':    // volver a correr una consulta guardada, sin gastar IA
    $c = ia_consulta((int)(body()['id'] ?? 0), $uid);
    try { $r = ia_ejecutar($c['sql_texto']); } catch (Throwable $e) { json_fail($e->getMessage()); }
    q('UPDATE ia_consultas SET veces=veces+1, ultimo_uso=NOW() WHERE id=?', [$c['id']]);
    ia_out(['id' => (int)$c['id'], 'pregunta' => $c['pregunta'], 'titulo' => $c['titulo'] ?: $c['pregunta'], 'grafico' => json_decode((string)$c['grafico'], true) ?: ['tipo' => 'none'], 'favorito' => (int)$c['favorito']] + $r);

case 'resumir':
    if (!ia_cfg()['resumen']) json_fail('El resumen está desactivado.');
    $c = ia_consulta((int)(body()['id'] ?? 0), $uid);
    try { $r = ia_ejecutar($c['sql_texto']); if (!$r['filas']) ia_out(['resumen' => 'La consulta no devolvió resultados para ese período.']); $t = ia_resumir($c['pregunta'], $r['columnas'], $r['filas']); } catch (Throwable $e) { json_fail($e->getMessage()); }
    ia_out(['resumen' => $t]);

case 'favorito':
    $c = ia_consulta((int)(body()['id'] ?? 0), $uid); $v = !empty(body()['valor']) ? 1 : 0;
    q('UPDATE ia_consultas SET favorito=? WHERE id=?', [$v, $c['id']]); ia_out(['favorito' => $v]);

case 'borrar':
    $c = ia_consulta((int)(body()['id'] ?? 0), $uid); q('DELETE FROM ia_consultas WHERE id=?', [$c['id']]); ia_out([]);

case 'csv':
    $c = ia_consulta((int)($_GET['id'] ?? 0), $uid);
    try { $r = ia_ejecutar($c['sql_texto']); } catch (Throwable $e) { http_response_code(400); exit($e->getMessage()); }
    $name = 'consulta_' . preg_replace('/[^a-z0-9]+/i', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)($c['titulo'] ?: 'reporte'))) . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    $safe = fn($v) => is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;   // evita fórmulas en Excel
    fputcsv($o, $r['columnas'], ';'); foreach ($r['filas'] as $f) fputcsv($o, array_map($safe, $f), ';');
    audit('IA', 'consulta', (int)$c['id'], 'Exportó CSV'); exit;

case 'esquema':
    header('Content-Type: text/plain; charset=utf-8'); echo ia_esquema_texto(); exit;
}
json_fail('Acción no válida');
