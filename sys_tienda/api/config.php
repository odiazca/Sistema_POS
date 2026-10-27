<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';
$a = $_GET['a'] ?? (body()['a'] ?? 'get');
api_guard([1], !in_array($a, ['get', 'seguridad'], true));
$pdo = db();

switch ($a) {
case 'get':
    json_out(['ok' => true, 'cfg' => $pdo->query('SELECT clave,valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR), 'series' => $pdo->query('SELECT sc.*, s.nombre sucursal FROM series_comprobantes sc JOIN sucursales s ON s.id=sc.sucursal_id ORDER BY sc.sucursal_id, sc.id')->fetchAll()]);

case 'save':
    $in = body()['cfg'] ?? []; $ok = ['meta_diaria', 'puntos_cada', 'puntos_valor', 'cumple_descuento'];
    if (!is_numeric($in['meta_diaria'] ?? '') || $in['meta_diaria'] < 0) json_fail('La meta diaria no es válida.');
    foreach (['puntos_cada' => 'Soles por punto', 'puntos_valor' => 'Valor del punto', 'cumple_descuento' => '% de cumpleaños'] as $k => $lb) if (isset($in[$k]) && (!is_numeric($in[$k]) || $in[$k] < 0)) json_fail("«$lb» no es válido.");
    if (isset($in['puntos_cada']) && (float)$in['puntos_cada'] <= 0) json_fail('Los soles por punto deben ser mayores a 0.');
    $st = $pdo->prepare('INSERT INTO configuracion (clave,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)');
    foreach ($ok as $k) if (isset($in[$k])) $st->execute([$k, mb_substr(trim((string)$in[$k]), 0, 250)]);
    audit('CONFIG', 'configuracion', null, 'Meta y fidelización');
    json_out(['ok' => true]);

case 'print_save':
    $in = body()['cfg'] ?? [];
    if (!in_array($in['print_modo'] ?? '', ['navegador', 'red', 'local'], true)) json_fail('Modo de impresión no válido.');
    if (!in_array((string)($in['print_ancho'] ?? '80'), ['58', '80'], true)) json_fail('El ancho del papel debe ser 58 o 80 mm.');
    if (!in_array((string)($in['print_codepage'] ?? '19'), ['0', '16', '19'], true)) json_fail('Tabla de caracteres no válida.');
    $dest = trim((string)($in['print_destino'] ?? ''));
    if ($in['print_modo'] !== 'navegador' && $dest === '') json_fail('Indica la dirección de la impresora (IP o nombre compartido).');
    if ($in['print_modo'] === 'red' && !preg_match('/^[A-Za-z0-9.\-]+(:\d{2,5})?$/', $dest)) json_fail('La dirección de red debe ser como 192.168.1.50 o 192.168.1.50:9100.');
    foreach (['print_cajon', 'print_auto'] as $sw) $in[$sw] = !empty($in[$sw]) && $in[$sw] !== '0' ? '1' : '0';
    $in['print_destino'] = $dest;
    foreach (['print_modo', 'print_destino', 'print_ancho', 'print_codepage', 'print_cajon', 'print_auto'] as $k) backup_set($k, (string)($in[$k] ?? ''));
    audit('CONFIG', 'impresion', null, 'Impresora: ' . $in['print_modo'] . ' ' . $dest); json_out(['ok' => true]);

case 'series':
    audit('CONFIG', 'series', null, 'Series y correlativos');
    foreach (body()['series'] ?? [] as $s) {
        $serie = strtoupper(trim($s['serie'] ?? '')); if (!preg_match('/^[A-Z0-9]{3,4}$/', $serie)) json_fail('La serie debe tener 3-4 letras/números.');
        if ((int)($s['correlativo'] ?? -1) < 0) json_fail('Correlativo inválido.');
        try { q('UPDATE series_comprobantes SET serie=?, correlativo=? WHERE id=?', [$serie, (int)$s['correlativo'], (int)$s['id']]); }
        catch (PDOException $e) { json_fail('Esa serie ya existe para el tipo de comprobante.'); }
    }
    json_out(['ok' => true]);

case 'seguridad':
    json_out(['ok' => true, 'checks' => seg_revisar(), 'sesion_minutos' => cfg_fresh('sesion_minutos', '480')]);

case 'seg_guardar':
    $m = (int)(body()['sesion_minutos'] ?? 480); if ($m < 5 || $m > 1440) json_fail('El tiempo de inactividad debe estar entre 5 y 1440 minutos.');
    backup_set('sesion_minutos', (string)$m); audit('CONFIG', 'seguridad', null, 'Inactividad de sesión: ' . $m . ' min'); json_out(['ok' => true]);
}
json_fail('Acción no válida');
