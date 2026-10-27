<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Zona horaria configurable (Empresa → Preferencias); por defecto la de config.php
            $tz = TIMEZONE;
            try { $v = $pdo->query("SELECT valor FROM configuracion WHERE clave='zona_horaria'")->fetchColumn(); if ($v && in_array($v, timezone_identifiers_list(), true)) $tz = $v; } catch (Throwable $e) {}
            date_default_timezone_set($tz);
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h3 style="font-family:sans-serif">No se pudo conectar a la base de datos.</h3>'
              . '<p style="font-family:sans-serif">Ejecuta primero <a href="' . BASE_URL . '/install.php">install.php</a> '
              . 'y revisa <code>config/config.php</code>.</p>');
        }
    }
    return $pdo;
}
