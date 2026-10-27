<?php
/** Copias de seguridad: crear, listar, descargar, restaurar, programar y restablecer el sistema */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';
$a = $_GET['a'] ?? (body()['a'] ?? $_POST['a'] ?? 'list');
api_guard([1], !in_array($a, ['list', 'directa', 'descargar', 'resumen'], true));
$pdo = db();

/** Confirma la identidad del administrador antes de una acción destructiva */
function bk_confirmar(array $b, string $palabra): void {
    $h = q('SELECT password FROM usuarios WHERE id=?', [usuario()['id']])->fetchColumn();
    if (!password_verify((string)($b['password'] ?? ''), $h)) json_fail('Contraseña de administrador incorrecta.');
    if (strtoupper(trim((string)($b['confirmar'] ?? ''))) !== $palabra) json_fail("Escribe $palabra para confirmar.");
}
function bk_resumen(PDO $pdo): array {
    $n = fn($t) => (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    return ['ventas' => $n('ventas'), 'productos' => $n('productos'), 'clientes' => max(0, $n('clientes') - 1), 'proveedores' => $n('proveedores'),
            'compras' => $n('compras'), 'usuarios' => $n('usuarios'), 'sucursales' => $n('sucursales'), 'cajas' => $n('cajas')];
}

switch ($a) {
case 'list':
    $k = ['backup_auto' => '1', 'backup_horas' => '24', 'backup_conservar' => '14', 'backup_copia' => '', 'backup_ultimo' => ''];
    $cf = []; foreach ($k as $kk => $d) $cf[$kk] = cfg_fresh($kk, $d);
    $dir = backup_dir(); $libre = @disk_free_space($dir); $lista = backup_listar();
    $db = $pdo->query("SELECT ROUND(SUM(data_length+index_length)/1024) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
    json_out(['ok' => true, 'cfg' => $cf, 'lista' => $lista, 'total_bytes' => array_sum(array_column($lista, 'bytes')), 'db_kb' => (int)$db,
              'dir' => $dir, 'libre' => $libre === false ? null : $libre, 'gzip' => function_exists('gzopen'), 'resumen' => bk_resumen($pdo)]);

case 'directa':   // descarga sin guardar en el servidor
    $name = 'sys_tienda_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8'); header("Content-Disposition: attachment; filename=\"$name\"");
    audit('RESPALDO', 'respaldo', null, 'Descarga directa');
    backup_volcar($pdo, function ($s) { echo $s; }); exit;

case 'crear':
    try { $n = backup_crear('manual'); } catch (Throwable $e) { json_fail('No se pudo crear el respaldo: ' . $e->getMessage(), 500); }
    audit('RESPALDO', 'respaldo', null, 'Manual: ' . $n); json_out(['ok' => true, 'nombre' => $n]);

case 'descargar':
    $p = backup_ruta((string)($_GET['n'] ?? '')); if (!$p) json_fail('Respaldo no encontrado', 404);
    audit('RESPALDO', 'respaldo', null, 'Descarga: ' . basename($p));
    header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . basename($p) . '"'); header('Content-Length: ' . filesize($p)); readfile($p); exit;

case 'borrar':
    $p = backup_ruta((string)(body()['n'] ?? '')); if (!$p) json_fail('Respaldo no encontrado', 404);
    @unlink($p); audit('RESPALDO', 'respaldo', null, 'Eliminado: ' . basename($p)); json_out(['ok' => true]);

case 'subir':
    $f = $_FILES['archivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) json_fail('No se pudo recibir el archivo (tamaño máximo permitido por PHP: ' . ini_get('upload_max_filesize') . ').');
    $gzf = preg_match('/\.sql\.gz$/i', $f['name']); if (!$gzf && !preg_match('/\.sql$/i', $f['name'])) json_fail('El archivo debe ser .sql o .sql.gz');
    $dest = 'sys_tienda_' . date('Ymd_His') . '_subido' . ($gzf ? '.sql.gz' : '.sql');
    if (!move_uploaded_file($f['tmp_name'], backup_dir() . '/' . $dest)) json_fail('No se pudo guardar el archivo.');
    audit('RESPALDO', 'respaldo', null, 'Subido: ' . $dest); json_out(['ok' => true, 'nombre' => $dest]);

case 'restaurar':
    $b = body(); bk_confirmar($b, 'RESTAURAR');
    $p = backup_ruta((string)($b['n'] ?? '')); if (!$p) json_fail('Respaldo no encontrado', 404);
    try { $n = backup_restaurar($p); } catch (Throwable $e) { json_fail('Falló la restauración: ' . $e->getMessage() . ' (se creó un respaldo previo por seguridad).', 500); }
    audit('RESPALDO', 'respaldo', null, 'Restaurado: ' . basename($p)); json_out(['ok' => true, 'sentencias' => $n]);

case 'guardar':
    $in = body()['cfg'] ?? [];
    $horas = (int)($in['backup_horas'] ?? 24); if (!in_array($horas, [1, 3, 6, 12, 24, 48, 168], true)) json_fail('Frecuencia no válida.');
    $keep = (int)($in['backup_conservar'] ?? 14); if ($keep < 3 || $keep > 365) json_fail('Conservar entre 3 y 365 respaldos.');
    $ext = trim((string)($in['backup_copia'] ?? ''));
    if ($ext !== '' && (!is_dir($ext) || !is_writable($ext))) json_fail('La carpeta de copia externa no existe o no se puede escribir en ella: ' . $ext);
    backup_set('backup_auto', !empty($in['backup_auto']) && $in['backup_auto'] !== '0' ? '1' : '0'); backup_set('backup_horas', (string)$horas); backup_set('backup_conservar', (string)$keep); backup_set('backup_copia', $ext);
    audit('CONFIG', 'respaldo', null, 'Programación de respaldos'); json_out(['ok' => true]);

case 'resetear':
    $b = body(); $alc = $b['alcance'] ?? '';
    if (!in_array($alc, ['movimientos', 'catalogo', 'empresa_nueva'], true)) json_fail('Elige qué deseas borrar.');
    bk_confirmar($b, $alc === 'empresa_nueva' ? 'EMPRESA NUEVA' : 'BORRAR');
    try { $previo = backup_crear('previo'); } catch (Throwable $e) { json_fail('No se pudo crear el respaldo de seguridad previo; no se borró nada: ' . $e->getMessage(), 500); }
    $yo = (int)usuario()['id'];
    $mov = ['detalle_devoluciones', 'devoluciones', 'detalle_cotizaciones', 'cotizaciones', 'detalle_traspasos', 'traspasos', 'movimientos_puntos', 'detalle_ventas', 'ventas', 'detalle_compras', 'compras', 'movimientos_inventario', 'movimientos_caja', 'cajas', 'gastos', 'pagos_clientes', 'auditoria'];
    $cat = ['promociones', 'stock_sucursal', 'productos', 'categorias', 'marcas', 'proveedores'];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($mov as $t) $pdo->exec("TRUNCATE TABLE `$t`");
        if ($alc === 'movimientos') { $pdo->exec('UPDATE stock_sucursal SET stock=0'); $pdo->exec('UPDATE productos SET stock=0'); }
        else {
            foreach ($cat as $t) $pdo->exec("TRUNCATE TABLE `$t`");
            $pdo->exec('DELETE FROM clientes WHERE id>1'); $pdo->exec('ALTER TABLE clientes AUTO_INCREMENT=2');
            foreach (glob(__DIR__ . '/../assets/uploads/productos/*') ?: [] as $f) if (is_file($f) && basename($f) !== '.htaccess') @unlink($f);
        }
        $pdo->exec('UPDATE series_comprobantes SET correlativo=0'); $pdo->exec('UPDATE clientes SET saldo_deuda=0, puntos=0');
        if ($alc === 'empresa_nueva') {
            try { $pdo->exec('TRUNCATE TABLE ia_consultas'); } catch (Throwable $e) {}
            $pdo->exec('DELETE FROM usuarios WHERE id<>' . $yo);
            $pdo->exec('DELETE FROM series_comprobantes WHERE sucursal_id<>1'); $pdo->exec('DELETE FROM sucursales WHERE id<>1');
            $pdo->exec('UPDATE usuarios SET sucursal_id=1 WHERE id=' . $yo);
            $pdo->prepare('UPDATE sucursales SET nombre=?, direccion=NULL, telefono=NULL WHERE id=1')->execute(['Sucursal Principal']);
            $dir = __DIR__ . '/../assets/uploads/empresa/'; $lg = cfg_fresh('empresa_logo', ''); if ($lg && is_file($dir . $lg)) @unlink($dir . $lg);
            foreach (['empresa_nombre' => 'Mi Empresa', 'empresa_razon_social' => '', 'empresa_ruc' => '', 'empresa_direccion' => '', 'empresa_telefono' => '', 'empresa_email' => '', 'empresa_web' => '', 'empresa_eslogan' => '', 'empresa_logo' => '',
                     'meta_diaria' => '0', 'ticket_mensaje' => '¡Gracias por su compra!', 'puntos_cada' => '10', 'puntos_valor' => '0.10', 'cumple_descuento' => '0'] as $k => $v) backup_set($k, $v);
        }
    } catch (Throwable $e) { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); json_fail('Error al restablecer: ' . $e->getMessage() . ' Puedes recuperar todo con el respaldo ' . $previo, 500); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    if ($alc === 'empresa_nueva') $_SESSION['suc'] = 1;
    audit('RESPALDO', 'reset', null, 'Restablecimiento: ' . $alc . ' (respaldo previo ' . $previo . ')');
    json_out(['ok' => true, 'previo' => $previo, 'alcance' => $alc]);
}
json_fail('Acción no válida');
