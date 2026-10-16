<?php
declare(strict_types=1);
/** POST /api/portal/auth_cambiar_password.php  { password_actual, password_nueva } → autenticado */
require_once dirname(__DIR__, 2) . '/core/Http.php';
require_once dirname(__DIR__, 2) . '/core/Logger.php';
require_once dirname(__DIR__, 2) . '/core/portal/PortalAuth.php';
require_once dirname(__DIR__, 2) . '/core/Email/EmailCola.php';
require_once dirname(__DIR__, 2) . '/core/Email/EmailTemplates.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[portal-auth] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

Http::requireMethod('POST');
$actorPortal = PortalAuth::actor();
if ($actorPortal === null) {
    Http::fail('No autenticado', 401);
}
Http::csrf();
$in = Http::input();
$actual = (string) ($in['password_actual'] ?? '');
$nueva  = (string) ($in['password_nueva'] ?? '');
if ($actual === '' || $nueva === '') {
    Http::fail('Debe indicar la contraseña actual y la nueva.');
}
if (PortalAuth::changePassword($actual, $nueva)) {
    // Confirmación por email (no rompe el flujo aunque falle el encolado).
    $cedula = (string) ($actorPortal['cedula'] ?? '');
    if ($cedula !== '') {
        $mailAsoc = EmailCola::emailAsociado($cedula);
        if ($mailAsoc) {
            EmailCola::encolar('cuenta', $mailAsoc, 'Contraseña actualizada — Portal de asociados',
                EmailTemplates::cuentaPasswordCambiada((string) ($actorPortal['nombre'] ?? ''), $cedula), null);
        }
    }
    Http::ok(['ok' => true]);
}
Http::fail('La contraseña actual es incorrecta.', 400);