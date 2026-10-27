<?php
/**
 * Copia este archivo como  config/local.php  y ajusta los valores de TU servidor.
 * Las actualizaciones del sistema no tocan local.php.
 */
define('APP_ENV', 'produccion');          // 'local' en tu PC con Laragon · 'produccion' en un servidor en internet

// Usuario de base de datos DEDICADO (no uses root). Créalo así en MySQL:
//   CREATE USER 'sys_tienda'@'localhost' IDENTIFIED BY 'UNA-CLAVE-LARGA-Y-UNICA';
//   GRANT ALL PRIVILEGES ON sys_tienda.* TO 'sys_tienda'@'localhost'; FLUSH PRIVILEGES;
define('DB_HOST', 'localhost');
define('DB_USER', 'sys_tienda');
define('DB_PASS', 'UNA-CLAVE-LARGA-Y-UNICA');
define('DB_NAME', 'sys_tienda');
define('DB_PORT', 3306);
