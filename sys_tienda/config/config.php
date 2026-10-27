<?php
/**
 * Configuración general · SYS_TIENDA
 */
// Ajustes propios del servidor: crea config/local.php (ver local.example.php). Tiene prioridad y las
// actualizaciones del sistema nunca lo sobrescriben.
if (is_file(__DIR__ . '/local.php')) require __DIR__ . '/local.php';

// Entorno: 'local' (Laragon, pruebas) o 'produccion' (servidor en internet: errores ocultos, cookies seguras, instalador bloqueado)
if (!defined('APP_ENV')) define('APP_ENV', 'local');
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');          // <- contraseña de MySQL
if (!defined('DB_NAME')) define('DB_NAME', 'sys_tienda');
if (!defined('DB_PORT')) define('DB_PORT', 3306);

define('APP_NAME', 'SysTienda');
define('APP_VERSION', '1.8.0');
// Versión de assets automática: cambia cuando se modifica cualquier JS/CSS (evita caché obsoleta)
define('ASSET_V', (string)max(array_map('filemtime', array_merge(glob(__DIR__ . '/../assets/js/*.js') ?: [], glob(__DIR__ . '/../assets/css/*.css') ?: [])) ?: [0]));
if (!defined('TIMEZONE')) define('TIMEZONE', 'America/Lima');   // se puede cambiar desde Empresa → Preferencias

// Ruta base (detecta automáticamente la carpeta del proyecto)
$__base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$__base = preg_replace('#/(api|modules|includes)$#', '', $__base);
define('BASE_URL', $__base);

date_default_timezone_set(TIMEZONE);
