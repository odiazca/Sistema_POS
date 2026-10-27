<?php
/** Chat con IA para consultar el sistema (solo administrador). Historial en la sesión del servidor. */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';
require_once __DIR__ . '/../includes/ia.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'estado');
api_guard([1], $a !== 'estado');
function chat_out(array $d): void { header('Content-Type: application/json; charset=utf-8'); echo json_encode($d + ['ok' => true], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE); exit; }
$hist = $_SESSION['chat'] ?? [];

switch ($a) {
case 'estado':
    $c = ia_cfg();
    chat_out(['listo' => $c['url'] !== '' && $c['modelo'] !== '' && (!IA_PRESETS[$c['proveedor']]['clave'] || $c['clave'] !== ''),
              'proveedor' => IA_PRESETS[$c['proveedor']]['nombre'], 'empresa' => cfg('empresa_nombre', 'tu tienda'),
              'historial' => array_map(fn($h) => ['q' => $h['q'], 'a' => $h['a']], $hist)]);

case 'enviar':
    $msg = trim((string)(body()['mensaje'] ?? ''));
    if (mb_strlen($msg) < 2) json_fail('Escribe tu mensaje.');
    if (mb_strlen($msg) > 400) json_fail('El mensaje es demasiado largo (máx. 400 caracteres).');
    $now = time(); $_SESSION['chat_t'] = array_values(array_filter($_SESSION['chat_t'] ?? [], fn($t) => $t > $now - 600));
    if (count($_SESSION['chat_t']) >= 40) json_fail('Demasiados mensajes seguidos. Espera unos minutos.', 429);
    $_SESSION['chat_t'][] = $now;
    try { ia_instalar(); $r = ia_chat($msg, $hist); } catch (Throwable $e) { json_fail($e->getMessage()); }
    $hist[] = ['q' => $msg, 'sql' => $r['sql'] ?? null, 'a' => mb_substr($r['texto'], 0, 800)];
    $_SESSION['chat'] = array_slice($hist, -12);
    if (!empty($r['sql'])) audit('IA', 'chat', null, mb_substr($msg, 0, 200));
    chat_out(['texto' => $r['texto'], 'tabla' => $r['tabla'] ?? null, 'sql' => $r['sql'] ?? null]);

case 'limpiar':
    $_SESSION['chat'] = []; chat_out([]);
}
json_fail('Acción no válida');
