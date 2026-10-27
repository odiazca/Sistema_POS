<?php
/**
 * Respaldo por línea de comandos (para el Programador de tareas de Windows o cron de Linux).
 *   Windows:  C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\sys_tienda\backup_cron.php
 *   Linux:    0 2 * * *  php /var/www/sys_tienda/backup_cron.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/backup.php';
function cfg(string $k, string $d = ''): string { return cfg_fresh($k, $d); }
try { $n = backup_crear('auto'); echo "OK $n\n"; } catch (Throwable $e) { fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n"); exit(1); }
