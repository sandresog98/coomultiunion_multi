<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/tienda/CatalogoService.php';
require_once $root . '/core/tienda/TiendaClientesService.php';
require_once $root . '/core/tienda/TiendaService.php';
require_once $root . '/core/tienda/TiendaPublicoService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[tienda-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
$cat    = new CatalogoService();
$cli    = new TiendaClientesService();
$tienda = new TiendaService();
$pub    = new TiendaPublicoService();