<?php
declare(strict_types=1);

require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Logger.php';

/**
 * Utilidades HTTP para endpoints API (JSON). API-first (regla 7).
 */
final class Http
{
    /** Cuerpo JSON o form-data como array asociativo. */
    public static function input(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST ?: [];
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function requireMethod(string $method): void
    {
        if (self::method() !== strtoupper($method)) {
            self::json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }
    }

    /**
     * @param mixed $data
     * @param array<string,string> $headers
     */
    public static function json($data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok($data = null, int $status = 200): void
    {
        self::json(['ok' => true, 'data' => $data], $status);
    }

    /** @param array<string,string> $headers */
    public static function fail(string $error, int $status = 400, array $headers = []): void
    {
        self::json(['ok' => false, 'error' => $error], $status, $headers);
    }

    /**
     * 503 de disponibilidad transitoria con Retry-After: el cliente (o el
     * navegador) reintenta con backoff en lugar de rendirse de inmediato.
     */
    public static function failRetry(string $error, int $status = 503, int $retryAfter = 2): void
    {
        self::fail($error, $status, ['Retry-After' => (string) max(1, $retryAfter)]);
    }

    /**
     * ¿El error es una caída de conexión a la base de datos?
     * Permite responder 503 en lugar de un 500 genérico.
     */
    public static function isDbUnavailable(Throwable $e): bool
    {
        if (!$e instanceof PDOException) {
            return false;
        }
        $msg = $e->getMessage();
        return str_contains($msg, '2002')
            || str_contains($msg, '2003')
            || str_contains($msg, '2006')
            || str_contains($msg, '2013')
            || stripos($msg, 'connection refused') !== false
            || stripos($msg, 'No se puede establecer') !== false
            || stripos($msg, 'Connection timed out') !== false
            || stripos($msg, 'gone away') !== false
            || stripos($msg, 'Lost connection') !== false;
    }

    /**
     * Guard de operación CRUD para endpoints mutadores.
     * - $op null → deriva del método (POST=crear, PUT/PATCH=editar, DELETE=eliminar).
     * - $op explícito para verbos semánticos (toggle, exportar, asignar, procesar…).
     * Corta con 401/403 según 'acciones' (deny-by-default).
     */
    public static function crud(string $clave, ?string $op = null): void
    {
        if ($op !== null) {
            Auth::requireAccion($clave . '.' . $op);
            return;
        }
        $verbo = match (self::method()) {
            'PUT', 'PATCH' => 'editar',
            'DELETE'      => 'eliminar',
            default       => 'crear',
        };
        Auth::requireAccion($clave . '.' . $verbo);
    }

    /**
     * Protección CSRF: en métodos mutadores (POST/PUT/PATCH/DELETE) exige el token de
     * sesión (cabecera X-CSRF-Token o campo _csrf). No-op en lecturas (GET/HEAD/OPTIONS).
     */
    public static function csrf(): void
    {
        $m = self::method();
        if ($m === 'GET' || $m === 'HEAD' || $m === 'OPTIONS') {
            return;
        }
        // Bearer token: no hay sesión/cookie que proteger (CSRF no aplica).
        if (class_exists('TokenAuth', false) && TokenAuth::active()) {
            return;
        }
        if (!Csrf::verify()) {
            Logger::log('csrf_rechazado', 'seguridad', null,
                'Token CSRF inválido · ' . $m . ' ' . ($_SERVER['REQUEST_URI'] ?? ''), null, null, 'warning');
            self::fail('Token CSRF inválido', 403);
        }
    }
}
