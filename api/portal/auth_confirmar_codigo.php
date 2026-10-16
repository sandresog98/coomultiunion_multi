<?php
declare(strict_types=1);
/** POST /api/portal/auth_confirmar_codigo.php  { cedula, codigo, password }  → PÚBLICO
 *  Valida el código y crea/restablece la contraseña. */
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
$in = Http::input();
$cedula   = trim((string) ($in['cedula'] ?? ''));
$codigo   = trim((string) ($in['codigo'] ?? ''));
$password = (string) ($in['password'] ?? '');
if ($cedula === '' || $codigo === '' || $password === '') {
    Http::fail('Cédula, código y contraseña son obligatorios.');
}
if (PortalAuth::confirmarCodigo($cedula, $codigo, $password)) {
    Http::ok(['ok' => true]);
}
Http::fail('No se pudo actualizar la contraseña.', 400);