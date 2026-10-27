<?php
require_once __DIR__ . '/../includes/helpers.php';
api_guard([ROL_ADMIN], true);
$id = (int)(body()['id'] ?? -1);
if ($id !== 0 && !q('SELECT 1 FROM sucursales WHERE id=? AND estado=1', [$id])->fetch()) json_fail('Sucursal no válida.');
$_SESSION['suc'] = $id;
audit('CAMBIO_SUCURSAL', 'sucursal', $id ?: null, $id ? 'Sucursal activa: ' . suc_nombre() : 'Vista consolidada');
json_out(['ok' => true]);
