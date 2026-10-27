<?php
/**
 * Respaldos de la base de datos · SysTienda
 * Volcado SQL en PHP puro (no depende de mysqldump), comprimido con gzip cuando está disponible.
 */
const BACKUP_DIR_NAME = 'backups';

function backup_dir(): string
{
    $d = dirname(__DIR__) . '/' . BACKUP_DIR_NAME;
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "# Acceso directo bloqueado\nRequire all denied\n");
    if (!is_file($d . '/index.php')) @file_put_contents($d . '/index.php', "<?php http_response_code(403);\n");
    return $d;
}

/** Escribe el volcado SQL usando $w(string) */
function backup_volcar(PDO $pdo, callable $w): void
{
    $w("-- Respaldo SysTienda v" . APP_VERSION . " · " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $w("DROP TABLE IF EXISTS `$t`;\n" . $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1] . ";\n\n");
        $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_NUM); $buf = []; $n = 0;
        while ($r = $st->fetch()) {
            $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $r)) . ')';
            if (++$n % 200 === 0) { $w("INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n"); $buf = []; }
        }
        if ($buf) $w("INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n");
        $w("\n");
    }
    $w("SET FOREIGN_KEY_CHECKS=1;\n");
}

/** Crea un respaldo en la carpeta backups/. $tipo: manual | auto | previo. Devuelve el nombre del archivo. */
function backup_crear(string $tipo = 'manual'): string
{
    $gz = function_exists('gzopen');
    $t = time();   // si ya existe una copia en ese segundo, se usa el siguiente (no se pisa ninguna)
    do { $name = 'sys_tienda_' . date('Ymd_His', $t++) . '_' . $tipo . ($gz ? '.sql.gz' : '.sql'); $path = backup_dir() . '/' . $name; } while (file_exists($path));
    $h = $gz ? gzopen($path, 'wb6') : fopen($path, 'wb');
    if (!$h) throw new RuntimeException('No se pudo escribir en la carpeta de respaldos.');
    try { backup_volcar(db(), function ($s) use ($h, $gz) { $gz ? gzwrite($h, $s) : fwrite($h, $s); }); }
    catch (Throwable $e) { $gz ? gzclose($h) : fclose($h); @unlink($path); throw $e; }
    $gz ? gzclose($h) : fclose($h);

    // Copia en una carpeta externa (USB, nube sincronizada, otra PC en red…)
    $ext = trim(cfg_fresh('backup_copia'));
    if ($ext !== '' && is_dir($ext) && is_writable($ext)) @copy($path, rtrim($ext, "/\\") . DIRECTORY_SEPARATOR . $name);

    backup_set('backup_ultimo', date('Y-m-d H:i:s'));
    backup_rotar();
    return $name;
}

/** Conserva solo los N respaldos automáticos más recientes (los manuales y previos a restaurar no se tocan) */
function backup_rotar(): void
{
    $keep = max(3, (int)cfg_fresh('backup_conservar', '14'));
    $auto = array_values(array_filter(backup_listar(), fn($b) => $b['tipo'] === 'auto'));
    foreach (array_slice($auto, $keep) as $b) @unlink(backup_dir() . '/' . $b['nombre']);
}

function backup_listar(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/sys_tienda_*.sql*') ?: [] as $f) {
        if (!preg_match('/^sys_tienda_(\d{8})_(\d{6})_(manual|auto|previo|subido)\.sql(\.gz)?$/', basename($f), $m)) continue;
        $out[] = ['nombre' => basename($f), 'tipo' => $m[3], 'fecha' => date('Y-m-d H:i:s', strtotime($m[1] . $m[2])), 'bytes' => filesize($f)];
    }
    usort($out, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    return $out;
}

function backup_ruta(string $nombre): ?string
{
    if (!preg_match('/^sys_tienda_\d{8}_\d{6}_(manual|auto|previo|subido)\.sql(\.gz)?$/', $nombre)) return null;
    $p = backup_dir() . '/' . $nombre;
    return is_file($p) ? $p : null;
}

/** Restaura un archivo .sql / .sql.gz. Antes crea un respaldo «previo» por seguridad. */
function backup_restaurar(string $ruta): int
{
    backup_crear('previo');
    $gz = substr($ruta, -3) === '.gz';
    $h = $gz ? gzopen($ruta, 'rb') : fopen($ruta, 'rb');
    if (!$h) throw new RuntimeException('No se pudo leer el archivo de respaldo.');
    $pdo = db(); $stmt = ''; $n = 0; $ok = false;
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        while (($line = $gz ? gzgets($h) : fgets($h)) !== false) {
            if ($stmt === '' && (trim($line) === '' || str_starts_with($line, '--'))) { if (str_contains($line, 'Respaldo SysTienda')) $ok = true; continue; }
            $stmt .= $line;
            if (preg_match('/;\s*$/', $line)) { $pdo->exec($stmt); $stmt = ''; $n++; }
        }
        if (!$ok) throw new RuntimeException('El archivo no parece un respaldo de SysTienda.');
    } finally { $gz ? gzclose($h) : fclose($h); try { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $e) {} }
    return $n;
}

function cfg_fresh(string $k, string $d = ''): string
{
    $st = db()->prepare('SELECT valor FROM configuracion WHERE clave=?'); $st->execute([$k]); $v = $st->fetchColumn();
    return $v === false ? $d : (string)$v;
}
function backup_set(string $k, string $v): void
{
    db()->prepare('INSERT INTO configuracion (clave,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)')->execute([$k, mb_substr($v, 0, 250)]);
}

/** Respaldo automático sin cron: se ejecuta al final de una petición cuando toca (según la frecuencia configurada). */
function backup_auto_si_toca(): void
{
    try {
        if (cfg_fresh('backup_auto', '1') !== '1') return;
        $horas = max(1, (int)cfg_fresh('backup_horas', '24'));
        $ult = cfg_fresh('backup_ultimo', '');
        if ($ult !== '' && strtotime($ult) > time() - $horas * 3600) return;
        // «Reserva» el turno antes de empezar para que dos peticiones simultáneas no dupliquen el respaldo
        $st = db()->prepare("UPDATE configuracion SET valor=? WHERE clave='backup_ultimo' AND valor=?");
        if ($ult === '') backup_set('backup_ultimo', date('Y-m-d H:i:s'));
        else { $st->execute([date('Y-m-d H:i:s'), $ult]); if (!$st->rowCount()) return; }
        backup_crear('auto');
    } catch (Throwable $e) { error_log('Respaldo automático falló: ' . $e->getMessage()); }
}

/** Revisa (como máximo cada 10 min por sesión) si toca un respaldo automático y lo hace al terminar de responder la página */
function backup_hook(): void
{
    if (!usuario() || ($_SESSION['bk_chk'] ?? 0) > time() - 600) return;
    $_SESSION['bk_chk'] = time();
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        ignore_user_abort(true); backup_auto_si_toca();
    });
}
