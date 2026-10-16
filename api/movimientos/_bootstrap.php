<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/movimientos/MovimientoService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), 400); }
    error_log('[movimientos-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

Auth::requireModulo('oficina.transacciones');
$service = new MovimientoService();