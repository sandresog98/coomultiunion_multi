<?php
declare(strict_types=1);

/**
 * Health check de base de datos para CLI (test/producción).
 * Uso: php scripts/health_db.php
 * Salida: 0 = OK, 1 = error de conexión/consulta.
 */

require_once dirname(__DIR__) . '/core/database.php';

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $name = $pdo->query('SELECT DATABASE()')->fetchColumn();
    fwrite(STDOUT, "OK base de datos: " . (string) $name . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR base de datos: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
