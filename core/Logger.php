<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Auditoría → logs. Nunca interrumpe el flujo (errores solo a error_log).
 */
final class Logger
{
    public static function log(
        string $accion,
        string $modulo,
        string|int|null $idUsuario = null,
        ?string $detalle = null,
        ?array $antes = null,
        ?array $despues = null,
        string $nivel = 'info'
    ): void {
        try {
            $st = db()->prepare(
                "INSERT INTO logs
                   (id_usuario, accion, modulo, detalle, ip_address, user_agent, datos_anteriores, datos_nuevos, nivel)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $idUsuario, $accion, $modulo, $detalle,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
                $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
                $nivel,
            ]);
        } catch (Throwable $e) {
            error_log('[Logger] ' . $e->getMessage());
        }
    }

    public static function login(string|int|null $idUsuario, string $detalle, bool $ok, string $modulo = 'auth'): void
    {
        self::log('login', $modulo, $idUsuario, $detalle, null, null, $ok ? 'info' : 'warning');
    }

    /** Descarga/servido de un archivo o exportación de un reporte. */
    public static function descarga(string|int|null $idUsuario, string $modulo, string $detalle): void
    {
        self::log('descargar', $modulo, $idUsuario, $detalle);
    }
}
