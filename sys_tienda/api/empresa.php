<?php
/** Empresa: datos, logo, moneda y formatos, impuestos y preferencias */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';   // backup_set()
$a = $_GET['a'] ?? (body()['a'] ?? $_POST['a'] ?? 'get');
api_guard([1], !in_array($a, ['get'], true));
$pdo = db();

const EMPRESA_KEYS = ['empresa_nombre', 'empresa_razon_social', 'empresa_ruc', 'empresa_direccion', 'empresa_telefono', 'empresa_email', 'empresa_web', 'empresa_eslogan',
    'moneda', 'moneda_codigo', 'moneda_decimales', 'sep_decimal', 'sep_miles', 'moneda_posicion', 'igv', 'impuesto_nombre',
    'zona_horaria', 'filas_pagina', 'vencimiento_dias', 'stock_minimo_defecto', 'ticket_mensaje', 'ticket_logo'];

switch ($a) {
case 'get':
    $c = $pdo->query('SELECT clave,valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
    $def = ['empresa_nombre' => '', 'moneda' => 'S/', 'moneda_codigo' => 'PEN', 'moneda_decimales' => '2', 'sep_decimal' => '.', 'sep_miles' => ',', 'moneda_posicion' => 'antes', 'igv' => '18', 'impuesto_nombre' => 'IGV',
            'zona_horaria' => TIMEZONE, 'filas_pagina' => '12', 'vencimiento_dias' => '30', 'stock_minimo_defecto' => '5', 'ticket_logo' => '1'];
    $cfg = []; foreach (EMPRESA_KEYS as $k) $cfg[$k] = (string)($c[$k] ?? ($def[$k] ?? ''));
    $zonas = array_values(array_filter(DateTimeZone::listIdentifiers(), fn($z) => preg_match('#^(America|Europe|Asia|Africa|Pacific|Atlantic|Australia)/#', $z)));
    json_out(['ok' => true, 'cfg' => $cfg, 'logo' => logo_url(), 'zonas' => $zonas, 'gd' => function_exists('imagecreatetruecolor')]);

case 'save':
    $in = body()['cfg'] ?? []; $v = [];
    foreach (EMPRESA_KEYS as $k) $v[$k] = trim((string)($in[$k] ?? ''));
    if ($v['empresa_nombre'] === '') json_fail('El nombre comercial de la empresa es obligatorio.');
    if ($v['empresa_ruc'] !== '' && !preg_match('/^[A-Za-z0-9\-]{5,20}$/', $v['empresa_ruc'])) json_fail('El RUC / NIT / RFC solo admite letras, números y guion (5-20 caracteres).');
    if ($v['empresa_email'] !== '' && !filter_var($v['empresa_email'], FILTER_VALIDATE_EMAIL)) json_fail('El correo de la empresa no es válido.');
    if ($v['empresa_web'] !== '' && !preg_match('#^[\w.\-/:]+\.[a-z]{2,}[\w\-./?=&%#]*$#i', $v['empresa_web'])) json_fail('La página web no es válida (ej. www.mitienda.com).');
    if ($v['moneda'] === '' || mb_strlen($v['moneda']) > 5) json_fail('El símbolo de la moneda es obligatorio (máx. 5 caracteres).');
    if (!preg_match('/^[A-Za-z]{3}$/', $v['moneda_codigo'])) json_fail('El código de moneda debe tener 3 letras (ej. PEN, USD, EUR).');
    $v['moneda_codigo'] = strtoupper($v['moneda_codigo']);
    if (!in_array($v['moneda_decimales'], ['0', '1', '2', '3'], true)) json_fail('Los decimales deben ser entre 0 y 3.');
    if (!in_array($v['sep_decimal'], ['.', ','], true)) json_fail('El separador decimal debe ser punto o coma.');
    if (!in_array($v['sep_miles'], [',', '.', 'espacio', 'ninguno'], true)) json_fail('Separador de miles no válido.');
    if (($v['sep_miles'] === ',' || $v['sep_miles'] === '.') && $v['sep_miles'] === $v['sep_decimal']) json_fail('El separador de miles no puede ser igual al separador decimal.');
    if (!in_array($v['moneda_posicion'], ['antes', 'despues'], true)) json_fail('Posición del símbolo no válida.');
    if (!is_numeric($v['igv']) || $v['igv'] < 0 || $v['igv'] > 30) json_fail('El impuesto debe estar entre 0 y 30 %.');
    if ($v['impuesto_nombre'] === '' || mb_strlen($v['impuesto_nombre']) > 10) json_fail('El nombre del impuesto es obligatorio (máx. 10 caracteres, ej. IGV, IVA).');
    if (!in_array($v['zona_horaria'], timezone_identifiers_list(), true)) json_fail('Zona horaria no válida.');
    if (!in_array($v['filas_pagina'], ['10', '12', '15', '20', '25', '50'], true)) json_fail('Filas por página no válido.');
    if (!ctype_digit($v['vencimiento_dias']) || $v['vencimiento_dias'] < 7 || $v['vencimiento_dias'] > 180) json_fail('Los días de alerta de vencimiento deben estar entre 7 y 180.');
    if (!is_numeric($v['stock_minimo_defecto']) || $v['stock_minimo_defecto'] < 0 || $v['stock_minimo_defecto'] > 100000) json_fail('El stock mínimo por defecto no es válido.');
    $v['ticket_logo'] = !empty($in['ticket_logo']) && $in['ticket_logo'] !== '0' ? '1' : '0';
    $v['ticket_mensaje'] = mb_substr($v['ticket_mensaje'], 0, 120);
    foreach ($v as $k => $val) backup_set($k, $val);
    audit('CONFIG', 'empresa', null, 'Datos de la empresa y formatos'); json_out(['ok' => true]);

case 'logo':
    $f = $_FILES['logo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_fail($f && $f['error'] === UPLOAD_ERR_INI_SIZE ? 'La imagen es demasiado grande.' : 'No se recibió la imagen.');
    if ($f['size'] > 2 * 1024 * 1024) json_fail('El logo supera 2 MB. Reduce su tamaño.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $info = $ext ? @getimagesize($f['tmp_name']) : false;
    if (!$ext || !$info) json_fail('Formato no permitido. Usa JPG, PNG o WEBP.');
    if ($info[0] < 40 || $info[1] < 40) json_fail('La imagen es demasiado pequeña (mínimo 40 × 40 px).');
    $dir = __DIR__ . '/../assets/uploads/empresa/'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'logo_' . bin2hex(random_bytes(4)) . '.' . $ext; $dest = $dir . $name; $ok = false;
    // Si hay GD se reduce a un máximo de 600 px de ancho/alto para que cargue rápido
    if (function_exists('imagecreatetruecolor') && ($info[0] > 600 || $info[1] > 600)) {
        $src = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$ext] ?? null;
        $im = $src && function_exists($src) ? @$src($f['tmp_name']) : false;
        if ($im) {
            $r = min(600 / $info[0], 600 / $info[1]); $w = max(1, (int)round($info[0] * $r)); $h = max(1, (int)round($info[1] * $r));
            $out = imagecreatetruecolor($w, $h); imagealphablending($out, false); imagesavealpha($out, true); imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
            $ok = $ext === 'png' ? imagepng($out, $dest, 6) : ($ext === 'webp' ? imagewebp($out, $dest, 88) : imagejpeg($out, $dest, 90));
        }
    }
    if (!$ok && !move_uploaded_file($f['tmp_name'], $dest)) json_fail('No se pudo guardar el logo (revisa permisos de assets/uploads).', 500);
    $old = cfg_fresh('empresa_logo'); if ($old && is_file($dir . $old)) @unlink($dir . $old);
    backup_set('empresa_logo', $name); audit('CONFIG', 'empresa', null, 'Logo actualizado');
    json_out(['ok' => true, 'logo' => BASE_URL . '/assets/uploads/empresa/' . $name . '?v=' . time()]);

case 'logo_quitar':
    $dir = __DIR__ . '/../assets/uploads/empresa/'; $old = cfg_fresh('empresa_logo'); if ($old && is_file($dir . $old)) @unlink($dir . $old);
    backup_set('empresa_logo', ''); audit('CONFIG', 'empresa', null, 'Logo eliminado'); json_out(['ok' => true]);
}
json_fail('Acción no válida');
