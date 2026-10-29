<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/tienda/CatalogoCargaMasivaService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 400);
    }
    error_log('[tienda-carga-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
Auth::requireModulo('tienda.catalogo');
$carga  = new CatalogoCargaMasivaService();
$actor  = Auth::actor();
