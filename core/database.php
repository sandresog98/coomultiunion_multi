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

    $pdo = null;
    $ultimoError = null;
    // Reintentos con backoff exponencial acotado para absorber microcortes
    // transitorios de la BD (p. ej. "connection refused" 2002/2003).
    $intentos = 4;
    for ($intento = 0; $intento < $intentos; $intento++) {
        try {
            $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5, // timeout de conexión (segundos)
            ]);
            break;
        } catch (PDOException $e) {
            $ultimoError = $e;
            if ($intento < $intentos - 1) {
                // 150 ms, 300 ms, 600 ms (backoff acotado + jitter).
                $espera = 150000 * (2 ** $intento);
                usleep($espera + random_int(0, 50000));
            }
        }
    }
    if (!$pdo instanceof PDO) {
        throw $ultimoError ?? new PDOException('No se pudo conectar a la base de datos.');
    }

    // Alinear la collation de la conexion con la de las tablas.
    // En hosting el default del servidor puede ser utf8mb4_general_ci mientras
    // las tablas/vistas son utf8mb4_unicode_ci; los parametros de texto (LIKE ?)
    // fallan con "Illegal mix of collations" (1267). Se toma la collation real
    // de una tabla base (local: uca1400, dev: unicode_ci) y, si no existe, la
    // collation por defecto de la base.
    try {
        $pdo->exec('SET NAMES ' . $charset);
        $collation = $pdo->query(
            "SELECT TABLE_COLLATION FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sifone_asociados' LIMIT 1"
        )->fetchColumn();
        if (!$collation) {
            $collation = $pdo->query('SELECT @@collation_database')->fetchColumn();
        }
        if ($collation && preg_match('/^[A-Za-z0-9_]+$/', (string) $collation)) {
            $pdo->exec('SET collation_connection = ' . $collation);
        }
    } catch (Throwable $e) {
        error_log('[db] no se pudo ajustar collation_connection: ' . $e->getMessage());
    }

    return $pdo;
}
