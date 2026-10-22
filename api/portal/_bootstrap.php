<?php
declare(strict_types=1);
/** Bootstrap del API del PORTAL de asociados (cx/). Requiere asociado autenticado. */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Logger.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/portal/PortalAuth.php';
require_once $root . '/core/portal/PortalService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[portal-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
PortalAuth::requireAsociado();
$portal = new PortalService();
$actor  = PortalAuth::actor();