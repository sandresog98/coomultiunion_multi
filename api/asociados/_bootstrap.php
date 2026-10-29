<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/asociados/AsociadoService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 400); }
    if (Http::isDbUnavailable($e)) {
        error_log('[asociados-api] DB no disponible: ' . $e->getMessage());
        Http::failRetry('Base de datos no disponible', 503, 1);
    }
    error_log('[asociados-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
Auth::requireModulo('oficina.asociados');
$service = new AsociadoService();
