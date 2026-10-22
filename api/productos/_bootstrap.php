<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/productos/ProductoService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), 400); }
    error_log('[productos-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
Auth::requireModulo('oficina.asociados');
$service = new ProductoService();
