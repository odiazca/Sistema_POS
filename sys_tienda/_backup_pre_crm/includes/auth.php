<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/format.php';

seg_iniciar();
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => es_https()]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(?string $t): bool
{
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function usuario(): ?array { return $_SESSION['user'] ?? null; }

/** Si la base de datos es de una versión anterior (sin sucursales), manda al instalador */
function verificar_esquema(bool $json): void
{
    try {
        db()->query('SELECT 1 FROM sucursales LIMIT 1');
        // Migración automática v1.2: gastos.estado (activar/desactivar en vez de eliminar)
        try { db()->query('SELECT estado FROM gastos LIMIT 1'); }
        catch (Throwable $e2) { db()->exec('ALTER TABLE gastos ADD COLUMN estado TINYINT(1) NOT NULL DEFAULT 1'); }
        // Migración automática v1.3: obligar cambio de contraseña
        try { db()->query('SELECT cambiar_clave FROM usuarios LIMIT 1'); }
        catch (Throwable $e3) { db()->exec('ALTER TABLE usuarios ADD COLUMN cambiar_clave TINYINT(1) NOT NULL DEFAULT 0'); }
        return;
    } catch (Throwable $e) {}
    $_SESSION = []; 
    if ($json) { http_response_code(409); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Base de datos desactualizada: ejecuta install.php']); }
    else header('Location: ' . BASE_URL . '/install.php?actualizar=1');
    exit;
}

function require_login(bool $json = false): void
{
    verificar_esquema($json);
    // Cierre de sesión por inactividad (minutos configurables; 480 = 8 h por defecto)
    if (usuario()) {
        $lim = 60 * max(5, (int)(db()->query("SELECT valor FROM configuracion WHERE clave='sesion_minutos'")->fetchColumn() ?: 480));
        if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > $lim) { $_SESSION = []; }
        else $_SESSION['ultima_actividad'] = time();
    }
    if (!usuario()) {
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No autenticado']);
        } else {
            header('Location: ' . BASE_URL . '/login.php');
        }
        exit;
    }
    // Contraseña de demostración o marcada para cambio: no deja usar el sistema hasta cambiarla
    if (!empty($_SESSION['forzar_clave']) && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['cambiar_clave.php', 'perfil.php', 'logout.php'], true)) {
        if ($json) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Debes cambiar tu contraseña antes de continuar.', 'forzar_clave' => true]); }
        else header('Location: ' . BASE_URL . '/cambiar_clave.php');
        exit;
    }
}

/** Valida credenciales con bloqueo temporal tras 5 intentos fallidos. */
function intentar_login(string $usuario, string $password): array
{
    if (ip_bloqueada()) return [false, 'Demasiados intentos desde este equipo. Espera 15 minutos e inténtalo de nuevo.'];
    $st = db()->prepare('SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.usuario = ? LIMIT 1');
    $st->execute([$usuario]);
    $u = $st->fetch();

    if (!$u || !$u['estado']) { registrar_fallo_login($usuario); return [false, 'Usuario o contraseña incorrectos.']; }

    if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        $min = ceil((strtotime($u['bloqueado_hasta']) - time()) / 60);
        return [false, "Cuenta bloqueada temporalmente. Intenta en $min min."];
    }

    if (!password_verify($password, $u['password'])) {
        registrar_fallo_login($usuario);
        $int = $u['intentos'] + 1;
        $bloq = $int >= 5 ? date('Y-m-d H:i:s', time() + 600) : null;
        db()->prepare('UPDATE usuarios SET intentos=?, bloqueado_hasta=? WHERE id=?')
            ->execute([$bloq ? 0 : $int, $bloq, $u['id']]);
        return [false, $bloq ? 'Demasiados intentos. Cuenta bloqueada 10 minutos.' : 'Usuario o contraseña incorrectos.'];
    }

    db()->prepare('UPDATE usuarios SET intentos=0, bloqueado_hasta=NULL, ultimo_acceso=NOW() WHERE id=?')->execute([$u['id']]);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$u['id'], 'usuario' => $u['usuario'], 'rol' => $u['rol'], 'rol_id' => (int)$u['rol_id'],
        'sucursal_id' => (int)$u['sucursal_id'],
        'nombre' => $u['nombres'] . ' ' . $u['apellidos'], 'iniciales' => mb_strtoupper(mb_substr($u['nombres'],0,1).mb_substr($u['apellidos'],0,1)),
    ];
    $_SESSION['suc'] = (int)$u['sucursal_id'];
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['forzar_clave'] = !empty($u['cambiar_clave']) || clave_conocida($password, $u['usuario']);
    db()->prepare('INSERT INTO auditoria (usuario_id,usuario,sucursal_id,accion,detalle,ip) VALUES (?,?,?,?,?,?)')
        ->execute([$u['id'], $u['usuario'], $u['sucursal_id'], 'LOGIN', 'Inicio de sesión', $_SERVER['REMOTE_ADDR'] ?? null]);
    return [true, ''];
}
