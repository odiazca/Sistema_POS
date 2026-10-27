<?php
/**
 * Seguridad · SysTienda
 * Endurecimiento de sesión/cabeceras, política de contraseñas y revisión de seguridad.
 */

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 0) == 443);
}

/** Se ejecuta antes de session_start() */
function seg_iniciar(): void
{
    if (APP_ENV === 'produccion') { ini_set('display_errors', '0'); ini_set('log_errors', '1'); error_reporting(E_ALL); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        if (es_https() && APP_ENV === 'produccion') header('Strict-Transport-Security: max-age=15552000');
    }
}

const CLAVES_DEBILES = ['123456', '1234567', '12345678', '123456789', 'password', 'contrasena', 'admin', 'admin123', 'cajero123', 'qwerty', '111111', '000000', 'abc123', 'tienda123', 'systienda', 'sys_tienda'];

/** true si la contraseña es una de las conocidas/de demostración o igual al usuario */
function clave_conocida(string $p, string $usuario = ''): bool
{
    $l = mb_strtolower($p);
    return in_array($l, CLAVES_DEBILES, true) || ($usuario !== '' && $l === mb_strtolower($usuario));
}

/** Devuelve null si es válida o el mensaje de error */
function validar_clave(string $p, string $usuario = ''): ?string
{
    if (mb_strlen($p) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]/u', $p) || !preg_match('/\d/', $p)) return 'La contraseña debe combinar letras y números.';
    if (clave_conocida($p, $usuario)) return 'Esa contraseña es muy común o fácil de adivinar. Elige otra.';
    if ($usuario !== '' && mb_stripos($p, $usuario) !== false) return 'La contraseña no debe contener tu nombre de usuario.';
    return null;
}

/** Bloqueo por IP: demasiados intentos fallidos recientes */
function ip_bloqueada(): bool
{
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM auditoria WHERE accion='LOGIN_FALLIDO' AND ip=? AND fecha > (NOW() - INTERVAL 15 MINUTE)");
        $st->execute([$_SERVER['REMOTE_ADDR'] ?? '']);
        return (int)$st->fetchColumn() >= 20;
    } catch (Throwable $e) { return false; }
}
function registrar_fallo_login(string $usuario): void
{
    try { db()->prepare("INSERT INTO auditoria (usuario,accion,detalle,ip) VALUES (?,?,?,?)")->execute([mb_substr($usuario, 0, 40), 'LOGIN_FALLIDO', 'Intento de acceso fallido', $_SERVER['REMOTE_ADDR'] ?? null]); } catch (Throwable $e) {}
}

/** Revisión de seguridad para el panel de administración: [estado ok|warn|bad, título, detalle] */
function seg_revisar(): array
{
    $r = []; $add = function ($est, $t, $d) use (&$r) { $r[] = ['estado' => $est, 'titulo' => $t, 'detalle' => $d]; };
    $root = dirname(__DIR__);

    $add(APP_ENV === 'produccion' ? 'ok' : 'warn', 'Entorno de la aplicación', APP_ENV === 'produccion' ? 'Modo producción: errores ocultos, instalador bloqueado.' : 'Estás en modo «local». Antes de publicar en internet cambia APP_ENV a «produccion» en config/local.php.');
    $add(es_https() ? 'ok' : (APP_ENV === 'produccion' ? 'bad' : 'warn'), 'Conexión cifrada (HTTPS)', es_https() ? 'Estás usando HTTPS.' : 'Esta conexión no usa HTTPS. En internet es obligatorio: sin HTTPS las contraseñas viajan en texto plano.');

    $demo = [];   // solo se prueban las contraseñas de demostración (bcrypt es lento a propósito)
    foreach (db()->query('SELECT usuario,password,cambiar_clave FROM usuarios WHERE estado=1')->fetchAll() as $u) {
        if ($u['cambiar_clave']) { $demo[] = $u['usuario'] . ' (pendiente de cambio)'; continue; }
        foreach (['admin123', 'cajero123', $u['usuario']] as $c) if (password_verify($c, $u['password'])) { $demo[] = $u['usuario']; break; }
    }
    $add($demo ? 'bad' : 'ok', 'Contraseñas de demostración', $demo ? 'Estos usuarios aún tienen una contraseña conocida: ' . implode(', ', $demo) . '. Cámbiala en Usuarios y roles o desde tu perfil.' : 'Ningún usuario activo usa una contraseña de demostración.');

    $add((DB_USER === 'root' && DB_PASS === '') ? (APP_ENV === 'produccion' ? 'bad' : 'warn') : 'ok', 'Base de datos', (DB_USER === 'root' && DB_PASS === '') ? 'Se conecta como «root» sin contraseña. Crea un usuario dedicado y colócalo en config/local.php (ver config/local.example.php).' : 'Usa credenciales propias.');

    $lock = is_file($root . '/config/installed.lock');
    $add($lock ? 'ok' : (APP_ENV === 'produccion' ? 'bad' : 'warn'), 'Instalador (install.php)', $lock ? 'Instalador bloqueado: no puede reinstalar ni borrar datos.' : 'install.php puede reinstalar el sistema y borrar los datos. En producción elimina el archivo o activa el modo producción.');

    $ba = is_file($root . '/.htaccess') && is_file($root . '/backups/.htaccess');
    $add($ba ? 'ok' : 'warn', 'Carpetas protegidas', $ba ? 'config, includes, database y backups bloqueadas por .htaccess (Apache).' : 'Faltan archivos .htaccess. Si usas Nginx debes bloquear config/, includes/, database/ y backups/ en su configuración.');

    $ub = $root . '/assets/uploads/productos/.htaccess';
    $add(is_file($ub) ? 'ok' : 'warn', 'Subida de imágenes', is_file($ub) ? 'La carpeta de fotos no ejecuta código PHP.' : 'Falta el .htaccess de la carpeta de fotos.');

    $add(ini_get('display_errors') ? (APP_ENV === 'produccion' ? 'bad' : 'warn') : 'ok', 'Mostrar errores en pantalla', ini_get('display_errors') ? 'PHP muestra errores al usuario (revela rutas y datos). Desactiva display_errors en producción.' : 'Los errores no se muestran al usuario.');

    $ult = cfg('backup_ultimo', '');
    $add($ult && strtotime($ult) > strtotime('-3 days') ? 'ok' : 'warn', 'Copias de seguridad', $ult ? 'Último respaldo: ' . $ult : 'Todavía no hay respaldos. Actívalos en la pestaña «Respaldos».');
    return $r;
}
