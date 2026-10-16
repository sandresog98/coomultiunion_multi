<?php
declare(strict_types=1);
/** POST /api/portal/auth_login.php  { cedula, password }  → PÚBLICO (sin auth) */
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
$password = (string) ($in['password'] ?? '');
if ($cedula === '' || $password === '') {
    Http::fail('Cédula y contraseña son obligatorias.');
}
$res = PortalAuth::login($cedula, $password);
if ($res === null) {
    Http::fail('Cédula o contraseña incorrectas.', 401);
}
Http::ok([
    'pending' => ($res['pending'] ?? false),
    'cedula'  => $res['cedula'] ?? null,
    'nombre'  => $res['nombre'] ?? null,
]);