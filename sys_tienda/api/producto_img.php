<?php
/** Foto del producto: subir (multipart) o quitar. JPG/PNG/WEBP hasta 1 MB. */
require_once __DIR__ . '/../includes/helpers.php';
api_guard([ROL_ADMIN, ROL_ALMACEN]);
$t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
if (!csrf_check($t)) json_fail('Sesión expirada. Recarga la página.', 419);
$id = (int)($_POST['id'] ?? 0);
$p = q('SELECT id,imagen,nombre FROM productos WHERE id=?', [$id])->fetch();
if (!$p) json_fail('Producto no encontrado.', 404);
$dir = __DIR__ . '/../assets/uploads/productos/';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

if (($_POST['a'] ?? '') === 'quitar') {
    if ($p['imagen'] && is_file($dir . $p['imagen'])) @unlink($dir . $p['imagen']);
    q('UPDATE productos SET imagen=NULL WHERE id=?', [$id]);
    audit('FOTO', 'producto', $id, 'Foto eliminada: ' . $p['nombre']);
    json_out(['ok' => true, 'imagen' => null]);
}
$f = $_FILES['foto'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_fail($f && $f['error'] === UPLOAD_ERR_INI_SIZE ? 'La imagen es demasiado grande.' : 'No se recibió la imagen.');
if ($f['size'] > 1024 * 1024) json_fail('La imagen supera 1 MB. Reduce su tamaño.');
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
if (!$ext || !@getimagesize($f['tmp_name'])) json_fail('Formato no permitido. Usa JPG, PNG o WEBP.');
$name = "p{$id}_" . bin2hex(random_bytes(4)) . ".$ext";
if (!move_uploaded_file($f['tmp_name'], $dir . $name)) json_fail('No se pudo guardar la imagen (revisa permisos de la carpeta assets/uploads).', 500);
if ($p['imagen'] && is_file($dir . $p['imagen'])) @unlink($dir . $p['imagen']);
q('UPDATE productos SET imagen=? WHERE id=?', [$name, $id]);
audit('FOTO', 'producto', $id, 'Foto actualizada: ' . $p['nombre']);
json_out(['ok' => true, 'imagen' => $name]);
