<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Conexión PDO única a MariaDB. Credenciales desde .env (nunca hardcodeadas).
 * Uso: $db = db();  $stmt = $db->prepare('... ?');  $stmt->execute([$x]);
 *
 * Siempre usar sentencias preparadas (nunca concatenar entrada del usuario).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $name    = env('DB_NAME', 'multi_app_v2');
    $charset = env('DB_CHARSET', 'utf8mb4');
    $socket  = env('DB_SOCKET', '');
    if ($socket !== '' && $socket !== null) {
        $dsn = "mysql:unix_socket=$socket;dbname=$name;charset=$charset";
    } else {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $dsn  = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";
    }

    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
