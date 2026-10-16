<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Rate-limit simple de intentos de login (mitiga fuerza bruta).
 * Clave por IP + identificador; ventana deslizante con límite por defecto 5/15 min.
 * Nunca interrumpe el flujo si la tabla aún no existe (errores solo a error_log).
 */
final class RateLimit
{
    /** ¿Debe bloquearse este intento? (true = bloqueado). */
    public static function bloqueado(string $clave, int $max = 5, int $ventana = 900): bool
    {
        $corte = date('Y-m-d H:i:s', time() - $ventana);
        try {
            $st = db()->prepare('SELECT COUNT(*) FROM login_intentos WHERE clave = ? AND intento_at >= ?');
            $st->execute([$clave, $corte]);
            return (int) $st->fetchColumn() >= $max;
        } catch (Throwable $e) {
            error_log('[RateLimit] ' . $e->getMessage());
            return false; // deploy parcial (tabla ausente): no bloquear
        }
    }

    /** Registra un intento fallido. */
    public static function registrar(string $clave): void
    {
        try {
            db()->prepare('INSERT INTO login_intentos (clave, intento_at) VALUES (?, NOW())')->execute([$clave]);
        } catch (Throwable $e) {
            error_log('[RateLimit] ' . $e->getMessage());
        }
    }
}
