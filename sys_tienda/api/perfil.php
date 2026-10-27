<?php
require_once __DIR__ . '/../includes/helpers.php';
api_guard([], true);
$b = body();
$h = q('SELECT password FROM usuarios WHERE id=?', [usuario()['id']])->fetchColumn();
if (!password_verify($b['actual'] ?? '', $h)) json_fail('La contraseña actual es incorrecta.');
if (($b['nueva'] ?? '') === ($b['actual'] ?? '')) json_fail('La nueva contraseña debe ser distinta a la actual.');
if ($err = validar_clave((string)($b['nueva'] ?? ''), usuario()['usuario'])) json_fail($err);
q('UPDATE usuarios SET password=?, cambiar_clave=0 WHERE id=?', [password_hash($b['nueva'], PASSWORD_DEFAULT), usuario()['id']]);
unset($_SESSION['forzar_clave']);
audit('CLAVE', 'usuarios', usuario()['id'], 'Cambio de contraseña');
json_out(['ok' => true]);
