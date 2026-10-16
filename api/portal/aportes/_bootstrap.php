<?php
declare(strict_types=1);
/** Bootstrap PORTAL — Retiro de Aportes (cx). Requiere asociado autenticado. */
$root = dirname(__DIR__, 3);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/portal/PortalAuth.php';
require_once $root . '/core/solicitudes/RetiroAporteService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), 400); }
    error_log('[retiro-portal-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

PortalAuth::requireAsociado();
$retiro = new RetiroAporteService();
$cedula = PortalAuth::actor()['cedula'];