<?php
declare(strict_types=1);
/** Bootstrap PORTAL — Fondo de Bienestar (cx). Requiere asociado autenticado. */
$root = dirname(__DIR__, 3);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/portal/PortalAuth.php';
require_once $root . '/core/Fondo/FondoService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), 400); }
    error_log('[fondo-portal-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
PortalAuth::requireAsociado();
$fondo = new FondoService();
$cedula = PortalAuth::actor()['cedula'];