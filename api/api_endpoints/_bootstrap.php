<?php
declare(strict_types=1);

/**
 * Bootstrap de la allowlist de endpoints autorizados para token (staff, permiso `api`).
 * Solo sesión de staff (nunca por token).
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/Permisos.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[api-endpoints] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

$staff = Auth::requireModulo('api');
