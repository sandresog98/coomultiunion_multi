<?php
declare(strict_types=1);

/**
 * Bootstrap de la administración de API por tokens (staff, permiso `api`).
 * A propósito NO se llama a TokenAuth::boot(): este módulo se gestiona SOLO con
 * sesión de staff (un token no puede administrar tokens).
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[api-tokens] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

$staff = Auth::requireModulo('api');
