<?php
declare(strict_types=1);
/** POST /api/portal/auth_emitir_codigo.php  { cedula }  → PÚBLICO
 *  Emite código de 6 dígitos (crear/restablecer contraseña) y lo envía por email. */
require_once dirname(__DIR__, 2) . '/core/Http.php';
require_once dirname(__DIR__, 2) . '/core/Logger.php';
require_once dirname(__DIR__, 2) . '/core/portal/PortalAuth.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[portal-auth] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

Http::requireMethod('POST');
$cedula = trim((string) (Http::input()['cedula'] ?? ''));
if ($cedula === '') {
    Http::fail('Ingrese su número de cédula.');
}
Http::ok(PortalAuth::emitirCodigo($cedula));